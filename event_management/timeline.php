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

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    if (!$selectedEvent) {
        $error = 'Bạn cần tạo sự kiện trước khi thêm công việc.';
    } elseif ($action === 'save_task') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $status = $_POST['status'] ?? 'todo';
        $priority = $_POST['priority'] ?? 'medium';
        $dueDate = trim($_POST['due_date'] ?? '');
        $assignee = trim($_POST['assignee'] ?? '');
        if ($title === '' || mb_strlen($title) > 180) {
            $error = 'Tên công việc không được để trống và tối đa 180 ký tự.';
        } elseif (!in_array($status, ['todo','doing','done'], true) ||
                  !in_array($priority, ['low','medium','high'], true)) {
            $error = 'Trạng thái hoặc mức ưu tiên không hợp lệ.';
        } else {
            $dueDate = $dueDate !== '' ? $dueDate : null;
            if ($taskId) {
                $sql = "UPDATE event_tasks SET title=?,description=?,status=?,priority=?,due_date=?,assignee=? WHERE id=? AND event_id=?";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$title,$description,$status,$priority,$dueDate,$assignee ?: null,$taskId,$selectedEventId]);
            } else {
                $sql = "INSERT INTO event_tasks(event_id,title,description,status,priority,due_date,assignee,created_by) VALUES(?,?,?,?,?,?,?,?)";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$selectedEventId,$title,$description,$status,$priority,$dueDate,$assignee ?: null,$userId]);
            }
            flash_set('success', $taskId ? 'Đã cập nhật công việc.' : 'Đã thêm công việc.');
            header('Location: timeline.php?event_id=' . $selectedEventId);
            exit;
        }
    } elseif ($action === 'change_status') {
        $taskId = (int)($_POST['task_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        if (in_array($status, ['todo','doing','done'], true)) {
            $stmt = $pdo->prepare("UPDATE event_tasks SET status=? WHERE id=? AND event_id=?");
            $stmt->execute([$status,$taskId,$selectedEventId]);
            flash_set('success', 'Đã cập nhật trạng thái công việc.');
        }
        header('Location: timeline.php?event_id=' . $selectedEventId);
        exit;
    } elseif ($action === 'delete_task') {
        $stmt = $pdo->prepare("DELETE FROM event_tasks WHERE id=? AND event_id=?");
        $stmt->execute([(int)($_POST['task_id'] ?? 0),$selectedEventId]);
        flash_set('success', 'Đã xóa công việc.');
        header('Location: timeline.php?event_id=' . $selectedEventId);
        exit;
    }
}

