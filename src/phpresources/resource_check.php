<?php
declare(strict_types=1);
/**
 * resource_check.php - 플랫폼 공통 부트스트랩
 * 직접 접근 차단(main_index_files 상수), DB 연결, 상수, 전역 보조 함수, Logger 로드.
 *
 * 참고: 게시판 resource_check.php 패턴을 현대화.
 *  - CSRF 토큰: 세션당 1회 발급 후 '안정적' 유지 (매 페이지/요청마다 갱신하지 않음)
 *    => AJAX/비동기 통신이 페이지 이동 사이에도 안정 동작.
 *  - 보안 자랑 string 없음.
 */
if (!defined('main_index_files') || main_index_files !== 1024) {
    http_response_code(503);
    echo "503 : Direct Access is Denied!";
    exit;
}

mb_internal_encoding('UTF-8');
date_default_timezone_set('Asia/Seoul');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('log_errors', '1');
ini_set('display_errors', '0');

// --- DB 접속 정보 (compose .env 환경변수) ---
define('DB_HOST', getenv('DB_HOST') ?: 'mariadb');
define('DB_NAME', getenv('DB_NAME') ?: 'joongna');
define('DB_USER', getenv('DB_USER') ?: 'joongna');
define('DB_PASS', getenv('DB_PASS')); // .env 필수, fallback 없음

// 비밀번호 솔트 (.env 환경변수)
define('PW_SALT_PRE', getenv('PW_SALT_PRE') ?: '');
define('PW_SALT_POST', getenv('PW_SALT_POST') ?: '');

define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('UPLOAD_WEB', '/uploads/');
define('MAX_IMG_SIZE', 10 * 1024 * 1024);
define('MAX_VID_SIZE', 20 * 1024 * 1024);
$GLOBALS['ALLOW_IMG_EXT'] = ['png', 'jpeg', 'jpg', 'gif', 'webp', 'heif'];
$GLOBALS['ALLOW_VID_EXT'] = ['mp4', 'webm', 'mov'];
$GLOBALS['ALLOW_IMG_MIME'] = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
$GLOBALS['ALLOW_VID_MIME'] = ['video/mp4', 'video/webm', 'video/quicktime'];

// --- 전역 보조 함수 (절차적 헬퍼; 클래스에서 재사용) ---

/** 실사용자 IP */
function get_user_ip(): array {
    if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return [trim($_SERVER['HTTP_CF_CONNECTING_IP']), 1];
    }
    $ip = '0';
    foreach (['HTTP_CF_CONNECTING_IP','HTTP_X_REAL_IP','HTTP_CLIENT_IP','HTTP_X_FORWARDED_FOR','HTTP_X_FORWARDED','HTTP_FORWARDED_FOR','HTTP_FORWARDED','REMOTE_ADDR'] as $k) {
        $v = $_SERVER[$k] ?? getenv($k) ?? '';
        if ($v !== false && $v !== '') {
            if ($k === 'HTTP_X_FORWARDED_FOR' && strpos($v, ',') !== false) {
                $v = trim(explode(',', $v)[0]);
            }
            if (filter_var($v, FILTER_VALIDATE_IP)) { $ip = $v; break; }
        }
    }
    return [$ip, 0];
}
function client_ip(): string { return get_user_ip()[0]; }

/** 세션 안정 CSRF 토큰 - 없으면 1회 발급 후 유지 */
function mk_sess_tkn(): string {
    return hash('sha256', bin2hex(random_bytes(16)) . client_ip() . 'joongna_sz42#x!q7' . (string)microtime(true));
}
function stable_csrf(): string {
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) !== 64) {
        $_SESSION['csrf_token'] = mk_sess_tkn();
    }
    return $_SESSION['csrf_token'];
}
/** token 회전 (로그인/로그아웃/비번변경 등 민감 전환시만 호출) */
function rotate_csrf(): string {
    $_SESSION['csrf_token'] = mk_sess_tkn();
    return $_SESSION['csrf_token'];
}

