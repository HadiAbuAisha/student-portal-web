CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_code VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(120) NOT NULL,
  email VARCHAR(160) NOT NULL UNIQUE,
  cohort VARCHAR(12) NOT NULL,
  status ENUM('Active','On Leave','Graduated') NOT NULL DEFAULT 'Active',
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE TABLE IF NOT EXISTS courses (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  code VARCHAR(18) NOT NULL UNIQUE,
  title VARCHAR(140) NOT NULL,
  credits TINYINT UNSIGNED NOT NULL
);
CREATE TABLE IF NOT EXISTS enrollments (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  course_id INT UNSIGNED NOT NULL,
  grade DECIMAL(5,2) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY student_course (student_id, course_id),
  FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
  FOREIGN KEY (course_id) REFERENCES courses(id) ON DELETE CASCADE
);
-- All demonstration persons below are fictional.
INSERT INTO students (student_code,full_name,email,cohort,status) VALUES
('ST-1001','Alex Carter','alex.carter@example.test','2023','Active'),
('ST-1002','Maya Brooks','maya.brooks@example.test','2024','Active'),
('ST-1003','Noor Salem','noor.salem@example.test','2024','Active'),
('ST-1004','Sam Taylor','sam.taylor@example.test','2025','Active'),
('ST-1005','Lina Malik','lina.malik@example.test','2025','Active'),
('ST-1006','Ryan Lee','ryan.lee@example.test','2023','On Leave'),
('ST-1007','Sara Khan','sara.khan@example.test','2024','Active'),
('ST-1008','Omar Faris','omar.faris@example.test','2025','Active');
INSERT INTO courses (code,title,credits) VALUES
('CS101','Programming Fundamentals',3),('CS201','Data Structures',3),
('CS220','Database Systems',3),('CS230','Web Development',3),
('CS310','Algorithms',3),('CS340','Software Engineering',3);
INSERT INTO enrollments(student_id,course_id,grade) VALUES
(1,1,92),(1,2,89),(2,1,95),(2,4,93),(3,1,90),(3,3,86),
(4,1,NULL),(4,4,NULL),(5,1,88),(5,5,NULL),(6,2,73),(7,3,97),(8,4,84);
