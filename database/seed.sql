USE conference_manager;

SET @admin_id = (SELECT user_id FROM users WHERE role = 'admin' ORDER BY user_id LIMIT 1);

INSERT INTO conferences (title, description, location, start_date, end_date, submission_deadline, status, created_by)
SELECT
    'VietComNet 2027 – Communication and Networks',
    'Hội thảo khoa học quốc tế về truyền thông, mạng thế hệ mới, trí tuệ nhân tạo cho mạng và các hệ thống kết nối thông minh. Chương trình kết hợp tham dự trực tiếp tại Hà Nội và trực tuyến.',
    'Học viện Công nghệ Bưu chính Viễn thông, Hà Nội',
    '2027-10-12 08:00:00',
    '2027-10-14 17:00:00',
    '2027-06-30 23:59:59',
    'open',
    @admin_id
WHERE @admin_id IS NOT NULL
  AND NOT EXISTS (SELECT 1 FROM conferences WHERE title = 'VietComNet 2027 – Communication and Networks');

SET @conference_id = (SELECT conference_id FROM conferences WHERE title = 'VietComNet 2027 – Communication and Networks' LIMIT 1);

INSERT INTO sessions (conference_id, session_name, start_time, end_time, room)
SELECT @conference_id, 'Khai mạc và bài phát biểu chính', '2027-10-12 08:30:00', '2027-10-12 10:00:00', 'Hội trường A2'
WHERE @conference_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sessions WHERE conference_id=@conference_id AND session_name='Khai mạc và bài phát biểu chính');

INSERT INTO sessions (conference_id, session_name, start_time, end_time, room)
SELECT @conference_id, 'Mạng 6G và truyền thông thông minh', '2027-10-12 10:30:00', '2027-10-12 12:00:00', 'Phòng 101'
WHERE @conference_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sessions WHERE conference_id=@conference_id AND session_name='Mạng 6G và truyền thông thông minh');

INSERT INTO sessions (conference_id, session_name, start_time, end_time, room)
SELECT @conference_id, 'AI cho tối ưu hóa mạng', '2027-10-13 09:00:00', '2027-10-13 11:30:00', 'Phòng 202'
WHERE @conference_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM sessions WHERE conference_id=@conference_id AND session_name='AI cho tối ưu hóa mạng');

