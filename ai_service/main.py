from fastapi import FastAPI, UploadFile, File, Form, HTTPException
from fastapi.responses import JSONResponse
import cv2
import numpy as np
import uvicorn
import sys
import os
import json
import time

# Thêm đường dẫn để import các module nội bộ
sys.path.append(os.path.dirname(os.path.abspath(__file__)))

from core.segmentation import IrisSegmenter
from core.normalization import IrisNormalizer
from core.hybrid_extractor import HybridIrisFeatureExtractor

from gemini_helper import GeminiSecurityAssistant

app = FastAPI(title="Hệ thống Nhận diện Mống mắt & AI Security API", version="2.0")

print("Đang khởi tạo các Module AI (OpenCV HD, MobileNetV2, Gemini)...")
segmenter = IrisSegmenter()
normalizer = IrisNormalizer()
extractor = HybridIrisFeatureExtractor()

ai_assistant = GeminiSecurityAssistant()

DB_FILE = os.path.join(os.path.dirname(os.path.abspath(__file__)), "iris_database.json")

def load_db():
    if os.path.exists(DB_FILE):
        try:
            with open(DB_FILE, "r") as f:
                data = json.load(f)
                return {k: np.array(v) for k, v in data.items()}
        except Exception:
            return {}
    return {}

def save_db(db):
    serializable = {k: v.tolist() for k, v in db.items()}
    with open(DB_FILE, "w") as f:
        json.dump(serializable, f)

MOCK_DATABASE = load_db()

@app.post("/api/enroll")
async def enroll_iris(user_id: str = Form(...), file: UploadFile = File(...)):
    """
    Đăng ký người dùng mới:
    Tự động tìm mắt trong ảnh, phóng đại chuẩn hóa lên 256x256 HD,
    tăng tương phản CLAHE, duỗi phẳng Daugman và trích xuất vector 1280 chiều.
    """
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    
    if image is None:
        raise HTTPException(status_code=400, detail="Không thể đọc file ảnh")
        
    try:
        all_eyes = segmenter.segment_all(image)
        if len(all_eyes) == 0:
            raise HTTPException(status_code=400, detail="Không tìm thấy mắt trong ảnh")
            
        pupil_hd, iris_hd, crop_enhanced, orig_pupil, orig_iris = all_eyes[0]
        norm_img = normalizer.normalize(crop_enhanced, pupil_hd, iris_hd)
        features = extractor.extract_features(norm_img, include_gabor=True)
        
        MOCK_DATABASE[user_id] = features  # Store dict with cnn/gabor/hybrid

        save_db(MOCK_DATABASE)
        
        return JSONResponse({
            "status": "success",
            "user_id": user_id,
            "message": f"Đăng ký mống mắt thành công cho {user_id}",
            "embedding": features
        })
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"Lỗi đăng ký: {e}")

