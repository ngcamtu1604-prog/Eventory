<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_role(['admin', 'user']);

$userId = (int)$_SESSION['user']['id'];
$isAdmin = $_SESSION['user']['role'] === 'admin';
$id = (int)($_GET['id'] ?? 0);

$event = [
    'title' => '',
    'description' => '',
    'event_date' => '',
    'location' => '',
    'capacity' => 50,
    'status' => 'Sắp diễn ra',
    'speaker_id' => '',
    'organizer_id' => '',
    'created_by' => $userId,
    'image_path' => null
];
if ($id) {
    $stmt = $pdo->prepare(
        "SELECT e.*, s.name AS speaker_name, o.name AS organizer_name
         FROM events e
         LEFT JOIN speakers s ON s.id = e.speaker_id
         LEFT JOIN organizers o ON o.id = e.organizer_id
         WHERE e.id = ?"
    );
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) {
        http_response_code(404);
        exit('Không tìm thấy sự kiện.');
    }
    if (!$isAdmin && (int)$found['created_by'] !== $userId) {
        http_response_code(403);
        exit('Bạn chỉ có thể chỉnh sửa sự kiện do mình tạo.');
    }
    $event = $found;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $date = trim($_POST['event_date'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $capacity = (int)($_POST['capacity'] ?? 0);
    $status = $_POST['status'] ?? 'Sắp diễn ra';

    if (!in_array($status, ['Sắp diễn ra', 'Đang diễn ra', 'Đã kết thúc'], true)) {
        $status = 'Sắp diễn ra';
    }

    // Người dùng nhập tên tự do; tìm bản ghi đã có hoặc tạo mới để lưu đúng FK.
    $speakerName = trim($_POST['speaker_name'] ?? '');
    $organizerName = trim($_POST['organizer_name'] ?? '');
    $speaker = null;
    $organizer = null;

    $newImagePath = null;
    $uploadedFile = $_FILES['event_image'] ?? null;
	
    if ($title === '' || mb_strlen($title) > 200 || $location === '' || !$date || $capacity < 1) {
        $error = 'Vui lòng nhập tên sự kiện, thời gian, địa điểm và sức chứa hợp lệ.';
    } elseif (mb_strlen($speakerName) > 150 || mb_strlen($organizerName) > 150) {
        $error = 'Tên diễn giả và ban tổ chức không được vượt quá 150 ký tự.';
    } elseif (DateTime::createFromFormat('Y-m-d\TH:i', $date) === false) {
        $error = 'Thời gian sự kiện không hợp lệ.';
    } elseif ($uploadedFile && $uploadedFile['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($uploadedFile['error'] !== UPLOAD_ERR_OK) {
            $error = 'Tải ảnh lên không thành công. Vui lòng thử lại.';
        } elseif ($uploadedFile['size'] > 5 * 1024 * 1024) {
            $error = 'Ảnh minh họa không được vượt quá 5MB.';
        } else {
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mime = $finfo->file($uploadedFile['tmp_name']);
            $allowed = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
                'image/gif' => 'gif'
            ];
            if (!isset($allowed[$mime]) || @getimagesize($uploadedFile['tmp_name']) === false) {
                $error = 'Định dạng ảnh không hợp lệ. Chỉ nhận JPG, PNG, WEBP hoặc GIF.';
            } else {
                $uploadDir = __DIR__ . '/uploads/events';
                if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
                    $error = 'Không thể tạo thư mục lưu ảnh. Hãy kiểm tra quyền ghi thư mục project.';
                } else {
                    $fileName = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
                    $destination = $uploadDir . '/' . $fileName;
                    if (move_uploaded_file($uploadedFile['tmp_name'], $destination)) {
                        $newImagePath = 'uploads/events/' . $fileName;
                    } else {
                        $error = 'Không thể lưu ảnh tải lên. Vui lòng kiểm tra quyền thư mục.';
                    }
                }
            }
        }
    }
    if ($error === '') {
        // Chuẩn hóa diễn giả và đơn vị tổ chức thành khóa ngoại hợp lệ.
        if ($speakerName !== '') {
            $find = $pdo->prepare('SELECT id FROM speakers WHERE name = ? LIMIT 1');
            $find->execute([$speakerName]); $speaker = $find->fetchColumn();
            if (!$speaker) { $add = $pdo->prepare('INSERT INTO speakers(name) VALUES(?)'); $add->execute([$speakerName]); $speaker = (int)$pdo->lastInsertId(); }
        }
        if ($organizerName !== '') {
            $find = $pdo->prepare('SELECT id FROM organizers WHERE name = ? LIMIT 1');
            $find->execute([$organizerName]); $organizer = $find->fetchColumn();
            if (!$organizer) { $add = $pdo->prepare('INSERT INTO organizers(name) VALUES(?)'); $add->execute([$organizerName]); $organizer = (int)$pdo->lastInsertId(); }
        }
        $eventDate = str_replace('T', ' ', $date);
        if (strlen($eventDate) === 16) {
            $eventDate .= ':00';
        }
        if ($id) {
            if ($newImagePath !== null) {
                $data = [
                    $title, $description, $eventDate, $location,
                    $capacity, $status, $speaker, $organizer, $newImagePath, $id
                ];
                $stmt = $pdo->prepare(
                    "UPDATE events SET
                        title=?, description=?, event_date=?, location=?,
                        capacity=?, status=?, speaker_id=?, organizer_id=?, image_path=?
                     WHERE id=?"
                );
            } else {
                $data = [
                    $title, $description, $eventDate, $location,
                    $capacity, $status, $speaker, $organizer, $id
                ];
                $stmt = $pdo->prepare(
                    "UPDATE events SET
                        title=?, description=?, event_date=?, location=?,
                        capacity=?, status=?, speaker_id=?, organizer_id=?
                     WHERE id=?"
                );
            }
        } else {
            $data = [
                $title, $description, $eventDate, $location,
                $capacity, $status, $speaker, $organizer, $userId, $newImagePath
            ];
            $stmt = $pdo->prepare(
                "INSERT INTO events
                    (title, description, event_date, location, capacity,
                     status, speaker_id, organizer_id, created_by, image_path)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
        }
        $stmt->execute($data);
        header('Location: my_events.php');
        exit;
    }
}
require 'includes/header.php';
?>
<h2 class="page-title mb-3"><?= $id ? 'Chỉnh sửa sự kiện' : 'Tạo sự kiện mới' ?></h2>
<p class="muted mb-4">Tạo và quản lý sự kiện của riêng bạn trên EVENTORY.</p>
<div class="card p-4" style="max-width:850px">
    <?php if ($error): ?>
        <div class="alert alert-danger"><?= h($error) ?></div>
    <?php endif; ?>
    <form method="post" enctype="multipart/form-data" class="row g-3">
        <?= csrf_field() ?>
        <div class="col-12">
            <label class="form-label">Tên sự kiện</label>
            <input class="form-control" name="title" maxlength="200" required
                   value="<?= h($event['title']) ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Ảnh minh họa sự kiện <span class="text-secondary">(không bắt buộc)</span></label>
            <input class="form-control" type="file" name="event_image" id="eventImage"
                   accept="image/jpeg,image/png,image/webp,image/gif">
            <small class="text-secondary">JPG, PNG, WEBP hoặc GIF · tối đa 5MB. Nếu không tải ảnh, EVENTORY sẽ dùng hình nền mặc định.</small>
            <div class="event-image-preview-wrap mt-3" id="eventImagePreviewWrap"
                 style="<?= !empty($event['image_path']) ? '' : 'display:none' ?>">
                <img id="eventImagePreview"
                     src="<?= !empty($event['image_path']) ? h($event['image_path']) : '' ?>"
                     alt="Ảnh minh họa xem trước">
            </div>
        </div>
        <div class="col-md-6">
            <label class="form-label">Thời gian</label>
            <input type="datetime-local" class="form-control" name="event_date" required
                   value="<?= $event['event_date'] ? date('Y-m-d\TH:i', strtotime($event['event_date'])) : '' ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Địa điểm</label>
            <input class="form-control" name="location" required
                   value="<?= h($event['location']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Sức chứa</label>
            <input type="number" min="1" class="form-control" name="capacity" required
                   value="<?= h($event['capacity']) ?>">
        </div>
        <div class="col-md-4">
            <label class="form-label">Trạng thái</label>
            <select class="form-select" name="status">
                <?php foreach (['Sắp diễn ra', 'Đang diễn ra', 'Đã kết thúc'] as $st): ?>
                    <option value="<?= h($st) ?>" <?= $event['status'] === $st ? 'selected' : '' ?>>
                        <?= h($st) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4">
            <label class="form-label">Diễn giả <span class="text-secondary">(không bắt buộc)</span></label>
            <input type="text" class="form-control" name="speaker_name" maxlength="150"
                   placeholder="Nhập tên diễn giả"
                   value="<?= h($event['speaker_name'] ?? '') ?>">
        </div>
        <div class="col-md-6">
            <label class="form-label">Ban tổ chức <span class="text-secondary">(không bắt buộc)</span></label>
            <input type="text" class="form-control" name="organizer_name" maxlength="150"
                   placeholder="Nhập tên ban tổ chức"
                   value="<?= h($event['organizer_name'] ?? '') ?>">
        </div>
        <div class="col-12">
            <label class="form-label">Mô tả sự kiện</label>
            <textarea class="form-control" name="description" rows="4"><?= h($event['description']) ?></textarea>
        </div>
        <div class="col-12">
            <button class="btn btn-primary"><?= $id ? 'Lưu thay đổi' : 'Tạo sự kiện' ?></button>
            <a href="my_events.php" class="btn btn-secondary">Quay lại</a>
        </div>
    </form>
</div>
<style>
.event-image-preview-wrap {
    width: min(100%, 440px);
    aspect-ratio: 16 / 9;
    overflow: hidden;
    border-radius: 14px;
    background: #302751;
}
.event-image-preview-wrap img {
    width: 100%; height: 100%; object-fit: cover; display: block;
}
</style>
<script>
document.getElementById('eventImage')?.addEventListener('change', function () {
    const file = this.files && this.files[0];
    const wrap = document.getElementById('eventImagePreviewWrap');
    const preview = document.getElementById('eventImagePreview');
    if (!file) return;
    if (file.size > 5 * 1024 * 1024) {
        alert('Ảnh minh họa không được vượt quá 5MB.');
        this.value = '';
        return;
    }
    preview.src = URL.createObjectURL(file);
    wrap.style.display = 'block';
});
</script>
<?php require 'includes/footer.php'; ?>
