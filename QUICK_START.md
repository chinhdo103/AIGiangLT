# Iris Recognition Access Control System - QUICK START

## Tình trạng hiện tại
✅ **Mock AI Server (PHP)** - Thay thế Python FastAPI  
✅ **Enrollment UI Fixed** - `enroll_multi.php` có đầy đủ header/footer  
✅ **Dashboard Pages Fixed** - `live_monitor.php`, `access_control.php` có layout hoàn chỉnh  
✅ **Hybrid Matching** - 0.65 CNN + 0.35 Gabor weights

## Khởi động hệ thống

### 1. Start Mock AI Server (Port 8000)
```bash
cd d:\Chinh_AI\GiangLT_AI
php -S 127.0.0.1:8000 ai_mock_server.php
```

### 2. Start Web Admin Server (Port 80 hoặc 3000)
```bash
# Option 1: XAMPP/WAMP - đặt web_admin vào htdocs/
# Option 2: PHP built-in server
cd web_admin
php -S localhost:3000
```

### 3. Truy cập Web Admin
- URL: `http://localhost/web_admin/` hoặc `http://localhost:3000/`
- Login: admin / admin (default)

## Test Enrollment Flow

1. **Tạo nhân viên mới**: Employees → Add New Employee
2. **Đăng ký mống mắt**: Click "Enroll Iris" → Webcam sẽ bật
3. **Chụp 3 mẫu**: Chụp 3 lần, hệ thống tự đánh giá chất lượng
4. **Kiểm tra Live Monitor**: Vào Live Monitor → xem logs thời gian thực

## Các endpoint AI Mock Server

- `GET /health` - Health check
- `POST /api/enroll_multi` - Đăng ký template (3 mẫu/người)
- `POST /api/verify_hybrid` - Xác thực CNN+Gabor hybrid
- `GET /api/stats?action=recent_logs` - Logs 24h
- `GET /api/stats` - Chart data

## Troubleshooting

### Camera không bật
- Kiểm tra quyền truy cập camera trong browser
- Chrome: Settings → Privacy → Camera → Allow

### AI Server lỗi
```bash
# Kiểm tra port 8000 có đang dùng không
netstat -an | findstr :8000

# Restart server
taskkill /F /IM php.exe
php -S 127.0.0.1:8000 ai_mock_server.php
```

### Database lỗi
- Import SQL: `db_schema/iris_recognition_v2.sql`
- Check config: `web_admin/config.php`

## Architecture

```
[Browser/Camera] 
    ↓ WebRTC getUserMedia
[enroll_multi.php] 
    ↓ fetch POST multipart/form-data
[ai_mock_server.php:8000] 
    ↓ Mock CNN+Gabor extraction
[Session Storage - In-memory DB]
    ↓ Template matching
[MySQL - Access Logs]
```

## Tính năng Mock Server

- ✅ Enroll 3 templates per employee
- ✅ Quality scoring (15-24 range)
- ✅ Hybrid matching với cosine similarity
- ✅ Liveness detection giả lập
- ✅ 24h chart data với random logs
- ✅ CORS enabled cho cross-origin requests
- ✅ Session-based in-memory storage

## Next Steps

1. ✅ Mock server hoàn tất
2. ✅ UI enrollment hoàn tất
3. ⏳ Test end-to-end flow
4. ⏳ Deploy production với Python FastAPI real
5. ⏳ Train CNN model với real iris dataset

---
**Last Updated**: 2026-10-24  
**Status**: Ready for testing
