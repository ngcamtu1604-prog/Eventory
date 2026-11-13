<?php
// Các hàm dùng chung: chống CSRF và thông báo flash.
// Nạp SAU config/auth.php (file này đã khởi động session).
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="'
         . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8')
         . '">';
}
function csrf_require(): void
{
    $token = $_POST['csrf'] ?? '';
    if (
        !is_string($token)
        || empty($_SESSION['csrf'])
        || !hash_equals($_SESSION['csrf'], $token)
    ) {
        http_response_code(419);
        exit('Phiên làm việc không hợp lệ. Vui lòng tải lại trang và thử lại.');
    }
}
function flash_set(string $type, string $text): void
{
    $_SESSION['flash'] = ['type' => $type, 'text' => $text];
}
function flash_render(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }
    $f = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = in_array($f['type'], ['success', 'danger', 'warning', 'info'], true)
        ? $f['type'] : 'info';
    echo '<div class="alert alert-' . $type . '">'
       . htmlspecialchars($f['text'], ENT_QUOTES, 'UTF-8')
       . '</div>';
}