<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

if (($_GET['page'] ?? '') === 'admin_workflow' && !is_post()) {
    redirect(route('admin') . '#admin-workflow');
}

if (($_GET['page'] ?? '') === 'admin'
    && is_post()
    && ($_POST['_admin_workflow'] ?? null) === '1') {

    require_once __DIR__ . '/app/admin/AdminWorkflowController.php';
    AdminWorkflowController::handlePost();
}

$page = preg_replace('/[^a-z_]/', '', (string) ($_GET['page'] ?? 'home')) ?: 'home';

try {
    if (is_post()) {
        handle_post($page);
    }
    render_page($page);
} catch (PDOException $exception) {
    http_response_code(500);
    $development = (getenv('APP_ENV') ?: 'production') === 'development';
    ?><!doctype html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Chưa kết nối cơ sở dữ liệu</title><link href="assets/vendor/bootstrap.min.css" rel="stylesheet"></head><body class="bg-light"><main class="container py-5"><div class="card shadow-sm mx-auto" style="max-width:720px"><div class="card-body p-5"><h1 class="h3">Chưa kết nối cơ sở dữ liệu</h1><p>Hãy tạo database, chạy <code>database/schema.sql</code> rồi <code>database/seed.sql</code>, sau đó sao chép <code>.env.example</code> thành <code>.env</code> và điền thông tin MySQL.</p><?php if ($development): ?><pre class="bg-dark text-white p-3 rounded small"><?= e($exception->getMessage()) ?></pre><?php endif; ?><a href="README.md" class="btn btn-primary">Xem hướng dẫn cài đặt</a></div></div></main></body></html><?php
} catch (DomainException | RuntimeException $exception) {
    if (is_post()) {
        if ($page === 'ajax') {
            json_response(['ok' => false, 'message' => $exception->getMessage()], 403);
        }
        remember_old_input($_POST);
        flash('danger', $exception->getMessage());
        $target = match ($page) {
            'review_submit' => 'dashboard',
            'logout' => 'home',
            'login', 'register', 'profile', 'papers' => $page,
            default => 'home',
        };
        redirect(route($target));
    }
    $status = http_response_code();
    if ($status < 400) {
        $status = 400;
    }
    render_error($status, $exception->getMessage());
} catch (Throwable $exception) {
    http_response_code(500);
    render_error(500, (getenv('APP_ENV') === 'development') ? $exception->getMessage() : 'Đã xảy ra lỗi. Vui lòng thử lại.');
}

