
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
                VALUES (?, ?, ?, 'participant')
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
                'role' => 'participant',
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
    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <title>Đăng ký - EVENTORY</title>

    <link rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

    <link rel="stylesheet" href="assets/style.css">
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
            <div class="alert alert-danger">
                <?= h($error) ?>
            </div>
        <?php endif; ?>

        <form method="post">

            <?= csrf_field() ?>

            <label class="form-label">Họ và tên</label>
            <input
                name="name"
                class="form-control mb-3"
                maxlength="120"
                required
                value="<?= h($old['name']) ?>"
            >

            <label class="form-label">Email</label>
            <input
                type="email"
                name="email"
                class="form-control mb-3"
                maxlength="150"
                required
                value="<?= h($old['email']) ?>"
            >

            <label class="form-label">Mật khẩu</label>
            <input
                type="password"
                name="password"
                class="form-control"
                minlength="8"
                required
            >

            <div class="form-text mb-3">
                Mật khẩu tối thiểu 8 ký tự.
            </div>

            <label class="form-label">
                Nhập lại mật khẩu
            </label>

            <input
                type="password"
                name="confirm"
                class="form-control mb-4"
                minlength="8"
                required
            >

            <button class="btn btn-primary w-100">
                Đăng ký
            </button>

        </form>

        <hr>

        <div class="small text-center">
            Đã có tài khoản?
            <a href="login.php">Đăng nhập</a>
        </div>

    </div>
</div>

</body>
</html>
