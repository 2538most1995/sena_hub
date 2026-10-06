<?php
declare(strict_types=1);

function account_roles_ready(PDO $pdo): bool {
    return in_array('role', $pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN), true);
}
function enable_account_roles(PDO $pdo): void {
    $columns = $pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN);
    if (array_diff(['id','username','password_hash'], $columns)) throw new RuntimeException('โครงสร้างบัญชีไม่ตรงกับ SENA Hub');
    if (!in_array('role', $columns, true)) {
        // Add only: keep every existing username, password hash and record.
        $pdo->exec("ALTER TABLE admins ADD COLUMN role VARCHAR(20) NOT NULL DEFAULT 'admin'");
    }
}
function account_input_error(string $username, string $password, string $confirm, bool $passwordRequired): string {
    if ($username === '' || mb_strlen($username) > 100 || preg_match('/[\x00-\x1F\x7F]/u', $username)) return 'ชื่อผู้ใช้ต้องมี 1–100 ตัวอักษร และไม่มีอักขระควบคุม';
    if ($passwordRequired || $password !== '') {
        if (mb_strlen($password) < 8 || strlen($password) > 72 || str_contains($password, "\0")) return 'รหัสผ่านต้องมีอย่างน้อย 8 ตัวอักษร และไม่เกิน 72 ไบต์ (ภาษาไทยใช้หลายไบต์ต่ออักษร)';
        if ($password !== $confirm) return 'รหัสผ่านทั้งสองช่องไม่ตรงกัน';
    } elseif ($confirm !== '') return 'กรุณากรอกรหัสผ่านใหม่ให้ตรงกัน';
    return '';
}
function save_hub_account(PDO $pdo, int $id, string $username, string $password, string $confirm, string $role, ?array $profile = null): int {
    $error = account_input_error($username, $password, $confirm, $id === 0);
    if ($error !== '') throw new InvalidArgumentException($error);
    if (!in_array($role, ['admin','teacher'], true)) throw new InvalidArgumentException('ระดับผู้ใช้ไม่ถูกต้อง');
    if (!account_roles_ready($pdo)) throw new InvalidArgumentException('กรุณาเปิดใช้งานบัญชีครูก่อน');
    $pdo->beginTransaction();
    try {
        // Serialize account edits to prevent two administrators demoting the last admins concurrently.
        $hasActive=in_array('active',$pdo->query('SHOW COLUMNS FROM admins')->fetchAll(PDO::FETCH_COLUMN),true);
        $rows = $pdo->query('SELECT id,role'.($hasActive?',active':',1 AS active').' FROM admins ORDER BY id FOR UPDATE')->fetchAll(PDO::FETCH_ASSOC);
        $current = null; $adminCount = 0;
        foreach ($rows as $row) { if ($row['role'] === 'admin' && $row['active']) $adminCount++; if ((int)$row['id'] === $id) $current = $row; }
        if ($id && !$current) throw new InvalidArgumentException('ไม่พบบัญชีที่ต้องการแก้ไข');
        if ($current && $current['role'] === 'admin' && $current['active'] && $role !== 'admin' && $adminCount <= 1) throw new InvalidArgumentException('ต้องเหลือผู้ดูแลระบบอย่างน้อย 1 บัญชี');
        if ($id) {
            $sql = 'UPDATE admins SET username=?,role=?'; $values = [$username,$role];
            if ($password !== '') { $sql .= ',password_hash=?'; $values[] = password_hash($password, PASSWORD_DEFAULT); }
            $stmt = $pdo->prepare($sql.' WHERE id=?'); $stmt->execute([...$values,$id]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO admins(username,password_hash,role) VALUES (?,?,?)');
            $stmt->execute([$username,password_hash($password, PASSWORD_DEFAULT),$role]); $id = (int)$pdo->lastInsertId();
        }
        if($profile!==null) save_profile($pdo,$id,$profile);
        if(function_exists('audit_change')) audit_change($pdo,'account.save','บัญชี #'.$id);
        $pdo->commit(); return $id;
    } catch (Throwable $error) {
        $pdo->rollBack();
        if ($error instanceof PDOException && ($error->errorInfo[1] ?? null) === 1062) throw new InvalidArgumentException('ชื่อผู้ใช้นี้มีอยู่แล้ว กรุณาใช้ชื่ออื่น');
        throw $error;
    }
}
