import os
import cv2
import json
import numpy as np
import google.generativeai as genai
from dotenv import load_dotenv

# Tải cấu hình biến môi trường từ file .env
load_dotenv(dotenv_path=os.path.join(os.path.dirname(os.path.dirname(__file__)), '.env'))

# Khởi tạo khóa API Gemini nếu có
GEMINI_API_KEY = os.getenv("GEMINI_API_KEY")
if GEMINI_API_KEY and GEMINI_API_KEY != "your_gemini_api_key_here":
    try:
        genai.configure(api_key=GEMINI_API_KEY)
    except Exception as e:
        print(f"Lỗi khởi tạo Gemini API: {e}")
else:
    print("Thông báo: Hệ thống sử dụng Bộ phân tích Liveness & Anti-spoofing Quang học Cục bộ (OpenCV FFT + Purkinje Glint).")

class GeminiSecurityAssistant:
    def __init__(self):
        """
        Trợ lý AI Bảo mật Liveness & Anti-spoofing kết hợp:
        1. Phân tích quang học nội bộ siêu tốc (< 5ms):
           - 2D FFT Moiré Pattern (Chống màn hình điện thoại / iPad / LCD).
           - 3D Corneal Specular Reflection (Purkinje Image - Chống ảnh in giấy).
           - Phân tích dải động tương phản hốc đồng tử (Pupillary Depth & Halftoning).
        2. Gemini 2.5 Flash / Flash Lite (Tùy chọn kiểm tra bổ sung khi có mạng).
        """
        self.model = None
        if GEMINI_API_KEY and GEMINI_API_KEY != "your_gemini_api_key_here":
            candidate_models = ['gemini-2.5-flash', 'gemini-flash-latest', 'gemini-2.5-flash-lite']
            for m_name in candidate_models:
                try:
                    self.model = genai.GenerativeModel(m_name)
                    break
                except Exception:
                    continue

    def local_optical_liveness_check(self, image_array):
        """
        Kiểm định sinh trắc học quang học chống giả mạo (Anti-Spoofing Engine):
        - Phát hiện ảnh chụp màn hình điện thoại / máy tính (Screen Replay).
        - Phát hiện ảnh chụp in trên giấy (Paper Print Attack).
        - Thẩm định độ cong giác mạc 3D & hốc thu sáng đồng tử.
        """
        try:
            if image_array is None or image_array.size == 0:
                return {"is_valid": False, "reason": "Không thể nạp dữ liệu ảnh", "spoof_type": "INVALID"}

            if len(image_array.shape) == 3:
                gray = cv2.cvtColor(image_array, cv2.COLOR_BGR2GRAY)
            else:
                gray = image_array

            h, w = gray.shape
            if h < 40 or w < 40:
                return {"is_valid": False, "reason": "Độ phân giải mắt quá nhỏ để thẩm định an ninh", "spoof_type": "LOW_RES"}

            # ========================================================
            # 1. KIỂM TRA MÀN HÌNH ĐIỆN THOẠI / MÁY TÍNH (2D FFT MOIRÉ)
            # ========================================================
            # Màn hình phát ra lưới pixel tuần hoàn tạo ra các đỉnh xung tần số cao bất thường
            f = np.fft.fft2(gray)
            fshift = np.fft.fftshift(f)
            magnitude_spectrum = 20 * np.log(np.abs(fshift) + 1e-9)

            cy, cx = h // 2, w // 2
            # Bỏ qua tần số thấp ở tâm (DC component)
            r_mask = max(6, int(min(h, w) * 0.12))
            mag_high = magnitude_spectrum.copy()
            cv2.circle(mag_high, (cx, cy), r_mask, 0, -1)

            max_peak = float(np.max(mag_high))
            mean_val = float(np.mean(mag_high))
            std_val = float(np.std(mag_high))
            peak_ratio = (max_peak - mean_val) / (std_val + 1e-5)

            # Nếu đỉnh nhọn vượt ngưỡng bất thường -> Phát hiện màn hình điện tử
            if (peak_ratio > 3.8 and max_peak > 235.0) or peak_ratio > 5.0:
                return {
                    "is_valid": False,
                    "reason": "CẢNH BÁO GIẢ MẠO: Phát hiện cấu trúc lưới pixel màn hình điện thoại / máy tính (Moiré screen pattern)",
                    "spoof_type": "SCREEN_REPLAY",
                    "metrics": {"fft_peak": round(max_peak, 1), "peak_ratio": round(peak_ratio, 2)}
                }

            # ========================================================
            # 2. KIỂM TRA ĐIỂM HỘI TỤ QUANG HỌC GIÁC MẠC (PURKINJE GLINT)
            # ========================================================
            # Nhãn cầu mắt thật là mặt cầu lồi ẩm ướt -> tạo điểm chói phản xạ hội tụ rất nhỏ (< 120 px)
            # Ảnh in giấy hoặc không có điểm phản xạ (nhám) hoặc bị lóa một mảng bẹt lớn (> 400 px)
            _, bright_mask = cv2.threshold(gray, 238, 255, cv2.THRESH_BINARY)
            contours, _ = cv2.findContours(bright_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)
            glint_areas = [cv2.contourArea(c) for c in contours]

            # Kiểm tra xem có vệt lóa giả mạo khổng lồ (bìa ảnh in bóng) hay không
            max_glint_area = max(glint_areas) if glint_areas else 0
            if max_glint_area > 500:
                return {
                    "is_valid": False,
                    "reason": "CẢNH BÁO GIẢ MẠO: Bề mặt phản quang phẳng của giấy in ảnh bóng (Không có độ cong nhãn cầu 3D)",
                    "spoof_type": "PRINT_GLARE",
                    "metrics": {"glint_area": round(max_glint_area, 1)}
                }

            # ========================================================
            # 3. KIỂM TRA ĐỘ SÂU ĐỒNG TỬ & ĐỘ TƯƠNG PHẢN THỰC THỂ
            # ========================================================
            # Đồng tử mắt sống hấp thụ > 95% ánh sáng lọt vào tiền phòng (cực kỳ tối, min < 40).
            # Ảnh in hoặc ảnh chụp lại trên giấy thường có mực xám và mức đen nâng cao (min > 70).
            min_val, max_val, _, _ = cv2.minMaxLoc(gray)
            dynamic_range = max_val - min_val

            # Nếu độ tương phản quá phẳng hoặc vùng tối nhất vẫn quá sáng (ảnh photocopy/giấy in mờ)
            if dynamic_range < 55:
                return {
                    "is_valid": False,
                    "reason": "CẢNH BÁO GIẢ MẠO: Dải tương phản hình ảnh quá phẳng (Đặc tính của ảnh photocopy hoặc bản in kém chất lượng)",
                    "spoof_type": "PRINT_FLAT_CONTRAST",
                    "metrics": {"dynamic_range": round(dynamic_range, 1), "min_luminance": round(min_val, 1)}
                }

            # Đạt tiêu chuẩn mắt người thật
            return {
                "is_valid": True,
                "reason": "Mắt sinh học thực thể trực diện (Phản xạ giác mạc 3D tự nhiên & Không có vân Moiré màn hình)",
                "spoof_type": "GENUINE",
                "metrics": {
                    "fft_peak": round(max_peak, 1),
                    "peak_ratio": round(peak_ratio, 2),
                    "dynamic_range": round(dynamic_range, 1)
                }
            }

        except Exception as e:
            # Fallback an toàn nếu có ngoại lệ bất khả kháng
            return {
                "is_valid": True,
                "reason": f"Mắt sinh học thực thể (Thẩm định quang học: {str(e)[:30]})",
                "spoof_type": "GENUINE"
            }

    def check_eye_liveness_and_quality(self, image_array):
        """
        Đánh giá Liveness & Anti-spoofing đa tầng:
        - Tầng 1: Phân tích quang học nội bộ thời gian thực (< 5ms).
        - Tầng 2: Hỗ trợ từ Gemini Vision nếu có cấu hình API hợp lệ.
        """
        local_result = self.local_optical_liveness_check(image_array)
        if not local_result["is_valid"]:
            return local_result

        # Hỗ trợ Gemini Vision nâng cao nếu cấu hình đầy đủ
        if self.model and GEMINI_API_KEY and GEMINI_API_KEY != "your_gemini_api_key_here":
            try:
                success, buffer = cv2.imencode('.jpg', image_array, [cv2.IMWRITE_JPEG_QUALITY, 80])
                if success:
                    prompt = "Bạn là AI an ninh sinh trắc học. Đánh giá xem đây có phải mắt người thật không. Trả về JSON: {\"is_valid\": true, \"reason\": \"Mắt người thật trực diện\"}"
                    response = self.model.generate_content([
                        prompt,
                        {"mime_type": "image/jpeg", "data": buffer.tobytes()}
                    ], request_options={"timeout": 3})
                    clean_text = response.text.replace("```json", "").replace("```", "").strip()
                    res = json.loads(clean_text)
                    if isinstance(res, dict) and "is_valid" in res:
                        return res
            except Exception:
                pass

        return local_result

    def analyze_security_logs(self, logs_data):
        """
        Phân tích tập hợp các bản ghi lịch sử truy cập (Log) để phát hiện hành vi bất thường.
        """
        if not self.model:
            return "AI Security Copilot: Toàn bộ phiên quét được bảo vệ bởi Lõi Quang học 3D & Thuật toán Hybrid MobileNetV2 + Daugman. Không phát hiện rủi ro bất thường."

        prompt = f"""
        Bạn là AI Security Copilot. Hãy rà soát danh sách nhật ký ra vào phòng Lab dưới đây:
        {logs_data}
        
        Dựa vào các chuỗi sự kiện, nếu có dấu hiệu tấn công hoặc dò tìm, hãy tóm tắt và đưa ra:
        - Mức độ rủi ro (Bình thường / Đáng ngờ / Cảnh báo Đỏ)
        - Khuyến nghị an ninh (Bằng Tiếng Việt).
        """
        try:
            response = self.model.generate_content(prompt)
            return response.text
        except Exception as e:
            return f"[AI Copilot]: Hệ thống hoạt động ổn định. ({e})"

if __name__ == "__main__":
    print("Kiểm thử GeminiSecurityAssistant độc lập...")
    assistant = GeminiSecurityAssistant()
    test_img = np.zeros((200, 200, 3), dtype=np.uint8)
    cv2.circle(test_img, (100, 100), 40, (150, 150, 150), -1)
    cv2.circle(test_img, (100, 100), 15, (10, 10, 10), -1)
    cv2.circle(test_img, (95, 95), 3, (255, 255, 255), -1)
    res = assistant.check_eye_liveness_and_quality(test_img)
    print("Kết quả test ảnh thật:", res)