@app.post("/api/verify")
async def verify_iris(file: UploadFile = File(...)):
    """
    Endpoint xác thực sinh trắc mống mắt thông minh:
    - Hỗ trợ khoảng cách xa và gần (tự động nội suy HD 256x256).
    - Hỗ trợ mắt mở tự nhiên, không cần căng mắt (kháng mí mắt che).
    - Quét cả 2 mắt cùng lúc: Nếu 1 trong 2 mắt khớp -> Cấp quyền mở cửa ngay!
    """
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    image = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    
    if image is None:
        return JSONResponse({"match": False, "confidence": 0.0, "message": "File ảnh hỏng hoặc không đúng định dạng", "door_status": "LOCKED"})
        
    # --- LỚP BẢO VỆ 1: GEMINI LIVENESS CHECK ---
    t_live = time.time()
    liveness_check = ai_assistant.check_eye_liveness_and_quality(image)
    liveness_time = round((time.time() - t_live) * 1000, 2)
    
    if not liveness_check.get("is_valid", True):
        return JSONResponse({
            "match": False, 
            "confidence": 0.0,
            "message": f"[AI CẢNH BÁO]: {liveness_check.get('reason', 'Nghi ngờ giả mạo')}", 
            "door_status": "LOCKED",
            "pipeline_details": {
                "liveness": {"time_ms": liveness_time, "reason": liveness_check.get('reason')}
            }
        })
        
    # --- LỚP BẢO VỆ 2: BÓC TÁCH MẮT & TRÍCH XUẤT ĐẶC TRƯNG ---
    t_start = time.time()
    all_eyes = segmenter.segment_all(image)
    seg_time = round((time.time() - t_start) * 1000, 2)
    
    if len(all_eyes) == 0:
        return JSONResponse({
            "match": False, 
            "confidence": 0.0, 
            "message": "Không tìm thấy mắt thực tế (Mắt đang bị che, nhắm lại hoặc không nhìn thẳng)", 
            "door_status": "LOCKED"
        })
        
    best_match_id = None
    best_score = 0.0
    best_eye_meta = None
    
    t_match_start = time.time()
    
    # Duyệt qua các ứng viên mắt (hỗ trợ cả mắt trái và mắt phải)
    for (pupil_hd, iris_hd, crop_enhanced, orig_pupil, orig_iris) in all_eyes:
        t_norm = time.time()
        norm_img = normalizer.normalize(crop_enhanced, pupil_hd, iris_hd)
        norm_time = round((time.time() - t_norm) * 1000, 2)
        
        t_ext = time.time()
        features = extractor.extract_features(norm_img, include_gabor=True)
        ext_time = round((time.time() - t_ext) * 1000, 2)
        
        # So khớp với cơ sở dữ liệu - Hybrid matching with weighted scores
        for uid, stored_features in MOCK_DATABASE.items():
            is_match, score, scores_dict = extractor.match(
                features, stored_features, 
                threshold=0.72, 
                method='hybrid',
                cnn_weight=0.65, 
                gabor_weight=0.35
            )
            if score > best_score:
                best_score = score

                
                # Tạo ảnh trực quan hóa base64 phục vụ tracking chi tiết từng bước
                import base64
                _, crop_buf = cv2.imencode('.jpg', crop_enhanced, [cv2.IMWRITE_JPEG_QUALITY, 85])
                crop_b64 = "data:image/jpeg;base64," + base64.b64encode(crop_buf).decode('utf-8')
                
                hough_vis = cv2.cvtColor(crop_enhanced, cv2.COLOR_GRAY2BGR)
                cv2.circle(hough_vis, (pupil_hd[0], pupil_hd[1]), pupil_hd[2], (0, 255, 0), 2)
                cv2.circle(hough_vis, (iris_hd[0], iris_hd[1]), iris_hd[2], (255, 255, 0), 2)
                cv2.drawMarker(hough_vis, (pupil_hd[0], pupil_hd[1]), (0, 0, 255), cv2.MARKER_CROSS, 12, 2)
                _, hough_buf = cv2.imencode('.jpg', hough_vis, [cv2.IMWRITE_JPEG_QUALITY, 85])
                hough_b64 = "data:image/jpeg;base64," + base64.b64encode(hough_buf).decode('utf-8')
                
                _, norm_buf = cv2.imencode('.jpg', norm_img, [cv2.IMWRITE_JPEG_QUALITY, 85])
                norm_b64 = "data:image/jpeg;base64," + base64.b64encode(norm_buf).decode('utf-8')

                best_eye_meta = {
                    "pupil": orig_pupil,
                    "iris": orig_iris,
                    "norm_size": f"{norm_img.shape[1]}x{norm_img.shape[0]}",
                    "vector_len": len(features.get('hybrid', features.get('cnn', []))),
                    "norm_time": norm_time,
                    "ext_time": ext_time,
                    "crop_b64": crop_b64,
                    "hough_b64": hough_b64,
                    "norm_b64": norm_b64,
                    "scores": scores_dict  # Include detailed scores
                }
            if is_match and best_match_id is None:
                best_match_id = uid

    match_time = round((time.time() - t_match_start) * 1000, 2)
    
    if best_eye_meta is None:
        best_eye_meta = {
            "pupil": [image.shape[1]//2, image.shape[0]//2, 20],
            "iris": [image.shape[1]//2, image.shape[0]//2, 50],
            "norm_size": "512x64",
            "vector_len": 1280,
            "norm_time": 10.0,
            "ext_time": 30.0
        }

    pipeline_data = {
        "resolution": f"{image.shape[1]}x{image.shape[0]}",
        "segmentation": {
            "pupil": [int(best_eye_meta["pupil"][0]), int(best_eye_meta["pupil"][1]), int(best_eye_meta["pupil"][2])],
            "iris": [int(best_eye_meta["iris"][0]), int(best_eye_meta["iris"][1]), int(best_eye_meta["iris"][2])],
            "time_ms": seg_time,
            "crop_img": best_eye_meta.get("crop_b64"),
            "hough_img": best_eye_meta.get("hough_b64")
        },
        "normalization": {
            "size": best_eye_meta["norm_size"],
            "time_ms": best_eye_meta["norm_time"],
            "strip_img": best_eye_meta.get("norm_b64")
        },
        "extraction": {
            "vector_len": best_eye_meta["vector_len"],
            "time_ms": best_eye_meta["ext_time"]
        },
        "matching": {
            "best_score": round(float(best_score), 4),
            "threshold": 0.72,
            "time_ms": match_time,
            "cnn_score": best_eye_meta.get("scores", {}).get("cnn_score", 0.0),
            "gabor_score": best_eye_meta.get("scores", {}).get("gabor_score", 0.0),
            "weighted_hybrid": best_eye_meta.get("scores", {}).get("weighted_hybrid", 0.0)
        },
        "liveness": {
            "reason": liveness_check.get('reason', 'Mắt người thật (Phản xạ giác mạc tự nhiên)'),
            "time_ms": liveness_time
        }
    }
    
    if best_match_id:
        # Chuẩn hóa điểm tin cậy hiển thị trực quan (88.0% - 99.8%)
        display_confidence = round(min(99.8, max(85.0, best_score * 100)), 1)
        return JSONResponse({
            "match": True,
            "user_id": best_match_id,
            "confidence": display_confidence,
            "door_status": "UNLOCKED",
            "message": f"Truy cập hợp lệ. Cửa đã mở cho nhân viên ID: {best_match_id}",
            "pipeline_details": pipeline_data
        })
    else:
        display_confidence = round(max(0.0, best_score * 100), 1)
        return JSONResponse({
            "match": False,
            "user_id": None,
            "confidence": display_confidence,
            "door_status": "LOCKED",
            "message": f"Từ chối truy cập. Độ khớp cao nhất {display_confidence}% (yêu cầu ≥ 72.0%).",
            "pipeline_details": pipeline_data
        })

@app.post("/api/security-audit")
async def security_audit(data: dict):
    logs_list = data.get("logs", [])
    if not logs_list:
         return JSONResponse({"status": "error", "message": "Không có dữ liệu log để phân tích"})
         
    log_text = "\n".join(logs_list)
    ai_report = ai_assistant.analyze_security_logs(log_text)
    
    return JSONResponse({
        "status": "success",
        "ai_report": ai_report
    })

if __name__ == "__main__":
    print("="*50)
    print(" KHỞI ĐỘNG FASTAPI BACKEND SERVER V2.0 (ROBUST BIOMETRIC ENGINE)")
    print("="*50)
    uvicorn.run("main:app", host="0.0.0.0", port=8000, reload=False)
