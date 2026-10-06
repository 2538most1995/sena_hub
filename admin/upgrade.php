<?php
require dirname(__DIR__).'/app/bootstrap.php';require_admin();require dirname(__DIR__).'/app/admin-layout.php';
$error='';
if($_SERVER['REQUEST_METHOD']==='POST') { check_csrf();try { upgrade_hub(db());$_SESSION['flash']='อัปเดตโครงสร้างหลังบ้านแล้ว';header('Location: dashboard.php');exit;}catch(Throwable $err){error_log((string)$err);$error='อัปเดตไม่สำเร็จ ตรวจสอบสิทธิ์ ALTER/CREATE ของฐานข้อมูลแล้วลองอีกครั้ง';} }
admin_open('settings','อัปเดตโครงสร้างหลังบ้าน','เพิ่มโปรไฟล์ ตารางหมวดหมู่ และประวัติการเปลี่ยนแปลง');admin_feedback($error); ?>
<section class="panel"><h2>เปิดใช้ฟีเจอร์หลังบ้านใหม่</h2><p>เพิ่มคอลัมน์และตาราง โดยเก็บบัญชี รหัสผ่าน เว็บไซต์ และข้อมูลเดิมไว้ครบ แนะนำสำรองฐานข้อมูลก่อนอัปเดตบนโฮสติ้ง</p><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="button">อัปเดตโครงสร้าง</button></form></section>
<?php admin_close();
