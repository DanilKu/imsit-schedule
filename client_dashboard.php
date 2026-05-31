<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Europe/Moscow');

// проверка, не запущена ли уже сессия
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
    header('Location: login');
    exit;
}

// --- Смена пароля ---
$changePassError = '';
$changePassSuccess = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $old_password = $_POST['old_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($old_password) || empty($new_password) || empty($confirm_password)) {
        $changePassError = 'Пожалуйста, заполните все поля.';
    } elseif ($new_password !== $confirm_password) {
        $changePassError = 'Новый пароль и подтверждение не совпадают.';
    } elseif (strlen($new_password) < 6) {
        $changePassError = 'Пароль должен быть не короче 6 символов.';
    } else {
        // Проверяем старый пароль
        $stmt = $pdo->prepare('SELECT password FROM users WHERE id = :id');
        $stmt->execute(['id' => $currentUser['id']]);
        $user = $stmt->fetch();
        if (!$user || !password_verify($old_password, $user['password'])) {
            $changePassError = 'Старый пароль введён неверно.';
        } else {
            // Обновляем пароль
            $newHash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('UPDATE users SET password = :password WHERE id = :id');
            $stmt->execute(['password' => $newHash, 'id' => $currentUser['id']]);
            $changePassSuccess = 'Пароль успешно изменён!';
        }
    }
}

// Получение заказов клиента
try {
    $sql = "SELECT * FROM orders WHERE client_name = :client_name ORDER BY created_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['client_name' => $currentUser['client_name']]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    $orders = [];
    $error = 'Ошибка получения заказов: ' . $e->getMessage();
}

// Класс WorkStage
require_once 'includes/WorkStage.php';
$workStage = new WorkStage($pdo);

$orderRequestMeta = [];
try {
    require_once 'includes/OrderRequest.php';
    $__svc = new OrderRequestService($pdo);
    $__settings = $__svc->getSettings();
    foreach ($__settings as $__row) {
        $orderRequestMeta[$__row['work_type']] = [
            'price' => (float)$__row['default_price'],
            'is_open' => (int)$__row['is_open'] === 1,
        ];
    }
} catch (Throwable $____e) {
    $orderRequestMeta = [];
}

$clientName = $currentUser['client_name'];

// Общий долг клиента
$totalDebt = 0;
foreach ($orders as $o) {
    $totalDebt += isset($o['debt_amount']) ? (float)$o['debt_amount'] : 0;
}

