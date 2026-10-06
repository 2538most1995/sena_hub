<?php
declare(strict_types=1);

/** Explicit CLI installation only; never invoked by public or admin page loads. */
function install_hub(PDO $pdo, string $schema, bool $seedDemo = false, ?string $password = null): ?string {
    $expected = [
        'systems' => ['id','name','description','url','icon','color','category','sort_order','active','featured','updated_at'],
        'admins' => ['id','username','password_hash'],
        'settings' => ['setting_key','setting_value'],
        'login_attempts' => ['attempt_key','failures','started_at'],
        'system_categories' => ['system_id','category_key'],
        'audit_log' => ['id','actor_id','action','subject','created_at'],
    ];
    // Refuse a database containing another application's tables before any DDL or writes.
    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($tables as $table) {
        if (!isset($expected[$table])) {
            throw new RuntimeException('ฐานข้อมูลนี้มีตารางของระบบอื่น กรุณาสร้างฐานข้อมูลแยกสำหรับ Hub');
        }
        $columns = $pdo->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
        if (array_diff($expected[$table], $columns)) {
            throw new RuntimeException('โครงสร้างตารางไม่ใช่ SENA Digital Hub หยุดติดตั้งเพื่อรักษาข้อมูลเดิม');
        }
    }
    if ($password !== null && (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0"))) {
        throw new RuntimeException('รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร และไม่เกิน 72 ไบต์');
    }
    $pdo->exec($schema); // CREATE TABLE IF NOT EXISTS only; no DROP / ALTER / TRUNCATE.
    $createdPassword = null;
    $pdo->beginTransaction();
    try {
        if ((int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn() === 0) {
            $createdPassword = $password ?? bin2hex(random_bytes(12));
            $stmt = $pdo->prepare('INSERT INTO admins(username,password_hash) VALUES (?,?)');
            $stmt->execute(['admin', password_hash($createdPassword, PASSWORD_DEFAULT)]);
        }
        if ($seedDemo && (int)$pdo->query('SELECT COUNT(*) FROM systems')->fetchColumn() === 0) {
            $rows = [
                ['ข้อมูลนักศึกษา','ตรวจสอบข้อมูลและบริการสำหรับนักศึกษา','school','student',1],
                ['ระบบสอบ N-NET','ข้อมูลการสอบและผลสอบ N-NET','file-text','student',1],
                ['SENA Asset','ทะเบียนและการจัดการครุภัณฑ์','building','management',1],
                ['SENA Guidance','แนะแนวการศึกษาและวางแผนอนาคต','compass','student',1],
                ['ผลการเรียน','ตรวจสอบผลการเรียนและคะแนน','chart-bar','student',0],
                ['กิจกรรม กพช.','กิจกรรมพัฒนาคุณภาพชีวิต','certificate','student',0],
                ['ระบบคะแนนนักศึกษา','บันทึกและจัดการคะแนนนักศึกษา','clipboard','staff',0],
                ['E-Library','หนังสือและสื่อการเรียนรู้ออนไลน์','book','learning',0],
                ['รายงานและสถิติ','ข้อมูลสรุปและรายงานการดำเนินงาน','chart-bar','reports',0],
                ['แบบฟอร์มและเอกสาร','เอกสารสำหรับนักศึกษาและบุคลากร','file-text','forms',0],
            ];
            $stmt = $pdo->prepare('INSERT INTO systems(name,description,icon,category,featured,sort_order) VALUES (?,?,?,?,?,?)');
            foreach ($rows as $i => $row) $stmt->execute([...$row,$i+1]);
        }
        // Only fill missing keys. Preserve the server's URLs, notices, and contact details.
        $stmt = $pdo->prepare('INSERT IGNORE INTO settings(setting_key,setting_value) VALUES (?,?)');
        foreach (['public_url'=>'','contact'=>'ติดต่อครูประจำกลุ่ม หรือเจ้าหน้าที่ สกร.ระดับอำเภอเสนา','notice'=>'เลือกหมวดหมู่หรือค้นหาชื่อระบบที่ต้องการใช้งานได้จากหน้านี้'] as $key=>$value) {
            $stmt->execute([$key,$value]);
        }
        $pdo->exec('INSERT IGNORE INTO system_categories(system_id,category_key) SELECT id,category FROM systems');
        $pdo->commit();
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
    return $createdPassword;
}
