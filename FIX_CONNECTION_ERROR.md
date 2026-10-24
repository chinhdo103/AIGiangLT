# 🔧 FIX: Lỗi "Failed to connect to 127.0.0.1 port 8000"

## Nguyên nhân
AI Mock Server chưa được khởi động hoặc đã bị tắt.

## ✅ Cách fix (3 bước)

### Bước 1: Khởi động AI Mock Server
**Click đúp file này:**
```
d:\Chinh_AI\GiangLT_AI\START_AI_SERVER.bat
```

**Hoặc chạy bằng cmd:**
```bash
cd d:\Chinh_AI\GiangLT_AI
php -S 127.0.0.1:8000 ai_mock_server.php
```

Bạn sẽ thấy:
```
[INFO] Starting server on port 8000...
[INFO] Endpoints:
  - http://127.0.0.1:8000/health
  ...
```

### Bước 2: Kiểm tra server đã chạy
Mở browser và truy cập:
```
http://127.0.0.1:8000/health
```

**Kết quả mong đợi:**
```json
{"status":"healthy","service":"Iris AI Mock v2.0 (PHP)","timestamp":"..."}
```

### Bước 3: Thử lại enrollment
- Quay lại trang enrollment
- Refresh (F5)
- Click "📷 Chụp Mẫu 1"

---

## ⚠️ Nếu vẫn lỗi

### Lỗi: Port 8000 already in use
```bash
# Windows: Tìm process đang dùng port 8000
netstat -ano | findstr :8000

# Kill process
taskkill /PID <process_id> /F

# Khởi động lại
START_AI_SERVER.bat
```

### Lỗi: PHP not found
```bash
# Kiểm tra PHP
php -v

# Nếu không có → cài đặt PHP 8.0+
# Download: https://windows.php.net/download/
# Thêm vào PATH: C:\php
```

### Lỗi: Permission denied
```bash
# Chạy cmd/powershell as Administrator
# Hoặc đổi port khác (nếu 8000 bị firewall block)

# Trong ai_mock_server.php: không cần sửa
# Trong START_AI_SERVER.bat: đổi 8000 → 8001
php -S 127.0.0.1:8001 ai_mock_server.php

# Trong enroll_multi.php dòng ~50: đổi URL
const AI_BASE = 'http://127.0.0.1:8001';
```

---

## 📝 Checklist debug

- [ ] AI server đang chạy (window cmd không đóng)
- [ ] `http://127.0.0.1:8000/health` trả về JSON
- [ ] Web admin đang chạy trên port 3000
- [ ] Browser không block camera
- [ ] Không có firewall block port 8000

---

## 🚀 Khởi động toàn bộ hệ thống

**Cách nhanh nhất** - Chạy cả 2 server cùng lúc:
```
START_SYSTEM.bat
```

Hoặc **manual** (2 terminal riêng):
```bash
# Terminal 1: AI Server
START_AI_SERVER.bat

# Terminal 2: Web Admin
START_WEB_ADMIN.bat

# Browser
http://127.0.0.1:3000
```

---

## ✅ Khi nào coi như OK?

Khi bạn thấy:
1. ✅ Terminal AI Server: `[200]: GET /health`
2. ✅ Browser health check: JSON response
3. ✅ Enrollment page: Camera bật được
4. ✅ Click Chụp: Status "⏳ Đang xử lý..." → "✅ Mẫu 1 đã lưu"

---

**Last Updated**: 2026-10-24
