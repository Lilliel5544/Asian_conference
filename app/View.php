<?php
declare(strict_types=1);

function conference_brand_context(): array
{
    $context = ['year' => date('Y'), 'location' => 'Hà Nội, Việt Nam', 'date_label' => ''];
    try {
        $row = Database::connection()->query("SELECT start_date, end_date, location FROM conferences WHERE status<>'draft' ORDER BY start_date DESC LIMIT 1")->fetch();
        if ($row) {
            $start = new DateTimeImmutable($row['start_date']);
            $end = new DateTimeImmutable($row['end_date']);
            $context = ['year' => $start->format('Y'), 'location' => $row['location'], 'date_label' => $start->format('d/m/Y') . ' – ' . $end->format('d/m/Y')];
        }
    } catch (PDOException $exception) {
        // Giữ giá trị mặc định khi chưa kết nối được database.
    }
    return $context;
}

function member_nav(string $active): void
{
    $items = ['dashboard' => 'Tổng quan', 'profile' => 'Hồ sơ cá nhân', 'papers' => 'Bài báo của tôi'];
    echo '<nav class="list-group dashboard-nav" aria-label="Khu vực thành viên">';
    foreach ($items as $page => $label) {
        $class = $active === $page ? ' active' : '';
        echo '<a class="list-group-item list-group-item-action' . $class . '" href="' . e(route($page)) . '">' . e($label) . '</a>';
    }
    if ((Auth::user()['role'] ?? '') === 'admin') {
        echo '<a class="list-group-item list-group-item-action" href="' . e(route('admin')) . '">Quản trị hệ thống</a>';
    }
    echo '</nav>';
}

