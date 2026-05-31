<?php
require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'config/telegram_auth.php';

// Защита от CSRF-атак
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Генерация CSRF-токена
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Инициализация Telegram Auth
$telegramAuth = new TelegramAuth($pdo);

// Проверка автоматического входа через Telegram (только если не авторизован)
if (!isAuthenticated()) {
    $telegramAutoLogin = $telegramAuth->checkAndAutoLogin();
    
    // Если автоматический вход успешен, перенаправляем
    if ($telegramAutoLogin) {
        $currentUser = getCurrentUser();
        if ($currentUser['role'] === 'admin') {
            header('Location: admin');
        } else {
            if (empty($currentUser['group'])) {
                header('Location: select_group.php');
            } else {
                header('Location: schedule-new.php');
            }
        }
        exit();
    }
}

// Защита от брутфорс-атак
$max_attempts = 5;
$lockout_time = 900; // 15 минут

if (!isset($_SESSION['login_attempts'])) {
    $_SESSION['login_attempts'] = 0;
    $_SESSION['last_attempt'] = 0;
}

// Проверка блокировки
if ($_SESSION['login_attempts'] >= $max_attempts) {
    if (time() - $_SESSION['last_attempt'] < $lockout_time) {
        $error = 'Слишком много неудачных попыток входа. Попробуйте через ' . 
                 ceil(($lockout_time - (time() - $_SESSION['last_attempt'])) / 60) . ' минут.';
    } else {
        // Сброс счетчика после истечения времени
        $_SESSION['login_attempts'] = 0;
        $_SESSION['last_attempt'] = 0;
    }
}

// Функция для очистки и валидации данных
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Функция для валидации email/username
function validateUsername($username) {
    return preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username);
}

// Функция для валидации пароля
function validatePassword($password) {
    return strlen($password) >= 6 && strlen($password) <= 128;
}

// Функция для валидации имени клиента
function validateClientName($name) {
    return preg_match('/^[а-яёА-ЯЁ\s]{2,50}$/u', $name);
}

// Функция для валидации Telegram username
function validateTelegramUsername($username) {
    return preg_match('/^@[a-zA-Z0-9_]{5,32}$/', $username);
}

// Получение данных Telegram пользователя для привязки
$telegramUserData = $telegramAuth->getStoredTelegramData();

// Если пользователь уже авторизован, перенаправляем
if (isAuthenticated()) {
    if (isAdmin()) {
        header('Location: admin');
    } else {
        // Всегда перенаправляем в личный кабинет
        header('Location: client_dashboard');
    }
    exit();
}

$error = '';
$success = '';

// Проверяем, был ли успешный выход
if (isset($_GET['logout']) && $_GET['logout'] === 'success') {
    $success = 'Вы успешно вышли из системы.';
}

