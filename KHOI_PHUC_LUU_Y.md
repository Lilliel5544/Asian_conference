# Lưu ý về bản phục hồi

Đây là bản phục hồi tạm từ bản sao dự án ngày 04/11/2026 và lịch sử chỉnh sửa của Codex. Bản này chưa được kiểm thử chạy bằng PHP/MySQL trên máy hiện tại.

- `index.php` được ghép lại từ bản nội dung đã đọc trong lịch sử.
- `ConferenceCrudController.php` được phục hồi từ bản vá cuối cùng trong lịch sử.
- `AdminWorkflowController.php` được phục hồi từ nội dung đã lưu và áp dụng lại thay đổi về phiên.
- `app/views/admin/workflow.php` dựa trên một bản cũ; hai form quản lý hội thảo và phiên được tái tạo. Giao diện có thể không giống phiên bản cuối.
- `app/View.php`, `app/helpers.php`, CSS và một số thành phần đã được khôi phục hoặc điều chỉnh từ bản cũ, không bảo đảm trùng bản cuối.
- File `.env`, cơ sở dữ liệu thực tế, tài khoản, mật khẩu và PDF cũ không nằm trong bản này và chưa được khôi phục. Không nhập `seed.sql` vào database có dữ liệu mà chưa kiểm tra.
- Cần cài/khôi phục PHP, Apache và MariaDB/MySQL, cấu hình `.env`, kiểm tra cú pháp PHP và thử các quy trình trước khi sử dụng.
- Địa chỉ email `.test` là thông tin mẫu, cần thay bằng địa chỉ thật trước khi dùng.

Bản này nên được giữ riêng để đối chiếu. Không ghi đè lên một dự án hoặc database đang có dữ liệu.
