<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self'; font-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
if (PHP_SAPI !== 'cli') {
    session_name('SENA_HUB_SESSION');
    session_set_cookie_params(['httponly'=>true,'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','samesite'=>'Lax']);
    ini_set('session.use_strict_mode', '1');
    session_start();
}
function e($value): string { return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function db(): PDO {
    static $pdo;
    if (!$pdo) {
        $file = dirname(__DIR__).'/config/local.php';
        if (!is_file($file)) throw new RuntimeException('ยังไม่ได้ตั้งค่าฐานข้อมูล');
        $c = require $file;
        $pdo = new PDO($c['dsn'], $c['username'], $c['password'], [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC, PDO::ATTR_EMULATE_PREPARES=>false]);
    }
    return $pdo;
}
function categories(): array { return ['student'=>'นักศึกษา','staff'=>'ครูและบุคลากร','management'=>'งานบริหาร','learning'=>'แหล่งเรียนรู้','reports'=>'รายงานและสถิติ','forms'=>'แบบฟอร์ม']; }
function icons(): array { return ['school','users','building','book','chart-bar','file-text','compass','clipboard','certificate','apps']; }
function icon(string $name, string $class=''): string {
    if (!in_array($name, array_merge(icons(), ['search','arrow-up-right','arrow-right','menu-2','x','shield-lock','logout','plus','adjustments','home','device-mobile','link','star','bell','user','phone','speakerphone','arrow-left','heart','stack']), true)) $name='apps';
    return '<img class="icon '.e($class).'" src="'.e(asset_base()).'assets/icons/'.e($name).'.svg" alt="" width="24" height="24">';
}
function asset_base(): string { return str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') ? '../' : ''; }
function settings(): array { return db()->query('SELECT setting_key, setting_value FROM settings')->fetchAll(PDO::FETCH_KEY_PAIR); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function check_csrf(): void { if (!hash_equals(csrf(), (string)($_POST['csrf'] ?? ''))) { http_response_code(403); exit('คำขอหมดอายุ กรุณาโหลดหน้าใหม่'); } }
function valid_url(string $url): bool { return filter_var($url, FILTER_VALIDATE_URL) && in_array(strtolower((string)parse_url($url, PHP_URL_SCHEME)), ['http','https'], true) && !parse_url($url, PHP_URL_USER) && !parse_url($url, PHP_URL_PASS); }
function hub_public_url(string $url): string {
    $base = rtrim(trim($url), '/');
    $path = (string)parse_url($base, PHP_URL_PATH);
    return preg_match('/\.[a-z0-9]{1,8}$/i', $path) ? $base : $base.'/';
}
function require_admin(): void { header('Cache-Control: no-store'); if (empty($_SESSION['admin_id'])) { header('Location: login.php'); exit; } }
function head(string $title, string $bodyClass=''): void { ?>
<!doctype html><html lang="th"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="theme-color" content="#7524ef"><title><?= e($title) ?> · SENA Digital Hub</title><link rel="stylesheet" href="<?= asset_base() ?>assets/css/app.css"><script defer src="<?= asset_base() ?>assets/js/app.js"></script><?php if($bodyClass==='portal'): ?><link rel="stylesheet" href="assets/css/portal.css"><?php elseif(str_contains($bodyClass,'admin-ui')): ?><link rel="stylesheet" href="<?= asset_base() ?>assets/css/admin.css"><?php endif; ?></head><body class="<?= e($bodyClass) ?>">
<?php }
function brand(string $home='./'): void { ?><a class="brand" href="<?= e($home) ?>"><img class="brand-logo" src="<?= asset_base() ?>assets/images/sena-logo.png" width="49" height="49" alt="ตรา สกร.เสนา"><span><strong>SENA <span>DIGITAL HUB</span></strong><small>ศูนย์รวมระบบดิจิทัล สกร.เสนา</small></span></a><?php }
function unavailable(Throwable $error): void { error_log((string)$error); http_response_code(503); head('ระบบยังไม่พร้อม'); echo '<main class="auth-card"><h1>กำลังเตรียมระบบ</h1><p>กรุณาตรวจสอบการตั้งค่าฐานข้อมูล หรือติดต่อผู้ดูแลระบบ</p><a href="./">ลองอีกครั้ง</a></main></body></html>'; exit; }
