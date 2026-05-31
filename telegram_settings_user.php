<?php
session_start();
require_once 'config/auth.php';
require_once 'config/database.php';

// Проверка авторизации
if (!isAuthenticated()) {
    header('Location: login.php');
    exit();
}

$currentUser = getCurrentUser();
$message = '';
$messageType = '';

// Получаем статус привязки Telegram
try {
    $stmt = $pdo->prepare("SELECT telegram_id FROM users WHERE id = ?");
    $stmt->execute([$currentUser['id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    $telegramLinked = !empty($user['telegram_id']);
    $telegramId = $user['telegram_id'] ?? null;
} catch (Exception $e) {
    $telegramLinked = false;
    $telegramId = null;
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Настройки Telegram - imsitID</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #0f172a; color: #e2e8f0; }
        .container { max-width: 800px; margin: 0 auto; padding: 2rem; }
        .header { text-align: center; margin-bottom: 2rem; }
        .header h1 { color: #f1f5f9; margin-bottom: 0.5rem; }
        .header p { color: #94a3b8; }
        
        .card { background: #1e293b; border-radius: 0.5rem; padding: 2rem; border: 1px solid #334155; margin-bottom: 2rem; }
        .card h2 { color: #f1f5f9; margin-bottom: 1rem; display: flex; align-items: center; gap: 0.5rem; }
        
        .status { display: flex; align-items: center; gap: 1rem; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1.5rem; }
        .status.linked { background: #065f46; color: #d1fae5; }
        .status.not-linked { background: #7f1d1d; color: #fecaca; }
        
        .btn { padding: 0.75rem 1.5rem; border: none; border-radius: 0.5rem; cursor: pointer; font-weight: 500; transition: all 0.2s; text-decoration: none; display: inline-block; }
        .btn-primary { background: #3b82f6; color: white; }
        .btn-primary:hover { background: #2563eb; }
        .btn-danger { background: #ef4444; color: white; }
        .btn-danger:hover { background: #dc2626; }
        .btn-secondary { background: #6b7280; color: white; }
        .btn-secondary:hover { background: #4b5563; }
        
        .instructions { background: #1e293b; border: 1px solid #334155; border-radius: 0.5rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        .instructions h3 { color: #f1f5f9; margin-bottom: 1rem; }
        .instructions ol { color: #cbd5e1; padding-left: 1.5rem; }
        .instructions li { margin-bottom: 0.5rem; }
        
        .qr-code { text-align: center; margin: 1.5rem 0; }
        .qr-code img { max-width: 200px; border-radius: 0.5rem; }
        
        .alert { padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; }
        .alert-success { background: #065f46; color: #d1fae5; }
        .alert-error { background: #7f1d1d; color: #fecaca; }
        
        .features { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 1rem; margin-top: 1.5rem; }
        .feature { background: #334155; padding: 1rem; border-radius: 0.5rem; }
        .feature h4 { color: #f1f5f9; margin-bottom: 0.5rem; }
        .feature p { color: #cbd5e1; font-size: 0.9rem; }
        
        @media (max-width: 768px) {
            .container { padding: 1rem; }
            .features { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fab fa-telegram"></i> Настройки Telegram</h1>
            <p>Привяжите ваш Telegram аккаунт для получения уведомлений</p>
        </div>

        <div id="alertContainer"></div>

        <div class="card">
            <h2><i class="fas fa-link"></i> Статус привязки</h2>
            
            <?php if ($telegramLinked): ?>
                <div class="status linked">
                    <i class="fas fa-check-circle"></i>
                    <div>
                        <strong>Telegram аккаунт привязан</strong>
                        <br>
                        <small>ID: <?php echo $telegramId; ?></small>
                    </div>
                </div>
                
                <p style="color: #cbd5e1; margin-bottom: 1.5rem;">
                    ✅ Ваш Telegram аккаунт успешно привязан! Теперь вы можете использовать бота для получения уведомлений и просмотра расписания.
                </p>
                
                <button class="btn btn-danger" onclick="unlinkTelegram()">
                    <i class="fas fa-unlink"></i> Отвязать Telegram
                </button>
            <?php else: ?>
                <div class="status not-linked">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Telegram аккаунт не привязан</strong>
                        <br>
                        <small>Следуйте инструкциям ниже для привязки</small>
                    </div>
                </div>
                
                <p style="color: #cbd5e1; margin-bottom: 1.5rem;">
                    ❌ Ваш Telegram аккаунт не привязан. Привяжите его, чтобы использовать все возможности бота.
                </p>
                
                <button class="btn btn-primary" onclick="linkTelegram()">
                    <i class="fas fa-link"></i> Привязать Telegram
                </button>
            <?php endif; ?>
        </div>

        <div class="card">
            <h2><i class="fas fa-robot"></i> Как привязать Telegram</h2>
            
            <div class="instructions">
                <h3>📋 Пошаговая инструкция:</h3>
                <ol>
                    <li>Найдите нашего бота в Telegram: <strong>@imsitshop_bot</strong></li>
                    <li>Отправьте команду <code>/start</code></li>
                    <li>Нажмите кнопку "Привязать аккаунт" выше</li>
                    <li>Введите ваш id телеграмма; Получить можно у бота <strong>@userid4Bot</strong></li>
                    <li>Нажмите кнопку "Привязать" на этой странице</li>
                </ol>
            </div>
            
            <div class="qr-code">
                <p style="color: #cbd5e1; margin-bottom: 1rem;">Или отсканируйте QR-код:</p>
                <div style="background: white; padding: 1rem; border-radius: 0.5rem; display: inline-block;">
                    <!-- Здесь будет QR-код для бота -->
                    <div style="width: 200px; height: 200px; background: #f0f0f0; display: flex; align-items: center; justify-content: center; color: #666;">
                        QR-код бота<br>
                        @imsitshop_bot
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-star"></i> Возможности бота</h2>
            
            <div class="features">
                <div class="feature">
                    <h4><i class="fas fa-calendar-alt"></i> Расписание</h4>
                    <p>Просматривайте расписание вашей группы на сегодня или всю неделю</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-clipboard-list"></i> Мои заказы</h4>
                    <p>Отслеживайте статус ваших заказов и получайте уведомления об изменениях</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-bell"></i> Уведомления</h4>
                    <p>Получайте мгновенные уведомления о новых сообщениях и обновлениях</p>
                </div>
                
                <div class="feature">
                    <h4><i class="fas fa-cog"></i> Настройки</h4>
                    <p>Выбирайте группу по умолчанию и настраивайте уведомления</p>
                </div>
            </div>
        </div>

        <div class="card">
            <h2><i class="fas fa-info-circle"></i> Полезная информация</h2>
            
            <div style="color: #cbd5e1;">
                <p><strong>🔗 Ссылки:</strong></p>
                <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                    <li>Бот: <a href="https://t.me/imsitshop_bot" style="color: #3b82f6;">@imsitshop_bot</a></li>
                    <li>Канал: <a href="https://t.me/imsitshop" style="color: #3b82f6;">@imsitshop</a></li>
                    <li>Техподдержка: <a href="https://t.me/cowgivesmilk" style="color: #3b82f6;">@cowgivesmilk</a></li>
                </ul>
                
                <p><strong>💡 Советы:</strong></p>
                <ul style="margin: 1rem 0; padding-left: 1.5rem;">
                    <li>Используйте команду /start для открытия главного меню</li>
                    <li>Все функции доступны через удобные кнопки</li>
                    <li>Расписание можно получить как текстом, так и изображением</li>
                </ul>
            </div>
        </div>

        <div style="text-align: center; margin-top: 2rem;">
            <a href="client_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад в панель
            </a>
        </div>
    </div>

    <script>
        function showAlert(message, type) {
            const container = document.getElementById('alertContainer');
            const alert = document.createElement('div');
            alert.className = `alert alert-${type}`;
            alert.textContent = message;
            container.appendChild(alert);
            setTimeout(() => alert.remove(), 5000);
        }

        function linkTelegram() {
            if (confirm('Вы уверены, что хотите привязать ваш Telegram аккаунт?')) {
                const formData = new FormData();
                formData.append('action', 'link_telegram');
                formData.append('telegram_id', prompt('Введите ваш Telegram ID (получите у бота @imsitshop_bot):'));

                fetch('api/link_telegram.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Ошибка привязки Telegram', 'error');
                });
            }
        }

        function unlinkTelegram() {
            if (confirm('Вы уверены, что хотите отвязать ваш Telegram аккаунт?')) {
                const formData = new FormData();
                formData.append('action', 'unlink_telegram');

                fetch('api/link_telegram.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 2000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    showAlert('Ошибка отвязки Telegram', 'error');
                });
            }
        }
    </script>
</body>
</html>
