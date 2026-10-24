<?php
/**
 * Script chạy Migration Database v2.0
 * Nâng cấp lên hệ thống Multi-Template
 */

require_once __DIR__ . '/config.php';

echo "================================\n";
echo "IRIS LAB SECURITY - DATABASE MIGRATION v2.0\n";
echo "================================\n\n";

try {
    // Đọc file SQL migration
    $migrationFile = __DIR__ . '/../database/migration_v2_multi_template.sql';
    
    if (!file_exists($migrationFile)) {
        die("ERROR: Migration file not found: $migrationFile\n");
    }
    
    $sql = file_get_contents($migrationFile);
    
    if (empty($sql)) {
        die("ERROR: Migration file is empty\n");
    }
    
    echo "✓ Migration file loaded: " . basename($migrationFile) . "\n";
    echo "✓ File size: " . number_format(strlen($sql)) . " bytes\n";
    echo "✓ Connected to database: " . DB_NAME . "\n\n";
    
    echo "Starting migration...\n\n";
    
    // Tắt foreign key check tạm thời
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // Xóa comment dòng (-- ...) trước khi tách câu lệnh
    $cleanSql = preg_replace('/^\s*--.*$/m', '', $sql);
    // Xóa comment khối /* ... */
    $cleanSql = preg_replace('#/\*.*?\*/#s', '', $cleanSql);
    
    // Tách các câu SQL riêng lẻ (delimiter ;)
    // Nhưng chú ý các giá trị string cũng chứa ';' -> dùng regex thông minh hơn
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $len = strlen($cleanSql);
    
    for ($i = 0; $i < $len; $i++) {
        $char = $cleanSql[$i];
        
        if (!$inString && ($char === "'" || $char === '"')) {
            $inString = true;
            $stringChar = $char;
        } elseif ($inString && $char === $stringChar && $cleanSql[$i-1] !== '\\') {
            $inString = false;
        }
        
        if (!$inString && $char === ';') {
            $stmt = trim($current);
            if (!empty($stmt)) {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }
    if (!empty(trim($current))) {
        $statements[] = trim($current);
    }
    
    $executed = 0;
    $skipped = 0;
    
    // Danh sách lỗi có thể bỏ qua (idempotent migration)
    $ignorableErrors = [
        'Duplicate column',
        'Duplicate key',
        'already exists',
        "doesn't exist",
        'Unknown column',
        'Key column',
    ];
    
    // Danh sách statement cần chạy lại sau khi tất cả cột đã tạo
    $deferred = [];
    
    foreach ($statements as $statement) {
        $statement = trim($statement);
        
        // Bỏ qua empty statements
        if (empty($statement)) {
            $skipped++;
            continue;
        }
        
        try {
            $pdo->exec($statement);
            $executed++;
            echo ".";
            
            if ($executed % 25 === 0) {
                echo " $executed\n";
            }
        } catch (PDOException $e) {
            $msg = $e->getMessage();
            $isIgnorable = false;
            foreach ($ignorableErrors as $err) {
                if (strpos($msg, $err) !== false) {
                    $isIgnorable = true;
                    break;
                }
            }
            
            if ($isIgnorable) {
                // Lưu lại để thử lại sau (dependency ordering)
                $deferred[] = $statement;
                $skipped++;
                continue;
            }
            
            echo "\n\n⚠ WARNING: $msg\n";
            echo "Statement: " . substr($statement, 0, 100) . "...\n\n";
        }
    }
    
    // Thử lại các statement bị bỏ qua (sau khi dependencies đã có)
    if (!empty($deferred)) {
        $deferredCount = count($deferred);
        echo "\n\nRetrying $deferredCount deferred statements...\n";
        foreach ($deferred as $statement) {
            try {
                $pdo->exec($statement);
                $executed++;
                echo ".";
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                $fatal = true;
                foreach ($ignorableErrors as $err) {
                    if (strpos($msg, $err) !== false) {
                        $fatal = false;
                        break;
                    }
                }
                if ($fatal) {
                    echo "\n⚠ " . $msg . "\n";
                }
            }
        }
    }
    
    // Tạo triggers (dùng PDO riêng vì cần DELIMITER)
    echo "\n\nCreating triggers...\n";
    $triggers = [
        "CREATE TRIGGER trg_iris_templates_after_insert
         AFTER INSERT ON iris_templates
         FOR EACH ROW
         BEGIN
             UPDATE employees
             SET template_count = (
                 SELECT COUNT(*) FROM iris_templates
                 WHERE emp_id = NEW.emp_id AND status = 'ACTIVE'
             ),
             iris_enrolled = 1
             WHERE emp_id = NEW.emp_id;
         END",
        "CREATE TRIGGER trg_iris_templates_after_delete
         AFTER DELETE ON iris_templates
         FOR EACH ROW
         BEGIN
             UPDATE employees
             SET template_count = (
                 SELECT COUNT(*) FROM iris_templates
                 WHERE emp_id = OLD.emp_id AND status = 'ACTIVE'
             ),
             iris_enrolled = (
                 SELECT COUNT(*) FROM iris_templates
                 WHERE emp_id = OLD.emp_id AND status = 'ACTIVE'
             ) > 0
             WHERE emp_id = OLD.emp_id;
         END"
    ];
    
    foreach ($triggers as $trigger) {
        try {
            $pdo->exec($trigger);
            echo "✓ ";
        } catch (PDOException $e) {
            echo "✗ ";
        }
    }
    echo "\n";
    
    // Bật lại foreign key check
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    echo "\n\n================================\n";
    echo "MIGRATION COMPLETED!\n";
    echo "================================\n";
    echo "✓ Total executed: $executed statements\n";
    echo "✓ Skipped: $skipped statements\n\n";
    
    // Verify migration kết quả
    echo "Verifying database structure...\n\n";
    
    $verify = [
        "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='iris_templates' AND COLUMN_NAME='template_index'" => "iris_templates.template_index",
        "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='iris_templates' AND COLUMN_NAME='gabor_vector'" => "iris_templates.gabor_vector",
        "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME='employees' AND COLUMN_NAME='template_count'" => "employees.template_count",
        "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='enrollment_sessions'" => "table enrollment_sessions",
        "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='lab_permissions'" => "table lab_permissions",
        "SELECT COUNT(*) as c FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_NAME='ai_settings'" => "table ai_settings",
    ];
    
    $allOk = true;
    foreach ($verify as $query => $label) {
        $result = $pdo->query($query)->fetch(PDO::FETCH_ASSOC);
        $exists = $result['c'] > 0;
        echo ($exists ? "✓" : "✗") . " $label: " . ($exists ? "OK" : "MISSING") . "\n";
        if (!$exists) $allOk = false;
    }
    
    echo "\n";
    
    if ($allOk) {
        echo "🎉 MIGRATION SUCCESS - All structures created!\n\n";
        
        // Hiển thị số lượng mẫu
        $stats = $pdo->query("SELECT 
            COUNT(DISTINCT emp_id) as total_employees,
            COUNT(*) as total_templates,
            SUM(CASE WHEN status='ACTIVE' THEN 1 ELSE 0 END) as active_templates
            FROM iris_templates")->fetch(PDO::FETCH_ASSOC);
        
        echo "Current Statistics:\n";
        echo "- Employees: " . $stats['total_employees'] . "\n";
        echo "- Total Templates: " . $stats['total_templates'] . "\n";
        echo "- Active Templates: " . $stats['active_templates'] . "\n\n";
        
        $settings = $pdo->query("SELECT COUNT(*) as c FROM ai_settings")->fetch(PDO::FETCH_ASSOC);
        echo "- AI Settings configured: " . $settings['c'] . " parameters\n\n";
    } else {
        echo "⚠ Some structures are missing. Check logs above.\n\n";
    }
    
} catch (Exception $e) {
    echo "\n\n❌ MIGRATION FAILED!\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . " Line: " . $e->getLine() . "\n\n";
    exit(1);
}
?>
