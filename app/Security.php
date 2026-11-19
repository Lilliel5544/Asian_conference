<?php
declare(strict_types=1);

final class Security
{
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        session_name('conference_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => $https,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        session_start();
        if (!isset($_SESSION['initiated_at'])) {
            session_regenerate_id(true);
            $_SESSION['initiated_at'] = time();
        }
        if (time() - (int) ($_SESSION['initiated_at'] ?? 0) > 1800) {
            session_regenerate_id(true);
            $_SESSION['initiated_at'] = time();
        }
    }

    public static function sendHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; font-src 'self'; img-src 'self' data:; connect-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
    }

    public static function csrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return (string) $_SESSION['csrf_token'];
    }

    public static function verifyCsrf(?string $token): void
    {
        if (!$token || !hash_equals(self::csrfToken(), $token)) {
            throw new RuntimeException('Phiên làm việc không hợp lệ. Vui lòng tải lại trang.');
        }
    }

    public static function clientIp(): string
    {
        return substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
    }

    public static function validatePdf(array $file): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Không thể tải tệp lên.');
        }
        $max = max(1, (int) (getenv('MAX_UPLOAD_MB') ?: 10)) * 1024 * 1024;
        if (($file['size'] ?? 0) < 1 || $file['size'] > $max) {
            throw new RuntimeException('Tệp PDF vượt quá dung lượng cho phép.');
        }
        $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
        $extension = strtolower(pathinfo((string) $file['name'], PATHINFO_EXTENSION));
        if ($mime !== 'application/pdf' || $extension !== 'pdf') {
            throw new RuntimeException('Chỉ chấp nhận tệp PDF hợp lệ.');
        }
        $handle = fopen($file['tmp_name'], 'rb');
        $magic = $handle ? fread($handle, 5) : '';
        if ($handle) fclose($handle);
        if ($magic !== '%PDF-') {
            throw new RuntimeException('Nội dung tệp không phải PDF.');
        }
        return [$file['tmp_name'], bin2hex(random_bytes(24)) . '.pdf'];
    }
}

