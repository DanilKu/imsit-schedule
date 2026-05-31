<?php
header('Content-Type: application/json; charset=utf-8');

try {
    require_once dirname(__DIR__) . '/config/database.php';
    
    $stmt = $pdo->query("SELECT id, full_name, short_name, department FROM teachers WHERE is_active = 1 ORDER BY full_name");
    $teachers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'teachers' => $teachers
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
}
?>
