"""
FastAPI Endpoints for Multi-Template Enrollment & Hybrid Matching
"""
from fastapi import APIRouter, UploadFile, File, HTTPException
from pydantic import BaseModel
import numpy as np
import cv2
import json
from typing import Optional
from .core.segmentation import detect_eye_and_segment
from .core.normalization import normalize_iris
from .core.hybrid_extractor import HybridIrisFeatureExtractor

router = APIRouter()
extractor = HybridIrisFeatureExtractor()

class EnrollRequest(BaseModel):
    emp_id: int
    template_index: int  # 1, 2, or 3

class VerifyRequest(BaseModel):
    emp_id: Optional[int] = None

@router.post("/api/enroll_multi")
async def enroll_multi_template(file: UploadFile = File(...), emp_id: int = 0, template_index: int = 1):
    """Enroll single template (1 of 3)"""
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    
    # Segment & normalize
    try:
        eye_img, pupil, iris = detect_eye_and_segment(img)
        normalized = normalize_iris(eye_img, pupil, iris)
    except:
        raise HTTPException(400, "Eye detection failed")
    
    # Extract features
    features = extractor.extract_features(normalized, include_gabor=True)
    
    # Quality score (simple variance check)
    quality = float(np.std(normalized))
    
    return {
        "success": True,
        "emp_id": emp_id,
        "template_index": template_index,
        "cnn_vector": features['cnn'].tolist(),
        "gabor_vector": features['gabor'].tolist(),
        "quality_score": quality,
        "status": "ACTIVE" if quality > 15 else "REJECTED"
    }

@router.post("/api/verify_hybrid")
async def verify_hybrid(file: UploadFile = File(...)):
    """Verify with hybrid matching against all templates"""
    contents = await file.read()
    nparr = np.frombuffer(contents, np.uint8)
    img = cv2.imdecode(nparr, cv2.IMREAD_COLOR)
    
    try:
        eye_img, pupil, iris = detect_eye_and_segment(img)
        normalized = normalize_iris(eye_img, pupil, iris)
        query_features = extractor.extract_features(normalized, include_gabor=True)
    except:
        return {"success": False, "error": "Eye detection failed"}
    
    # Load all templates from DB (pseudo-code, needs actual DB connection)
    # For now, return structure
    return {
        "success": True,
        "matched": False,
        "best_match": {
            "emp_id": None,
            "template_id": None,
            "cnn_score": 0.0,
            "gabor_score": 0.0,
            "hybrid_score": 0.0
        }
    }
