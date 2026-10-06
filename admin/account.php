<?php
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/accounts.php';
$account = require_account(); $error='';
$isAdmin = ($account['role'] ?? 'admin') === 'admin';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    if (($_POST['action'] ?? '') === 'logout') { $_SESSION=[]; session_destroy(); header('Location: login.php'); exit; }
    try {
        if (($_POST['action'] ?? '') !== 'account') throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
        $username=trim((string)($_POST['username'] ?? '')); $password=(string)($_POST['new_password'] ?? '');
        $error=account_input_error($username,$password,(string)($_POST['confirm_password'] ?? ''),false);
        if ($error) throw new InvalidArgumentException($error);
        if (!password_verify((string)($_POST['current_password'] ?? ''),$account['password_hash'])) throw new InvalidArgumentException('รหัสผ่านปัจจุบันไม่ถูกต้อง');
        // The self-service form never accepts a role from the browser.
        if (account_roles_ready(db())) save_hub_account(db(),(int)$account['id'],$username,$password,(string)($_POST['confirm_password'] ?? ''),$account['role']);
        else {
            $stmt=db()->prepare('UPDATE admins SET username=?,password_hash=? WHERE id=?');
            $stmt->execute([$username,$password!==''?password_hash($password,PASSWORD_DEFAULT):$account['password_hash'],$account['id']]);
        }
        $stmt=db()->prepare('SELECT password_hash FROM admins WHERE id=?'); $stmt->execute([$account['id']]);
        $_SESSION['account_fingerprint']=hash('sha256',(string)$stmt->fetchColumn());
        session_regenerate_id(true); $_SESSION['admin_username']=$username; $_SESSION['flash']='บันทึกบัญชีเรียบร้อยแล้ว';
        header('Location: account.php'); exit;
    } catch (InvalidArgumentException $err) { $error=$err->getMessage(); }
    catch (PDOException $err) { if (($err->errorInfo[1] ?? null)===1062) $error='ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น'; else unavailable($err); }
}
head('บัญชีของฉัน','admin-ui'); ?>
<header class="header"><div class="container header-inner"><?php brand('../'); ?><span class="admin-label"><?= $isAdmin?'ผู้ดูแลระบบ':'ครูและบุคลากร' ?></span><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="text-button" name="action" value="logout"><?= icon('logout') ?> ออกจากระบบ</button></form></div></header>
<main class="container accounts-main"><div class="section-title"><div><p class="eyebrow dark">บัญชีของฉัน</p><h1>สวัสดี <?= e($account['username']) ?></h1><p><?= $isAdmin?'จัดการข้อมูลเข้าสู่ระบบของคุณ':'เข้าถึงบริการสำหรับครูและจัดการบัญชีของคุณ' ?></p></div><a class="button secondary" href="<?= $isAdmin?'index.php':'../?category=staff' ?>"><?= $isAdmin?'กลับหน้าจัดการ':'เปิดระบบสำหรับครู' ?> <?= icon('arrow-right') ?></a></div>
<?php if($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?><?php if(isset($_SESSION['flash'])): ?><p class="alert success" role="status"><?= e($_SESSION['flash']) ?></p><?php unset($_SESSION['flash']); endif; ?>
<section class="panel"><div class="editor-symbol"><?= icon('user') ?></div><h2>ชื่อผู้ใช้และรหัสผ่าน</h2><p>ใช้ภาษาไทยหรือภาษาอังกฤษเป็นชื่อผู้ใช้ได้ รหัสผ่านขั้นต่ำ 8 ตัวอักษร</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="account"><div class="two-columns"><label>ชื่อผู้ใช้<input name="username" required maxlength="100" autocomplete="username" value="<?= e($_POST['username'] ?? $account['username']) ?>"></label><label>รหัสผ่านปัจจุบัน<input type="password" name="current_password" required autocomplete="current-password"></label></div><div class="two-columns"><label>รหัสผ่านใหม่<input type="password" name="new_password" minlength="8" autocomplete="new-password"><small>เว้นว่างเพื่อใช้รหัสผ่านเดิม สูงสุด 72 ไบต์</small></label><label>ยืนยันรหัสผ่านใหม่<input type="password" name="confirm_password" minlength="8" autocomplete="new-password"></label></div><button class="button">บันทึกบัญชี</button></form></section>
<?php if(!$isAdmin): ?><section class="panel settings-panel"><h2>บริการสำหรับครูและบุคลากร</h2><p>เลือกบริการจากหน้า Hub ระบบปลายทางใช้บัญชีและสิทธิ์ของแต่ละระบบ</p><a class="button secondary" href="../?category=staff">ดูบริการสำหรับครู <?= icon('arrow-up-right') ?></a></section><?php endif; ?></main></body></html>
