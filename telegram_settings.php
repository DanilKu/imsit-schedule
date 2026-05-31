<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'api/telegram_notifications.php';

// Проверка авторизации и прав администратора
requireAdmin();

$telegram = new TelegramNotifications();
$message = '';
$messageType = '';

// Обработка POST запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'test_notification':
                $result = $telegram->sendMessage("🧪 <b>Тестовое уведомление</b>\n\nЭто тестовое сообщение из админки. Если вы видите это сообщение, уведомления настроены правильно! ✅");
                if ($result) {
                    $message = 'Тестовое уведомление отправлено успешно!';
                    $messageType = 'success';
                } else {
                    $message = 'Ошибка отправки уведомления. Проверьте настройки.';
                    $messageType = 'error';
                }
                break;
                
            case 'get_bot_info':
                $botInfo = $telegram->getBotInfo();
                if ($botInfo && $botInfo['ok']) {
                    $message = 'Информация о боте получена успешно!';
                    $messageType = 'success';
                } else {
                    $message = 'Ошибка получения информации о боте.';
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Получаем информацию о боте
$botInfo = $telegram->getBotInfo();
$botData = $botInfo && $botInfo['ok'] ? $botInfo['result'] : null;

// Получаем текущего админа
$currentUser = getCurrentUser();
$adminTelegramId = $currentUser['telegram_id'] ?? null;
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки Telegram - ImsitShop</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            color: #333;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 30px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header h1 {
            color: white;
            font-size: 2.5rem;
            margin-bottom: 10px;
            text-align: center;
        }
        
        .header p {
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            font-size: 1.1rem;
        }
        
        .card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .card h2 {
            color: #333;
            margin-bottom: 20px;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .bot-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            background: rgba(102, 126, 234, 0.1);
            padding: 20px;
            border-radius: 15px;
            border-left: 4px solid #667eea;
        }
        
        .info-item h3 {
            color: #667eea;
            margin-bottom: 10px;
            font-size: 1.1rem;
        }
        
        .info-item p {
            color: #666;
            line-height: 1.6;
        }
        
        .status {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }
        
        .status.connected {
            background: #d4edda;
            color: #155724;
        }
        
        .status.disconnected {
            background: #f8d7da;
            color: #721c24;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
            transform: translateY(-2px);
        }
        
        .message {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .setup-steps {
            background: rgba(102, 126, 234, 0.05);
            padding: 25px;
            border-radius: 15px;
            margin-bottom: 20px;
        }
        
        .setup-steps h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }
        
        .step {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 15px;
            padding: 15px;
            background: white;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .step-number {
            background: #667eea;
            color: white;
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }
        
        .step-content h4 {
            color: #333;
            margin-bottom: 5px;
        }
        
        .step-content p {
            color: #666;
            line-height: 1.5;
        }
        
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            transition: all 0.3s ease;
            margin-bottom: 20px;
        }
        
        .back-link:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .code {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 5px;
            padding: 10px;
            font-family: 'Courier New', monospace;
            font-size: 0.9rem;
            color: #495057;
            word-break: break-all;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Назад в админку
        </a>
        
        <div class="header">
            <h1><i class="fab fa-telegram"></i> Настройки Telegram</h1>
            <p>Управление уведомлениями о новых заказах</p>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="card">
            <h2><i class="fas fa-robot"></i> Информация о боте</h2>
            
            <?php if ($botData): ?>
                <div class="bot-info">
                    <div class="info-item">
                        <h3>Имя бота</h3>
                        <p><?php echo htmlspecialchars($botData['first_name']); ?></p>
                    </div>
                    <div class="info-item">
                        <h3>Username</h3>
                        <p>@<?php echo htmlspecialchars($botData['username']); ?></p>
                    </div>
                    <div class="info-item">
                        <h3>ID бота</h3>
                        <p><?php echo htmlspecialchars($botData['id']); ?></p>
                    </div>
                    <div class="info-item">
                        <h3>Статус</h3>
                        <p><span class="status connected">Подключен</span></p>
                    </div>
                </div>
            <?php else: ?>
                <div class="message error">
                    <i class="fas fa-exclamation-circle"></i>
                    Не удалось получить информацию о боте. Проверьте токен.
                </div>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-cog"></i> Настройка уведомлений</h2>
            
            <div class="setup-steps">
                <h3>Инструкция по настройке:</h3>
                
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Найдите бота в Telegram</h4>
                        <p>Откройте Telegram и найдите бота: <strong>@<?php echo $botData ? htmlspecialchars($botData['username']) : 'your_bot_username'; ?></strong></p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Запустите бота</h4>
                        <p>Отправьте команду <code>/start</code> боту</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4>Активируйте уведомления</h4>
                        <p>Отправьте команду <code>/setadmin</code> боту</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h4>Проверьте работу</h4>
                        <p>Используйте кнопку "Отправить тест" ниже</p>
                    </div>
                </div>
            </div>
            
            <div style="display: flex; gap: 15px; flex-wrap: wrap;">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="test_notification">
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i>
                        Отправить тест
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="action" value="get_bot_info">
                    <button type="submit" class="btn btn-secondary">
                        <i class="fas fa-sync"></i>
                        Обновить информацию
                    </button>
                </form>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Текущие настройки</h2>
            
            <div class="info-item">
                <h3>Ваш Telegram ID</h3>
                <p>
                    <?php if ($adminTelegramId): ?>
                        <span class="code"><?php echo htmlspecialchars($adminTelegramId); ?></span>
                        <span class="status connected">Настроен</span>
                    <?php else: ?>
                        <span class="status disconnected">Не настроен</span>
                        <br><small>Выполните настройку через бота в Telegram</small>
                    <?php endif; ?>
                </p>
            </div>
        </div>
        
        <div class="card">
            <h2><i class="fas fa-bell"></i> Типы уведомлений</h2>
            
            <div class="bot-info">
                <div class="info-item">
                    <h3>🆕 Новые заказы</h3>
                    <p>Уведомления о поступлении новых заказов с полной информацией о клиенте и заказе</p>
                </div>
                <div class="info-item">
                    <h3>📊 Изменение статусов</h3>
                    <p>Уведомления об изменении статуса выполнения заказов</p>
                </div>
                <div class="info-item">
                    <h3>💬 Сообщения клиентов</h3>
                    <p>Уведомления о новых сообщениях от клиентов через форму обратной связи</p>
                </div>
                <div class="info-item">
                    <h3>💰 Финансовые уведомления</h3>
                    <p>Уведомления об оплате заказов и изменении финансового статуса</p>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
