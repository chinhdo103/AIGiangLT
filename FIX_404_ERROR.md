# 🔴 FIX LỖI 404: "Lỗi Đăng ký Sinh trắc: Lỗi HTTP từ Server AI: 404"

## ⚠️ Nguyên nhân

Lỗi **404 Not Found** nghĩa là:
- ✅ Server AI **ĐANG CHẠY** (không phải lỗi connection như HTTP 0)
- ❌ Nhưng endpoint `/api/enroll_multi` **KHÔNG TỒN TẠI** trên server đó

**Có thể bạn đang chạy:**
- ❌ Node.js server cũ (`ai_mock_server.js`)
- ❌ Python Flask server cũ (`app.py` trong `ai_service/`)
- ❌ Hoặc server khác không hỗ trợ `/api/enroll_multi`

**Thay vì chạy:**
- ✅ PHP Mock Server (`ai_mock_server.php`) - server mới có đầy đủ endpoints

---

## 🚀 FIX NGAY (3 bước)

### Bước 1: STOP tất cả server cũ

Mở **Task Manager** (Ctrl+Shift+Esc) → tìm và **End Task**:
- `node.exe` (nếu đang chạy)
- `python.exe` hoặc `pythonw.exe` (nếu đang chạy)
- Hoặc đóng tất cả cửa sổ CMD có text "AI Server" / "Mock Server"

### Bước 2: START server PHP mới

**Mở CMD** và chạy:
```bash
cd d:\Chinh_AI\GiangLT_AI
php -S 127.0.0.1:8000 ai_mock_server.php
```

**Hoặc** double-click file:
```
d:\Chinh_AI\GiangLT_AI\START_AI_SERVER.bat
```

**Đợi thấy dòng:**
```
[Sun Oct 24 23:45:12 2026] PHP 8.2.12 Development Server (http://127.0.0.1:8000) started
```

### Bước 3: VERIFY endpoint tồn tại

**Mở trình duyệt**, truy cập:
```
http://127.0.0.1:8000/health
```

**Kết quả đúng:**
```json
{
  "status": "healthy",
  "service": "Iris AI Mock v2.0 (PHP)",
  "timestamp": "2026-10-24T23:45:30+07:00"
}
```

---

## 🧪 Test Enrollment (Tool Debug)

**Mở file test trong trình duyệt:**
```
http://localhost:3000/test_ai_enroll.html
```

**Chạy từng test:**
1. **Test 1: Health Check** → phải **OK**
2. **Test 2: POST Simple** → phải **OK** (quality_score: 18-24)
3. **Test 3: POST with Fake Image** → phải **OK**
4. **Test 4: Real Camera** → phải **OK** (chụp ảnh thật)

**Nếu tất cả test OK** → Enrollment sẽ hoạt động!

---

## 🔍 Kiểm tra Server đang chạy

### Cách 1: Check Port 8000
```bash
netstat -ano | findstr ":8000"
```

**Kết quả mong muốn:**
```
TCP    127.0.0.1:8000    0.0.0.0:0    LISTENING    12345
```

### Cách 2: Test bằng curl
```bash
curl http://127.0.0.1:8000/api/enroll_multi -X POST -d "emp_id=1&template_index=1"
```

**Kết quả đúng:** JSON với `"success":true`, `"quality_score":XX.XX`

**Kết quả SAI:**
```
curl: (7) Failed to connect to 127.0.0.1 port 8000  → Server không chạy
```
```json
{"error":"Endpoint not found","path":"/api/enroll_multi"}  → Chạy sai server
```

---

## ❓ Nếu vẫn lỗi 404

### Trường hợp 1: Browser cache cũ
**Fix:** Hard refresh trong browser
- Chrome/Edge: `Ctrl + Shift + R` hoặc `Ctrl + F5`
- Firefox: `Ctrl + Shift + Del` → Clear cache

### Trường hợp 2: Chạy 2 server cùng lúc
**Fix:** Kiểm tra lại Task Manager, chỉ để **1 process** trên port 8000

### Trường hợp 3: File `ai_mock_server.php` bị lỗi
**Fix:** Tải lại từ backup hoặc verify line 36:
```bash
php -l ai_mock_server.php
```
Phải thấy: `No syntax errors detected`

### Trường hợp 4: Web Admin gọi sai URL
**Fix:** Kiểm tra file `enroll_multi.php` line 36:
```javascript
const AI='http://127.0.0.1:8000'
```
Nếu khác → sửa lại về `127.0.0.1:8000`

---

## ✅ Checklist Hoàn Chỉnh

- [ ] Đóng tất cả server cũ (Node/Python)
- [ ] Chạy `START_AI_SERVER.bat` hoặc `php -S 127.0.0.1:8000 ai_mock_server.php`
- [ ] Thấy dòng "PHP Development Server started"
- [ ] Test `http://127.0.0.1:8000/health` → thấy `"status":"healthy"`
- [ ] Test `http://localhost:3000/test_ai_enroll.html` → Test 1,2,3 đều OK
- [ ] Thử enrollment thật → Không còn lỗi 404

---

## 📞 Vẫn Không Được?

**Debug thông tin:**
1. Chụp ảnh màn hình cửa sổ CMD đang chạy server
2. Chụp ảnh kết quả test từ `test_ai_enroll.html`
3. Mở DevTools (F12) → tab **Network** → chụp request bị lỗi 404
4. Gửi 3 ảnh trên để được hỗ trợ
