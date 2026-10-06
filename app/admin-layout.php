<?php
declare(strict_types=1);
function admin_open(string $section,string $title,string $description): void {
    $account=require_account(); $admin=($account['role'] ?? 'admin')==='admin';
    head($title,'admin-ui workspace-ui');
    ?>
    <a class="skip-link" href="#workspaceMain">ข้ามไปเนื้อหา</a>
    <header class="workspace-header"><?php brand('../'); ?><div class="workspace-header-actions"><a href="../" target="_blank" rel="noopener" class="text-button"><?= icon('arrow-up-right') ?><span>เปิดหน้า Hub</span></a><a class="account-chip" href="account.php"><?= account_avatar($account) ?><span><?= e(($account['display_name'] ?? '') ?: $account['username']) ?><small><?= $admin?'ผู้ดูแลระบบ':'ครูและบุคลากร' ?></small></span></a><form method="post" action="account.php"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><button class="text-button" name="action" value="logout" aria-label="ออกจากระบบ"><?= icon('logout') ?></button></form></div></header>
    <div class="workspace-layout"><aside class="workspace-sidebar"><p class="sidebar-caption">พื้นที่จัดการ</p><nav aria-label="เมนูหลังบ้าน">
    <?php $nav=$admin?[['dashboard','dashboard.php','ภาพรวม','chart-bar'],['catalog','index.php','ระบบและเว็บไซต์','apps'],['menus','menus.php','เมนูและหมวดหมู่','menu-2'],['users','users.php','ผู้ใช้และสิทธิ์','users'],['settings','settings.php','ตั้งค่าหน้าเว็บ','adjustments'],['activity','activity.php','ประวัติการทำงาน','clipboard'],['account','account.php','โปรไฟล์ของฉัน','user']]:[['account','account.php','โปรไฟล์ของฉัน','user'],['activity','activity.php','ประวัติของฉัน','clipboard']]; foreach($nav as [$key,$href,$label,$glyph]): ?><a href="<?= $href ?>" <?= $section===$key?'aria-current="page"':'' ?>><?= icon($glyph) ?><span><?= $label ?></span></a><?php endforeach; ?></nav><div class="sidebar-note">SENA DIGITAL HUB<small>ศูนย์รวมบริการดิจิทัล</small></div></aside><main class="workspace-main" id="workspaceMain"><div class="workspace-title"><div><p class="eyebrow dark">SENA WORKSPACE</p><h1><?= e($title) ?></h1><p><?= e($description) ?></p></div></div>
    <?php if($admin && !management_ready(db())): ?><div class="alert error">โครงสร้างหลังบ้านยังไม่พร้อม <a href="upgrade.php">อัปเดตโครงสร้างเพื่อเปิดใช้ฟีเจอร์ใหม่</a></div><?php endif;
}
function account_avatar(array $account): string {
    if (!empty($account['avatar_file'])) return '<img class="profile-avatar" src="../profile-photo.php?id='.(int)$account['id'].'&amp;v='.e($account['avatar_file']).'" alt="" width="42" height="42">';
    return '<span class="profile-avatar avatar-initial">'.e(mb_substr(($account['display_name'] ?? '') ?: $account['username'],0,1)).'</span>';
}
function admin_feedback(string $error=''): void {
    if ($error) echo '<p class="alert error" role="alert">'.e($error).'</p>';
    if(isset($_SESSION['flash'])) { echo '<p class="alert success" role="status">'.e($_SESSION['flash']).'</p>'; unset($_SESSION['flash']); }
}
function admin_close(): void { echo '</main></div></body></html>'; }
function pagination(int $page,int $total,int $perPage=25): void {
    $pages=max(1,(int)ceil($total/$perPage));
    if ($pages<=1) return;
    echo '<nav class="pagination" aria-label="แบ่งหน้ารายการ"><span>หน้า '.$page.' / '.$pages.' · '.number_format($total).' รายการ</span><div>';
    foreach ([$page-1=>'ก่อนหน้า',$page+1=>'ถัดไป'] as $p=>$label) {
        if($p<1 || $p>$pages) echo '<span class="page-disabled">'.$label.'</span>';
        else echo '<a class="button secondary" href="?'.e(http_build_query(array_merge($_GET,['page'=>$p]))).'">'.$label.'</a>';
    }
    echo '</div></nav>';
}

function profile_fields(array $profile): void { ?>
<label>ชื่อ–นามสกุลที่แสดง<input name="display_name" maxlength="150" autocomplete="name" value="<?= e($profile['display_name'] ?? '') ?>"></label><div class="two-columns"><label>อีเมล<input name="email" type="email" maxlength="254" autocomplete="email" value="<?= e($profile['email'] ?? '') ?>"></label><label>โทรศัพท์<input name="phone" type="tel" maxlength="40" autocomplete="tel" value="<?= e($profile['phone'] ?? '') ?>"></label></div><label>ตำแหน่ง / หน่วยงาน<input name="position" maxlength="150" value="<?= e($profile['position'] ?? '') ?>"></label><label>เกี่ยวกับฉัน<textarea name="bio" rows="3" maxlength="1000"><?= e($profile['bio'] ?? '') ?></textarea></label>
<?php }
