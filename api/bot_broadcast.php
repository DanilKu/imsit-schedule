<?php
// Рассылка сообщения всем пользователям бота с детализацией в реальном времени
error_reporting(0);
ini_set('display_errors', 0);

try {
    require_once dirname(__DIR__) . '/config/database.php';
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'error' => 'Database connection failed', 'type' => 'error']);
    exit;
}

$BOT_TOKEN = getenv('IMSIT_TELEGRAM_BOT_TOKEN') ?: '8371794642:AAEtU08o8r6qL-HB8qJGvRKik0gCvzd_b2M';

function tg_api($method, $payload) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$resp) {
        return ['ok' => false, 'error' => 'HTTP ' . $httpCode];
    }
    
    $decoded = json_decode($resp, true);
    return $decoded ?: ['ok' => false, 'error' => 'Invalid JSON response'];
}

function sendChunk($data) {
    echo json_encode($data, JSON_UNESCAPED_UNICODE) . "\n";
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

// Устанавливаем заголовки для потоковой передачи
header('Content-Type: application/x-ndjson');
header('Cache-Control: no-cache');
header('X-Accel-Buffering: no');

// Отключаем буферизацию вывода
if (ob_get_level() > 0) {
    ob_end_clean();
}

try {
    $message = $_POST['message'] ?? '';
    $photo = $_FILES['photo']['tmp_name'] ?? null;
    $document = $_FILES['document']['tmp_name'] ?? null;
    
    if ($message === '' && !$photo && !$document) {
        sendChunk(['ok' => false, 'error' => 'Message or media required', 'type' => 'error']);
        exit;
    }

    // Получаем список выбранных пользователей
    $selectedChatIds = [];
    if (isset($_POST['chat_ids']) && is_array($_POST['chat_ids']) && !empty($_POST['chat_ids'])) {
        $selectedChatIds = array_values(array_filter(array_map('intval', $_POST['chat_ids']), function($id) {
            return $id > 0;
        }));
    }
    
    // Если не выбраны пользователи, отправляем всем
    if (empty($selectedChatIds)) {
        $stmt = $pdo->query("SELECT chat_id, username, first_name FROM bot_users ORDER BY chat_id");
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } else {
        // Получаем только выбранных пользователей
        $count = count($selectedChatIds);
        $placeholders = implode(',', array_fill(0, $count, '?'));
        $sql = "SELECT chat_id, username, first_name FROM bot_users WHERE chat_id IN ($placeholders) ORDER BY chat_id";
        $stmt = $pdo->prepare($sql);
        
        // Убеждаемся, что передаем правильное количество параметров
        if ($stmt->execute($selectedChatIds) === false) {
            $error = $stmt->errorInfo();
            sendChunk(['ok' => false, 'error' => 'Database error: ' . $error[2], 'type' => 'error']);
            exit;
        }
        
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    $total = count($users);
    
    if ($total === 0) {
        sendChunk(['ok' => false, 'error' => 'No users found or selected', 'type' => 'error']);
        exit;
    }
    
    // Логируем для отладки (можно убрать в продакшене)
    error_log("Broadcast: selected " . count($selectedChatIds) . " users, total found: " . $total);

    sendChunk(['ok' => true, 'type' => 'start', 'total' => $total]);

    $sent = 0;
    $failed = 0;
    $failedUsers = [];

    foreach ($users as $index => $user) {
        $chatId = (int)$user['chat_id'];
        $userName = $user['username'] ? '@' . $user['username'] : ($user['first_name'] ?? "ID:{$chatId}");
        $current = $index + 1;
        
        try {
            if ($photo) {
                $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendPhoto";
                $data = [
                    'chat_id' => $chatId,
                    'photo' => new CURLFile(
                        $_FILES['photo']['tmp_name'],
                        $_FILES['photo']['type'] ?? 'image/jpeg',
                        $_FILES['photo']['name'] ?? 'photo.jpg'
                    ),
                    'caption' => $message,
                    'parse_mode' => 'HTML'
                ];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200 || !$resp) {
                    throw new Exception('HTTP ' . $httpCode);
                }
                
                $res = json_decode($resp, true);
                if (!$res || empty($res['ok'])) {
                    throw new Exception($res['description'] ?? 'Unknown error');
                }
            } elseif ($document) {
                $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendDocument";
                $data = [
                    'chat_id' => $chatId,
                    'document' => new CURLFile(
                        $_FILES['document']['tmp_name'],
                        $_FILES['document']['type'] ?? 'application/octet-stream',
                        $_FILES['document']['name'] ?? 'file.bin'
                    ),
                    'caption' => $message,
                    'parse_mode' => 'HTML'
                ];
                $ch = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_TIMEOUT, 10);
                $resp = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);
                
                if ($httpCode !== 200 || !$resp) {
                    throw new Exception('HTTP ' . $httpCode);
                }
                
                $res = json_decode($resp, true);
                if (!$res || empty($res['ok'])) {
                    throw new Exception($res['description'] ?? 'Unknown error');
                }
            } else {
                $res = tg_api('sendMessage', [
                    'chat_id' => $chatId,
                    'text' => $message,
                    'parse_mode' => 'HTML',
                    'disable_web_page_preview' => true
                ]);
                
                if (!$res || empty($res['ok'])) {
                    throw new Exception($res['description'] ?? 'Unknown error');
                }
            }
            
            $sent++;
            sendChunk([
                'ok' => true,
                'type' => 'progress',
                'current' => $current,
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'user' => $userName,
                'status' => 'success'
            ]);
        } catch (Exception $e) {
            $failed++;
            $failedUsers[] = ['user' => $userName, 'chat_id' => $chatId, 'error' => $e->getMessage()];
            
            sendChunk([
                'ok' => true,
                'type' => 'progress',
                'current' => $current,
                'total' => $total,
                'sent' => $sent,
                'failed' => $failed,
                'user' => $userName,
                'status' => 'failed',
                'error' => $e->getMessage()
            ]);
        }
        
        usleep(120000); // 0.12s задержка
    }

    sendChunk([
        'ok' => true,
        'type' => 'complete',
        'sent' => $sent,
        'failed' => $failed,
        'total' => $total,
        'failedUsers' => $failedUsers
    ]);

} catch (Exception $e) {
    sendChunk(['ok' => false, 'error' => $e->getMessage(), 'type' => 'error']);
}
?>


