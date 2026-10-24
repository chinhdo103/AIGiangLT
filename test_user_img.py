import cv2
import sys
import os

sys.path.append("ai_service")
from core.segmentation import IrisSegmenter

seg = IrisSegmenter()
user_img_path = r"C:/Users/congc/.gemini/antigravity/brain/17204124-ab96-4dc9-974c-e7502ea85bd0/.user_uploaded/media_1792813168785_5f6fc6a4.png"
img = cv2.imread(user_img_path)

if img is None:
    print("Cannot read image")
    sys.exit(1)

print(f"User image size: {img.shape}")
candidates = seg.detect_eye_candidates(img)
print(f"Detected {len(candidates)} eye candidates.")

debug_img = img.copy()
for i, (crop_enh, crop_hd, offset) in enumerate(candidates):
    x, y, w, h = offset
    cv2.rectangle(debug_img, (x, y), (x+w, y+h), (0, 255, 0), 2)
    cv2.putText(debug_img, f"Eye {i+1}", (x, y-5), cv2.FONT_HERSHEY_SIMPLEX, 0.7, (0, 255, 0), 2)

os.makedirs("ai_service/test_results", exist_ok=True)
cv2.imwrite("ai_service/test_results/user_debug_boxes.jpg", debug_img)
print("Saved debug boxes to ai_service/test_results/user_debug_boxes.jpg")

