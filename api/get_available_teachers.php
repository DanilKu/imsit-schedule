<?php
/**
 * API endpoint для получения списка доступных преподавателей
 * Используется iOS приложением
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SELECT DISTINCT teacher_name FROM schedule_all WHERE teacher_name IS NOT NULL AND teacher_name != '' ORDER BY teacher_name");
    $teachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'teachers' => $teachers
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'teachers' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>

