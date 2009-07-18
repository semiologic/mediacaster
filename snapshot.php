<?php
status_header(200);
header('Content-Type: text/plain; Charset: UTF-8');

#var_dump($match);die;
@unlink(WP_CONTENT_DIR . '/test.jpg');
$fp = fopen(WP_CONTENT_DIR . '/test.jpg', 'wb');
fwrite($fp, file_get_contents('php://input'));
fclose($fp);
chmod(WP_CONTENT_DIR . '/test.jpg', 0666);
echo WP_CONTENT_URL . '/test.jpg';
?>