function mk_verify_code(): string {
    return str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
}
function hash_password(string $pw): string {
    return hash('sha256', PW_SALT_PRE . $pw . PW_SALT_POST);
}
function now(): string { return date('Y-m-d H:i:s'); }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8'); }
function ejs(string $s): string {
    return str_replace(['\\', "'", '"', "\n", "\r", "\t", '</'], ['\\\\', "\\'", '\\"', '\n', '\r', '\t', '<\\/'], $s);
}
function only_digits($v, int $max = 20): string {
    if (!is_string($v) && !is_int($v)) return '';
    $s = (string)$v;
    return preg_match('/^([0-9]){1,' . $max . '}$/', $s) ? $s : '';
}
function valid_email(string $email): bool {
    return (bool)preg_match('/^([a-zA-Z0-9]{1,24})([\.\-]?[a-zA-Z0-9]{1,12})\@(((gmail|naver|icloud|hanmail)([\.])com)|(daum([\.])net))$/', $email);
}
function valid_accid(string $id): bool { return (bool)preg_match('/^([0-9a-zA-Z]){4,20}$/', $id); }
function valid_password(string $pw): bool { return (bool)preg_match('/^([^<>\n\t\r\f]){8,32}$/', $pw); }

function json_out(array $arr): void {
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
    }
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