function handle_post(string $page): void
{
    Security::verifyCsrf($_POST['csrf_token'] ?? null);
    $db = Database::connection();
    if ($page === 'ajax') handle_ajax();

    if ($page === 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen((string) ($_POST['password'] ?? '')) < 8) {
            throw new RuntimeException('Email hoặc mật khẩu không hợp lệ.');
        }
        if (!Auth::attempt($email, (string) $_POST['password'])) {
            remember_old_input(['email' => $email]);
            flash('danger', 'Email hoặc mật khẩu không chính xác.');
            redirect(route('login'));
        }
        flash('success', 'Đăng nhập thành công.');
        redirect(route('dashboard'));
    }
    if ($page === 'logout') {
        Auth::logout();
        redirect(route('home'));
    }
    if ($page === 'register') {
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) throw new RuntimeException('Họ tên phải từ 2 đến 100 ký tự.');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100) throw new RuntimeException('Email không hợp lệ.');
        if ($phone !== '' && !preg_match('/^[0-9+ .()-]{8,20}$/', $phone)) throw new RuntimeException('Số điện thoại không hợp lệ.');
        if (strlen($password) < 10 || !preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/\d/', $password)) throw new RuntimeException('Mật khẩu cần ít nhất 10 ký tự, gồm chữ hoa, chữ thường và số.');
        if (!hash_equals($password, (string) ($_POST['password_confirmation'] ?? ''))) throw new RuntimeException('Xác nhận mật khẩu không khớp.');
        $stmt = $db->prepare('INSERT INTO users(full_name,email,password_hash,phone,role,created_at) VALUES(?,?,?,?,\'user\',NOW())');
        try { $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone ?: null]); }
        catch (PDOException $e) { if (($e->errorInfo[1] ?? 0) === 1062) throw new RuntimeException('Email đã được sử dụng.'); throw $e; }
        Auth::attempt($email, $password);
        flash('success', 'Tài khoản đã được tạo an toàn.');
        redirect(route('dashboard'));
    }
    if ($page === 'profile') {
        $user = Auth::requireLogin();
        $name = trim((string) ($_POST['full_name'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        if (mb_strlen($name) < 2 || mb_strlen($name) > 100) throw new RuntimeException('Họ tên không hợp lệ.');
        if ($phone !== '' && !preg_match('/^[0-9+ .()-]{8,20}$/', $phone)) throw new RuntimeException('Số điện thoại không hợp lệ.');
        $db->prepare('UPDATE users SET full_name=?, phone=? WHERE user_id=?')->execute([$name, $phone ?: null, $user['user_id']]);
        flash('success', 'Đã cập nhật hồ sơ.');
        redirect(route('profile'));
    }
    if ($page === 'papers') {
        $user = Auth::requireLogin();
        $paperAction = (string) ($_POST['paper_action'] ?? 'submit');

        if ($paperAction === 'withdraw') {
            $paperId = int_input('paper_id');
            $stmt = $db->prepare("UPDATE papers SET status='withdrawn' WHERE paper_id=? AND author_id=? AND status='submitted'");
            $stmt->execute([$paperId, $user['user_id']]);
            if (!$stmt->rowCount()) {
                throw new RuntimeException('Chỉ có thể thu hồi bài đang ở trạng thái đã nộp.');
            }
            flash('success', 'Bài báo đã được thu hồi khỏi quy trình xét duyệt.');
            redirect(route('papers'));
        }

        if ($paperAction === 'replace') {
            $paperId = int_input('paper_id');
            $stmt = $db->prepare("SELECT file_path FROM papers WHERE paper_id=? AND author_id=? AND status='submitted'");
            $stmt->execute([$paperId, $user['user_id']]);
            $oldFile = $stmt->fetchColumn();
            if (!$oldFile) {
                throw new RuntimeException('Chỉ có thể thay PDF khi bài chưa được đưa vào phản biện.');
            }
            [$temporary, $fileName] = Security::validatePdf($_FILES['paper_file'] ?? []);
            $destination = ROOT_PATH . '/storage/uploads/' . $fileName;
            if (!move_uploaded_file($temporary, $destination)) {
                throw new RuntimeException('Không thể lưu tệp PDF thay thế.');
            }
            try {
                $db->beginTransaction();
                $update = $db->prepare("UPDATE papers SET file_path=?, submitted_at=NOW() WHERE paper_id=? AND author_id=? AND status='submitted'");
                $update->execute([$fileName, $paperId, $user['user_id']]);
                if (!$update->rowCount()) {
                    throw new RuntimeException('Bài báo đã thay đổi trạng thái; vui lòng tải lại trang.');
                }
                $db->commit();
            } catch (Throwable $exception) {
                if ($db->inTransaction()) $db->rollBack();
                if (is_file($destination)) unlink($destination);
                throw $exception;
            }
            $oldPath = ROOT_PATH . '/storage/uploads/' . basename((string) $oldFile);
            if (is_file($oldPath)) unlink($oldPath);
            flash('success', 'Đã thay thế PDF và cập nhật thời gian nộp bài.');
            redirect(route('papers'));
        }

        if ($paperAction !== 'submit') {
            throw new RuntimeException('Thao tác bài báo không hợp lệ.');
        }
        $conferenceId = int_input('conference_id');
        $title = trim((string) ($_POST['title'] ?? ''));
        $abstract = trim((string) ($_POST['abstract'] ?? ''));
        $topic = trim((string) ($_POST['topic'] ?? ''));
        if ($conferenceId < 1 || mb_strlen($title) < 5 || mb_strlen($title) > 255 || mb_strlen($abstract) < 50 || mb_strlen($topic) < 2 || mb_strlen($topic) > 100) throw new RuntimeException('Vui lòng kiểm tra tiêu đề, tóm tắt, chủ đề và hội thảo.');
        $check = $db->prepare("SELECT COUNT(*) FROM conferences WHERE conference_id=? AND submission_deadline >= NOW() AND status IN ('upcoming','open')"); $check->execute([$conferenceId]);
        if (!(int) $check->fetchColumn()) throw new RuntimeException('Hội thảo không mở nhận bài hoặc đã hết hạn nộp bài.');
        [$temporary, $fileName] = Security::validatePdf($_FILES['paper_file'] ?? []);
        $destination = ROOT_PATH . '/storage/uploads/' . $fileName;
        if (!move_uploaded_file($temporary, $destination)) throw new RuntimeException('Không thể lưu tệp bài báo.');
        try {
            $db->prepare('INSERT INTO papers(title,abstract,file_path,topic,author_id,conference_id,session_id,submitted_at,status) VALUES(?,?,?,?,?,?,NULL,NOW(),\'submitted\')')->execute([$title, $abstract, $fileName, $topic, $user['user_id'], $conferenceId]);
        } catch (Throwable $e) { if (is_file($destination)) unlink($destination); throw $e; }
        flash('success', 'Bài báo đã được nộp và lưu bằng tên tệp ngẫu nhiên.');
        redirect(route('papers'));
    }
    if ($page === 'review_submit') {
        $user = Auth::requireRole('reviewer', 'admin');
        $reviewId = int_input('review_id'); $score = int_input('score');
        $comments = trim((string) ($_POST['comments'] ?? '')); $recommendation = (string) ($_POST['recommendation'] ?? '');
        if ($score < 1 || $score > 10 || mb_strlen($comments) < 20 || !in_array($recommendation, ['accept','minor_revision','major_revision','reject'], true)) throw new RuntimeException('Nội dung phản biện chưa hợp lệ.');
        
        $stmt = $db->prepare(
            'UPDATE reviews
            SET score=?,
                comments=?,
                recommendation=?,
                reviewed_at=NOW()
            WHERE review_id=?
            AND reviewer_id=?
            AND reviewed_at IS NULL'
        );

        $stmt->execute([
            $score,
            $comments,
            $recommendation,
            $reviewId,
            $user['user_id']
        ]);

        if (!$stmt->rowCount()) {
            throw new RuntimeException(
                'Bạn không được phân công hoặc bản phản biện đã hoàn thành.'
            );
        }

        flash('success','Đã lưu kết quả phản biện.'); redirect(route('dashboard'));
    }
    if ($page === 'admin_action') handle_admin_action();
    throw new RuntimeException('Yêu cầu không được hỗ trợ.');
}

function handle_ajax(): never
{
    if (strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '')) !== 'xmlhttprequest') json_response(['ok'=>false,'message'=>'Yêu cầu không hợp lệ.'],400);
    $user = Auth::user(); if (!$user) json_response(['ok'=>false,'message'=>'Vui lòng đăng nhập trước.'],401);
    if (($_GET['action'] ?? '') !== 'toggle-registration') json_response(['ok'=>false,'message'=>'Thao tác không tồn tại.'],404);
    $conferenceId = int_input('conference_id'); if ($conferenceId < 1) json_response(['ok'=>false,'message'=>'Hội thảo không hợp lệ.'],422);
    $db = Database::connection();
    $conference=$db->prepare("SELECT conference_id FROM conferences WHERE conference_id=? AND end_date>=NOW() AND status IN ('upcoming','open')"); $conference->execute([$conferenceId]);
    if(!$conference->fetch()) json_response(['ok'=>false,'message'=>'Hội thảo không mở đăng ký hoặc không tồn tại.'],404);
    $existing=$db->prepare('SELECT registration_id,status FROM registrations WHERE user_id=? AND conference_id=?'); $existing->execute([$user['user_id'],$conferenceId]); $row=$existing->fetch();
    if($row && $row['status']!=='cancelled'){
        $db->prepare('UPDATE registrations SET status=\'cancelled\' WHERE registration_id=?')->execute([$row['registration_id']]);
        json_response(['ok'=>true,'registered'=>false,'message'=>'Đã hủy đăng ký tham dự.']);
    }
    if($row){$db->prepare('UPDATE registrations SET status=\'pending\',registration_date=NOW() WHERE registration_id=?')->execute([$row['registration_id']]);}
    else{$db->prepare('INSERT INTO registrations(user_id,conference_id,registration_date,status) VALUES(?,?,NOW(),\'pending\')')->execute([$user['user_id'],$conferenceId]);}
    json_response(['ok'=>true,'registered'=>true,'message'=>'Đăng ký đã được gửi và đang chờ duyệt.']);
}


