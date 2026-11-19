<?php
declare(strict_types=1);

final class Auth
{
    public static function user(): ?array
    {
        if (empty($_SESSION['user_id'])) return null;
        static $user = false;
        if ($user === false) {
            $stmt = Database::connection()->prepare('SELECT user_id, full_name, email, phone, role, created_at FROM users WHERE user_id = ?');
            $stmt->execute([(int) $_SESSION['user_id']]);
            $user = $stmt->fetch() ?: null;
        }
        return $user ?: null;
    }

    public static function attempt(string $email, string $password): bool
    {
        $db = Database::connection();
        $ipHash = hash('sha256', Security::clientIp());
        $db->prepare('DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 1 DAY)')->execute();
        $check = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE email = ? AND ip_hash = ? AND success = 0 AND attempted_at > DATE_SUB(NOW(), INTERVAL 15 MINUTE)');
        $check->execute([$email, $ipHash]);
        if ((int) $check->fetchColumn() >= 5) {
            throw new RuntimeException('Quá nhiều lần đăng nhập thất bại. Vui lòng thử lại sau 15 phút.');
        }
        $stmt = $db->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        $success = $user && password_verify($password, $user['password_hash']);
        $log = $db->prepare('INSERT INTO login_attempts(email, ip_hash, success, attempted_at) VALUES (?, ?, ?, NOW())');
        $log->execute([$email, $ipHash, $success ? 1 : 0]);
        if (!$success) return false;
        if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
            $db->prepare('UPDATE users SET password_hash = ? WHERE user_id = ?')->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);
        }
        session_regenerate_id(true);
        $_SESSION['user_id'] = (int) $user['user_id'];
        $_SESSION['initiated_at'] = time();
        return true;
    }

    public static function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', (bool) $p['secure'], (bool) $p['httponly']);
        }
        session_destroy();
    }

    public static function requireLogin(): array
    {
        $user = self::user();
        if (!$user) {
            flash('warning', 'Vui lòng đăng nhập để tiếp tục.');
            redirect('?page=login');
        }
        return $user;
    }

    public static function requireRole(string ...$roles): array
    {
        $user = self::requireLogin();
        if (!in_array($user['role'], $roles, true)) {
            http_response_code(403);
            render_error(403, 'Bạn không có quyền thực hiện thao tác này.');
            exit;
        }
        return $user;
    }
}

