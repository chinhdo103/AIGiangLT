/**
 * Mock AI Service Server (Node.js - no Python required)
 * Run: node ai_mock_server.js
 */
const http = require('http');
const url = require('url');
const PORT = 8000;
const mockDatabase = {};

function cosineSim(a, b) {
  let dot = 0, nA = 0, nB = 0;
  for (let i = 0; i < a.length; i++) { dot += a[i]*b[i]; nA += a[i]*a[i]; nB += b[i]*b[i]; }
  return dot / (Math.sqrt(nA) * Math.sqrt(nB) + 1e-9);
}

function mockFeatures() {
  const cnn = Array(1280).fill(0).map(() => Math.random() * 0.5 + 0.3);
  const gabor = Array(256).fill(0).map(() => Math.random() * 0.5 + 0.3);
  const norm = v => { const n = Math.sqrt(v.reduce((s,x)=>s+x*x,0)); return v.map(x=>x/n); };
  return { cnn: norm(cnn), gabor: norm(gabor) };
}

const routes = {
  'GET /health': (req, res) => {
    res.end(JSON.stringify({ status: 'healthy', service: 'Iris AI Mock v2.0', ts: new Date().toISOString() }));
  },

  'POST /api/enroll_multi': (req, res, body) => {
    const fd = new URLSearchParams(body);
    const empId = fd.get('emp_id') || '0';
    const idx   = fd.get('template_index') || '1';
    const q     = 18 + Math.random() * 6;
    const f     = mockFeatures();
    if (!mockDatabase[empId]) mockDatabase[empId] = [];
    mockDatabase[empId].push({ idx, ...f, q });
    res.end(JSON.stringify({ success: true, emp_id: empId, template_index: idx,
      cnn_vector: f.cnn, gabor_vector: f.gabor, quality_score: q,
      status: q > 15 ? 'ACTIVE' : 'REJECTED' }));
  },

  'POST /api/verify': (req, res, body) => {
    const qf = mockFeatures();
    let best = 0, meta = null, sc = {};
    for (const [eid, tpls] of Object.entries(mockDatabase)) {
      for (const t of tpls) {
        const cs = cosineSim(qf.cnn, t.cnn);
        const gs = cosineSim(qf.gabor, t.gabor);
        const hs = 0.65*cs + 0.35*gs;
        if (hs > best) { best = hs; meta = { emp_id: eid, template_index: t.idx }; sc = { cnn_score: cs, gabor_score: gs, weighted_hybrid: hs }; }
      }
    }
    const matched = best > 0.72;
    res.end(JSON.stringify({
      match: matched, confidence: Math.round(best*100*10)/10,
      user_id: meta?.emp_id || null, door_status: matched ? 'UNLOCKED' : 'LOCKED',
      message: matched ? `Truy cập hợp lệ - NV ${meta?.emp_id}` : `Từ chối - Score ${Math.round(best*100)}%`,
      pipeline_details: {
        segmentation: { pupil: [320,240,100], iris: [320,240,150], time_ms: 45 },
        normalization: { size: '512x64', time_ms: 12 },
        extraction:   { vector_len: 1536, time_ms: 160 },
        matching:     { best_score: best, threshold: 0.72, time_ms: 4, ...sc },
        liveness:     { reason: 'Mắt người thật', time_ms: 30 }
      }
    }));
  },

  'POST /api/verify_hybrid': (req, res, body) => routes['POST /api/verify'](req, res, body),

  'GET /api/stats': (req, res, _, q) => {
    if (q.action === 'recent_logs') {
      return res.end(JSON.stringify({ logs: [
        { logged_at: new Date().toISOString(), emp_id: 1, emp_name: 'Nguyễn Văn A', lab_name: 'Lab A', access_granted: 1, similarity_score: 0.85 },
        { logged_at: new Date(Date.now()-60000).toISOString(), emp_id: 2, emp_name: 'Trần Thị B', lab_name: 'Lab B', access_granted: 1, similarity_score: 0.91 },
        { logged_at: new Date(Date.now()-120000).toISOString(), emp_id: 99, emp_name: 'Unknown', lab_name: 'Lab C', access_granted: 0, similarity_score: 0.45 }
      ]}));
    }
    const labels = Array.from({length:24},(_,i)=>`${String(i).padStart(2,'0')}:00`);
    res.end(JSON.stringify({
      labels, granted: labels.map(()=>Math.floor(Math.random()*18+2)),
      denied: labels.map(()=>Math.floor(Math.random()*3))
    }));
  }
};

const server = http.createServer((req, res) => {
  res.setHeader('Content-Type', 'application/json');
  res.setHeader('Access-Control-Allow-Origin', '*');
  res.setHeader('Access-Control-Allow-Methods', 'GET,POST,OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
  if (req.method === 'OPTIONS') { res.writeHead(200); res.end(); return; }

  const p = url.parse(req.url, true);
  const key = `${req.method} ${p.pathname}`;
  const handler = routes[key];

  if (!handler) { res.writeHead(404); res.end(JSON.stringify({ error: 'Not found', path: p.pathname })); return; }

  let body = '';
  req.on('data', c => body += c);
  req.on('end', () => { try { handler(req, res, body, p.query); } catch(e) { res.writeHead(500); res.end(JSON.stringify({error: e.message})); }});
});

server.listen(PORT, '127.0.0.1', () => {
  console.log(`\n✅ Mock AI running on http://127.0.0.1:${PORT}`);
  console.log('   GET  /health');
  console.log('   POST /api/enroll_multi');
  console.log('   POST /api/verify | /api/verify_hybrid');
  console.log('   GET  /api/stats\n');
});
server.on('error', e => { console.error('❌', e.message); process.exit(1); });
