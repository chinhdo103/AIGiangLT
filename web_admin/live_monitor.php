<?php
require_once 'includes/header.php';
require_once 'auth_guard.php';
?>
<!-- Live Monitor Dashboard -->
<div class="container-fluid mt-4">
    <div class="row mb-4">
        <div class="col">
            <h3>Giám Sát Thời Gian Thực</h3>
        </div>
    </div>
    
    <!-- Status Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center" style="border: 1px solid #22c55e;">
                <div class="card-body">
                    <div style="font-size: 2rem">🟢</div>
                    <h6>Camera Feed</h6>
                    <span id="camera-status">Hoạt Động</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border: 1px solid #38BDF8;">
                <div class="card-body">
                    <div style="font-size: 2rem">🤖</div>
                    <h6>AI Service</h6>
                    <span id="ai-status">Kiểm Tra...</span>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border: 1px solid #6366f1;">
                <div class="card-body">
                    <div style="font-size: 2rem">📊</div>
                    <h6>Hàng Đợi AI</h6>
                    <h3 id="ai-queue">0</h3>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center" style="border: 1px solid #f59e0b;">
                <div class="card-body">
                    <div style="font-size: 2rem">⏱️</div>
                    <h6>Thời Gian Phản Hồi</h6>
                    <h3 id="response-time">0ms</h3>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Chart 24h -->
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">Lịch Sử Truy Cập (24h)</div>
                <div class="card-body">
                    <canvas id="access-chart" height="100"></canvas>
                </div>
            </div>
        </div>
        <div class="col-lg-4">
            <div class="card">
                <div class="card-header">Match Rate Realtime</div>
                <div class="card-body">
                    <canvas id="match-gauge"></canvas>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Latest Logs -->
    <div class="row mt-4">
        <div class="col">
            <div class="card">
                <div class="card-header">Log Truy Cập Gần Nhất</div>
                <div class="card-body">
                    <div id="live-logs" style="height: 300px; overflow-y: auto; font-family: monospace; font-size: 0.85rem; background: #0a0f1e; padding: 12px; border-radius: 8px;">
                        <div class="log-entry text-success">[SYSTEM] Live Monitor đã khởi động...</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
// AI service status check
async function checkAIService() {
    try {
        const start = Date.now();
        const res = await fetch('http://localhost:8000/health');
        const ms = Date.now() - start;
        document.getElementById('ai-status').textContent = 'Hoạt Động';
        document.getElementById('response-time').textContent = ms + 'ms';
    } catch {
        document.getElementById('ai-status').textContent = 'Offline';
        document.getElementById('ai-status').style.color = '#ef4444';
    }
}
checkAIService();
setInterval(checkAIService, 5000);

// Load 24h chart from DB
async function loadAccessChart() {
    const res = await fetch('api_get_stats.php?range=24h');
    const data = await res.json();
    
    new Chart(document.getElementById('access-chart'), {
        type: 'line',
        data: {
            labels: data.labels,
            datasets: [
                {
                    label: 'Cho Phép',
                    data: data.granted,
                    borderColor: '#22c55e',
                    backgroundColor: 'rgba(34, 197, 94, 0.1)',
                    fill: true
                },
                {
                    label: 'Từ Chối',
                    data: data.denied,
                    borderColor: '#ef4444',
                    backgroundColor: 'rgba(239, 68, 68, 0.1)',
                    fill: true
                }
            ]
        },
        options: {
            responsive: true,
            scales: {
                y: { beginAtZero: true }
            }
        }
    });
}
loadAccessChart();

// Polling live logs every 3s
async function pollLogs() {
    const res = await fetch('api_get_stats.php?action=recent_logs');
    const data = await res.json();
    const container = document.getElementById('live-logs');
    
    if (data.logs) {
        data.logs.slice(0, 5).forEach(log => {
            const color = log.access_granted ? '#22c55e' : '#ef4444';
            const entry = document.createElement('div');
            entry.className = 'log-entry';
            entry.style.color = color;
            entry.innerHTML = `[${log.logged_at}] ${log.access_granted ? 'GRANTED' : 'DENIED'} - ${log.emp_name} - Score: ${(log.similarity_score * 100).toFixed(1)}%`;
            container.insertBefore(entry, container.firstChild);
        });
        
        while (container.children.length > 50) container.removeChild(container.lastChild);
    }
}
setInterval(pollLogs, 3000);
pollLogs();
</script>
<?php require_once 'includes/footer.php'; ?>
