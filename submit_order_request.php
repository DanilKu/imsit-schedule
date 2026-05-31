<?php
// Чистая версия API для заявок
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Устанавливаем заголовки СРАЗУ
header('Content-Type: application/json; charset=utf-8');

// Отключаем ВСЕ выводы
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Перехватываем весь вывод
ob_start();

try {
    require_once 'config/database.php';
    require_once 'config/auth.php';
    require_once 'includes/OrderRequest.php';
    
    // Очищаем буфер вывода
    $unwanted_output = ob_get_clean();
    
    // Если есть нежелательный вывод, логируем его
    if (!empty(trim($unwanted_output))) {
        error_log("Unwanted output in submit_order_request: " . $unwanted_output);
    }
    
    if (!isAuthenticated()) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Не авторизован']);
        exit;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'error' => 'Метод не поддерживается']);
        exit;
    }
    
    $currentUser = getCurrentUser();
    if (!$currentUser) {
        throw new Exception('Не удалось получить данные пользователя');
    }
    
    $service = new OrderRequestService($pdo);
    
    $workType = $_POST['work_type'] ?? '';
    $topicNumber = trim($_POST['topic_number'] ?? '');
    $topicDescription = trim($_POST['topic_description'] ?? '');
    
    // Валидация данных
    if (empty($workType)) {
        throw new Exception('Выберите тип работы');
    }
    
    if (empty($topicDescription)) {
        throw new Exception('Опишите тему работы');
    }
    
    $id = $service->createRequest([
        'user_id' => $currentUser['id'],
        'client_name' => $currentUser['client_name'],
        'work_type' => $workType,
        'semester' => 7,
        'topic_number' => $topicNumber,
        'topic_description' => $topicDescription,
    ]);
    
    echo json_encode(['ok' => true, 'id' => $id]);
    
} catch (Exception $e) {
    // Очищаем буфер если есть
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(400);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} catch (PDOException $e) {
    // Очищаем буфер если есть
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => 'Ошибка базы данных']);
}
