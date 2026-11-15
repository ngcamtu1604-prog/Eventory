
<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';

if (!empty($_SESSION['user'])) {
    header('Location: index.php');
    exit;
}

$error = '';
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();

    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm'] ?? '';

    $old = ['name' => $name, 'email' => $email];

    if ($name === '' || mb_strlen($name) > 120) {
        $error = 'Vui lòng nhập họ tên hợp lệ.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 150) {
        $error = 'Email không hợp lệ.';
    } elseif (strlen($password) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } elseif ($password !== $confirm) {
        $error = 'Mật khẩu nhập lại không khớp.';
    } else {
        try {
            // Tạo tài khoản với avatar mặc định
            $stmt = $pdo->prepare("
                INSERT INTO users
                (full_name, email, password, role)
                VALUES (?, ?, ?, 'user')
            ");

            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT)
            ]);

            $userId = (int)$pdo->lastInsertId();

            session_regenerate_id(true);

            $_SESSION['user'] = [
                'id' => $userId,
                'name' => $name,
                'email' => $email,
                'role' => 'user',
                'avatar' => ''
            ];

            flash_set(
                'success',
                'Tạo tài khoản thành công!'
            );

            header('Location: events.php');
            exit;

        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) == 1062) {
                $error = 'Email này đã được sử dụng.';
            } else {
                error_log($e->getMessage());
                $error = 'Không thể tạo tài khoản. Vui lòng thử lại.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Đăng ký - EVENTORY</title>
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
            <span class="auth-eyebrow">YOUR EVENT JOURNEY STARTS HERE</span>
            <h1>Mỗi sự kiện.<br>Một trải nghiệm đáng nhớ.</h1>
            <p>Tạo tài khoản để khám phá, đăng ký và tự tổ chức những sự kiện mang dấu ấn của riêng bạn.</p>
            <div class="auth-feature-cards">
                <div class="auth-feature-card auth-feature-one"><span>✦</span><strong>Discover</strong><small>Khám phá</small></div>
                <div class="auth-feature-card auth-feature-two"><span>♡</span><strong>Connect</strong><small>Kết nối</small></div>
                <div class="auth-feature-card auth-feature-three"><span>✓</span><strong>Experience</strong><small>Trải nghiệm</small></div>
            </div>
        </div>
        <small class="auth-copyright">EVENTORY Management System © 2026</small>
    </section>
    <main class="auth-form-panel">
        <div class="auth-form-inner auth-register-inner">
            <span class="auth-eyebrow auth-eyebrow-form">CREATE YOUR ACCOUNT</span>
            <h2>Tạo tài khoản</h2>
            <p class="auth-subtitle">Tham gia EVENTORY và bắt đầu hành trình sự kiện của bạn.</p>
            <?php if ($error): ?>
                <div class="alert alert-danger" role="alert"><?= h($error) ?></div>
            <?php endif; ?>
            <form method="post" class="auth-form">
                <?= csrf_field() ?>
                <label class="form-label" for="name">Họ và tên</label>
                <input id="name" name="name" class="form-control" maxlength="120" placeholder="Nhập họ và tên" required value="<?= h($old['name']) ?>">
                <label class="form-label" for="email">Địa chỉ email</label>
                <input id="email" type="email" name="email" class="form-control" maxlength="150" placeholder="Nhập email của bạn" required value="<?= h($old['email']) ?>">
                <label class="form-label" for="password">Mật khẩu</label>
                <input id="password" type="password" name="password" class="form-control" minlength="8" placeholder="Tối thiểu 8 ký tự" required>
                <label class="form-label" for="confirm">Nhập lại mật khẩu</label>
                <input id="confirm" type="password" name="confirm" class="form-control" minlength="8" placeholder="Nhập lại mật khẩu" required>
                <button type="submit" class="btn auth-submit">Đăng ký <span>→</span></button>
            </form>
            <div class="auth-switch"><span>EVENTORY</span></div>
            <p class="auth-bottom-note">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
            <p class="auth-security-note">♡ Thông tin của bạn được bảo vệ an toàn</p>
        </div>
    </main>
</div>
<script src="assets/app.js?v=2"></script>
</body>
</html>
