<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_login();

$admin  = $_SESSION['user']['role'] === 'admin';
$userId = (int)$_SESSION['user']['id'];
/**
 * Đăng ký tham gia sự kiện trong một transaction.
 * Khóa dòng sự kiện (FOR UPDATE) để không bị vượt sức chứa
 * khi nhiều người bấm cùng lúc.
 * Trả về [loại thông báo, nội dung].
 */
function register_for_event(PDO $pdo, int $eventId, int $userId): array
{
    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT * FROM events WHERE id = ? FOR UPDATE");
        $stmt->execute([$eventId]);
        $event = $stmt->fetch();
        if (!$event) {
            $pdo->rollBack();
            return ['danger', 'Không tìm thấy sự kiện.'];
        }
        if ((int)($event['created_by'] ?? 0) === $userId) {
            $pdo->rollBack();
            return ['warning', 'Đây là sự kiện do bạn tổ chức, không thể tự đăng ký.'];
        }
        if ($event['status'] === 'Đã kết thúc'
            || strtotime($event['event_date']) < time()) {
            $pdo->rollBack();
            return ['warning', 'Sự kiện đã diễn ra hoặc đã kết thúc.'];
        }
        $stmt = $pdo->prepare(
            "SELECT id FROM registrations WHERE event_id = ? AND user_id = ?"
        );
        $stmt->execute([$eventId, $userId]);
        if ($stmt->fetch()) {
            $pdo->rollBack();
            return ['warning', 'Bạn đã đăng ký sự kiện này rồi.'];
        }
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM registrations WHERE event_id = ?"
        );
        $stmt->execute([$eventId]);
        if ((int)$stmt->fetchColumn() >= (int)$event['capacity']) {
            $pdo->rollBack();
            return ['warning', 'Sự kiện đã đủ số lượng.'];
        }
        // Mã tham dự là UNIQUE: nếu trùng thì sinh mã khác
        for ($i = 0; $i < 5; $i++) {
            $code = strtoupper(bin2hex(random_bytes(3)));
            try {
                $stmt = $pdo->prepare(
                    "INSERT INTO registrations (event_id, user_id, attendance_code)
                     VALUES (?, ?, ?)"
                );
                $stmt->execute([$eventId, $userId, $code]);
                $pdo->commit();
                return ['success', 'Đăng ký thành công! Mã tham dự của bạn: ' . $code];
            } catch (PDOException $e) {
                if (($e->errorInfo[1] ?? 0) != 1062) {
                    throw $e;
                }
            }
        }
        $pdo->rollBack();
        return ['danger', 'Không thể tạo mã tham dự, vui lòng thử lại.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log($e->getMessage());
        return ['danger', 'Có lỗi xảy ra, vui lòng thử lại sau.'];
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action  = $_POST['action'] ?? '';
    $eventId = (int)($_POST['event_id'] ?? 0);
    if ($action === 'register') {
        [$type, $text] = register_for_event($pdo, $eventId, $userId);
        flash_set($type, $text);
    } elseif ($action === 'delete') {
        $stmt = $pdo->prepare(
            "DELETE FROM events WHERE id = ? AND (created_by = ? OR ? = 1)"
        );
        $stmt->execute([$eventId, $userId, $admin ? 1 : 0]);
        flash_set($stmt->rowCount() ? 'success' : 'danger',
            $stmt->rowCount() ? 'Đã xóa sự kiện.' : 'Bạn không có quyền xóa sự kiện này.');
    }
    header('Location: events.php');
    exit;
}
$search = trim($_GET['q'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');
$locationFilter = trim($_GET['location'] ?? '');
$allowedStatuses = ['Sắp diễn ra','Đang diễn ra','Đã kết thúc'];
if (!in_array($statusFilter, $allowedStatuses, true)) $statusFilter = '';
$like = '%' . addcslashes($search, '%_\\') . '%';
$locationLike = '%' . addcslashes($locationFilter, '%_\\') . '%';
$sql = "SELECT e.*, (SELECT COUNT(*) FROM registrations r WHERE r.event_id=e.id) AS reg_count, (SELECT COUNT(*) FROM registrations r2 WHERE r2.event_id=e.id AND r2.user_id=?) AS joined FROM events e WHERE e.title LIKE ? AND e.location LIKE ?";
$params = [$userId, $like, $locationLike];
if ($statusFilter !== '') { $sql .= ' AND e.status = ?'; $params[] = $statusFilter; }
$sql .= ' ORDER BY e.event_date DESC';
$stmt = $pdo->prepare($sql); $stmt->execute($params);
$events = $stmt->fetchAll();
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-4 events-page-heading">
    <h2 class="page-title">Danh sách sự kiện</h2>
    <a href="event_form.php" class="btn btn-primary btn-create-event">+ Tạo sự kiện</a>
</div>
<?php flash_render(); ?>
<form method="get" class="event-search-toolbar mb-4">
    <div class="event-search-field"><input class="form-control" name="q" placeholder="Tìm theo tên sự kiện..." value="<?= h($search) ?>"></div>
    <div class="event-location-field"><input class="form-control" name="location" placeholder="Địa điểm" value="<?= h($locationFilter) ?>"></div>
    <div class="event-status-field"><select class="form-select" name="status"><option value="">Mọi trạng thái</option><?php foreach ($allowedStatuses as $st): ?><option value="<?= h($st) ?>" <?= $statusFilter===$st?'selected':'' ?>><?= h($st) ?></option><?php endforeach; ?></select></div>
    <div class="event-search-actions"><button class="btn btn-primary" type="submit">Lọc</button><a class="btn btn-outline-secondary" href="events.php">Xóa</a></div>
</form>
<div class="row g-3 event-grid">
<?php foreach ($events as $e):
    $past = $e['status'] === 'Đã kết thúc'
         || strtotime($e['event_date']) < time();
    $full = (int)$e['reg_count'] >= (int)$e['capacity'];
    $isEventOrganizer = (int)($e['created_by'] ?? 0) === $userId;
    $isEventParticipant = !$isEventOrganizer && (int)$e['joined'] > 0;
?>
    <div class="col-md-6 col-xl-4 event-grid-item">
        <div class="card event-card">
            <?php if (!empty($e['image_path'])): ?>
                <div class="event-banner has-event-image">
                    <img src="<?= h($e['image_path']) ?>" alt="<?= h($e['title']) ?>">
                </div>
            <?php else: ?>
                <div class="event-banner"><span aria-hidden="true">✦</span></div>
            <?php endif; ?>
            <div class="p-4 event-card-body">
                <span class="badge badge-soft mb-2"><?= h($e['status']) ?></span>
                <?php if ($isEventOrganizer): ?>
                    <span class="badge mb-2" style="background:#e1efff;color:#2868a8">Organizer · Sự kiện của bạn</span>
                <?php elseif ($isEventParticipant): ?>
                    <span class="badge mb-2" style="background:#e1f5e9;color:#237447">Participant · Đã đăng ký</span>
                <?php endif; ?>
                <h5 class="fw-bold"><?= h($e['title']) ?></h5>
                <p class="muted small">
                    📅 <?= date('d/m/Y H:i', strtotime($e['event_date'])) ?>
                </p>
                <p class="muted small">📍 <?= h($e['location']) ?></p>
                <div class="event-description-wrap">
                    <p class="event-description"><?= h(trim((string)($e['description'] ?? ''))) ?></p>
                    <button type="button" class="event-description-toggle" aria-expanded="false" hidden>… Xem thêm</button>
                </div>
                <div class="event-card-footer">
                    <span class="event-participant-count" title="Số người đã đăng ký">
                        <span aria-hidden="true">👥</span>
                        <strong><?= (int)$e['reg_count'] ?>/<?= (int)$e['capacity'] ?></strong>
                    </span>
                    <?php if ($admin || (int)($e['created_by'] ?? 0) === $userId): ?>
                        <div class="event-admin-actions">
                            <a class="event-action-edit" href="event_form.php?id=<?= (int)$e['id'] ?>">Sửa</a>
                            <form method="post" onsubmit="return confirmDelete()">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
                                <button class="event-action-delete" type="submit">Xóa</button>
                            </form>
                            <a class="event-action-participants" href="participants.php?event_id=<?= (int)$e['id'] ?>">Người tham gia</a>
                        </div>
                    <?php elseif ($e['joined']): ?>
                        <span class="badge text-bg-success">Đã đăng ký</span>
                    <?php elseif ($past): ?>
                        <span class="badge text-bg-secondary">Đã kết thúc</span>
                    <?php elseif ($full): ?>
                        <span class="badge text-bg-warning">Hết chỗ</span>
                    <?php else: ?>
                        <form method="post" class="event-register-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="event_id" value="<?= (int)$e['id'] ?>">
                            <button class="btn btn-sm btn-primary">Đăng ký</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
<?php endforeach; ?>
<?php if (!$events): ?>
    <div class="col-12">
        <div class="card empty text-center py-5">
            <div class="mb-3" aria-hidden="true" style="font-size:2.5rem">📅</div>
            <h5 class="fw-bold mb-2">
                <?= $search !== '' ? 'Không tìm thấy sự kiện phù hợp' : 'Chưa có sự kiện nào' ?>
            </h5>
            <p class="muted mb-3">
                <?= $search !== ''
                    ? 'Thử thay đổi từ khóa tìm kiếm hoặc xóa nội dung tìm kiếm.'
                    : 'Các sự kiện do bạn hoặc người dùng khác tạo sẽ xuất hiện tại đây.' ?>
            </p>
            <?php if ($search === ''): ?>
                <div>
                    <a href="event_form.php" class="btn btn-primary">+ Tạo sự kiện đầu tiên</a>
                </div>
            <?php else: ?>
                <div>
                    <a href="events.php" class="btn btn-outline-primary">Xóa tìm kiếm</a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>
</div>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.event-grid-item .event-description-wrap').forEach(function (wrap) {
        var description = wrap.querySelector('.event-description');
        var toggle = wrap.querySelector('.event-description-toggle');
        if (!description || !toggle || !description.textContent.trim()) return;

        // Đo bằng bản sao không bị line-clamp giới hạn.
        var measure = description.cloneNode(true);
        measure.classList.remove('is-expanded');
        measure.style.cssText += ';position:fixed;left:-99999px;top:0;width:' +
            description.getBoundingClientRect().width + 'px;height:auto;max-height:none;' +
            'display:block;overflow:visible;-webkit-line-clamp:unset;line-clamp:unset;' +
            'visibility:hidden;';
        document.body.appendChild(measure);
        var fullHeight = measure.getBoundingClientRect().height;
        measure.remove();

        var lineHeight = parseFloat(getComputedStyle(description).lineHeight) || 24;
        if (fullHeight > lineHeight * 3 + 2) toggle.hidden = false;

        toggle.addEventListener('click', function () {
            var expanded = description.classList.toggle('is-expanded');
            toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            toggle.textContent = expanded ? 'Thu gọn' : '… Xem thêm';
        });
    });
});
</script>
<?php require 'includes/footer.php'; ?>