function handle_admin_action(): never
{
    Auth::requireRole('admin');

    flash(
        'warning',
        'Đường dẫn quản trị cũ đã ngừng sử dụng.'
    );

    redirect(route('admin'));
}


function render_page(string $page): void
{
    match($page){
        'home'=>view_home(), 'conferences'=>view_conferences(), 'conference'=>view_conference(), 'committee'=>view_committee(),
        'schedule'=>view_schedule(), 'contact'=>view_contact(), 'login'=>view_login(), 'register'=>view_register(),
        'dashboard'=>view_dashboard(), 'profile'=>view_profile(), 'papers'=>view_papers(), 'download'=>download_paper(),
        'admin'=>view_admin(), default=>not_found(),
    };
}

function conference_cards(array $items): void
{
    if(!$items){echo '<div class="empty-state">Chưa có hội thảo phù hợp.</div>';return;}
    echo '<div class="row g-4">'; foreach($items as $c){$date=new DateTime($c['start_date']);?>
    <div class="col-md-6 col-xl-4 conference-col"><article class="card conference-card" data-conference-card="<?= e($c['title'].' '.$c['location']) ?>"><div class="card-accent"></div><div class="card-body p-4"><div class="d-flex gap-3"><div class="date-block"><small>THÁNG</small><?= $date->format('m') ?><small><?= $date->format('Y') ?></small></div><div><div class="mb-2"><?= status_badge($c['status']) ?></div><h3 class="h5 fw-bold"><?= e($c['title']) ?></h3></div></div><p class="text-secondary mt-3 mb-2"><?= e(mb_strimwidth($c['description'],0,150,'…')) ?></p><p class="small mb-3">📍 <?= e($c['location']) ?></p><a class="btn btn-outline-primary" href="<?= route('conference',['id'=>$c['conference_id']]) ?>">Xem chi tiết</a></div></article></div>
    <?php } echo '</div>';
}

function view_home(): void
{
    $db=Database::connection(); $conferences=$db->query('SELECT * FROM conferences WHERE status<>\'draft\' ORDER BY start_date ASC LIMIT 6')->fetchAll();
    $stats=['Hội thảo'=>(int)$db->query('SELECT COUNT(*) FROM conferences')->fetchColumn(),'Bài báo'=>(int)$db->query('SELECT COUNT(*) FROM papers')->fetchColumn(),'Người tham dự'=>(int)$db->query("SELECT COUNT(*) FROM registrations WHERE status='approved'")->fetchColumn()];
    render_header('Trang chủ','home');?>
    <section class="container content-section"><div class="row g-4 mb-5"><?php foreach($stats as $label=>$value):?><div class="col-md-4"><div class="card stat-card"><div class="card-body"><div class="stat-number"><?= $value ?></div><div><?= e($label) ?></div></div></div></div><?php endforeach;?></div><div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><p class="eyebrow">KHÁM PHÁ VÀ THAM GIA</p><h2 class="h3 fw-bold mb-0">Hội thảo nổi bật</h2></div><a class="btn btn-primary" href="<?= route('conferences') ?>">Xem tất cả</a></div><?php conference_cards($conferences);?></section>
    <?php render_footer();
}

function view_conferences(): void
{
    $items=Database::connection()->query('SELECT * FROM conferences WHERE status<>\'draft\' ORDER BY start_date DESC')->fetchAll(); render_header('Danh sách hội thảo','conferences'); page_title('Danh sách hội thảo');?>
    <div class="container content-section"><div class="row mb-4"><div class="col-lg-6"><label for="conferenceSearch" class="form-label fw-semibold">Tìm kiếm hội thảo</label><input id="conferenceSearch" class="form-control form-control-lg" placeholder="Nhập tên hoặc địa điểm…" data-conference-search></div></div><?php conference_cards($items);?></div><?php render_footer();
}

