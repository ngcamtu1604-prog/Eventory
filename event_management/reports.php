<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_admin();
// Đánh giá TB tính bằng subquery để không bị nhân bản dòng
// (join cả registrations lẫn surveys) và không dùng AVG(DISTINCT).
$rows = $pdo->query(
    "SELECT
        e.id,
        e.title,
        e.capacity,
        COUNT(r.id) AS registrations,
        COALESCE(SUM(r.attended = 1), 0) AS attendees,
        (SELECT AVG(s.rating) FROM surveys s
          WHERE s.event_id = e.id) AS avg_rating,
        (SELECT COUNT(*) FROM surveys s2
          WHERE s2.event_id = e.id) AS survey_count
    FROM events e
    LEFT JOIN registrations r ON r.event_id = e.id
    GROUP BY e.id
    ORDER BY registrations DESC, e.event_date DESC"
)->fetchAll();
$total = (int)$pdo->query("SELECT COUNT(*) FROM registrations")->fetchColumn();
$att = (int)$pdo->query(
    "SELECT COUNT(*) FROM registrations WHERE attended = 1"
)->fetchColumn();
require 'includes/header.php';
?>
<h2 class="page-title mb-2">Thống kê và báo cáo</h2>
<p class="muted mb-4">Tổng hợp tình hình đăng ký và tham dự các sự kiện.</p>
<div class="row g-3 mb-4">
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="muted">Tổng lượt đăng ký</div>
            <div class="stat-number"><?= $total ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="muted">Tổng lượt tham dự</div>
            <div class="stat-number"><?= $att ?></div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card stat-card">
            <div class="muted">Tỷ lệ tham dự</div>
            <div class="stat-number">
                <?= $total ? round($att / $total * 100) : 0 ?>%
            </div>
        </div>
    </div>
</div>
<div class="card p-3">
    <h5 class="fw-bold mb-3">Báo cáo theo sự kiện</h5>
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Sự kiện</th>
                    <th>Sức chứa</th>
                    <th>Đăng ký</th>
                    <th>Tham dự</th>
                    <th>Tỷ lệ</th>
                    <th>Đánh giá TB</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r):
                $reg = (int)$r['registrations'];
                $att_e = (int)$r['attendees'];
            ?>
                <tr>
                    <td><?= h($r['title']) ?></td>
                    <td><?= (int)$r['capacity'] ?></td>
                    <td><?= $reg ?></td>
                    <td><?= $att_e ?></td>
                    <td><?= $reg ? round($att_e / $reg * 100) : 0 ?>%</td>
                    <td>
                        <?= $r['avg_rating'] !== null
                            ? number_format((float)$r['avg_rating'], 1)
                              . '/5 (' . (int)$r['survey_count'] . ' lượt)'
                            : 'Chưa có' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6" class="empty">Chưa có dữ liệu thống kê.</td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'includes/footer.php'; ?>