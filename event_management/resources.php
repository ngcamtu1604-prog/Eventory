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

$error=''; $editResource=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
    csrf_require(); $action=$_POST['action']??'';
    if(!$selectedEvent){$error='Bạn cần tạo sự kiện trước khi quản lý tài nguyên.';}
    elseif($action==='save'){
        $id=(int)($_POST['resource_id']??0);$name=trim($_POST['resource_name']??'');$category=trim($_POST['category']??'Thiết bị');
        $quantity=(int)($_POST['quantity']??0);$unit=trim($_POST['unit']??'cái');$status=$_POST['resource_status']??'planned';
        $responsible=trim($_POST['responsible']??'');$note=trim($_POST['note']??'');
        if($name===''||mb_strlen($name)>180||$quantity<1){$error='Vui lòng nhập tên tài nguyên và số lượng hợp lệ.';}
        elseif(!in_array($status,['planned','ready','in_use','returned'],true)){$error='Trạng thái tài nguyên không hợp lệ.';}
        else{
            if($id){$stmt=$pdo->prepare("UPDATE event_resources SET resource_name=?,category=?,quantity=?,unit=?,resource_status=?,responsible=?,note=? WHERE id=? AND event_id=?");$stmt->execute([$name,$category,$quantity,$unit,$status,$responsible?:null,$note,$id,$selectedEventId]);flash_set('success','Đã cập nhật tài nguyên.');}
            else{$stmt=$pdo->prepare("INSERT INTO event_resources(event_id,resource_name,category,quantity,unit,resource_status,responsible,note,created_by) VALUES(?,?,?,?,?,?,?,?,?)");$stmt->execute([$selectedEventId,$name,$category,$quantity,$unit,$status,$responsible?:null,$note,$userId]);flash_set('success','Đã thêm tài nguyên.');}
            header('Location: resources.php?event_id='.$selectedEventId);exit;
        }
    }elseif($action==='delete'){
        $stmt=$pdo->prepare("DELETE FROM event_resources WHERE id=? AND event_id=?");$stmt->execute([(int)$_POST['resource_id'],$selectedEventId]);
        flash_set('success','Đã xóa tài nguyên.');header('Location: resources.php?event_id='.$selectedEventId);exit;
    }
}
$items=[];
if($selectedEvent){$stmt=$pdo->prepare("SELECT * FROM event_resources WHERE event_id=? ORDER BY category,resource_name");$stmt->execute([$selectedEventId]);$items=$stmt->fetchAll();if(isset($_GET['edit']))foreach($items as $it)if((int)$it['id']===(int)$_GET['edit'])$editResource=$it;}
$statuses=['planned'=>'Cần chuẩn bị','ready'=>'Đã sẵn sàng','in_use'=>'Đang sử dụng','returned'=>'Đã hoàn trả'];
require 'includes/header.php';
?>
<div class="module-page">
    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3 mb-3"><div><h2 class="page-title mb-1">Quản lý tài nguyên</h2><p class="muted mb-0">Theo dõi thiết bị, vật tư và người phụ trách cho từng sự kiện.</p></div>
    <?php if($events): ?><form method="get"><select class="form-select" name="event_id" onchange="this.form.submit()"><?php foreach($events as $e): ?><option value="<?= (int)$e['id'] ?>" <?= (int)$e['id']===$selectedEventId?'selected':'' ?>><?= h($e['title']) ?></option><?php endforeach; ?></select></form><?php endif; ?></div>
    <?php flash_render(); ?><?php if($error): ?><div class="alert alert-danger"><?= h($error) ?></div><?php endif; ?>
    <?php if(!$events): ?><div class="card p-4 text-center"><h5>Chưa có sự kiện</h5><p class="muted">Tạo sự kiện để lập danh sách tài nguyên cần chuẩn bị.</p><a class="btn btn-primary mx-auto" href="event_form.php">Tạo sự kiện</a></div>
    <?php else: ?>
    <p class="small muted mb-3">Sự kiện: <strong><?= h($selectedEvent['title']) ?></strong> · <?= count($items) ?> hạng mục tài nguyên</p>
    <div class="card p-4 mb-4" id="resource-form"><h5 class="mb-3"><?= $editResource?'Chỉnh sửa tài nguyên':'Thêm tài nguyên' ?></h5>
    <form method="post" class="row g-3"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="resource_id" value="<?= (int)($editResource['id']??0) ?>">
        <div class="col-md-4"><label class="form-label">Tên tài nguyên</label><input class="form-control" name="resource_name" maxlength="180" required value="<?= h($editResource['resource_name']??'') ?>" placeholder="VD: Máy chiếu"></div>
        <div class="col-md-2"><label class="form-label">Danh mục</label><select class="form-select" name="category"><?php foreach(['Thiết bị','Vật tư','Địa điểm','Nhân sự','Truyền thông','Khác'] as $cat): ?><option <?= ($editResource['category']??'Thiết bị')===$cat?'selected':'' ?>><?= h($cat) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-2"><label class="form-label">Số lượng</label><input class="form-control" type="number" min="1" name="quantity" required value="<?= h($editResource['quantity']??1) ?>"></div>
        <div class="col-md-2"><label class="form-label">Đơn vị</label><input class="form-control" name="unit" maxlength="40" value="<?= h($editResource['unit']??'cái') ?>"></div>
        <div class="col-md-2"><label class="form-label">Trạng thái</label><select class="form-select" name="resource_status"><?php foreach($statuses as $key=>$label): ?><option value="<?= $key ?>" <?= ($editResource['resource_status']??'planned')===$key?'selected':'' ?>><?= h($label) ?></option><?php endforeach; ?></select></div>
        <div class="col-md-4"><label class="form-label">Người phụ trách</label><input class="form-control" name="responsible" maxlength="120" value="<?= h($editResource['responsible']??'') ?>"></div>
        <div class="col-md-8"><label class="form-label">Ghi chú</label><input class="form-control" name="note" value="<?= h($editResource['note']??'') ?>"></div>
        <div class="col-12"><button class="btn btn-primary">Lưu tài nguyên</button><?php if($editResource): ?> <a class="btn btn-outline-secondary" href="resources.php?event_id=<?= $selectedEventId ?>">Hủy sửa</a><?php endif; ?></div>
    </form></div>
    <div class="card p-3"><h5 class="mb-3">Danh sách tài nguyên</h5><div class="table-responsive"><table class="table align-middle"><thead><tr><th>Tài nguyên</th><th>Danh mục</th><th>Số lượng</th><th>Phụ trách</th><th>Trạng thái</th><th></th></tr></thead><tbody>
    <?php foreach($items as $it): ?><tr><td><strong><?= h($it['resource_name']) ?></strong><?php if($it['note']): ?><div class="small muted"><?= h($it['note']) ?></div><?php endif; ?></td><td><?= h($it['category']) ?></td><td><?= (int)$it['quantity'].' '.h($it['unit']) ?></td><td><?= h($it['responsible']??'—') ?></td><td><span class="badge text-bg-<?= $it['resource_status']==='ready'?'success':($it['resource_status']==='in_use'?'primary':($it['resource_status']==='returned'?'secondary':'warning')) ?>"><?= h($statuses[$it['resource_status']]) ?></span></td><td><a class="btn btn-sm btn-outline-secondary" href="resources.php?event_id=<?= $selectedEventId ?>&edit=<?= (int)$it['id'] ?>#resource-form">Sửa</a> <form method="post" class="d-inline" onsubmit="return confirm('Xóa tài nguyên này?')"><?= csrf_field() ?><input type="hidden" name="event_id" value="<?= $selectedEventId ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="resource_id" value="<?= (int)$it['id'] ?>"><button class="btn btn-sm btn-outline-danger">Xóa</button></form></td></tr><?php endforeach; ?>
    <?php if(!$items): ?><tr><td colspan="6" class="text-center muted py-4">Chưa có tài nguyên. Hãy thêm thiết bị hoặc vật tư cần chuẩn bị.</td></tr><?php endif; ?>
    </tbody></table></div></div>
    <?php endif; ?>
</div>
<?php require 'includes/footer.php'; ?>
