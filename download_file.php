<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверяем, не запущена ли уже сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'config/auth.php';

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Получение информации о текущем пользователе
$currentUser = getCurrentUser();

if (!$currentUser) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Получение ID файла
$file_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$file_id) {
    header('Location: client_dashboard.php');
    exit;
}

try {
    // Получение информации о файле с проверкой доступа
    $sql = "SELECT f.*, o.client_name 
            FROM files f 
            JOIN orders o ON f.order_id = o.id 
            WHERE f.id = :file_id AND o.client_name = :client_name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'file_id' => $file_id,
        'client_name' => $currentUser['client_name']
    ]);
    $file = $stmt->fetch();
    
    if (!$file) {
        header('Location: client_dashboard.php');
        exit;
    }
    
    // Проверка существования файла
    $filepath = $file['file_path'];
    if (!file_exists($filepath)) {
        header('Location: client_dashboard.php');
        exit;
    }
    
    // Получение информации о файле
    $filesize = filesize($filepath);
    $filename = $file['filename'];
    
    // Определение MIME-типа
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $filepath);
    finfo_close($finfo);
    
    // Установка заголовков для скачивания
    header('Content-Type: ' . $mime_type);
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . $filesize);
    header('Cache-Control: no-cache, must-revalidate');
    header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');
    
    // Отправка файла
    readfile($filepath);
    exit;
    
} catch (PDOException $e) {
    header('Location: client_dashboard.php');
    exit;
}
?> 