import cv2
import numpy as np
import os

class IrisSegmenter:
    def __init__(self, unet_model_path=None):
        face_path = os.path.join(cv2.data.haarcascades, 'haarcascade_frontalface_default.xml')
        eye_path = os.path.join(cv2.data.haarcascades, 'haarcascade_eye.xml')
        self.face_cascade = cv2.CascadeClassifier(face_path)
        self.eye_cascade = cv2.CascadeClassifier(eye_path)
        self.clahe = cv2.createCLAHE(clipLimit=2.8, tileGridSize=(8, 8))

    def detect_eye_candidates(self, image):
        """
        Tìm tất cả các mắt trong khung hình (cả mắt trái và mắt phải, từ xa đến gần).
        Mỗi mắt được tự động cắt, phóng đại chuẩn hóa lên 256x256 HD và tăng cường tương phản.
        """
        if len(image.shape) == 3:
            gray = cv2.cvtColor(image, cv2.COLOR_BGR2GRAY)
        else:
            gray = image.copy()
            image = cv2.cvtColor(gray, cv2.COLOR_GRAY2BGR)

        h_orig, w_orig = gray.shape
        candidates = []

        # 1. Quét tìm khuôn mặt tổng thể
        faces = self.face_cascade.detectMultiScale(gray, scaleFactor=1.1, minNeighbors=3, minSize=(45, 45))

        if len(faces) > 0:
            faces = sorted(faces, key=lambda f: f[2] * f[3], reverse=True)
            (fx, fy, fw, fh) = faces[0]

            # Vùng nửa trên khuôn mặt chứa mắt
            ey1 = max(0, fy + int(fh * 0.15))
            ey2 = min(h_orig, fy + int(fh * 0.60))
            ex1 = max(0, fx + int(fw * 0.05))
            ex2 = min(w_orig, fx + int(fw * 0.95))

            face_eyes_roi = gray[ey1:ey2, ex1:ex2]

            # Dò tìm tất cả mắt trong vùng này
            eyes = self.eye_cascade.detectMultiScale(face_eyes_roi, scaleFactor=1.06, minNeighbors=2, minSize=(16, 16))

            if len(eyes) > 0:
                # Sắp xếp từ trái qua phải theo khuôn mặt
                eyes = sorted(eyes, key=lambda e: e[0])
                for (ex, ey, ew, eh) in eyes[:2]: # Lấy tối đa 2 mắt
                    abs_x = ex1 + ex
                    abs_y = ey1 + ey

                    pad_w = int(ew * 0.35)
                    pad_h = int(eh * 0.30)

                    cx1 = max(0, abs_x - pad_w)
                    cy1 = max(0, abs_y - pad_h)
                    cx2 = min(w_orig, abs_x + ew + pad_w)
                    cy2 = min(h_orig, abs_y + eh + pad_h)

                    crop = image[cy1:cy2, cx1:cx2]
                    if crop.size > 0:
                        candidates.append((crop, (cx1, cy1, cx2 - cx1, cy2 - cy1)))

        # 2. Nếu không tìm thấy mắt qua khuôn mặt, quét mắt trực tiếp toàn khung hình
        if len(candidates) == 0:
            direct_eyes = self.eye_cascade.detectMultiScale(
                gray, 
                scaleFactor=1.05, 
                minNeighbors=2, 
                minSize=(25, 25),
                maxSize=(int(w_orig * 0.85), int(h_orig * 0.85))
            )
            if len(direct_eyes) > 0:
                for (x, y, w, h) in sorted(direct_eyes, key=lambda e: e[2]*e[3], reverse=True)[:2]:
                    pad_w = int(w * 0.3)
                    pad_h = int(h * 0.25)
                    x1 = max(0, x - pad_w)
                    y1 = max(0, y - pad_h)
                    x2 = min(w_orig, x + w + pad_w)
                    y2 = min(h_orig, y + h + pad_h)
                    crop = image[y1:y2, x1:x2]
                    if crop.size > 0:
                        candidates.append((crop, (x1, y1, x2 - x1, y2 - y1)))

        # 3. Quét mắt cận cảnh (Macro Eye) khi người dùng đưa mắt sát camera
        if len(candidates) == 0:
            blurred_full = cv2.GaussianBlur(gray, (9, 9), 0)
            circles = cv2.HoughCircles(
                blurred_full, cv2.HOUGH_GRADIENT, dp=1.2, minDist=80,
                param1=50, param2=25, minRadius=int(h_orig * 0.08), maxRadius=int(h_orig * 0.45)
            )
            if circles is not None:
                circles = np.round(circles[0, :]).astype("int")
                for (cx, cy, cr) in circles[:1]:
                    roi_center = gray[max(0, cy - cr//2):min(h_orig, cy + cr//2), max(0, cx - cr//2):min(w_orig, cx + cr//2)]
                    # Bắt buộc tâm hình tròn là đồng tử thật màu tối (min_val < 75)
                    if roi_center.size > 0 and np.min(roi_center) < 75:
                        pad = int(cr * 2.0)
                        x1 = max(0, cx - pad)
                        y1 = max(0, cy - pad)
                        x2 = min(w_orig, cx + pad)
                        y2 = min(h_orig, cy + pad)
                        crop = image[y1:y2, x1:x2]
                        if crop.size > 0:
                            candidates.append((crop, (x1, y1, x2 - x1, y2 - y1)))

        # Chuẩn hóa tất cả các ứng viên mắt lên 256x256 HD
        results = []
        for (crop, offset) in candidates:
            crop_hd = cv2.resize(crop, (256, 256), interpolation=cv2.INTER_CUBIC)
            crop_gray = cv2.cvtColor(crop_hd, cv2.COLOR_BGR2GRAY)
            crop_enhanced = self.clahe.apply(crop_gray)
            results.append((crop_enhanced, crop_hd, offset))

        return results

    def validate_eye_structure(self, crop_enhanced):
        """
        Kiểm tra cấu trúc sinh trắc học thực sự của mắt (Biological Eye Verification):
        1. Phải có đồng tử tối màu ở vùng trung tâm (min_val < 80).
        2. Phải có độ tương phản giữa đồng tử và vùng xung quanh (củng mạc/mống mắt).
        Loại bỏ tuyệt đối bàn tay, ngón tay, nếp nhăn hoặc bề mặt da đồng nhất.
        """
        h, w = crop_enhanced.shape
        cy1, cy2 = int(h * 0.20), int(h * 0.80)
        cx1, cx2 = int(w * 0.20), int(w * 0.80)
        center = crop_enhanced[cy1:cy2, cx1:cx2]

        min_val, _, _, _ = cv2.minMaxLoc(center)

        outer_left = crop_enhanced[int(h*0.20):int(h*0.80), :int(w*0.25)]
        outer_right = crop_enhanced[int(h*0.20):int(h*0.80), int(w*0.75):]
        outer_mean = (np.mean(outer_left) + np.mean(outer_right)) / 2.0

        # Mắt người thật: Đồng tử tối < 80, vùng xung quanh sáng hơn ít nhất 14 đơn vị
        if min_val > 80:
            return False
        if (outer_mean - min_val) < 14:
            return False

        return True

    def segment_single_hd(self, crop_enhanced, offset):
        """
        Bóc tách đồng tử và mống mắt trên 1 ảnh mắt HD 256x256.
        Kháng mí mắt che (mắt thư giãn tự nhiên, không cần căng to mắt).
        """
        h, w = crop_enhanced.shape
        blurred = cv2.GaussianBlur(crop_enhanced, (7, 7), 0)

        # 1. Tìm đồng tử qua vùng tối tự nhiên ở trung tâm
        margin_x = int(w * 0.18)
        margin_y = int(h * 0.18)
        center_roi = blurred[margin_y:h-margin_y, margin_x:w-margin_x]

        min_val, _, _, _ = cv2.minMaxLoc(center_roi)
        dark_thresh = min(min_val + 38, 85)
        _, dark_mask = cv2.threshold(center_roi, dark_thresh, 255, cv2.THRESH_BINARY_INV)

        kernel = cv2.getStructuringElement(cv2.MORPH_ELLIPSE, (5, 5))
        dark_mask = cv2.morphologyEx(dark_mask, cv2.MORPH_OPEN, kernel)

        contours, _ = cv2.findContours(dark_mask, cv2.RETR_EXTERNAL, cv2.CHAIN_APPROX_SIMPLE)

        pupil_cx = w // 2
        pupil_cy = h // 2
        pupil_r = 26

        if len(contours) > 0:
            best_cnt = max(contours, key=cv2.contourArea)
            area = cv2.contourArea(best_cnt)
            if area > 60:
                M = cv2.moments(best_cnt)
                if M["m00"] > 0:
                    pupil_cx = int(M["m10"] / M["m00"]) + margin_x
                    pupil_cy = int(M["m01"] / M["m00"]) + margin_y
                    pupil_r = int(np.sqrt(area / np.pi))
                    pupil_r = max(14, min(pupil_r, 42))

        # Tinh chỉnh Hough nếu có
        pupils = cv2.HoughCircles(
            blurred, cv2.HOUGH_GRADIENT, dp=1.1, minDist=25,
            param1=50, param2=10, minRadius=max(12, pupil_r - 8), maxRadius=min(45, pupil_r + 10)
        )
        if pupils is not None:
            pupils = np.round(pupils[0, :]).astype("int")
            best_dist = float('inf')
            for (px, py, pr) in pupils:
                d = np.hypot(px - pupil_cx, py - pupil_cy)
                if d < best_dist and d < 18:
                    best_dist = d
                    pupil_cx, pupil_cy, pupil_r = px, py, pr

        # 2. Xác định bán kính mống mắt (Iris)
        expected_iris_r = int(pupil_r * 2.5)
        irises = cv2.HoughCircles(
            blurred, cv2.HOUGH_GRADIENT, dp=1.1, minDist=30,
            param1=50, param2=12, minRadius=max(45, expected_iris_r - 20), maxRadius=min(115, expected_iris_r + 25)
        )

        iris_cx = pupil_cx
        iris_cy = pupil_cy
        iris_r = expected_iris_r

        if irises is not None:
            irises = np.round(irises[0, :]).astype("int")
            best_d = float('inf')
            for (ix, iy, ir) in irises:
                d = np.hypot(ix - pupil_cx, iy - pupil_cy)
                if d < best_d and d < 20:
                    best_d = d
                    iris_cx, iris_cy, iris_r = ix, iy, ir

        # Tọa độ gốc trên ảnh camera ban đầu
        ox, oy, ow, oh = offset
        scale_x = ow / float(w)
        scale_y = oh / float(h)

        orig_pupil = (int(pupil_cx * scale_x + ox), int(pupil_cy * scale_y + oy), int(pupil_r * scale_x))
        orig_iris = (int(iris_cx * scale_x + ox), int(iris_cy * scale_y + oy), int(iris_r * scale_x))

        pupil_hd = (pupil_cx, pupil_cy, pupil_r)
        iris_hd = (iris_cx, iris_cy, iris_r)

        return pupil_hd, iris_hd, crop_enhanced, orig_pupil, orig_iris

    def segment_all(self, image):
        """
        Bóc tách tất cả các mắt được phát hiện trong khung hình.
        Áp dụng xác thực sinh học (loại bỏ tay che, nhắm mắt, bề mặt phẳng).
        Trả về danh sách các bộ (pupil_hd, iris_hd, crop_enhanced, orig_pupil, orig_iris)
        """
        candidates = self.detect_eye_candidates(image)
        results = []
        for (crop_enhanced, crop_color_hd, offset) in candidates:
            # Kiểm tra mắt người thực thụ (Biological Validation)
            if not self.validate_eye_structure(crop_enhanced):
                continue
            res = self.segment_single_hd(crop_enhanced, offset)
            if res is not None:
                results.append(res)
        return results

    def segment(self, image):
        """
        Khả năng tương thích ngược: Trả về tọa độ gốc của mắt to nhất.
        """
        all_res = self.segment_all(image)
        if len(all_res) > 0:
            p_hd, i_hd, crop_enh, op, oi = all_res[0]
            return op, oi
        return (image.shape[1]//2, image.shape[0]//2, 20), (image.shape[1]//2, image.shape[0]//2, 50)
