<?php
/**
 * API endpoint для получения списка доступных групп
 * Используется iOS приложением
 */
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

require_once __DIR__ . '/../config/database.php';

try {
    $stmt = $pdo->query("SELECT DISTINCT group_name FROM schedule_all WHERE group_name IS NOT NULL AND group_name != '' ORDER BY group_name");
    $groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo json_encode([
        'success' => true,
        'groups' => $groups
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'groups' => []
    ], JSON_UNESCAPED_UNICODE);
}
?>

