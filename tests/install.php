<?php
// Isolated MySQL fixtures; never changes the running Hub database.
require dirname(__DIR__).'/app/install.php';
$c = require dirname(__DIR__).'/config/local.php';
$serverDsn = preg_replace('/;dbname=[^;]+/', '', $c['dsn']);
$pdo = new PDO($serverDsn, $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name = 'hub_install_test_'.bin2hex(random_bytes(5));
$schema = file_get_contents(dirname(__DIR__).'/database/schema.sql');
function expect(bool $value, string $label): void { if (!$value) throw new RuntimeException($label); echo 'PASS '.$label.PHP_EOL; }
function snapshot(PDO $pdo): array { $result=[]; foreach(['admins','systems','settings','login_attempts'] as $table) $result[$table]=$pdo->query('SELECT * FROM '.$table.' ORDER BY 1')->fetchAll(PDO::FETCH_ASSOC); return $result; }
try {
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4');
    $pdo->exec('USE `'.$name.'`');
    expect(install_hub($pdo,$schema) !== null, 'fresh install creates a private random admin password');
    expect((int)$pdo->query('SELECT COUNT(*) FROM systems')->fetchColumn()===0, 'production install does not seed demo systems by default');
    $pdo->exec("UPDATE settings SET setting_value='https://live.example.org/' WHERE setting_key='public_url'");
    $pdo->exec("INSERT INTO systems(name,url) VALUES ('Server data','https://live.example.org/system')");
    $before=snapshot($pdo);
    expect(install_hub($pdo,$schema,true,'NewPasswordMustNotReplaceOld')===null, 'repeat install does not reset admin password');
    expect(snapshot($pdo)===$before, 'repeat install preserves all existing records and does not duplicate seeds');
    $pdo->exec('CREATE TABLE unrelated_existing_site (id INT PRIMARY KEY)');
    $pdo->exec('INSERT INTO unrelated_existing_site VALUES (42)');
    $blocked=false;
    try { install_hub($pdo,$schema); } catch (RuntimeException $error) { $blocked=true; }
    expect($blocked && snapshot($pdo)===$before && (int)$pdo->query('SELECT id FROM unrelated_existing_site')->fetchColumn()===42, 'refuses a foreign database before modifying records');
    $pdo->exec('DROP TABLE unrelated_existing_site');
    $pdo->exec('DROP TABLE system_categories');
    $pdo->exec('DROP TABLE systems');
    $pdo->exec('CREATE TABLE systems (id INT PRIMARY KEY)');
    $blocked=false;
    try { install_hub($pdo,$schema); } catch (RuntimeException $error) { $blocked=true; }
    expect($blocked, 'refuses conflicting table structure');
} finally {
    $pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`');
}