function view_conference(): void
{
    $id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0; $db=Database::connection(); $stmt=$db->prepare('SELECT c.*,u.full_name creator FROM conferences c JOIN users u ON u.user_id=c.created_by WHERE c.conference_id=? AND c.status<>\'draft\'');$stmt->execute([$id]);$c=$stmt->fetch(); if(!$c){not_found();return;}
    $session=$db->prepare('SELECT * FROM sessions WHERE conference_id=? ORDER BY start_time');$session->execute([$id]);$sessions=$session->fetchAll(); $registered=false; if($u=Auth::user()){$x=$db->prepare("SELECT COUNT(*) FROM registrations WHERE user_id=? AND conference_id=? AND status<>'cancelled'");$x->execute([$u['user_id'],$id]);$registered=(bool)$x->fetchColumn();}
    render_header($c['title'],'conferences'); page_title($c['title'],'CHI TIẾT HỘI THẢO');?>
    <div class="container content-section"><div class="row g-5"><div class="col-lg-8"><div class="mb-3"><?= status_badge($c['status']) ?></div><p class="lead"><?= nl2br(e($c['description'])) ?></p><div class="row g-3 mt-3"><div class="col-sm-6"><div class="card"><div class="card-body"><strong>Thời gian</strong><br><?= date_vi($c['start_date']) ?> – <?= date_vi($c['end_date']) ?></div></div></div><div class="col-sm-6"><div class="card"><div class="card-body"><strong>Địa điểm</strong><br><?= e($c['location']) ?></div></div></div></div><h3 class="section-heading">Lịch trình</h3><?php if($sessions):foreach($sessions as $s):?><div class="schedule-item"><strong><?= e($s['session_name']) ?></strong><div><?= date_vi($s['start_time']) ?> – <?= date_vi($s['end_time']) ?></div><small>Phòng <?= e($s['room']) ?></small></div><?php endforeach;else:?><p>Chưa công bố lịch trình.</p><?php endif;?></div><aside class="col-lg-4"><div class="card"><div class="card-body p-4"><h3 class="h5 fw-bold">Tham gia hội thảo</h3><p>Hạn nộp bài: <strong><?= date_vi($c['submission_deadline']) ?></strong></p><?php if(Auth::user()):?><button class="btn <?= $registered?'btn-danger':'btn-primary' ?> w-100 mb-2" data-register-conference="<?= $id ?>" <?= $registered ? 'data-confirm-registration="Bạn có chắc muốn hủy đăng ký hội thảo này?"' : '' ?>><?= $registered?'Hủy đăng ký':'Đăng ký tham dự' ?></button><a class="btn btn-outline-primary w-100" href="<?= route('papers') ?>">Nộp bài báo</a><?php else:?><a class="btn btn-primary w-100" href="<?= route('login') ?>">Đăng nhập để tham gia</a><?php endif;?></div></div></aside></div></div><?php render_footer();
}

function view_committee(): void
{
    $groups=[
        'Ban bảo trợ'=>['PGS. TS. Nguyễn Minh Tuấn — Học viện Công nghệ Bưu chính Viễn thông','GS. Elena Rossi — University of Catania, Italy'],
        'Chủ tịch hội thảo'=>['GS. Trần Đức Minh — Đại học Bách khoa Hà Nội'],
        'Đồng chủ tịch'=>['PGS. TS. Lê Hải Châu — Học viện Công nghệ Bưu chính Viễn thông','Prof. N. Prabagarane — SSN College of Engineering, India'],
        'Chủ tịch chương trình'=>['TS. Nguyễn Trung Thành — Phenikaa University','Dr. Iacovos Ioannou — Neapolis University Paphos, Cyprus'],
        'Ban kỹ thuật chương trình'=>['TS. Phạm Anh Thư — PTIT','Dr. Kien Nguyen — Chiba University, Japan','TS. Đặng Minh Tuấn — CMC University','Dr. Chavis Srichan — Khon Kaen University, Thailand'],
        'Ban cố vấn quốc tế'=>['GS. Elena Rossi — University of Catania, Italy','Dr. Kien Nguyen — Chiba University, Japan','Dr. Iacovos Ioannou — Neapolis University Paphos, Cyprus'],
        'Ban truyền thông và xuất bản'=>['TS. Đặng Minh Tuấn — CMC University','TS. Phạm Anh Thư — PTIT'],
        'Thư ký hội thảo'=>['ThS. Trần Thị Hương Giang — PTIT'],
    ];
    render_header('Ban tổ chức','committee');page_title('Ban tổ chức');?>
    <div class="container content-section committee-page">
        <p class="committee-intro">Ban tổ chức VietComNet quy tụ các nhà khoa học và chuyên gia trong nước, quốc tế nhằm xây dựng một diễn đàn học thuật chuyên nghiệp, cởi mở và có giá trị kết nối lâu dài.</p>
        <div class="committee-list"><?php foreach($groups as $group=>$people):?><section class="committee-group"><h3><?= e($group) ?></h3><div class="committee-people"><?php foreach($people as $person):?><p><?= e($person) ?></p><?php endforeach;?></div></section><?php endforeach;?></div>
    </div><?php render_footer();
}

