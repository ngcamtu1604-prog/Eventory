<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_login();

$userId = (int)$_SESSION['user']['id'];
$isAdmin = $_SESSION['user']['role'] === 'admin';
$selectedEventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? 0);
$message = '';
$kind = 'info';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $code = strtoupper(trim($_POST['code'] ?? ''));

    $stmt = $pdo->prepare(
        "SELECT r.*, u.full_name, e.title, e.id AS event_id, e.created_by
         FROM registrations r
         JOIN users u ON u.id = r.user_id
         JOIN events e ON e.id = r.event_id
         WHERE r.attendance_code = ?
           AND (e.created_by = ? OR ? = 1)
           AND (? = 0 OR e.id = ?)"
    );
    $stmt->execute([
        $code,
        $userId,
        $isAdmin ? 1 : 0,
        $selectedEventId,
        $selectedEventId
    ]);
    $row = $stmt->fetch();

    if (!$row) {
        $message = 'Mã tham dự không hợp lệ hoặc sự kiện không thuộc quyền quản lý của bạn.';
        $kind = 'danger';
    } elseif ($row['attended']) {
        $message = 'Người tham gia đã được điểm danh trước đó.';
        $kind = 'warning';
    } else {
        $stmt = $pdo->prepare(
            "UPDATE registrations SET attended = 1, checked_in_at = NOW() WHERE id = ?"
        );
        $stmt->execute([$row['id']]);
        $selectedEventId = (int)$row['event_id'];
        $message = 'Điểm danh thành công: ' . $row['full_name'] . ' - ' . $row['title'];
        $kind = 'success';
    }
}

if ($isAdmin) {
    $eventsStmt = $pdo->query("SELECT id, title FROM events ORDER BY event_date DESC");
} else {
    $eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE created_by = ? ORDER BY event_date DESC");
    $eventsStmt->execute([$userId]);
}
$myEvents = $eventsStmt->fetchAll();

$sql = "SELECT u.full_name, e.title, r.checked_in_at
        FROM registrations r
        JOIN users u ON u.id = r.user_id
        JOIN events e ON e.id = r.event_id
        WHERE r.attended = 1 AND (e.created_by = ? OR ? = 1)";
$params = [$userId, $isAdmin ? 1 : 0];
if ($selectedEventId > 0) {
    $sql .= " AND e.id = ?";
    $params[] = $selectedEventId;
}
$sql .= " ORDER BY r.checked_in_at DESC LIMIT 20";
$recentStmt = $pdo->prepare($sql);
$recentStmt->execute($params);
$recent = $recentStmt->fetchAll();

require 'includes/header.php';
?>
<h2 class="page-title mb-2">Điểm danh sự kiện</h2>
<p class="muted mb-4">Xác nhận người tham gia các sự kiện do bạn tổ chức.</p>
<div class="card p-4 mb-4" style="max-width:760px">
    <?php if ($message): ?>
        <div class="alert alert-<?= h($kind) ?>"><?= h($message) ?></div>
    <?php endif; ?>
    <?php if (!$myEvents): ?>
        <div class="alert alert-info mb-0">
            Bạn chưa có sự kiện nào để điểm danh. Hãy tạo sự kiện trước nhé.
        </div>
    <?php else: ?>
        <form method="post">
            <?= csrf_field() ?>
            <label class="form-label">Chọn sự kiện</label>
            <select class="form-select mb-3" name="event_id" required>
                <option value="">-- Chọn sự kiện cần điểm danh --</option>
                <?php foreach ($myEvents as $event): ?>
                    <option value="<?= (int)$event['id'] ?>" <?= $selectedEventId === (int)$event['id'] ? 'selected' : '' ?>>
                        <?= h($event['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <label class="form-label">Mã tham dự</label>
            <div class="input-group">
                <input class="form-control" name="code" required placeholder="Ví dụ: A1B2C3">
                <button class="btn btn-primary">Kiểm tra và điểm danh</button>
            </div>
        </form>
    <?php endif; ?>
</div>
<div class="card p-3">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
        <h5 class="fw-bold mb-0">Lượt điểm danh gần đây</h5>
        <form method="get">
            <select class="form-select form-select-sm" name="event_id" onchange="this.form.submit()">
                <option value="">Tất cả sự kiện của tôi</option>
                <?php foreach ($myEvents as $event): ?>
                    <option value="<?= (int)$event['id'] ?>" <?= $selectedEventId === (int)$event['id'] ? 'selected' : '' ?>>
                        <?= h($event['title']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <div class="table-responsive">
        <table class="table">
            <thead><tr><th>Người tham gia</th><th>Sự kiện</th><th>Thời gian</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= h($r['full_name']) ?></td>
                    <td><?= h($r['title']) ?></td>
                    <td><?= $r['checked_in_at'] ? date('d/m/Y H:i', strtotime($r['checked_in_at'])) : '—' ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$recent): ?>
                <tr><td colspan="3" class="empty">Chưa có lượt điểm danh nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
