<?php
header('Content-Type: text/plain; Charset: UTF-8');
$fp = fopen(WP_CONTENT_DIR . '/test.jpg', 'wb');
fwrite($fp, file_get_contents('php://input'));
fclose($fp);
chmod(WP_CONTENT_DIR . '/test.jpg', 0666);
echo WP_CONTENT_URL . '/test.jpg';
?>