function view_schedule(): void
{
    $items=Database::connection()->query('SELECT s.*,c.title conference_title FROM sessions s JOIN conferences c ON c.conference_id=s.conference_id WHERE c.status<>\'draft\' ORDER BY s.start_time')->fetchAll(); render_header('Lịch trình','schedule');page_title('Lịch trình hội thảo');?>
    <div class="container content-section" style="max-width:960px"><?php if(!$items):?><div class="empty-state">Lịch trình đang được cập nhật.</div><?php else:foreach($items as $s):?><div class="schedule-item"><p class="eyebrow mb-1"><?= e($s['conference_title']) ?></p><h3 class="h5 fw-bold"><?= e($s['session_name']) ?></h3><p class="mb-1"><?= date_vi($s['start_time']) ?> – <?= date_vi($s['end_time']) ?></p><span class="badge text-bg-light">Phòng <?= e($s['room']) ?></span></div><?php endforeach;endif;?></div><?php render_footer();
}

function view_contact(): void
{
    $year = conference_brand_context()['year'];
    render_header('Liên hệ','contact');page_title('Liên hệ');?>
    <div class="container content-section"><div class="row g-4"><div class="col-lg-7"><h3 class="h4 fw-bold">Ban thư ký VietComNet <?= e($year) ?></h3><p>Học viện Công nghệ Bưu chính Viễn thông, Hà Nội, Việt Nam</p><p>Email: <a href="mailto:contact@vietcomnet.test">contact@vietcomnet.test</a><br>Hỗ trợ kỹ thuật: <a href="mailto:support@vietcomnet.test">support@vietcomnet.test</a></p></div><div class="col-lg-5"><div class="card"><div class="card-body"><h3 class="h5">Thời gian hỗ trợ</h3><p class="mb-0">Thứ Hai – Thứ Sáu<br>08:00 – 17:00 (GMT+7)</p></div></div></div></div></div><?php render_footer();
}

function view_login(): void
{
    if (Auth::user()) redirect(route('dashboard'));
    render_header('Đăng nhập');
    page_title('Đăng nhập', 'TÀI KHOẢN');
    ?>
    <div class="container content-section">
        <div class="card mx-auto" style="max-width:520px"><div class="card-body p-4 p-md-5">
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="email">Email</label>
                    <input class="form-control" id="email" type="email" name="email" value="<?= old('email') ?>" required autocomplete="email">
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password">Mật khẩu</label>
                    <div class="input-group">
                        <input class="form-control" id="password" type="password" name="password" minlength="8" required autocomplete="current-password">
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password" aria-label="Hiện mật khẩu">Hiện</button>
                    </div>
                </div>
                <button class="btn btn-primary w-100">Đăng nhập</button>
                <p class="text-center mt-3 mb-0">Chưa có tài khoản? <a href="<?= route('register') ?>">Đăng ký</a></p>
            </form>
        </div></div>
    </div>
    <?php render_footer();
}

function view_register(): void
{
    if (Auth::user()) redirect(route('dashboard'));
    render_header('Đăng ký');
    page_title('Tạo tài khoản', 'TÀI KHOẢN');
    ?>
    <div class="container content-section">
        <div class="card mx-auto" style="max-width:620px"><div class="card-body p-4 p-md-5">
            <form method="post">
                <?= csrf_field() ?>
                <div class="mb-3">
                    <label class="form-label" for="full_name">Họ và tên</label>
                    <input class="form-control" id="full_name" name="full_name" value="<?= old('full_name') ?>" minlength="2" maxlength="100" autocomplete="name" required>
                </div>
                <div class="row">
                    <div class="col-md-7 mb-3">
                        <label class="form-label" for="register-email">Email</label>
                        <input class="form-control" id="register-email" type="email" name="email" value="<?= old('email') ?>" maxlength="100" required autocomplete="email">
                    </div>
                    <div class="col-md-5 mb-3">
                        <label class="form-label" for="phone">Điện thoại</label>
                        <input class="form-control" id="phone" name="phone" value="<?= old('phone') ?>" minlength="8" maxlength="20" autocomplete="tel" inputmode="tel">
                    </div>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="register-password">Mật khẩu</label>
                    <div class="input-group">
                        <input class="form-control" id="register-password" type="password" name="password" minlength="10" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d).{10,}" title="Ít nhất 10 ký tự, gồm chữ hoa, chữ thường và số" required autocomplete="new-password" aria-describedby="password-help">
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="#register-password" aria-label="Hiện mật khẩu">Hiện</button>
                    </div>
                    <div class="form-text" id="password-help">Ít nhất 10 ký tự, gồm chữ hoa, chữ thường và số.</div>
                </div>
                <div class="mb-4">
                    <label class="form-label" for="password_confirmation">Xác nhận mật khẩu</label>
                    <div class="input-group">
                        <input class="form-control" id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password">
                        <button class="btn btn-outline-secondary" type="button" data-password-toggle="#password_confirmation" aria-label="Hiện mật khẩu xác nhận">Hiện</button>
                    </div>
                </div>
                <button class="btn btn-primary w-100">Tạo tài khoản</button>
                <p class="small text-secondary text-center mt-3 mb-0">Đã có tài khoản? <a href="<?= route('login') ?>">Đăng nhập</a></p>
            </form>
        </div></div>
    </div>
    <?php render_footer();
}

