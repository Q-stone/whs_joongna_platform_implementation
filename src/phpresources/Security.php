<?php
/**
 * Security.php - 보안 핵심 클래스 (상속 기반, 명시적 메서드).
 *
 * 담당:
 *  - CSRF 토큰 (세션 기반. 매 페이지 진입마다 갱신, 클라이언트 localStorage에 보관)
 *  - Rate Limit (세션 내 폼 실패 카운트 + 타임아웃)
 *  - IP 보안 (로그인 당시 IP/국가 vs 현재 IP/국가 비교 - on/off)
 *  - 제재/휴면 상태 확인 (acc_onelogin 카운터로 전체로그아웃 감지 / acc_status==2 휴면)
 *  - 욕설 필터, 이스케이핑 헬퍼
 *
 * (참고 게시판 is_valid_user.php / resource_check.php 의 보안 로직을 클래스화)
 */
if (!defined('main_index_files')) {
    http_response_code(503); echo "503 : Direct Access is Denied!"; exit;
}

class Security {
    /** 세션 안정 CSRF 토큰 (1회 발급 후 유지) */
    public static function csrf(): string { return stable_csrf(); }

    /** CSRF 검증. POST 'token' / 헤더 'X-CSRF-Token' / GET 'token'(poll 전용) 지원.
 *  HMAC 윈도우 기반: 현재·직전·다음 15분 윈도우 허용 (시계 오차 대응) */
    public static function csrf_ok(): bool {
        $secret = $_SESSION['csrf_secret'] ?? ''; if ($secret === '') return false;
        $given = '';
        if (!empty($_POST['token'])) $given = trim($_POST['token']);
        elseif (!empty($_SERVER['HTTP_X_CSRF_TOKEN'])) $given = trim($_SERVER['HTTP_X_CSRF_TOKEN']);
        elseif (!empty($_GET['token'])) $given = trim($_GET['token']);
        if ($given === '' || !preg_match('/^([a-f0-9]){64}$/', $given)) return false;
        $sid = session_id();
        $w = intdiv(time(), 900); // 15분 윈도우
        // ±1 윈도우 허용 (시계 차이 + 토큰 만료 직전 대응)
        for ($i = -1; $i <= 1; $i++) {
            if (hash_equals(hash_hmac('sha256', $sid . ':' . ($w + $i), $secret), $given)) return true;
        }
        return false;
    }

    /** CSRF 실패 시 즉시 에러 응답 */
    public static function csrf_guard(): void {
        if (!self::csrf_ok()) {
            json_out(['error' => 1, 'description' => '보안 토큰이 만료되었습니다. 페이지를 새로고침 후 다시 시도하세요.', 'csrf_expired' => 1]);
        }
    }

    /**
     * 페이지 ACL 검증 (참고 게시판 page_acl 정책).
     * 모든 ajax 요청은 해당 페이지에서 부여받은 ACL과 일치해야 함.
     */
    public static function acl_guard(string $required_acl): void {
        if (empty($_SESSION['page_acl']) || $_SESSION['page_acl'] !== $required_acl) {
            unset($_SESSION['secure_token']);
            json_out(['error' => 1, 'description' => '503 Wrong Access (page_acl).']);
        }
    }

    /**
     * Rate limit: 폼 실패 누적. 10회 초과 시 2시간 동안 타임아웃.
     * (참고 게시판 form_request_limit / form_limit_time 동일 로직)
     */
    public static function rate_limit_tick(): void {
        if (!isset($_SESSION['form_request_limit'])) {
            $_SESSION['form_request_limit'] = 1;
        } else {
            if (!isset($_SESSION['form_limit_time'])) {
                $_SESSION['form_request_limit'] += 1;
            }
        }
    }

    public static function rate_limit_check(): void {
        if (isset($_SESSION['form_limit_time'])) {
            if (time() > (int)$_SESSION['form_limit_time']) {
                unset($_SESSION['form_limit_time']);
                unset($_SESSION['form_request_limit']);
            } else {
                sleep(2);
            }
        }
    }

