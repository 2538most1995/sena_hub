<?php
declare(strict_types=1);

function management_ready(PDO $pdo,bool $refresh=false): bool {
    static $ready=[];
    $key=spl_object_id($pdo);
    if($refresh)unset($ready[$key]);
    return $ready[$key] ??= !array_diff(['display_name','email','phone','position','bio','avatar_file','active'],$pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN))
        && (bool)$pdo->query("SHOW TABLES LIKE 'system_categories'")->fetchColumn()
        && (bool)$pdo->query("SHOW TABLES LIKE 'audit_log'")->fetchColumn();
}
function upgrade_hub(PDO $pdo): void {
    require_once __DIR__.'/accounts.php';
    enable_account_roles($pdo);
    $columns=$pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);
    foreach (['display_name'=>"VARCHAR(150) NOT NULL DEFAULT ''",'email'=>"VARCHAR(254) NOT NULL DEFAULT ''",'phone'=>"VARCHAR(40) NOT NULL DEFAULT ''",'position'=>"VARCHAR(150) NOT NULL DEFAULT ''",'bio'=>"TEXT NULL",'avatar_file'=>"VARCHAR(40) NULL",'active'=>"TINYINT(1) NOT NULL DEFAULT 1"] as $name=>$definition) {
        if (!in_array($name,$columns,true)) $pdo->exec("ALTER TABLE admins ADD COLUMN `$name` $definition");
    }
    $pdo->exec('CREATE TABLE IF NOT EXISTS system_categories (system_id INT UNSIGNED NOT NULL, category_key VARCHAR(30) NOT NULL, PRIMARY KEY(system_id,category_key), INDEX category_lookup(category_key,system_id), FOREIGN KEY(system_id) REFERENCES systems(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $pdo->exec("CREATE TABLE IF NOT EXISTS audit_log (id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, actor_id INT UNSIGNED NOT NULL, action VARCHAR(40) NOT NULL, subject VARCHAR(200) NOT NULL, created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX audit_time(created_at,id), INDEX audit_actor(actor_id,id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $indexes=$pdo->query('SHOW INDEX FROM systems')->fetchAll(PDO::FETCH_ASSOC);
    if (!in_array('catalog_order',array_column($indexes,'Key_name'),true)) $pdo->exec('ALTER TABLE systems ADD INDEX catalog_order(active,sort_order,id)');
    // Rebuild only memberships, preserving service IDs and the legacy primary category.
    $stored=$pdo->query("SELECT setting_key,setting_value FROM settings WHERE setting_key LIKE 'system_categories_%'")->fetchAll(PDO::FETCH_KEY_PAIR);
    $insert=$pdo->prepare('INSERT IGNORE INTO system_categories(system_id,category_key) VALUES (?,?)');
    $pdo->beginTransaction();
    try {
        foreach($pdo->query('SELECT id,category FROM systems') as $s) {
            $selected=json_decode($stored['system_categories_'.$s['id']] ?? '',true);
            if (!is_array($selected) || !$selected) $selected=[$s['category']];
            foreach(array_unique($selected) as $key) if (is_string($key) && preg_match('/^[a-z][a-z0-9_-]{0,29}$/D',$key)) $insert->execute([$s['id'],$key]);
        }
        $pdo->commit();management_ready($pdo,true);
    } catch(Throwable $error) { $pdo->rollBack(); throw $error; }
}
function audit_change(PDO $pdo,string $action,string $subject): void {
    if (!management_ready($pdo)) return;
    $stmt=$pdo->prepare('INSERT INTO audit_log(actor_id,action,subject) VALUES (?,?,?)');
    $stmt->execute([(int)($_SESSION['admin_id'] ?? 0),$action,mb_substr($subject,0,200)]);
}
function save_setting(PDO $pdo,string $key,string $value): void {
    $stmt=$pdo->prepare('INSERT INTO settings(setting_key,setting_value) VALUES (?,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value)');
    $stmt->execute([$key,$value]);
}
function profile_input(array $input): array {
    $profile=[];
    foreach(['display_name'=>150,'email'=>254,'phone'=>40,'position'=>150,'bio'=>1000] as $key=>$limit) {
        $value=trim((string)($input[$key] ?? ''));
        if (mb_strlen($value)>$limit || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u',$value)) throw new InvalidArgumentException('ข้อมูลโปรไฟล์ยาวเกินกำหนดหรือมีอักขระที่ไม่รองรับ');
        $profile[$key]=$value;
    }
    if ($profile['email']!=='' && !filter_var($profile['email'],FILTER_VALIDATE_EMAIL)) throw new InvalidArgumentException('รูปแบบอีเมลไม่ถูกต้อง');
    return $profile;
}
function save_profile(PDO $pdo,int $id,array $profile,?string $avatar=null,bool $remove=false): void {
    $sql='UPDATE admins SET display_name=?,email=?,phone=?,position=?,bio=?';
    $values=array_values($profile);
    if ($avatar!==null || $remove) { $sql.=',avatar_file=?'; $values[]=$avatar; }
    $stmt=$pdo->prepare($sql.' WHERE id=?'); $stmt->execute([...$values,$id]);
}
function set_account_active(PDO $pdo,int $id,bool $active): void {
    $pdo->beginTransaction();
    try {
        $rows=$pdo->query('SELECT id,role,active FROM admins ORDER BY id FOR UPDATE')->fetchAll();
        $target=null;$count=0;
        foreach($rows as $row) { if ($row['role']==='admin' && $row['active']) $count++; if ((int)$row['id']===$id) $target=$row; }
        if (!$target) throw new InvalidArgumentException('ไม่พบบัญชีผู้ใช้');
        if (!$active && $target['role']==='admin' && $target['active'] && $count<=1) throw new InvalidArgumentException('ต้องเหลือผู้ดูแลระบบที่ใช้งานได้อย่างน้อย 1 บัญชี');
        if (!$active && $id===(int)($_SESSION['admin_id'] ?? 0)) throw new InvalidArgumentException('ไม่สามารถปิดบัญชีที่กำลังใช้งานอยู่');
        $stmt=$pdo->prepare('UPDATE admins SET active=? WHERE id=?');$stmt->execute([(int)$active,$id]);
        audit_change($pdo,$active?'account.enable':'account.disable','บัญชี #'.$id);$pdo->commit();
    } catch(Throwable $error) { $pdo->rollBack();throw $error; }
}

function settings_for_systems(PDO $pdo,array $ids): array {
    $keys=[];
    foreach(array_unique(array_map('intval',$ids)) as $id) {
        foreach(['system_categories_','system_icon_','system_upload_','favicon_rev_'] as $prefix)$keys[]=$prefix.$id;
    }
    if(!$keys)return [];
    $stmt=$pdo->prepare('SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('.implode(',',array_fill(0,count($keys),'?')).')');
    $stmt->execute($keys);return $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
}
