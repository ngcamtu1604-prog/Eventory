
<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_login();
$userId = (int)$_SESSION['user']['id'];
$avatarDir = __DIR__ . '/uploads/avatars/';
$avatarUrl = 'uploads/avatars/';
// Lấy thông tin tài khoản
function getAccountUser($pdo, $userId) {
    $stmt = $pdo->prepare("
        SELECT id, full_name, email, role, avatar_path, created_at
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}
// Xóa ảnh avatar cũ
function deleteAccountAvatar($path, $dir) {
    if (!$path || strpos($path, 'uploads/avatars/') !== 0) {
        return;
    }
    $file = $dir . basename($path);
    if (is_file($file)) {
        unlink($file);
    }
}
$user = getAccountUser($pdo, $userId);
if (!$user) {
    session_destroy();
    header('Location: login.php');
    exit;
}
// XỬ LÝ FORM
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    // 1. CẬP NHẬT HỌ TÊN
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '' || mb_strlen($name, 'UTF-8') > 120) {
            flash_set('danger', 'Họ tên không hợp lệ.');
        } else {
            $stmt = $pdo->prepare("
                UPDATE users
                SET full_name = ?
                WHERE id = ?
            ");
            $stmt->execute([$name, $userId]);
            $_SESSION['user']['name'] = $name;
            flash_set('success', 'Cập nhật thông tin thành công.');
        }
    }
    // 2. UPLOAD AVATAR
    elseif ($action === 'avatar') {
        $file = $_FILES['avatar'] ?? null;
        if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
            flash_set('danger', 'Vui lòng chọn ảnh hợp lệ.');
        } elseif ($file['size'] > 3 * 1024 * 1024) {
            flash_set('danger', 'Ảnh không được vượt quá 3MB.');
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($file['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp'
            ];
            $imageInfo = getimagesize($file['tmp_name']);
            if (!isset($allowed[$mime]) || !$imageInfo) {
                flash_set('danger', 'Chỉ hỗ trợ JPG, PNG và WEBP.');
            } elseif ($imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
                flash_set('danger', 'Kích thước ảnh quá lớn.');
            } else {
                if (!is_dir($avatarDir)) {
                    mkdir($avatarDir, 0755, true);
                }
                $filename = bin2hex(random_bytes(16))
                          . '.' . $allowed[$mime];
                $destination = $avatarDir . $filename;
                if (move_uploaded_file($file['tmp_name'], $destination)) {
                    $newPath = $avatarUrl . $filename;
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET avatar_path = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$newPath, $userId]);
                    deleteAccountAvatar(
                        $user['avatar_path'],
                        $avatarDir
                    );
                    $_SESSION['user']['avatar'] = $newPath;
                    flash_set('success', 'Đã cập nhật ảnh đại diện.');
                } else {

                    flash_set('danger', 'Không thể lưu ảnh đại diện.');
                }
            }
        }
    }
    // 3. XÓA AVATAR
    elseif ($action === 'remove_avatar') {
        $stmt = $pdo->prepare("
            UPDATE users
            SET avatar_path = NULL
            WHERE id = ?
        ");
        $stmt->execute([$userId]);
        deleteAccountAvatar(
            $user['avatar_path'],
            $avatarDir
        );
        $_SESSION['user']['avatar'] = '';
        flash_set('success', 'Đã xóa ảnh đại diện.');
    }
    // 4. ĐỔI MẬT KHẨU
    elseif ($action === 'password') {
        $current = $_POST['current'] ?? '';
        $new = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';

        $stmt = $pdo->prepare("
            SELECT password FROM users WHERE id = ?
        ");
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        if (!password_verify($current, $hash)) {
            flash_set('danger', 'Mật khẩu hiện tại không đúng.');
        } elseif (strlen($new) < 8) {
            flash_set('danger', 'Mật khẩu mới cần ít nhất 8 ký tự.');
        } elseif ($new !== $confirm) {
            flash_set('danger', 'Mật khẩu xác nhận không khớp.');
        } elseif ($new === $current) {
            flash_set('warning', 'Mật khẩu mới phải khác mật khẩu cũ.');
        } else {
            $newHash = password_hash($new, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                UPDATE users
                SET password = ?
                WHERE id = ?
            ");
            $stmt->execute([$newHash, $userId]);
            session_regenerate_id(true);
            flash_set('success', 'Đổi mật khẩu thành công.');
        }
    }
    header('Location: account.php');
    exit;
}
// LẤY LẠI THÔNG TIN
$user = getAccountUser($pdo, $userId);

$_SESSION['user']['name'] = $user['full_name'];
$_SESSION['user']['avatar'] = $user['avatar_path'] ?? '';

$avatarLetter = function_exists('mb_substr')
    ? mb_strtoupper(mb_substr($user['full_name'], 0, 1, 'UTF-8'), 'UTF-8')
    : strtoupper(substr($user['full_name'], 0, 1));
// THỐNG KÊ
$createdCount = 0;
$registeredCount = 0;
try {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM events
        WHERE created_by = ?
    ");
    $stmt->execute([$userId]);
    $createdCount = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM registrations
        WHERE user_id = ?
    ");
    $stmt->execute([$userId]);
    $registeredCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // Không làm hỏng trang hồ sơ nếu thiếu bảng thống kê.
}
require 'includes/header.php';
?>
<div class="account-page">
    <div class="account-heading">
        <div>
            <span>MY PROFILE</span>
            <h2>Tài khoản của tôi</h2>
            <p>Quản lý hồ sơ cá nhân và bảo mật tài khoản.</p>
        </div>
    </div>
    <?php flash_render(); ?>
    <!-- THẺ HỒ SƠ -->
    <div class="profile-card">
        <div class="profile-main">
            <div class="avatar-preview" id="avatarPreview">
                <?php if (!empty($user['avatar_path'])): ?>
                    <img src="<?= h($user['avatar_path']) ?>" alt="Avatar">
                <?php else: ?>
                    <span><?= h($avatarLetter) ?></span>
                <?php endif; ?>
            </div>
            <div>
                <h3><?= h($user['full_name']) ?></h3>
                <p><?= h($user['email']) ?></p>
                <small>
                    Thành viên từ
                    <?= date('d/m/Y', strtotime($user['created_at'])) ?>
                </small>
            </div>
        </div>
        <div class="profile-stats">
            <div>
                <strong><?= $createdCount ?></strong>
                <span>Sự kiện đã tạo</span>
            </div>
            <div>
                <strong><?= $registeredCount ?></strong>
                <span>Vé đã đăng ký</span>
            </div>
        </div>
    </div>
    <div class="account-grid">
        <!-- HỒ SƠ CÁ NHÂN -->
        <section class="account-card">
            <h3>👤 Hồ sơ cá nhân</h3>
            <p>Cập nhật ảnh đại diện và thông tin của bạn.</p>
            <form method="POST"
                  enctype="multipart/form-data"
                  id="avatarForm">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="avatar">
                <input
                    type="file"
                    name="avatar"
                    id="avatarInput"
                    accept="image/jpeg,image/png,image/webp"
                    hidden
                >
                <div class="avatar-actions">
                    <button type="button"
                            class="purple-btn"
                            id="chooseAvatar">
                        📷 Chọn ảnh đại diện
                    </button>
                    <?php if (!empty($user['avatar_path'])): ?>
                        <button
                            type="submit"
                            form="removeAvatarForm"
                            class="delete-btn">
                            Xóa ảnh
                        </button>
                    <?php endif; ?>
                    <button
                        type="submit"
                        id="saveAvatar"
                        class="purple-btn"
                        hidden>
                        Lưu ảnh
                    </button>
                </div>
                <small class="help-text">
                    JPG, PNG, WEBP. Dung lượng tối đa 3MB.
                </small>
            </form>
            <form method="POST" id="removeAvatarForm" hidden>
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="remove_avatar">
            </form>
            <hr>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <label>Họ và tên</label>
                <input
                    type="text"
                    name="name"
                    value="<?= h($user['full_name']) ?>"
                    maxlength="120"
                    required
                >
                <label>Email</label>
                <input
                    type="email"
                    value="<?= h($user['email']) ?>"
                    readonly
                    class="readonly"
                >
                <small class="help-text">
                    Email hiện chưa hỗ trợ thay đổi.
                </small>
                <button class="purple-btn">
                    Lưu thông tin
                </button>
            </form>
        </section>
        <!-- BẢO MẬT -->
        <section class="account-card">
            <h3>🔐 Bảo mật tài khoản</h3>
            <p>Thay đổi mật khẩu đăng nhập.</p>
            <form method="POST">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="password">
                <label>Mật khẩu hiện tại</label>
                <div class="password-wrap">
                    <input
                        type="password"
                        name="current"
                        id="currentPassword"
                        required
                    >
                    <button type="button" data-target="currentPassword">
                        ◉
                    </button>
                </div>
                <label>Mật khẩu mới</label>
                <div class="password-wrap">
                    <input
                        type="password"
                        name="new"
                        id="newPassword"
                        minlength="8"
                        required
                    >
                    <button type="button" data-target="newPassword">
                        ◉
                    </button>
                </div>
                <small class="help-text">
                    Mật khẩu cần ít nhất 8 ký tự.
                </small>
                <label>Nhập lại mật khẩu mới</label>
                <div class="password-wrap">
                    <input
                        type="password"
                        name="confirm"
                        id="confirmPassword"
                        minlength="8"
                        required
                    >
                    <button type="button" data-target="confirmPassword">
                        ◉
                    </button>
                </div>
                <button class="purple-btn">
                    Cập nhật mật khẩu
                </button>
            </form>
        </section>
    </div>
    <!-- LIÊN KẾT NHANH -->
    <div class="account-shortcuts">
        <a href="my_events.php">
            📅
            <span>
                <strong>Sự kiện của tôi</strong>
                <small>Quản lý sự kiện đã tạo</small>
            </span>
            →
        </a>
        <a href="myevents.php">
            🎟️
            <span>
                <strong>Vé của tôi</strong>
                <small>Xem vé đã đăng ký</small>
            </span>
            →
        </a>
    </div>
</div>
<style>
.account-page {
    max-width: 1200px;
    margin: auto;
}
.account-heading {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    gap: 15px;
}
.account-heading span {
    color: #b69bea;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 1px;
}
.account-heading h2 {
    color: white;
    font-size: 28px;
    font-weight: 750;
    margin: 5px 0;
}
html[data-theme="light"] .account-heading h2 {
    color: #51458f;
}
html[data-theme="dark"] .account-heading h2 {
    color: #ffffff;
}
.account-heading p {
    color: #b7acd0;
    font-size: 13px;
    margin: 0;
}
.role-badge {
    padding: 9px 15px;
    background: #392957;
    border-radius: 25px;
}
.profile-card {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 20px;
    padding: 25px;
    margin-bottom: 20px;
    border-radius: 18px;
    background: linear-gradient(120deg, #30234f, #49356d);
    color: white;
}
.profile-main {
    display: flex;
    align-items: center;
    gap: 18px;
}
.avatar-preview {
    width: 85px;
    height: 85px;
    flex-shrink: 0;
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #e8ddff;
    color: #49356d;
    font-size: 32px;
    font-weight: bold;
    border: 3px solid white;
}
.avatar-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.profile-main h3 {
    margin: 0;
    font-size: 21px;
}
.profile-main p {
    color: #d2c4ed;
    margin: 5px 0;
}
.profile-role {
    display: block;
    font-size: 11px;
    margin-bottom: 6px;
}
.profile-main small {
    display: block;
    color: #c7b8e3;
    font-size: 11px;
}
.profile-stats {
    display: flex;
    gap: 30px;
}
.profile-stats div {
    display: flex;
    flex-direction: column;
    align-items: center;
}
.profile-stats strong {
    font-size: 26px;
}
.profile-stats span {
    color: #d2c4ed;
    font-size: 11px;
}
.account-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
}
.account-card {
    padding: 25px;
    background: white;
    border-radius: 17px;
    color: #302744;
    box-shadow: 0 5px 20px rgba(0,0,0,.08);
}
.account-card h3 {
    font-size: 18px;
    font-weight: 750;
}
.account-card > p {
    color: #888095;
    font-size: 12px;
    margin-bottom: 22px;
}
.account-card label {
    display: block;
    font-size: 12px;
    font-weight: 650;
    margin: 14px 0 7px;
}
.account-card input:not([type="hidden"]) {
    width: 100%;
    padding: 11px 13px;
    border: 1px solid #e4ddef;
    border-radius: 9px;
    outline: none;
    font-size: 13px;
    box-sizing: border-box;
}
.account-card input:focus {
    border-color: #8958df;
    box-shadow: 0 0 0 3px rgba(137,88,223,.12);
}
.account-card input.readonly,
.account-card input[readonly] {
    background: #f1eff5;
}
.purple-btn {
    display: inline-block;
    margin-top: 15px;
    padding: 10px 16px;
    border: none;
    border-radius: 9px;
    background: #8051df;
    color: white;
    cursor: pointer;
    font-size: 12px;
}
.purple-btn:hover {
    background: #6e40cb;
}
.delete-btn {
    margin-top: 15px;
    padding: 10px 14px;
    border: 1px solid #e7b9c0;
    border-radius: 9px;
    background: white;
    color: #c33d55;
    cursor: pointer;
    font-size: 12px;
}
.avatar-actions {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.help-text {
    display: block;
    margin-top: 7px;
    color: #92899e;
    font-size: 10px;
}
.password-wrap {
    position: relative;
}
.password-wrap input {
    padding-right: 45px !important;
}
.password-wrap button {
    position: absolute;
    right: 8px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    background: transparent;
    color: #8051df;
    cursor: pointer;
}
.account-shortcuts {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 13px;
    margin-top: 18px;
}
.account-shortcuts a {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 16px;
    border-radius: 13px;
    background: #30234f;
    color: white;
    text-decoration: none;
}
.account-shortcuts a span {
    flex: 1;
}
.account-shortcuts strong,
.account-shortcuts small {
    display: block;
}
.account-shortcuts small {
    color: #c5b6e0;
    font-size: 10px;
}
@media(max-width: 850px) {
    .account-grid {
        grid-template-columns: 1fr;
    }
}
@media(max-width: 550px) {
    .profile-card {
        align-items: flex-start;
    }
    .profile-stats {
        width: 100%;
        justify-content: space-around;
    }
    .account-shortcuts {
        grid-template-columns: 1fr;
    }
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // CHỌN ẢNH
    const input = document.getElementById('avatarInput');
    const choose = document.getElementById('chooseAvatar');
    const preview = document.getElementById('avatarPreview');
    const save = document.getElementById('saveAvatar');
    choose.addEventListener('click', function() {
        input.click();
    });
    input.addEventListener('change', function() {
        const file = this.files[0];
        if (!file) return;
        if (file.size > 3 * 1024 * 1024) {
            alert('Ảnh không được vượt quá 3MB.');
            this.value = '';
            return;
        }
        if (!['image/jpeg', 'image/png', 'image/webp'].includes(file.type)) {
            alert('Chỉ hỗ trợ JPG, PNG và WEBP.');
            this.value = '';
            return;
        }
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.innerHTML = '';
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Ảnh xem trước';
            preview.appendChild(img);
            save.hidden = false;
        };
        reader.readAsDataURL(file);
    });
    // HIỆN / ẨN MẬT KHẨU
    document.querySelectorAll('.password-wrap button').forEach(function(button) {
        button.addEventListener('click', function() {
            const input = document.getElementById(this.dataset.target);
            input.type = input.type === 'password'
                ? 'text'
                : 'password';
        });

    });
});
</script>
<?php require 'includes/footer.php'; ?>
