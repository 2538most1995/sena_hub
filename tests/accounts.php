<?php
require dirname(__DIR__).'/app/accounts.php';
$c=require dirname(__DIR__).'/config/local.php';
$pdo=new PDO(preg_replace('/;dbname=[^;]+/', '', $c['dsn']),$c['username'],$c['password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$name='hub_accounts_test_'.bin2hex(random_bytes(5));
function check_account(bool $condition,string $label): void { if(!$condition) throw new RuntimeException($label); echo "PASS $label\n"; }
try {
    $pdo->exec('CREATE DATABASE `'.$name.'` CHARACTER SET utf8mb4'); $pdo->exec('USE `'.$name.'`');
    $pdo->exec('CREATE TABLE admins(id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,username VARCHAR(100) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
    $hash=password_hash('ExistingPassword',PASSWORD_DEFAULT); $stmt=$pdo->prepare('INSERT INTO admins(username,password_hash) VALUES (?,?)'); $stmt->execute(['admin',$hash]);
    enable_account_roles($pdo); enable_account_roles($pdo);
    check_account($pdo->query('SELECT password_hash FROM admins')->fetchColumn()===$hash,'migration is repeatable and preserves existing password');
    check_account($pdo->query('SELECT role FROM admins')->fetchColumn()==='admin','existing account remains administrator');
    check_account(account_input_error('teacher','1234567','1234567',true)!=='','reject seven characters');
    $id=save_hub_account($pdo,0,'ครูทดสอบ','12345678','12345678','teacher');
    check_account(password_verify('12345678',$pdo->query('SELECT password_hash FROM admins WHERE id='.$id)->fetchColumn()),'eight character password accepted and hashed');
    $before=$pdo->query('SELECT password_hash FROM admins WHERE id='.$id)->fetchColumn(); save_hub_account($pdo,$id,'ชื่อครูใหม่','','','teacher');
    check_account($pdo->query('SELECT password_hash FROM admins WHERE id='.$id)->fetchColumn()===$before,'rename without resetting password');
    $blocked=false; try{save_hub_account($pdo,$id,'admin','','','teacher');}catch(InvalidArgumentException $e){$blocked=true;}
    check_account($blocked && $pdo->query('SELECT username FROM admins WHERE id='.$id)->fetchColumn()==='ชื่อครูใหม่','duplicate username rejected without partial update');
    $blocked=false; try{save_hub_account($pdo,1,'admin','','','teacher');}catch(InvalidArgumentException $e){$blocked=true;}
    check_account($blocked,'last administrator cannot be demoted');
    $blocked=false; try{save_hub_account($pdo,$id,'teacher','','','superadmin');}catch(InvalidArgumentException $e){$blocked=true;}
    check_account($blocked,'invalid role rejected');
} finally { $pdo->exec('DROP DATABASE IF EXISTS `'.$name.'`'); }