$tasks = [];
$editTask = null;
if ($selectedEvent) {
    $stmt = $pdo->prepare("SELECT * FROM event_tasks WHERE event_id=? ORDER BY COALESCE(due_date,'9999-12-31'),created_at DESC");
    $stmt->execute([$selectedEventId]);
    $tasks = $stmt->fetchAll();
    if (isset($_GET['edit'])) {
        foreach ($tasks as $task) if ((int)$task['id'] === (int)$_GET['edit']) $editTask = $task;
    }
}
$columns = [
    'todo' => ['Việc cần làm','secondary'],
    'doing' => ['Đang thực hiện','primary'],
    'done' => ['Hoàn thành','success']
];
require 'includes/header.php';
?>
<div class="module-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><h2 class="page-title mb-1">Timeline & Kanban</h2><p class="muted mb-0">Lập kế hoạch và theo dõi tiến độ công việc cho từng sự kiện.</p></div>
        <?php if ($events): ?>
        <form method="get" class="d-flex gap-2 align-items-center">
            <select class="form-select" name="event_id" onchange="this.form.submit()">
                <?php foreach ($events as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)$e['id']===$selectedEventId?'selected':'' ?>><?= h($e['title']) ?></option><?php endforeach; ?>
            </select>
        </form>
        <?php endif; ?>
    </div>
    <?php flash_render(); ?>
    <?php if ($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <?php if (!$events): ?>
        <div class="card p-4 text-center"><h5>Chưa có sự kiện để lập kế hoạch</h5><p class="muted">Hãy tạo sự kiện trước, sau đó bạn có thể thêm đầu việc và theo dõi tiến độ.</p><a class="btn btn-primary mx-auto" href="event_form.php">Tạo sự kiện</a></div>
    <?php else: ?>
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="small muted">Sự kiện: <strong><?= h($selectedEvent['title']) ?></strong> · <?= count($tasks) ?> công việc</div>
            <a class="btn btn-primary btn-sm" href="timeline.php?event_id=<?= $selectedEventId ?>#task-form">+ Thêm công việc</a>
        </div>
        <div class="row g-3 mb-4">
            <?php foreach ($columns as $key=>$column): $count=count(array_filter($tasks,fn($t)=>$t['status']===$key)); ?>
            <div class="col-lg-4"><div class="kanban-column h-100">
                <div class="d-flex justify-content-between align-items-center mb-3"><strong><?= h($column[0]) ?></strong><span class="badge text-bg-<?= $column[1] ?>"><?= $count ?></span></div>
                <?php foreach ($tasks as $task): if ($task['status']!==$key) continue; ?>
                <div class="kanban-task">
                    <div class="d-flex justify-content-between gap-2"><strong><?= h($task['title']) ?></strong><span class="small"><?= $task['priority']==='high'?'🔴':($task['priority']==='low'?'🟢':'🟡') ?></span></div>
                    <?php if ($task['description']): ?><p class="small muted mt-2 mb-2"><?= nl2br(h($task['description'])) ?></p><?php endif; ?>
                    <div class="small muted mb-2"><?= $task['due_date'] ? 'Hạn: '.date('d/m/Y',strtotime($task['due_date'])) : 'Chưa đặt hạn' ?><?= $task['assignee'] ? ' · '.h($task['assignee']) : '' ?></div>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-secondary" href="timeline.php?event_id=<?= $selectedEventId ?>&edit=<?= (int)$task['id'] ?>#task-form">Sửa</a>
                        <form method="post" class="d-flex gap-1"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><input type="hidden" name="action" value="change_status"><select class="form-select form-select-sm" name="status" onchange="this.form.submit()"><option value="todo" <?= $key==='todo'?'selected':'' ?>>Cần làm</option><option value="doing" <?= $key==='doing'?'selected':'' ?>>Đang làm</option><option value="done" <?= $key==='done'?'selected':'' ?>>Hoàn thành</option></select></form>
                        <form method="post" onsubmit="return confirm('Xóa công việc này?')"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><input type="hidden" name="action" value="delete_task"><button class="btn btn-sm btn-outline-danger">Xóa</button></form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if (!$count): ?><div class="small muted text-center py-4">Chưa có công việc</div><?php endif; ?>
            </div></div>
            <?php endforeach; ?>
        </div>
        <div class="card p-4" id="task-form">
            <h5 class="mb-3"><?= $editTask ? 'Chỉnh sửa công việc' : 'Thêm công việc mới' ?></h5>
            <form method="post" class="row g-3">
                <?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="action" value="save_task"><input type="hidden" name="task_id" value="<?= (int)($editTask['id']??0) ?>">
                <div class="col-md-6"><label class="form-label">Tên công việc</label><input class="form-control" name="title" maxlength="180" required value="<?= h($editTask['title']??'') ?>"></div>
                <div class="col-md-3"><label class="form-label">Trạng thái</label><select class="form-select" name="status"><?php foreach ($columns as $key=>$col): ?><option value="<?= $key ?>" <?= ($editTask['status']??'todo')===$key?'selected':'' ?>><?= h($col[0]) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-3"><label class="form-label">Ưu tiên</label><select class="form-select" name="priority"><option value="low" <?= ($editTask['priority']??'medium')==='low'?'selected':'' ?>>Thấp</option><option value="medium" <?= ($editTask['priority']??'medium')==='medium'?'selected':'' ?>>Trung bình</option><option value="high" <?= ($editTask['priority']??'medium')==='high'?'selected':'' ?>>Cao</option></select></div>
                <div class="col-md-4"><label class="form-label">Hạn hoàn thành</label><input type="date" class="form-control" name="due_date" value="<?= h($editTask['due_date']??'') ?>"></div>
                <div class="col-md-4"><label class="form-label">Người phụ trách</label><input class="form-control" name="assignee" maxlength="120" value="<?= h($editTask['assignee']??'') ?>"></div>
                <div class="col-12"><label class="form-label">Mô tả</label><textarea class="form-control" rows="2" name="description"><?= h($editTask['description']??'') ?></textarea></div>
                <div class="col-12"><button class="btn btn-primary">Lưu công việc</button><?php if ($editTask): ?> <a class="btn btn-outline-secondary" href="timeline.php?event_id=<?= $selectedEventId ?>">Hủy sửa</a><?php endif; ?></div>
            </form>
        </div>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
