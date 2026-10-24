# TEST CHECKLIST - Iris Recognition System

## Pre-Test Setup
- [ ] MySQL running với database `iris_recognition_v2`
- [ ] PHP CLI installed và trong PATH
- [ ] Chrome/Edge browser với camera access
- [ ] Ports 8000 và 3000 available

## Test 1: Server Startup
```bash
# Run:
START_SYSTEM.bat

# Expected:
- 2 CMD windows open (AI Mock + Web Admin)
- Browser opens to http://127.0.0.1:3000
- AI health check: http://127.0.0.1:8000/health returns {"status":"healthy"}
```

**Result**: ✅ / ❌

---

## Test 2: Login
```
URL: http://127.0.0.1:3000/login.php
User: admin
Pass: admin
```

**Expected**: Dashboard loads with sidebar navigation

**Result**: ✅ / ❌

---

## Test 3: Dashboard Pages Load
- [ ] `index.php` - Dashboard với stats cards
- [ ] `employees.php` - Employee list table
- [ ] `live_monitor.php` - Real-time monitoring (charts + status cards)
- [ ] `access_control.php` - Access logs với filters
- [ ] `lab_manager.php` - Lab management

**Result**: ✅ / ❌

---

## Test 4: Enrollment Flow (Critical)
### 4.1 Navigate to Enrollment
```
employees.php → Click "Enroll Iris" trên bất kỳ nhân viên
```

**Expected**: 
- `enroll_multi.php?emp_id=X` loads
- Stepper shows: Mẫu 1 → Mẫu 2 → Hoàn Tất
- Camera starts automatically
- Scanner overlay hiển thị (blue circle pulse)

**Result**: ✅ / ❌

### 4.2 Capture Template 1
```
Click "📷 Chụp Mẫu 1"
```

**Expected**:
- Button text → "⏳ Xử lý..."
- POST request to `127.0.0.1:8000/api/enroll_multi`
- Response: `{"success":true, "quality_score":18-24, "status":"ACTIVE"}`
- Preview thumb appears (120x90px)
- Stepper: Mẫu 1 ✓ (green check)
- Button text → "📷 Chụp Mẫu 2"

**Result**: ✅ / ❌

### 4.3 Capture Templates 2-3
Repeat cho Mẫu 2 và Mẫu 3

**Expected**:
- 3 preview thumbs visible
- After Mẫu 3: Button text → "💾 Đang lưu..."
- POST to `enrollment_handler.php`
- Success message: "🎉 Đăng ký thành công!"
- Auto redirect to `employees.php` sau 2s

**Result**: ✅ / ❌

---

## Test 5: AI Endpoints (Manual cURL)
### 5.1 Health Check
```bash
curl http://127.0.0.1:8000/health
```

**Expected**: `{"status":"healthy","service":"Iris AI Mock v2.0 (PHP)"}`

**Result**: ✅ / ❌

### 5.2 Stats Endpoint
```bash
curl "http://127.0.0.1:8000/api/stats"
```

**Expected**: JSON với `labels` (24h), `granted`, `denied` arrays

**Result**: ✅ / ❌

### 5.3 Recent Logs
```bash
curl "http://127.0.0.1:8000/api/stats?action=recent_logs"
```

**Expected**: `{"logs":[...]}` với 3 mock entries

**Result**: ✅ / ❌

---

## Test 6: Error Handling
### 6.1 AI Server Down
```bash
# Stop AI server
taskkill /FI "WINDOWTITLE eq AI Mock*" /F

# Try enrollment
```

**Expected**: 
- Error message: "❌ Lỗi AI service: ... Kiểm tra ai_mock_server.js đang chạy."
- Button re-enabled cho retry

**Result**: ✅ / ❌

### 6.2 Camera Blocked
```
Chrome → Site Settings → Camera → Block
Reload enroll_multi.php
```

**Expected**: 
- Status message: "Không truy cập được camera: ..."
- No white screen / unhandled exception

**Result**: ✅ / ❌

---

## Test 7: Live Monitor Real-time
### 7.1 Initial Load
```
live_monitor.php
```

**Expected**:
- 4 status cards (Camera, AI, Queue, Response Time)
- AI Status: "🟢 Online" or "Kiểm Tra..."
- Chart renders với 24h data
- Recent Logs section (từ mock API)

**Result**: ✅ / ❌

### 7.2 Polling
```
Wait 3 seconds (auto-refresh interval)
```

**Expected**:
- Chart updates
- Logs auto-prepend (newest first)
- Max 50 log entries

**Result**: ✅ / ❌

---

## Test 8: Database Integration
### 8.1 Check Templates Saved
```sql
SELECT * FROM iris_templates WHERE emp_id = X ORDER BY template_index;
```

**Expected**: 3 rows với `cnn_vector`, `gabor_vector` populated

**Result**: ✅ / ❌

### 8.2 Check Access Logs
```sql
SELECT * FROM access_logs ORDER BY logged_at DESC LIMIT 10;
```

**Expected**: Logs từ verification attempts

**Result**: ✅ / ❌

---

## Test 9: Verification (If implemented)
### 9.1 Mock Verify Request
```bash
curl -X POST http://127.0.0.1:8000/api/verify_hybrid \
  -F "file=@test_iris.jpg"
```

**Expected**: 
```json
{
  "success": true,
  "match": true/false,
  "confidence": 0-100,
  "user_id": "X",
  "scores": {"cnn_score": X, "gabor_score": X, "weighted_hybrid": X}
}
```

**Result**: ✅ / ❌

---

## Test 10: Browser Compatibility
- [ ] Chrome 90+ (primary)
- [ ] Edge 90+
- [ ] Firefox 88+ (WebRTC support)

**Result**: ✅ / ❌

---

## Performance Benchmarks
- [ ] AI enroll response < 500ms
- [ ] Page load < 2s
- [ ] Camera start < 1s
- [ ] Template capture + process < 1s

**Result**: ✅ / ❌

---

## Known Issues / Blockers
1. **Node.js not installed** → ✅ Solved: PHP mock server
2. **Python not in PATH** → ✅ Solved: PHP mock server  
3. **Session storage volatile** → ⚠️ Templates lost on server restart (OK for testing)

---

## Summary
- **Total Tests**: 10 categories, ~25 checks
- **Passed**: ___
- **Failed**: ___
- **Blocked**: ___

**Overall Status**: 🟢 Ready / 🟡 Needs fixes / 🔴 Blocked

**Tester**: _______________  
**Date**: 2026-10-24  
**Notes**: 