    public static function rate_limit_hit(): bool {
        if (!isset($_SESSION['form_request_limit'])) return false;
        if ((int)$_SESSION['form_request_limit'] > 10) {
            if (!isset($_SESSION['form_limit_time'])) {
                $_SESSION['form_limit_time'] = time() + 7200;
            }
            unset($_SESSION['form_request_limit']);
            return true;
        }
        return false;
    }

    /**
     * IP 보안 검사:
     * - 로그인 당시 IP(acc_login_ip) vs 현재 IP (is_check_ip ON 일 때)
     * - 최초 접속 국가(is_allow_cc) vs 현재 국가 (is_check_cc ON 일 때)
     * 위반 시 false. 원칙은 세션 파기 + 재로그인 유도.
     * @param array $user  users 행
     */
    public static function ip_security_ok(array $user): bool {
        list($ip_now) = get_user_ip();

        // ip_security 행 조회
        global $sql;
        $row = $sql->one("SELECT * FROM ip_security WHERE is_user = ?", 'i', [$user['acc_number']]);

        // 미설정이면 IP/국가 검사 모두 비활성화로 간주 -> 통과
        if (!$row) return true;

        // 1) IP 검사
        if ((int)$row['is_check_ip'] === 1) {
            $saved = $user['acc_login_ip'];
            if ($saved !== '' && $ip_now !== '0' && $ip_now !== '' && $saved !== $ip_now) {
                return false;
            }
        }
        // 2) 국가 검사 (허용 국가 단일 지정)
        if ((int)$row['is_check_cc'] === 1 && $row['is_allow_cc'] !== '') {
            $cc_now = ip_to_cc($ip_now);
            if ($cc_now !== '' && $cc_now !== $row['is_allow_cc']) {
                return false;
            }
            // 외부 GeoIP 미사용으로 cc_now 빈값이면 (개발환경) 통과
        }
        return true;
    }

    /**
     * 전체로그아웃 카운터(svn_increment) 검증.
     * (참고: 게시판 acc_onelogin 비교)
     * DB의 acc_onelogin != 세션 onelogin 이면 세션 파기 필요 -> false 반환.
     */
    public static function onelogin_ok(array $user): bool {
        if (!isset($_SESSION['login']['onelogin'])) return false;
        return (int)$_SESSION['login']['onelogin'] === (int)$user['acc_onelogin'];
    }

    /**
     * 제재/휴면 상태 판정.
     * @return array [ok:bool, dormant:bool, reason:string]
     *  - ok=true: 정상 접근/활동 가능
     *  - dormant=true: 휴면(acc_status==2). 검색/목록은 가능, 상세/활동 차단.
     */
    public static function status_check(array $user): array {
        $st = (int)$user['acc_status'];
        if ($st === 3) return [false, false, '정지된 계정입니다.'];
        if ($st === 2) return [false, true, '신고 누적 또는 장기 미접속으로 임시조치(휴면)된 계정입니다. 관리자 검토 대기 중.'];
        return [true, false, ''];
    }

    /** 욕설 검사 헬퍼 (전역 bad_words_check 위임) */
    public static function badwords($s): array { return bad_words_check($s); }

    /**
     * 리치 텍스트 저장: HTML 허용 태그 + 개행→br 이중 인코딩으로 DB 저장.
     * 순서: strip unsafe → escape all(저장 안전) → nl2br → DB.
     * DB에는 &lt;b&gt;같은 entity와 &lt;br /&gt;가 공존.
     */
    public static function purify_rich(string $s): string {
        $allowed = ['b','i','strike','hr','br'];
        $s = strip_tags($s, '<' . implode('><', $allowed) . '>');
        $s = preg_replace('/<(\w+)[^>]*>/', '<$1>', $s);
        $s = str_replace('<br>', '<br />', $s);
        // 1) 엔터티화 (모든 문자 안전하게)
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // 2) 개행 → &lt;br /&gt; 변환 (render 시 br로 복원)
        return nl2br($s);
    }

