<?php
require_once 'config/auth.php';
// Xóa dữ liệu phiên đăng nhập.
$_SESSION = [];
// Xóa cookie phiên trên trình duyệt nếu PHP đang sử dụng cookie.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}
session_destroy();
header('Location: login.php');
exit;
?>