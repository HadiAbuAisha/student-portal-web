<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';

$views = ['dashboard', 'students', 'courses', 'enrollments'];
$view = in_array($_GET['view'] ?? '', $views, true) ? $_GET['view'] : 'dashboard';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    check_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'login') {
        $configured = getenv('ADMIN_PASSWORD');
        $submitted = (string)($_POST['password'] ?? '');
        if ($configured && strlen($configured) >= 12 && hash_equals($configured, $submitted)) {
            session_regenerate_id(true); $_SESSION['auth'] = true; redirect();
        }
        flash('Invalid password or admin password not configured (minimum 12 characters).', 'error'); redirect();
    }
    if ($action === 'logout') {
        unset($_SESSION['auth']); session_regenerate_id(true); redirect();
    }
    if (!($_SESSION['auth'] ?? false)) { http_response_code(403); exit('Please sign in.'); }
    try {
        if ($action === 'student-save') {
            $id = ($_POST['id'] ?? '') === '' ? null : positive_id($_POST['id']);
            $code = field('student_code', 20);
            $name = field('full_name', 120);
            $email = field('email', 160);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('Invalid email address.');
            $cohort = field('cohort', 12);
            $status = (string)($_POST['status'] ?? 'Active');
            if (!in_array($status, ['Active', 'On Leave', 'Graduated'], true)) throw new InvalidArgumentException('Invalid status.');
            if ($id !== null) {
                execute('UPDATE students SET student_code=?,full_name=?,email=?,cohort=?,status=? WHERE id=?', [$code,$name,$email,$cohort,$status,$id]);
            } else {
                execute('INSERT INTO students(student_code,full_name,email,cohort,status) VALUES(?,?,?,?,?)', [$code,$name,$email,$cohort,$status]);
            }
            flash('Student record saved.'); redirect('students');
        }
        if ($action === 'student-delete') {
            execute('DELETE FROM students WHERE id=?', [positive_id($_POST['id'] ?? null)]);
            flash('Student record deleted.'); redirect('students');
        }
        if ($action === 'course-save') {
            $id = ($_POST['id'] ?? '') === '' ? null : positive_id($_POST['id']);
            $code = field('code', 18); $title = field('title', 140);
            $credits = filter_var($_POST['credits'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>10]]);
            if ($credits === false) throw new InvalidArgumentException('Credits must be between 1 and 10.');
            if ($id !== null) execute('UPDATE courses SET code=?,title=?,credits=? WHERE id=?', [$code,$title,$credits,$id]);
            else execute('INSERT INTO courses(code,title,credits) VALUES(?,?,?)', [$code,$title,$credits]);
            flash('Course saved.'); redirect('courses');
        }
        if ($action === 'course-delete') {
            execute('DELETE FROM courses WHERE id=?', [positive_id($_POST['id'] ?? null)]);
            flash('Course deleted.'); redirect('courses');
        }
        if ($action === 'enrollment-save') {
            $studentId = positive_id($_POST['student_id'] ?? null);
            $courseId = positive_id($_POST['course_id'] ?? null);
            $rawGrade = trim((string)($_POST['grade'] ?? ''));
            $grade = null;
            if ($rawGrade !== '') {
                if (!is_numeric($rawGrade) || (float)$rawGrade < 0 || (float)$rawGrade > 100) throw new InvalidArgumentException('Grade must be between 0 and 100.');
                $grade = round((float)$rawGrade, 2);
            }
            execute('INSERT INTO enrollments(student_id,course_id,grade) VALUES(?,?,?) ON DUPLICATE KEY UPDATE grade=VALUES(grade)', [$studentId,$courseId,$grade]);
            flash('Enrollment saved.'); redirect('enrollments');
        }
        if ($action === 'enrollment-delete') {
            execute('DELETE FROM enrollments WHERE id=?', [positive_id($_POST['id'] ?? null)]);
            flash('Enrollment deleted.'); redirect('enrollments');
        }
        throw new InvalidArgumentException('Unknown action.');
    } catch (InvalidArgumentException $e) {
        flash($e->getMessage(), 'error'); redirect($view);
    } catch (PDOException $e) {
        error_log($e->getMessage()); flash('Unable to save. Check that the code/email is unique and the database is running.', 'error'); redirect($view);
    }
}

