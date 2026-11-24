<?php
require __DIR__.'/../app/bootstrap.php';
assert(e('<script>alert("hi")</script>') === '&lt;script&gt;alert(&quot;hi&quot;)&lt;/script&gt;');
assert(strlen(csrf()) === 48);
assert(positive_id('19') === 19);
try {positive_id('-1'); throw new RuntimeException('Expected input rejection');} catch (InvalidArgumentException $e) {}
print("PHP helper assertions passed.\n");
