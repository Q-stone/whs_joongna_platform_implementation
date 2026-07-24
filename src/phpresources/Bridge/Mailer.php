<?php
declare(strict_types=1);
namespace Bridge;

use function e;

class Mailer {
    public static function send(string $to, string $subject, string $body, ?int $uid = null): bool {
        $headers = "MIME-Version: 1.0\r\nContent-Type: text/html; charset=UTF-8\r\nFrom: =?UTF-8?B?64KY66y07J6l7YSw?= <no-reply@joongna.local>\r\n";
        $html = '<div style="font-family:sans-serif;max-width:480px;margin:0 auto;padding:20px"><h2 style="color:#2563eb">나무장터</h2><p>' . nl2br(e($body)) . '</p><hr><p style="color:#999;font-size:12px">본 메일은 발신 전용입니다.</p></div>';
        return @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers) !== false;
    }
}