USE conference_manager;

-- Chạy một lần cho database đã được tạo trước khi trạng thái thu hồi bài được bổ sung.
-- Hỗ trợ cả MySQL 8 và MariaDB đi kèm XAMPP.
SET @drop_status_check = IF(
    VERSION() LIKE '%MariaDB%',
    'ALTER TABLE papers DROP CONSTRAINT chk_papers_status',
    'ALTER TABLE papers DROP CHECK chk_papers_status'
);
PREPARE drop_status_check_stmt FROM @drop_status_check;
EXECUTE drop_status_check_stmt;
DEALLOCATE PREPARE drop_status_check_stmt;
ALTER TABLE papers
    ADD CONSTRAINT chk_papers_status
    CHECK (status IN ('submitted','under_review','accepted','rejected','withdrawn'));
