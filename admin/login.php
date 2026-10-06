<?php
require dirname(__DIR__).'/app/bootstrap.php';
header('Cache-Control: no-store');
if (!empty($_SESSION['admin_id'])) { header('Location: index.php'); exit; }
$error='';
if ($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf();
 $username=mb_substr(trim((string)($_POST['username'] ?? '')),0,100);
 $key=hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '').'|'.mb_strtolower($username));
 try {
 $stmt=db()->prepare('SELECT * FROM login_attempts WHERE attempt_key=?'); $stmt->execute([$key]); $attempt=$stmt->fetch();
 if ($attempt && $attempt['failures']>=5 && $attempt['started_at']>time()-900) { $error='เข้าสู่ระบบไม่สำเร็จหลายครั้ง กรุณารอ 15 นาที'; }
 else {
 $stmt=db()->prepare('SELECT * FROM admins WHERE username=?'); $stmt->execute([$username]); $admin=$stmt->fetch();
 if ($admin && password_verify((string)($_POST['password'] ?? ''),$admin['password_hash'])) {
 session_regenerate_id(true); $_SESSION['admin_id']=$admin['id']; $_SESSION['admin_username']=$admin['username']; $_SESSION['csrf']=bin2hex(random_bytes(32));
 $stmt=db()->prepare('DELETE FROM login_attempts WHERE attempt_key=?'); $stmt->execute([$key]); header('Location: index.php'); exit;
 }
 $stmt=db()->prepare('INSERT INTO login_attempts(attempt_key, failures, started_at) VALUES (?,1,?) ON DUPLICATE KEY UPDATE failures=IF(started_at < ?,1,failures+1),started_at=IF(started_at < ?,VALUES(started_at),started_at)'); $stmt->execute([$key,time(),time()-900,time()-900]);
 $error='ชื่อผู้ใช้หรือรหัสผ่านไม่ถูกต้อง';
 }
 } catch(Throwable $err) { error_log((string)$err); $error='ยังเชื่อมต่อฐานข้อมูลไม่ได้ กรุณาตรวจสอบการติดตั้ง'; }
}
head('เข้าสู่ระบบ Admin', 'admin-ui login-ui'); ?>
<main class="login-layout"><section class="login-intro"><?php brand('../'); ?><div class="login-message"><span class="login-eyebrow">พื้นที่ผู้ดูแลระบบ</span><h2>ทุกบริการของเสนา<br>จัดการได้ในที่เดียว</h2><p>เพิ่มเว็บไซต์ จัดหมวดหมู่ และอัปเดตข้อมูล<br>ให้ทุกคนเข้าถึงบริการได้สะดวกขึ้น</p><div class="login-features"><span><?= icon('apps') ?> จัดการระบบ</span><span><?= icon('speakerphone') ?> ข่าวประชาสัมพันธ์</span><span><?= icon('link') ?> เชื่อมเมนู LINE</span></div></div><span class="login-org">ศูนย์ส่งเสริมการเรียนรู้ระดับอำเภอเสนา</span></section><section class="login-form-area"><div class="auth-card"><a class="login-back" href="../"><?= icon('arrow-left') ?> กลับสู่หน้า Hub</a><div class="auth-heading"><span class="auth-symbol"><?= icon('shield-lock') ?></span><h1>เข้าสู่ระบบผู้ดูแล</h1><p>ยินดีต้อนรับสู่ SENA Digital Hub</p></div><?php if($error): ?><p class="alert error" role="alert"><?= e($error) ?></p><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><label>ชื่อผู้ใช้<input name="username" required autocomplete="username" maxlength="100" placeholder="ชื่อผู้ใช้ของคุณ" value="<?= e($_POST['username'] ?? '') ?>"></label><label for="adminPassword">รหัสผ่าน</label><div class="password-field"><input id="adminPassword" name="password" type="password" required autocomplete="current-password" placeholder="กรอกรหัสผ่าน"><button type="button" data-password-toggle="adminPassword" aria-pressed="false" aria-label="แสดงรหัสผ่าน">แสดง</button></div><button class="button wide login-submit">เข้าสู่ระบบ <?= icon('arrow-right') ?></button></form><p class="login-hint">สำหรับผู้ดูแลระบบ สกร.เสนา</p></div></section></main></body></html>
