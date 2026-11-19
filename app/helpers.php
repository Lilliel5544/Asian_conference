<?php
declare(strict_types=1);

function e(mixed $value): string { return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function route(string $page, array $params = []): string { return '?' . http_build_query(['page' => $page] + $params); }
function redirect(string $url): never { header('Location: ' . $url, true, 303); exit; }
function is_post(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function csrf_field(): string { return '<input type="hidden" name="csrf_token" value="' . e(Security::csrfToken()) . '">'; }
function flash(string $type, string $message): void { $_SESSION['flash'][] = compact('type', 'message'); }
function flashes(): array { $items = $_SESSION['flash'] ?? []; unset($_SESSION['flash']); return $items; }
function old(string $key, string $default = ''): string { return e($_SESSION['old_input'][$key] ?? $_POST[$key] ?? $default); }
function remember_old_input(array $input): void {
    unset($input['password'], $input['password_confirmation'], $input['csrf_token']);
    $_SESSION['old_input'] = $input;
}
function clear_old_input(): void { unset($_SESSION['old_input']); }
function int_input(string $key): int { return filter_input(INPUT_POST, $key, FILTER_VALIDATE_INT) ?: 0; }
function date_vi(?string $value, bool $time = true): string { if (!$value) return '—'; $d = new DateTime($value); return $d->format($time ? 'd/m/Y H:i' : 'd/m/Y'); }
function status_badge(string $status): string {
    $map = ['upcoming'=>'primary','open'=>'success','closed'=>'secondary','pending'=>'warning','approved'=>'success','cancelled'=>'danger','submitted'=>'info','under_review'=>'warning','accepted'=>'success','rejected'=>'danger','withdrawn'=>'secondary','completed'=>'success'];
    $labels = [
        'draft' => 'Bản nháp', 'upcoming' => 'Sắp diễn ra', 'open' => 'Đang mở',
        'closed' => 'Đã đóng', 'completed' => 'Đã hoàn tất', 'pending' => 'Chờ duyệt',
        'approved' => 'Đã duyệt', 'cancelled' => 'Đã hủy', 'submitted' => 'Đã nộp',
        'under_review' => 'Đang phản biện', 'accepted' => 'Đã chấp nhận', 'rejected' => 'Đã từ chối',
        'withdrawn' => 'Đã thu hồi',
    ];
    return '<span class="badge text-bg-' . ($map[$status] ?? 'secondary') . '">' . e($labels[$status] ?? $status) . '</span>';
}
function recommendation_label(string $recommendation): string {
    return [
        'accept' => 'Chấp nhận', 'minor_revision' => 'Sửa nhỏ',
        'major_revision' => 'Sửa lớn', 'reject' => 'Từ chối',
    ][$recommendation] ?? $recommendation;
}
function json_response(array $payload, int $status = 200): never { http_response_code($status); header('Content-Type: application/json; charset=utf-8'); echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); exit; }