// Обработка входа
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Проверка CSRF-токена
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = 'Ошибка безопасности. Обновите страницу и попробуйте снова.';
    } else {
        if ($_POST['action'] === 'login') {
            $username = sanitizeInput($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            
            // Валидация данных
            if (empty($username) || empty($password)) {
                $error = 'Введите логин и пароль';
            } elseif (!validateUsername($username)) {
                $error = 'Некорректный формат логина';
            } elseif (!validatePassword($password)) {
                $error = 'Некорректный формат пароля';
            } else {
                // Проверка блокировки
                if ($_SESSION['login_attempts'] >= $max_attempts) {
                    if (time() - $_SESSION['last_attempt'] < $lockout_time) {
                        $error = 'Слишком много неудачных попыток входа. Попробуйте через ' . 
                                 ceil(($lockout_time - (time() - $_SESSION['last_attempt'])) / 60) . ' минут.';
                    } else {
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['last_attempt'] = 0;
                    }
                }
                
                if (empty($error)) {
                    if (login($username, $password)) {
                        // Сброс счетчика попыток при успешном входе
                        $_SESSION['login_attempts'] = 0;
                        $_SESSION['last_attempt'] = 0;
                        
                        if (isAdmin()) {
                            header('Location: admin');
                        } else {
                            // Проверяем, есть ли у пользователя группа
                            $user = getCurrentUser();
                        // Всегда перенаправляем в личный кабинет после авторизации
                        header('Location: client_dashboard');
                        }
                        exit();
                    } else {
                        // Увеличение счетчика неудачных попыток
                        $_SESSION['login_attempts']++;
                        $_SESSION['last_attempt'] = time();
                        
                        $error = 'Неверный логин или пароль';
                    }
                }
            }
        }
        
        // Обработка регистрации
        if ($_POST['action'] === 'register') {
            $client_name = sanitizeInput($_POST['client_name'] ?? '');
            $telegram_username = sanitizeInput($_POST['telegram_username'] ?? '');
            $group = sanitizeInput($_POST['group'] ?? '');
            
            // Валидация данных
            if (empty($client_name) || empty($telegram_username)) {
                $error = 'Заполните все обязательные поля';
            } elseif (!validateClientName($client_name)) {
                $error = 'Некорректный формат имени (только русские буквы, 2-50 символов)';
            } elseif (!validateTelegramUsername($telegram_username)) {
                $error = 'Некорректный формат Telegram username (например: @username)';
            } else {
                try {
                    // Проверяем, не отправлялась ли уже заявка
                    $sql = "SELECT id FROM registration_requests WHERE first_name = :client_name AND telegram_username = :telegram_username AND status = 'pending'";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([
                        'client_name' => $client_name,
                        'telegram_username' => $telegram_username
                    ]);
                    
                    if ($stmt->fetch()) {
                        $error = 'Заявка с такими данными уже отправлена. Ожидайте ответа администратора.';
                    } else {
                        // Создаём запрос на регистрацию
                        $sql = "INSERT INTO registration_requests (first_name, last_name, telegram_username, status, created_at) 
                                VALUES (:first_name, '', :telegram_username, 'pending', NOW())";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'first_name' => $client_name,
                            'telegram_username' => $telegram_username
                        ]);
                        
                        $success = 'Заявка на регистрацию отправлена! Администратор рассмотрит её и создаст для вас аккаунт. Ожидайте уведомления в Telegram.';
                    }
                } catch (PDOException $e) {
                    error_log("Registration error: " . $e->getMessage());
                    $error = 'Ошибка при отправке заявки. Попробуйте позже.';
                }
            }
        }
    }
}

