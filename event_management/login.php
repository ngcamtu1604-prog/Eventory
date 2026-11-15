<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
// Nếu đã đăng nhập thì chuyển về trang chủ
if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}
$error = (($_GET['account'] ?? '') === 'disabled') ? 'Tài khoản của bạn đã bị khóa hoặc không còn tồn tại. Vui lòng liên hệ Admin.' : '';
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
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng nhập - EVENTORY</title>
    <script>
    (function () {
        try {
            var theme = localStorage.getItem('eventory_theme') || 'light';
            document.documentElement.setAttribute('data-theme', theme);
        } catch (e) {
            document.documentElement.setAttribute('data-theme', 'light');
        }
    })();
    </script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body class="auth-page">
<div class="auth-shell">
    <section class="auth-visual">
        <a class="auth-brand" href="login.php"><span class="auth-brand-mark">▦</span><span>EVENTORY</span></a>
        <div class="auth-visual-content">
            <span class="auth-eyebrow">EVENT MANAGEMENT PLATFORM</span>
            <h1>Kết nối sự kiện.<br>Kiến tạo trải nghiệm.</h1>
            <p>Nền tảng quản lý sự kiện trực tuyến, kết nối con người và tối ưu mọi hoạt động tổ chức.</p>
            <div class="auth-feature-cards">
                <div class="auth-feature-card auth-feature-one"><span>✦</span><strong>Plan</strong><small>Lên kế hoạch</small></div>
                <div class="auth-feature-card auth-feature-two"><span>♡</span><strong>Connect</strong><small>Kết nối mọi người</small></div>
                <div class="auth-feature-card auth-feature-three"><span>✓</span><strong>Manage</strong><small>Quản lý hiệu quả</small></div>
            </div>
        </div>
        <small class="auth-copyright">EVENTORY Management System © 2026</small>
    </section>
    <main class="auth-form-panel">
        <div class="auth-form-inner">
            <span class="auth-eyebrow auth-eyebrow-form">WELCOME BACK</span>
            <h2>Chào mừng trở lại!</h2>
            <p class="auth-subtitle">Đăng nhập để tiếp tục sử dụng EVENTORY.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" action="login.php" class="auth-form">
                <?= csrf_field() ?>
                <label class="form-label" for="email">Địa chỉ email</label>
                <input id="email" type="email" name="email" class="form-control" placeholder="Nhập email của bạn" autocomplete="username" required autofocus>
                <div class="auth-password-label">
                    <label class="form-label mb-0" for="password">Mật khẩu</label>
                    <a href="#" class="auth-muted-link" onclick="return false;">Quên mật khẩu?</a>
                </div>
                <input id="password" type="password" name="password" class="form-control" placeholder="Nhập mật khẩu" autocomplete="current-password" required>
                <label class="auth-remember"><input type="checkbox" name="remember"> <span>Ghi nhớ đăng nhập</span></label>
                <button type="submit" class="btn auth-submit">Đăng nhập <span>→</span></button>
            </form>
            <div class="auth-switch"><span>EVENTORY</span></div>
            <p class="auth-bottom-note">Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
            <p class="auth-security-note">♡ Hệ thống quản lý sự kiện an toàn và chuyên nghiệp</p>
        </div>
    </main>
</div>
<script src="assets/app.js?v=2"></script>
</body>
</html>
