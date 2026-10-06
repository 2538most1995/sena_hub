<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__).'/app/bootstrap.php';
require dirname(__DIR__).'/app/install.php';
try {
    $c = require dirname(__DIR__).'/config/local.php';
    if (in_array('--create-database', $argv, true)) {
        if (!preg_match('/(?:^|;)dbname=([a-zA-Z0-9_-]+)(?:;|$)/', $c['dsn'], $match)) {
            throw new RuntimeException('กรุณาระบุชื่อฐานข้อมูล Hub ที่ถูกต้องใน DSN');
        }
        $server = new PDO(preg_replace('/;dbname=[^;]+/', '', $c['dsn']), $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
        $server->exec('CREATE DATABASE IF NOT EXISTS `'.$match[1].'` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    }
    $password = install_hub(db(), file_get_contents(dirname(__DIR__).'/database/schema.sql'), in_array('--seed-demo', $argv, true), getenv('HUB_ADMIN_PASSWORD') ?: null);
    if ($password !== null) {
        $path = dirname(__DIR__).'/.local-credentials.txt';
        file_put_contents($path,"SENA Digital Hub — บัญชี Admin เริ่มต้น\nUsername: admin\nPassword: $password\nเปลี่ยนรหัสผ่านในหน้า Admin ก่อนเผยแพร่ และห้ามอัปโหลดไฟล์นี้ขึ้นโฮสติ้ง\n");
        chmod($path,0600);
        echo "ติดตั้งสำเร็จ บัญชีใหม่อยู่ใน .local-credentials.txt\n";
    } else {
        echo "ตรวจสอบการติดตั้งแล้ว เก็บบัญชี รหัสผ่าน และข้อมูลเดิมไว้\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, 'หยุดติดตั้ง: '.$error->getMessage().PHP_EOL);
    exit(1);
}
