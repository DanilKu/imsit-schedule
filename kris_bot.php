<?php
// Админ-панель для управления KrisBot (без авторизации)
require_once 'config/database.php';
date_default_timezone_set('Europe/Moscow');

// Токен бота для отправки сообщений
$BOT_TOKEN = '7663426513:AAHMco1BuG3dUKwks3lNCfT6NFlIMqOB6ck';

// Функция для логирования действий
function logAction($pdo, $action, $details = null, $commandId = null) {
    try {
        // Создаем таблицу, если её нет
        $pdo->exec("CREATE TABLE IF NOT EXISTS `kris_bot_logs` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `action` varchar(50) NOT NULL COMMENT 'Тип действия',
            `user_ip` varchar(45) DEFAULT NULL COMMENT 'IP адрес пользователя',
            `user_agent` text DEFAULT NULL COMMENT 'User Agent браузера',
            `details` text DEFAULT NULL COMMENT 'Детали действия (JSON)',
            `command_id` int(11) DEFAULT NULL COMMENT 'ID команды (если применимо)',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `action` (`action`),
            KEY `command_id` (`command_id`),
            KEY `created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        $userIp = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $pdo->prepare("INSERT INTO kris_bot_logs (action, user_ip, user_agent, details, command_id) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$action, $userIp, $userAgent, $detailsJson, $commandId]);
    } catch (PDOException $e) {
        error_log("Error logging action: " . $e->getMessage());
    }
}

// Функция для загрузки и сохранения фото
function uploadPhoto($file, $commandId = null) {
    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        return null;
    }
    
    // Проверяем тип файла
    $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
    $fileType = $file['type'] ?? mime_content_type($file['tmp_name']);
    
    if (!in_array($fileType, $allowedTypes)) {
        return ['error' => 'Недопустимый тип файла. Разрешены только изображения (JPEG, PNG, GIF, WebP)'];
    }
    
    // Проверяем размер файла (максимум 10 МБ)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['error' => 'Файл слишком большой. Максимальный размер: 10 МБ'];
    }
    
    // Создаем директорию для фото, если её нет
    $uploadDir = __DIR__ . '/uploads/kris_bot/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Генерируем уникальное имя файла
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'cmd_' . ($commandId ?? 'new') . '_' . uniqid() . '_' . time() . '.' . $extension;
    $filePath = $uploadDir . $filename;
    
    // Перемещаем файл
    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        // Возвращаем относительный путь для сохранения в БД
        return 'uploads/kris_bot/' . $filename;
    }
    
    return ['error' => 'Ошибка при сохранении файла'];
}

// Функция для удаления фото
function deletePhoto($photoPath) {
    if ($photoPath && file_exists(__DIR__ . '/' . $photoPath)) {
        @unlink(__DIR__ . '/' . $photoPath);
    }
}

// Функция для отправки сообщения в Telegram
function sendTelegramMessage($chatId, $text) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendMessage";
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML'
    ];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return ['success' => $httpCode === 200, 'response' => json_decode($response, true)];
}

// Обработка скачивания вебхука
if (isset($_GET['download_webhook'])) {
    $webhookFile = __DIR__ . '/api/kris_bot_webhook.php';
    if (file_exists($webhookFile)) {
        header('Content-Type: application/x-php');
        header('Content-Disposition: attachment; filename="kris_bot_webhook.php"');
        header('Content-Length: ' . filesize($webhookFile));
        readfile($webhookFile);
        logAction($pdo, 'download_webhook', ['file' => 'kris_bot_webhook.php']);
        exit;
    }
}

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'add_command':
                    $commandName = trim($_POST['command_name'] ?? '');
                    $commandText = trim($_POST['command_text'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $responseText = trim($_POST['response_text'] ?? '');
                    
                    if (empty($commandName) || empty($commandText) || empty($responseText)) {
                        $error = 'Заполните все обязательные поля';
                        break;
                    }
                    
                    // Проверяем формат команды (должна начинаться с /)
                    if (!preg_match('/^\/[a-zA-Z0-9_]+$/', $commandText)) {
                        $error = 'Команда должна начинаться с / и содержать только буквы, цифры и подчеркивания';
                        break;
                    }
                    
                    // Проверяем, не существует ли уже такая команда
                    $stmt = $pdo->prepare("SELECT id FROM kris_bot_commands WHERE command_text = ?");
                    $stmt->execute([$commandText]);
                    if ($stmt->fetch()) {
                        $error = 'Команда с таким текстом уже существует';
                        break;
                    }
                    
                    // Добавляем команду (сначала без фото, чтобы получить ID)
                    $stmt = $pdo->prepare("INSERT INTO kris_bot_commands (command_name, command_text, description, response_text, is_active) VALUES (?, ?, ?, ?, 1)");
                    $stmt->execute([$commandName, $commandText, $description, $responseText]);
                    $commandId = $pdo->lastInsertId();
                    
                    // Обрабатываем загрузку фото после получения ID
                    $photoPath = null;
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        $uploadResult = uploadPhoto($_FILES['photo'], $commandId);
                        if (isset($uploadResult['error'])) {
                            $error = $uploadResult['error'];
                            // Удаляем команду, если загрузка фото не удалась
                            $stmt = $pdo->prepare("DELETE FROM kris_bot_commands WHERE id = ?");
                            $stmt->execute([$commandId]);
                            break;
                        }
                        $photoPath = $uploadResult;
                        
                        // Обновляем путь к фото в БД
                        $stmt = $pdo->prepare("UPDATE kris_bot_commands SET photo_path = ? WHERE id = ?");
                        $stmt->execute([$photoPath, $commandId]);
                    }
                    
                    // Логируем действие
                    logAction($pdo, 'add_command', [
                        'command_name' => $commandName,
                        'command_text' => $commandText,
                        'description' => $description,
                        'has_photo' => !empty($photoPath)
                    ], $commandId);
                    
                    $success = 'Команда успешно добавлена';
                    break;
                    
                case 'edit_command':
                    $id = (int)($_POST['id'] ?? 0);
                    $commandName = trim($_POST['command_name'] ?? '');
                    $commandText = trim($_POST['command_text'] ?? '');
                    $description = trim($_POST['description'] ?? '');
                    $responseText = trim($_POST['response_text'] ?? '');
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    $deletePhoto = isset($_POST['delete_photo']) ? true : false;
                    
                    if (empty($commandName) || empty($commandText) || empty($responseText)) {
                        $error = 'Заполните все обязательные поля';
                        break;
                    }
                    
                    // Проверяем формат команды
                    if (!preg_match('/^\/[a-zA-Z0-9_]+$/', $commandText)) {
                        $error = 'Команда должна начинаться с / и содержать только буквы, цифры и подчеркивания';
                        break;
                    }
                    
                    // Проверяем, не существует ли уже такая команда (кроме текущей)
                    $stmt = $pdo->prepare("SELECT id FROM kris_bot_commands WHERE command_text = ? AND id != ?");
                    $stmt->execute([$commandText, $id]);
                    if ($stmt->fetch()) {
                        $error = 'Команда с таким текстом уже существует';
                        break;
                    }
                    
                    // Получаем текущий путь к фото
                    $stmt = $pdo->prepare("SELECT photo_path FROM kris_bot_commands WHERE id = ?");
                    $stmt->execute([$id]);
                    $currentPhoto = $stmt->fetchColumn();
                    
                    $photoPath = $currentPhoto;
                    
                    // Удаляем старое фото, если запрошено
                    if ($deletePhoto && $currentPhoto) {
                        deletePhoto($currentPhoto);
                        $photoPath = null;
                    }
                    
                    // Обрабатываем загрузку нового фото
                    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                        // Удаляем старое фото, если оно есть
                        if ($currentPhoto) {
                            deletePhoto($currentPhoto);
                        }
                        
                        $uploadResult = uploadPhoto($_FILES['photo'], $id);
                        if (isset($uploadResult['error'])) {
                            $error = $uploadResult['error'];
                            break;
                        }
                        $photoPath = $uploadResult;
                    }
                    
                    // Обновляем команду
                    $stmt = $pdo->prepare("UPDATE kris_bot_commands SET command_name = ?, command_text = ?, description = ?, response_text = ?, photo_path = ?, is_active = ? WHERE id = ?");
                    $stmt->execute([$commandName, $commandText, $description, $responseText, $photoPath, $isActive, $id]);
                    
                    // Логируем действие
                    logAction($pdo, 'edit_command', [
                        'command_name' => $commandName,
                        'command_text' => $commandText,
                        'description' => $description,
                        'is_active' => $isActive,
                        'photo_changed' => ($photoPath !== $currentPhoto)
                    ], $id);
                    
                    $success = 'Команда успешно обновлена';
                    break;
                    
                case 'delete_command':
                    $id = (int)($_POST['id'] ?? 0);
                    
                    // Получаем информацию о команде перед удалением для лога
                    $stmt = $pdo->prepare("SELECT command_name, command_text, photo_path FROM kris_bot_commands WHERE id = ?");
                    $stmt->execute([$id]);
                    $commandInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Удаляем фото, если оно есть
                    if ($commandInfo && $commandInfo['photo_path']) {
                        deletePhoto($commandInfo['photo_path']);
                    }
                    
                    $stmt = $pdo->prepare("DELETE FROM kris_bot_commands WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Логируем действие
                    if ($commandInfo) {
                        logAction($pdo, 'delete_command', [
                            'command_name' => $commandInfo['command_name'],
                            'command_text' => $commandInfo['command_text']
                        ], $id);
                    }
                    
                    $success = 'Команда успешно удалена';
                    break;
                    
                case 'toggle_active':
                    $id = (int)($_POST['id'] ?? 0);
                    
                    // Получаем текущий статус
                    $stmt = $pdo->prepare("SELECT is_active, command_name, command_text FROM kris_bot_commands WHERE id = ?");
                    $stmt->execute([$id]);
                    $commandInfo = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $stmt = $pdo->prepare("UPDATE kris_bot_commands SET is_active = NOT is_active WHERE id = ?");
                    $stmt->execute([$id]);
                    
                    // Логируем действие
                    if ($commandInfo) {
                        $newStatus = $commandInfo['is_active'] ? 0 : 1;
                        logAction($pdo, 'toggle_active', [
                            'command_name' => $commandInfo['command_name'],
                            'command_text' => $commandInfo['command_text'],
                            'old_status' => $commandInfo['is_active'],
                            'new_status' => $newStatus
                        ], $id);
                    }
                    
                    $success = 'Статус команды изменен';
                    break;
                    
                case 'send_message':
                    $chatId = trim($_POST['chat_id'] ?? '');
                    $message = trim($_POST['message'] ?? '');
                    
                    if (empty($chatId) || empty($message)) {
                        $error = 'Заполните ID чата и сообщение';
                        break;
                    }
                    
                    if (!is_numeric($chatId)) {
                        $error = 'ID чата должен быть числом';
                        break;
                    }
                    
                    $result = sendTelegramMessage($chatId, $message);
                    
                    if ($result['success']) {
                        // Логируем действие
                        logAction($pdo, 'send_message', [
                            'chat_id' => $chatId,
                            'message_length' => strlen($message)
                        ]);
                        $success = 'Сообщение успешно отправлено';
                    } else {
                        $error = 'Ошибка отправки сообщения: ' . ($result['response']['description'] ?? 'Неизвестная ошибка');
                    }
                    break;
            }
        } catch (PDOException $e) {
            $error = 'Ошибка базы данных: ' . $e->getMessage();
        }
    }
}

// Проверяем и добавляем поле photo_path, если его нет
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM kris_bot_commands LIKE 'photo_path'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE `kris_bot_commands` ADD COLUMN `photo_path` varchar(255) DEFAULT NULL COMMENT 'Путь к фото для команды' AFTER `response_text`");
    }
} catch (PDOException $e) {
    // Игнорируем ошибки, если таблица не существует или поле уже есть
}

// Получаем все команды
$commands = [];
try {
    $stmt = $pdo->query("SELECT * FROM kris_bot_commands ORDER BY created_at DESC");
    $commands = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error = 'Ошибка загрузки команд: ' . $e->getMessage();
}

// Получаем команду для редактирования
$editCommand = null;
if (isset($_GET['edit'])) {
    $id = (int)$_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM kris_bot_commands WHERE id = ?");
    $stmt->execute([$id]);
    $editCommand = $stmt->fetch(PDO::FETCH_ASSOC);
}

// Получаем логи действий (последние 50)
$logs = [];
try {
    $stmt = $pdo->query("SELECT * FROM kris_bot_logs ORDER BY created_at DESC LIMIT 50");
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Таблица может не существовать, это нормально
}
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>KrisBot - Управление командами</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
        }
        
        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .admin-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .btn-modern {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-size: 0.875rem;
        }
        
        .btn-modern:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary-modern:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
        }
        
        .btn-danger-modern {
            background: rgba(239, 68, 68, 0.2);
            border-color: rgba(239, 68, 68, 0.4);
        }
        
        .btn-danger-modern:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .input-modern {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 0.75rem;
            border-radius: 8px;
            width: 100%;
            transition: all 0.2s;
        }
        
        .input-modern:focus {
            outline: none;
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .textarea-modern {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            color: #fff;
            padding: 0.75rem;
            border-radius: 8px;
            width: 100%;
            min-height: 120px;
            resize: vertical;
            transition: all 0.2s;
            font-family: inherit;
        }
        
        .textarea-modern:focus {
            outline: none;
            border-color: rgba(102, 126, 234, 0.5);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.4);
            color: #86efac;
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.2);
            border: 1px solid rgba(239, 68, 68, 0.4);
            color: #fca5a5;
        }
        
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .badge-active {
            background: rgba(34, 197, 94, 0.2);
            color: #86efac;
        }
        
        .badge-inactive {
            background: rgba(107, 114, 128, 0.2);
            color: #9ca3af;
        }
        
        .table-modern {
            width: 100%;
            border-collapse: collapse;
        }
        
        .table-modern th,
        .table-modern td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table-modern th {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
        }
        
        .table-modern td {
            color: #fff;
        }
        
        .table-modern tr:hover {
            background: rgba(255, 255, 255, 0.03);
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased">
    <!-- Background gradients -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -right-32 h-[42rem] w-[42rem] rounded-full bg-gradient-to-br from-indigo-500/30 via-fuchsia-500/20 to-emerald-400/20 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-20 h-[38rem] w-[38rem] rounded-full bg-gradient-to-tr from-purple-500/20 via-blue-500/20 to-cyan-400/20 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(255,255,255,0.06),rgba(0,0,0,0)_70%)]"></div>
    </div>

    <header class="sm:px-6 sm:pt-6 pt-4 pr-4 pb-2 pl-4">
        <div class="max-w-[90rem] flex mr-auto ml-auto items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-indigo-500/70 to-fuchsia-500/70 text-white ring-1 ring-white/20 shadow-lg">
                    <i class="fab fa-telegram text-xl"></i>
                </div>
                <div>
                    <h1 class="text-[22px] sm:text-2xl font-semibold tracking-tight">KrisBot - Управление командами</h1>
                    <p class="text-sm text-slate-300">Админ-панель для управления командами Telegram бота</p>
                </div>
            </div>
            <div class="flex gap-2">
                <a href="?download_webhook=1" class="btn-modern" title="Скачать код вебхука">
                    <i class="fas fa-download"></i>
                    Скачать вебхук
                </a>
            </div>
        </div>
    </header>

    <main class="px-4 pb-24 sm:px-6">
        <section class="mx-auto max-w-[90rem] space-y-6">
            <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <!-- Форма добавления/редактирования команды -->
            <div class="admin-card p-5 sm:p-7">
                <div class="flex items-center gap-3 mb-6">
                    <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-emerald-500/70 to-teal-500/70 text-white ring-1 ring-white/20 shadow-lg">
                        <i class="fas fa-plus text-lg"></i>
                    </div>
                    <h2 class="text-xl font-semibold tracking-tight">
                        <?php echo $editCommand ? 'Редактировать команду' : 'Добавить новую команду'; ?>
                    </h2>
                </div>
                
                <form method="POST" enctype="multipart/form-data" class="space-y-4">
                    <input type="hidden" name="action" value="<?php echo $editCommand ? 'edit_command' : 'add_command'; ?>">
                    <?php if ($editCommand): ?>
                        <input type="hidden" name="id" value="<?php echo $editCommand['id']; ?>">
                    <?php endif; ?>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium mb-2 text-slate-300">Название команды *</label>
                            <input type="text" name="command_name" class="input-modern" 
                                   value="<?php echo htmlspecialchars($editCommand['command_name'] ?? ''); ?>" 
                                   placeholder="Например: Погода" required>
                            <p class="text-xs text-slate-400 mt-1">Отображается на кнопке</p>
                        </div>
                        
                        <div>
                            <label class="block text-sm font-medium mb-2 text-slate-300">Текст команды *</label>
                            <input type="text" name="command_text" class="input-modern" 
                                   value="<?php echo htmlspecialchars($editCommand['command_text'] ?? ''); ?>" 
                                   placeholder="/weather" required pattern="/[a-zA-Z0-9_]+">
                            <p class="text-xs text-slate-400 mt-1">Должна начинаться с /</p>
                        </div>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-slate-300">Описание</label>
                        <input type="text" name="description" class="input-modern" 
                               value="<?php echo htmlspecialchars($editCommand['description'] ?? ''); ?>" 
                               placeholder="Краткое описание команды">
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-slate-300">Текст ответа *</label>
                        <textarea name="response_text" class="textarea-modern" 
                                  placeholder="Текст, который бот отправит в ответ на команду" required><?php echo htmlspecialchars($editCommand['response_text'] ?? ''); ?></textarea>
                        <p class="text-xs text-slate-400 mt-1">Поддерживается HTML форматирование. Будет использован как подпись к фото, если фото загружено</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-slate-300">Фото (опционально)</label>
                        <input type="file" name="photo" accept="image/jpeg,image/jpg,image/png,image/gif,image/webp" class="input-modern">
                        <p class="text-xs text-slate-400 mt-1">Максимальный размер: 10 МБ. Форматы: JPEG, PNG, GIF, WebP</p>
                        <?php if ($editCommand && !empty($editCommand['photo_path'])): ?>
                            <div class="mt-3 flex items-center gap-3">
                                <div class="relative">
                                    <img src="<?php echo htmlspecialchars($editCommand['photo_path']); ?>" 
                                         alt="Текущее фото" 
                                         class="max-w-xs max-h-32 rounded-lg border border-slate-600">
                                </div>
                                <label class="flex items-center gap-2 cursor-pointer">
                                    <input type="checkbox" name="delete_photo" value="1">
                                    <span class="text-sm text-slate-300">Удалить текущее фото</span>
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if ($editCommand): ?>
                        <div>
                            <label class="flex items-center gap-2 cursor-pointer">
                                <input type="checkbox" name="is_active" <?php echo $editCommand['is_active'] ? 'checked' : ''; ?>>
                                <span class="text-sm text-slate-300">Активна</span>
                            </label>
                        </div>
                    <?php endif; ?>
                    
                    <div class="flex gap-3">
                        <button type="submit" class="btn-modern btn-primary-modern">
                            <i class="fas fa-save"></i>
                            <?php echo $editCommand ? 'Сохранить изменения' : 'Добавить команду'; ?>
                        </button>
                        
                        <?php if ($editCommand): ?>
                            <a href="kris_bot.php" class="btn-modern">
                                <i class="fas fa-times"></i>
                                Отмена
                            </a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>

            <!-- Форма отправки сообщения -->
            <div class="admin-card p-5 sm:p-7">
                <div class="flex items-center gap-3 mb-6">
                    <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-blue-500/70 to-cyan-500/70 text-white ring-1 ring-white/20 shadow-lg">
                        <i class="fas fa-paper-plane text-lg"></i>
                    </div>
                    <h2 class="text-xl font-semibold tracking-tight">Отправить сообщение в бот</h2>
                </div>
                
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="send_message">
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-slate-300">ID чата *</label>
                        <input type="text" name="chat_id" class="input-modern" 
                               placeholder="Например: 123456789" required>
                        <p class="text-xs text-slate-400 mt-1">Telegram Chat ID получателя</p>
                    </div>
                    
                    <div>
                        <label class="block text-sm font-medium mb-2 text-slate-300">Сообщение *</label>
                        <textarea name="message" class="textarea-modern" 
                                  placeholder="Текст сообщения" required></textarea>
                        <p class="text-xs text-slate-400 mt-1">Поддерживается HTML форматирование</p>
                    </div>
                    
                    <div class="flex gap-3">
                        <button type="submit" class="btn-modern btn-primary-modern">
                            <i class="fas fa-paper-plane"></i>
                            Отправить сообщение
                        </button>
                    </div>
                </form>
            </div>

            <!-- Список команд -->
            <div class="admin-card p-5 sm:p-7">
                <div class="flex items-center gap-3 mb-6">
                    <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-purple-500/70 to-pink-500/70 text-white ring-1 ring-white/20 shadow-lg">
                        <i class="fas fa-list text-lg"></i>
                    </div>
                    <h2 class="text-xl font-semibold tracking-tight">Список команд (<?php echo count($commands); ?>)</h2>
                </div>
                
                <?php if (empty($commands)): ?>
                    <div class="text-center py-12 text-slate-400">
                        <i class="fas fa-inbox text-4xl mb-4"></i>
                        <p>Команды пока не добавлены</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Фото</th>
                                    <th>Название</th>
                                    <th>Команда</th>
                                    <th>Описание</th>
                                    <th>Статус</th>
                                    <th>Создана</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($commands as $cmd): ?>
                                    <tr>
                                        <td><?php echo $cmd['id']; ?></td>
                                        <td>
                                            <?php if (!empty($cmd['photo_path']) && file_exists($cmd['photo_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($cmd['photo_path']); ?>" 
                                                     alt="Фото команды" 
                                                     class="w-16 h-16 object-cover rounded-lg border border-slate-600">
                                            <?php else: ?>
                                                <span class="text-slate-500 text-xs">Нет фото</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?php echo htmlspecialchars($cmd['command_name']); ?></strong></td>
                                        <td><code class="text-indigo-300"><?php echo htmlspecialchars($cmd['command_text']); ?></code></td>
                                        <td class="text-slate-400"><?php echo htmlspecialchars($cmd['description'] ?: '-'); ?></td>
                                        <td>
                                            <span class="badge <?php echo $cmd['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                <?php echo $cmd['is_active'] ? 'Активна' : 'Неактивна'; ?>
                                            </span>
                                        </td>
                                        <td class="text-slate-400 text-sm">
                                            <?php echo date('d.m.Y H:i', strtotime($cmd['created_at'])); ?>
                                        </td>
                                        <td>
                                            <div class="flex gap-2">
                                                <a href="?edit=<?php echo $cmd['id']; ?>" class="btn-modern" title="Редактировать">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены?');">
                                                    <input type="hidden" name="action" value="toggle_active">
                                                    <input type="hidden" name="id" value="<?php echo $cmd['id']; ?>">
                                                    <button type="submit" class="btn-modern" title="<?php echo $cmd['is_active'] ? 'Деактивировать' : 'Активировать'; ?>">
                                                        <i class="fas fa-<?php echo $cmd['is_active'] ? 'eye-slash' : 'eye'; ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить эту команду?');">
                                                    <input type="hidden" name="action" value="delete_command">
                                                    <input type="hidden" name="id" value="<?php echo $cmd['id']; ?>">
                                                    <button type="submit" class="btn-modern btn-danger-modern" title="Удалить">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Логи действий -->
            <div class="admin-card p-5 sm:p-7">
                <div class="flex items-center gap-3 mb-6">
                    <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-amber-500/70 to-orange-500/70 text-white ring-1 ring-white/20 shadow-lg">
                        <i class="fas fa-history text-lg"></i>
                    </div>
                    <h2 class="text-xl font-semibold tracking-tight">Логи действий (<?php echo count($logs); ?>)</h2>
                </div>
                
                <?php if (empty($logs)): ?>
                    <div class="text-center py-12 text-slate-400">
                        <i class="fas fa-inbox text-4xl mb-4"></i>
                        <p>Логи пока отсутствуют</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-modern">
                            <thead>
                                <tr>
                                    <th>Дата/Время</th>
                                    <th>Действие</th>
                                    <th>Детали</th>
                                    <th>IP адрес</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($logs as $log): ?>
                                    <tr>
                                        <td class="text-slate-400 text-sm">
                                            <?php echo date('d.m.Y H:i:s', strtotime($log['created_at'])); ?>
                                        </td>
                                        <td>
                                            <span class="badge badge-active">
                                                <?php 
                                                $actionNames = [
                                                    'add_command' => 'Добавление команды',
                                                    'edit_command' => 'Редактирование команды',
                                                    'delete_command' => 'Удаление команды',
                                                    'toggle_active' => 'Изменение статуса',
                                                    'send_message' => 'Отправка сообщения',
                                                    'download_webhook' => 'Скачивание вебхука'
                                                ];
                                                echo $actionNames[$log['action']] ?? $log['action'];
                                                ?>
                                            </span>
                                        </td>
                                        <td class="text-slate-300 text-sm">
                                            <?php 
                                            if ($log['details']) {
                                                $details = json_decode($log['details'], true);
                                                if ($details) {
                                                    $detailParts = [];
                                                    if (isset($details['command_name'])) {
                                                        $detailParts[] = 'Команда: ' . htmlspecialchars($details['command_name']);
                                                    }
                                                    if (isset($details['command_text'])) {
                                                        $detailParts[] = 'Текст: ' . htmlspecialchars($details['command_text']);
                                                    }
                                                    if (isset($details['chat_id'])) {
                                                        $detailParts[] = 'Chat ID: ' . htmlspecialchars($details['chat_id']);
                                                    }
                                                    if (isset($details['message_length'])) {
                                                        $detailParts[] = 'Длина сообщения: ' . $details['message_length'] . ' символов';
                                                    }
                                                    if (isset($details['old_status']) && isset($details['new_status'])) {
                                                        $detailParts[] = 'Статус: ' . ($details['old_status'] ? 'Активна' : 'Неактивна') . ' → ' . ($details['new_status'] ? 'Активна' : 'Неактивна');
                                                    }
                                                    echo implode(', ', $detailParts) ?: '-';
                                                } else {
                                                    echo htmlspecialchars(substr($log['details'], 0, 100));
                                                }
                                            } else {
                                                echo '-';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-slate-400 text-sm">
                                            <?php echo htmlspecialchars($log['user_ip'] ?? '-'); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </section>
    </main>
</body>
</html>

