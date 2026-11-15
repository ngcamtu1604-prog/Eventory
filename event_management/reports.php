<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_login();

$userId = (int)$_SESSION['user']['id'];
$isAdmin = ($_SESSION['user']['role'] ?? '') === 'admin';

$reportSql = "SELECT e.id,e.title,e.capacity,COUNT(r.id) registrations,
                     COALESCE(SUM(r.attended=1),0) attendees,
                     (SELECT AVG(s.rating) FROM surveys s WHERE s.event_id=e.id) avg_rating,
                     (SELECT COUNT(*) FROM surveys s2 WHERE s2.event_id=e.id) survey_count
              FROM events e LEFT JOIN registrations r ON r.event_id=e.id";
$reportParams = [];
if (!$isAdmin) {
    $reportSql .= " WHERE e.created_by = ?";
    $reportParams[] = $userId;
}
$reportSql .= " GROUP BY e.id ORDER BY e.event_date DESC";
$stmt = $pdo->prepare($reportSql);
$stmt->execute($reportParams);
$rows = $stmt->fetchAll();

if ($isAdmin) {
    $total = (int)$pdo->query('SELECT COUNT(*) FROM registrations')->fetchColumn();
    $att = (int)$pdo->query('SELECT COUNT(*) FROM registrations WHERE attended=1')->fetchColumn();
} else {
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=?");
    $countStmt->execute([$userId]);
    $total = (int)$countStmt->fetchColumn();

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM registrations r JOIN events e ON e.id=r.event_id WHERE e.created_by=? AND r.attended=1");
    $countStmt->execute([$userId]);
    $att = (int)$countStmt->fetchColumn();
}

$labels = array_map(fn($r) => $r['title'], $rows);
$registered = array_map(fn($r) => (int)$r['registrations'], $rows);
$attended = array_map(fn($r) => (int)$r['attendees'], $rows);

$ratingLabels = [];
$ratings = [];
foreach ($rows as $r) {
    if ($r['avg_rating'] !== null) {
        $ratingLabels[] = $r['title'];
        $ratings[] = round((float)$r['avg_rating'], 2);
    }
}

$overallRating = null;
if ($ratings) {
    $overallRating = round(array_sum($ratings) / count($ratings), 1);
}

require 'includes/header.php';
?>

