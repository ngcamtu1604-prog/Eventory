<?php
require_once 'config/database.php';
require_once 'config/auth.php';
require_once 'includes/security.php';
require_role(['admin', 'user']);

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
$editItem = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_require();
    $action = $_POST['action'] ?? '';
    if (!$selectedEvent) {
        $error = 'Bạn cần tạo sự kiện trước khi quản lý ngân sách.';
    } elseif ($action === 'save') {
        $id = (int)($_POST['item_id'] ?? 0);
        $name = trim($_POST['item_name'] ?? '');
        $category = trim($_POST['category'] ?? 'Khác');
        $quantity = (float)($_POST['quantity'] ?? 0);
        $unitCost = (float)($_POST['unit_cost'] ?? -1);
        $status = $_POST['payment_status'] ?? 'planned';
        $note = trim($_POST['note'] ?? '');
        if ($name === '' || mb_strlen($name)>180 || $quantity<=0 || $unitCost<0) {
            $error = 'Vui lòng nhập tên hạng mục, số lượng và đơn giá hợp lệ.';
        } elseif (!in_array($status,['planned','paid'],true)) {
            $error = 'Trạng thái thanh toán không hợp lệ.';
        } else {
            if ($id) {
                $stmt=$pdo->prepare("UPDATE event_budget_items SET item_name=?,category=?,quantity=?,unit_cost=?,payment_status=?,note=? WHERE id=? AND event_id=?");
                $stmt->execute([$name,$category,$quantity,$unitCost,$status,$note,$id,$selectedEventId]);
                flash_set('success','Đã cập nhật hạng mục ngân sách.');
            } else {
                $stmt=$pdo->prepare("INSERT INTO event_budget_items(event_id,item_name,category,quantity,unit_cost,payment_status,note,created_by) VALUES(?,?,?,?,?,?,?,?)");
                $stmt->execute([$selectedEventId,$name,$category,$quantity,$unitCost,$status,$note,$userId]);
                flash_set('success','Đã thêm hạng mục ngân sách.');
            }
            header('Location: budget.php?event_id='.$selectedEventId); exit;
        }
    } elseif ($action==='delete') {
        $stmt=$pdo->prepare("DELETE FROM event_budget_items WHERE id=? AND event_id=?");
        $stmt->execute([(int)$_POST['item_id'],$selectedEventId]);
        flash_set('success','Đã xóa hạng mục ngân sách.');
        header('Location: budget.php?event_id='.$selectedEventId); exit;
    }
}
$items=[];
$total=0; $paid=0;
if ($selectedEvent) {
    $stmt=$pdo->prepare("SELECT * FROM event_budget_items WHERE event_id=? ORDER BY category,item_name");
    $stmt->execute([$selectedEventId]); $items=$stmt->fetchAll();
    foreach($items as $item){$amount=(float)$item['quantity']*(float)$item['unit_cost'];$total+=$amount;if($item['payment_status']==='paid')$paid+=$amount;}
    if(isset($_GET['edit'])) foreach($items as $item) if((int)$item['id']===(int)$_GET['edit']) $editItem=$item;
}
require 'includes/header.php';
?>
<div class="module-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3">
        <div><h2 class="page-title mb-1">Ngân sách & chi phí</h2><p class="muted mb-0">Lập dự toán, theo dõi khoản đã chi và chi phí còn lại.</p></div>
        <?php if($events): ?><form method="get"><select class="form-select" name="event_id" onchange="this.form.submit()"><?php foreach($events as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)$e['id']===$selectedEventId?'selected':'' ?>><?= h($e['title']) ?></option><?php endforeach; ?></select></form><?php endif; ?>
    </div>
    <?php flash_render(); ?><?php if($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <?php if(!$events): ?><div class="card p-4 text-center"><h5>Chưa có sự kiện</h5><p class="muted">Tạo sự kiện để bắt đầu lập ngân sách.</p><a class="btn btn-primary mx-auto" href="event_form.php">Tạo sự kiện</a></div>
    <?php else: ?>
    <p class="small muted mb-3">Sự kiện: <strong><?= h($selectedEvent['title']) ?></strong></p>
    <div class="row g-3 mb-4">
        <div class="col-md-4"><div class="card stat-card"><div class="muted">Tổng dự toán</div><div class="stat-number"><?= eventory_money($total) ?></div></div></div>
        <div class="col-md-4"><div class="card stat-card"><div class="muted">Đã thanh toán</div><div class="stat-number"><?= eventory_money($paid) ?></div></div></div>
        <div class="col-md-4"><div class="card stat-card"><div class="muted">Chưa thanh toán</div><div class="stat-number"><?= eventory_money($total-$paid) ?></div></div></div>
    </div>
    <div class="card p-4 mb-4" id="budget-form"><h5 class="mb-3"><?= $editItem?'Chỉnh sửa hạng mục':'Thêm hạng mục chi phí' ?></h5>
        <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="item_id" value="<?= (int)($editItem['id']??0) ?>">
            <div class="col-md-4"><label class="form-label">Tên khoản chi</label><input class="form-control" name="item_name" maxlength="180" required value="<?= h($editItem['item_name']??'') ?>" placeholder="VD: Thuê âm thanh"></div>
            <div class="col-md-2"><label class="form-label">Danh mục</label><select class="form-select" name="category"><?php foreach(['Địa điểm','Thiết bị','Truyền thông','Nhân sự','Ăn uống','Di chuyển','Khác'] as $cat): ?><option <?= ($editItem['category']??'Khác')===$cat?'selected':'' ?>><?= h($cat) ?></option><?php endforeach; ?></select></div>
            <div class="col-md-2"><label class="form-label">Số lượng</label><input class="form-control" type="number" min="0.01" step="0.01" name="quantity" required value="<?= h($editItem['quantity']??'1') ?>"></div>
            <div class="col-md-2"><label class="form-label">Đơn giá (₫)</label><input class="form-control" type="number" min="0" step="1000" name="unit_cost" required value="<?= h($editItem['unit_cost']??'0') ?>"></div>
            <div class="col-md-2"><label class="form-label">Thanh toán</label><select class="form-select" name="payment_status"><option value="planned" <?= ($editItem['payment_status']??'planned')==='planned'?'selected':'' ?>>Dự kiến</option><option value="paid" <?= ($editItem['payment_status']??'planned')==='paid'?'selected':'' ?>>Đã chi</option></select></div>
            <div class="col-12"><label class="form-label">Ghi chú</label><input class="form-control" name="note" value="<?= h($editItem['note']??'') ?>"></div>
            <div class="col-12"><button class="btn btn-primary">Lưu khoản chi</button><?php if($editItem): ?> <a class="btn btn-outline-secondary" href="budget.php?event_id=<?= $selectedEventId ?>">Hủy sửa</a><?php endif; ?></div>
        </form>
    </div>
    <div class="card p-3"><h5 class="mb-3">Chi tiết ngân sách</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Khoản chi</th><th>Danh mục</th><th>Số lượng</th><th>Đơn giá</th><th>Thành tiền</th><th>Trạng thái</th><th></th></tr></thead><tbody>
    <?php foreach($items as $item): ?><tr><td><strong><?= h($item['item_name']) ?></strong><?php if($item['note']): ?><div class="small muted"><?= h($item['note']) ?></div><?php endif; ?></td><td><?= h($item['category']) ?></td><td><?= h($item['quantity']) ?></td><td><?= eventory_money($item['unit_cost']) ?></td><td><?= eventory_money($item['quantity']*$item['unit_cost']) ?></td><td><span class="badge text-bg-<?= $item['payment_status']==='paid'?'success':'warning' ?>"><?= $item['payment_status']==='paid'?'Đã chi':'Dự kiến' ?></span></td><td><a class="btn btn-sm btn-outline-secondary" href="budget.php?event_id=<?= $selectedEventId ?>&edit=<?= (int)$item['id'] ?>#budget-form">Sửa</a> <form method="post" class="d-inline" onsubmit="return confirm('Xóa khoản chi này?')"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr><?php endforeach; ?>
    <?php if(!$items): ?><tr><td colspan="7" class="text-center muted py-4">Chưa có khoản chi nào. Hãy thêm hạng mục đầu tiên.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
