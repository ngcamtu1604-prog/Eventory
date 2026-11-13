<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_login();

$userId = (int)$_SESSION['user']['id'];
$isAdmin = $_SESSION['user']['role'] === 'admin';
$eventId = (int)($_GET['event_id'] ?? 0);

if ($isAdmin) {
    $eventsStmt = $pdo->query("SELECT id, title FROM events ORDER BY event_date DESC");
} else {
    $eventsStmt = $pdo->prepare("SELECT id, title FROM events WHERE created_by = ? ORDER BY event_date DESC");
    $eventsStmt->execute([$userId]);
}
$myEvents = $eventsStmt->fetchAll();
$sql = "SELECT u.full_name, u.email, e.title, e.id AS event_id,
               r.attendance_code, r.attended, r.registered_at, r.checked_in_at
        FROM registrations r
        JOIN users u ON u.id = r.user_id
        JOIN events e ON e.id = r.event_id
        WHERE (e.created_by = ? OR ? = 1)";
$params = [$userId, $isAdmin ? 1 : 0];
if ($eventId > 0) {
    $sql .= " AND e.id = ?";
    $params[] = $eventId;
}
$sql .= " ORDER BY r.registered_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$attendees = $stmt->fetchAll();
require 'includes/header.php';
?>
<h2 class="page-title mb-2">Người tham gia</h2>
<p class="muted mb-4">Danh sách người đăng ký các sự kiện do bạn tổ chức.</p>
<div class="card p-3">
    <form method="get" class="mb-3" style="max-width:520px">
        <label class="form-label">Lọc theo sự kiện</label>
        <select class="form-select" name="event_id" onchange="this.form.submit()">
            <option value="">Tất cả sự kiện của tôi</option>
            <?php foreach ($myEvents as $event): ?>
                <option value="<?= (int)$event['id'] ?>" <?= $eventId === (int)$event['id'] ? 'selected' : '' ?>>
                    <?= h($event['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr><th>Họ tên</th><th>Email</th><th>Sự kiện</th><th>Mã tham dự</th><th>Trạng thái</th><th>Ngày đăng ký</th></tr>
            </thead>
            <tbody>
            <?php foreach ($attendees as $person): ?>
                <tr>
                    <td><?= h($person['full_name']) ?></td>
                    <td><?= h($person['email']) ?></td>
                    <td><?= h($person['title']) ?></td>
                    <td><span class="code-box fs-6"><?= h($person['attendance_code']) ?></span></td>
                    <td><?= $person['attended'] ? 'Đã điểm danh' : 'Chưa điểm danh' ?></td>
                    <td><?= date('d/m/Y H:i', strtotime($person['registered_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$attendees): ?>
                <tr><td colspan="6" class="empty">Chưa có người đăng ký sự kiện nào.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'includes/footer.php'; ?>
