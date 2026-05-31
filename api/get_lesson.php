<?php
header('Content-Type: application/json');

require_once '../config/database.php';
require_once '../includes/ScheduleManager.php';

try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Метод не поддерживается');
    }
    
    // Получаем ID пары
    $lessonId = $_POST['lesson_id'] ?? null;
    
    if (!$lessonId) {
        throw new Exception('ID пары не указан');
    }
    
    // Проверяем, что ID - число
    if (!is_numeric($lessonId)) {
        throw new Exception('Неверный ID пары');
    }
    
    $lessonId = (int)$lessonId;
    
    // Создаем экземпляр ScheduleManager
    $scheduleManager = new ScheduleManager($pdo);
    
    // Получаем данные пары
    $lesson = $scheduleManager->getLessonById($lessonId);
    
    if (!$lesson) {
        throw new Exception('Пара не найдена');
    }
    
    // Возвращаем успешный ответ
    echo json_encode([
        'success' => true,
        'lesson' => $lesson
    ]);
    
} catch (Exception $e) {
    // Возвращаем ошибку
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
