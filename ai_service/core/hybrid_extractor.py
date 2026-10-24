"""
Hybrid Feature Extractor: MobileNetV2 (CNN) + Gabor Wavelets
Kết hợp deep learning với handcrafted features để tăng độ chính xác
"""

import torch
import torch.nn as nn
import torchvision.models as models
import torchvision.transforms as transforms
import numpy as np
import cv2

class HybridIrisFeatureExtractor:
    def __init__(self, gabor_scales=4, gabor_orientations=8):
        """
        Hybrid Feature Extraction: CNN + Gabor
        - CNN: 1280-D deep features
        - Gabor: 256-D texture features (32 filters x 2 stats)
        """
        # 1. MobileNetV2 CNN
        weights = models.MobileNet_V2_Weights.DEFAULT
        self.model = models.mobilenet_v2(weights=weights)
        self.model.classifier = nn.Identity()
        self.model.eval()
        
        self.preprocess = transforms.Compose([
            transforms.ToPILImage(),
            transforms.Resize((224, 224)),
            transforms.ToTensor(),
            transforms.Normalize(mean=[0.485, 0.456, 0.406], std=[0.229, 0.224, 0.225]),
        ])
        
        # 2. Gabor Filters
        self.gabor_kernels = self._build_gabor_filters(gabor_scales, gabor_orientations)

    def _build_gabor_filters(self, scales, orientations):
        """Tạo bộ lọc Gabor"""
        filters = []
        ksize = 31
        
        for scale in range(scales):
            sigma = 1.0 + scale * 0.5
            lambd = 10.0 + scale * 5.0
            
            for theta in np.arange(0, np.pi, np.pi / orientations):
                kernel = cv2.getGaborKernel((ksize, ksize), sigma, theta, lambd, 0.5, 0, ktype=cv2.CV_32F)
                kernel /= 1.5 * kernel.sum()
                filters.append(kernel)
        
        return filters

    def extract_gabor_features(self, normalized_iris):
        """Trích xuất Gabor features"""
        if len(normalized_iris.shape) == 3:
            gray = cv2.cvtColor(normalized_iris, cv2.COLOR_BGR2GRAY)
        else:
            gray = normalized_iris
        
        gray = cv2.resize(gray, (128, 128))
        
        features = []
        for kernel in self.gabor_kernels:
            filtered = cv2.filter2D(gray, cv2.CV_8UC3, kernel)
            features.append(filtered.mean())
            features.append(filtered.std())
        
        gabor_vector = np.array(features, dtype=np.float32)
        norm = np.linalg.norm(gabor_vector)
        if norm > 0:
            gabor_vector = gabor_vector / norm
        
        return gabor_vector
    
    def extract_cnn_features(self, normalized_iris):
        """Trích xuất CNN features"""
        if len(normalized_iris.shape) == 2:
            img_color = cv2.cvtColor(normalized_iris, cv2.COLOR_GRAY2RGB)
        else:
            img_color = cv2.cvtColor(normalized_iris, cv2.COLOR_BGR2RGB)

        input_tensor = self.preprocess(img_color)
        input_batch = input_tensor.unsqueeze(0)
        
        with torch.no_grad():
            features = self.model(input_batch)
        
        cnn_vector = features.numpy().flatten()
        norm = np.linalg.norm(cnn_vector)
        if norm > 0:
            cnn_vector = cnn_vector / norm
        
        return cnn_vector

    def extract_features(self, normalized_iris, include_gabor=True):
        """
        Trích xuất tất cả features
        Returns: dict {'cnn': array, 'gabor': array, 'hybrid': array}
        """
        cnn_vector = self.extract_cnn_features(normalized_iris)
        result = {'cnn': cnn_vector}
        
        if include_gabor:
            gabor_vector = self.extract_gabor_features(normalized_iris)
            result['gabor'] = gabor_vector
            result['hybrid'] = np.concatenate([cnn_vector, gabor_vector])
        
        return result

    def match(self, features_1, features_2, threshold=0.72, method='hybrid',
              cnn_weight=0.65, gabor_weight=0.35):
        """
        So khớp thông minh tương thích ngược:
        - Tự động nhận diện định dạng dict {'cnn', 'gabor', 'hybrid'} hoặc numpy array 1280-D thuần.
        - Kháng lỗi lệch kích thước (shape mismatch).
        - Kết hợp weighted hybrid nếu cả 2 mẫu đều có Gabor; tự động fallback sang CNN nếu mẫu CSDL chỉ có 1280-D.
        Returns: (is_matched: bool, similarity: float, scores: dict)
        """
        # 1. Trích xuất vector CNN 1280-D từ input 1
        if isinstance(features_1, dict):
            cnn1 = features_1.get('cnn')
            gabor1 = features_1.get('gabor')
        else:
            arr1 = np.asarray(features_1, dtype=np.float32)
            cnn1 = arr1[:1280] if len(arr1) >= 1280 else arr1
            gabor1 = arr1[1280:] if len(arr1) > 1280 else None

        # 2. Trích xuất vector CNN 1280-D từ input 2
        if isinstance(features_2, dict):
            cnn2 = features_2.get('cnn')
            gabor2 = features_2.get('gabor')
        else:
            arr2 = np.asarray(features_2, dtype=np.float32)
            cnn2 = arr2[:1280] if len(arr2) >= 1280 else arr2
            gabor2 = arr2[1280:] if len(arr2) > 1280 else None

        # 3. Tính Cosine similarity cho CNN (1280-D)
        cnn_sim = 0.0
        if cnn1 is not None and cnn2 is not None:
            c1 = np.asarray(cnn1, dtype=np.float32).flatten()
            c2 = np.asarray(cnn2, dtype=np.float32).flatten()
            min_len = min(len(c1), len(c2))
            if min_len > 0:
                c1 = c1[:min_len]
                c2 = c2[:min_len]
                norm1 = np.linalg.norm(c1)
                norm2 = np.linalg.norm(c2)
                if norm1 > 0 and norm2 > 0:
                    cnn_sim = float(np.dot(c1, c2) / (norm1 * norm2))

        # 4. Tính Cosine similarity cho Gabor nếu cả 2 đều có
        gabor_sim = 0.0
        has_gabor = (gabor1 is not None and gabor2 is not None and len(gabor1) > 0 and len(gabor2) > 0)
        if has_gabor:
            g1 = np.asarray(gabor1, dtype=np.float32).flatten()
            g2 = np.asarray(gabor2, dtype=np.float32).flatten()
            min_g_len = min(len(g1), len(g2))
            if min_g_len > 0:
                g1 = g1[:min_g_len]
                g2 = g2[:min_g_len]
                norm_g1 = np.linalg.norm(g1)
                norm_g2 = np.linalg.norm(g2)
                if norm_g1 > 0 and norm_g2 > 0:
                    gabor_sim = float(np.dot(g1, g2) / (norm_g1 * norm_g2))

        # 5. Kết hợp Hybrid nếu có cả hai, hoặc dùng CNN
        if has_gabor and method == 'hybrid':
            similarity = float(cnn_weight * cnn_sim + gabor_weight * gabor_sim)
            used_method = 'hybrid'
        else:
            similarity = float(cnn_sim)
            used_method = 'cnn'

        similarity = max(0.0, min(1.0, similarity))
        is_matched = bool(similarity >= threshold)

        scores = {
            'method': used_method,
            'score': similarity,
            'cnn_score': cnn_sim,
            'gabor_score': gabor_sim,
            'weighted_hybrid': similarity
        }

        return is_matched, similarity, scores
