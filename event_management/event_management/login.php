<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
// Nếu đã đăng nhập thì chuyển về trang chủ
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$error = '';
$maxTries = 5;
$lockSeconds = 60;
// Hết thời gian khóa thì đặt lại bộ đếm đăng nhập sai
if (
    ($_SESSION['login_fail']['n'] ?? 0) >= $maxTries
    && time() - ($_SESSION['login_fail']['t'] ?? 0) >= $lockSeconds
) {
    unset($_SESSION['login_fail']);
}
$locked = ($_SESSION['login_fail']['n'] ?? 0) >= $maxTries;
// XỬ LÝ ĐĂNG NHẬP
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if ($locked) {
        $error = 'Bạn đã nhập sai quá nhiều lần. Vui lòng thử lại sau 1 phút.';
    } else {
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $stmt = $pdo->prepare("
            SELECT *
            FROM users
            WHERE email = ? AND status = 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        // KIỂM TRA EMAIL VÀ MẬT KHẨU
        if ($user && password_verify($password, $user['password'])) {
            // Xóa bộ đếm đăng nhập sai
            unset($_SESSION['login_fail']);
            // Tạo session ID mới
            session_regenerate_id(true);
            // LƯU THÔNG TIN NGƯỜI DÙNG
            $_SESSION['user'] = [
                'id'     => (int)$user['id'],
                'name'   => $user['full_name'],
                'email'  => $user['email'],
                'role'   => $user['role'],
                // BỔ SUNG ẢNH ĐẠI DIỆN
                'avatar' => $user['avatar_path'] ?? ''
            ];
            // Chuyển về trang chủ
            header('Location: index.php');
            exit;
        } else {
            // Tăng số lần đăng nhập sai
            $_SESSION['login_fail'] = [
                'n' => ($_SESSION['login_fail']['n'] ?? 0) + 1,
                't' => time()
            ];
            $error = 'Email hoặc mật khẩu không chính xác.';
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, initial-scale=1">
    <title>Đăng nhập - EVENTORY</title>
    <link rel="stylesheet"
          href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet"
          href="assets/style.css">
</head>
<body>
<div class="login-wrap">
    <div class="card login-card">
        <div class="text-center mb-4">
            <div class="fs-1">✦</div>
            <h3 class="fw-bold">EVENTORY</h3>
            <p class="muted">
                Discover More. Experience More.
            </p>
        </div>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <?= h($error) ?>
            </div>
        <?php endif; ?>
        <form method="post" action="login.php">
            <?= csrf_field() ?>
            <label class="form-label" for="email">
                Email
            </label>
            <input
                id="email"
                type="email"
                name="email"
                class="form-control mb-3"
                autocomplete="username"
                required
                autofocus
            >
            <label class="form-label" for="password">
                Mật khẩu
            </label>
            <input
                id="password"
                type="password"
                name="password"
                class="form-control mb-4"
                autocomplete="current-password"
                required
            >
            <button
                type="submit"
                class="btn btn-primary w-100">
                Đăng nhập
            </button>
        </form>
        <hr>
        <div class="small text-center">
            Chưa có tài khoản?
            <a href="register.php">
                Đăng ký ngay
            </a>
        </div>
    </div>
</div>
</body>
</html>
