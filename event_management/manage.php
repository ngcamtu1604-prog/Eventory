<?php
require_once 'config/database.php'; require_once 'config/auth.php'; require_once 'includes/security.php'; require_admin();
if ($_SERVER['REQUEST_METHOD']==='POST') {
 csrf_require(); $action=$_POST['action']??''; $id=(int)($_POST['id']??0); $type=$_POST['type']??'';
 if (in_array($type,['speaker','organizer'],true) && in_array($action,['save','delete'],true)) {
  $table=$type==='speaker'?'speakers':'organizers';
  if($action==='delete') { $st=$pdo->prepare("DELETE FROM {$table} WHERE id=?");$st->execute([$id]);flash_set('success','Đã xóa dữ liệu.'); }
  else { $name=trim($_POST['name']??'');$info=trim($_POST['info']??'');
   if($name===''||mb_strlen($name)>150) flash_set('danger','Tên không được để trống và tối đa 150 ký tự.');
   elseif($id>0){$st=$pdo->prepare("UPDATE {$table} SET name=?,info=? WHERE id=?");$st->execute([$name,$info,$id]);flash_set('success','Đã cập nhật thông tin.');}
   else {$st=$pdo->prepare("INSERT INTO {$table}(name,info) VALUES(?,?)");$st->execute([$name,$info]);flash_set('success','Đã thêm thông tin.');}
  }
 }
 header('Location: manage.php');exit;
}
$editType=$_GET['type']??'';$editId=(int)($_GET['edit']??0);$editing=null;
if($editId && in_array($editType,['speaker','organizer'],true)){$table=$editType==='speaker'?'speakers':'organizers';$st=$pdo->prepare("SELECT * FROM {$table} WHERE id=?");$st->execute([$editId]);$editing=$st->fetch();}
$speakers=$pdo->query('SELECT * FROM speakers ORDER BY id DESC')->fetchAll();$organizers=$pdo->query('SELECT * FROM organizers ORDER BY id DESC')->fetchAll();require 'includes/header.php';
?>
<h2 class="page-title mb-4">Quản lý diễn giả và ban tổ chức</h2><?php flash_render(); ?>
<div class="row g-3"><div class="col-lg-4"><div class="card p-3"><h5><?= $editing?'Chỉnh sửa thông tin':'Thêm thông tin' ?></h5><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int)($editing['id']??0) ?>">
<label class="form-label">Loại thông tin</label><select class="form-select mb-3" name="type"><option value="speaker" <?= ($editType?:'speaker')==='speaker'?'selected':'' ?>>Diễn giả</option><option value="organizer" <?= $editType==='organizer'?'selected':'' ?>>Ban tổ chức</option></select>
<label class="form-label">Tên</label><input class="form-control mb-3" name="name" maxlength="150" required value="<?= h($editing['name']??'') ?>"><label class="form-label">Thông tin mô tả</label><textarea class="form-control mb-3" name="info" rows="3"><?= h($editing['info']??'') ?></textarea><button class="btn btn-primary"><?= $editing?'Lưu thay đổi':'Thêm mới' ?></button><?php if($editing): ?> <a href="manage.php" class="btn btn-outline-secondary">Hủy</a><?php endif; ?></form></div></div>
<div class="col-lg-8"><div class="card p-3 mb-3"><h5>Danh sách diễn giả</h5><div class="table-responsive"><table class="table"><thead><tr><th>Tên</th><th>Thông tin</th><th>Thao tác</th></tr></thead><tbody><?php foreach($speakers as $s): ?><tr><td><?= h($s['name']) ?></td><td><?= h($s['info']) ?></td><td><a href="manage.php?type=speaker&edit=<?= (int)$s['id'] ?>">Sửa</a> <form method="post" class="d-inline" onsubmit="return confirm('Xóa diễn giả này?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="speaker"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button class="btn btn-link text-danger p-0">Xóa</button></form></td></tr><?php endforeach; ?><?php if(!$speakers): ?><tr><td colspan="3">Chưa có diễn giả.</td></tr><?php endif; ?></tbody></table></div></div>
<div class="card p-3"><h5>Danh sách ban tổ chức</h5><div class="table-responsive"><table class="table"><thead><tr><th>Tên đơn vị</th><th>Thông tin</th><th>Thao tác</th></tr></thead><tbody><?php foreach($organizers as $o): ?><tr><td><?= h($o['name']) ?></td><td><?= h($o['info']) ?></td><td><a href="manage.php?type=organizer&edit=<?= (int)$o['id'] ?>">Sửa</a> <form method="post" class="d-inline" onsubmit="return confirm('Xóa ban tổ chức này?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="type" value="organizer"><input type="hidden" name="id" value="<?= (int)$o['id'] ?>"><button class="btn btn-link text-danger p-0">Xóa</button></form></td></tr><?php endforeach; ?><?php if(!$organizers): ?><tr><td colspan="3">Chưa có ban tổ chức.</td></tr><?php endif; ?></tbody></table></div></div></div></div>
<?php require 'includes/footer.php'; ?>
