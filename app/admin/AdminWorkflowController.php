<?php
declare(strict_types=1);

/**
 * Module bo sung nghiep vu cho trang ?page=admin.
 * Luon duoc include tu index.php SAU khi app/bootstrap.php da duoc load.
 * Khong truy cap truc tiep file nay tu trinh duyet.
 */
final class AdminWorkflowController
{
    private const MIN_COMPLETED_REVIEWS = 2;
    private const MAX_ASSIGNED_REVIEWERS = 3;

    private static function postText(string $key): string
    {
        $value = $_POST[$key] ?? '';
        return is_string($value) ? trim($value) : '';
    }

    private static function postId(string $key): int
    {
        $value = filter_var($_POST[$key] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        return $value === false ? 0 : (int) $value;
    }

    
    private static function postDate(string $key): DateTimeImmutable
    {
        $value = self::postText($key);

        $date = DateTimeImmutable::createFromFormat(
            '!Y-m-d\TH:i',
            $value
        );

        if (!$date || $date->format('Y-m-d\TH:i') !== $value) {
            throw new DomainException(
                'Ngày giờ không hợp lệ: ' . $key
            );
        }

        return $date;
    }

    private static function audit(
        PDO $db,
        int $actorId,
        string $action,
        string $targetType,
        int $targetId,
        ?string $oldValue,
        ?string $newValue,
        ?string $reason = null
    ): void {
        $stmt = $db->prepare('INSERT INTO admin_audit_logs
            (actor_id, action, target_type, target_id, old_value, new_value, reason)
            VALUES (?, ?, ?, ?, ?, ?, ?)');
        $stmt->execute([$actorId, $action, $targetType, $targetId, $oldValue, $newValue, $reason]);
    }

    public static function handlePost(): never
    {
        // Chi xu ly form thuoc module nay; form admin cu tiep tuc theo index.php.
        if (!is_post() || ($_POST['_admin_workflow'] ?? null) !== '1') {
            http_response_code(400);
            exit('Yeu cau khong hop le.');
        }
        $admin = Auth::requireRole('admin');
        $db = Database::connection();
        try {
            Security::verifyCsrf(self::postText('csrf_token'));
            $action = self::postText('action');
            $actorId = (int) $admin['user_id'];
            $db->beginTransaction();

            switch ($action) {
                
                case 'save_conference':
                case 'delete_conference':
                case 'conference_status': {
                    require_once __DIR__ . '/ConferenceCrudController.php';
                    $message = handle_conference_crud($db, $actorId, $action);
                    break;
                }

                case 'save_session': {
                    $id = self::postId('session_id');
                    $conferenceId = self::postId('conference_id');
                    $name = self::postText('session_name');
                    $room = self::postText('room');

                    $start = self::postDate('start_time');
                    $end = self::postDate('end_time');

                    if (
                        $conferenceId < 1 ||
                        mb_strlen($name) < 3 ||
                        mb_strlen($name) > 255 ||
                        mb_strlen($room) < 1 ||
                        mb_strlen($room) > 100 ||
                        $start >= $end
                    ) {
                        throw new DomainException('Thông tin phiên không hợp lệ.');
                    }

                    $before = null;
                    if ($id > 0) {
                        $stmt = $db->prepare(
                            'SELECT session_name, conference_id
                            FROM sessions
                            WHERE session_id=? FOR UPDATE'
                        );
                        $stmt->execute([$id]);
                        $before = $stmt->fetch();

                        if (!$before) {
                            throw new DomainException('Không tìm thấy phiên cần sửa.');
                        }

                        if ((int) $before['conference_id'] !== $conferenceId) {
                            $stmt = $db->prepare(
                                'SELECT COUNT(*) FROM papers WHERE session_id=?'
                            );
                            $stmt->execute([$id]);

                            if ((int) $stmt->fetchColumn() > 0) {
                                throw new DomainException(
                                    'Không thể chuyển phiên đã có bài sang hội thảo khác.'
                                );
                            }
                        }
                    }

                    $stmt = $db->prepare(
                        'SELECT start_date, end_date, status
                        FROM conferences
                        WHERE conference_id=? FOR UPDATE'
                    );
                    $stmt->execute([$conferenceId]);
                    $conference = $stmt->fetch();

                    if (
                        !$conference ||
                        $conference['status'] === 'completed' ||
                        $start < new DateTimeImmutable($conference['start_date']) ||
                        $end > new DateTimeImmutable($conference['end_date'])
                    ) {
                        throw new DomainException(
                            'Phiên phải nằm trong thời gian diễn ra hội thảo.'
                        );
                    }

                    $stmt = $db->prepare(
                        'SELECT COUNT(*) FROM sessions
                        WHERE conference_id=?
                        AND room=?
                        AND start_time < ?
                        AND end_time > ?
                        AND session_id <> ?'
                    );

                    $stmt->execute([
                        $conferenceId,
                        $room,
                        $end->format('Y-m-d H:i:s'),
                        $start->format('Y-m-d H:i:s'),
                        $id
                    ]);

                    if ((int) $stmt->fetchColumn() > 0) {
                        throw new DomainException(
                            'Phòng đã có phiên khác trong khoảng thời gian này.'
                        );
                    }

                    if ($id > 0) {
                        $db->prepare(
                            'UPDATE sessions
                            SET conference_id=?, session_name=?, start_time=?,
                                end_time=?, room=?
                            WHERE session_id=?'
                        )->execute([
                            $conferenceId,
                            $name,
                            $start->format('Y-m-d H:i:s'),
                            $end->format('Y-m-d H:i:s'),
                            $room,
                            $id
                        ]);

                        self::audit(
                            $db, $actorId, 'edit_session',
                            'session', $id,
                            (string) $before['session_name'], $name
                        );

                        $message = 'Đã cập nhật phiên hội thảo.';
                    } else {
                        $db->prepare(
                            'INSERT INTO sessions
                            (conference_id, session_name, start_time, end_time, room)
                            VALUES (?, ?, ?, ?, ?)'
                        )->execute([
                            $conferenceId,
                            $name,
                            $start->format('Y-m-d H:i:s'),
                            $end->format('Y-m-d H:i:s'),
                            $room
                        ]);

                        $id = (int) $db->lastInsertId();

                        self::audit(
                            $db, $actorId, 'create_session',
                            'session', $id, null, $name
                        );

                        $message = 'Đã tạo phiên hội thảo.';
                    }
                    break;
                }

                case 'delete_session': {
                    $id = self::postId('session_id');

                    $stmt = $db->prepare(
                        'SELECT session_name FROM sessions
                        WHERE session_id=? FOR UPDATE'
                    );
                    $stmt->execute([$id]);
                    $name = $stmt->fetchColumn();

                    if ($name === false) {
                        throw new DomainException('Không tìm thấy phiên.');
                    }

                    $stmt = $db->prepare(
                        'SELECT COUNT(*) FROM papers WHERE session_id=?'
                    );
                    $stmt->execute([$id]);

                    if ((int) $stmt->fetchColumn() > 0) {
                        throw new DomainException(
                            'Cần gỡ lịch các bài báo trước khi xóa phiên.'
                        );
                    }

                    $db->prepare(
                        'DELETE FROM sessions WHERE session_id=?'
                    )->execute([$id]);

                    self::audit(
                        $db, $actorId, 'delete_session',
                        'session', $id, $name, 'deleted'
                    );

                    $message = 'Đã xóa phiên hội thảo.';
                    break;
                }

                case 'schedule_paper': {
                    $paperId = self::postId('paper_id');
                    $sessionId = self::postId('session_id');

                    $stmt = $db->prepare(
                        'SELECT conference_id, status, session_id
                        FROM papers
                        WHERE paper_id=? FOR UPDATE'
                    );
                    $stmt->execute([$paperId]);
                    $paper = $stmt->fetch();

                    if (!$paper || $paper['status'] !== 'accepted') {
                        throw new DomainException(
                            'Chỉ được xếp lịch cho bài báo đã chấp nhận.'
                        );
                    }

                    if ($sessionId > 0) {
                        $stmt = $db->prepare(
                            'SELECT conference_id FROM sessions WHERE session_id=?'
                        );
                        $stmt->execute([$sessionId]);

                        if ((int) $stmt->fetchColumn() !== (int) $paper['conference_id']) {
                            throw new DomainException(
                                'Phiên không thuộc hội thảo của bài báo.'
                            );
                        }
                    }

                    $db->prepare(
                        'UPDATE papers SET session_id=? WHERE paper_id=?'
                    )->execute([$sessionId ?: null, $paperId]);

                    self::audit(
                        $db, $actorId, 'schedule_paper',
                        'paper', $paperId,
                        (string) ($paper['session_id'] ?? 'none'),
                        $sessionId ? (string) $sessionId : 'none'
                    );

                    $message = 'Đã cập nhật lịch trình bài báo.';
                    break;
                }

                case 'remove_reviewer': {
                    $reviewId = self::postId('review_id');

                    $stmt = $db->prepare(
                        'SELECT r.paper_id, r.reviewer_id,
                                r.reviewed_at, p.status
                        FROM reviews r
                        JOIN papers p ON p.paper_id=r.paper_id
                        WHERE r.review_id=? FOR UPDATE'
                    );
                    $stmt->execute([$reviewId]);
                    $review = $stmt->fetch();

                    if (
                        !$review ||
                        $review['reviewed_at'] !== null ||
                        $review['status'] !== 'under_review'
                    ) {
                        throw new DomainException(
                            'Chỉ được thu hồi phân công chưa hoàn thành.'
                        );
                    }

                    $db->prepare(
                        'DELETE FROM reviews WHERE review_id=?'
                    )->execute([$reviewId]);

                    $stmt = $db->prepare(
                        'SELECT COUNT(*) FROM reviews WHERE paper_id=?'
                    );
                    $stmt->execute([$review['paper_id']]);

                    if ((int) $stmt->fetchColumn() === 0) {
                        $db->prepare(
                            "UPDATE papers SET status='submitted' WHERE paper_id=?"
                        )->execute([$review['paper_id']]);
                    }

                    self::audit(
                        $db, $actorId, 'remove_reviewer',
                        'paper', (int) $review['paper_id'],
                        'reviewer_id=' . $review['reviewer_id'],
                        'removed'
                    );

                    $message = 'Đã thu hồi phân công phản biện.';
                    break;
                }

                case 'registration_decision': {
                    $id = self::postId('registration_id');
                    $newStatus = self::postText('status');
                    $reason = self::postText('reason');
                    if ($id === 0 || !in_array($newStatus, ['approved', 'cancelled'], true)) {
                        throw new DomainException('Dữ liệu duyệt đăng ký không hợp lệ.');
                    }
                    if ($newStatus === 'cancelled' && mb_strlen($reason) < 10) {
                        throw new DomainException('Cần nêu lý do từ chối đăng ký (ít nhất 10 ký tự).');
                    }
                    $stmt = $db->prepare('SELECT status FROM registrations WHERE registration_id = ? FOR UPDATE');
                    $stmt->execute([$id]);
                    $oldStatus = $stmt->fetchColumn();
                    if ($oldStatus !== 'pending') {
                        throw new DomainException('Chỉ có thể xử lý đăng ký đang chờ duyệt.');
                    }
                    $stmt = $db->prepare('UPDATE registrations SET status = ? WHERE registration_id = ?');
                    $stmt->execute([$newStatus, $id]);
                    self::audit($db, $actorId, 'registration_decision', 'registration', $id,
                        'pending', $newStatus, $reason ?: null);
                    $message = 'Đã cập nhật trạng thái đăng ký.';
                    break;
                }
                case 'reviewer_role': {
                    $id = self::postId('user_id');
                    $newRole = self::postText('role');
                    if ($id === 0 || !in_array($newRole, ['user', 'reviewer'], true)) {
                        throw new DomainException('Vai trò không hợp lệ.');
                    }
                    if ($id === $actorId) {
                        throw new DomainException('Không thể tự thay đổi vai trò của mình.');
                    }
                    $stmt = $db->prepare('SELECT role FROM users WHERE user_id = ? FOR UPDATE');
                    $stmt->execute([$id]);
                    $oldRole = $stmt->fetchColumn();
                    if (!$oldRole || $oldRole === 'admin') {
                        throw new DomainException('Không thể thay đổi tài khoản quản trị.');
                    }
                    if ($oldRole === $newRole) {
                        throw new DomainException('Tài khoản đã có vai trò này.');
                    }
                    if ($newRole === 'user') {
                        $stmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE reviewer_id = ?');
                        $stmt->execute([$id]);
                        if ((int) $stmt->fetchColumn() > 0) {
                            throw new DomainException('Phản biện viên đã có lịch sử được phân công; không thể hạ quyền trực tiếp.');
                        }
                    }
                    $stmt = $db->prepare('UPDATE users SET role = ? WHERE user_id = ?');
                    $stmt->execute([$newRole, $id]);
                    self::audit($db, $actorId, 'reviewer_role', 'user', $id,
                        (string) $oldRole, $newRole);
                    $message = 'Đã cập nhật vai trò người dùng.';
                    break;
                }
                case 'assign_reviewer': {
                    $paperId = self::postId('paper_id');
                    $reviewerId = self::postId('reviewer_id');
                    if ($paperId === 0 || $reviewerId === 0) {
                        throw new DomainException('Vui lòng chọn bài báo và phản biện viên.');
                    }
                    $stmt = $db->prepare('SELECT author_id, status FROM papers WHERE paper_id = ? FOR UPDATE');
                    $stmt->execute([$paperId]);
                    $paper = $stmt->fetch();
                    if (!$paper || !in_array($paper['status'], ['submitted', 'under_review'], true)) {
                        throw new DomainException('Bài báo không ở trạng thái có thể phân công.');
                    }
                    $stmt = $db->prepare('SELECT role FROM users WHERE user_id = ?');
                    $stmt->execute([$reviewerId]);
                    if ($stmt->fetchColumn() !== 'reviewer') {
                        throw new DomainException('Người được phân công phải có vai trò reviewer.');
                    }
                    if ((int) $paper['author_id'] === $reviewerId) {
                        throw new DomainException('Tác giả không được phản biện bài của chính mình.');
                    }
                    $stmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE paper_id = ?');
                    $stmt->execute([$paperId]);
                    if ((int) $stmt->fetchColumn() >= self::MAX_ASSIGNED_REVIEWERS) {
                        throw new DomainException('Mỗi bài báo chỉ được phân công tối đa 3 phản biện viên.');
                    }
                    $stmt = $db->prepare('SELECT COUNT(*) FROM reviews WHERE paper_id = ? AND reviewer_id = ?');
                    $stmt->execute([$paperId, $reviewerId]);
                    if ((int) $stmt->fetchColumn() > 0) {
                        throw new DomainException('Phản biện viên đã được phân công bài này.');
                    }
                    $stmt = $db->prepare('INSERT INTO reviews (paper_id, reviewer_id) VALUES (?, ?)');
                    $stmt->execute([$paperId, $reviewerId]);
                    if ($paper['status'] === 'submitted') {
                        $db->prepare("UPDATE papers SET status = 'under_review' WHERE paper_id = ?")
                           ->execute([$paperId]);
                    }
                    self::audit($db, $actorId, 'assign_reviewer', 'paper', $paperId,
                        null, 'reviewer_id=' . $reviewerId);
                    $message = 'Đã phân công phản biện viên và chuyển bài sang quy trình phản biện.';
                    break;
                }
                case 'paper_decision': {
                    $paperId = self::postId('paper_id');
                    $newStatus = self::postText('status');
                    $reason = self::postText('reason');
                    if ($paperId === 0 || !in_array($newStatus, ['accepted', 'rejected'], true)) {
                        throw new DomainException('Quyết định bài báo không hợp lệ.');
                    }
                    if (mb_strlen($reason) < 10 || mb_strlen($reason) > 500) {
                        throw new DomainException('Lý do quyết định phải dài từ 10 đến 500 ký tự.');
                    }
                    $stmt = $db->prepare('SELECT status FROM papers WHERE paper_id = ? FOR UPDATE');
                    $stmt->execute([$paperId]);
                    if ($stmt->fetchColumn() !== 'under_review') {
                        throw new DomainException('Chỉ được quyết định bài đang phản biện.');
                    }
                    $stmt = $db->prepare('SELECT COUNT(*) FROM reviews
                        WHERE paper_id = ? AND reviewed_at IS NOT NULL
                        AND score IS NOT NULL AND recommendation IS NOT NULL');
                    $stmt->execute([$paperId]);
                    if ((int) $stmt->fetchColumn() < self::MIN_COMPLETED_REVIEWS) {
                        throw new DomainException('Cần ít nhất 2 bản phản biện hoàn thành trước khi quyết định.');
                    }
                    $stmt = $db->prepare('UPDATE papers SET status = ? WHERE paper_id = ?');
                    $stmt->execute([$newStatus, $paperId]);
                    self::audit($db, $actorId, 'paper_decision', 'paper', $paperId,
                        'under_review', $newStatus, $reason);
                    $message = 'Đã ghi nhận quyết định cuối cùng cho bài báo.';
                    break;
                }
                default:
                    throw new DomainException('Hành động quản trị không được hỗ trợ.');
            }

            $db->commit();
            flash('success', $message);
        } catch (DomainException | RuntimeException $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            flash('danger', $exception->getMessage());
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log('Admin workflow error: ' . $exception->getMessage());
            flash('danger', 'Không thể thực hiện thao tác. Kiểm tra bảng admin_audit_logs và nhật ký máy chủ.');
        }
        redirect(route('admin') . '#admin-workflow');
    }

    public static function renderPanel(): void
    {
        Auth::requireRole('admin');
        static $rendered = false;
        if ($rendered) {
            return;
        }
        $rendered = true;
        $db = Database::connection();
        $stats = [
            'Đăng ký chờ duyệt' => (int) $db->query("SELECT COUNT(*) FROM registrations WHERE status='pending'")->fetchColumn(),
            'Bài đang phản biện' => (int) $db->query("SELECT COUNT(*) FROM papers WHERE status='under_review'")->fetchColumn(),
            'Bài đã chấp nhận' => (int) $db->query("SELECT COUNT(*) FROM papers WHERE status='accepted'")->fetchColumn(),
            'Phản biện viên' => (int) $db->query("SELECT COUNT(*) FROM users WHERE role='reviewer'")->fetchColumn(),
        ];
        $registrations = $db->query("SELECT r.registration_id, r.status, r.registration_date,
            u.full_name, u.email, c.title AS conference_title
            FROM registrations r JOIN users u ON u.user_id = r.user_id
            JOIN conferences c ON c.conference_id = r.conference_id
            WHERE r.status = 'pending' ORDER BY r.registration_date LIMIT 80")->fetchAll();
        $users = $db->query("SELECT user_id, full_name, email, role FROM users
            WHERE role IN ('user', 'reviewer') ORDER BY created_at DESC LIMIT 80")->fetchAll();
        $reviewers = $db->query("SELECT user_id, full_name, email FROM users
            WHERE role = 'reviewer' ORDER BY full_name")->fetchAll();
        $papers = $db->query("SELECT p.paper_id, p.title, p.topic, p.status,
            a.full_name AS author_name, c.title AS conference_title,
            (SELECT COUNT(*) FROM reviews v WHERE v.paper_id = p.paper_id) AS assigned_count,
            (SELECT COUNT(*) FROM reviews v WHERE v.paper_id = p.paper_id
               AND v.reviewed_at IS NOT NULL AND v.score IS NOT NULL
               AND v.recommendation IS NOT NULL) AS completed_count,
            (SELECT ROUND(AVG(v.score), 1) FROM reviews v WHERE v.paper_id = p.paper_id
               AND v.reviewed_at IS NOT NULL) AS avg_score
            FROM papers p JOIN users a ON a.user_id = p.author_id
            JOIN conferences c ON c.conference_id = p.conference_id
            WHERE p.status IN ('submitted','under_review')
            ORDER BY p.submitted_at DESC LIMIT 80")->fetchAll();
        $conferences = $db->query('SELECT conference_id, title, status, start_date,
            end_date, submission_deadline FROM conferences ORDER BY start_date DESC LIMIT 40')->fetchAll();
        $auditLogs = $db->query('SELECT l.*, u.full_name AS actor_name
            FROM admin_audit_logs l JOIN users u ON u.user_id = l.actor_id
            ORDER BY l.created_at DESC, l.audit_id DESC LIMIT 30')->fetchAll();
        
        $editConferenceId = filter_input(
            INPUT_GET,
            'edit_conference',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $editingConference = null;

        if ($editConferenceId > 0) {
            $stmt = $db->prepare(
                'SELECT * FROM conferences WHERE conference_id=?'
            );
            $stmt->execute([$editConferenceId]);
            $editingConference = $stmt->fetch() ?: null;
        }

        $sessions = $db->query(
            'SELECT s.*, c.title AS conference_title,
                (SELECT COUNT(*) FROM papers p
                WHERE p.session_id=s.session_id) AS paper_count
            FROM sessions s
            JOIN conferences c ON c.conference_id=s.conference_id
            ORDER BY s.start_time'
        )->fetchAll();

        $editSessionId = filter_input(
            INPUT_GET,
            'edit_session',
            FILTER_VALIDATE_INT
        ) ?: 0;

        $editingSession = null;

        if ($editSessionId > 0) {
            $stmt = $db->prepare(
                'SELECT * FROM sessions WHERE session_id=?'
            );
            $stmt->execute([$editSessionId]);
            $editingSession = $stmt->fetch() ?: null;
        }

        $acceptedPapers = $db->query(
            "SELECT p.paper_id, p.title, p.conference_id,
                    p.session_id, c.title AS conference_title
            FROM papers p
            JOIN conferences c ON c.conference_id=p.conference_id
            WHERE p.status='accepted'
            ORDER BY p.submitted_at DESC"
        )->fetchAll();

        $reviewAssignments = [];

        $assignedRows = $db->query(
            'SELECT r.review_id, r.paper_id, r.reviewed_at,
                    u.full_name
            FROM reviews r
            JOIN users u ON u.user_id=r.reviewer_id
            ORDER BY r.assigned_at DESC'
        )->fetchAll();

        foreach ($assignedRows as $row) {
            $reviewAssignments[(int) $row['paper_id']][] = $row;
        }

        $nextStatus = ['draft' => 'upcoming', 'upcoming' => 'open',
            'open' => 'closed', 'closed' => 'completed'];

        $minCompletedReviews = self::MIN_COMPLETED_REVIEWS;
        $maxAssignedReviewers = self::MAX_ASSIGNED_REVIEWERS;
        // Trang admin cu da render_header/page_title; partial khong duoc render footer.
        require dirname(__DIR__) . '/views/admin/workflow.php';
    }
}
