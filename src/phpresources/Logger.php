<?php
declare(strict_types=1);
/**
 * Logger.php - DB 기반 감사 로깅 (행위 상세 메모 포함).
 * - login_log: 로그인 시도 (성공/실패)
 * - admin_log: 관리자 액션 (누가/무엇을/어떻게)
 * - request_log: 모든 service_bridge 요청 (사용자/IP/UA/mode/메시지/ok)
 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }

class Logger {
    public static function login(?int $uid, string $ip, string $cc, string $ua, int $ok): void {
        $sql = $GLOBALS['__db'];
        $sql->run("INSERT INTO login_log (lg_user,lg_ip,lg_cc,lg_agent,lg_ok,lg_time) VALUES (?,?,?,?,?,NOW())",
            'isssi', [$uid ?? 0, $ip, $cc, mb_substr($ua, 0, 250), $ok]);
    }

    /** 관리자 액션 로그: action/대상/상세메모(어떻게) */
    public static function admin(int $adminId, string $action, ?int $target = null, string $memo = ''): void {
        $sql = $GLOBALS['__db'];
        $sql->run("INSERT INTO admin_log (am_admin,am_action,am_target,am_memo,am_time) VALUES (?,?,?,?,NOW())",
            'isis', [$adminId, mb_substr($action,0,50), $target, mb_substr($memo,0,300)]);
    }

    /** service_bridge 요청 단위 로그 (mode/결과/메시지/사용자명/IP/UA) */
    public static function request(string $mode, int $ok, string $msg = '', ?int $uid = null, string $ip = '', string $ua = ''): void {
        $sql = $GLOBALS['__db'];
        $sql->run("CREATE TABLE IF NOT EXISTS request_log (
            rl_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            rl_user BIGINT NULL DEFAULT NULL,
            rl_user_name VARCHAR(20) NOT NULL DEFAULT '',
            rl_mode VARCHAR(40) NOT NULL,
            rl_msg VARCHAR(300) NOT NULL DEFAULT '',
            rl_ok TINYINT UNSIGNED NOT NULL,
            rl_ip VARCHAR(45) NOT NULL DEFAULT '',
            rl_ua VARCHAR(255) NOT NULL DEFAULT '',
            rl_memo VARCHAR(300) NOT NULL DEFAULT '',
            rl_time DATETIME NOT NULL,
            PRIMARY KEY (rl_id), KEY idx_user(rl_user), KEY idx_mode(rl_mode), KEY idx_time(rl_time DESC), KEY idx_ip(rl_ip)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $uname = '';
        if ($uid) {
            $uname = (string)$sql->scalar("SELECT acc_id FROM users WHERE acc_number=?", 'i', [$uid]);
        }
        if ($ua === '') $ua = mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 250);
        $sql->run("INSERT INTO request_log (rl_user,rl_user_name,rl_mode,rl_msg,rl_ok,rl_ip,rl_ua,rl_memo,rl_time)
                   VALUES (?,?,?,?,?,?,?,?,NOW())",
            'issiisss', [$uid ?? null, $uname, $mode, mb_substr($msg, 0, 280), $ok, $ip, $ua, mb_substr($msg, 0, 280)]);
    }
}