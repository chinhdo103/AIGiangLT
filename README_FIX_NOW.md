# 🚨 LỖI KẾT NỐI AI SERVER - FIX NGAY

## Triệu chứng
```
❌ Lỗi kết nối Máy chủ AI Mống mắt (HTTP 0)
   Failed to connect to 127.0.0.1 port 8000
```

---

## ✅ CÁCH FIX (30 giây)

### Bước 1: Double-click file này
```
📁 d:\Chinh_AI\GiangLT_AI\START_SYSTEM.bat
```

### Bước 2: Đợi 10 giây
Sẽ xuất hiện:
- ✅ Window "AI Mock Server - Iris" (port 8000)
- ✅ Window "Web Admin - Iris" (port 3000)
- ✅ Browser tự động mở http://127.0.0.1:3000

### Bước 3: Verify
Mở link này trong browser:
```
http://127.0.0.1:8000/health
```

**Kết quả đúng:**
```json
{"status":"healthy","service":"Iris AI Mock v2.0 (PHP)"}
```

### Bước 4: Thử lại enrollment
- Quay lại trang enrollment
- Refresh (F5)
- Click "📷 Chụp Mẫu 1"

---

## 🔥 Nếu vẫn lỗi

### Option A: Chạy riêng từng server

**Terminal 1 - AI Server:**
```bash
cd d:\Chinh_AI\GiangLT_AI
START_AI_SERVER.bat
```

**Terminal 2 - Web Admin:**
```bash
cd d:\Chinh_AI\GiangLT_AI
START_WEB_ADMIN.bat
```

### Option B: Kiểm tra port conflict
```bash
# Check port 8000 có ai đang dùng không
netstat -ano | findstr :8000

# Nếu có → kill process
taskkill /PID <số_PID> /F
```

### Option C: Test thủ công
```bash
cd d:\Chinh_AI\GiangLT_AI
php test_ai_connection.php
```

Nếu pass 3 tests → AI server OK!

---

## 📖 Tài liệu chi tiết

- **Setup Guide**: `QUICK_START.md`
- **Troubleshooting**: `FIX_CONNECTION_ERROR.md`
- **Test Cases**: `TEST_CHECKLIST.md`

---

## ☎️ Tóm tắt

**TL;DR**: Double-click `START_SYSTEM.bat` → đợi 10s → done!

Nếu vẫn lỗi → đọc `FIX_CONNECTION_ERROR.md`
