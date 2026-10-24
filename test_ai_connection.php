<?php
/**
 * Test AI Mock Server Connection
 * Usage: php test_ai_connection.php
 */

echo "===========================================\n";
echo " AI Mock Server Connection Test\n";
echo "===========================================\n\n";

$AI_URL = 'http://127.0.0.1:8000';

// Test 1: Health Check
echo "[TEST 1] Health Check Endpoint\n";
echo "URL: {$AI_URL}/health\n";

$ch = curl_init("{$AI_URL}/health");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ FAILED: {$error}\n";
    echo "\n💡 Fix: Run START_AI_SERVER.bat first!\n";
    exit(1);
}

if ($httpCode === 200) {
    echo "✅ PASSED: HTTP {$httpCode}\n";
    echo "Response: {$response}\n\n";
} else {
    echo "❌ FAILED: HTTP {$httpCode}\n";
    exit(1);
}

// Test 2: Mock Enrollment
echo "[TEST 2] Mock Enrollment Request\n";
echo "URL: {$AI_URL}/api/enroll_multi\n";

$postData = [
    'emp_id' => '999',
    'template_index' => '1'
];

$ch = curl_init("{$AI_URL}/api/enroll_multi");
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if ($data['success'] ?? false) {
        echo "✅ PASSED: Enrollment successful\n";
        echo "Quality Score: {$data['quality_score']}\n";
        echo "Status: {$data['status']}\n\n";
    } else {
        echo "❌ FAILED: Invalid response\n";
        exit(1);
    }
} else {
    echo "❌ FAILED: HTTP {$httpCode}\n";
    exit(1);
}

// Test 3: Stats Endpoint
echo "[TEST 3] Stats Endpoint\n";
echo "URL: {$AI_URL}/api/stats\n";

$ch = curl_init("{$AI_URL}/api/stats");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $data = json_decode($response, true);
    if (isset($data['labels']) && count($data['labels']) === 24) {
        echo "✅ PASSED: 24h stats retrieved\n";
        echo "Sample: {$data['labels'][0]} - Granted: {$data['granted'][0]}\n\n";
    } else {
        echo "❌ FAILED: Invalid stats format\n";
        exit(1);
    }
} else {
    echo "❌ FAILED: HTTP {$httpCode}\n";
    exit(1);
}

echo "===========================================\n";
echo " ✅ ALL TESTS PASSED\n";
echo "===========================================\n";
echo "\nAI Mock Server is ready!\n";
echo "You can now use enrollment at:\n";
echo "http://127.0.0.1:3000/web_admin/enroll_multi.php\n";
