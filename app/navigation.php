<?php
declare(strict_types=1);

function default_category_definitions(): array {
    return [
        ['key'=>'student','label'=>'นักศึกษา','icon'=>'school','tone'=>'blue'],
        ['key'=>'staff','label'=>'ครูและบุคลากร','icon'=>'users','tone'=>'green'],
        ['key'=>'management','label'=>'งานบริหาร','icon'=>'building','tone'=>'red'],
        ['key'=>'learning','label'=>'แหล่งเรียนรู้','icon'=>'book','tone'=>'purple'],
        ['key'=>'reports','label'=>'รายงานและสถิติ','icon'=>'chart-bar','tone'=>'orange'],
        ['key'=>'forms','label'=>'แบบฟอร์ม','icon'=>'file-text','tone'=>'pink']
    ];
}
function default_navigation(): array {
    return [
        ['id'=>'home','label'=>'หน้าแรก','target'=>'./','icon'=>'home','location'=>'both','active'=>1],
        ['id'=>'all','label'=>'ระบบทั้งหมด','target'=>'?view=all','icon'=>'apps','location'=>'both','active'=>1],
        ['id'=>'news','label'=>'ข่าวสาร','target'=>'?view=news','icon'=>'bell','location'=>'both','active'=>1],
        ['id'=>'contact','label'=>'ติดต่อเรา','target'=>'?view=contact','icon'=>'phone','location'=>'both','active'=>1],
        ['id'=>'profile','label'=>'โปรไฟล์','target'=>'admin/account.php','icon'=>'user','location'=>'both','active'=>1]
    ];
}
function navigation_data(): array {
    static $data;
    if ($data === null) {
        $stmt=db()->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('hub_categories','hub_navigation')");
        $stored=$stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $data=['categories'=>json_decode($stored['hub_categories'] ?? '',true) ?: default_category_definitions(),
            'menus'=>isset($stored['hub_navigation']) ? (json_decode($stored['hub_navigation'],true) ?? default_navigation()) : default_navigation()];
    }
    return $data;
}
function category_definitions(): array { return navigation_data()['categories']; }
function public_navigation(string $location): array {
    return array_values(array_filter(navigation_data()['menus'],fn($m)=>$m['active'] && in_array($m['location'],[$location,'both'],true)));
}
function navigation_target_valid(string $target): bool {
    if (in_array($target,['./','admin/','admin/account.php'],true)) return true;
    if (preg_match('/^\?view=(home|all|news|contact|favorites|profile)$/D',$target)) return true;
    if (preg_match('/^\?category=([a-z][a-z0-9_-]{0,29})$/D',$target,$m)) return isset(categories()[$m[1]]);
    return valid_url($target) && strlen($target)<=2048;
}
