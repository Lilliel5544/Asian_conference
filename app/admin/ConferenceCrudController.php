<?php
declare(strict_types=1);

/*
 * File xử lý form thêm, sửa, xóa hội thảo.
 * Cách làm giống bài giảng: form gửi action và dữ liệu bằng POST,
 * sau đó switch chọn công việc cần thực hiện.
 */

function conference_log(
    PDO $db,
    int $adminId,
    string $action,
    int $conferenceId,
    ?string $oldValue,
    ?string $newValue
): void {
    $sql = 'INSERT INTO admin_audit_logs
        (actor_id, action, target_type, target_id, old_value, new_value)
        VALUES (?, ?, \'conference\', ?, ?, ?)';

    $stmt = $db->prepare($sql);
    $stmt->execute([
        $adminId,
        $action,
        $conferenceId,
        $oldValue,
        $newValue,
    ]);
}

function handle_conference_crud(
    PDO $db,
    int $adminId,
    string $action
): string {
    switch ($action) {
        /* ==================== THÊM HOẶC SỬA ==================== */
        case 'save_conference':
            $id = (int) ($_POST['conference_id'] ?? 0);
            $title = trim((string) ($_POST['title'] ?? ''));
            $description = trim((string) ($_POST['description'] ?? ''));
            $location = trim((string) ($_POST['location'] ?? ''));
            $startInput = (string) ($_POST['start_date'] ?? '');
            $endInput = (string) ($_POST['end_date'] ?? '');
            $deadlineInput = (string) ($_POST['submission_deadline'] ?? '');

            $start = strtotime($startInput);
            $end = strtotime($endInput);
            $deadline = strtotime($deadlineInput);

            if (
                mb_strlen($title) < 5 ||
                mb_strlen($title) > 255 ||
                mb_strlen($description) < 20 ||
                mb_strlen($location) < 2 ||
                mb_strlen($location) > 255 ||
                $start === false ||
                $end === false ||
                $deadline === false ||
                $start >= $end ||
                $deadline > $start
            ) {
                throw new DomainException(
                    'Thông tin hội thảo hoặc mốc thời gian không hợp lệ.'
                );
            }

            $startDate = date('Y-m-d H:i:s', $start);
            $endDate = date('Y-m-d H:i:s', $end);
            $deadlineDate = date('Y-m-d H:i:s', $deadline);

            if ($id > 0) {
                // Có ID: sửa hội thảo.
                $stmt = $db->prepare(
                    'SELECT title, status FROM conferences
                    WHERE conference_id=?'
                );
                $stmt->execute([$id]);
                $oldConference = $stmt->fetch();

                if (!$oldConference || $oldConference['status'] === 'completed') {
                    throw new DomainException(
                        'Không thể sửa hội thảo đã hoàn tất hoặc không tồn tại.'
                    );
                }

                $sql = 'UPDATE conferences
                    SET title=?, description=?, location=?,
                        start_date=?, end_date=?, submission_deadline=?
                    WHERE conference_id=?';

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $title,
                    $description,
                    $location,
                    $startDate,
                    $endDate,
                    $deadlineDate,
                    $id,
                ]);

                conference_log(
                    $db,
                    $adminId,
                    'edit_conference',
                    $id,
                    (string) $oldConference['title'],
                    $title
                );

                return 'Đã sửa hội thảo.';
            }

            // Không có ID: thêm hội thảo mới.
            $sql = "INSERT INTO conferences
                (title, description, location, start_date, end_date,
                 submission_deadline, status, created_by)
                VALUES (?, ?, ?, ?, ?, ?, 'draft', ?)";

            $stmt = $db->prepare($sql);
            $stmt->execute([
                $title,
                $description,
                $location,
                $startDate,
                $endDate,
                $deadlineDate,
                $adminId,
            ]);

            $newId = (int) $db->lastInsertId();
            conference_log(
                $db,
                $adminId,
                'create_conference',
                $newId,
                null,
                $title
            );

            return 'Đã thêm hội thảo.';

        /* ==================== XÓA ==================== */
        case 'delete_conference':
            $id = (int) ($_POST['conference_id'] ?? 0);

            $stmt = $db->prepare(
                'SELECT title, status FROM conferences
                WHERE conference_id=?'
            );
            $stmt->execute([$id]);
            $conference = $stmt->fetch();

            if (!$conference || $conference['status'] !== 'draft') {
                throw new DomainException(
                    'Chỉ được xóa hội thảo ở trạng thái nháp.'
                );
            }

            // Không xóa nếu hội thảo đã có dữ liệu liên quan.
            foreach (['papers', 'registrations', 'sessions'] as $table) {
                $stmt = $db->prepare(
                    "SELECT COUNT(*) FROM {$table} WHERE conference_id=?"
                );
                $stmt->execute([$id]);

                if ((int) $stmt->fetchColumn() > 0) {
                    throw new DomainException(
                        'Hội thảo còn bài báo, đăng ký hoặc phiên. Không thể xóa.'
                    );
                }
            }

            $stmt = $db->prepare(
                'DELETE FROM conferences WHERE conference_id=?'
            );
            $stmt->execute([$id]);

            conference_log(
                $db,
                $adminId,
                'delete_conference',
                $id,
                (string) $conference['title'],
                'deleted'
            );

            return 'Đã xóa hội thảo.';

        /* ==================== ĐỔI TRẠNG THÁI ==================== */
        case 'conference_status':
            $id = (int) ($_POST['conference_id'] ?? 0);
            $newStatus = (string) ($_POST['status'] ?? '');

            $nextStatus = [
                'draft' => 'upcoming',
                'upcoming' => 'open',
                'open' => 'closed',
                'closed' => 'completed',
            ];

            $stmt = $db->prepare(
                'SELECT status FROM conferences WHERE conference_id=?'
            );
            $stmt->execute([$id]);
            $oldStatus = $stmt->fetchColumn();

            if (!$oldStatus || ($nextStatus[$oldStatus] ?? '') !== $newStatus) {
                throw new DomainException(
                    'Hội thảo phải chuyển trạng thái theo đúng thứ tự.'
                );
            }

            $stmt = $db->prepare(
                'UPDATE conferences SET status=? WHERE conference_id=?'
            );
            $stmt->execute([$newStatus, $id]);

            conference_log(
                $db,
                $adminId,
                'conference_status',
                $id,
                (string) $oldStatus,
                $newStatus
            );

            return 'Đã chuyển trạng thái hội thảo.';

        default:
            throw new DomainException(
                'Thao tác hội thảo không được hỗ trợ.'
            );
    }
}