<style>
/* EVENTORY - Báo cáo & thống kê */
.report-chart-card {
    height: 100%;
    padding: 22px 24px;
    background: var(--card-bg, #fff);
    border: 1px solid rgba(133,112,190,.13);
    border-radius: 18px;
    box-shadow: 0 7px 22px rgba(35,25,66,.045);
}
.report-chart-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 16px;
    margin-bottom: 18px;
}
.report-chart-heading h5 {
    margin: 0;
    color: var(--text-primary, #30264b);
    font-size: 17px;
    font-weight: 750;
}
.report-chart-subtitle {
    margin: 6px 0 0;
    color: var(--text-muted, #8b849d);
    font-size: 12px;
}
.report-chart-tag {
    flex: 0 0 auto;
    padding: 7px 12px;
    border-radius: 999px;
    background: #f0eaff;
    color: #6843b5;
    font-size: 11px;
    font-weight: 750;
}
.report-rating-summary {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 14px;
    color: #6d5a9f;
    font-size: 12px;
    font-weight: 650;
}
.report-rating-summary strong {
    color: #4b347f;
    font-size: 23px;
    line-height: 1;
}
.report-rating-chart-wrap {
    position: relative;
    width: 100%;
    height: 260px;
}
.report-rating-chart-wrap.is-tall {
    height: 320px;
}
.report-rating-empty {
    min-height: 210px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 9px;
    padding: 24px;
    border: 1px dashed #ded5f0;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(240,234,255,.55), rgba(249,247,253,.7));
    text-align: center;
}
.report-rating-empty-icon {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    border-radius: 13px;
    background: #eee7ff;
    color: #7958bd;
    font-size: 20px;
}
.report-rating-empty strong {
    color: var(--text-primary, #403751);
    font-size: 14px;
}
.report-rating-empty span {
    max-width: 360px;
    color: var(--text-muted, #8b849d);
    font-size: 12px;
}
@media(max-width: 600px) {
    .report-chart-card { padding: 17px; }
    .report-chart-heading { flex-direction: column; }
    .report-rating-chart-wrap { height: 230px; }
}
</style>

<h2 class="page-title mb-2">Thống kê và báo cáo</h2>
<p class="muted mb-4">Tổng hợp tình hình đăng ký, tham dự và đánh giá trên phạm vi bạn được phép quản lý.</p>

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
            <div class="stat-number"><?= $total ? round($att / $total * 100) : 0 ?>%</div>
        </div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-lg-7">
        <div class="report-chart-card">
            <div class="report-chart-heading">
                <div>
                    <h5>Đăng ký và tham dự theo sự kiện</h5>
                    <p class="report-chart-subtitle">So sánh số lượt đăng ký với số người đã tham dự</p>
                </div>
            </div>
            <div style="position:relative;height:280px">
                <canvas id="attendanceChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-lg-5">
        <div class="report-chart-card">
            <div class="report-chart-heading">
                <div>
                    <h5>Tỷ lệ tham dự</h5>
                    <p class="report-chart-subtitle">Tỷ trọng người tham dự và vắng mặt</p>
                </div>
            </div>
            <div style="position:relative;height:280px">
                <canvas id="ratioChart"></canvas>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="report-chart-card">
            <div class="report-chart-heading">
                <div>
                    <h5>Điểm đánh giá trung bình</h5>
                    <p class="report-chart-subtitle">Tổng hợp điểm đánh giá của người tham gia theo từng sự kiện</p>
                </div>
                <span class="report-chart-tag">Thang điểm 5</span>
            </div>

            <?php if ($ratings): ?>
                <div class="report-rating-summary">
                    <strong><?= number_format($overallRating, 1) ?>/5</strong>
                    <span>Điểm trung bình trên <?= count($ratings) ?> sự kiện có đánh giá</span>
                </div>
                <div class="report-rating-chart-wrap <?= count($ratings) > 4 ? 'is-tall' : '' ?>">
                    <canvas id="ratingChart"></canvas>
                </div>
            <?php else: ?>
                <div class="report-rating-empty">
                    <div class="report-rating-empty-icon">☆</div>
                    <strong>Chưa có đánh giá nào</strong>
                    <span>Biểu đồ sẽ hiển thị khi người tham gia gửi đánh giá sau sự kiện.</span>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<div class="card p-3">
    <h5 class="fw-bold mb-3">Báo cáo chi tiết theo sự kiện</h5>
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
                    $a = (int)$r['attendees'];
                ?>
                    <tr>
                        <td><?= h($r['title']) ?></td>
                        <td><?= (int)$r['capacity'] ?></td>
                        <td><?= $reg ?></td>
                        <td><?= $a ?></td>
                        <td><?= $reg ? round($a / $reg * 100) : 0 ?>%</td>
                        <td><?= $r['avg_rating'] !== null ? number_format((float)$r['avg_rating'], 1) . '/5 (' . (int)$r['survey_count'] . ' lượt)' : 'Chưa có' ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$rows): ?>
                    <tr><td colspan="6" class="empty">Chưa có dữ liệu thống kê.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const labels = <?= json_encode($labels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const registered = <?= json_encode($registered) ?>;
const attended = <?= json_encode($attended) ?>;
const ratingLabels = <?= json_encode($ratingLabels, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
const ratings = <?= json_encode($ratings) ?>;

if (window.Chart) {
    const commonLegend = {
        position: 'bottom',
        labels: { usePointStyle: true, pointStyle: 'circle', boxWidth: 8, padding: 18 }
    };

    new Chart(document.getElementById('attendanceChart'), {
        type: 'bar',
        data: {
            labels,
            datasets: [
                { label: 'Đăng ký', data: registered, backgroundColor: '#b6a5e5', borderRadius: 7, maxBarThickness: 24 },
                { label: 'Đã tham dự', data: attended, backgroundColor: '#7155ad', borderRadius: 7, maxBarThickness: 24 }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: commonLegend },
            scales: {
                x: { grid: { display: false }, ticks: { color: '#817894' } },
                y: { beginAtZero: true, ticks: { precision: 0, color: '#817894' }, grid: { color: 'rgba(133,112,190,.13)' } }
            }
        }
    });

    new Chart(document.getElementById('ratioChart'), {
        type: 'doughnut',
        data: {
            labels: ['Đã tham dự', 'Vắng mặt'],
            datasets: [{
                data: [<?= $att ?>, <?= max(0, $total - $att) ?>],
                backgroundColor: ['#8064bd', '#e8e2f2'],
                borderWidth: 0,
                hoverOffset: 5
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: { legend: commonLegend }
        }
    });

    <?php if ($ratings): ?>
    new Chart(document.getElementById('ratingChart'), {
        type: 'bar',
        data: {
            labels: ratingLabels,
            datasets: [{
                label: 'Điểm đánh giá',
                data: ratings,
                backgroundColor: ratings.map((value, index) => index % 2 === 0 ? '#9b86d0' : '#b8a8df'),
                borderRadius: 8,
                maxBarThickness: 30
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: { callbacks: { label: context => ` ${context.parsed.x.toFixed(1)} / 5 điểm` } }
            },
            scales: {
                x: {
                    min: 0,
                    max: 5,
                    ticks: { stepSize: 1, color: '#817894' },
                    grid: { color: 'rgba(133,112,190,.13)' }
                },
                y: {
                    ticks: { color: '#51466e', autoSkip: false },
                    grid: { display: false }
                }
            }
        }
    });
    <?php endif; ?>
}
</script>

<?php require 'includes/footer.php'; ?>
