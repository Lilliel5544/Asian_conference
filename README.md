# VietComNet Conference Manager

Ứng dụng quản lý hội thảo khoa học dùng PHP 8.1+, MySQL 8+, Bootstrap 5 và AJAX thuần. Giao diện lấy cảm hứng từ trang ASIANComNet 2026 nhưng sử dụng nhận diện VietComNet riêng.

## Tính năng

- Guest: xem hội thảo, ban tổ chức, lịch trình và thông tin liên hệ.
- User: đăng ký tài khoản, cập nhật hồ sơ, đăng ký hoặc hủy tham dự bằng AJAX, nộp/thay thế/thu hồi bài PDF, tải bài, theo dõi trạng thái và xem phản hồi quyết định.
- Reviewer: toàn bộ quyền user, xem bài được phân công, chấm điểm, nhận xét và khuyến nghị.
- Admin: quản lý hội thảo, phiên, đăng ký, bài báo, phân công phản biện, quyết định kết quả và phân quyền tài khoản.
- Cơ sở dữ liệu bám theo ERD gồm `users`, `conferences`, `registrations`, `papers`, `reviews`, `sessions`; có thêm `login_attempts` để bảo vệ đăng nhập.

## Cài đặt với XAMPP hoặc Laragon

1. Sao chép thư mục này vào `htdocs` (XAMPP) hoặc `www` (Laragon).
2. Đổi tên `.env.example` thành `.env`, rồi cập nhật thông tin MySQL và `APP_URL`.
3. Trong phpMyAdmin, chạy `database/schema.sql`.
4. Mở PowerShell tại thư mục dự án và tạo tài khoản quản trị:

   ```powershell
   C:\xampp\php\php.exe app\create_admin.php
   ```

5. Chạy `database/seed.sql` để thêm hội thảo và lịch trình mẫu.
6. Đảm bảo PHP có các extension `pdo_mysql`, `fileinfo`, `mbstring`; thư mục `storage/uploads` có quyền ghi.
7. Truy cập URL đã cấu hình, ví dụ `http://localhost/asiancomnet-conference`.

Với database đã tạo từ phiên bản cũ, chạy thêm `database/migration_user_interface.sql` một lần để bổ sung trạng thái thu hồi bài báo.

Nếu dùng Nginx, đặt document root vào thư mục dự án, chặn truy cập trực tiếp tới `app`, `database`, `storage` và chuyển các URL không phải tệp thật về `index.php`.

## Bảo mật đã triển khai

- Mật khẩu băm bằng `password_hash`, tự rehash khi thuật toán mặc định thay đổi.
- PDO prepared statements, không nối trực tiếp dữ liệu người dùng vào SQL.
- CSRF token cho mọi POST và AJAX; escape HTML tập trung để chống XSS.
- Cookie phiên `HttpOnly`, `SameSite=Lax`, `Secure` khi dùng HTTPS; strict mode và đổi session ID.
- RBAC tại server cho guest, user, reviewer và admin; quyền tải PDF kiểm tra theo tác giả, phản biện hoặc admin.
- Upload PDF kiểm tra lỗi, dung lượng, MIME, phần mở rộng và magic bytes; đổi tên ngẫu nhiên và lưu trong vùng bị chặn truy cập trực tiếp.
- Giới hạn 5 lần đăng nhập lỗi trong 15 phút theo email và hash IP.
- CSP, chống clickjacking, MIME sniffing, rò rỉ referrer và vô hiệu hóa quyền trình duyệt không cần thiết.
- Ràng buộc khóa ngoại, unique, check constraint và index cho luồng nghiệp vụ chính.

## Khuyến nghị triển khai production

- Dùng HTTPS bắt buộc, đặt `APP_ENV=production`, tài khoản MySQL riêng với quyền tối thiểu và mật khẩu mạnh.
- Chuyển `storage` ra ngoài web root nếu cấu trúc hosting cho phép.
- Thiết lập sao lưu database và tệp PDF; quét malware ở tầng máy chủ nếu nhận tệp từ Internet.
- Cấu hình giới hạn request tại reverse proxy, log tập trung, email xác thực và quy trình đặt lại mật khẩu có token hết hạn.
- Không commit `.env`, dữ liệu upload hoặc bản sao database vào Git.