$isAuthed = (bool)($_SESSION['auth'] ?? false);
$flash = $_SESSION['flash'] ?? null; unset($_SESSION['flash']);
$stats = [];
$students = $courses = $enrollments = [];
$selected = null;
$error = null;
if ($isAuthed) {
    try {
        if ($view === 'dashboard') {
            $stats['students'] = (int)(one('SELECT COUNT(*) AS n FROM students')['n'] ?? 0);
            $stats['courses'] = (int)(one('SELECT COUNT(*) AS n FROM courses')['n'] ?? 0);
            $stats['enrollments'] = (int)(one('SELECT COUNT(*) AS n FROM enrollments')['n'] ?? 0);
            $stats['average'] = (float)(one('SELECT ROUND(AVG(grade),1) AS n FROM enrollments WHERE grade IS NOT NULL')['n'] ?? 0);
            $stats['byCourse'] = all('SELECT c.code,c.title,COUNT(e.id) AS total FROM courses c LEFT JOIN enrollments e ON e.course_id=c.id GROUP BY c.id,c.code,c.title ORDER BY total DESC,c.code');
            $stats['recent'] = all('SELECT s.student_code,s.full_name,s.status,s.cohort FROM students s ORDER BY s.created_at DESC,s.id DESC LIMIT 5');
        }
        if ($view === 'students') {
            $students = all('SELECT * FROM students ORDER BY id DESC');
            if (isset($_GET['edit'])) $selected = one('SELECT * FROM students WHERE id=?',[positive_id($_GET['edit'])]);
        }
        if ($view === 'courses') {
            $courses = all('SELECT c.*,COUNT(e.id) AS enrollment_count FROM courses c LEFT JOIN enrollments e ON c.id=e.course_id GROUP BY c.id,c.code,c.title,c.credits ORDER BY c.code');
            if (isset($_GET['edit'])) $selected = one('SELECT * FROM courses WHERE id=?',[positive_id($_GET['edit'])]);
        }
        if ($view === 'enrollments') {
            $students = all('SELECT id,student_code,full_name FROM students ORDER BY full_name');
            $courses = all('SELECT id,code,title FROM courses ORDER BY code');
            $enrollments = all('SELECT e.id,s.student_code,s.full_name,c.code,c.title,e.grade FROM enrollments e JOIN students s ON e.student_id=s.id JOIN courses c ON e.course_id=c.id ORDER BY e.id DESC');
        }
    } catch (Throwable $e) {
        error_log($e->getMessage()); $error = 'Database is unavailable. Check the Docker Compose setup.';
    }
}
function token_field(): void { echo '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function delete_form(string $action, int $id, string $confirm): void {
    echo '<form method="post" class="inline-form" data-confirm="'.e($confirm).'">'; token_field();
    echo '<input type="hidden" name="action" value="'.e($action).'"><input type="hidden" name="id" value="'.$id.'"><button class="text-danger" type="submit">Delete</button></form>';
}
?><!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>CampusFlow · Student Management</title><link rel="stylesheet" href="/assets/styles.css"><script defer src="/assets/app.js"></script></head>
<body>
<?php if (!$isAuthed): ?>
<main class="sign-in-layout"><div class="sign-in-panel"><div class="brand-icon">CF</div><p class="eyebrow">CAMPUSFLOW · PORTFOLIO DEMO</p><h1>Make academic work simpler.</h1><p class="muted">A full-stack student, course and enrollment manager built with PHP and MySQL.</p><div class="security-note">This demo uses only fictional sample student records. Run it locally; it is not a production student information system.</div></div><div class="sign-in-card"><h2>Admin sign in</h2><p class="muted">Enter the admin password you configured in your <code>.env</code> file.</p>
<?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<form method="post"><?php token_field(); ?><input type="hidden" name="action" value="login"><label for="password">Admin password</label><input id="password" type="password" name="password" autocomplete="current-password" required><button class="button" type="submit">Sign in →</button></form></div></main>
<?php else: ?>
<div class="app-shell"><aside class="sidebar"><div class="brand"><span class="brand-icon">CF</span><span>CampusFlow<small>Academic admin suite</small></span></div><nav aria-label="Main navigation">
<?php foreach(['dashboard'=>'Overview','students'=>'Students','courses'=>'Courses','enrollments'=>'Enrollments'] as $name=>$label): ?>
<a class="nav-link <?= $view===$name?'active':'' ?>" href="/?view=<?= e($name) ?>"><span class="nav-dot"></span><?= e($label) ?></a>
<?php endforeach; ?></nav><div class="sidebar-footer"><strong>Demo environment</strong><p>No real student information.</p><form method="post"><?php token_field(); ?><input type="hidden" name="action" value="logout"><button class="logout" type="submit">Log out ↗</button></form></div></aside>
<main class="content"><header class="topbar"><div><p class="eyebrow">ACADEMIC ADMINISTRATION / <?= strtoupper(e($view)) ?></p><h1><?= e(['dashboard'=>'Dashboard','students'=>'Student directory','courses'=>'Course catalogue','enrollments'=>'Course enrollments'][$view]) ?></h1></div><span class="avatar" aria-label="Administrator">AD</span></header>
<?php if ($flash): ?><div class="notice <?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($error): ?><div class="notice error"><?= e($error) ?></div><?php else: ?>
<?php if ($view==='dashboard'): ?>
<div class="metric-grid"><article class="metric"><span>Registered students</span><strong><?= $stats['students'] ?></strong><small>All student records</small></article><article class="metric"><span>Available courses</span><strong><?= $stats['courses'] ?></strong><small>Current catalogue</small></article><article class="metric"><span>Enrollments</span><strong><?= $stats['enrollments'] ?></strong><small>Student-course links</small></article><article class="metric"><span>Average grade</span><strong><?= number_format($stats['average'],1) ?></strong><small>Graded enrollments only</small></article></div>
<div class="two-columns"><section class="panel"><div class="panel-heading"><div><h2>Course participation</h2><p>Enrollment volume by course</p></div></div>
<?php $max = max(array_map(fn($r)=>(int)$r['total'], $stats['byCourse']) ?: [1]); foreach($stats['byCourse'] as $row): ?>
<div class="bar-line"><div class="bar-label"><strong><?= e($row['code']) ?></strong><span><?= (int)$row['total'] ?> students</span></div><div class="bar-track"><div class="bar-fill" style="width:<?= $max>0?(int)round(100*(int)$row['total']/$max):0 ?>%"></div></div></div>
<?php endforeach; ?></section><section class="panel"><div class="panel-heading"><div><h2>Recently added students</h2><p>Latest records in this local demo</p></div><a class="small-link" href="/?view=students">View all</a></div>
<?php foreach($stats['recent'] as $row): ?><div class="person-row"><div class="person-avatar"><?= e(strtoupper(substr($row['full_name'],0,1))) ?></div><div><strong><?= e($row['full_name']) ?></strong><small><?= e($row['student_code']) ?> · <?= e($row['cohort']) ?></small></div><span class="status"><?= e($row['status']) ?></span></div><?php endforeach; ?></section></div>
<?php elseif ($view==='students'): ?>
<div class="two-columns table-layout"><section class="panel"><div class="panel-heading"><div><h2>Students</h2><p><?= count($students) ?> records</p></div><input class="search" type="search" aria-label="Filter students" data-filter="student-table" placeholder="Search students..."></div><div class="table-scroll"><table id="student-table"><thead><tr><th>ID</th><th>Student</th><th>Cohort</th><th>Status</th><th>Actions</th></tr></thead><tbody>
<?php foreach ($students as $row): ?><tr><td><code><?= e($row['student_code']) ?></code></td><td><strong><?= e($row['full_name']) ?></strong><small><?= e($row['email']) ?></small></td><td><?= e($row['cohort']) ?></td><td><span class="status"><?= e($row['status']) ?></span></td><td class="actions"><a href="/?view=students&amp;edit=<?= (int)$row['id'] ?>">Edit</a><?php delete_form('student-delete',(int)$row['id'],'Delete this student and their enrollments?'); ?></td></tr><?php endforeach; ?>
</tbody></table></div></section><section class="panel form-panel"><h2><?= $selected?'Edit student':'Add new student' ?></h2><p>Use fictional student data in this demo.</p><form method="post" class="field-stack"><?php token_field(); ?><input type="hidden" name="action" value="student-save"><input type="hidden" name="id" value="<?= e($selected['id']??'') ?>"><label>Student ID<input name="student_code" required maxlength="20" value="<?= e($selected['student_code']??'') ?>" placeholder="ST-1009"></label><label>Full name<input name="full_name" required maxlength="120" value="<?= e($selected['full_name']??'') ?>"></label><label>Email<input type="email" name="email" required maxlength="160" value="<?= e($selected['email']??'') ?>" placeholder="person@example.test"></label><label>Cohort<input name="cohort" required maxlength="12" value="<?= e($selected['cohort']??'2026') ?>"></label><label>Status<select name="status"><?php foreach (['Active','On Leave','Graduated'] as $status): ?><option <?= ($selected['status']??'Active')===$status?'selected':'' ?>><?= e($status) ?></option><?php endforeach; ?></select></label><button class="button" type="submit">Save student</button></form></section></div>
<?php elseif ($view==='courses'): ?>
<div class="two-columns table-layout"><section class="panel"><div class="panel-heading"><div><h2>Courses</h2><p><?= count($courses) ?> records</p></div><input class="search" type="search" aria-label="Filter courses" data-filter="course-table" placeholder="Search courses..."></div><div class="table-scroll"><table id="course-table"><thead><tr><th>Code</th><th>Course name</th><th>Credits</th><th>Enrolled</th><th>Actions</th></tr></thead><tbody><?php foreach($courses as $row): ?><tr><td><code><?= e($row['code']) ?></code></td><td><strong><?= e($row['title']) ?></strong></td><td><?= (int)$row['credits'] ?></td><td><?= (int)$row['enrollment_count'] ?></td><td class="actions"><a href="/?view=courses&amp;edit=<?= (int)$row['id'] ?>">Edit</a><?php delete_form('course-delete',(int)$row['id'],'Delete this course and its enrollments?'); ?></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel form-panel"><h2><?= $selected?'Edit course':'Add course' ?></h2><p>Update your academic catalogue.</p><form method="post" class="field-stack"><?php token_field(); ?><input type="hidden" name="action" value="course-save"><input type="hidden" name="id" value="<?= e($selected['id']??'') ?>"><label>Course code<input required maxlength="18" name="code" value="<?= e($selected['code']??'') ?>" placeholder="CS350"></label><label>Course title<input required maxlength="140" name="title" value="<?= e($selected['title']??'') ?>"></label><label>Credits<input type="number" name="credits" required min="1" max="10" value="<?= e($selected['credits']??'3') ?>"></label><button class="button" type="submit">Save course</button></form></section></div>
<?php elseif ($view==='enrollments'): ?>
<div class="two-columns table-layout"><section class="panel"><div class="panel-heading"><div><h2>Enrollments</h2><p><?= count($enrollments) ?> records</p></div><input class="search" type="search" aria-label="Filter enrollments" data-filter="enrollment-table" placeholder="Search enrollments..."></div><div class="table-scroll"><table id="enrollment-table"><thead><tr><th>Student</th><th>Course</th><th>Grade</th><th>Action</th></tr></thead><tbody><?php foreach($enrollments as $row): ?><tr><td><strong><?= e($row['full_name']) ?></strong><small><?= e($row['student_code']) ?></small></td><td><?= e($row['code']) ?></td><td><?= $row['grade']===null?'In progress':number_format((float)$row['grade'],2) ?></td><td><?php delete_form('enrollment-delete',(int)$row['id'],'Remove this course enrollment?'); ?></td></tr><?php endforeach; ?></tbody></table></div></section><section class="panel form-panel"><h2>Assign or update grade</h2><p>If the student is already enrolled, this updates their grade.</p><form method="post" class="field-stack"><?php token_field(); ?><input type="hidden" name="action" value="enrollment-save"><label>Student<select name="student_id" required><?php foreach($students as $row): ?><option value="<?= (int)$row['id'] ?>"><?= e($row['full_name']) ?> (<?= e($row['student_code']) ?>)</option><?php endforeach; ?></select></label><label>Course<select name="course_id" required><?php foreach($courses as $row): ?><option value="<?= (int)$row['id'] ?>"><?= e($row['code']) ?> — <?= e($row['title']) ?></option><?php endforeach; ?></select></label><label>Final grade <small>(optional)</small><input type="number" name="grade" min="0" max="100" step="0.01" placeholder="Leave blank if in progress"></label><button class="button" type="submit">Save enrollment</button></form></section></div>
<?php endif; endif; ?></main></div>
<?php endif; ?></body></html>
