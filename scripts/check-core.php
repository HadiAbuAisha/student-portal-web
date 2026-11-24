<?php
declare(strict_types=1);
require __DIR__ . '/../app/bootstrap.php';
assert(e('<script>') === '&lt;script&gt;');
assert(e(123) === '123');
assert(positive_id('3') === 3);
try { positive_id('0'); throw new RuntimeException('Validation missing'); }
catch (InvalidArgumentException $e) {}
$_POST = ['full_name' => '  Demo Student  '];
assert(field('full_name') === 'Demo Student');
assert(strlen(csrf()) === 48);
echo "Core helpers passed (escaping, IDs, form fields, token).\n";
