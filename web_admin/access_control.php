<?php
require_once 'includes/header.php';
require_once 'auth_guard.php';
?>
<!-- Real-time Access Control Dashboard -->
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h3>Kiểm Soát Truy Cập Thời Gian Thực</h3>
        </div>
    </div>
    
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body">
                    <h6>Truy Cập Hôm Nay</h6>
                    <h3 id="today-count">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body">
                    <h6>Thất Bại</h6>
                    <h3 id="failed-count">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body">
                    <h6>Hiệu Suất Khớp</h6>
                    <h3 id="match-rate">0%</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card stats-card">
                <div class="card-body">
                    <h6>Độ Tin Cậy Avg</h6>
                    <h3 id="avg-confidence">0%</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Real-time Feed -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5>Hoạt Động Vào Cửa (Live)</h5>
                </div>
                <div class="card-body" style="max-height: 600px; overflow-y: auto;">
                    <table class="table" id="access-feed">
                        <thead>
                            <tr>
                                <th>Thời Gian</th>
                                <th>Nhân Viên</th>
                                <th>Kết Quả</th>
                                <th>Độ Tin Cậy</th>
                                <th>Phòng Lab</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">
                    <h5>Phân Bố Phòng Lab</h5>
                </div>
                <div class="card-body">
                    <canvas id="lab-distribution"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Scoring Breakdown Chart -->
    <div class="row mt-4">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h5>Điểm Số Chi Tiết (CNN vs Gabor vs Hybrid)</h5>
                </div>
                <div class="card-body">
                    <canvas id="score-breakdown"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// WebSocket for live updates
const ws = new WebSocket('ws://localhost:8000/ws/access_log');

ws.onmessage = (e) => {
    const data = JSON.parse(e.data);
    updateAccessFeed(data);
    updateStats(data);
};

function updateAccessFeed(data) {
    const tbody = document.querySelector('#access-feed tbody');
    const row = `<tr class="${data.success ? 'table-success' : 'table-danger'}">
        <td>${new Date().toLocaleTimeString()}</td>
        <td>${data.emp_name}</td>
        <td>${data.success ? '✓ Cho Phép' : '✗ Từ Chối'}</td>
        <td>${(data.hybrid_score * 100).toFixed(1)}%</td>
        <td>${data.lab_name}</td>
    </tr>`;
    tbody.insertAdjacentHTML('afterbegin', row);
    if (tbody.children.length > 50) tbody.removeChild(tbody.lastChild);
}

// Chart: Lab Distribution
new Chart(document.getElementById('lab-distribution'), {
    type: 'doughnut',
    data: {
        labels: ['Lab A', 'Lab B', 'Lab C'],
        datasets: [{
            data: [45, 30, 25],
            backgroundColor: ['#38BDF8', '#6366F1', '#8B5CF6']
        }]
    }
});

// Chart: Score Breakdown
new Chart(document.getElementById('score-breakdown'), {
    type: 'radar',
    data: {
        labels: ['CNN', 'Gabor', 'Hybrid'],
        datasets: [{
            label: 'Đợt gần nhất',
            data: [0.85, 0.78, 0.82],
            backgroundColor: 'rgba(56, 189, 248, 0.2)',
            borderColor: '#38BDF8',
            pointBackgroundColor: '#38BDF8'
        }]
    }
});
</script>

<style>
.stats-card {
    border: 1px solid var(--border-color);
    background: linear-gradient(135deg, rgba(56, 189, 248, 0.1), transparent);
}
.stats-card h3 {
    color: var(--accent-color);
    font-weight: bold;
}
#access-feed table tr:hover {
    background-color: rgba(56, 189, 248, 0.1);
}
</style>
<?php require_once 'includes/footer.php'; ?>
