<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Kiểm tra đăng nhập
function require_login() {
    if (empty($_SESSION['user'])) {
        header('Location: login.php');
        exit;
    }
}
// Kiểm tra quyền quản trị
function require_admin() {
    require_login();
    if ($_SESSION['user']['role'] !== 'admin') {
        http_response_code(403);
        exit('Bạn không có quyền truy cập chức năng này.');
    }
}
// Bảo vệ dữ liệu hiển thị
function h($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}
?>