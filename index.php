<?php
require __DIR__.'/app/bootstrap.php';
try { $systems=db()->query('SELECT * FROM systems WHERE active=1 ORDER BY sort_order,id')->fetchAll(); $settings=settings(); } catch(Throwable $error) { unavailable($error); }
$category=(string)($_GET['category'] ?? 'all'); if ($category!=='all' && !isset(categories()[$category])) $category='all';
$view=(string)($_GET['view'] ?? (isset($_GET['category']) || !empty($_GET['q'])?'all':'home'));
if(!in_array($view,['home','all','favorites','news','profile','contact'],true)) $view='home';
$meta=[
 'student'=>['ระบบสำหรับนักศึกษา','ระบบงานและบริการสำหรับนักศึกษา','school','purple'],
 'staff'=>['ระบบครูและบุคลากร','ระบบงานสำหรับครูและบุคลากร','users','green'],
 'management'=>['ระบบบริหาร','ระบบงานบริหารและการจัดการ','building','red'],
 'learning'=>['แหล่งเรียนรู้','เรียนรู้ได้ทุกที่ ทุกเวลา','book','purple'],
 'reports'=>['รายงานและสถิติ','ข้อมูลสรุปและรายงานการดำเนินงาน','chart-bar','orange'],
 'forms'=>['แบบฟอร์ม','เอกสารและแบบฟอร์มที่จำเป็น','file-text','pink'],
 'all'=>['ระบบทั้งหมด','ศูนย์รวมบริการดิจิทัล สกร.เสนา','apps','purple']
];
$current=$meta[$category];
head($view==='all'?$current[0]:'ศูนย์รวมระบบดิจิทัล','portal');
?>
<div class="portal-shell">
<header class="portal-top"><a class="portal-brand" href="./"><img src="assets/images/sena-logo.png" alt="ตรา สกร.ระดับอำเภอเสนา" width="48" height="48"><span>สกร.เสนา<small>SENA DIGITAL HUB</small></span></a><details class="portal-menu"><summary aria-label="เปิดเมนู"><?= icon('menu-2') ?></summary><nav aria-label="เมนูหลัก"><a href="./">หน้าแรก</a><a href="?view=all">ระบบทั้งหมด</a><a href="?view=news">ข่าวสาร</a><a href="?view=contact">ติดต่อเรา</a><a href="admin/">ผู้ดูแลระบบ</a></nav></details></header>
<main>
<?php if($view==='home'): ?>
<section class="welcome" id="home"><h1>SENA DIGITAL HUB</h1><h2>ศูนย์รวมระบบ สกร.เสนา</h2><p>เรียนรู้ได้ทุกที่ ทุกเวลา เพื่ออนาคตที่ดีกว่า</p></section>
<?php elseif($view==='all'): ?>
<section class="category-hero tone-<?= e($current[3]) ?>"><a href="./" class="back-home" aria-label="กลับหน้าแรก"><?= icon('arrow-left') ?></a><?= icon($current[2]) ?><div><h1><?= e($current[0]) ?></h1><p><?= e($current[1]) ?></p></div></section>
<?php endif; ?>
<?php if(in_array($view,['home','all'],true)): ?>
<form class="portal-search" method="get" role="search"><?= icon('search') ?><label class="sr-only" for="systemSearch">ค้นหาระบบ</label><input id="systemSearch" name="q" type="search" value="<?= e($_GET['q'] ?? '') ?>" placeholder="<?= $category==='student'?'ค้นหาระบบนักศึกษา...':'ค้นหาระบบ เช่น N-NET, ครุภัณฑ์, คะแนน' ?>" autocomplete="off"><input type="hidden" name="view" value="all"><input type="hidden" name="category" value="<?= e($category) ?>"><button type="reset" class="search-reset" aria-label="ล้างคำค้น">ล้าง</button></form>
<?php if($view==='home'): ?>
<div id="homeContent"><section class="banner-slider" aria-label="แนะนำ สกร.เสนา"><div class="banner-slides"><img data-slide="0" src="assets/images/sena-banner.jpg" alt="ศูนย์ส่งเสริมการเรียนรู้ระดับอำเภอเสนา แหล่งเรียนรู้และวัฒนธรรม" width="1920" height="756"><img data-slide="1" src="assets/images/sena-banner-2.jpg" alt="ส่งเสริมการเรียนรู้ พัฒนาทักษะ สร้างโอกาส และพัฒนาคุณภาพชีวิต" width="1920" height="758" hidden></div><div class="slider-controls"><button aria-label="ภาพประชาสัมพันธ์ 1" aria-pressed="true" data-slide-target="0"></button><button aria-label="ภาพประชาสัมพันธ์ 2" aria-pressed="false" data-slide-target="1"></button></div></section>
<section class="home-menu" aria-label="หมวดหมู่บริการ"><h2 class="sr-only">เลือกบริการที่ต้องการ</h2>
<?php $tiles=[['student','ระบบนักศึกษา','school','blue'],['staff','ระบบครู','users','green'],['management','ระบบบริหาร','building','red'],['learning','แหล่งเรียนรู้','book','purple'],['reports','รายงาน/สถิติ','chart-bar','orange'],['forms','แบบฟอร์ม','file-text','pink'],['favorites','รายการโปรด','star','amber'],['news','ข่าวสาร','speakerphone','blue'],['contact','ติดต่อเรา','phone','green']]; foreach($tiles as [$key,$label,$glyph,$tone]): ?><a class="menu-tile tone-<?= $tone ?>" href="<?= isset(categories()[$key])?'?category='.$key:'?view='.$key ?>"><span class="tile-icon"><?= icon($glyph) ?></span><span><?= e($label) ?></span></a><?php endforeach; ?>
</section></div>
<?php endif; ?>
<section class="portal-catalog" id="systems" <?= $view==='home'?'hidden':'' ?> data-initial-category="<?= e($category) ?>" data-home="<?= $view==='home'?'true':'false' ?>">
<?php if($category==='all'): ?><nav class="catalog-filters" aria-label="หมวดหมู่ระบบ"><a href="?view=all" aria-current="true">ทั้งหมด</a><?php foreach(categories() as $key=>$label): ?><a href="?category=<?= $key ?>"><?= e($label) ?></a><?php endforeach; ?></nav><?php endif; ?>
<div class="results-heading"><h2 id="resultsTitle"><?= e($current[0]) ?></h2><span id="resultCount" aria-live="polite"></span></div>
<div class="portal-system-grid">
<?php foreach($systems as $i=>$s): $matches=$category==='all'||$category===$s['category']; $tones=['school'=>'blue','file-text'=>'blue','building'=>'red','compass'=>'cyan','chart-bar'=>'green','certificate'=>'purple','clipboard'=>'blue','book'=>'red','users'=>'green','apps'=>'purple']; $tone=$tones[$s['icon']] ?? 'purple'; ?>
<article class="system-card portal-system-card tone-<?= $tone ?>" data-category="<?= e($s['category']) ?>" data-search="<?= e($s['name'].' '.$s['description'].' '.(categories()[$s['category']] ?? '')) ?>" <?= !$matches?'hidden':'' ?>><span class="service-icon" style="<?= $s['color']!=='#176b55'?'--tone:'.e($s['color']).';--tint:color-mix(in srgb,'.e($s['color']).' 12%,white)':'' ?>"><?php if($s['url'] && ($settings['system_icon_'.$s['id']] ?? 'favicon')==='favicon'): ?><img class="service-favicon" src="favicon.php?id=<?= (int)$s['id'] ?>&amp;v=<?= e($settings['favicon_rev_'.$s['id']] ?? 'v2') ?>" alt="" width="40" height="40" loading="lazy" decoding="async" data-service-favicon><?php endif; ?><span class="service-glyph" style="--glyph:url('../icons/<?= e(in_array($s['icon'],icons(),true)?$s['icon']:'apps') ?>.svg')" aria-hidden="true"></span></span><h3><?= e($s['name']) ?></h3><p><?= e($s['description']) ?></p><?php if($s['url']): ?><a class="system-open" href="<?= e($s['url']) ?>" target="_blank" rel="noopener noreferrer"><span class="sr-only">เข้าใช้งาน <?= e($s['name']) ?> (เปิดแท็บใหม่)</span><?= icon('arrow-right') ?></a><?php else: ?><span class="pending">รอเพิ่มลิงก์ระบบ</span><?php endif; ?></article>
<?php endforeach; ?></div><div class="empty-state" id="emptyState" hidden><?= icon('search') ?><h3>ไม่พบระบบที่ค้นหา</h3><p>ลองใช้คำค้นอื่น หรือดูระบบทั้งหมด</p><a class="button" href="?view=all">แสดงระบบทั้งหมด</a></div><noscript><style>#systems{display:block!important}</style><p>เปิด JavaScript เพื่อค้นหาชื่อระบบ</p></noscript>
</section>
<?php elseif($view==='news'||$view==='contact'): ?>
<section class="portal-page"><div class="page-icon tone-<?= $view==='news'?'blue':'green' ?>"><?= icon($view==='news'?'speakerphone':'phone') ?></div><h1><?= $view==='news'?'ข่าวสารและประชาสัมพันธ์':'ติดต่อ สกร.เสนา' ?></h1><p><?= nl2br(e($settings[$view==='news'?'notice':'contact'] ?? '')) ?></p><a class="button" href="./">กลับหน้าแรก</a></section>
<?php else: ?>
<section class="portal-page"><div class="page-icon tone-<?= $view==='favorites'?'amber':'purple' ?>"><?= icon($view==='favorites'?'star':'user') ?></div><span class="coming-soon">เตรียมเปิดใช้งาน</span><h1><?= $view==='favorites'?'ระบบโปรดของคุณ':'โปรไฟล์ของคุณ' ?></h1><p><?= $view==='favorites'?'บันทึกระบบที่ใช้บ่อยไว้ในที่เดียว ฟีเจอร์นี้จะเปิดใช้งานพร้อมระบบสมาชิกในขั้นถัดไป':'เข้าใช้งานด้วย LINE และดูบริการตามสิทธิ์ของคุณ ฟีเจอร์นี้อยู่ในแผนพัฒนาขั้นถัดไป' ?></p><a class="button" href="?view=all">ดูระบบทั้งหมด</a><a class="profile-admin" href="admin/">เข้าสู่ระบบผู้ดูแล <?= icon('arrow-right') ?></a></section>
<?php endif; ?>
<?php if($view==='home'): ?><section class="home-help" id="contact"><p><?= nl2br(e($settings['contact'] ?? '')) ?></p><a href="?view=contact">ติดต่อเรา <?= icon('arrow-right') ?></a></section><?php endif; ?>
</main>
<footer class="portal-footer">ศูนย์ส่งเสริมการเรียนรู้ระดับอำเภอเสนา</footer>
<nav class="bottom-nav" aria-label="แถบนำทางด้านล่าง"><?php $nav=[['home','หน้าแรก','home'],['all','ทั้งหมด','apps'],['favorites','รายการโปรด','star'],['news','แจ้งเตือน','bell'],['profile','โปรไฟล์','user']]; foreach($nav as [$key,$label,$glyph]): ?><a href="<?= $key==='home'?'./':'?view='.$key ?>" <?= $view===$key?'aria-current="page"':'' ?>><?= icon($glyph) ?><span><?= $label ?></span></a><?php endforeach; ?></nav>
</div></body></html>
