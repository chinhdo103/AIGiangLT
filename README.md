# HỆ THỐNG XÁC THỰC SINH TRẮC HỌC MỐNG MẮT (IRIS BIOMETRICS LAB SECURITY)

Hệ thống kiểm soát an ninh ra vào phòng Lab bảo mật cao bằng công nghệ phân tích mống mắt sinh trắc học thời gian thực (Daugman Rubber Sheet + CNN MobileNetV2 + Cosine Distance Matching + AI Anti-Spoofing Liveness Detection).

---

## 🚀 HƯỚNG DẪN CÀI ĐẶT & CHẠY TRÊN MÁY MỚI

### 1. Chuẩn bị Môi Trường
* **PHP**: Phiên bản 8.0 trở lên (khuyến nghị dùng XAMPP / Laragon).
* **MySQL / MariaDB**: Cổng mặc định 3306.
* **Python**: Phiên bản 3.10 hoặc 3.11.
* **Webcam**: Camera HD phục vụ thu nhận hình ảnh mống mắt.

---

### 2. Thiết Lập Cơ Sở Dữ Liệu (MySQL)
1. Mở XAMPP hoặc MariaDB, khởi động dịch vụ **MySQL**.
2. Tạo cơ sở dữ liệu mới mang tên `iris_lab_security`:
   ```sql
   CREATE DATABASE iris_lab_security CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. Import bản dump dữ liệu đầy đủ từ thư mục `database/`:
   ```bash
   mysql -u root -p iris_lab_security < database/iris_lab_security_full_dump.sql
   ```
   *(File `iris_lab_security_full_dump.sql` đã chứa sẵn cấu trúc bảng, danh mục phòng Lab, tài khoản nhân viên, vector mẫu mống mắt và lịch sử logs).*

---

### 3. Cài Đặt Thư Viện Python (AI Service)
1. Mở terminal tại thư mục gốc của dự án:
   ```bash
   pip install -r requirements.txt
   ```
2. Khởi chạy AI FastAPI Service (chạy ở cổng 8000):
   ```bash
   python ai_service/main.py
   ```
   *Dịch vụ AI sẽ sẵn sàng tại: `http://localhost:8000` (Swagger UI: `http://localhost:8000/docs`).*

---

### 4. Khởi Chạy Giao Diện Web Admin (PHP)
Mở một cửa sổ terminal khác tại thư mục dự án và chạy:
```bash
php -S 0.0.0.0:3000
```
*Hoặc copy toàn bộ thư mục vào thư mục `C:/xampp/htdocs/iris` của XAMPP.*

---

### 5. Truy Cập Hệ Thống
* **Cổng quét mống mắt đăng nhập**: `http://localhost:3000` (hoặc `http://localhost:3000/web_admin/login.php`)
* **Kiosk mô phỏng cổng quét cửa ra vào**: `http://localhost:3000/web_admin/kiosk.php`
* **Trang Quản trị (Dashboard)**: `http://localhost:3000/web_admin/dashboard.php`
* **Trang Tracking Sơ đồ AI Pipeline 7 bước**: `http://localhost:3000/web_admin/tracking.php`
* **Trang Báo cáo Kỹ thuật & Xuất PDF/Excel/Word**: `http://localhost:3000/web_admin/reports.php`

**Tài khoản Quản Trị Viên (Admin) dự phòng**:
* **Tài khoản**: `admin`
* **Mật khẩu**: `admin123`

---

## 🛠 CÁC CÔNG NGHỆ CHÍNH
* **Frontend**: HTML5, Modern CSS Design System (nền sáng Enterprise), Bootstrap 5.3, SweetAlert2, FontAwesome 6, Tracking.js (face + eye cascade + biological pixel contrast).
* **Backend Web**: PHP PDO thuần kết nối MySQL / MariaDB.
* **AI Core Backend**: Python, FastAPI, Uvicorn, OpenCV (Haar, HoughCircles, CLAHE, FFT Moiré), NumPy, MobileNetV2, Google Generative AI (Gemini Liveness).
* **Báo cáo & Giám định**: SheetJS (`xlsx.full.min.js`), `html2pdf.bundle.min.js`, `biometric_export.js` (xuất phiếu giám định chuẩn A4 kèm 4 tầng ảnh cắt lớp Canvas).

