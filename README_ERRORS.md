# 🚨 HƯỚNG DẪN FIX LỖI CONNECTION & 404

## Tổng Quan Lỗi

### Lỗi 1: "Failed to connect to 127.0.0.1 port 8000"
- **Nguyên nhân:** Server AI chưa được khởi động
- **Giải pháp:** Xem `README_FIX_NOW.md` hoặc `HOW_TO_START.md`

### Lỗi 2: "HTTP 404 - Lỗi Đăng ký Sinh trắc" ⬅️ **LỖI MỚI**
- **Nguyên nhân:** Đang chạy **SAI SERVER** (Node/Python cũ không có endpoint `/api/enroll_multi`)
- **Giải pháp:** Xem `FIX_404_ERROR.md`

---

## 🎯 Quick Diagnosis

### Bước 1: Kiểm tra Port 8000
```bash
netstat -ano | findstr ":8000"
```

**Kết quả:**
- **Không có gì** → Lỗi 1 (Connection Failed) → Đọc `README_FIX_NOW.md`
- **Có process** → Server đang chạy, tiếp tục bước 2

### Bước 2: Test Health Endpoint
Mở trình duyệt:
```
http://127.0.0.1:8000/health
```

**Kết quả:**
- **Không load được** → Lỗi 1 (Connection Failed)
- **Load được nhưng:**
  - Thấy `"service":"Iris AI Mock v2.0 (PHP)"` → ✅ **ĐÚNG** server
  - Thấy khác (hoặc JSON khác format) → ❌ **SAI** server → Lỗi 2 (404)

### Bước 3: Test Enroll Endpoint
Mở:
```
http://localhost:3000/test_ai_enroll.html
```

Chạy **Test 2: POST Simple**:
- **OK (Quality: XX.XX)** → ✅ Server hoạt động tốt
- **FAIL: HTTP 404** → ❌ Endpoint không tồn tại → Đọc `FIX_404_ERROR.md`
- **FAIL: Failed to fetch** → ❌ Connection error → Đọc `README_FIX_NOW.md`

---

## 📂 File Hướng Dẫn

| File | Mô Tả | Khi Nào Dùng |
|------|-------|--------------|
| `README_FIX_NOW.md` | Fix lỗi connection (không kết nối được) | Lỗi: Failed to connect |
| `FIX_404_ERROR.md` | Fix lỗi 404 (server sai endpoint) | Lỗi: HTTP 404 |
| `HOW_TO_START.md` | Hướng dẫn khởi động hệ thống đầy đủ | Lần đầu chạy / setup |
| `FIX_CONNECTION_ERROR.md` | Troubleshooting chi tiết connection | Debug sâu hơn |
| `test_ai_enroll.html` | Tool test trực tiếp endpoint | Kiểm tra kỹ thuật |

---

## 🚀 Fix Nhanh Nhất (TL;DR)

### Nếu lỗi "Failed to connect"
```bash
cd d:\Chinh_AI\GiangLT_AI
START_SYSTEM.bat
```
Đợi 10 giây → Thử lại enrollment

### Nếu lỗi "HTTP 404"
1. **Đóng tất cả** cửa sổ CMD/PowerShell
2. **Mở Task Manager** → End task `node.exe` và `python.exe`
3. Chạy:
```bash
cd d:\Chinh_AI\GiangLT_AI
START_AI_SERVER.bat
```
4. Thấy dòng "PHP Development Server started" → **Done**
5. Thử lại enrollment

---

## ✅ Checklist Hoạt Động Tốt

Hệ thống hoạt động hoàn hảo khi:
- [ ] `http://127.0.0.1:8000/health` trả về `"service":"Iris AI Mock v2.0 (PHP)"`
- [ ] `http://localhost:3000/test_ai_enroll.html` Test 1,2,3 đều **OK**
- [ ] Enrollment 3 mẫu thành công, không lỗi 404 hay connection
- [ ] `employees.php` hiển thị nhân viên có ✅ **Iris Enrolled**

---

## 🔧 Debug Tools

### 1. Test Connection
```bash
curl http://127.0.0.1:8000/health
```
**Mong đợi:** `{"status":"healthy",...}`

### 2. Test Enroll Endpoint
```bash
curl -X POST http://127.0.0.1:8000/api/enroll_multi -d "emp_id=1&template_index=1"
```
**Mong đợi:** `{"success":true,"quality_score":XX.XX,...}`

### 3. Browser Test Tool
```
http://localhost:3000/test_ai_enroll.html
```
Run all 4 tests → Tất cả phải **OK**

---

## 📞 Support

Nếu sau khi làm theo tất cả hướng dẫn vẫn lỗi:

**Chụp màn hình:**
1. Cửa sổ CMD đang chạy server
2. Browser DevTools (F12) → tab Network → request bị lỗi
3. Kết quả từ `test_ai_enroll.html`

**Kèm thông tin:**
- Hệ điều hành: Windows 10/11
- PHP version: `php -v`
- Trình duyệt: Chrome/Edge/Firefox + version

Gửi đến: chinh.do@lab-security.ai