function render_header(string $title, string $active = ''): void
{
    $user = Auth::user();
    $brand = conference_brand_context();
    $fullTitle = e($title) . ' | VietComNet ' . e($brand['year']);
    $nav = [
        'home' => 'Trang chủ', 'conferences' => 'Hội thảo', 'committee' => 'Ban tổ chức',
        'schedule' => 'Lịch trình', 'papers' => 'Bài báo', 'contact' => 'Liên hệ',
    ];
    ?>
<!doctype html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="<?= e(Security::csrfToken()) ?>">
    <title><?= $fullTitle ?></title>
    <meta name="description" content="Hệ thống quản lý hội thảo khoa học VietComNet <?= e($brand['year']) ?>">
    <link href="assets/vendor/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<nav class="navbar navbar-expand-xl navbar-dark conference-nav sticky-top" aria-label="Điều hướng chính">
    <div class="container-fluid px-lg-5">
        <a class="navbar-brand d-xl-none" href="<?= route('home') ?>">VIETCOMNET</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav" aria-controls="mainNav" aria-expanded="false" aria-label="Mở điều hướng"><span class="navbar-toggler-icon"></span></button>
        <div class="collapse navbar-collapse" id="mainNav">
            <ul class="navbar-nav me-auto mb-2 mb-xl-0">
                <?php foreach ($nav as $key => $label): ?>
                    <li class="nav-item"><a class="nav-link <?= $active === $key ? 'active' : '' ?>" href="<?= route($key) ?>"><?= e($label) ?></a></li>
                <?php endforeach; ?>
            </ul>
            <div class="d-flex align-items-xl-center gap-2 flex-column flex-xl-row auth-nav">
                <?php if ($user): ?>
                    <a class="nav-link text-white" href="<?= route('dashboard') ?>">Xin chào, <?= e($user['full_name']) ?></a>
                    <?php if ($user['role'] === 'admin'): ?><a class="btn btn-sm btn-light" href="<?= route('admin') ?>">Quản trị</a><?php endif; ?>
                    <form method="post" action="<?= route('logout') ?>" class="m-0"><?= csrf_field() ?><button class="btn btn-sm btn-outline-light">Đăng xuất</button></form>
                <?php else: ?>
                    <a class="nav-link text-white" href="<?= route('login') ?>">Đăng nhập</a>
                    <a class="btn btn-sm btn-light" href="<?= route('register') ?>">Đăng ký</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</nav>
<?php if ($active === 'home'): ?>
<header class="conference-hero">
    <div class="container hero-inner">
        <div class="row align-items-center g-4">
            <div class="col-lg-7">
                <div class="brand-lockup"><img src="assets/logo.svg" alt="VietComNet" width="280" height="78"></div>
                <h1><?= e($brand['year']) ?> Vietnam Conference on <br>Communication and Networks</h1>
                <p class="hybrid-label">Hybrid · Trực tuyến và trực tiếp</p>
                <p class="hero-meta"><?= e($brand['location']) ?><?php if ($brand['date_label'] !== ''): ?> <span><?= e($brand['date_label']) ?></span><?php endif; ?></p>
                <p class="record-label">Diễn đàn kết nối nghiên cứu và đổi mới công nghệ</p>
            </div>
            <div class="col-lg-5 d-none d-lg-block" aria-hidden="true">
                <div class="skyline">
                    <span class="tower tower-a"></span><span class="tower tower-b"></span><span class="tower tower-c"></span><span class="tower tower-d"></span>
                    <span class="bridge"></span><span class="sun"></span>
                </div>
            </div>
        </div>
    </div>
</header>
<?php endif; ?>
<main>
    <?php foreach (flashes() as $item): ?>
        <div class="container mt-3"><div class="alert alert-<?= e($item['type']) ?> alert-dismissible fade show" role="alert"><?= e($item['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Đóng"></button></div></div>
    <?php endforeach; ?>
<?php
}

function page_title(string $title, ?string $eyebrow = null): void
{
    $eyebrow ??= 'VIETCOMNET ' . conference_brand_context()['year'];
    ?>
    <section class="page-title-wrap">
        <div class="container">
            <div class="page-title-icon" aria-hidden="true">“</div>
            <div><p class="eyebrow mb-1"><?= e($eyebrow) ?></p><h1 class="mb-0"><?= e($title) ?></h1></div>
        </div>
    </section>
    <?php
}

function render_footer(): void
{
    clear_old_input();
    $year = conference_brand_context()['year'];
    ?>
</main>
<footer class="site-footer mt-5">
    <div class="container py-5">
        <div class="row g-4 align-items-start">
            <div class="col-lg-7">
                <img src="assets/logo.svg" class="footer-logo" alt="VietComNet" width="230" height="64">
                <p class="mt-3 mb-0">Hệ thống quản lý hội thảo khoa học dành cho nhà nghiên cứu, phản biện và ban tổ chức.</p>
            </div>
            <div class="col-lg-5">
                <h2 class="h5">LIÊN HỆ</h2>
                <p class="mb-1">Email: <a href="mailto:contact@vietcomnet.test">contact@vietcomnet.test</a></p>
                <p class="mb-0">Hỗ trợ kỹ thuật: <a href="mailto:support@vietcomnet.test">support@vietcomnet.test</a></p>
            </div>
        </div>
    </div>
    <div class="footer-bottom"><div class="container">© <?= e($year) ?> VietComNet Conference. Hệ thống quản lý hội thảo khoa học.</div></div>
</footer>
<div class="toast-container position-fixed bottom-0 end-0 p-3"><div id="liveToast" class="toast" role="status" aria-live="polite"><div class="toast-header"><strong class="me-auto">VietComNet</strong><button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Đóng"></button></div><div class="toast-body"></div></div></div>
<script src="assets/vendor/bootstrap.bundle.min.js"></script>
<script src="assets/app.js"></script>
</body>
</html>
    <?php
}

function render_error(int $code, string $message): void
{
    render_header("Lỗi {$code}");
    page_title("Lỗi {$code}", 'THÔNG BÁO');
    echo '<div class="container content-section"><div class="alert alert-danger">' . e($message) . '</div><a class="btn btn-primary" href="' . route('home') . '">Về trang chủ</a></div>';
    render_footer();
}

