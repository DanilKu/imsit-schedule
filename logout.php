<?php
// Начинаем сессию в самом начале, до любых других операций
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Настройка заголовков безопасности
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Проверяем, был ли запрос методом POST (для безопасности)
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Если не POST, показываем страницу подтверждения
    ?>
    <!DOCTYPE html>
    <html lang="ru">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Выход из системы - ImsitShop</title>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1rem;
            }
            
            .logout-container {
                background: white;
                border-radius: 16px;
                box-shadow: 0 20px 40px rgba(0,0,0,0.1);
                padding: 2.5rem;
                text-align: center;
                max-width: 400px;
                width: 100%;
                animation: fadeInUp 0.5s ease-out;
            }
            
            @keyframes fadeInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
            
            .logout-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #ff6b6b, #ee5a52);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 1.5rem;
                color: white;
                font-size: 2rem;
            }
            
            .logout-title {
                font-size: 1.5rem;
                font-weight: 600;
                color: #333;
                margin-bottom: 1rem;
            }
            
            .logout-message {
                color: #666;
                line-height: 1.6;
                margin-bottom: 2rem;
            }
            
            .logout-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
            }
            
            .btn {
                padding: 0.75rem 1.5rem;
                border: none;
                border-radius: 8px;
                font-size: 1rem;
                font-weight: 500;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                gap: 0.5rem;
                transition: all 0.3s ease;
            }
            
            .btn-cancel {
                background: #f8f9fa;
                color: #6c757d;
                border: 1px solid #dee2e6;
            }
            
            .btn-cancel:hover {
                background: #e9ecef;
                color: #495057;
            }
            
            .btn-logout {
                background: linear-gradient(135deg, #ff6b6b, #ee5a52);
                color: white;
            }
            
            .btn-logout:hover {
                background: linear-gradient(135deg, #ee5a52, #d63031);
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(238, 90, 82, 0.4);
            }
            
            @media (max-width: 480px) {
                .logout-container {
                    padding: 2rem 1.5rem;
                }
                
                .logout-buttons {
                    flex-direction: column;
                }
                
                .btn {
                    width: 100%;
                    justify-content: center;
                }
            }
        </style>
    </head>
    <body>
        <div class="logout-container">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            
            <h1 class="logout-title">Выход из системы</h1>
            
            <p class="logout-message">
                Вы уверены, что хотите выйти из системы? 
                Все несохранённые данные будут потеряны.
            </p>
            
            <div class="logout-buttons">
                <a href="javascript:history.back()" class="btn btn-cancel">
                    <i class="fas fa-arrow-left"></i>
                    Отмена
                </a>
                
                <form method="POST" style="display: inline;">
                    <button type="submit" class="btn btn-logout">
                        <i class="fas fa-sign-out-alt"></i>
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit();
}

// Если это POST запрос, выполняем выход
require_once 'config/auth.php';

// Очищаем все данные сессии
$_SESSION = array();

// Уничтожаем сессию
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

session_destroy();

// Перенаправляем на страницу входа с сообщением об успешном выходе
header('Location: login?logout=success');
exit();
?> 