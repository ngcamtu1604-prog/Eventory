<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_admin();
$rows = $pdo->query(
    "SELECT r.*, u.full_name, u.email, e.title
    FROM registrations r
    JOIN users u ON u.id = r.user_id
    JOIN events e ON e.id = r.event_id
    ORDER BY r.registered_at DESC"
)->fetchAll();
require 'includes/header.php';
?>
<h2 class="page-title mb-2">
    Quản lý đăng ký
</h2>
<p class="muted mb-4">
    Danh sách người tham gia đã đăng ký sự kiện.
</p>
<div class="card p-3">
    <div class="table-responsive">
        <table class="table">
            <thead>
                <tr>
                    <th>Người tham gia</th>
                    <th>Email</th>
                    <th>Sự kiện</th>
                    <th>Mã tham dự</th>
                    <th>Ngày đăng ký</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= h($r['full_name']) ?></td>
                    <td><?= h($r['email']) ?></td>
                    <td><?= h($r['title']) ?></td>
                    <td>
                        <strong><?= h($r['attendance_code']) ?></strong>
                    </td>
                    <td>
                        <?= date('d/m/Y H:i', strtotime($r['registered_at'])) ?>
                    </td>
                    <td>
                        <?= $r['attended']
                            ? 'Đã tham dự'
                            : 'Chưa tham dự' ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$rows): ?>
                <tr>
                    <td colspan="6" class="empty">
                        Chưa có lượt đăng ký.
                    </td>
                </tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php require 'includes/footer.php'; ?>