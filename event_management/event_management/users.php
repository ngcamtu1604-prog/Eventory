<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    // Khóa / mở khóa tài khoản
    if (isset($_POST['toggle'])) {
        $id = (int)$_POST['toggle'];
        if ($id !== (int)$_SESSION['user']['id']) {
            $stmt = $pdo->prepare(
                "UPDATE users SET status = IF(status = 1, 0, 1) WHERE id = ?"
            );
            $stmt->execute([$id]);
            flash_set('success', 'Đã cập nhật trạng thái tài khoản.');
        }
        header('Location: users.php');
        exit;
    }
    // Tạo tài khoản mới
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = $_POST['role'] ?? 'participant';
    if (!in_array($role, ['admin', 'participant'], true)) {
        $role = 'participant';
    }
    if ($name === '' || mb_strlen($name) > 120) {
        flash_set('danger', 'Vui lòng nhập họ tên (tối đa 120 ký tự).');
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash_set('danger', 'Email không hợp lệ.');
    } elseif (strlen($password) < 8) {
        flash_set('danger', 'Mật khẩu phải có ít nhất 8 ký tự.');
    } else {
        try {
            $stmt = $pdo->prepare(
                "INSERT INTO users(full_name,email,password,role)
                 VALUES(?,?,?,?)"
            );
            $stmt->execute([
                $name,
                $email,
                password_hash($password, PASSWORD_DEFAULT),
                $role
            ]);
            flash_set('success', 'Đã tạo tài khoản mới.');
        } catch (PDOException $e) {
            if (($e->errorInfo[1] ?? 0) == 1062) {
                flash_set('danger', 'Email đã tồn tại.');
            } else {
                error_log($e->getMessage());
                flash_set('danger', 'Không thể tạo tài khoản.');
            }
        }
    }
    header('Location: users.php');
    exit;
}
$users = $pdo->query(
    "SELECT id,full_name,email,role,status,created_at
     FROM users ORDER BY id DESC"
)->fetchAll();
require 'includes/header.php';
?>
<h2 class="page-title mb-4">Quản lý người dùng</h2>
<?php flash_render(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card p-3">
            <h5>Thêm tài khoản</h5>
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label">Họ tên</label>
                <input class="form-control mb-3" name="name"
                       maxlength="120" required>
                <label class="form-label">Email</label>
                <input class="form-control mb-3" type="email"
                       name="email" required>
                <label class="form-label">Mật khẩu</label>
                <input class="form-control mb-3" type="password"
                       name="password" minlength="8" required>
                <label class="form-label">Vai trò</label>
                <select class="form-select mb-3" name="role">
                    <option value="participant">Người tham gia</option>
                    <option value="admin">Quản trị viên</option>
                </select>
                <button class="btn btn-primary">Tạo tài khoản</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card p-3">
            <div class="table-responsive">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Họ tên</th>
                            <th>Email</th>
                            <th>Vai trò</th>
                            <th>Trạng thái</th>
                            <th>Ngày tạo</th>
                            <th>Thao tác</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= h($u['full_name']) ?></td>
                            <td><?= h($u['email']) ?></td>
                            <td>
                                <?= $u['role'] === 'admin' ? 'Quản trị' : 'Người tham gia' ?>
                            </td>
                            <td><?= $u['status'] ? 'Hoạt động' : 'Đã khóa' ?></td>
                            <td><?= date('d/m/Y', strtotime($u['created_at'])) ?></td>
                            <td>
                                <?php if ((int)$u['id'] !== (int)$_SESSION['user']['id']): ?>
                                    <form method="post">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="toggle"
                                               value="<?= (int)$u['id'] ?>">
                                        <button class="btn btn-link p-0">
                                            <?= $u['status'] ? 'Khóa' : 'Mở khóa' ?>
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php require 'includes/footer.php'; ?>