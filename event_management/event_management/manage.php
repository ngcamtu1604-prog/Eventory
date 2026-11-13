<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_admin();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    if (isset($_POST['delete_speaker'])) {
        $stmt = $pdo->prepare("DELETE FROM speakers WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_speaker']]);
        flash_set('success', 'Đã xóa diễn giả.');
    } elseif (isset($_POST['delete_organizer'])) {
        $stmt = $pdo->prepare("DELETE FROM organizers WHERE id = ?");
        $stmt->execute([(int)$_POST['delete_organizer']]);
        flash_set('success', 'Đã xóa ban tổ chức.');
    } else {
        $name = trim($_POST['name'] ?? '');
        $info = trim($_POST['info'] ?? '');
        $type = $_POST['type'] ?? '';

        if ($name === '' || mb_strlen($name) > 150) {
            flash_set('danger', 'Vui lòng nhập tên (tối đa 150 ký tự).');
        } elseif ($type === 'speaker') {
            $stmt = $pdo->prepare("INSERT INTO speakers(name,info) VALUES(?,?)");
            $stmt->execute([$name, $info]);
            flash_set('success', 'Đã thêm diễn giả.');
        } elseif ($type === 'organizer') {
            $stmt = $pdo->prepare("INSERT INTO organizers(name,info) VALUES(?,?)");
            $stmt->execute([$name, $info]);
            flash_set('success', 'Đã thêm ban tổ chức.');
        }
    }
    header('Location: manage.php');
    exit;
}
$speakers = $pdo->query("SELECT * FROM speakers ORDER BY id DESC")->fetchAll();
$organizers = $pdo->query("SELECT * FROM organizers ORDER BY id DESC")->fetchAll();
require 'includes/header.php';
?>
<h2 class="page-title mb-4">Quản lý diễn giả và ban tổ chức</h2>
<?php flash_render(); ?>
<div class="row g-3">
    <div class="col-lg-4">
        <div class="card p-3">
            <h5>Thêm thông tin</h5>
            <form method="post">
                <?= csrf_field() ?>
                <label class="form-label">Loại thông tin</label>
                <select class="form-select mb-3" name="type">
                    <option value="speaker">Diễn giả</option>
                    <option value="organizer">Ban tổ chức</option>
                </select>
                <label class="form-label">Tên</label>
                <input class="form-control mb-3" name="name"
                       maxlength="150" required>
                <label class="form-label">Thông tin mô tả</label>
                <textarea class="form-control mb-3" name="info" rows="3"></textarea>
                <button class="btn btn-primary">Thêm mới</button>
            </form>
        </div>
    </div>
    <div class="col-lg-8">
        <div class="card p-3 mb-3">
            <h5>Danh sách diễn giả</h5>
            <table class="table">
                <thead>
                    <tr><th>Tên diễn giả</th><th>Thông tin</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($speakers as $s): ?>
                    <tr>
                        <td><?= h($s['name']) ?></td>
                        <td><?= h($s['info']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirmDelete()">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_speaker"
                                       value="<?= (int)$s['id'] ?>">
                                <button class="btn btn-link text-danger p-0">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$speakers): ?>
                    <tr><td colspan="3" class="empty">Chưa có diễn giả.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card p-3">
            <h5>Danh sách ban tổ chức</h5>
            <table class="table">
                <thead>
                    <tr><th>Tên đơn vị</th><th>Thông tin</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($organizers as $o): ?>
                    <tr>
                        <td><?= h($o['name']) ?></td>
                        <td><?= h($o['info']) ?></td>
                        <td>
                            <form method="post" onsubmit="return confirmDelete()">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_organizer"
                                       value="<?= (int)$o['id'] ?>">
                                <button class="btn btn-link text-danger p-0">Xóa</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$organizers): ?>
                    <tr><td colspan="3" class="empty">Chưa có ban tổ chức.</td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require 'includes/footer.php'; ?>