// Получение темы
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <title>ImsitID - Вход в личный кабинет</title>
    <link href="assets/css/local-fonts.css" rel="stylesheet">
    <link href="assets/css/tailwind-local.css" rel="stylesheet">
    <link href="assets/css/fontawesome-local.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--bg);
            min-height: 100vh;
            position: relative;
            overflow-x: hidden;
        }


        .auth-container {
            display: flex;
            min-height: 100vh;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            z-index: 1;
        }
        
        .auth-card {
            background: var(--card);
            backdrop-filter: blur(20px);
            border-radius: 1rem;
            border: 1px solid var(--border);
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 2rem;
            width: 100%;
            max-width: 420px;
            position: relative;
            overflow: hidden;
        }

        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo {
            font-size: 3rem;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 15px;
            display: inline-block;
        }
        
        .auth-title {
            font-size: 1.8rem;
            color: var(--text);
            margin-bottom: 0.5rem;
            font-weight: 700;
        }
        
        .auth-subtitle {
            color: var(--muted);
            font-size: 1rem;
            font-weight: 400;
        }
        
        .auth-tabs {
            display: flex;
            margin-bottom: 1.5rem;
            background: var(--card);
            border-radius: 0.5rem;
            padding: 0.25rem;
            position: relative;
            border: 1px solid var(--border);
        }
        
        .auth-tab {
            flex: 1;
            padding: 0.75rem 1rem;
            text-align: center;
            cursor: pointer;
            border-radius: 0.375rem;
            transition: all 0.2s ease;
            color: var(--muted);
            font-weight: 500;
            position: relative;
            z-index: 1;
        }
        
        .auth-tab.active {
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: white;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }

        .auth-tab:hover:not(.active) {
            color: var(--text);
            background: var(--card-hover);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text);
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            border-radius: 0.5rem;
            background: var(--card);
            color: var(--text);
            font-size: 0.875rem;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--ring-strong);
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
        }
        
        .toggle-password {
            position: absolute;
            right: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--muted);
            cursor: pointer;
            padding: 0.25rem;
            transition: color 0.2s ease;
        }
        
        .toggle-password:hover {
            color: var(--text);
        }
        
        .space-y-2 > * + * {
            margin-top: 0.5rem;
        }
        
        .mt-4 {
            margin-top: 1rem;
        }
        
        .w-full {
            width: 100%;
        }
        
        .mt-2 {
            margin-top: 0.5rem;
        }
        
        /* Кастомный чекбокс "Запомнить меня" */
        .remember-checkbox {
            display: flex;
            align-items: center;
            cursor: pointer;
            user-select: none;
        }
        
        .remember-checkbox input[type="checkbox"] {
            display: none;
        }
        
        .checkmark {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border);
            border-radius: 4px;
            background: var(--card);
            margin-right: 8px;
            position: relative;
            transition: all 0.2s ease;
        }
        
        .remember-checkbox input[type="checkbox"]:checked + .checkmark {
            background: var(--accent-color);
            border-color: var(--accent-color);
        }
        
        .remember-checkbox input[type="checkbox"]:checked + .checkmark::after {
            content: "✓";
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            color: white;
            font-size: 12px;
            font-weight: bold;
        }
        
        .remember-text {
            color: var(--text);
            font-size: 0.875rem;
        }
        
        /* Стили для кнопок */
        .btn-green {
            background: linear-gradient(135deg, #10b981, #059669) !important;
            color: white !important;
            border: 1px solid rgba(16, 185, 129, 0.3) !important;
        }
        
        .btn-green:hover {
            background: linear-gradient(135deg, #059669, #047857) !important;
            transform: translateY(-1px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }
        
        .btn-transparent {
            background: rgba(255, 255, 255, 0.05) !important;
            color: var(--muted) !important;
            border: 1px solid rgba(255, 255, 255, 0.1) !important;
        }
        
        .btn-transparent:hover {
            background: rgba(255, 255, 255, 0.1) !important;
            color: var(--text) !important;
            border-color: rgba(255, 255, 255, 0.2) !important;
        }
        
        /* Стили для дополнительных кнопок */
        .mt-4 {
            margin-top: 1rem;
        }
        
        .mt-4 a, .mt-4 button {
            display: block;
            width: 100%;
            text-align: center;
            text-decoration: none;
        }

        .form-input::placeholder {
            color: var(--muted);
            font-weight: 400;
        }
        
        .form-btn {
            width: 100%;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, var(--accent-color) 0%, var(--accent-hover) 100%);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            backdrop-filter: blur(10px);
        }
        
        .form-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .form-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }

        .form-btn:hover::before {
            left: 100%;
        }

        .form-btn:active {
            transform: translateY(0);
        }

        .schedule {
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
            margin-top: 15px;
        }
        .schedule::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        .schedule:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
        }
        .schedule:hover::before {
            left: 100%;
        }

        /* Стили для модального окна уведомлений */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(5px);
        }

        .modal-content {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            margin: 5% auto;
            padding: 30px;
            border-radius: 15px;
            width: 90%;
            max-width: 500px;
            position: relative;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 20px;
            top: 15px;
        }

        .close:hover {
            color: #000;
        }

        .notification-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .notification-header h2 {
            margin: 0;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .notification-body {
            margin-bottom: 25px;
            line-height: 1.6;
        }
        
        .notification-body p {
            margin: 0;
            font-size: 1rem;
        }
        
        .notification-actions {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .text-success { color: #28a745; }
        .text-warning { color: #ffc107; }
        .text-danger { color: #dc3545; }
        .text-info { color: #17a2b8; }

        /* Стили для модального окна "Забыли пароль?" */
        .forgot-password-modal {
            max-width: 450px;
            padding: 0;
            overflow: hidden;
        }

        .forgot-password-content {
            padding: 0;
        }

        .forgot-password-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 25px 30px 20px 30px;
            text-align: center;
            margin: 0 -30px 0 -30px;
        }

        .forgot-password-header i {
            font-size: 2.2rem;
            margin-bottom: 12px;
            display: block;
        }

        .forgot-password-header h2 {
            margin: 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .forgot-password-body {
            padding: 15px 30px 10px 30px;
        }

        .forgot-password-body p {
            margin: 0 0 15px 0;
            color: #4a5568;
            font-size: 0.95rem;
            line-height: 1.5;
        }

        .contact-info {
            margin-bottom: 12px;
        }

        .contact-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            background: #f7fafc;
            border-radius: 10px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .contact-item:hover {
            background: #edf2f7;
            transform: translateX(5px);
        }

        .contact-item i {
            font-size: 1.4rem;
            margin-right: 12px;
            width: 25px;
            text-align: center;
        }

        .contact-item .fab.fa-telegram {
            color: #0088cc;
        }

        .contact-details {
            flex: 1;
        }

        .contact-details strong {
            display: block;
            font-size: 0.9rem;
            color: #2d3748;
            margin-bottom: 2px;
        }

        .contact-details span {
            font-size: 1rem;
            color: #4a5568;
            font-weight: 500;
        }

        .forgot-password-note {
            background: #ebf8ff;
            border: 1px solid #bee3f8;
            border-radius: 8px;
            padding: 8px 12px;
            display: flex;
            align-items: flex-start;
            gap: 8px;
            margin-top: 15px;
            margin-bottom: 15px;
        }

        .forgot-password-note i {
            color: #3182ce;
            font-size: 1rem;
            margin-top: 1px;
        }

        .forgot-password-note p {
            margin: 0;
            font-size: 0.85rem;
            color: #2c5282;
            line-height: 1.4;
        }

        .forgot-password-actions {
            padding: 0 30px 10px 30px;
            text-align: center;
        }

        

        .btn-close {
            background: #e2e8f0;
            color: #4a5568;
            border: none;
            padding: 6px 14px;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .btn-close:hover {
            background: #cbd5e0;
            color: #2d3748;
            transform: translateY(-1px);
        }
        
        .alert {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
            border: none;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #51cf66, #40c057);
            color: white;
        }

        .alert i {
            font-size: 1.2rem;
        }
        
        .required {
            color: #e53e3e;
            font-weight: 700;
        }

        /* Анимации */
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

        .auth-card {
            animation: fadeInUp 0.6s ease-out;
        }

        /* Адаптивность */
        @media (max-width: 480px) {
            .auth-container {
                padding: 10px;
            }
            
            .auth-card {
                padding: 30px 20px;
                border-radius: 16px;
            }
            
            .auth-title {
                font-size: 1.5rem;
            }
            
            .auth-logo {
                font-size: 2.5rem;
            }
        }

        /* Дополнительные эффекты */
        .form-input:focus + .form-label {
            color: #667eea;
        }

        .auth-tab i {
            margin-right: 8px;
        }

        .form-btn i {
            margin-right: 8px;
        }
        
        .schedule-btn {
            width: 100%;
            padding: 16px 20px;
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.3);
            margin-top: 15px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        
        .schedule-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }
        
        .schedule-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.4);
            color: white;
            text-decoration: none;
        }

        .schedule-btn:hover::before {
            left: 100%;
        }

        .schedule-btn:active {
            transform: translateY(0);
        }
        
        .schedule-btn i {
            margin-right: 8px;
        }
        .toggle-password {
            position: absolute;
            right: 15px;
            top: 65%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color:rgb(138, 146, 183);
            font-size: 1.10rem;
            cursor: pointer;
            padding: 4px;
            z-index: 2;
        }
        .form-group input[type="password"] {
            padding-right: 44px;
        }
        .toggle-password:focus {
            outline: none;
            color: #667eea;
        }
        .toggle-password i.fa-eye-slash {
            color: #e53e3e;
        }
        .recovery-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            width: 100%;
            padding: 12px 20px;
            background: transparent;
            color: #667eea;
            border: 2px solid #667eea;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            margin-top: 15px;
        }
        
        .recovery-btn:hover {
            background: #667eea;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .recovery-btn i {
            margin-right: 8px;
        }

        /* Стили для Telegram Web App */
        .telegram-link-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 20px;
            padding: 30px;
            margin-bottom: 20px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
        }

        .telegram-info {
            text-align: center;
            margin-bottom: 25px;
        }

        .telegram-icon {
            font-size: 2.5rem;
            margin-bottom: 15px;
        }

        .telegram-info h3 {
            color: white;
            margin: 0 0 10px 0;
            font-size: 1.4rem;
            font-weight: 600;
        }

        .telegram-info p {
            color: rgba(255, 255, 255, 0.8);
            margin: 0;
            font-size: 0.9rem;
        }

        .telegram-user-info {
            margin-bottom: 25px;
        }

        .telegram-user-card {
            display: flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .telegram-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            font-size: 1.2rem;
            font-weight: bold;
            color: white;
        }

        .telegram-user-details {
            flex: 1;
        }

        .telegram-name {
            color: white;
            font-weight: 600;
            font-size: 1rem;
            margin-bottom: 5px;
        }

        .telegram-username {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.9rem;
        }

        /* Уведомления */
        .telegram-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 10px;
            padding: 15px 20px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
            z-index: 10000;
            transform: translateX(400px);
            transition: transform 0.3s ease;
            max-width: 300px;
        }

        .telegram-notification.show {
            transform: translateX(0);
        }

        .telegram-notification-success {
            border-left: 4px solid #10b981;
        }

        .telegram-notification-error {
            border-left: 4px solid #ef4444;
        }

        .notification-content {
            display: flex;
            align-items: center;
        }

        .notification-icon {
            margin-right: 10px;
            font-size: 1.2rem;
        }

        .notification-message {
            color: #374151;
            font-weight: 500;
        }

        /* Индикатор загрузки */
        .loading-spinner {
            display: inline-block;
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

    </style>
</head>
<body class="h-full">
    <!-- Фоновые декоративные круги в стиле shedule2.php -->
    <div class="page-bg">
        <div class="blob blob-a"></div>
        <div class="blob blob-b"></div>
        <div class="overlay"></div>
    </div>

    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <div class="auth-logo">imsitID</div>
                <h1 class="auth-title">Вход в систему</h1>
                <p class="auth-subtitle">Войдите в свой аккаунт или зарегистрируйтесь</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <!-- Переключатель вкладок -->
            <div class="auth-tabs">
                <button class="auth-tab active" onclick="switchTab('login')">
                    <i class="fas fa-sign-in-alt"></i> Вход
                </button>
                <button class="auth-tab" onclick="switchTab('register')">
                    <i class="fas fa-user-plus"></i> Регистрация
                </button>
            </div>
            
            <!-- Форма привязки Telegram аккаунта (показывается только в Telegram Web App) -->
            <div id="telegram-link-form" class="telegram-link-section" style="display: none;">
                <div class="telegram-info">
                    <div class="telegram-icon">🔗</div>
                    <h3>Привязка Telegram аккаунта</h3>
                    <p>Для автоматического входа в будущем введите ваши данные:</p>
                </div>
                
                <div id="telegram-user-info" class="telegram-user-info">
                    <!-- Информация о Telegram пользователе будет загружена через JavaScript -->
                </div>
                
                <form id="link-form" class="auth-form">
                    <div class="form-group">
                        <label for="link-username" class="form-label">Логин:</label>
                        <input type="text" id="link-username" name="username" class="form-input" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="link-password" class="form-label">Пароль:</label>
                        <input type="password" id="link-password" name="password" class="form-input" required>
                    </div>
                    
                    <button type="submit" id="link-account-btn" class="form-btn">
                        Привязать аккаунт
                    </button>
                </form>
            </div>
            
            <!-- Форма входа -->
            <form method="POST" class="auth-form active" id="login-form">
                <input type="hidden" name="action" value="login">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="username" class="form-label">Логин</label>
                    <input type="text" id="username" name="username" class="form-input" placeholder="Введите логин" required>
                </div>
                
                <div class="form-group">
                    <label for="password" class="form-label">Пароль</label>
                    <div class="relative">
                        <input type="password" id="password" name="password" class="form-input" placeholder="Введите пароль" required>
                        <button type="button" class="toggle-password" onclick="togglePassword('password', this)">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="remember-checkbox">
                        <input type="checkbox" name="remember" id="remember" value="1">
                        <span class="checkmark"></span>
                        <span class="remember-text">Запомнить меня</span>
                    </label>
                </div>
                
                <button type="submit" class="form-btn">
                    <i class="fas fa-sign-in-alt"></i> Войти
                </button>
            </form>
            
            <!-- Дополнительные кнопки -->
            <div class="mt-4">
                <a href="id.php" class="form-btn btn-green w-full">
                    <i class="fas fa-calendar-alt"></i> Расписание
                </a>
                
                <button type="button" class="form-btn btn-transparent w-full mt-2" onclick="showForgotPasswordModal()">
                    <i class="fas fa-key"></i> Забыли пароль?
                </button>
            </div>
            
            <!-- Форма регистрации -->
            <form method="POST" class="auth-form" id="register-form" style="display: none;">
                <input type="hidden" name="action" value="register">
                <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                
                <div class="form-group">
                    <label for="client_name" class="form-label">
                        Фамилия и имя <span class="text-red-400">*</span>
                    </label>
                    <input type="text" id="client_name" name="client_name" class="form-input" placeholder="Иванов Иван" required>
                    <small class="text-xs text-slate-400">
                        Регистрация открыта для всех студентов
                    </small>
                </div>
                
                <div class="form-group">
                    <label for="telegram_username" class="form-label">
                        Telegram username <span class="text-red-400">*</span>
                    </label>
                    <input type="text" id="telegram_username" name="telegram_username" class="form-input" placeholder="@username" required>
                </div>
                
                <div class="form-group">
                    <label for="group" class="form-label">Группа</label>
                    <input type="text" id="group" name="group" class="form-input" placeholder="Введите группу(необязательно)" required>
                        
                    </input>
                </div>
                
                <button type="submit" class="form-btn">
                    <i class="fas fa-paper-plane"></i> Отправить заявку
                </button>
            </form>
                
            </div>
        </div>
    </div>

    <!-- Модальное окно уведомлений -->
    <div id="notificationModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close" onclick="closeNotificationModal()">&times;</span>
            <div id="notificationContent">
                <!-- Содержимое уведомления будет загружено динамически -->
            </div>
        </div>
    </div>

    <!-- Модальное окно "Забыли пароль?" -->
    <div id="forgotPasswordModal" class="modal" style="display: none;">
        <div class="modal-content forgot-password-modal">
            <span class="close" onclick="closeForgotPasswordModal()">&times;</span>
            <div class="forgot-password-content">
                <div class="forgot-password-header">
                    <i class="fas fa-key"></i>
                    <h2>Забыли пароль?</h2>
                </div>
                
                <div class="forgot-password-body">
                    <p>Для восстановления доступа к аккаунту свяжитесь с администратором или тех. разработчиком:</p>
                    
                    <div class="contact-info">
                        <div class="contact-item">
                            <i class="fab fa-telegram"></i>
                            <div class="contact-details">
                                <strong>Telegram администратора</strong>
                                <span>@danyalacio</span>
                            </div>
                        </div>
                        <div class="contact-item">
                            <i class="fab fa-telegram"></i>
                            <div class="contact-details">
                                <strong>Telegram тех. разработчика</strong>
                                <span>@cowgivesmilk</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="forgot-password-note">
                        <i class="fas fa-info-circle"></i>
                        <p>Укажите в сообщении ваш логин и причину обращения для быстрого решения вопроса.</p>
                    </div>
                    <div class="forgot-password-note">
                        <i class="fas fa-info-circle"></i>
                        <p>Присоединяйтесь к нашему <a href="https://t.me/imsitid" target="_blank">Telegram</a> каналу для получения актуальной информации о приложении.</p>
                    </div>
                </div>
                
                <div class="forgot-password-actions">
                    <button onclick="closeForgotPasswordModal()" class="btn-close">
                        <i class="fas fa-times"></i> Закрыть
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Cookie functions for group persistence
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = name + '=' + value + ';expires=' + expires.toUTCString() + ';path=/';
        }

        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i];
                while (c.charAt(0) === ' ') c = c.substring(1, c.length);
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }

        function switchTab(tab) {
            // Обновляем активную вкладку
            document.querySelectorAll('.auth-tab').forEach(t => {
                t.classList.remove('active', 'bg-white/10', 'text-white');
                t.classList.add('text-slate-400');
            });
            document.querySelectorAll('.auth-form').forEach(f => f.style.display = 'none');
            
            event.target.classList.add('active', 'bg-white/10', 'text-white');
            event.target.classList.remove('text-slate-400');
            document.getElementById(tab + '-form').style.display = 'block';
        }
        
        // Фокус на поле логина при загрузке
        document.getElementById('username').focus();

        function togglePassword(inputId, btn) {
            const input = document.getElementById(inputId);
            if (!input) return;
            if (input.type === 'password') {
                input.type = 'text';
                btn.querySelector('i').classList.remove('fa-eye');
                btn.querySelector('i').classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                btn.querySelector('i').classList.remove('fa-eye-slash');
                btn.querySelector('i').classList.add('fa-eye');
            }
        }

        // Система уведомлений для страницы входа
        let currentNotification = null;

        // Функция загрузки и показа уведомлений
        async function loadAndShowNotifications() {
            try {
                const response = await fetch('api/get_notifications.php?context=login');
                const data = await response.json();
                
                if (data.success && data.notifications.length > 0) {
                    // Показываем первое уведомление
                    showNotification(data.notifications[0]);
                }
            } catch (error) {
                console.error('Ошибка загрузки уведомлений:', error);
            }
        }

        // Функция показа уведомления
        function showNotification(notification) {
            currentNotification = notification;
            const modal = document.getElementById('notificationModal');
            const content = document.getElementById('notificationContent');
            
            // Определяем иконку и цвет в зависимости от типа
            let icon, colorClass;
            switch(notification.type) {
                case 'success':
                    icon = 'fas fa-check-circle';
                    colorClass = 'text-success';
                    break;
                case 'warning':
                    icon = 'fas fa-exclamation-triangle';
                    colorClass = 'text-warning';
                    break;
                case 'error':
                    icon = 'fas fa-times-circle';
                    colorClass = 'text-danger';
                    break;
                default:
                    icon = 'fas fa-info-circle';
                    colorClass = 'text-info';
            }
            
            content.innerHTML = `
                <div class="notification-header ${colorClass}">
                    <h2><i class="${icon}"></i> ${notification.title}</h2>
                </div>
                <div class="notification-body">
                    <p>${notification.message}</p>
                </div>
                <div class="notification-actions">
                    <button onclick="closeNotificationModal()" class="form-btn">
                        <i class="fas fa-check"></i> Понятно
                    </button>
                </div>
            `;
            
            modal.style.display = 'flex';
        }

        // Функция закрытия модального окна уведомлений
        async function closeNotificationModal() {
            const modal = document.getElementById('notificationModal');
            modal.style.display = 'none';
            
            // Отмечаем уведомление как показанное
            if (currentNotification) {
                try {
                    await fetch('api/mark_notification_shown.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            notification_id: currentNotification.id
                        })
                    });
                } catch (error) {
                    console.error('Ошибка отметки уведомления:', error);
                }
                currentNotification = null;
            }
        }

        // Закрытие модального окна уведомлений по клику вне его
        document.getElementById('notificationModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeNotificationModal();
            }
        });

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const notificationModal = document.getElementById('notificationModal');
                if (notificationModal.style.display === 'flex') {
                    closeNotificationModal();
                }
            }
        });

        // Функциональность "Запомнить меня"
        function saveLoginData() {
            const rememberCheckbox = document.getElementById('remember');
            const username = document.getElementById('username').value;
            
            if (rememberCheckbox.checked && username) {
                localStorage.setItem('remembered_username', username);
                localStorage.setItem('remember_me', 'true');
            } else {
                localStorage.removeItem('remembered_username');
                localStorage.removeItem('remember_me');
            }
        }
        
        function loadLoginData() {
            const rememberedUsername = localStorage.getItem('remembered_username');
            const rememberMe = localStorage.getItem('remember_me');
            
            if (rememberedUsername && rememberMe === 'true') {
                document.getElementById('username').value = rememberedUsername;
                document.getElementById('remember').checked = true;
            }
        }
        
        // Обработчик для формы входа
        document.getElementById('login-form').addEventListener('submit', function(e) {
            saveLoginData();
        });
        
        // Загружаем уведомления при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            loadLoginData();
            
            // Инициализация группы из cookies
            const savedGroup = getCookie('selected_group');
            if (savedGroup && ['Исип-05', 'Исип-06'].includes(savedGroup)) {
                // Устанавливаем сохраненную группу в селект регистрации
                const groupSelect = document.getElementById('group');
                if (groupSelect) {
                    groupSelect.value = savedGroup;
                }
            }
            
            // Небольшая задержка для лучшего UX
            setTimeout(loadAndShowNotifications, 1500);
        });

        // Функции для модального окна "Забыли пароль?"
        function showForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'flex';
        }

        function closeForgotPasswordModal() {
            document.getElementById('forgotPasswordModal').style.display = 'none';
        }

        // Закрытие модального окна "Забыли пароль?" по клику вне его
        document.getElementById('forgotPasswordModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeForgotPasswordModal();
            }
        });

        // Закрытие по Escape для модального окна "Забыли пароль?"
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const forgotPasswordModal = document.getElementById('forgotPasswordModal');
                if (forgotPasswordModal.style.display === 'flex') {
                    closeForgotPasswordModal();
                }
            }
        });

    </script>
    
    <!-- Telegram Web App Script -->
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <script src="js/telegram-webapp-direct.js"></script>
</body>
</html> 