# ✅ LỖI ĐÃ ĐƯỢC FIX - Hướng Dẫn Khởi Động

## 🎯 Lỗi gốc
```
❌ Failed to connect to 127.0.0.1 port 8000
   → AI Mock Server chưa chạy
```

---

## 🚀 CÁCH KHỞI ĐỘNG (Chọn 1 trong 3)

### ⭐ OPTION 1: Tự động (Khuyên dùng)
```
👆 Double-click file:
   d:\Chinh_AI\GiangLT_AI\START_SYSTEM.bat
```

**Kết quả:**
- ✅ 2 windows CMD xuất hiện (AI Server + Web Admin)
- ✅ Browser tự mở: http://127.0.0.1:3000
- ✅ Auto-verify kết nối AI server
- ✅ Thông báo "System Started Successfully!"

---

### OPTION 2: Chạy riêng từng server (Manual)

#### Bước 1: Start AI Server
```
👆 Double-click: d:\Chinh_AI\GiangLT_AI\START_AI_SERVER.bat
```
Hoặc cmd:
```bash
cd d:\Chinh_AI\GiangLT_AI
php -S 127.0.0.1:8000 ai_mock_server.php
```

**Verify**: Mở browser → `http://127.0.0.1:8000/health`
```json
{"status":"healthy","service":"Iris AI Mock v2.0 (PHP)"}
```

#### Bước 2: Start Web Admin
```
👆 Double-click: d:\Chinh_AI\GiangLT_AI\START_WEB_ADMIN.bat
```
Hoặc cmd:
```bash
cd d:\Chinh_AI\GiangLT_AI\web_admin
php -S 127.0.0.1:3000
```

#### Bước 3: Login
```
Browser → http://127.0.0.1:3000
Username: admin
Password: admin
```

---

### OPTION 3: Test connection trước
```bash
cd d:\Chinh_AI\GiangLT_AI
php test_ai_connection.php
```

**Nếu PASS** → AI server OK → có thể enroll ngay  
**Nếu FAIL** → chạy START_AI_SERVER.bat rồi test lại

---

## 📋 Checklist - Verify System OK

### 1. AI Server Running
```bash
# Check port
netstat -an | findstr :8000
# Kết quả: LISTENING

# Check health
curl http://127.0.0.1:8000/health
# Kết quả: {"status":"healthy",...}
```

### 2. Web Admin Running
```bash
# Browser
http://127.0.0.1:3000
# Kết quả: Trang login hiển thị
```

### 3. Enrollment Works
```
1. Login → Employees
2. Click "Enroll Iris" bất kỳ nhân viên
3. Camera bật
4. Click "📷 Chụp Mẫu 1"
5. Status: "⏳ Đang xử lý..." → "✅ Mẫu 1 đã lưu (Score: 18-24)"
```

---

## 🔧 Troubleshooting

### Lỗi: "Port 8000 already in use"
```bash
# Tìm process
netstat -ano | findstr :8000

# Kill
taskkill /PID <số_PID> /F

# Restart
START_AI_SERVER.bat
```

### Lỗi: "PHP not found"
```bash
# Check
php -v

# Fix: Download PHP 8.0+
https://windows.php.net/download/

# Add to PATH:
C:\php
```

### Lỗi: Camera không bật
```
Chrome → Settings → Privacy → Camera → Allow
hoặc
Click biểu tượng 🔒 bên trái address bar → Camera → Allow
```

### Lỗi: AI server bị crash
```bash
# Check log trong CMD window "AI Mock Server"
# Hoặc chạy test:
php test_ai_connection.php
```

---

## 📁 Files Đã Tạo/Sửa

### Mới tạo:
1. **ai_mock_server.php** - PHP AI server (thay Node.js)
2. **START_SYSTEM.bat** - Launch toàn bộ (auto-verify)
3. **START_AI_SERVER.bat** - Launch riêng AI server
4. **START_WEB_ADMIN.bat** - Launch riêng Web Admin
5. **test_ai_connection.php** - Test script (3 tests)
6. **README_FIX_NOW.md** - Quick fix guide
7. **FIX_CONNECTION_ERROR.md** - Troubleshooting chi tiết

### Đã sửa:
1. **enroll_multi.php** - Rewrite: 3-step UI, camera, preview
2. **live_monitor.php** - Thêm header/footer
3. **access_control.php** - Thêm header/footer

---

## 🎨 Demo Flow

### Enrollment (Happy Path)
```
1. http://127.0.0.1:3000 → Login
2. Employees → Click "Enroll Iris" (emp_id=1)
3. Camera bật → overlay scanner animation
4. Click "Chụp Mẫu 1"
   → Status: "Đang xử lý..."
   → POST http://127.0.0.1:8000/api/enroll_multi
   → Response: {"success":true,"quality_score":19.43}
   → Preview thumbnail hiện
   → Stepper: Mẫu 1 ✓ (green checkmark)
5. Repeat cho Mẫu 2, 3
6. Auto-redirect → Employees page
7. Success toast: "🎉 Đăng ký thành công 3 mẫu!"
```

---

## 📊 API Endpoints Available

| Endpoint | Method | Purpose |
|----------|--------|---------|
| /health | GET | Server health check |
| /api/enroll_multi | POST | Enroll template (3x per person) |
| /api/verify_hybrid | POST | Verify iris (CNN+Gabor) |
| /api/stats | GET | 24h chart data |
| /api/stats?action=recent_logs | GET | Recent access logs |

---

## ✅ Xác nhận fix thành công

Khi bạn thấy:
1. ✅ Terminal: `[200]: POST /api/enroll_multi - 200 OK`
2. ✅ Browser: Preview thumbnail xuất hiện
3. ✅ Status message: "✅ Mẫu X đã lưu (Score: XX.XX)"
4. ✅ Stepper: Mẫu X có dấu ✓ màu xanh

→ **HỆ THỐNG ĐÃ HOẠT ĐỘNG!**

---

## 🎯 Summary

**Trước:**
- ❌ Node.js not installed
- ❌ AI server không chạy
- ❌ Enrollment fail với HTTP 0 error

**Sau:**
- ✅ PHP Mock Server (không cần Node/Python)
- ✅ START_SYSTEM.bat (1-click launch)
- ✅ Auto-verify connection
- ✅ Enrollment hoạt động với 3-step UI

---

**Created**: 2026-10-24  
**Status**: ✅ READY TO USE  
**Next**: Double-click `START_SYSTEM.bat` và test enrollment!
