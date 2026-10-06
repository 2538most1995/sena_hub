<?php
require dirname(__DIR__).'/app/bootstrap.php';require dirname(__DIR__).'/app/accounts.php';require dirname(__DIR__).'/app/uploads.php';require dirname(__DIR__).'/app/admin-layout.php';
$account=require_account();$error='';$newAvatar=null;$ready=management_ready(db());$isAdmin=($account['role'] ?? 'admin')==='admin';
if($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf();$action=(string)($_POST['action'] ?? '');
 if($action==='logout'){$_SESSION=[];session_destroy();header('Location: login.php');exit;}
 try {
  if($action==='profile') {
   if(!$ready)throw new InvalidArgumentException('กรุณาให้ผู้ดูแลอัปเดตโครงสร้างหลังบ้านก่อน');
   $profile=profile_input($_POST);
   if(isset($_FILES['avatar']) && $_FILES['avatar']['error']!==UPLOAD_ERR_NO_FILE)$newAvatar=uploaded_icon_save($_FILES['avatar']);
   $remove=isset($_POST['remove_avatar']);
   db()->beginTransaction();$locked=db()->prepare('SELECT avatar_file FROM admins WHERE id=? FOR UPDATE');$locked->execute([$account['id']]);$oldAvatar=$locked->fetchColumn() ?: null;save_profile(db(),(int)$account['id'],$profile,$newAvatar,$remove);audit_change(db(),'profile.update','โปรไฟล์ของฉัน');db()->commit();
   if($newAvatar || $remove)uploaded_icon_remove($oldAvatar);$newAvatar=null;
   $_SESSION['flash']='บันทึกโปรไฟล์เรียบร้อยแล้ว';
  } elseif($action==='account') {
   $username=trim((string)($_POST['username'] ?? ''));$password=(string)($_POST['new_password'] ?? '');$confirm=(string)($_POST['confirm_password'] ?? '');
   $error=account_input_error($username,$password,$confirm,false);if($error)throw new InvalidArgumentException($error);
   if(!password_verify((string)($_POST['current_password'] ?? ''),$account['password_hash']))throw new InvalidArgumentException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
   if(account_roles_ready(db()))save_hub_account(db(),(int)$account['id'],$username,$password,$confirm,$account['role']);
   else {$stmt=db()->prepare('UPDATE admins SET username=?,password_hash=? WHERE id=?');$stmt->execute([$username,$password!==''?password_hash($password,PASSWORD_DEFAULT):$account['password_hash'],$account['id']]);}
   $stmt=db()->prepare('SELECT password_hash FROM admins WHERE id=?');$stmt->execute([$account['id']]);$_SESSION['account_fingerprint']=hash('sha256',(string)$stmt->fetchColumn());session_regenerate_id(true);$_SESSION['admin_username']=$username;$_SESSION['flash']='บันทึกบัญชีเรียบร้อยแล้ว';
  } else throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
  header('Location: account.php');exit;
 }catch(InvalidArgumentException $err){if(db()->inTransaction())db()->rollBack();uploaded_icon_remove($newAvatar);$error=$err->getMessage();}
 catch(PDOException $err){if(db()->inTransaction())db()->rollBack();uploaded_icon_remove($newAvatar);if(($err->errorInfo[1] ?? null)===1062)$error='ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น';else unavailable($err);}
}
$profileDraft=($_POST['action'] ?? '')==='profile'?array_merge($account,$_POST):$account;
admin_open('account','โปรไฟล์ของฉัน','จัดการข้อมูลส่วนตัว รูปโปรไฟล์ และความปลอดภัยของบัญชี');admin_feedback($error); ?>
<div class="profile-overview panel"><?= account_avatar($account) ?><div><h2><?= e(($account['display_name'] ?? '') ?: $account['username']) ?></h2><p><?= e(($account['position'] ?? '') ?: ($isAdmin?'ผู้ดูแลระบบ':'ครูและบุคลากร')) ?></p><span class="status-badge"><?= $isAdmin?'สิทธิ์ผู้ดูแลระบบ':'สิทธิ์ครูและบุคลากร' ?></span></div><a class="button secondary" href="../?category=staff">เปิดบริการสำหรับครู <?= icon('arrow-up-right') ?></a></div>
<div class="workspace-columns"><section class="panel"><h2>ข้อมูลโปรไฟล์</h2><p>ข้อมูลนี้ใช้ในพื้นที่บัญชี ไม่แสดงต่อผู้เยี่ยมชมหน้า Hub</p><?php if($ready): ?><form method="post" enctype="multipart/form-data" data-dirty-form><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="profile"><?php profile_fields($profileDraft); ?><label>รูปโปรไฟล์<input type="file" name="avatar" accept="image/png,image/jpeg,image/webp" data-avatar-input><small>PNG, JPG หรือ WebP ไม่เกิน 2 MB</small></label><img class="avatar-preview" data-avatar-preview hidden alt="ตัวอย่างรูปโปรไฟล์"><?php if(!empty($account['avatar_file'])): ?><label class="checkbox"><input type="checkbox" name="remove_avatar">นำรูปโปรไฟล์เดิมออก</label><?php endif; ?><button class="button">บันทึกโปรไฟล์</button></form><?php else: ?><p class="empty-message">ให้ผู้ดูแลอัปเดตโครงสร้างหลังบ้านเพื่อแก้ไขโปรไฟล์</p><?php endif; ?></section>
<section class="panel"><h2>บัญชีและรหัสผ่าน</h2><p>ยืนยันรหัสผ่านปัจจุบันก่อนเปลี่ยนข้อมูลเข้าสู่ระบบ</p><form method="post" data-dirty-form><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="account"><label>ชื่อผู้ใช้<input name="username" required maxlength="100" autocomplete="username" value="<?= e(($_POST['action'] ?? '')==='account'?($_POST['username'] ?? ''):$account['username']) ?>"></label><label>รหัสผ่านปัจจุบัน<input name="current_password" type="password" required autocomplete="current-password"></label><label>รหัสผ่านใหม่<input name="new_password" type="password" minlength="8" autocomplete="new-password"><small>เว้นว่างเพื่อเก็บรหัสเดิม · อย่างน้อย 8 ตัวอักษร สูงสุด 72 ไบต์</small></label><label>ยืนยันรหัสผ่านใหม่<input name="confirm_password" type="password" minlength="8" autocomplete="new-password"></label><button class="button">บันทึกบัญชี</button></form></section></div>
<?php admin_close();
