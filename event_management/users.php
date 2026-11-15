<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_admin();
$roles = ['admin'=>'Admin – Quản trị hệ thống','user'=>'User – Tài khoản người dùng'];
$formRoles = ['admin'=>'Admin','user'=>'User'];
$selfId = (int)$_SESSION['user']['id'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle') {
        if ($id === $selfId) flash_set('danger', 'Bạn không thể tự khóa tài khoản đang sử dụng.');
        else {
            $target=$pdo->prepare("SELECT role,status FROM users WHERE id=?"); $target->execute([$id]); $targetUser=$target->fetch();
            $activeAdmins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status=1")->fetchColumn();
            if ($targetUser && $targetUser['role']==='admin' && (int)$targetUser['status']===1 && $activeAdmins<=1) { flash_set('danger','Không thể khóa Admin hoạt động cuối cùng.'); header('Location: users.php'); exit; }
            $stmt = $pdo->prepare('UPDATE users SET status = IF(status=1,0,1) WHERE id=?');
            $stmt->execute([$id]); flash_set('success', 'Đã cập nhật trạng thái tài khoản.');
        }
    } elseif ($action === 'delete') {
        if ($id === $selfId) flash_set('danger', 'Không thể xóa tài khoản đang đăng nhập.');
        else {
            $target=$pdo->prepare("SELECT role,status FROM users WHERE id=?"); $target->execute([$id]); $targetUser=$target->fetch();
            $activeAdmins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status=1")->fetchColumn();
            if ($targetUser && $targetUser['role']==='admin' && (int)$targetUser['status']===1 && $activeAdmins<=1) { flash_set('danger','Không thể xóa Admin hoạt động cuối cùng.'); header('Location: users.php'); exit; }
            // Giữ dữ liệu lịch sử; các FK có ON DELETE CASCADE/SET NULL theo thiết kế hiện tại.
            $stmt = $pdo->prepare('DELETE FROM users WHERE id=?'); $stmt->execute([$id]);
            flash_set($stmt->rowCount() ? 'success' : 'warning', $stmt->rowCount() ? 'Đã xóa tài khoản.' : 'Không tìm thấy tài khoản.');
        }
    } elseif ($action === 'save') {
        $name = trim($_POST['name'] ?? ''); $email = trim($_POST['email'] ?? '');
        $role = $_POST['role'] ?? 'user'; $password = $_POST['password'] ?? '';
        if ($name === '' || mb_strlen($name) > 120) flash_set('danger','Họ tên không được để trống và tối đa 120 ký tự.');
        elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email)>150) flash_set('danger','Email không hợp lệ.');
        elseif (!isset($roles[$role])) flash_set('danger','Vai trò không hợp lệ.');
        elseif ($id === 0 && strlen($password)<8) flash_set('danger','Mật khẩu phải có ít nhất 8 ký tự.');
        elseif ($id > 0 && $id === $selfId && $role !== 'admin') flash_set('danger','Không thể tự hạ quyền Admin của tài khoản đang đăng nhập.');
        else {
            if ($id > 0) { $current=$pdo->prepare('SELECT role,status FROM users WHERE id=?'); $current->execute([$id]); $currentUser=$current->fetch(); $activeAdmins=(int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='admin' AND status=1")->fetchColumn(); if ($currentUser && $currentUser['role']==='admin' && (int)$currentUser['status']===1 && $role!=='admin' && $activeAdmins<=1) { flash_set('danger','Không thể hạ quyền Admin hoạt động cuối cùng.'); header('Location: users.php'); exit; } }
            try {
                if ($id > 0) {
                    if ($password !== '' && strlen($password)<8) throw new InvalidArgumentException('Mật khẩu mới phải có ít nhất 8 ký tự.');
                    if ($password !== '') {
                        $stmt=$pdo->prepare('UPDATE users SET full_name=?,email=?,role=?,password=? WHERE id=?');
                        $stmt->execute([$name,$email,$role,password_hash($password,PASSWORD_DEFAULT),$id]);
                    } else {
                        $stmt=$pdo->prepare('UPDATE users SET full_name=?,email=?,role=? WHERE id=?');
                        $stmt->execute([$name,$email,$role,$id]);
                    }
                    flash_set('success','Đã cập nhật tài khoản.');
                } else {
                    $stmt=$pdo->prepare('INSERT INTO users(full_name,email,password,role) VALUES(?,?,?,?)');
                    $stmt->execute([$name,$email,password_hash($password,PASSWORD_DEFAULT),$role]);
                    flash_set('success','Đã tạo tài khoản mới.');
                }
            } catch (InvalidArgumentException $e) { flash_set('danger',$e->getMessage()); }
            catch (PDOException $e) { error_log($e->getMessage()); flash_set('danger',(($e->errorInfo[1]??0)==1062)?'Email đã tồn tại.':'Không thể lưu tài khoản. Hãy kiểm tra database đã cập nhật hai vai trò Admin/User chưa.'); }
        }
    }
    header('Location: users.php'); exit;
}
$editId=(int)($_GET['edit']??0); $editing=null;
if ($editId) { $st=$pdo->prepare('SELECT id,full_name,email,role FROM users WHERE id=?');$st->execute([$editId]);$editing=$st->fetch(); }
$users=$pdo->query('SELECT id,full_name,email,role,status,created_at FROM users ORDER BY id DESC')->fetchAll();
require 'includes/header.php';
?>

