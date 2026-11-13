<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_login();
$id = (int)($_GET['id'] ?? $_POST['event_id'] ?? 0);
$stmt = $pdo->prepare(
    "SELECT e.*
    FROM events e
    JOIN registrations r ON r.event_id = e.id
    WHERE e.id = ? AND r.user_id = ? AND r.attended = 1"
);
$stmt->execute([
    $id,
    $_SESSION['user']['id']
]);
$event = $stmt->fetch();
if (!$event) {
    exit('Bạn cần đăng ký và tham dự sự kiện để đánh giá.');
}
$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $rating = (int)($_POST['rating'] ?? 0);
    $comment = trim($_POST['comment'] ?? '');
    if ($rating < 1 || $rating > 5) {
        $message = 'Vui lòng chọn mức đánh giá từ 1 đến 5.';
    } else {
        $stmt = $pdo->prepare(
            "INSERT INTO surveys(event_id,user_id,rating,comment)
            VALUES(?,?,?,?)
            ON DUPLICATE KEY UPDATE
            rating=VALUES(rating),
            comment=VALUES(comment),
            created_at=NOW()"
        );
        $stmt->execute([
            $id,
            $_SESSION['user']['id'],
            $rating,
            $comment
        ]);
        $message = 'Cảm ơn bạn đã gửi đánh giá!';
    }
}
require 'includes/header.php';
?>
<h2 class="page-title mb-3">
    Đánh giá sự kiện
</h2>
<div class="card p-4" style="max-width:650px">
    <h5 class="fw-bold">
        <?= h($event['title']) ?>
    </h5>
    <?php if ($message): ?>
        <div class="alert alert-info">
            <?= h($message) ?>
        </div>
    <?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden"
               name="event_id"
               value="<?= $id ?>">
        <label class="form-label mt-3">
            Mức độ hài lòng
        </label>
        <select class="form-select mb-3"
                name="rating"
                required>
            <option value="">
                Chọn số sao
            </option>
            <?php for ($i = 5; $i >= 1; $i--): ?>
                <option value="<?= $i ?>">
                    <?= $i ?> / 5 sao
                </option>
            <?php endfor; ?>
        </select>
        <label class="form-label">
            Ý kiến của bạn
        </label>
        <textarea class="form-control mb-3"
                  name="comment"
                  rows="4"
                  placeholder="Chia sẻ cảm nhận của bạn..."></textarea>
        <button class="btn btn-primary">
            Gửi đánh giá
        </button>
    </form>
</div>
<?php require 'includes/footer.php'; ?>