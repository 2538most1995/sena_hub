<?php
require dirname(__DIR__).'/app/bootstrap.php';require_admin();require dirname(__DIR__).'/app/admin-layout.php';
$error='';$draft=null;$data=navigation_data();
if($_SERVER['REQUEST_METHOD']==='POST') {
 check_csrf();$action=(string)($_POST['action'] ?? '');$draft=$_POST;
 try {
  db()->beginTransaction();
  foreach(['hub_categories'=>default_category_definitions(),'hub_navigation'=>default_navigation()] as $key=>$defaults) {
   $stmt=db()->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES (?,?)');$stmt->execute([$key,json_encode($defaults,JSON_UNESCAPED_UNICODE)]);
  }
  $stored=db()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('hub_categories','hub_navigation') ORDER BY setting_key FOR UPDATE")->fetchAll(PDO::FETCH_KEY_PAIR);
  $data=['categories'=>json_decode($stored['hub_categories'],true),'menus'=>json_decode($stored['hub_navigation'],true)];
  $kind=(string)($_POST['kind'] ?? '');if(!in_array($kind,['categories','menus'],true))throw new InvalidArgumentException('ชนิดรายการไม่ถูกต้อง');
  $items=$data[$kind];$id=(string)($_POST['id'] ?? '');$idKey=$kind==='categories'?'key':'id';$index=null;
  foreach($items as $i=>$item)if($item[$idKey]===$id)$index=$i;
  if($action==='save') {
   $label=trim((string)($_POST['label'] ?? ''));$glyph=(string)($_POST['icon'] ?? 'apps');
   if($label==='' || mb_strlen($label)>40 || !in_array($glyph,array_merge(icons(),['home','star','bell','user','phone','speakerphone','link']),true))throw new InvalidArgumentException('กรอกชื่อไม่เกิน 40 ตัวอักษรและเลือกไอคอนที่รองรับ');
   if($kind==='categories') {
    $key=$id ?: trim((string)($_POST['key'] ?? ''));
    if(!preg_match('/^[a-z][a-z0-9_-]{0,29}$/D',$key) || in_array($key,['all','home','profile','contact','news','favorites'],true))throw new InvalidArgumentException('รหัสหมวดต้องขึ้นต้นด้วย a–z ใช้ a–z ตัวเลข _ หรือ - ไม่เกิน 30 ตัว และไม่ใช่ชื่อหน้าที่สงวนไว้');
    if(!$id && in_array($key,array_column($items,'key'),true))throw new InvalidArgumentException('รหัสหมวดหมู่นี้มีอยู่แล้ว');
    $tone=(string)($_POST['tone'] ?? 'purple');if(!in_array($tone,['blue','green','red','purple','orange','pink','amber','cyan'],true))throw new InvalidArgumentException('สีหมวดหมู่ไม่ถูกต้อง');
    $entry=['key'=>$key,'label'=>$label,'icon'=>$glyph,'tone'=>$tone];
   } else {
    $target=trim((string)($_POST['target'] ?? ''));$location=(string)($_POST['location'] ?? '');
    if(!navigation_target_valid($target) || !in_array($location,['top','bottom','both'],true))throw new InvalidArgumentException('ลิงก์หรือตำแหน่งเมนูไม่ถูกต้อง ใช้ลิงก์หน้าที่มีในระบบหรือ http(s)://');
    $entry=['id'=>$id ?: bin2hex(random_bytes(6)),'label'=>$label,'target'=>$target,'icon'=>$glyph,'location'=>$location,'active'=>isset($_POST['active'])?1:0];
   }
   if($id && $index===null)throw new InvalidArgumentException('ไม่พบรายการที่ต้องการแก้ไข');
   if($index===null)$items[]=$entry;else $items[$index]=$entry;
  } elseif($action==='delete') {
   if($index===null)throw new InvalidArgumentException('ไม่พบรายการ');
   if($kind==='categories') {
    if(!management_ready(db()))throw new InvalidArgumentException('อัปเดตโครงสร้างหลังบ้านก่อนลบหมวดหมู่');
    if(in_array($id,array_column(default_category_definitions(),'key'),true))throw new InvalidArgumentException('หมวดมาตรฐานใช้กับลิงก์ LINE เดิม เปลี่ยนชื่อหรือจัดลำดับได้');
    $stmt=db()->prepare('SELECT COUNT(*) FROM systems WHERE category=?'.(management_ready(db())?' OR EXISTS (SELECT 1 FROM system_categories sc WHERE sc.system_id=systems.id AND sc.category_key=?)':''));$stmt->execute(management_ready(db())?[$id,$id]:[$id]);
    if($stmt->fetchColumn())throw new InvalidArgumentException('หมวดนี้มีระบบอยู่ ย้ายระบบไปหมวดอื่นก่อนลบ');
    foreach($data['menus'] as $m)if($m['target']==='?category='.$id)throw new InvalidArgumentException('มีเมนูลิงก์ไปหมวดนี้ แก้เมนูก่อนลบ');
   }
   array_splice($items,$index,1);
  } elseif($action==='move') {
   if($index===null)throw new InvalidArgumentException('ไม่พบรายการ');
   $direction=(string)($_POST['direction'] ?? '');if(!in_array($direction,['up','down'],true))throw new InvalidArgumentException('ทิศทางไม่ถูกต้อง');
   $next=$index+($direction==='up'?-1:1);if(isset($items[$next]))[$items[$index],$items[$next]]=[$items[$next],$items[$index]];
  } else throw new InvalidArgumentException('คำสั่งไม่ถูกต้อง');
  if(count($items)>($kind==='categories'?100:50))throw new InvalidArgumentException('รายการเกินจำนวนที่รองรับ');
  if($kind==='menus' && count(array_filter($items,fn($m)=>$m['active'] && in_array($m['location'],['bottom','both'],true)))>7)throw new InvalidArgumentException('แถบด้านล่างแสดงได้ไม่เกิน 7 เมนู กรุณาย้ายบางรายการไปด้านบนหรือซ่อน');
  save_setting(db(),$kind==='categories'?'hub_categories':'hub_navigation',json_encode($items,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));audit_change(db(),'navigation.'.$action,$kind.' · '.($label ?? $id));db()->commit();
  $_SESSION['flash']='บันทึกเมนูและหมวดหมู่แล้ว';header('Location: menus.php?tab='.$kind);exit;
 }catch(InvalidArgumentException $err){if(db()->inTransaction())db()->rollBack();$error=$err->getMessage();$data=navigation_data();}
 catch(Throwable $err){if(db()->inTransaction())db()->rollBack();unavailable($err);}
}
$tab=(string)($_GET['tab'] ?? ($draft['kind'] ?? 'categories'));if(!in_array($tab,['categories','menus'],true))$tab='categories';
$edit=$tab==='categories'?['key'=>'','label'=>'','icon'=>'apps','tone'=>'purple']:['id'=>'','label'=>'','target'=>'?view=all','icon'=>'apps','location'=>'top','active'=>1];
if(isset($_GET['edit']))foreach($data[$tab] as $item)if(($item['key'] ?? $item['id'])===$_GET['edit'])$edit=$item;
if($draft && ($draft['action'] ?? '')==='save'){$edit=array_merge($edit,$draft);$edit['active']=isset($draft['active'])?1:0;}
admin_open('menus','เมนูและหมวดหมู่','ออกแบบทางเข้าบริการของคุณ เปลี่ยนชื่อ ไอคอน ลิงก์ และลำดับได้');admin_feedback($error); ?>
<nav class="workspace-tabs" aria-label="ประเภทการจัดการ"><a href="?tab=categories" <?= $tab==='categories'?'aria-current="page"':'' ?>>หมวดหมู่บริการ <span><?= count($data['categories']) ?></span></a><a href="?tab=menus" <?= $tab==='menus'?'aria-current="page"':'' ?>>เมนูนำทาง <span><?= count($data['menus']) ?></span></a></nav>
<div class="workspace-columns"><section class="panel"><div class="panel-heading"><h2><?= $tab==='categories'?'หมวดหมู่หน้า Hub':'เมนูด้านบนและด้านล่าง' ?></h2><a href="?tab=<?= $tab ?>#menuEditor">เพิ่มรายการ</a></div><p>ใช้ลูกศรขึ้น–ลงเพื่อเปลี่ยนลำดับ รายการด้านบนจะแสดงก่อน</p><div class="admin-list">
<?php foreach($data[$tab] as $item):$id=$item['key'] ?? $item['id']; ?><div class="admin-row"><span class="system-icon"><?= icon($item['icon']) ?></span><div class="row-info"><strong><?= e($item['label']) ?></strong><small><?= e($tab==='categories'?$item['key']:($item['active']?'แสดง · ':'ซ่อน · ').['top'=>'ด้านบน','bottom'=>'ด้านล่าง','both'=>'ทั้งสองตำแหน่ง'][$item['location']]) ?></small><?php if($tab==='menus'): ?><small><?= e($item['target']) ?></small><?php endif; ?></div><div class="row-actions"><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="kind" value="<?= $tab ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><input type="hidden" name="action" value="move"><button class="move-button" name="direction" value="up" aria-label="เลื่อน <?= e($item['label']) ?> ขึ้น">↑</button><button class="move-button" name="direction" value="down" aria-label="เลื่อน <?= e($item['label']) ?> ลง">↓</button></form><a class="edit-link" href="?tab=<?= $tab ?>&amp;edit=<?= e($id) ?>#menuEditor">แก้ไข</a><form method="post" data-confirm="ลบรายการ <?= e($item['label']) ?>?"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="kind" value="<?= $tab ?>"><input type="hidden" name="id" value="<?= e($id) ?>"><button class="delete-button" name="action" value="delete">ลบ</button></form></div></div><?php endforeach; ?><?php if(!$data[$tab]): ?><p class="empty-message">ยังไม่มีเมนู เพิ่มรายการแรกจากฟอร์มด้านข้าง</p><?php endif; ?></div></section>
<section class="panel" id="menuEditor"><h2><?= isset($_GET['edit'])?'แก้ไขรายการ':'เพิ่มรายการใหม่' ?></h2><form method="post" data-dirty-form><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="kind" value="<?= $tab ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= e($tab==='categories'?($_GET['edit'] ?? ($draft['id'] ?? '')):$edit['id']) ?>"><label>ชื่อที่แสดง<input name="label" required maxlength="40" value="<?= e($edit['label']) ?>"></label>
<?php if($tab==='categories'): ?><label>รหัสหมวดหมู่<input name="key" required maxlength="30" pattern="[a-z][a-z0-9_-]{0,29}" value="<?= e($edit['key']) ?>" <?= isset($_GET['edit'])?'readonly':'' ?>><small>ใช้ใน URL เช่น student รหัสของหมวดที่สร้างแล้วจะคงเดิม</small></label><label>สีหมวด<select name="tone"><?php foreach(['blue'=>'ฟ้า','green'=>'เขียว','red'=>'แดง','purple'=>'ม่วง','orange'=>'ส้ม','pink'=>'ชมพู','amber'=>'เหลือง','cyan'=>'ฟ้าอมเขียว'] as $key=>$label): ?><option value="<?= $key ?>" <?= $edit['tone']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label>
<?php else: ?><label>ลิงก์ปลายทาง<input name="target" required maxlength="2048" list="menuTargets" value="<?= e($edit['target']) ?>"><small>เลือกหน้าของ Hub หรือใส่ https:// เพื่อเปิดเว็บไซต์ภายนอก</small></label><datalist id="menuTargets"><option value="./">หน้าแรก</option><?php foreach(['all'=>'ระบบทั้งหมด','news'=>'ข่าวสาร','contact'=>'ติดต่อเรา'] as $key=>$label): ?><option value="?view=<?= $key ?>"><?= $label ?></option><?php endforeach; ?><?php foreach(categories() as $key=>$label): ?><option value="?category=<?= e($key) ?>"><?= e($label) ?></option><?php endforeach; ?><option value="admin/account.php">โปรไฟล์</option></datalist><label>ตำแหน่ง<select name="location"><?php foreach(['top'=>'เมนูด้านบน','bottom'=>'แถบด้านล่าง','both'=>'ทั้งด้านบนและด้านล่าง'] as $key=>$label): ?><option value="<?= $key ?>" <?= $edit['location']===$key?'selected':'' ?>><?= $label ?></option><?php endforeach; ?></select></label><label class="checkbox"><input type="checkbox" name="active" <?= $edit['active']?'checked':'' ?>>แสดงเมนูนี้</label><?php endif; ?>
<label>ไอคอน<select name="icon"><?php foreach(array_merge(icons(),['home','star','bell','user','phone','speakerphone','link']) as $glyph): ?><option <?= $edit['icon']===$glyph?'selected':'' ?>><?= e($glyph) ?></option><?php endforeach; ?></select></label><div class="form-actions"><button class="button">บันทึกรายการ</button><a href="?tab=<?= $tab ?>">ยกเลิก</a></div></form></section></div>
<?php admin_close();