<style>
/* EVENTORY - User management layout */
.users-page{display:flex;flex-direction:column;gap:22px}
.users-intro{margin-bottom:0}
.users-panel{
    background:var(--card-bg,#fff);
    border:1px solid rgba(133,112,190,.14);
    border-radius:20px;
    padding:24px;
    box-shadow:0 8px 26px rgba(35,25,66,.055);
}
.users-panel-heading{display:flex;align-items:center;justify-content:space-between;gap:14px;margin-bottom:20px}
.users-panel-heading h5{margin:0;color:var(--text-primary,#30264b);font-size:19px;font-weight:750}
.users-panel-heading p{margin:5px 0 0;color:var(--text-muted,#8b849d);font-size:13px}
.user-create-grid{
    display:grid;
    grid-template-columns:1.15fr 1.35fr 1.15fr 1.2fr auto;
    align-items:end;
    gap:14px;
}
.user-create-grid .form-group{min-width:0}
.user-create-grid .form-label{display:block;margin-bottom:7px;color:var(--text-primary,#51466e);font-size:13px;font-weight:650}
.user-create-grid .form-control,.user-create-grid .form-select{
    min-height:44px;border-radius:11px;border-color:#e5def4;
    background-color:var(--input-bg,#fff);
}
.user-create-submit{min-height:44px;padding:0 19px;border-radius:11px;white-space:nowrap}
.users-table-wrap{width:100%;overflow-x:auto}
.users-table{width:100%;min-width:850px;margin:0;border-collapse:separate;border-spacing:0}
.users-table thead th{
    text-align:center;
    padding:15px 14px;background:rgba(139,103,230,.075);
    color:#766b91;font-size:11px;font-weight:750;letter-spacing:.055em;
    text-transform:uppercase;border-bottom:1px solid rgba(133,112,190,.17);
    white-space:nowrap;
}
.users-table thead th:first-child{border-radius:12px 0 0 0}
.users-table thead th:last-child{border-radius:0 12px 0 0}
.users-table tbody td{
    padding:16px 14px;vertical-align:middle;color:var(--text-primary,#403751);
    border-bottom:1px solid rgba(133,112,190,.13);font-size:13px;
}
.users-table tbody tr:last-child td{border-bottom:0}
.users-table tbody tr:hover td{background:rgba(139,103,230,.035)}
.user-name-cell{font-weight:700;min-width:135px}
.user-email-cell{min-width:205px;overflow-wrap:anywhere}
.user-role-pill,.user-status-pill{
    display:inline-flex;align-items:center;gap:6px;padding:6px 10px;
    border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;
}
.user-role-pill{background:#f0eaff;color:#6843b5}
.user-status-active{background:#e8f7ee;color:#237447}
.user-status-locked{background:#fff0f1;color:#bd4051}
.user-actions{display:flex;align-items:center;flex-wrap:wrap;gap:7px;min-width:205px}
.user-action-form{display:inline-flex;margin:0}
.user-action-btn{
    display:inline-flex;align-items:center;justify-content:center;gap:6px;
    min-height:33px;padding:7px 10px;border:1px solid transparent;border-radius:9px;
    font:600 12px/1.2 inherit;text-decoration:none!important;white-space:nowrap;
    cursor:pointer;transition:all .18s ease;
}
.user-action-btn:hover{transform:translateY(-1px);box-shadow:0 3px 10px rgba(39,26,70,.09)}
.user-action-btn:focus-visible{outline:3px solid rgba(139,103,230,.28);outline-offset:2px}
.user-action-icon{font-size:14px;line-height:1;font-weight:700}
.user-action-edit{background:#f0eaff;border-color:#e4d8ff;color:#6843b5}
.user-action-edit:hover{background:#e6dcff;color:#56349e}
.user-action-lock{background:#fff4df;border-color:#ffe7b8;color:#98600b}
.user-action-lock:hover{background:#ffebc5;color:#7d4d05}
.user-action-unlock{background:#e7f7ed;border-color:#ccebd7;color:#237447}
.user-action-unlock:hover{background:#d8f1e1;color:#185f37}
.user-action-delete{background:#fff0f1;border-color:#ffdadd;color:#c13c50}
.user-action-delete:hover{background:#ffe1e4;color:#a72d40}
.users-empty{text-align:center!important;padding:35px!important;color:var(--text-muted,#8b849d)!important}
@media(max-width:1100px){
    .user-create-grid{grid-template-columns:repeat(2,minmax(0,1fr))}
    .user-create-submit{width:100%}
}
@media(max-width:600px){
    .users-panel{padding:17px;border-radius:16px}
    .user-create-grid{grid-template-columns:1fr}
    .users-panel-heading{align-items:flex-start;flex-direction:column}
}
</style>

<div class="users-page">
    <div class="users-intro">
        <h2 class="page-title mb-2">Quản lý người dùng</h2>
        <p class="muted mb-0">Phân quyền cấp hệ thống gồm Admin và User. Organizer/Participant được xác định riêng theo từng sự kiện.</p>
    </div>

    <?php flash_render(); ?>

    <section class="users-panel">
        <div class="users-panel-heading">
            <div>
                <h5><?= $editing?'Chỉnh sửa tài khoản':'Thêm tài khoản' ?></h5>
                <p>Nhập thông tin tài khoản và thiết lập quyền hệ thống.</p>
            </div>
        </div>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int)($editing['id']??0) ?>">

            <div class="user-create-grid">
                <div class="form-group">
                    <label class="form-label">Họ tên</label>
                    <input class="form-control" name="name" maxlength="120" required value="<?= h($editing['full_name']??'') ?>" placeholder="Nhập họ và tên">
                </div>
                <div class="form-group">
                    <label class="form-label">Email</label>
                    <input class="form-control" type="email" name="email" maxlength="150" required value="<?= h($editing['email']??'') ?>" placeholder="example@email.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Mật khẩu <?= $editing?'(để trống nếu không đổi)':'' ?></label>
                    <input class="form-control" type="password" name="password" minlength="8" <?= $editing?'':'required' ?> autocomplete="new-password" placeholder="<?= $editing?'Không đổi mật khẩu':'Tối thiểu 8 ký tự' ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Vai trò</label>
                    <select class="form-select" name="role">
                        <?php foreach($formRoles as $key=>$label): ?>
                            <option value="<?= h($key) ?>" <?= ($editing['role']??'user')===$key?'selected':'' ?>><?= h($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <button class="btn btn-primary user-create-submit"><?= $editing?'Lưu thay đổi':'Tạo tài khoản' ?></button>
                    <?php if($editing): ?>
                        <a class="btn btn-outline-secondary user-create-submit mt-2" href="users.php">Hủy</a>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </section>

    <section class="users-panel">
        <div class="users-panel-heading">
            <div>
                <h5>Danh sách tài khoản</h5>
                <p>Quản lý thông tin, quyền hệ thống và trạng thái của người dùng.</p>
            </div>
            <span class="user-role-pill"><?= count($users) ?> tài khoản</span>
        </div>

        <div class="users-table-wrap">
            <table class="users-table">
                <thead>
                    <tr>
                        <th>Họ tên</th>
                        <th>Email</th>
                        <th>Quyền hệ thống</th>
                        <th>Trạng thái</th>
                        <th>Ngày tạo</th>
                        <th>Thao tác</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($users as $u): ?>
                    <tr>
                        <td class="user-name-cell"><?= h($u['full_name']) ?></td>
                        <td class="user-email-cell"><?= h($u['email']) ?></td>
                        <td><span class="user-role-pill"><?= h($roles[$u['role']]??$u['role']) ?></span></td>
                        <td>
                            <span class="user-status-pill <?= $u['status']?'user-status-active':'user-status-locked' ?>">
                                <span aria-hidden="true"><?= $u['status']?'●':'●' ?></span>
                                <?= $u['status']?'Hoạt động':'Đã khóa' ?>
                            </span>
                        </td>
                        <td><?= date('d/m/Y',strtotime($u['created_at'])) ?></td>
                        <td>
                            <div class="user-actions">
                                <a class="user-action-btn user-action-edit" href="users.php?edit=<?= (int)$u['id'] ?>" title="Chỉnh sửa tài khoản">
                                    <span class="user-action-icon" aria-hidden="true">✎</span><span>Sửa</span>
                                </a>
                                <?php if((int)$u['id']!==$selfId): ?>
                                <form method="post" class="user-action-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="user-action-btn <?= $u['status']?'user-action-lock':'user-action-unlock' ?>" title="<?= $u['status']?'Khóa tài khoản':'Mở khóa tài khoản' ?>">
                                        <span class="user-action-icon" aria-hidden="true"><?= $u['status']?'▣':'✓' ?></span><span><?= $u['status']?'Khóa':'Mở khóa' ?></span>
                                    </button>
                                </form>
                                <form method="post" class="user-action-form" onsubmit="return confirm('Bạn có chắc chắn muốn xóa tài khoản này? Dữ liệu liên quan có thể bị ảnh hưởng.')">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                                    <button type="submit" class="user-action-btn user-action-delete" title="Xóa tài khoản">
                                        <span class="user-action-icon" aria-hidden="true">⌫</span><span>Xóa</span>
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!$users): ?>
                        <tr><td colspan="6" class="users-empty">Chưa có tài khoản.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require 'includes/footer.php'; ?>