    /** 단순 텍스트 (이전 호환). 개행→br + escape */
    public static function purify_text(string $s): string {
        $s = htmlspecialchars(trim($s), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        return nl2br($s);
    }

    /**
     * DB 저장 텍스트를 화면 출력용으로 복원.
     * purify_rich가 이미 htmlspecialchars + nl2br를 적용했으므로
     * 여기서는 entity 디코딩만 수행 (중복 br 방지).
     */
    public static function render_text(string $s): string {
        $map = [
            '&lt;br /&gt;' => '<br />',
            '&lt;br&gt;' => '<br />',
            '&lt;b&gt;' => '<b>', '&lt;/b&gt;' => '</b>',
            '&lt;i&gt;' => '<i>', '&lt;/i&gt;' => '</i>',
            '&lt;strike&gt;' => '<strike>', '&lt;/strike&gt;' => '</strike>',
            '&lt;hr&gt;' => '<hr />', '&lt;hr /&gt;' => '<hr />',
        ];
        foreach ($map as $esc => $html) {
            $s = str_replace($esc, $html, $s);
        }
        return $s;
    }

    /**
     * textarea용: 저장된 entity를 디코딩 + <br/>을 \n으로 변환 (저장-편집-재저장 반복 시 br 누적 방지).
     */
    public static function for_textarea(string $s): string {
        $s = htmlspecialchars_decode($s, ENT_QUOTES | ENT_HTML5);
        $s = str_replace(['<br />', '<br/>', '<br>'], "\n", $s);
        return $s;
    }

    /**
     * TOTP 검증 (RFC 6238, 자체 구현 - 외부 의존성 회피, HOTP기반)
     * @param string $secret  base32
     * @param string $code   6자리
     */
    public static function totp_verify(string $secret, string $code): bool {
        if (strlen($code) !== 6 || !preg_match('/^[0-9]{6}$/', $code)) return false;
        $key = self::base32_decode($secret);
        if ($key === '') return false;
        $t = floor(time() / 30);
        // ±1 윈도우 허용
        for ($off = -1; $off <= 1; $off++) {
            $tc = $t + $off;
            $bin = pack('N', 0) . pack('N', $tc);
            $hash = hash_hmac('sha1', $bin, $key, true);
            $offv = ord($hash[19]) & 0xf;
            $bin = (ord($hash[$offv]) & 0x7f) << 24
                 | (ord($hash[$offv + 1]) & 0xff) << 16
                 | (ord($hash[$offv + 2]) & 0xff) << 8
                 | (ord($hash[$offv + 3]) & 0xff);
            $otp = $bin % 1000000;
            if (hash_equals(str_pad((string)$otp, 6, '0', STR_PAD_LEFT), $code)) return true;
        }
        return false;
    }

    public static function base32_decode(string $b32): string {
        $map = array_flip(array_merge(range('A', 'Z'), range(2, 7)));
        $b32 = strtoupper(rtrim($b32, '='));
        $buf = 0; $bits = 0; $out = '';
        for ($i = 0; $i < strlen($b32); $i++) {
            $c = $b32[$i];
            if (!isset($map[$c])) return '';
            $buf = ($buf << 5) | $map[$c];
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($buf >> $bits) & 0xff);
            }
        }
        return $out;
    }

    /** 새 TOTP base32 시크릿 생성 (20 bytes) */
    public static function totp_secret(): string {
        $bin = random_bytes(20);
        $alphabet = array_merge(range('A', 'Z'), range(2, 7));
        $bits = ''; $out = '';
        for ($i = 0; $i < strlen($bin); $i++) {
            $bits .= str_pad(decbin(ord($bin[$i])), 8, '0', STR_PAD_LEFT);
        }
        for ($i = 0; $i < strlen($bits); $i += 5) {
            $chunk = substr($bits, $i, 5);
            if (strlen($chunk) < 5) break;
            $out .= $alphabet[bindec($chunk)];
        }
        return $out;
    }
}