/** 공통 방화벽 체크 (index.php + service_bridge 모두 적용). 차단 시 403 후 exit */
function firewall_check(): void {
    $sql = $GLOBALS['__db'] ?? null; if (!$sql) return;
    $ip = client_ip(); if ($ip === '0' || $ip === '') return;
    $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $cc = ip_to_cc($ip);
    // TOR
    $torOn = ((string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='tor'") ?: '0') === '1';
    if ($torOn) {
        $torIps = include __DIR__ . '/tor_ip.php';
        if (is_array($torIps) && in_array($ip, $torIps, true)) {
            http_response_code(403); echo '403 Forbidden'; exit;
        }
    }
    // UA
    $uaOn = ((string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='ua_block'") ?: '0') === '1';
    if ($uaOn && $ua !== '') {
        $uaList = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='ua_list'");
        $uaMode = (string)$sql->scalar("SELECT ss_value FROM system_status WHERE ss_key='ua_mode'");
        $patterns = array_values(array_filter(array_map('trim', explode("\n", $uaList)), function ($p) { return $p !== ''; }));
        if (count($patterns) > 0) {
            if ($uaMode === 'and') {
                $hit = true;
                foreach ($patterns as $p) { if (stripos($ua, $p) === false) { $hit = false; break; } }
                if ($hit) { http_response_code(403); echo '403 Forbidden (UA)'; exit; }
            } else {
                foreach ($patterns as $p) { if (stripos($ua, $p) !== false) { http_response_code(403); echo '403 Forbidden (UA)'; exit; } }
            }
        }
    }
    // IP firewall
    $rows = $sql->run("SELECT fw_type,fw_rule,fw_pattern FROM ip_firewall WHERE fw_rule<>'ua'");
    if ($rows) {
        $we = false; $wo = false;
        foreach ($rows as $r) {
            $m = fw_match($r['fw_rule'], $r['fw_pattern'], $ip, $cc, $ua);
            if ((int)$r['fw_type'] === 1) { $we = true; if ($m) $wo = true; }
            elseif ($m) { http_response_code(403); echo '403 Forbidden (IP)'; exit; }
        }
        if ($we && !$wo) { http_response_code(403); echo '403 Forbidden (Whitelist)'; exit; }
    }
}
function fw_match(string $rule, string $pattern, string $ip, string $cc, string $ua): bool {
    return match ($rule) {
        'ip' => hash_equals($pattern, $ip),
        'wildcard' => fnmatch($pattern, $ip),
        'country' => $cc !== '' && $pattern === $cc,
        'tor' => in_array($ip, array_map('trim', explode(',', $pattern)), true),
        'ua' => $pattern !== '' && stripos($ua, $pattern) !== false,
        default => false,
    };
}
function error_exception(int $level, string $statement): void {
    $tag = $level == 2 ? 'Error' : ($level == 1 ? 'Warning' : 'Alert');
    error_log("[$tag] $statement [time:" . time() . "]");
    if ($level >= 2 && PHP_SAPI !== 'cli') {
        json_out(['error' => 1, 'description' => '서버 처리 중 오류가 발생했습니다. 잠시 후 다시 시도해주세요.']);
    }
    if ($level >= 2) exit;
}

function ip_to_cc(string $ip): string {
    if ($ip === '0' || $ip === '') return '';
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return 'KR';
    }
    // DB cache lookup
    $sql = $GLOBALS['__db'] ?? null;
    if ($sql) {
        $sql->run("CREATE TABLE IF NOT EXISTS ip_country (ic_ip VARCHAR(45) NOT NULL, ic_cc VARCHAR(2) NOT NULL, ic_time DATETIME NOT NULL, PRIMARY KEY(ic_ip)) ENGINE=InnoDB");
        $row = $sql->one("SELECT ic_cc,ic_time FROM ip_country WHERE ic_ip=?", 's', [$ip]);
        if ($row && strtotime($row['ic_time']) > time() - 86400) return $row['ic_cc'];
        // Try API
        $ctx = stream_context_create(['http'=>['timeout'=>2,'method'=>'GET']]);
        $json = @file_get_contents("http://ip-api.com/json/{$ip}?fields=countryCode", false, $ctx);
        if ($json) { $data = json_decode($json, true); if (isset($data['countryCode'])) { $cc = $data['countryCode']; $sql->run("REPLACE INTO ip_country (ic_ip,ic_cc,ic_time) VALUES (?,?,NOW())", 'ss', [$ip,$cc]); return $cc; } }
    }
    return '';
}

require_once __DIR__ . '/badwords.php';

function ext_extract(string $filename): string {
    if (strlen($filename) > 255) return '';
    $base = trim(basename($filename));
    if ($base === '') return '';
    $parts = explode('.', $base);
    $i = count($parts) - 1;
    while ($i >= 0) {
        $ext = strtolower(trim($parts[$i]));
        if ($ext !== '') return $ext;
        $i--;
    }
    return '';
}

function chk_image_file(string $path): string {
    if (!is_uploaded_file($path)) return 'not uploaded';
    $info = @getimagesize($path);
    if ($info === false) return 'invalid image';
    $chk = 'true';
    $im = false;
    switch ($info['mime']) {
        case 'image/gif':  $im = @imagecreatefromgif($path);  if ($im === false) $chk = 'invalid gif';  break;
        case 'image/jpeg': $im = @imagecreatefromjpeg($path); if ($im === false) $chk = 'invalid jpg';  break;
        case 'image/png':  $im = @imagecreatefrompng($path);  if ($im === false) $chk = 'invalid png';  break;
        case 'image/webp': $im = @imagecreatefromwebp($path); if ($im === false) $chk = 'invalid webp'; break;
        default: $chk = 'unsupported mime';
    }
    if ((is_resource($im) || $im instanceof \GdImage)) imagedestroy($im);
    return $chk;
}

function chk_video_file(string $path): string {
    if (!is_uploaded_file($path)) return 'not uploaded';
    if (!class_exists('finfo')) return 'finfo missing';
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($path);
    if (!in_array($mime, ['video/mp4', 'video/webm', 'video/quicktime'], true)) return 'invalid video';
    $fp = @fopen($path, 'rb');
    if (!$fp) return 'read fail';
    $head = fread($fp, 12);
    fclose($fp);
    if ($mime === 'video/webm') {
        if (strncmp($head, "\x1A\x45\xDF\xA3", 4) !== 0) return 'bad webm header';
    } else {
        if (strpos($head, 'ftyp') === false) return 'bad mp4 header';
    }
    return 'true';
}

// --- 오토로드 (Security/Database/FileUpload/Logger/Bridge\* ...) ---
spl_autoload_register(function (string $cls) {
    // 네임스페이스 Bridge\X => phpresources/Bridge/X.php 우선
    $rel = str_replace(['\\', '\\\\'], '/', $cls);
    // 1) phpresources/<Class>.php (네임스페이스 무관 파일명 match)
    $bare = substr(strrchr('/' . $cls, '/'), 1);
    $path1 = __DIR__ . '/' . $bare . '.php';
    if (is_file($path1)) { require_once $path1; return; }
    // 2) 네임스페이스 경로
    if (strpos($cls, 'Bridge') === 0 || strpos($rel, 'Bridge') === 0) {
        $path2 = __DIR__ . '/' . $rel . '.php';
        if (is_file($path2)) { require_once $path2; return; }
    }
    // 3) 그 외 phpresources 하위
    $path3 = __DIR__ . '/' . $rel . '.php';
    if (is_file($path3)) { require_once $path3; }
});

if (!isset($GLOBALS['__db'])) {
    $GLOBALS['__db'] = new Database();
}
/** @var Database $sql */
$sql = $GLOBALS['__db'];
if ($sql->connect_errno()) error_exception(2, "DB 접속 불가");
$sql->set_charset('utf8mb4');