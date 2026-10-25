<?php
declare(strict_types=1);
header('Cache-Control: no-store, private');
header('X-Robots-Tag: noindex, nofollow');
header('Location: /test/login.php', true, 303);
exit;
