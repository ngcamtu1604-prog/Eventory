<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_login();
$stmt = $pdo->prepare(
    "SELECT e.*, r.attendance_code, r.attended
    FROM registrations r
    JOIN events e ON e.id = r.event_id
    WHERE r.user_id = ?
    ORDER BY e.event_date DESC"
);
$stmt->execute([$_SESSION['user']['id']]);
$rows = $stmt->fetchAll();
require 'includes/header.php';
?>
<h2 class="page-title mb-3">
    Vé của tôi
</h2>
<p class="muted mb-4">
    Theo dõi vé và mã tham dự của những sự kiện bạn đã đăng ký.
</p>
<div class="card p-3">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Sự kiện</th>
                    <th>Thời gian</th>
                    <th>Mã tham dự</th>
                    <th>Điểm danh</th>
                    <th>Đánh giá</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['title']) ?><div class="mt-1"><span class="badge" style="background:#e1f5e9;color:#237447">Participant</span></div></td>
                    <td>
                        <?= date('d/m/Y H:i', strtotime($r['event_date'])) ?>
                    </td>
                    <td>
                        <span class="code-box fs-6">
                            <?= h($r['attendance_code']) ?>
                        </span>
                    </td>
                    <td>
                        <?= $r['attended']
                            ? 'Đã tham dự'
                            : 'Chưa điểm danh' ?>
                    </td>
                    <td>
                        <?php if ($r['attended']): ?>
                            <a href="survey.php?id=<?= $r['id'] ?>">
                                Đánh giá
                            </a>
                        <?php else: ?>
                            <span class="muted">
                                Chưa thể đánh giá
                            </span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="5" class="empty">
                        Bạn chưa đăng ký sự kiện nào.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'includes/footer.php'; ?>