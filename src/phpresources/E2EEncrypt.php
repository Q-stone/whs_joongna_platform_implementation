<?php
/**
 * E2EEncrypt.php - ECDH 키교환 + AES-GCM 양방향 암호화
 * 
 * 핸드셰이크: X25519 (Curve25519) via sodium / Web Crypto
 *   서버: 키페어 생성 → 공개키(32B hex)를 meta로 전달
 *   클라이언트: Web Crypto X25519 → 서버 공개키로 공유비밀 도출 → AES-256 키
 *              → 자신의 공개키를 _dh_pub 파라미터로 첫 API 요청에 포함
 *   서버: 클라이언트 공개키로 sodium_crypto_scalarmult → 동일 공유비밀 → AES 키
 *   공유비밀(32B)을 AES-256-GCM 키로 직접 사용 (HKDF 없음)
 */
if (!defined('main_index_files')) { http_response_code(503); echo '503'; exit; }

class E2EEncrypt {
    /** 서버 X25519 키페어 생성. 공개키 hex 반환. */
    public static function init(): string {
        if (empty($_SESSION['e2e_priv'])) {
            $kp = sodium_crypto_kx_keypair();
            $_SESSION['e2e_priv'] = bin2hex(sodium_crypto_kx_secretkey($kp));
        }
        if (!empty($_SESSION['e2e_priv']) && empty($_SESSION['e2e_pub'])) {
            $sk = hex2bin($_SESSION['e2e_priv']);
            $_SESSION['e2e_pub'] = bin2hex(sodium_crypto_scalarmult_base($sk));
        }
        return $_SESSION['e2e_pub'] ?? '';
    }

    /** 클라이언트 공개키로 공유비밀 도출 → AES-256키 */
    public static function deriveKey(string $clientPubHex): string {
        $skHex = $_SESSION['e2e_priv'] ?? '';
        if ($skHex === '' || $clientPubHex === '') { error_log('[E2E] deriveKey: missing skHex or clientPub'); return ''; }
        $sk = hex2bin($skHex);
        $cp = hex2bin($clientPubHex);
        if ($sk === false || $cp === false) { error_log('[E2E] deriveKey: hex2bin failed'); return ''; }
        $shared = sodium_crypto_scalarmult($sk, $cp);
        if ($shared === false || strlen($shared) !== 32) { error_log('[E2E] deriveKey: scalar_mult failed'); return ''; }
        // 공유비밀을 AES-256 키로 직접 사용 (32B)
        $_SESSION['e2ekey'] = bin2hex($shared);
        error_log('[E2E] deriveKey OK, shared=' . substr($_SESSION['e2ekey'], 0, 16) . '...');
        return $_SESSION['e2ekey'];
    }

    /** AES-256-GCM 복호화 */
    public static function decrypt(string $data, string $iv, string $tag): string {
        $k = hex2bin($_SESSION['e2ekey'] ?? '');
        if ($k === false || $data === '' || $iv === '' || $tag === '') { error_log('[E2E] decrypt: bad params'); return ''; }
        $d = hex2bin($data); $i = hex2bin($iv); $t = hex2bin($tag);
        if ($d === false || $i === false || $t === false) { error_log('[E2E] decrypt: hex2bin failed'); return ''; }
        $r = openssl_decrypt($d, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $i, $t);
        if ($r === false) { error_log('[E2E] decrypt GCM FAIL (key mismatch?)'); return ''; }
        error_log('[E2E] decrypt OK, len='.strlen($r));
        return $r;
    }

    /** AES-256-GCM 응답 암호화 */
    public static function encrypt(string $plain): string {
        $k = hex2bin($_SESSION['e2ekey'] ?? '');
        if ($k === false) return $plain;
        $iv = random_bytes(12); $tag = '';
        $c = openssl_encrypt($plain, 'aes-256-gcm', $k, OPENSSL_RAW_DATA, $iv, $tag);
        if ($c === false) return $plain;
        return json_encode(['_enc_resp'=>bin2hex($c),'_enc_iv'=>bin2hex($iv),'_enc_tag'=>bin2hex((string)$tag)],JSON_UNESCAPED_UNICODE);
    }
}