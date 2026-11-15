
<?php
// =====================================================
// EVENTORY - XÁC THỰC VÀ PHÂN QUYỀN NGƯỜI DÙNG
// =====================================================

// Khởi tạo Session
if (session_status() === PHP_SESSION_NONE) {
    $secure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}

// Nạp kết nối Database ở phạm vi toàn cục
require_once __DIR__ . '/database.php';


// =====================================================
// 1. KIỂM TRA ĐĂNG NHẬP VÀ TRẠNG THÁI TÀI KHOẢN
// =====================================================

function require_login(): void {

    global $pdo;

    // Kiểm tra Session đăng nhập
    if (empty($_SESSION['user']['id'])) {
        header('Location: login.php');
        exit;
    }

    // Kiểm tra kết nối Database
    if (!$pdo instanceof PDO) {
        die('Lỗi kết nối cơ sở dữ liệu.');
    }

    // Lấy thông tin tài khoản từ Database
    $stmt = $pdo->prepare(
        'SELECT id, full_name, email, role, status, avatar_path
         FROM users
         WHERE id = ?
         LIMIT 1'
    );

    $stmt->execute([
        (int) $_SESSION['user']['id']
    ]);

    $account = $stmt->fetch();

    // Nếu tài khoản không tồn tại hoặc đã bị khóa
    if (!$account || (int)$account['status'] !== 1) {

        $_SESSION = [];

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

        header('Location: login.php?account=disabled');
        exit;
    }

    // Đồng bộ thông tin tài khoản từ Database vào Session
    $_SESSION['user']['id'] = (int)$account['id'];
    $_SESSION['user']['name'] = $account['full_name'];
    $_SESSION['user']['email'] = $account['email'];
    $_SESSION['user']['role'] = $account['role'];
    $_SESSION['user']['avatar'] = $account['avatar_path'] ?? '';
}


// =====================================================
// 2. KIỂM TRA QUYỀN TRUY CẬP
// =====================================================

function require_role(array $roles): void {

    require_login();

    $role = $_SESSION['user']['role'] ?? '';

    if (!in_array($role, $roles, true)) {

        http_response_code(403);

        exit('Bạn không có quyền truy cập chức năng này.');
    }
}


// =====================================================
// 3. KIỂM TRA QUYỀN ADMIN
// =====================================================

function require_admin(): void {
    require_role(['admin']);
}


// =====================================================
// 4. LẤY VAI TRÒ HIỆN TẠI
// =====================================================

function current_user_role(): string {

    return (string)($_SESSION['user']['role'] ?? '');
}


// =====================================================
// 5. KIỂM TRA ADMIN
// =====================================================

function is_admin(): bool {

    return current_user_role() === 'admin';
}


// =====================================================
// 6. LÀM SẠCH DỮ LIỆU HIỂN THỊ
// =====================================================

function h($value): string {

    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

?>
