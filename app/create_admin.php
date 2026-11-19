<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require __DIR__ . '/bootstrap.php';

echo "Tạo tài khoản quản trị VietComNet\n";
echo "Họ và tên: ";
$name = trim((string) fgets(STDIN));
echo "Email: ";
$email = strtolower(trim((string) fgets(STDIN)));
echo "Mật khẩu (ít nhất 12 ký tự, gồm hoa, thường, số): ";
$password = rtrim((string) fgets(STDIN), "\r\n");

if (mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Họ tên hoặc email không hợp lệ.\n");
    exit(1);
}
if (strlen($password) < 12 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) {
    fwrite(STDERR, "Mật khẩu chưa đáp ứng yêu cầu.\n");
    exit(1);
}

$db = Database::connection();
$stmt = $db->prepare("INSERT INTO users(full_name,email,password_hash,role,created_at) VALUES(?,?,?,'admin',NOW()) ON DUPLICATE KEY UPDATE full_name=VALUES(full_name), password_hash=VALUES(password_hash), role='admin'");
$stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
echo "Đã tạo/cập nhật quản trị viên: {$email}\n";

