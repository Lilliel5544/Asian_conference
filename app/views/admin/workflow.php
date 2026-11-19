<?php
/**
 * Partial view duoc goi trong trang ?page=admin boi AdminWorkflowController::renderPanel().
 * Cac bien $stats, $registrations, $users, $reviewers, $papers,
 * $conferences, $auditLogs duoc controller truyen qua pham vi include.
 */
?>
<section class="container py-4" id="admin-workflow" aria-label="Nghiep vu Admin nang cao">
    <div class="border-top pt-4 mb-3">
        <p class="text-uppercase small fw-bold text-primary mb-2">TÍCH HỢP TRONG TRANG QUẢN TRỊ CHÍNH</p>
        <h2 class="h3">Nghiệp vụ điều hành hội thảo</h2>
        <p class="text-muted mb-0">Các tác vụ nghiệp vụ chuyên sâu và nhật ký được tích hợp bên dưới chức năng quản trị hiện có.</p>
    </div>
<div class="py-2">
    <details id="conference-management" class="border rounded mb-3" <?= isset($_GET['edit_conference']) ? 'open' : '' ?>>
        <summary class="px-3 py-3 fw-semibold">Quản lý hội thảo</summary>
        <div class="p-3">
            <form method="post" action="<?= e(route('admin')) ?>" class="card card-body mb-4">
                <?= csrf_field() ?>
                <input type="hidden" name="_admin_workflow" value="1">
                <input type="hidden" name="action" value="save_conference">
                <input type="hidden" name="conference_id" value="<?= (int) ($editingConference['conference_id'] ?? 0) ?>">
                <h3 class="h5"><?= $editingConference ? 'Sửa hội thảo' : 'Tạo hội thảo' ?></h3>
                <label for="conf-title" class="form-label">Tên hội thảo</label>
                <input id="conf-title" class="form-control mb-2" name="title" minlength="5" maxlength="255" required value="<?= e($editingConference['title'] ?? '') ?>">
                <label for="conf-description" class="form-label">Mô tả</label>
                <textarea id="conf-description" class="form-control mb-2" name="description" minlength="20" required><?= e($editingConference['description'] ?? '') ?></textarea>
                <label for="conf-location" class="form-label">Địa điểm</label>
                <input id="conf-location" class="form-control mb-2" name="location" maxlength="255" required value="<?= e($editingConference['location'] ?? '') ?>">
                <?php foreach (['start_date' => 'Bắt đầu', 'end_date' => 'Kết thúc', 'submission_deadline' => 'Hạn nộp bài'] as $field => $label): ?>
                    <label for="conf-<?= e($field) ?>" class="form-label"><?= e($label) ?></label>
                    <input id="conf-<?= e($field) ?>" type="datetime-local" class="form-control mb-2" name="<?= e($field) ?>" required value="<?= !empty($editingConference[$field]) ? e(date('Y-m-d\TH:i', strtotime($editingConference[$field]))) : '' ?>">
                <?php endforeach; ?>
                <div><button class="btn btn-primary"><?= $editingConference ? 'Lưu thay đổi' : 'Tạo hội thảo' ?></button>
                    <?php if ($editingConference): ?><a class="btn btn-outline-secondary" href="<?= e(route('admin')) ?>#conference-management">Hủy sửa</a><?php endif; ?>
                </div>
            </form>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Hội thảo</th><th>Thời gian</th><th>Trạng thái</th><th>Thao tác</th></tr></thead><tbody>
            <?php foreach ($conferences as $c): ?>
                <tr><td><?= e($c['title']) ?></td><td><?= date_vi($c['start_date']) ?></td><td><?= status_badge($c['status']) ?></td><td>
                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin', ['edit_conference' => $c['conference_id']])) ?>#conference-management">Sửa</a>
                        <?php if ($c['status'] === 'draft'): ?>
                            <form method="post" action="<?= e(route('admin')) ?>"><?= csrf_field() ?><input type="hidden" name="_admin_workflow" value="1"><input type="hidden" name="action" value="delete_conference"><input type="hidden" name="conference_id" value="<?= (int) $c['conference_id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Xóa hội thảo nháp này?">Xóa</button></form>
                        <?php endif; ?>
                    </div>
                </td></tr>
            <?php endforeach; ?>
            <?php if (!$conferences): ?><tr><td colspan="4">Chưa có hội thảo.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </details>
    <details id="session-management" class="border rounded mb-3" <?= isset($_GET['edit_session']) ? 'open' : '' ?>>
        <summary class="px-3 py-3 fw-semibold">Quản lý phiên</summary>
        <div class="p-3">
            <form method="post" action="<?= e(route('admin')) ?>" class="card card-body mb-4">
                <?= csrf_field() ?><input type="hidden" name="_admin_workflow" value="1"><input type="hidden" name="action" value="save_session"><input type="hidden" name="session_id" value="<?= (int) ($editingSession['session_id'] ?? 0) ?>">
                <h3 class="h5"><?= $editingSession ? 'Sửa phiên' : 'Tạo phiên' ?></h3>
                <label for="session-conference" class="form-label">Hội thảo</label><select id="session-conference" class="form-select mb-2" name="conference_id" required><option value="">Chọn hội thảo</option><?php foreach ($conferences as $c): ?><option value="<?= (int) $c['conference_id'] ?>" <?= (int) ($editingSession['conference_id'] ?? 0) === (int) $c['conference_id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select>
                <label for="session-name" class="form-label">Tên phiên</label><input id="session-name" class="form-control mb-2" name="session_name" maxlength="255" required value="<?= e($editingSession['session_name'] ?? '') ?>">
                <?php foreach (['start_time' => 'Bắt đầu', 'end_time' => 'Kết thúc'] as $field => $label): ?><label for="session-<?= e($field) ?>" class="form-label"><?= e($label) ?></label><input id="session-<?= e($field) ?>" class="form-control mb-2" type="datetime-local" name="<?= e($field) ?>" required value="<?= !empty($editingSession[$field]) ? e(date('Y-m-d\TH:i', strtotime($editingSession[$field]))) : '' ?>"><?php endforeach; ?>
                <label for="session-room" class="form-label">Phòng</label><input id="session-room" class="form-control mb-2" name="room" maxlength="100" required value="<?= e($editingSession['room'] ?? '') ?>">
                <div><button class="btn btn-primary"><?= $editingSession ? 'Lưu thay đổi' : 'Tạo phiên' ?></button><?php if ($editingSession): ?> <a class="btn btn-outline-secondary" href="<?= e(route('admin')) ?>#session-management">Hủy sửa</a><?php endif; ?></div>
            </form>
            <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Phiên</th><th>Hội thảo</th><th>Thời gian</th><th>Phòng</th><th>Thao tác</th></tr></thead><tbody>
            <?php foreach ($sessions as $s): ?><tr><td><?= e($s['session_name']) ?></td><td><?= e($s['conference_title']) ?></td><td><?= date_vi($s['start_time']) ?> – <?= date_vi($s['end_time']) ?></td><td><?= e($s['room']) ?></td><td><div class="d-flex gap-2">
                <a class="btn btn-sm btn-outline-primary" href="<?= e(route('admin', ['edit_session' => $s['session_id']])) ?>#session-management">Sửa</a>
                <?php if ((int) $s['paper_count'] === 0): ?><form method="post" action="<?= e(route('admin')) ?>"><?= csrf_field() ?><input type="hidden" name="_admin_workflow" value="1"><input type="hidden" name="action" value="delete_session"><input type="hidden" name="session_id" value="<?= (int) $s['session_id'] ?>"><button class="btn btn-sm btn-outline-danger" data-confirm="Xóa phiên này?">Xóa</button></form><?php endif; ?>
            </div></td></tr><?php endforeach; ?>
            <?php if (!$sessions): ?><tr><td colspan="5">Chưa có phiên.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </details>
    <div class="alert alert-info">
        Quy trình: <strong>Duyệt đăng ký → Cấp vai trò reviewer → Phân công phản biện →
        Nhận ít nhất <?= $minCompletedReviews ?> nhận xét → Quyết định bài báo.</strong>
        Tất cả thay đổi đều lưu nhật ký.
    </div>
    <div class="row g-3 mb-4">
        <?php foreach ($stats as $label => $number): ?>
            <div class="col-md-3"><div class="card shadow-sm h-100"><div class="card-body">
                <p class="text-muted mb-1"><?= e($label) ?></p>
                <p class="display-6 fw-bold mb-0"><?= $number ?></p>
            </div></div></div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-4">
        <a class="btn btn-outline-primary btn-sm" href="#registrations">Đăng ký</a>
        <a class="btn btn-outline-primary btn-sm" href="#users">Người dùng</a>
        <a class="btn btn-outline-primary btn-sm" href="#papers">Bài báo</a>
        <a class="btn btn-outline-primary btn-sm" href="#conferences">Hội thảo</a>
        <a class="btn btn-outline-primary btn-sm" href="#audit">Nhật ký</a>
    </div>

    <details id="registrations" class="border rounded mb-3" open>
        <summary class="px-3 py-3 fw-semibold">1. Duyệt đăng ký tham dự</summary>
        <div class="p-3">
        <div class="table-responsive"><table class="table table-bordered table-striped align-middle">
            <thead><tr><th>Người đăng ký</th><th>Hội thảo</th><th>Ngày đăng ký</th><th>Xử lý</th></tr></thead><tbody>
            <?php foreach ($registrations as $r): ?>
                <tr><td><?= e($r['full_name']) ?><div class="small text-muted"><?= e($r['email']) ?></div></td>
                    <td><?= e($r['conference_title']) ?></td>
                    <td><?= e(date_vi($r['registration_date'])) ?></td>
                    <td>
                        <form method="post" action="<?= e(route('admin')) ?>" class="d-flex flex-wrap gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_admin_workflow" value="1">
                            <input type="hidden" name="action" value="registration_decision">
                            <input type="hidden" name="registration_id" value="<?= (int) $r['registration_id'] ?>">
                            <input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do nếu từ chối">
                            <button class="btn btn-success btn-sm" name="status" value="approved">Duyệt</button>
                            <button class="btn btn-outline-danger btn-sm" name="status" value="cancelled">Từ chối</button>
                        </form>
                    </td></tr>
            <?php endforeach; ?>
            <?php if (!$registrations): ?><tr><td colspan="4" class="text-center text-muted">Không có đăng ký chờ duyệt.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </details>

    <details id="users" class="border rounded mb-3">
        <summary class="px-3 py-3 fw-semibold">2. Quản lý vai trò phản biện</summary>
        <div class="p-3">
        <p class="text-muted">Admin có thể cấp vai trò reviewer, nhưng không thể tự đổi quyền hoặc chỉnh tài khoản admin khác.</p>
        <div class="table-responsive"><table class="table table-bordered table-striped align-middle">
            <thead><tr><th>Họ tên</th><th>Email</th><th>Vai trò hiện tại</th><th>Thao tác</th></tr></thead><tbody>
            <?php foreach ($users as $u): ?>
                <tr><td><?= e($u['full_name']) ?></td><td><?= e($u['email']) ?></td>
                    <td><?= e($u['role']) ?></td><td>
                        <form method="post" action="<?= e(route('admin')) ?>" class="d-flex gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_admin_workflow" value="1">
                            <input type="hidden" name="action" value="reviewer_role">
                            <input type="hidden" name="user_id" value="<?= (int) $u['user_id'] ?>">
                            <input type="hidden" name="role" value="<?= $u['role'] === 'reviewer' ? 'user' : 'reviewer' ?>">
                            <button class="btn btn-sm btn-outline-primary">
                                <?= $u['role'] === 'reviewer' ? 'Hạ về user' : 'Cấp quyền reviewer' ?>
                            </button>
                        </form>
                    </td></tr>
            <?php endforeach; ?>
            <?php if (!$users): ?><tr><td colspan="4" class="text-center text-muted">Chưa có người dùng.</td></tr><?php endif; ?>
            </tbody></table></div>
        </div>
    </details>

    <details id="papers" class="border rounded mb-3">
        <summary class="px-3 py-3 fw-semibold">3. Phân công và quyết định bài báo</summary>
        <div class="p-3">
        <p class="text-muted">Mỗi bài phân công tối đa <?= $maxAssignedReviewers ?> reviewer; cần <?= $minCompletedReviews ?> đánh giá hoàn thành trước khi duyệt/từ chối.</p>
        <?php foreach ($papers as $p): ?>
            <div class="card shadow-sm mb-3"><div class="card-body">
                <h4 class="h6 mb-1">#<?= (int) $p['paper_id'] ?> — <?= e($p['title']) ?></h4>
                <p class="small text-muted mb-2">Tác giả: <?= e($p['author_name']) ?> · Chủ đề: <?= e($p['topic']) ?> · <?= e($p['conference_title']) ?></p>
                <p class="mb-2"><?= status_badge($p['status']) ?> · Đã phân công:
                    <strong><?= (int) $p['assigned_count'] ?></strong> · Đã hoàn thành:
                    <strong><?= (int) $p['completed_count'] ?></strong> · Điểm TB:
                    <strong><?= $p['avg_score'] === null ? '—' : e($p['avg_score']) ?></strong></p>
                <div class="row g-3">
                    <div class="col-lg-5">
                        <form method="post" action="<?= e(route('admin')) ?>" class="d-flex flex-wrap gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_admin_workflow" value="1">
                            <input type="hidden" name="action" value="assign_reviewer">
                            <input type="hidden" name="paper_id" value="<?= (int) $p['paper_id'] ?>">
                            <select class="form-select form-select-sm" name="reviewer_id" required>
                                <option value="">Chọn phản biện viên</option>
                                <?php foreach ($reviewers as $r): ?>
                                    <option value="<?= (int) $r['user_id'] ?>"><?= e($r['full_name']) ?> (<?= e($r['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button class="btn btn-sm btn-outline-primary" <?= (int) $p['assigned_count'] >= $maxAssignedReviewers ? 'disabled' : '' ?>>Phân công</button>
                        </form>
                    </div>
                    <div class="col-lg-7">
                        <form method="post" action="<?= e(route('admin')) ?>" class="d-flex flex-wrap gap-2">
                            <?= csrf_field() ?>
                            <input type="hidden" name="_admin_workflow" value="1">
                            <input type="hidden" name="action" value="paper_decision">
                            <input type="hidden" name="paper_id" value="<?= (int) $p['paper_id'] ?>">
                            <input class="form-control form-control-sm" name="reason" maxlength="500" placeholder="Lý do quyết định (10–500 ký tự)" required>
                            <?php $ready = $p['status'] === 'under_review' && (int) $p['completed_count'] >= $minCompletedReviews; ?>
                            <button class="btn btn-success btn-sm" name="status" value="accepted" <?= $ready ? '' : 'disabled' ?>>Chấp nhận</button>
                            <button class="btn btn-outline-danger btn-sm" name="status" value="rejected" <?= $ready ? '' : 'disabled' ?>>Từ chối</button>
                        </form>
                    </div>
                </div>
            </div></div>
        <?php endforeach; ?>
        <?php if (!$papers): ?><p class="text-muted">Không có bài báo đang chờ xử lý.</p><?php endif; ?>
        </div>
    </details>

    <details id="conferences" class="border rounded mb-3">
        <summary class="px-3 py-3 fw-semibold">4. Điều phối trạng thái hội thảo</summary>
        <div class="p-3">
        <p class="text-muted">Trạng thái chuyển theo thứ tự: draft → upcoming → open → closed → completed.</p>
        <div class="table-responsive"><table class="table table-bordered table-striped align-middle"><thead><tr>
            <th>Hội thảo</th><th>Thời gian</th><th>Hạn nộp bài</th><th>Trạng thái</th><th>Thao tác</th>
        </tr></thead><tbody>
        <?php foreach ($conferences as $c): ?>
            <tr><td><?= e($c['title']) ?></td>
                <td><?= e(date_vi($c['start_date'])) ?> – <?= e(date_vi($c['end_date'])) ?></td>
                <td><?= e(date_vi($c['submission_deadline'])) ?></td>
                <td><?= status_badge($c['status']) ?></td><td>
                    <?php if (isset($nextStatus[$c['status']])): ?>
                    <form method="post" action="<?= e(route('admin')) ?>">
                        <?= csrf_field() ?>
                            <input type="hidden" name="_admin_workflow" value="1">
                        <input type="hidden" name="action" value="conference_status">
                        <input type="hidden" name="conference_id" value="<?= (int) $c['conference_id'] ?>">
                        <input type="hidden" name="status" value="<?= e($nextStatus[$c['status']]) ?>">
                        <button class="btn btn-sm btn-outline-primary">Chuyển sang <?= e($nextStatus[$c['status']]) ?></button>
                    </form>
                    <?php else: ?>Đã hoàn tất<?php endif; ?>
                </td></tr>
        <?php endforeach; ?>
        <?php if (!$conferences): ?><tr><td colspan="5" class="text-muted text-center">Chưa có hội thảo.</td></tr><?php endif; ?>
        </tbody></table></div>
        </div>
    </details>

    <details id="audit" class="border rounded mb-3">
        <summary class="px-3 py-3 fw-semibold">5. Nhật ký hoạt động Admin</summary>
        <div class="p-3">
        <div class="table-responsive"><table class="table table-bordered table-striped align-middle"><thead><tr>
            <th>Thời gian</th><th>Admin</th><th>Hành động</th><th>Đối tượng</th><th>Thay đổi</th><th>Lý do</th>
        </tr></thead><tbody>
        <?php foreach ($auditLogs as $l): ?>
            <tr><td><?= e(date_vi($l['created_at'])) ?></td><td><?= e($l['actor_name']) ?></td>
                <td><?= e($l['action']) ?></td><td><?= e($l['target_type']) ?> #<?= (int) $l['target_id'] ?></td>
                <td><?= e($l['old_value'] ?? '—') ?> → <?= e($l['new_value'] ?? '—') ?></td>
                <td><?= e($l['reason'] ?? '—') ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$auditLogs): ?><tr><td colspan="6" class="text-center text-muted">Chưa có thao tác nào.</td></tr><?php endif; ?>
        </tbody></table></div>
        </div>
    </details>
</div>
</section>
