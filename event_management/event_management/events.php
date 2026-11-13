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
// Escape ký tự đặc biệt của LIKE để tìm đúng nghĩa đen
$like = '%' . addcslashes($search, '%_\\') . '%';

$stmt = $pdo->prepare(
    "SELECT e.*,
        (SELECT COUNT(*) FROM registrations r
         WHERE r.event_id = e.id) AS reg_count,
        (SELECT COUNT(*) FROM registrations r2
         WHERE r2.event_id = e.id AND r2.user_id = ?) AS joined
     FROM events e
     WHERE e.title LIKE ?
     ORDER BY e.event_date DESC"
);
$stmt->execute([$userId, $like]);
$events = $stmt->fetchAll();
require 'includes/header.php';
?>
<div class="d-flex justify-content-between mb-4">
    <h2 class="page-title">Danh sách sự kiện</h2>
    <a href="event_form.php" class="btn btn-primary">+ Tạo sự kiện</a>
</div>
<?php flash_render(); ?>
<form method="get" class="mb-4">
    <div class="input-group" style="max-width:500px">
        <input class="form-control" name="q"
               placeholder="Tìm kiếm sự kiện..."
               value="<?= h($search) ?>">
        <button class="btn btn-primary">Tìm kiếm</button>
    </div>
</form>
<div class="row g-3">
<?php foreach ($events as $e):
    $past = $e['status'] === 'Đã kết thúc'
         || strtotime($e['event_date']) < time();
    $full = (int)$e['reg_count'] >= (int)$e['capacity'];
?>
    <div class="col-md-6 col-xl-4">
        <div class="card event-card">
            <?php if (!empty($e['image_path'])): ?>
                <div class="event-banner has-event-image">
                    <img src="<?= h($e['image_path']) ?>" alt="<?= h($e['title']) ?>">
                </div>
            <?php else: ?>
                <div class="event-banner"><span aria-hidden="true">✦</span></div>
            <?php endif; ?>
            <div class="p-4">
                <span class="badge badge-soft mb-2"><?= h($e['status']) ?></span>
                <h5 class="fw-bold"><?= h($e['title']) ?></h5>
                <p class="muted small">
                    📅 <?= date('d/m/Y H:i', strtotime($e['event_date'])) ?>
                </p>
                <p class="muted small">📍 <?= h($e['location']) ?></p>
                <p><?= h($e['description']) ?></p>
                <div class="d-flex justify-content-between align-items-center">
                    <span class="small">
                        👥 <?= (int)$e['reg_count'] ?>/<?= (int)$e['capacity'] ?>
                    </span>
                    <?php if ($admin || (int)($e['created_by'] ?? 0) === $userId): ?>
                        <span>
                            <a href="event_form.php?id=<?= (int)$e['id'] ?>">Sửa</a>
                            <form method="post" class="d-inline ms-2"
                                  onsubmit="return confirmDelete()">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="event_id"
                                       value="<?= (int)$e['id'] ?>">
                                <button class="btn btn-link text-danger p-0">Xóa</button>
                            </form>
                            <a class="ms-2" href="participants.php?event_id=<?= (int)$e['id'] ?>">Người tham gia</a>
                        </span>
                    <?php elseif ($e['joined']): ?>
                        <span class="badge text-bg-success">Đã đăng ký</span>
                    <?php elseif ($past): ?>
                        <span class="badge text-bg-secondary">Đã kết thúc</span>
                    <?php elseif ($full): ?>
                        <span class="badge text-bg-warning">Hết chỗ</span>
                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="register">
                            <input type="hidden" name="event_id"
                                   value="<?= (int)$e['id'] ?>">
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
        <div class="card empty">Không tìm thấy sự kiện.</div>
    </div>
<?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>