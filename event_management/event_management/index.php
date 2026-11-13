<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_login();

$search = trim($_GET['q'] ?? '');
$events = [];

if ($search !== '') {
    // Tìm theo tên, mô tả hoặc địa điểm; dữ liệu lấy trực tiếp từ MySQL.
    $like = '%' . addcslashes($search, '%_\\') . '%';
    $stmt = $pdo->prepare(
        "SELECT e.*,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
         FROM events e
         WHERE e.title LIKE ? OR e.description LIKE ? OR e.location LIKE ?
         ORDER BY e.event_date ASC
         LIMIT 30"
    );
    $stmt->execute([$like, $like, $like]);
    $events = $stmt->fetchAll();
} else {
    // Trang chủ ưu tiên các sự kiện sắp diễn ra.
    $events = $pdo->query(
        "SELECT e.*,
            (SELECT COUNT(*) FROM registrations r WHERE r.event_id = e.id) AS reg_count
         FROM events e
         WHERE e.event_date >= NOW() AND e.status <> 'Đã kết thúc'
         ORDER BY e.event_date ASC
         LIMIT 4"
    )->fetchAll();
}

require 'includes/header.php';
?>
<div class="home-heading-row">
    <div>
        <div class="home-kicker">EVENTORY · HOME</div>
    </div>
    <span class="week-badge">THIS WEEK</span>
</div>

<section class="home-hero">
    <h1>What's happening this week?</h1>
    <form class="home-search-form" method="get" action="index.php" role="search">
        <span class="home-search-icon" aria-hidden="true">⌕</span>
        <input
            type="search"
            name="q"
            value="<?= h($search) ?>"
            placeholder="Tìm kiếm..."
            aria-label="Tìm kiếm sự kiện"
        >
        <?php if ($search !== ''): ?>
            <a class="home-search-clear" href="index.php" aria-label="Xóa tìm kiếm">×</a>
        <?php endif; ?>
        <button type="submit" aria-label="Tìm kiếm">→</button>
    </form>
</section>

<section class="featured-section">
    <div class="section-heading">
        <h2><?= $search !== '' ? 'Kết quả tìm kiếm' : 'Sự kiện nổi bật' ?></h2>
        <?php if ($search === ''): ?>
            <a href="events.php">Xem tất cả <span aria-hidden="true">→</span></a>
        <?php else: ?>
            <span class="search-result-count"><?= count($events) ?> kết quả</span>
        <?php endif; ?>
    </div>

    <?php if ($events): ?>
        <div class="featured-grid">
            <?php foreach ($events as $event): ?>
                <article class="featured-card">
                    <a class="featured-card-link" href="events.php?q=<?= urlencode($event['title']) ?>">
                        <div class="featured-art<?= !empty($event['image_path']) ? ' has-event-image' : '' ?>">
                            <?php if (!empty($event['image_path'])): ?>
                                <img src="<?= h($event['image_path']) ?>" alt="<?= h($event['title']) ?>">
                            <?php else: ?>
                                <span aria-hidden="true">✦</span>
                            <?php endif; ?>
                        </div>
                        <div class="featured-card-body">
                            <h3><?= h($event['title']) ?></h3>
                            <p class="featured-meta">
                                <span>▦ <?= date('d/m/Y · H:i', strtotime($event['event_date'])) ?></span>
                                <span>⌖ <?= h($event['location']) ?></span>
                            </p>
                            <p class="featured-description">
                                <?= h(mb_strimwidth((string)$event['description'], 0, 105, '…', 'UTF-8')) ?>
                            </p>
                            <span class="featured-action">Xem sự kiện <span aria-hidden="true">→</span></span>
                        </div>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php elseif ($search !== ''): ?>
        <div class="not-found-panel">
            <div class="not-found-icon">⌕</div>
            <h3>Not Found</h3>
            <p>Không tìm thấy sự kiện phù hợp với từ khóa “<?= h($search) ?>”.</p>
            <a href="index.php">Quay lại trang chủ</a>
        </div>
    <?php else: ?>
        <div class="not-found-panel">
            <div class="not-found-icon">✦</div>
            <h3>Chưa có sự kiện sắp diễn ra</h3>
            <p>Hiện tại chưa có sự kiện nào được đăng. Hãy quay lại sau nhé!</p>
            <a href="events.php">Khám phá tất cả sự kiện</a>
        </div>
    <?php endif; ?>
</section>
<?php require 'includes/footer.php'; ?>
