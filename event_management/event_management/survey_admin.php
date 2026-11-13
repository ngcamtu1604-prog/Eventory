<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_login();

$userId = (int)$_SESSION['user']['id'];
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

if ($isAdmin) {
    $events = $pdo->query("SELECT id,title,event_date FROM events ORDER BY event_date DESC")->fetchAll();
} else {
    $stmt = $pdo->prepare("SELECT id,title,event_date FROM events WHERE created_by = ? ORDER BY event_date DESC");
    $stmt->execute([$userId]);
    $events = $stmt->fetchAll();
}

$selectedEventId = (int)($_GET['event_id'] ?? $_POST['event_id'] ?? ($events[0]['id'] ?? 0));
$selectedEvent = null;
foreach ($events as $eventOption) {
    if ((int)$eventOption['id'] === $selectedEventId) {
        $selectedEvent = $eventOption;
        break;
    }
}
if ($selectedEventId && !$selectedEvent) {
    http_response_code(403);
    exit('Bạn không có quyền quản lý sự kiện này.');
}
function eventory_money($amount) {
    return number_format((float)$amount, 0, ',', '.') . ' ₫';
}

$selectedFilter = (int)($_GET['event_id'] ?? 0);
$filterSql = '';
$params = [];
if ($isAdmin) {
    $baseSql = "SELECT s.id,s.rating,s.comment,s.created_at,u.full_name,e.id event_id,e.title event_title
                FROM surveys s JOIN users u ON u.id=s.user_id JOIN events e ON e.id=s.event_id";
} else {
    $baseSql = "SELECT s.id,s.rating,s.comment,s.created_at,u.full_name,e.id event_id,e.title event_title
                FROM surveys s JOIN users u ON u.id=s.user_id JOIN events e ON e.id=s.event_id
                WHERE e.created_by = ?";
    $params[] = $userId;
}
if ($selectedFilter) {
    $baseSql .= $isAdmin ? " WHERE e.id = ?" : " AND e.id = ?";
    $params[] = $selectedFilter;
}
$baseSql .= " ORDER BY s.created_at DESC";
$stmt=$pdo->prepare($baseSql);$stmt->execute($params);$feedback=$stmt->fetchAll();

$summarySql = "SELECT e.id,e.title,COUNT(s.id) total_reviews,AVG(s.rating) avg_rating
               FROM events e LEFT JOIN surveys s ON s.event_id=e.id";
$summaryParams=[];
if (!$isAdmin) {$summarySql.=" WHERE e.created_by=?";$summaryParams[]=$userId;}
$summarySql.=" GROUP BY e.id ORDER BY e.event_date DESC";
$stmt=$pdo->prepare($summarySql);$stmt->execute($summaryParams);$summaries=$stmt->fetchAll();
$totalReviews=count($feedback);
$avgRating=$totalReviews ? array_sum(array_column($feedback,'rating'))/$totalReviews : 0;
require 'includes/header.php';
?>
<div class="module-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><h2 class="page-title mb-1">Khảo sát & đánh giá</h2><p class="muted mb-0">Theo dõi phản hồi và mức độ hài lòng của người tham dự.</p></div>
        <form method="get" class="d-flex gap-2"><select class="form-select" name="event_id" onchange="this.form.submit()"><option value="0">Tất cả sự kiện</option><?php foreach($events as $e): ?><option value="<?= (int)$e['id'] ?>" <?= $selectedFilter===(int)$e['id']?'selected':'' ?>><?= h($e['title']) ?></option><?php endforeach; ?></select></form>
    </div>
    <div class="row g-3 mb-4">
        <div class="col-md-6"><div class="card stat-card"><div class="muted">Tổng lượt đánh giá<?= $selectedFilter?' (theo bộ lọc)':'' ?></div><div class="stat-number"><?= $totalReviews ?></div></div></div>
        <div class="col-md-6"><div class="card stat-card"><div class="muted">Điểm trung bình</div><div class="stat-number"><?= $totalReviews?number_format($avgRating,1).'/5':'—' ?></div></div></div>
    </div>
    <div class="card p-3 mb-4"><h5 class="mb-3">Tổng hợp theo sự kiện</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sự kiện</th><th>Số lượt đánh giá</th><th>Điểm trung bình</th></tr></thead><tbody>
    <?php foreach($summaries as $s): ?><tr><td><?= h($s['title']) ?></td><td><?= (int)$s['total_reviews'] ?></td><td><?= $s['avg_rating']!==null?number_format((float)$s['avg_rating'],1).' / 5':'Chưa có đánh giá' ?></td></tr><?php endforeach; ?>
    <?php if(!$summaries): ?><tr><td colspan="3" class="text-center muted py-4">Bạn chưa có sự kiện để tổng hợp phản hồi.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <div class="card p-3"><h5 class="mb-3">Phản hồi gần đây</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Sự kiện</th><th>Người đánh giá</th><th>Điểm</th><th>Nhận xét</th><th>Thời gian</th></tr></thead><tbody>
    <?php foreach($feedback as $f): ?><tr><td><?= h($f['event_title']) ?></td><td><?= h($f['full_name']) ?></td><td><span class="text-warning"><?= str_repeat('★',(int)$f['rating']) ?></span> <span class="muted"><?= (int)$f['rating'] ?>/5</span></td><td><?= !empty($f['comment'])?nl2br(h($f['comment'])):'<span class="muted">Không có nhận xét</span>' ?></td><td><?= date('d/m/Y H:i',strtotime($f['created_at'])) ?></td></tr><?php endforeach; ?>
    <?php if(!$feedback): ?><tr><td colspan="5" class="text-center muted py-4">Chưa có phản hồi. Người tham dự có thể đánh giá sau khi được điểm danh.</td></tr><?php endif; ?>
    </tbody></table></div></div>
</div>
<?php require 'includes/footer.php'; ?>
