<?php
/**
 * FileUpload.php - 미디어 업로드 보안 클래스 (상속 기반).
 * 사진: png/jpeg/jpg/gif/webp/heif, 장당 10MB
 * 영상: mp4/webm/mov, 20MB
 * 웹셸 원천 차단: PHP 확장자/위장 확장자 거름, Apache/Nginx에서 엔진 오프 + 파일명 난수화.
 *
 * (참고: 게시판 service_bridge.php 파일검증 로직 + resource_check chk_image_file)
 */
if (!defined('main_index_files')) {
    http_response_code(503); echo "503 : Direct Access is Denied!"; exit;
}

class FileUpload {
    /** 업로드 금지 확장자 (이중 dot, 웹셸 위장) */
    public static array $BLOCK_EXT = [
        'php', 'phps', 'php3', 'php4', 'php5', 'php7', 'php8', 'pht', 'phtml',
        'phar', 'htaccess', 'inc', 'xml', 'html', 'xhtml', 'htm', 'shtml', 'cgi',
    ];

    /** 단일 파일 검증 후 저장. [ok, save_name, err] 반환 */
    public static function save(array $file): array {
        if (is_array($file['name'] ?? null)) {
            return [false, '', '다중 파일 업로드는 허용되지 않습니다.'];
        }
        if (empty($file['name']) || !is_uploaded_file($file['tmp_name'])) {
            return [false, '', '업로드된 파일이 없습니다.'];
        }
        if ($file['error'] !== UPLOAD_ERR_OK) {
            return [false, '', '업로드 오류 코드: ' . (int)$file['error']];
        }

        $orig = $file['name'];
        // 파일명 내 금지문자
        if (strlen($orig) !== strcspn($orig, "\0\\/:;*?\"'<>|")) {
            return [false, '', '파일명에 허용되지 않는 문자가 포함되어 있습니다.'];
        }
        if (strlen($orig) > 120) {
            return [false, '', '파일명이 너무 깁니다.'];
        }

        $ext = ext_extract($orig);
        if ($ext === '') return [false, '', '확장자가 없는 파일입니다.'];

        // 웹셸 위험 확장자가 파일명 내 어느 위치에도 있으면 거절
        $parts = explode('.', strtolower($orig));
        foreach (self::$BLOCK_EXT as $bad) {
            if (in_array($bad, $parts, true)) {
                return [false, '', '이 확장자는 보안상 업로드할 수 없습니다: .' . $bad];
            }
        }

        // 사진 vs 영상 분기
        $is_img = in_array($ext, $GLOBALS['ALLOW_IMG_EXT'], true);
        $is_vid = in_array($ext, $GLOBALS['ALLOW_VID_EXT'], true);
        if (!$is_img && !$is_vid) {
            return [false, '', '허용되지 않는 확장자입니다. (사진: png/jpeg/jpg/gif/webp/heif, 영상: mp4/webm/mov)'];
        }

        // 사이즈
        $size = filesize($file['tmp_name']);
        if ($is_img && $size > MAX_IMG_SIZE) return [false, '', '사진은 장당 10MB 이하만 가능합니다.'];
        if ($is_vid && $size > MAX_VID_SIZE) return [false, '', '영상은 20MB 이하만 가능합니다.'];
        if ($size <= 0) return [false, '', '빈 파일입니다.'];

        // 실제 파일 시그니처 검증
        if ($is_img) {
            $chk = chk_image_file($file['tmp_name']);
            if ($chk !== 'true') return [false, '', '유효하지 않은 이미지: ' . $chk];
        } else {
            if ($ext === 'heif') {
                // heif는 GD 미지원 -> finfo mime 만
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $m = $finfo->file($file['tmp_name']);
                if (strpos($m, 'image/') !== 0) return [false, '', 'heif 이미지가 아닙니다.'];
            } else {
                $chk = chk_video_file($file['tmp_name']);
                if ($chk !== 'true') return [false, '', '유효하지 않은 영상: ' . $chk];
            }
        }

        // 저장 (난수화 파일명 - 웹셸 경로 추정 차단)
        list($ip) = get_user_ip();
        $rand = hash('sha256', bin2hex(random_bytes(8)) . $ip . (string)microtime(true));
        $kind = $is_img ? 'img' : 'vid';
        $dir = UPLOAD_DIR . $kind . '/';
        if (!is_dir($dir)) {
            mkdir($dir, 0770, true); // Apache 컨테이너가 쓰도록
        }
        $save = substr($rand, 0, 40) . '.' . $ext;
        $dest = $dir . $save;
        if (!move_uploaded_file($file['tmp_name'], $dest)) {
            return [false, '', '파일 저장 실패.'];
        }

        return [true, "img/" . $save, ''];
    }
}