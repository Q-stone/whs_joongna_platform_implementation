<?php
/**
 * is_valid_user.php - 세션 기반 사용자 인증/검증.
 * (참고: 게시단 is_valid_user.php - onelogin 카운터, IP 검증, 제재계정 처리)
 *
 * 전역 $system_v['login'] 에 상태 주입.
 * IP 보안(ip_security 테이블 설정 기반):
 *  - is_check_ip ON: 로그인 당시 acc_login_ip 와 현재 IP 불일치 시 파기
 *  - is_check_cc  ON: is_allow_cc 와 현재 국가 불일치 시 파기 (개발환경 사설망=KR)
 */
if (!defined('main_index_files') || main_index_files !== 1024) {
    http_response_code(503); echo "503 : Direct Access is Denied!"; exit;
}
if (!isset($system_v) || !is_array($system_v)) {
    echo "100 : Initialize Error."; exit;
}

require_once __DIR__ . '/resource_check.php';
if (!isset($L)) require __DIR__ . '/language/language_kor.php';

// 기본 상태 (게스트/옵저버)
$system_v['login']['valid'] = 0;
list($obs_ip) = get_user_ip();
$system_v['login']['id'] = '옵저버 : ' . $obs_ip;
$system_v['login']['authorize_phrase'] = '로그인';
$system_v['login']['authorize_link'] = '/?mode=authorize';
$system_v['login']['number'] = null;
$system_v['login']['dormant'] = false;
$system_v['login']['banned'] = false;
$system_v['login']['report_ban'] = false;
$system_v['login']['group'] = 0;
$system_v['login']['balance'] = 0;
$system_v['login']['trust'] = 0;
$system_v['login']['sell'] = 0;
$system_v['login']['buy'] = 0;

function self_logout(): void {
    $lg_e = $_SESSION['form_request_limit'] ?? 0;
    $lg_r = $_SESSION['form_limit_time'] ?? time();
    unset($_SESSION['login']);
    if (session_status() === PHP_SESSION_ACTIVE) { session_destroy(); session_start(); }
    if (!isset($_SESSION['form_request_limit'])) $_SESSION['form_request_limit'] = $lg_e;
    if (!isset($_SESSION['form_limit_time'])) $_SESSION['form_limit_time'] = $lg_r;
}

if (!(isset($_SESSION['login']) && (int)($_SESSION['login']['valid'] ?? 0) === 1 && isset($_SESSION['login']['number']))) {
    return; // 게스트 상태 유지
}

global $sql;
$myNum = (int)$_SESSION['login']['number'];
$u = $sql->one(
    "SELECT acc_number, acc_id, acc_nick, acc_onelogin, acc_group, acc_status, acc_report_ban,
            acc_email, acc_intro, acc_phone, acc_address, acc_totp_ok, acc_balance,
            acc_trust, acc_trade_count, acc_sell_count, acc_buy_count, acc_login_ip
     FROM users WHERE acc_number=?",
    'i', [$myNum]
);

if (!$u) { self_logout(); return; }

// 전체로그아웃 카운터(svn_increment) 검증
if (!Security::onelogin_ok($u)) { self_logout(); return; }

// 정지 계정 -> 세션 비활성
$st = (int)$u['acc_status'];
if ($st === 3) { self_logout(); return; }

// IP 보안 (설정 기반) - ip_security 행에서 on/off 확인
list($ip_now) = get_user_ip();
$ipsec = $sql->one("SELECT is_check_ip, is_check_cc, is_allow_cc FROM ip_security WHERE is_user=?", 'i', [$myNum]);
$enforce = false;
if ($ipsec) {
    if ((int)$ipsec['is_check_ip'] === 1) {
        if ($u['acc_login_ip'] !== '' && $ip_now !== '0' && $ip_now !== '' && $u['acc_login_ip'] !== $ip_now) {
            $enforce = true;
        }
    }
    if ((int)$ipsec['is_check_cc'] === 1 && $ipsec['is_allow_cc'] !== '') {
        $cc_now = ip_to_cc($ip_now);
        if ($cc_now !== '' && $cc_now !== $ipsec['is_allow_cc']) {
            $enforce = true;
        }
    }
}
if ($enforce) { self_logout(); return; }

// 정상 세션 갱신
$system_v['login']['valid'] = 1;
$system_v['login']['id'] = '유저 : ' . $u['acc_id'];
$system_v['login']['nick'] = $u['acc_nick']; // 닉네임
$system_v['login']['number'] = (int)$u['acc_number'];
$system_v['login']['group'] = (int)$u['acc_group']; // 1=관리자
$system_v['login']['authorize_phrase'] = '로그아웃';
$system_v['login']['authorize_link'] = '/?mode=authorize&logout';
$system_v['login']['email'] = $u['acc_email'];
$system_v['login']['intro'] = $u['acc_intro'];
$system_v['login']['balance'] = (int)$u['acc_balance'];
$system_v['login']['trust'] = (float)$u['acc_trust'];
$system_v['login']['sell'] = (int)$u['acc_sell_count'];
$system_v['login']['buy'] = (int)$u['acc_buy_count'];
$system_v['login']['dormant'] = ($st === 2);
$system_v['login']['banned'] = ($st === 3);
$system_v['login']['report_ban'] = ((int)$u['acc_report_ban'] === 1);
$_SESSION['user_ip'] = $ip_now;