<?php
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/accounts.php';
require_admin(); $error=''; $draft=null;
try {
    if ($_SERVER['REQUEST_METHOD']==='POST') {
        check_csrf(); $action=(string)($_POST['action'] ?? '');
        try {
            if ($action==='enable_roles') { enable_account_roles(db()); $_SESSION['flash']='เปิดใช้งานบัญชีครูแล้ว บัญชีและข้อมูลเดิมยังอยู่ครบ'; }
            elseif ($action==='save_user') {
                $draft=['id'=>(int)($_POST['id'] ?? 0),'username'=>trim((string)($_POST['username'] ?? '')),'role'=>(string)($_POST['role'] ?? '')];
                if ($draft['id']===(int)$_SESSION['admin_id']) throw new InvalidArgumentException('แก้บัญชีของคุณได้จากหน้า “บัญชีของฉัน”');
                save_hub_account(db(),$draft['id'],$draft['username'],(string)($_POST['password'] ?? ''),(string)($_POST['confirm_password'] ?? ''),$draft['role']);
                $_SESSION['flash']=$draft['id']?'บันทึกบัญชีเรียบร้อยแล้ว':'เพิ่มบัญชีเรียบร้อยแล้ว';
            } else throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
            header('Location: users.php'); exit;
        } catch (InvalidArgumentException $err) { $error=$err->getMessage(); }
    }
    $ready=account_roles_ready(db());
    $users=db()->query('SELECT id,username'.($ready?',role':",'admin' AS role").' FROM admins ORDER BY id')->fetchAll();
    $edit=$draft ?? ['id'=>0,'username'=>'','role'=>'teacher'];
    if (!$draft && isset($_GET['edit'])) {
        foreach ($users as $user) if ((int)$user['id']===(int)$_GET['edit']) $edit=$user;
        if ((int)$edit['id']===(int)$_SESSION['admin_id']) { header('Location: account.php'); exit; }
    }
} catch (Throwable $err) { unavailable($err); }
head('จัดการผู้ใช้','admin-ui'); ?>
<header class="header"><div class="container header-inner"><?php brand('../'); ?><span class="admin-label">พื้นที่ผู้ดูแลระบบ</span><a class="text-button" href="index.php">กลับหน้าจัดการ</a></div></header>
<main class="container accounts-main"><div class="section-title"><div><p class="eyebrow dark">ทีมงาน SENA</p><h1>จัดการผู้ใช้</h1><p>เพิ่มบัญชีครู แก้ชื่อผู้ใช้ รหัสผ่าน และระดับสิทธิ์</p></div><a class="button secondary" href="account.php">บัญชีของฉัน <?= icon('user') ?></a></div>
<?php if($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?><?php if(isset($_SESSION['flash'])): ?><p class="alert success" role="status"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif; ?>
<?php if(!$ready): ?><section class="panel"><h2>เปิดใช้งานบัญชีครู</h2><p>อัปเดตโครงสร้างบัญชีครั้งเดียว โดยเพิ่มช่องระดับสิทธิ์ บัญชีและรหัสผ่านเดิมจะคงอยู่</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="button" name="action" value="enable_roles">เปิดใช้งานบัญชีครู</button></form></section><?php else: ?>
<div class="admin-layout"><section class="panel"><div class="panel-heading"><h2>บัญชีทั้งหมด <span class="count"><?= count($users) ?></span></h2><a href="users.php#user-editor">เพิ่มผู้ใช้</a></div><div class="admin-list"><?php foreach($users as $user): ?><div class="admin-row"><span class="system-icon" style="--system-color:#7524ef"><?= icon($user['role']==='admin'?'shield-lock':'user') ?></span><div class="row-info"><strong><?= e($user['username']) ?></strong><small><?= $user['role']==='admin'?'ผู้ดูแลระบบ':'ครูและบุคลากร' ?><?= (int)$user['id']===(int)$_SESSION['admin_id']?' · บัญชีของคุณ':'' ?></small></div><a class="edit-link" href="<?= (int)$user['id']===(int)$_SESSION['admin_id']?'account.php':'?edit='.(int)$user['id'].'#user-editor' ?>">แก้ไข</a></div><?php endforeach; ?></div></section>
<section class="panel" id="user-editor"><div class="editor-symbol"><?= icon($edit['id']?'user':'plus') ?></div><h2><?= $edit['id']?'แก้ไขผู้ใช้':'เพิ่มผู้ใช้ใหม่' ?></h2><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save_user"><input type="hidden" name="id" value="<?= (int)$edit['id'] ?>"><label>ชื่อผู้ใช้<input name="username" maxlength="100" required autocomplete="off" value="<?= e($edit['username']) ?>"></label><label>ระดับผู้ใช้<select name="role"><option value="teacher" <?= $edit['role']==='teacher'?'selected':'' ?>>ครูและบุคลากร</option><option value="admin" <?= $edit['role']==='admin'?'selected':'' ?>>ผู้ดูแลระบบ</option></select><small>ครูใช้บริการและแก้บัญชีตัวเองได้ ผู้ดูแลจัดการระบบและผู้ใช้ทั้งหมด</small></label><label><?= $edit['id']?'รหัสผ่านใหม่':'รหัสผ่าน' ?><input name="password" type="password" minlength="8" autocomplete="new-password" <?= !$edit['id']?'required':'' ?>><small><?= $edit['id']?'เว้นว่างเพื่อเก็บรหัสเดิม · ':'' ?>ขั้นต่ำ 8 ตัวอักษร สูงสุด 72 ไบต์</small></label><label>ยืนยันรหัสผ่าน<input name="confirm_password" type="password" minlength="8" autocomplete="new-password" <?= !$edit['id']?'required':'' ?>></label><div class="form-actions"><button class="button">บันทึกผู้ใช้</button><?php if($edit['id']): ?><a href="users.php#user-editor">ยกเลิก</a><?php endif; ?></div></form></section></div><?php endif; ?></main></body></html>
