<?php
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/accounts.php';require dirname(__DIR__).'/app/admin-layout.php';require_admin();
$error='';$draft=null;$ready=management_ready(db());$rolesReady=account_roles_ready(db());
try {
 if($_SERVER['REQUEST_METHOD']==='POST') {
  check_csrf();$action=(string)($_POST['action'] ?? '');
  try {
   if($action==='enable_roles'){upgrade_hub(db());$_SESSION['flash']='เปิดใช้งานบัญชีและโปรไฟล์แล้ว';}
   elseif($action==='set_active') {
    if(!$ready)throw new InvalidArgumentException('อัปเดตโครงสร้างก่อนจัดการสถานะบัญชี');
    if(!in_array($_POST['active'] ?? '',['0','1'],true))throw new InvalidArgumentException('สถานะไม่ถูกต้อง');
    set_account_active(db(),(int)($_POST['id'] ?? 0),$_POST['active']==='1');$_SESSION['flash']='เปลี่ยนสถานะบัญชีแล้ว';
   }elseif($action==='save_user') {
    $draft=['id'=>(int)($_POST['id'] ?? 0),'username'=>trim((string)($_POST['username'] ?? '')),'role'=>(string)($_POST['role'] ?? '')];
    if($draft['id']===(int)$_SESSION['admin_id'])throw new InvalidArgumentException('แก้บัญชีของคุณได้จากหน้า “บัญชีของฉัน”');
    $profile=$ready?profile_input($_POST):null;if($profile)$draft=array_merge($draft,$profile);
    save_hub_account(db(),$draft['id'],$draft['username'],(string)($_POST['password'] ?? ''),(string)($_POST['confirm_password'] ?? ''),$draft['role'],$profile);
    $_SESSION['flash']=$draft['id']?'บันทึกบัญชีเรียบร้อยแล้ว':'เพิ่มบัญชีเรียบร้อยแล้ว';
   }else throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
   header('Location: users.php');exit;
  }catch(InvalidArgumentException $err){$error=$err->getMessage();}
 }
 $q=mb_substr(trim((string)($_GET['q'] ?? '')),0,100);$role=(string)($_GET['role'] ?? '');$status=(string)($_GET['status'] ?? '');$where=[];$values=[];
 if($q!==''){$where[]=$ready?'(username LIKE ? OR display_name LIKE ? OR email LIKE ?)':'username LIKE ?';$values=array_fill(0,$ready?3:1,'%'.$q.'%');}
 if($rolesReady && in_array($role,['admin','teacher'],true)){$where[]='role=?';$values[]=$role;}
 if($ready && in_array($status,['0','1'],true)){$where[]='active=?';$values[]=(int)$status;}
 $sql=$where?' WHERE '.implode(' AND ',$where):'';$stmt=db()->prepare('SELECT COUNT(*) FROM admins'.$sql);$stmt->execute($values);$total=(int)$stmt->fetchColumn();$page=max(1,min((int)($_GET['page'] ?? 1),max(1,(int)ceil($total/25))));
 $stmt=db()->prepare('SELECT id,username'.($rolesReady?',role':",'admin' AS role").($ready?',display_name,email,active,avatar_file':''). ' FROM admins'.$sql.' ORDER BY id DESC LIMIT 25 OFFSET '.(($page-1)*25));$stmt->execute($values);$users=$stmt->fetchAll();
 $edit=$draft ?? ['id'=>0,'username'=>'','role'=>'teacher'];
 if(!$draft && isset($_GET['edit'])){$stmt=db()->prepare('SELECT * FROM admins WHERE id=?');$stmt->execute([(int)$_GET['edit']]);$found=$stmt->fetch();if($found)$edit=$found;else $error='ไม่พบบัญชีที่ต้องการแก้ไข';if((int)$edit['id']===(int)$_SESSION['admin_id']){header('Location: account.php');exit;}}
}catch(Throwable $err){unavailable($err);}
admin_open('users','ผู้ใช้และสิทธิ์','จัดการบัญชีทีมงาน โปรไฟล์ และสถานะการเข้าถึงระบบ');admin_feedback($error); ?>
<?php if(!$rolesReady): ?><section class="panel"><h2>เปิดใช้งานบัญชีครู</h2><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="button" name="action" value="enable_roles">เปิดใช้งานบัญชีครู</button></form></section><?php else: ?>
<div class="workspace-columns"><section class="panel"><div class="panel-heading"><h2>บัญชีทั้งหมด <span class="count"><?= number_format($total) ?></span></h2><a href="users.php#user-editor">เพิ่มผู้ใช้</a></div><form class="catalog-toolbar" method="get"><input name="q" type="search" aria-label="ค้นหาบัญชี" placeholder="ชื่อผู้ใช้ ชื่อจริง หรืออีเมล" value="<?= e($q) ?>"><select name="role" aria-label="กรองสิทธิ์"><option value="">ทุกสิทธิ์</option><option value="admin" <?= $role==='admin'?'selected':'' ?>>ผู้ดูแล</option><option value="teacher" <?= $role==='teacher'?'selected':'' ?>>ครูและบุคลากร</option></select><select name="status" aria-label="กรองสถานะ"><option value="">ทุกสถานะ</option><option value="1" <?= $status==='1'?'selected':'' ?>>ใช้งานอยู่</option><option value="0" <?= $status==='0'?'selected':'' ?>>ปิดบัญชี</option></select><button class="button secondary">ค้นหา</button><a href="users.php">ล้าง</a></form><div class="admin-list">
<?php foreach($users as $user):$self=(int)$user['id']===(int)$_SESSION['admin_id']; ?><div class="admin-row"><?= account_avatar($user) ?><div class="row-info"><strong><?= e(($user['display_name'] ?? '') ?: $user['username']) ?></strong><small><?= e($user['username']) ?> · <?= $user['role']==='admin'?'ผู้ดูแลระบบ':'ครูและบุคลากร' ?><?= $self?' · บัญชีของคุณ':'' ?></small><span class="status-badge <?= !($user['active'] ?? 1)?'is-hidden':'' ?>"><?= ($user['active'] ?? 1)?'ใช้งานอยู่':'ปิดบัญชีแล้ว' ?></span></div><div class="row-actions"><a class="edit-link" href="<?= $self?'account.php':'?edit='.(int)$user['id'].'#user-editor' ?>">แก้ไข</a><?php if($ready && !$self): ?><form method="post" data-confirm="<?= $user['active']?'ปิดบัญชีนี้? ผู้ใช้จะเข้าสู่ระบบไม่ได้จนกว่าจะเปิดอีกครั้ง':'เปิดบัญชีนี้อีกครั้ง?' ?>"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="id" value="<?= (int)$user['id'] ?>"><input type="hidden" name="active" value="<?= $user['active']?'0':'1' ?>"><button class="delete-button" name="action" value="set_active"><?= $user['active']?'ปิดบัญชี':'เปิดบัญชี' ?></button></form><?php endif; ?></div></div><?php endforeach; ?><?php if(!$users): ?><p class="empty-message">ไม่พบผู้ใช้ ล้างตัวกรองหรือเพิ่มบัญชีใหม่</p><?php endif; ?></div><?php pagination($page,$total); ?></section>
<section class="panel" id="user-editor"><h2><?= $edit['id']?'แก้ไขผู้ใช้':'เพิ่มผู้ใช้ใหม่' ?></h2><form method="post" data-dirty-form><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><label>ชื่อผู้ใช้<input name="username" required maxlength="100" autocomplete="off" value="<?= e($edit['username']) ?>"></label><label>ระดับผู้ใช้<select name="role"><option value="teacher" <?= $edit['role']==='teacher'?'selected':'' ?>>ครูและบุคลากร</option><option value="admin" <?= $edit['role']==='admin'?'selected':'' ?>>ผู้ดูแลระบบ</option></select><small>ผู้ดูแลจัดการเว็บไซต์ เมนู และผู้ใช้ทั้งหมด ครูจัดการโปรไฟล์ของตนเอง</small></label><?php if($ready): ?><details class="profile-details" <?= $edit['id']?'open':'' ?>><summary>ข้อมูลโปรไฟล์</summary><?php profile_fields($edit); ?></details><?php endif; ?><label><?= $edit['id']?'รหัสผ่านใหม่':'รหัสผ่าน' ?><input name="password" type="password" minlength="8" autocomplete="new-password" <?= !$edit['id']?'required':'' ?>><small><?= $edit['id']?'เว้นว่างเพื่อเก็บรหัสเดิม · ':'' ?>อย่างน้อย 8 ตัวอักษร สูงสุด 72 ไบต์</small></label><label>ยืนยันรหัสผ่าน<input name="confirm_password" type="password" minlength="8" autocomplete="new-password" <?= !$edit['id']?'required':'' ?>></label><div class="form-actions"><button class="button">บันทึกผู้ใช้</button><a href="users.php#user-editor">ยกเลิก</a></div></form></section></div><?php endif; ?>
<?php admin_close();
