<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_login();

$userId = (int)$_SESSION['user']['id'];
$isAdmin = $_SESSION['user']['role'] === 'admin';

$stmt = $pdo->prepare(
    "SELECT e.*,
        (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
     FROM events e
     WHERE e.created_by = ?
     ORDER BY e.created_at DESC"
);
$stmt->execute([$userId]);
$rows = $stmt->fetchAll();
require 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-3">
    <div>
        <h2 class="page-title mb-1">Sự kiện của tôi</h2>
        <p class="muted mb-0">Quản lý những sự kiện bạn đã tạo.</p>
    </div>
    <a href="event_form.php" class="btn btn-primary">+ Tạo sự kiện mới</a>
</div>
<?php flash_render(); ?>
<div class="row g-3">
    <?php foreach ($rows as $event): ?>
        <div class="col-md-6 col-xl-4">
            <div class="card event-card">
                <?php if (!empty($event['image_path'])): ?>
                    <div class="event-banner has-event-image">
                        <img src="<?= h($event['image_path']) ?>" alt="<?= h($event['title']) ?>">
                    </div>
                <?php else: ?>
                    <div class="event-banner"><span aria-hidden="true">✦</span></div>
                <?php endif; ?>
                <div class="p-4">
                    <span class="badge badge-soft mb-2"><?= h($event['status']) ?></span>
                    <span class="badge mb-2" style="background:#e1efff;color:#2868a8">Organizer · Sự kiện do bạn tạo</span>
                    <h5 class="fw-bold"><?= h($event['title']) ?></h5>
                    <p class="muted small mb-2">📅 <?= date('d/m/Y H:i', strtotime($event['event_date'])) ?></p>
                    <p class="muted small mb-2">📍 <?= h($event['location']) ?></p>
                    <p class="small">👥 <?= (int)$event['reg_count'] ?> / <?= (int)$event['capacity'] ?> người đăng ký</p>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-primary" href="event_form.php?id=<?= (int)$event['id'] ?>">Chỉnh sửa</a>
                        <a class="btn btn-sm btn-outline-primary" href="participants.php?event_id=<?= (int)$event['id'] ?>">Người tham gia</a>
                        <a class="btn btn-sm btn-outline-primary" href="checkin.php?event_id=<?= (int)$event['id'] ?>">Điểm danh</a>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
    <?php if (!$rows): ?>
        <div class="col-12">
            <div class="card empty">
                Bạn chưa tạo sự kiện nào.
                <div class="mt-3"><a href="event_form.php" class="btn btn-primary">Tạo sự kiện đầu tiên</a></div>
            </div>
        </div>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
