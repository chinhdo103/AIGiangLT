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

    def match(self, features_1, features_2, threshold=0.70, method='hybrid',
              cnn_weight=0.65, gabor_weight=0.35):
        """
        So khớp với weighted hybrid scoring
        Returns: (is_matched: bool, similarity: float, scores: dict)
        """
        # Xử lý input
        if isinstance(features_1, dict):
            vec1 = features_1.get(method, features_1.get('cnn'))
        else:
            vec1 = features_1
        
        if isinstance(features_2, dict):
            vec2 = features_2.get(method, features_2.get('cnn'))
        else:
            vec2 = features_2
        
        # Cosine similarity
        similarity = float(np.dot(vec1, vec2))
        is_matched = bool(similarity >= threshold)
        
        scores = {'method': method, 'score': similarity}
        
        # Tính riêng từng phương pháp nếu có
        if isinstance(features_1, dict) and isinstance(features_2, dict):
            if 'cnn' in features_1 and 'cnn' in features_2:
                cnn_sim = float(np.dot(features_1['cnn'], features_2['cnn']))
                scores['cnn_score'] = cnn_sim
            
            if 'gabor' in features_1 and 'gabor' in features_2:
                gabor_sim = float(np.dot(features_1['gabor'], features_2['gabor']))
                scores['gabor_score'] = gabor_sim
            
            # Weighted hybrid
            if 'cnn_score' in scores and 'gabor_score' in scores:
                scores['weighted_hybrid'] = cnn_weight * scores['cnn_score'] + gabor_weight * scores['gabor_score']
        
        return is_matched, similarity, scores