function view_dashboard(): void
{
    $u = Auth::requireLogin();
    $db = Database::connection();

    $stmt = $db->prepare('SELECT r.*, c.title FROM registrations r JOIN conferences c ON c.conference_id=r.conference_id WHERE r.user_id=? ORDER BY r.registration_date DESC');
    $stmt->execute([$u['user_id']]);
    $regs = $stmt->fetchAll();

    $stmt = $db->prepare('SELECT p.*, c.title conference_title FROM papers p JOIN conferences c ON c.conference_id=p.conference_id WHERE p.author_id=? ORDER BY p.submitted_at DESC');
    $stmt->execute([$u['user_id']]);
    $papers = $stmt->fetchAll();

    $reviews = [];
    if (in_array($u['role'], ['reviewer','admin'], true)) {
        $stmt = $db->prepare('SELECT r.*, p.title paper_title FROM reviews r JOIN papers p ON p.paper_id=r.paper_id WHERE r.reviewer_id=? ORDER BY r.assigned_at DESC');
        $stmt->execute([$u['user_id']]);
        $reviews = $stmt->fetchAll();
    }

    render_header('Bảng điều khiển');
    page_title('Bảng điều khiển', 'KHU VỰC THÀNH VIÊN');
    ?>
    <div class="container content-section">
        <div class="row g-4">
            <aside class="col-lg-3"><?php member_nav('dashboard'); ?></aside>
            <div class="col-lg-9">
                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 fw-bold mb-0">Đăng ký tham dự</h2>
                    <a class="btn btn-sm btn-outline-primary" href="<?= route('conferences') ?>">Tìm hội thảo</a>
                </div>
                <div class="table-responsive mb-5">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Danh sách hội thảo người dùng đã đăng ký</caption>
                        <thead><tr><th>Hội thảo</th><th>Ngày đăng ký</th><th>Trạng thái</th><th class="text-end">Thao tác</th></tr></thead>
                        <tbody>
                        <?php foreach ($regs as $x): ?>
                            <tr>
                                <td><a class="fw-semibold text-decoration-none" href="<?= route('conference', ['id'=>$x['conference_id']]) ?>"><?= e($x['title']) ?></a></td>
                                <td><?= date_vi($x['registration_date']) ?></td>
                                <td id="registration-status-<?= (int)$x['conference_id'] ?>"><?= status_badge($x['status']) ?></td>
                                <td class="text-end">
                                    <button class="btn btn-sm <?= $x['status'] === 'cancelled' ? 'btn-outline-primary' : 'btn-outline-danger' ?>"
                                            data-register-conference="<?= (int)$x['conference_id'] ?>"
                                            data-registration-status-target="registration-status-<?= (int)$x['conference_id'] ?>"
                                            <?= $x['status'] !== 'cancelled' ? 'data-confirm-registration="Bạn có chắc muốn hủy đăng ký hội thảo này?"' : '' ?>>
                                        <?= $x['status'] === 'cancelled' ? 'Đăng ký lại' : 'Hủy đăng ký' ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$regs): ?><tr><td colspan="4">Bạn chưa đăng ký hội thảo nào.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                    <h2 class="h5 fw-bold mb-0">Bài báo đã nộp</h2>
                    <a class="btn btn-sm btn-primary" href="<?= route('papers') ?>">Nộp bài</a>
                </div>
                <div class="table-responsive mb-5">
                    <table class="table align-middle">
                        <caption class="visually-hidden">Danh sách bài báo người dùng đã nộp</caption>
                        <thead><tr><th>Tiêu đề</th><th>Hội thảo</th><th>Trạng thái</th></tr></thead>
                        <tbody>
                        <?php foreach ($papers as $x): ?>
                            <tr>
                                <td><a class="fw-semibold text-decoration-none" href="<?= route('papers') ?>#paper-<?= (int)$x['paper_id'] ?>"><?= e($x['title']) ?></a></td>
                                <td><?= e($x['conference_title']) ?></td>
                                <td><?= status_badge($x['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$papers): ?><tr><td colspan="3">Bạn chưa nộp bài báo nào.</td></tr><?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <?php if ($reviews): ?>
                    <h2 class="h5 fw-bold mb-3">Phản biện được phân công</h2>
                    <?php foreach ($reviews as $rv): ?>
                        <article class="card mb-3"><div class="card-body">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2">
                                <h3 class="h6 fw-bold mb-0"><?= e($rv['paper_title']) ?></h3>
                                <a class="btn btn-sm btn-outline-primary" href="<?= route('download',['id'=>$rv['paper_id']]) ?>">Tải bài PDF</a>
                            </div>
                            <?php if ($rv['reviewed_at']): ?>
                                <p class="mt-3 mb-1">Đã hoàn thành: <?= date_vi($rv['reviewed_at']) ?></p>
                                <p class="small text-secondary mb-0">Điểm: <?= (int)$rv['score'] ?>/10 · <?= e(recommendation_label((string)$rv['recommendation'])) ?></p>
                            <?php else: ?>
                                <form method="post" action="<?= route('review_submit') ?>" class="row g-3 mt-1">
                                    <?= csrf_field() ?><input type="hidden" name="review_id" value="<?= (int)$rv['review_id'] ?>">
                                    <div class="col-sm-3"><label class="form-label" for="score-<?= (int)$rv['review_id'] ?>">Điểm</label><input class="form-control" id="score-<?= (int)$rv['review_id'] ?>" type="number" name="score" min="1" max="10" required></div>
                                    <div class="col-sm-9"><label class="form-label" for="recommendation-<?= (int)$rv['review_id'] ?>">Khuyến nghị</label><select class="form-select" id="recommendation-<?= (int)$rv['review_id'] ?>" name="recommendation" required><option value="accept">Chấp nhận</option><option value="minor_revision">Sửa nhỏ</option><option value="major_revision">Sửa lớn</option><option value="reject">Từ chối</option></select></div>
                                    <div class="col-12"><label class="form-label" for="comments-<?= (int)$rv['review_id'] ?>">Nhận xét</label><textarea class="form-control" id="comments-<?= (int)$rv['review_id'] ?>" name="comments" minlength="20" rows="4" required></textarea></div>
                                    <div><button class="btn btn-primary">Gửi phản biện</button></div>
                                </form>
                            <?php endif; ?>
                        </div></article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php render_footer();
}

function view_profile(): void
{
    $u = Auth::requireLogin();
    render_header('Hồ sơ');
    page_title('Hồ sơ cá nhân', 'KHU VỰC THÀNH VIÊN');
    ?>
    <div class="container content-section">
        <div class="row g-4">
            <aside class="col-lg-3"><?php member_nav('profile'); ?></aside>
            <div class="col-lg-9">
                <div class="card"><div class="card-body p-4 p-md-5">
                    <h2 class="h5 fw-bold mb-4">Thông tin cá nhân</h2>
                    <form method="post">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label" for="profile-full-name">Họ và tên</label>
                            <input class="form-control" id="profile-full-name" name="full_name" value="<?= old('full_name', (string)$u['full_name']) ?>" maxlength="100" autocomplete="name" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label" for="profile-email">Email</label>
                            <input class="form-control" id="profile-email" value="<?= e($u['email']) ?>" autocomplete="email" disabled>
                            <div class="form-text">Email đăng nhập không thể đổi tại đây.</div>
                        </div>
                        <div class="mb-4">
                            <label class="form-label" for="profile-phone">Điện thoại</label>
                            <input class="form-control" id="profile-phone" name="phone" value="<?= old('phone', (string)$u['phone']) ?>" maxlength="20" autocomplete="tel" inputmode="tel">
                        </div>
                        <button class="btn btn-primary">Lưu thay đổi</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
    <?php render_footer();
}

function view_papers(): void
{
    $u = Auth::requireLogin();
    $db = Database::connection();
    $conferences = $db->query("SELECT conference_id,title,submission_deadline FROM conferences WHERE submission_deadline>=NOW() AND status IN ('upcoming','open') ORDER BY submission_deadline")->fetchAll();
    $stmt = $db->prepare(
        "SELECT p.*, c.title conference_title,
            (SELECT l.reason FROM admin_audit_logs l
             WHERE l.action='paper_decision' AND l.target_type='paper' AND l.target_id=p.paper_id
             ORDER BY l.created_at DESC, l.audit_id DESC LIMIT 1) decision_reason
         FROM papers p
         JOIN conferences c ON c.conference_id=p.conference_id
         WHERE p.author_id=?
         ORDER BY p.submitted_at DESC"
    );
    $stmt->execute([$u['user_id']]);
    $papers = $stmt->fetchAll();

    render_header('Bài báo', 'papers');
    page_title('Quản lý bài báo', 'KHU VỰC TÁC GIẢ');
    ?>
    <div class="container content-section">
        <div class="row g-4">
            <aside class="col-lg-3"><?php member_nav('papers'); ?></aside>
            <div class="col-lg-9">
                <div class="row g-4">
                    <div class="col-xl-5">
                        <div class="card"><div class="card-body p-4">
                            <h2 class="h5 fw-bold">Nộp bài báo mới</h2>
                            <?php if (!$conferences): ?>
                                <div class="alert alert-info">Hiện chưa có hội thảo đang mở nhận bài.</div>
                            <?php endif; ?>
                            <form method="post" enctype="multipart/form-data" data-upload-form>
                                <?= csrf_field() ?><input type="hidden" name="paper_action" value="submit">
                                <div class="mb-3">
                                    <label class="form-label" for="paper-conference">Hội thảo</label>
                                    <select class="form-select" id="paper-conference" name="conference_id" required <?= !$conferences ? 'disabled' : '' ?>>
                                        <option value="">Chọn hội thảo</option>
                                        <?php foreach ($conferences as $x): ?><option value="<?= (int)$x['conference_id'] ?>" <?= old('conference_id') === (string)$x['conference_id'] ? 'selected' : '' ?>><?= e($x['title']) ?> — hạn <?= date_vi($x['submission_deadline']) ?></option><?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="paper-title">Tiêu đề</label>
                                    <input class="form-control" id="paper-title" name="title" value="<?= old('title') ?>" minlength="5" maxlength="255" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="paper-topic">Chủ đề</label>
                                    <input class="form-control" id="paper-topic" name="topic" value="<?= old('topic') ?>" minlength="2" maxlength="100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label" for="paper-abstract">Tóm tắt</label>
                                    <textarea class="form-control" id="paper-abstract" name="abstract" rows="6" minlength="50" required><?= old('abstract') ?></textarea>
                                    <div class="form-text">Tối thiểu 50 ký tự.</div>
                                </div>
                                <div class="mb-4">
                                    <label class="form-label" for="paper-file">Tệp PDF</label>
                                    <input class="form-control" id="paper-file" type="file" name="paper_file" accept="application/pdf,.pdf" data-file-input required>
                                    <div class="form-text">Tối đa <?= e(getenv('MAX_UPLOAD_MB') ?: '10') ?> MB. Tệp được kiểm tra trước khi lưu.</div>
                                    <div class="small text-secondary mt-1 file-selection" data-file-selection aria-live="polite"></div>
                                </div>
                                <button class="btn btn-primary" data-submit-label="Nộp bài" <?= !$conferences ? 'disabled' : '' ?>>Nộp bài</button>
                            </form>
                        </div></div>
                    </div>
                    <div class="col-xl-7">
                        <h2 class="h5 fw-bold mb-3">Bài báo của tôi</h2>
                        <?php if (!$papers): ?>
                            <div class="empty-state">Chưa có bài báo nào.</div>
                        <?php else: foreach ($papers as $p): ?>
                            <article class="card mb-3" id="paper-<?= (int)$p['paper_id'] ?>">
                                <div class="card-body">
                                    <div class="d-flex flex-wrap justify-content-between align-items-start gap-3">
                                        <div>
                                            <h3 class="h6 fw-bold mb-1"><?= e($p['title']) ?></h3>
                                            <p class="small mb-1"><?= e($p['conference_title']) ?> · <?= e($p['topic']) ?></p>
                                            <p class="small text-secondary mb-0">Cập nhật lúc <?= date_vi($p['submitted_at']) ?></p>
                                        </div>
                                        <div><?= status_badge($p['status']) ?></div>
                                    </div>

                                    <?php if (!empty($p['decision_reason'])): ?>
                                        <div class="paper-feedback rounded p-3 mt-3">
                                            <strong>Phản hồi từ ban tổ chức</strong>
                                            <p class="mb-0 mt-1"><?= nl2br(e($p['decision_reason'])) ?></p>
                                        </div>
                                    <?php endif; ?>

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        <a class="btn btn-sm btn-outline-primary" href="<?= route('download',['id'=>$p['paper_id']]) ?>">Tải PDF</a>
                                        <?php if ($p['status'] === 'submitted'): ?>
                                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#replace-paper-<?= (int)$p['paper_id'] ?>" aria-expanded="false" aria-controls="replace-paper-<?= (int)$p['paper_id'] ?>">Thay PDF</button>
                                            <form method="post" class="d-inline">
                                                <?= csrf_field() ?><input type="hidden" name="paper_action" value="withdraw"><input type="hidden" name="paper_id" value="<?= (int)$p['paper_id'] ?>">
                                                <button class="btn btn-sm btn-outline-danger" data-confirm="Thu hồi bài sẽ đưa bài ra khỏi quy trình xét duyệt. Bạn có chắc chắn?">Thu hồi bài</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>

                                    <?php if ($p['status'] === 'submitted'): ?>
                                        <div class="collapse mt-3" id="replace-paper-<?= (int)$p['paper_id'] ?>">
                                            <form method="post" enctype="multipart/form-data" class="border rounded p-3" data-upload-form>
                                                <?= csrf_field() ?><input type="hidden" name="paper_action" value="replace"><input type="hidden" name="paper_id" value="<?= (int)$p['paper_id'] ?>">
                                                <label class="form-label" for="replace-file-<?= (int)$p['paper_id'] ?>">Chọn PDF thay thế</label>
                                                <input class="form-control" id="replace-file-<?= (int)$p['paper_id'] ?>" type="file" name="paper_file" accept="application/pdf,.pdf" data-file-input required>
                                                <div class="small text-secondary mt-1 file-selection" data-file-selection aria-live="polite"></div>
                                                <button class="btn btn-sm btn-primary mt-3" data-submit-label="Cập nhật PDF">Cập nhật PDF</button>
                                            </form>
                                        </div>
                                    <?php elseif ($p['status'] === 'under_review'): ?>
                                        <p class="small text-secondary mt-3 mb-0">Bài đang phản biện nên không thể thay tệp hoặc thu hồi.</p>
                                    <?php endif; ?>
                                </div>
                            </article>
                        <?php endforeach; endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php render_footer();
}

function download_paper(): never
{
    $u=Auth::requireLogin();$id=filter_input(INPUT_GET,'id',FILTER_VALIDATE_INT)?:0;$db=Database::connection();$q=$db->prepare('SELECT p.* FROM papers p WHERE p.paper_id=?');$q->execute([$id]);$p=$q->fetch();if(!$p)throw new RuntimeException('Không tìm thấy bài báo.');$allowed=$u['role']==='admin'||(int)$p['author_id']===(int)$u['user_id'];if(!$allowed&&$u['role']==='reviewer'){$x=$db->prepare('SELECT COUNT(*) FROM reviews WHERE paper_id=? AND reviewer_id=?');$x->execute([$id,$u['user_id']]);$allowed=(bool)$x->fetchColumn();}if(!$allowed){http_response_code(403);throw new RuntimeException('Bạn không có quyền tải bài báo này.');}$path=ROOT_PATH.'/storage/uploads/'.basename($p['file_path']);if(!is_file($path))throw new RuntimeException('Tệp không tồn tại.');header('Content-Type: application/pdf');header('Content-Disposition: attachment; filename="paper-'.$id.'.pdf"');header('Content-Length: '.filesize($path));header('X-Content-Type-Options: nosniff');readfile($path);exit;
}


function view_admin(): void
{
    Auth::requireRole('admin');

    require_once __DIR__ . '/app/admin/AdminWorkflowController.php';

    render_header('Quản trị');
    page_title('Trung tâm quản trị hệ thống', 'ADMIN');

    AdminWorkflowController::renderPanel();

    render_footer();
}


function not_found(): void { http_response_code(404);render_error(404,'Trang bạn yêu cầu không tồn tại.'); }