$theme = 'dark';
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Личный кабинет - <?php echo htmlspecialchars($clientName); ?></title>
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui;
        }

        .header-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .header-left {
            display: flex;
            align-items: center;
        }

        .header-left p {
            font-size: 1.2rem;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
        }

        .logo {
            font-size: 1.5rem;
            color: #ffffff;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .logo i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }



        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .username {
            color: #ffffff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
        }

        .username i {
            background: rgba(255, 255, 255, 0.2);
            padding: 0.5rem;
            border-radius: 12px;
            backdrop-filter: blur(10px);
        }







        .logout-btn:hover {
            background: rgba(255, 255, 255, 0.3) !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
        }



        /* Стили блока действий клиента */
        .user-actions {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            align-items: center;
        }

        .actions-left {
            display: flex;
            gap: 0.75rem;
        }

        .actions-right {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }



        .user-menu {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .username {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-color);
        }

        .logout-btn {
            background: var(--danger-color);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 5px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.3s;
        }

        .logout-btn:hover {
            background: #c82333;
        }

        /* Кнопка «Вверх» */
        .scroll-top-btn {
            position: fixed;
            right: 20px;
            bottom: 24px;
            width: 56px;
            height: 56px;
            border-radius: 50%;
            border: none;
            outline: none;
            cursor: pointer;
            background: var(--gradient-blue);
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px) scale(.95);
            pointer-events: none;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 255, 255, 0.2);
        }
        .scroll-top-btn.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .scroll-top-btn:hover { 
            transform: translateY(-4px) scale(1.05);
            box-shadow: var(--shadow-xl);
            background: linear-gradient(135deg, #2563eb, #1e40af);
        }
        .scroll-top-btn i { 
            font-size: 1.2rem;
            font-weight: 600;
        }

        /* Стили модалки уведомлений */
        .notification-header {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
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

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            background: transparent;
        }

        .client-header {
            background: none;
            border-radius: 20px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            text-align: center;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-color);
        }

        .client-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }



        .client-avatar {
            width: 100px;
            height: 100px;
            background: var(--gradient-blue);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            font-size: 2.5rem;
            color: white;
            backdrop-filter: blur(10px);
            border: 3px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }



        .client-name {
            font-size: 2.2rem;
            margin-bottom: 1rem;
            color: var(--text-color);
            font-weight: 700;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }



        .client-info {
            margin-bottom: 2rem;
        }

        .client-info p {
            margin: 0.5rem 0;
            color: var(--text-color);
            font-size: 1.1rem;
            font-weight: 500;
        }

        .client-info i {
            margin-right: 0.5rem;
            color: var(--primary-color);
            font-size: 1.2rem;
        }

        [data-theme="dark"] .client-info p {
            color: rgba(255, 255, 255, 0.9);
        }

        [data-theme="dark"] .client-info i {
            color: rgba(255, 255, 255, 0.8);
        }

        .client-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }

        .stat-item {
            background: var(--gradient-blue);
            padding: 1.5rem;
            border-radius: 16px;
            text-align: center;
            border: 1px solid rgba(255, 255, 255, 0.1);
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .stat-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.1) 0%, transparent 50%);
            pointer-events: none;
        }

        .stat-item:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: #ffffff;
            margin-bottom: 0.5rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .stat-label {
            color: rgba(255, 255, 255, 0.9);
            font-size: 1rem;
            font-weight: 500;
        }

        .orders-section {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border-color);
        }

        .orders-section h2 {
            margin-bottom: 1.5rem;
            color: var(--text-color);
            font-size: 1.8rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .order-card {
            background: none;
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .order-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.05) 0%, transparent 50%);
            pointer-events: none;
        }



        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        .order-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: var(--text-color);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        [data-theme="dark"] .order-title {
            color: #ffffff;
        }

        .order-status {
            padding: 0.4rem 1rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            position: relative;
            overflow: hidden;
        }

        .order-status::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.5s;
        }

        .order-status:hover::before {
            left: 100%;
        }

        .status-pending {
            background: linear-gradient(135deg, #ffd54f, #ffb300);
            color: #000;
            box-shadow: 0 2px 8px rgba(255, 181, 0, 0.3);
        }

        .status-in_progress {
            background: linear-gradient(135deg, #42a5f5, #1976d2);
            color: white;
            box-shadow: 0 2px 8px rgba(66, 165, 245, 0.3);
        }

        .status-completed {
            background: linear-gradient(135deg, #66bb6a, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 187, 106, 0.3);
        }

        .status-done {
            background: linear-gradient(135deg, #66bb6a, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 187, 106, 0.3);
        }

        .status-finished {
            background: linear-gradient(135deg, #66bb6a, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 187, 106, 0.3);
        }

        [data-theme="dark"] .status-pending {
            background: linear-gradient(135deg, #ffd54f, #ffb300);
            color: #000;
            box-shadow: 0 2px 8px rgba(255, 181, 0, 0.4);
        }

        [data-theme="dark"] .status-in_progress {
            background: linear-gradient(135deg, #42a5f5, #1976d2);
            color: white;
            box-shadow: 0 2px 8px rgba(66, 165, 245, 0.4);
        }

        [data-theme="dark"] .status-completed {
            background: linear-gradient(135deg, #66bb6a, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 187, 106, 0.4);
        }

        [data-theme="dark"] .status-done {
            background: linear-gradient(135deg, #66bb6a, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 187, 106, 0.4);
        }

        [data-theme="dark"] .status-finished {
            background: linear-gradient(135deg, #66bb6a, #388e3c);
            color: white;
            box-shadow: 0 2px 8px rgba(102, 187, 106, 0.4);
        }

        .order-details {
            color: var(--text-color);
            margin-bottom: 1rem;
            position: relative;
            z-index: 1;
        }

        [data-theme="dark"] .order-details {
            color: rgba(255, 255, 255, 0.9);
        }

        .order-details p {
            margin: 0.5rem 0;
            font-size: 1rem;
            font-weight: 500;
        }

        .order-actions {
            display: flex;
            gap: 0.75rem;
            position: relative;
            z-index: 1;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 600;
            font-size: 0.95rem;
            box-shadow: var(--shadow);
        }

        .btn-primary {
            background: var(--gradient-blue);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #2563eb 0%, #1e40af 100%);
        }

        .btn-secondary {
            background: var(--gradient-card);
            color: var(--text-color);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            background: linear-gradient(135deg, #7c3aed 0%, #6d28d9 100%);
            color: white;
        }

        [data-theme="dark"] .btn-secondary {
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        [data-theme="dark"] .btn-secondary:hover {
            color: white;
        }

        .no-orders {
            text-align: center;
            color: var(--text-muted);
            padding: 3rem;
            font-size: 1.1rem;
            font-weight: 500;
        }

        [data-theme="dark"] .no-orders {
            color: rgba(255, 255, 255, 0.8);
        }

        .alert {
            padding: 1.25rem;
            border-radius: 16px;
            margin-bottom: 1.5rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 12px;
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .alert-error {
            background: var(--gradient-danger);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .alert-success {
            background: var(--gradient-success);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .alert-warning {
            background: var(--gradient-warning);
            color: #ffffff;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        

        @media (max-width: 900px) {
            .header-content, .main {
                padding: 0 1rem;
            }
            .main {
                padding: 1.5rem 1rem;
            }
            .client-stats {
                grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
                gap: 1rem;
            }
        }
        
        @media (max-width: 600px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
                padding: 1rem;
                min-height: auto;
                overflow: hidden;
            }
            .header-left {
                margin-bottom: 0;
                width: 100%;
            }
            .header-right {
                width: 100%;
                justify-content: space-between;
                gap: 0.5rem;
                flex-wrap: wrap;
                align-items: center;
            }
            .user-menu {
                flex-direction: row;
                gap: 0.5rem;
                width: 100%;
                justify-content: space-between;
                align-items: center;
                flex-wrap: wrap;
            }
            .username {
                font-size: 0.85rem;
                flex-shrink: 0;
            }
            .username i {
                padding: 0.3rem;
                font-size: 0.85rem;
            }

            .logout-btn {
                padding: 0.3rem 0.6rem !important;
                font-size: 0.85rem !important;
                flex-shrink: 0;
                white-space: nowrap;
            }
            .main {
                padding: 1rem 0.5rem;
            }
            .client-header {
                padding: 1.5rem 1rem;
                margin-bottom: 1.5rem;
                border-radius: 16px;
            }
            .client-name {
                font-size: 1.5rem;
            }
            .client-avatar {
                width: 80px;
                height: 80px;
                font-size: 2rem;
            }
            .client-stats {
                grid-template-columns: 1fr;
                gap: 1rem;
                margin-top: 1.5rem;
            }
            .stat-item {
                padding: 1.25rem;
            }
            .stat-number {
                font-size: 2rem;
            }
            .orders-section {
                padding: 1.5rem 1rem;
                border-radius: 16px;
            }
            .order-card {
                padding: 1.25rem 1rem;
                border-radius: 12px;
            }
            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }
            .order-title {
                font-size: 1.1rem;
            }
            .order-actions {
                flex-direction: column;
                gap: 0.5rem;
                width: 100%;
            }
            .btn {
                width: 100%;
                justify-content: center;
                padding: 0.75rem 1rem;
            }
            .modal-window {
                padding: 1.5rem 1rem;
                margin: 1rem;
                border-radius: 16px;
            }
            
            /* Мобильные стили для блока действий */
            .user-actions {
                flex-direction: column;
                gap: 1rem;
                align-items: stretch;
            }
            
            .actions-left {
                justify-content: flex-start;
            }
            
            .actions-center {
                justify-content: left;
            }
            .actions-right {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .actions-right .btn {
                width: 100%;
                justify-content: center;
            }
        }
        @media (max-width: 420px) {
            .header-content {
                padding: 0.5rem;
                gap: 0.75rem;
            }
            .header-right {
                gap: 0.3rem;
            }
            .user-menu {
                gap: 0.3rem;
            }
            .username {
                font-size: 0.8rem;
            }
            .username i {
                padding: 0.25rem;
                font-size: 0.8rem;
            }

            .logout-btn {
                padding: 0.25rem 0.5rem !important;
                font-size: 0.8rem !important;
            }
            .client-stats {
                grid-template-columns: 1fr;
            }
            .orders-section {
                padding: 0.5rem 0.1rem;
            }
            .order-card {
                padding: 0.7rem 0.2rem;
            }
        }
        /* Горизонтальный скролл для заказов на мобильных */
        @media (max-width: 600px) {
            .orders-section {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }

        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        .modal-window {
            background: none;
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            padding: 2.5rem 2rem 2rem 2rem;
            min-width: 400px;
            max-width: 95vw;
            width: 100%;
            position: relative;
            animation: modalPop 0.3s ease;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Стили для модального окна уведомлений */
        .modal {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease;
        }
        
        .modal-content {
            background: rgba(30, 41, 59, 0.9);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            box-shadow: var(--shadow-xl);
            padding: 2.5rem 2rem 2rem 2rem;
            min-width: 400px;
            max-width: 95vw;
            width: 100%;
            position: relative;
            animation: modalPop 0.3s ease;
            backdrop-filter: blur(10px);
        }
        .modal-close {
            position: absolute;
            top: 12px; right: 18px;
            background: none;
            border: none;
            font-size: 2rem;
            color: var(--text-muted);
            cursor: pointer;
            transition: color 0.2s;
        }
        .modal-close:hover {
            color: var(--danger-color);
        }

        /* Стили для крестика в модальном окне уведомлений */
        .modal .close {
            position: absolute;
            top: 15px; right: 20px;
            background: none;
            border: none;
            font-size: 2.5rem;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 10;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        
        .modal .close:hover {
            color: #ffffff;
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        /* Стили для содержимого уведомления */
        #notificationContent {
            color: #ffffff;
            font-size: 1.1rem;
            line-height: 1.6;
            text-align: center;
        }

        #notificationContent h2 {
            color: #06b6d4;
            font-size: 1.8rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        #notificationContent p {
            margin-bottom: 1.5rem;
            color: rgba(255, 255, 255, 0.9);
        }

        #notificationContent .btn {
            background: var(--gradient-blue);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        #notificationContent .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        @keyframes modalPop {
            from { transform: scale(0.95); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        @media (max-width: 480px) {
            .modal-window {
                padding: 1.2rem 0.5rem 1rem 0.5rem;
                min-width: 0;
            }
            .modal-content {
                padding: 1.5rem 1rem;
                margin: 1rem;
                min-width: 0;
            }
            .modal .close {
                top: 10px;
                right: 15px;
                font-size: 2rem;
                width: 35px;
                height: 35px;
            }
            #notificationContent {
                font-size: 1rem;
            }
            #notificationContent h2 {
                font-size: 1.5rem;
            }
        }
        .footer {
            background: transparent;
            padding: 1rem;
            text-align: center;
            color: var(--text-muted);
            border-top: 1px solid var(--border-color);
        }
        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
        }
        .footer-content p {
            margin: 0;
        }
        .footer-content a {
            color: var(--primary-color);
            text-decoration: none;
        }
        
        /* Стили для отображения этапов работы */
        .order-stages {
            margin-top: 1rem;
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 16px;
            padding: 1.5rem;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 1;
        }
        
        .order-stages h4 {
            font-size: 1.1rem;
            margin-bottom: 1rem;
            color: #ffffff;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .order-stages ul {
            list-style: none;
            padding-left: 0;
            margin: 0;
        }
        
        .order-stages li {
            display: flex;
            align-items: center;
            font-size: 1rem;
            margin: 0.5rem 0;
            padding: 0.5rem 0;
            font-weight: 500;
        }
        
        .order-stages li i {
            margin-right: 0.75rem;
            width: 20px;
            text-align: center;
            font-size: 1.1rem;
        }
        
        .order-stages li.todo i {
            color: #fbbf24;
        }
        
        .order-stages li.done i {
            color: #10b981;
        }
        
        .order-stages li.todo {
            color: rgba(255, 255, 255, 0.7);
        }
        
        .order-stages li.done {
            color: #ffffff;
        }
    </style>
</head>
<body class="h-full bg-slate-950 text-slate-100 antialiased font-sans [font-family:Inter,ui-sans-serif,system-ui]">
    <!-- Background gradients -->
    <div class="fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -top-40 -right-32 h-[42rem] w-[42rem] rounded-full bg-gradient-to-br from-indigo-500/30 via-fuchsia-500/20 to-emerald-400/20 blur-3xl"></div>
        <div class="absolute -bottom-40 -left-20 h-[38rem] w-[38rem] rounded-full bg-gradient-to-tr from-purple-500/20 via-blue-500/20 to-cyan-400/20 blur-3xl"></div>
        <div class="absolute inset-0 bg-[radial-gradient(60%_50%_at_50%_0%,rgba(255,255,255,0.06),rgba(0,0,0,0)_70%)]"></div>
    </div>

    <header class="sm:px-6 sm:pt-6 pt-4 pr-4 pb-2 pl-4">
        <div class="max-w-[72rem] flex mr-auto ml-auto items-center justify-between">
            <div class="flex items-center gap-2 text-sm text-slate-300">
                <span>Личный кабинет</span>
            </div>
            <div class="flex gap-2 items-center">
                <span class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md">
                    <i class="fas fa-user h-4 w-4"></i>
                    <?php echo htmlspecialchars($_SESSION['username']); ?>
                </span>
                
                <form method="POST" action="logout" style="display: inline;">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
                        <i class="fas fa-sign-out-alt h-4 w-4"></i>
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="px-4 pb-24 sm:px-6">
        <section class="mx-auto max-w-[72rem] space-y-6">
            <!-- Hero / Profile -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl ring-1 ring-white/10">
                <div class="absolute inset-x-0 -top-24 h-48 bg-gradient-to-b from-white/10 to-transparent"></div>
                <div class="p-5 sm:p-7">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-3">
                            <div class="relative grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-indigo-500/70 to-fuchsia-500/70 text-white ring-1 ring-white/20 shadow-lg">
                                <i class="fas fa-user h-6 w-6"></i>
                            </div>
                            <div>
                                <h1 class="text-[22px] sm:text-2xl font-semibold tracking-tight">
                                    <?php echo htmlspecialchars($clientName); ?>
                                </h1>
                                <p class="text-sm text-slate-300">
                                    Логин: <?php echo htmlspecialchars($_SESSION['username']); ?> • Telegram: <?php echo htmlspecialchars($currentUser['telegram_username']); ?>
                                </p>
                            </div>
                        </div>
                    </div>

                    <!-- Stats -->
                    <div class="mt-5 grid grid-cols-2 sm:grid-cols-4 gap-4">
                        <div class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-white"><?php echo count($orders); ?></div>
                                <div class="text-sm text-slate-300">Всего заказов</div>
                            </div>
                        </div>
                        <div class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-yellow-300"><?php echo count(array_filter($orders, fn($o) => ($o['work_status'] ?? 'pending') === 'pending')); ?></div>
                                <div class="text-sm text-slate-300">Ожидающих</div>
                            </div>
                        </div>
                        <div class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-blue-300"><?php echo count(array_filter($orders, fn($o) => ($o['work_status'] ?? '') === 'in_progress')); ?></div>
                                <div class="text-sm text-slate-300">В работе</div>
                            </div>
                        </div>
                        <div class="relative rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10">
                            <div class="text-center">
                                <div class="text-2xl font-bold text-green-300"><?php echo count(array_filter($orders, fn($o) => in_array(($o['work_status'] ?? ''), ['completed','done','finished']))); ?></div>
                                <div class="text-sm text-slate-300">Завершённых</div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div class="flex flex-wrap gap-2">
                            <a href="client_requests" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                                <i class="fas fa-list h-4 w-4"></i>
                                Мои заявки
                            </a>
                            <a href="id.php" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                                <i class="fas fa-calendar-alt h-4 w-4"></i>
                                Расписание
                            </a>
                            <a href="telegram_settings_user.php" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                                <i class="fab fa-telegram h-4 w-4"></i>
                                Telegram
                            </a>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button id="openOrderRequestModal" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                                <i class="fas fa-plus h-4 w-4"></i>
                                Создать заказ
                            </button>
                            <button id="openChangePassModal" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                                <i class="fas fa-key h-4 w-4"></i>
                                Сменить пароль
                            </button>
                        </div>
                    </div>
                </div>
            </div>

    <!-- Модальное окно смены пароля -->
    <div id="changePassModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <button class="absolute top-4 right-4 text-slate-400 hover:text-white transition" id="closeChangePassModal" type="button" title="Закрыть">
                <i class="fas fa-times h-5 w-5"></i>
            </button>
            <h2 class="text-xl font-bold text-white mb-6 text-center">Сменить пароль</h2>
            
            <?php if ($changePassError): ?>
                <div class="mb-4 p-3 rounded-lg bg-red-500/20 border border-red-500/30 text-red-300 text-sm">
                    <i class="fas fa-exclamation-triangle mr-2"></i>
                    <?php echo htmlspecialchars($changePassError); ?>
                </div>
            <?php endif; ?>
            <?php if ($changePassSuccess): ?>
                <div class="mb-4 p-3 rounded-lg bg-green-500/20 border border-green-500/30 text-green-300 text-sm">
                    <i class="fas fa-check-circle mr-2"></i>
                    <?php echo htmlspecialchars($changePassSuccess); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" class="space-y-4">
                <input type="hidden" name="change_password" value="1">
                <div>
                    <label for="old_password" class="block text-sm font-medium text-slate-300 mb-2">Старый пароль</label>
                    <input type="password" id="old_password" name="old_password" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent" required autocomplete="current-password">
                </div>
                <div>
                    <label for="new_password" class="block text-sm font-medium text-slate-300 mb-2">Новый пароль</label>
                    <input type="password" id="new_password" name="new_password" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent" required autocomplete="new-password">
                </div>
                <div>
                    <label for="confirm_password" class="block text-sm font-medium text-slate-300 mb-2">Подтвердите новый пароль</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent" required autocomplete="new-password">
                </div>
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                    <i class="fas fa-key h-4 w-4"></i>
                    Сменить пароль
                </button>
            </form>
        </div>
    </div>

    <!-- Модальное окно заявки на заказ -->
    <div id="orderRequestModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-2xl w-full shadow-2xl">
            <button class="absolute top-4 right-4 text-slate-400 hover:text-white transition" id="closeOrderRequestModal" type="button" title="Закрыть">
                <i class="fas fa-times h-5 w-5"></i>
            </button>
            <div class="text-center mb-6">
                <h2 class="text-2xl font-bold text-white mb-2">Новая заявка на заказ</h2>
                <p class="text-slate-300">7 семестр • Данные ФИО берутся из вашего профиля</p>
            </div>
            
            <div id="orderReqAlert" class="mb-4 p-3 rounded-lg text-sm hidden"></div>
            
            <form id="orderRequestForm" class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Фамилия и имя</label>
                    <input type="text" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-slate-400" value="<?php echo htmlspecialchars($clientName); ?>" disabled>
                </div>
                
                <div>
                    <label for="req_work_type" class="block text-sm font-medium text-slate-300 mb-2">Тип работы <span class="text-red-400">*</span></label>
                    <select id="req_work_type" name="work_type" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent" required>
                        <option value="">Выберите тип работы</option>
                        <option value="coursework" <?php echo !empty($orderRequestMeta['coursework']['is_open'])? '' : 'disabled'; ?>>Курсовая работа</option>
                        <option value="production_practice" <?php echo !empty($orderRequestMeta['production_practice']['is_open'])? '' : 'disabled'; ?>>Производственная практика</option>
                        <option value="study_practice" <?php echo !empty($orderRequestMeta['study_practice']['is_open'])? '' : 'disabled'; ?>>Учебная практика</option>
                    </select>
                    <div id="req_price_hint" class="mt-1 text-xs text-slate-400 hidden"></div>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-slate-300 mb-2">Семестр</label>
                    <input type="text" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-slate-400" value="7 семестр" disabled>
                </div>
                
                <div>
                    <label for="req_topic_number" class="block text-sm font-medium text-slate-300 mb-2">Номер темы <span class="text-red-400">*</span></label>
                    <input type="number" id="req_topic_number" name="topic_number" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent" placeholder="Например: 17" min="1" max="1000" required>
                </div>
                
                <div>
                    <label for="req_topic_description" class="block text-sm font-medium text-slate-300 mb-2">Описание темы</label>
                    <textarea id="req_topic_description" name="topic_description" class="w-full px-3 py-2 bg-white/5 border border-white/10 rounded-lg text-white placeholder-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:border-transparent resize-vertical" rows="4" placeholder="Напишите название темы"></textarea>
                </div>
                
                <button type="submit" class="w-full inline-flex items-center justify-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                    <i class="fas fa-paper-plane h-4 w-4"></i>
                    Отправить заявку
                </button>
            </form>
            
            <p class="mt-4 text-center text-xs text-slate-400">Заявки доступны только для 7 семестра</p>
        </div>
    </div>

    <!-- Успех отправки заявки -->
    <div id="orderRequestSuccess" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display:none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-green-500/20 rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <button class="absolute top-4 right-4 text-slate-400 hover:text-white transition" id="closeOrderRequestSuccess" type="button" title="Закрыть">
                <i class="fas fa-times h-5 w-5"></i>
            </button>
            <div class="text-center">
                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-green-500/20 flex items-center justify-center">
                    <i class="fas fa-check-circle text-green-400 text-2xl"></i>
                </div>
                <h2 class="text-xl font-bold text-white mb-2">Заявка отправлена</h2>
                <p class="text-slate-300 mb-6">Мы уведомим, когда администратор возьмёт её в работу</p>
                <div class="flex gap-3 justify-center">
                    <a href="client_requests" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                        <i class="fas fa-list h-4 w-4"></i>
                        Мои заявки
                    </a>
                    <button type="button" id="backSuccessModal" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                        <i class="fas fa-arrow-left h-4 w-4"></i>
                        Назад
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Уведомления -->
    <?php if (isset($error)): ?>
        <div class="fixed top-4 right-4 z-50">
            <div class="p-4 rounded-lg bg-red-500/20 border border-red-500/30 text-red-300 text-sm backdrop-blur-md">
                <i class="fas fa-exclamation-triangle mr-2"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        </div>
    <?php endif; ?>


            <!-- Debt Alert -->
            <?php if ($totalDebt > 0): ?>
                <div class="relative overflow-hidden rounded-2xl border border-red-500/20 bg-red-500/5 backdrop-blur-xl shadow-2xl ring-1 ring-red-500/20">
                    <div class="p-4 text-center">
                        <div class="inline-flex items-center gap-2 rounded-full bg-red-500/20 px-4 py-2 text-sm text-red-300 ring-1 ring-red-500/30">
                            <i class="fas fa-exclamation-circle h-4 w-4"></i>
                            Общая сумма к оплате: <?php echo number_format($totalDebt, 2, ',', ' '); ?> ₽
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Orders Section -->
            <section class="space-y-3" aria-labelledby="ordersTitle">
                <div class="flex items-center gap-2 px-1">
                    <i class="fas fa-list h-5 w-5 text-slate-300"></i>
                    <h2 id="ordersTitle" class="text-xl font-semibold tracking-tight">Мои заказы</h2>
                </div>

                <div class="grid grid-cols-1 gap-3">
                    <?php if (empty($orders)): ?>
                        <div class="rounded-2xl border border-white/10 bg-white/5 p-8 text-center ring-1 ring-white/10">
                            <div class="mx-auto mb-3 grid h-10 w-10 place-items-center rounded-xl bg-white/10 text-slate-200">
                                <i class="fas fa-inbox h-5 w-5"></i>
                            </div>
                            <p class="text-base font-medium tracking-tight">У вас пока нет заказов</p>
                            <p class="mt-1 text-sm text-slate-300">Обратитесь к администратору для создания заказа или оставьте заявку на заказ в форме выше</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($orders as $order): ?>
                            <article class="group relative overflow-hidden rounded-xl border border-white/10 bg-white/5 p-4 ring-1 ring-white/10 backdrop-blur-xl transition hover:bg-white/[0.08]">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-2">
                                            <h3 class="text-base font-medium tracking-tight">Заказ #<?php echo $order['id']; ?></h3>
                                            <?php 
                                            $workStatus = $order['work_status'] ?? 'pending';
                                            $statusClass = match($workStatus) {
                                                'pending' => 'bg-yellow-500/20 text-yellow-300 ring-yellow-500/30',
                                                'in_progress' => 'bg-blue-500/20 text-blue-300 ring-blue-500/30',
                                                'completed', 'done', 'finished' => 'bg-green-500/20 text-green-300 ring-green-500/30',
                                                default => 'bg-gray-500/20 text-gray-300 ring-gray-500/30'
                                            };
                                            $statusText = match($workStatus) {
                                                'pending' => 'Ожидает',
                                                'in_progress' => 'В работе',
                                                'completed', 'done', 'finished' => 'Завершён',
                                                default => 'Ожидает'
                                            };
                                            ?>
                                            <span class="inline-flex items-center rounded-full bg-white/5 px-2.5 py-1 text-xs text-slate-200 ring-1 ring-white/10 <?php echo $statusClass; ?>">
                                                <?php echo $statusText; ?>
                                            </span>
                                            <?php if (!empty($order['debt_amount']) && $order['debt_amount'] > 0): ?>
                                                <span class="inline-flex items-center rounded-full bg-red-500/20 px-2.5 py-1 text-xs text-red-300 ring-1 ring-red-500/30">
                                                    Не оплачено
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="space-y-1 text-sm text-slate-300">
                                            <p><span class="font-medium">Номер:</span> <?php echo htmlspecialchars($order['topic_number'] ?? 'Не указан'); ?></p>
                                            <p><span class="font-medium">Тема:</span> <?php echo htmlspecialchars($order['topic_description'] ?? 'Не указана'); ?></p>
                                            <p><span class="font-medium">Дата:</span> <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?></p>
                                            <?php if (!empty($order['work_type'])): ?>
                                                <p><span class="font-medium">Тип:</span> 
                                                    <?php 
                                                    echo match($order['work_type']) {
                                                        'coursework' => 'Курсовая работа',
                                                        'production_practice' => 'Производственная практика',
                                                        'study_practice' => 'Учебная практика',
                                                        default => htmlspecialchars($order['work_type'])
                                                    };
                                                    ?>
                                                </p>
                                            <?php endif; ?>
                                            <?php if (!empty($order['debt_amount']) && $order['debt_amount'] > 0): ?>
                                                <p class="text-red-300"><span class="font-medium">Долг:</span> <?php echo number_format($order['debt_amount'], 2, ',', ' '); ?> ₽</p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if (($order['work_status'] ?? '') === 'in_progress'): ?>
                                            <?php 
                                            try {
                                                $stages = $workStage->getByOrderId($order['id']);
                                                if (!empty($stages)):
                                            ?>
                                                <div class="mt-3 rounded-lg bg-white/5 p-3 ring-1 ring-white/10">
                                                    <h4 class="text-sm font-medium text-white mb-2 flex items-center gap-1">
                                                        <i class="fas fa-tasks h-4 w-4"></i>
                                                        Этапы работы
                                                    </h4>
                                                    <div class="space-y-1">
                                                        <?php foreach ($stages as $stage): ?>
                                                            <div class="flex items-center gap-2 text-sm">
                                                                <i class="fas <?php echo $stage['is_completed'] ? 'fa-check-circle text-green-400' : 'fa-hourglass-half text-yellow-400'; ?> h-3 w-3"></i>
                                                                <span class="<?php echo $stage['is_completed'] ? 'text-white' : 'text-slate-300'; ?>">
                                                                    <?php echo htmlspecialchars($stage['stage_name']); ?>
                                                                </span>
                                                            </div>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                            <?php 
                                                endif;
                                            } catch (Exception $e) {
                                                // Игнорируем ошибки получения этапов
                                            }
                                            ?>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="shrink-0">
                                        <a href="client_order_details?id=<?php echo $order['id']; ?>" class="inline-flex items-center gap-2 rounded-full bg-white/10 px-3 py-1.5 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                                            <i class="fas fa-eye h-3.5 w-3.5"></i>
                                            Подробнее
                                        </a>
                                    </div>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>
        </section>
    <!-- Кнопка «Вверх» -->
    <button type="button" class="fixed bottom-6 right-6 w-12 h-12 rounded-full bg-white/10 backdrop-blur-md border border-white/10 text-white hover:bg-white/20 transition-all duration-300 opacity-0 pointer-events-none" id="scrollTopBtn" aria-label="Наверх" title="Наверх">
        <i class="fas fa-arrow-up"></i>
    </button>

    <footer class="mt-12 px-4 pb-6">
        <div class="max-w-[72rem] mx-auto text-center text-sm text-slate-400">
            <p>© 2026 ImsitID. Все права защищены.</p>
            <div class="mt-2 flex justify-center gap-4">
                <a href="user_agreement" target="_blank" class="hover:text-white transition">Пользовательское соглашение</a>
                <span>•</span>
                <a href="https://t.me/cowgivesmilk" target="_blank" class="hover:text-white transition">Made by ImsitShop <i class="fas fa-heart text-red-400"></i></a>
            </div>
        </div>
    </footer>

    <!-- Модальное окно уведомлений -->
    <div id="notificationModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm z-50 flex items-center justify-center p-4" style="display: none;">
        <div class="bg-slate-800/95 backdrop-blur-xl border border-white/10 rounded-2xl p-8 max-w-md w-full shadow-2xl">
            <button class="absolute top-4 right-4 text-slate-400 hover:text-white transition" onclick="closeNotificationModal()">
                <i class="fas fa-times h-5 w-5"></i>
            </button>
            <div id="notificationContent">
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-blue-500/20 flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-400 text-2xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">Добро пожаловать в ImsitShop!</h2>
                    <p class="text-slate-300 mb-6">Мы рады вас приветствовать! Обязательно подпишитесь на нас в телеграмм - @imsitshop. Там будут публиковаться все новости и объявления касаемо нашего проекта.</p>
                    <button onclick="closeNotificationModal()" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                        <i class="fas fa-check h-4 w-4"></i>
                        Понятно
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Кнопка «Вверх»
        (function(){
            const btn = document.getElementById('scrollTopBtn');
            function onScroll(){
                if (window.scrollY > 300) {
                    btn.classList.remove('opacity-0', 'pointer-events-none');
                    btn.classList.add('opacity-100');
                } else {
                    btn.classList.add('opacity-0', 'pointer-events-none');
                    btn.classList.remove('opacity-100');
                }
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            btn.addEventListener('click', function(){
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            // Инициализация состояния при загрузке
            onScroll();
        })();

        // Установка темной темы при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            document.documentElement.setAttribute('data-theme', 'dark');
        });



        // Модальные окна
        const openBtn = document.getElementById('openChangePassModal');
        const closeBtn = document.getElementById('closeChangePassModal');
        const modal = document.getElementById('changePassModal');
        if (openBtn && closeBtn && modal) {
            openBtn.onclick = () => { modal.style.display = 'flex'; };
            closeBtn.onclick = () => { modal.style.display = 'none'; };
            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') modal.style.display = 'none';
            });
            modal.addEventListener('click', function(e) {
                if (e.target === modal) modal.style.display = 'none';
            });
        }

        // Заявка на заказ
        const reqOpen = document.getElementById('openOrderRequestModal');
        const reqClose = document.getElementById('closeOrderRequestModal');
        const reqModal = document.getElementById('orderRequestModal');
        if (reqOpen && reqClose && reqModal) {
            reqOpen.onclick = () => { reqModal.style.display = 'flex'; };
            reqClose.onclick = () => { reqModal.style.display = 'none'; };
            window.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') reqModal.style.display = 'none';
            });
            reqModal.addEventListener('click', function(e) {
                if (e.target === reqModal) reqModal.style.display = 'none';
            });
        }

        const orderRequestForm = document.getElementById('orderRequestForm');
        const orderReqAlert = document.getElementById('orderReqAlert');
        const orderReqSuccess = document.getElementById('orderRequestSuccess');
        const closeOrderReqSuccess = document.getElementById('closeOrderRequestSuccess');
        const backSuccessModal = document.getElementById('backSuccessModal');
        const reqWorkType = document.getElementById('req_work_type');
        const reqPriceHint = document.getElementById('req_price_hint');
        const priceMap = <?php echo json_encode($orderRequestMeta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        if (reqWorkType && reqPriceHint) {
            reqWorkType.addEventListener('change', function() {
                const v = this.value;
                if (priceMap && priceMap[v] && typeof priceMap[v].price !== 'undefined') {
                    const price = Number(priceMap[v].price || 0);
                    if (!isNaN(price) && price > 0) {
                        reqPriceHint.classList.remove('hidden');
                        reqPriceHint.textContent = 'Примерная стоимость: ' + price.toLocaleString('ru-RU') + ' ₽';
                    } else {
                        reqPriceHint.classList.remove('hidden');
                        reqPriceHint.textContent = 'Примерная стоимость: уточняется';
                    }
                } else {
                    reqPriceHint.classList.add('hidden');
                    reqPriceHint.textContent = '';
                }
            });
        }
        if (orderRequestForm && orderReqAlert) {
            orderRequestForm.addEventListener('submit', async function(e){
                e.preventDefault();
                orderReqAlert.classList.add('hidden');
                orderReqAlert.className = 'mb-4 p-3 rounded-lg text-sm hidden';
                try {
                    const formData = new FormData(orderRequestForm);
                    console.log('Отправляем заявку:', Object.fromEntries(formData));
                    
                    const res = await fetch('submit_order_request', { method: 'POST', body: formData });
                    console.log('Ответ сервера:', res.status, res.statusText);
                    
                    // Проверяем тип контента
                    const contentType = res.headers.get('content-type');
                    console.log('Тип контента:', contentType);
                    
                    if (!contentType || !contentType.includes('application/json')) {
                        const text = await res.text();
                        console.error('Сервер вернул не JSON:', text);
                        throw new Error('Сервер вернул неверный формат данных');
                    }
                    
                    const data = await res.json();
                    console.log('Данные ответа:', data);
                    
                    if (data.ok) {
                        // закрываем форму
                        if (reqModal) reqModal.style.display = 'none';
                        // показываем успех
                        if (orderReqSuccess) orderReqSuccess.style.display = 'flex';
                        orderRequestForm.reset();
                    } else {
                        orderReqAlert.classList.remove('hidden');
                        orderReqAlert.classList.add('bg-red-500/20', 'border-red-500/30', 'text-red-300');
                        orderReqAlert.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>' + (data.error || 'Ошибка');
                    }
                } catch (err) {
                    console.error('Ошибка отправки заявки:', err);
                    orderReqAlert.classList.remove('hidden');
                    orderReqAlert.classList.add('bg-red-500/20', 'border-red-500/30', 'text-red-300');
                    orderReqAlert.innerHTML = '<i class="fas fa-exclamation-triangle mr-2"></i>Ошибка: ' + (err.message || 'Неизвестная ошибка');
                }
            });
        }

        if (closeOrderReqSuccess && orderReqSuccess) {
            closeOrderReqSuccess.addEventListener('click', () => { orderReqSuccess.style.display = 'none'; });
        }
        if (backSuccessModal && orderReqSuccess) {
            backSuccessModal.addEventListener('click', () => { orderReqSuccess.style.display = 'none'; });
        }

        // Система уведомлений
        let currentNotification = null;

        // Функция загрузки и показа уведомлений
        async function loadAndShowNotifications() {
            try {
                const response = await fetch('api/get_notifications.php?context=dashboard');
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
            let icon, colorClass, bgClass;
            switch(notification.type) {
                case 'success':
                    icon = 'fas fa-check-circle';
                    colorClass = 'text-green-400';
                    bgClass = 'bg-green-500/20';
                    break;
                case 'warning':
                    icon = 'fas fa-exclamation-triangle';
                    colorClass = 'text-yellow-400';
                    bgClass = 'bg-yellow-500/20';
                    break;
                case 'error':
                    icon = 'fas fa-times-circle';
                    colorClass = 'text-red-400';
                    bgClass = 'bg-red-500/20';
                    break;
                default:
                    icon = 'fas fa-info-circle';
                    colorClass = 'text-blue-400';
                    bgClass = 'bg-blue-500/20';
            }
            
            content.innerHTML = `
                <div class="text-center">
                    <div class="w-16 h-16 mx-auto mb-4 rounded-full ${bgClass} flex items-center justify-center">
                        <i class="${icon} ${colorClass} text-2xl"></i>
                    </div>
                    <h2 class="text-xl font-bold text-white mb-2">${notification.title}</h2>
                    <p class="text-slate-300 mb-6">${notification.message}</p>
                    <button onclick="closeNotificationModal()" class="inline-flex items-center gap-2 rounded-lg bg-white/10 px-4 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/20 hover:ring-white/20 active:scale-[0.98] transition">
                        <i class="fas fa-check h-4 w-4"></i>
                        Понятно
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
        const notificationModal = document.getElementById('notificationModal');
        if (notificationModal) {
            notificationModal.addEventListener('click', function(e) {
                if (e.target === this) {
                    closeNotificationModal();
                }
            });
        }

        // Закрытие по Escape
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                const notificationModal = document.getElementById('notificationModal');
                if (notificationModal && notificationModal.style.display === 'flex') {
                    closeNotificationModal();
                }
            }
        });

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

        // Загружаем уведомления при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            // Сохраняем группу пользователя в cookies если она есть в профиле
            const userGroup = '<?php echo htmlspecialchars($currentUser['group'] ?? ''); ?>';
            if (userGroup && ['Исип-05', 'Исип-06'].includes(userGroup)) {
                setCookie('selected_group', userGroup, 30);
            }
            
            // Небольшая задержка для лучшего UX
            setTimeout(loadAndShowNotifications, 1000);
        });
    </script>
</body>
</html> 