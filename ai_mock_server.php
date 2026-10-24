<?php
/**
 * Mock AI Service Server - PHP Version (No Python/Node Required)
 * Run: php -S 127.0.0.1:8000 ai_mock_server.php
 */
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
session_start();
if (!isset($_SESSION['mock_db'])) $_SESSION['mock_db'] = [];
$uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$method = $_SERVER['REQUEST_METHOD'];

function mockFeatures() {
    $cnn = array_map(fn() => 0.3 + mt_rand(0,500)/1000, range(1,1280));
    $gabor = array_map(fn() => 0.3 + mt_rand(0,500)/1000, range(1,256));
    $norm = fn($v) => array_map(fn($x)=>$x/(sqrt(array_sum(array_map(fn($y)=>$y*$y,$v)))+1e-9), $v);
    return ['cnn'=>$norm($cnn), 'gabor'=>$norm($gabor)];
}
function cosineSim($a,$b){
    $dot=0; foreach($a as $i=>$x){ $dot += $x*($b[$i]??0); }
    $na=sqrt(array_sum(array_map(fn($x)=>$x*$x,$a)));
    $nb=sqrt(array_sum(array_map(fn($x)=>$x*$x,$b)));
    return $dot/($na*$nb+1e-9);
}

// GET /health
if ($uri==='/health' && $method==='GET') {
    echo json_encode(['status'=>'healthy','service'=>'Iris AI Mock v2.0 (PHP)','timestamp'=>date('c')]);
    exit;
}

// POST /api/enroll_multi
if ($uri==='/api/enroll_multi' && $method==='POST') {
    $empId = $_POST['emp_id'] ?? '0';
    $idx = $_POST['template_index'] ?? '1';
    $quality = 18 + mt_rand(0,600)/100;
    $f = mockFeatures();
    if (!isset($_SESSION['mock_db'][$empId])) $_SESSION['mock_db'][$empId]=[];
    $_SESSION['mock_db'][$empId][] = ['idx'=>$idx,'cnn'=>$f['cnn'],'gabor'=>$f['gabor'],'q'=>$quality];
    echo json_encode([
        'success'=>true,'emp_id'=>$empId,'template_index'=>$idx,
        'cnn_vector'=>$f['cnn'],'gabor_vector'=>$f['gabor'],
        'quality_score'=>$quality,'status'=>$quality>15?'ACTIVE':'REJECTED'
    ]);
    exit;
}


// POST /api/verify | /api/verify_hybrid
if (($uri==='/api/verify' || $uri==='/api/verify_hybrid') && $method==='POST') {
    $qf = mockFeatures();
    $bestScore=0; $bestMatch=null; $sc=['cnn_score'=>0,'gabor_score'=>0,'weighted_hybrid'=>0];
    foreach ($_SESSION['mock_db'] as $eid=>$tpls) {
        foreach ($tpls as $t) {
            $cs=cosineSim($qf['cnn'],$t['cnn']);
            $gs=cosineSim($qf['gabor'],$t['gabor']);
            $hy=0.65*$cs+0.35*$gs;
            if($hy>$bestScore){ $bestScore=$hy; $bestMatch=['emp_id'=>$eid,'template_index'=>$t['idx']]; $sc=['cnn_score'=>$cs,'gabor_score'=>$gs,'weighted_hybrid'=>$hy]; }
        }
    }
    $m=$bestScore>0.72;
    echo json_encode([
        'success'=>true,'match'=>$m,'matched'=>$m,'confidence'=>round($bestScore*100,1),
        'user_id'=>$bestMatch?$bestMatch['emp_id']:null,'best_match'=>$bestMatch,
        'door_status'=>$m?'UNLOCKED':'LOCKED',
        'message'=>$m?"Truy cập NV {$bestMatch['emp_id']}":"Từ chối ".round($bestScore*100)."%",
        'scores'=>$sc,'threshold'=>0.72,
        'pipeline_details'=>[
            'resolution'=>'640x480',
            'segmentation'=>['time_ms'=>45,'pupil'=>[320,240,100],'iris'=>[320,240,150]],
            'normalization'=>['size'=>'512x64','time_ms'=>12],
            'extraction'=>['vector_len'=>1536,'time_ms'=>160],
            'matching'=>['best_score'=>$bestScore,'threshold'=>0.72,'time_ms'=>4,'cnn_score'=>$sc['cnn_score'],'gabor_score'=>$sc['gabor_score'],'weighted_hybrid'=>$sc['weighted_hybrid']],
            'liveness'=>['result'=>true,'reason'=>'Mắt người thật','time_ms'=>30]
        ]
    ]);
    exit;
}

// GET /api/stats
if ($uri==='/api/stats' && $method==='GET') {
    $a = $_GET['action'] ?? 'stats';
    if ($a==='recent_logs') {
        $logs=[
            ['logged_at'=>date('Y-m-d H:i:s'),'emp_id'=>1,'emp_name'=>'Nguyễn Văn A','lab_name'=>'Lab A','access_granted'=>1,'similarity_score'=>0.85],
            ['logged_at'=>date('Y-m-d H:i:s',time()-60),'emp_id'=>2,'emp_name'=>'Trần Thị B','lab_name'=>'Lab B','access_granted'=>1,'similarity_score'=>0.91],
            ['logged_at'=>date('Y-m-d H:i:s',time()-120),'emp_id'=>99,'emp_name'=>'Unknown','lab_name'=>'Lab C','access_granted'=>0,'similarity_score'=>0.45]
        ];
        echo json_encode(['logs'=>$logs]);
        exit;
    }
    $labels=array_map(fn($i)=>sprintf('%02d:00',$i),range(0,23));
    $granted=array_map(fn()=>mt_rand(2,20),$labels);
    $denied=array_map(fn()=>mt_rand(0,3),$labels);
    echo json_encode(['labels'=>$labels,'granted'=>$granted,'denied'=>$denied]);
    exit;
}

http_response_code(404);
echo json_encode(['error'=>'Endpoint not found','path'=>$uri]);
