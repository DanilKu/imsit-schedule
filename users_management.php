<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Настройки кодировки для корректной работы с русскими символами
mb_internal_encoding('UTF-8');
mb_http_output('UTF-8');
mb_http_input('P'); // POST данные
mb_http_input('G'); // GET данные
mb_language('uni');
mb_regex_encoding('UTF-8');

// Установка заголовков для правильной кодировки
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

// Функция для очистки и валидации русских символов
function clean_russian_text($text) {
    $text = trim($text);
    $text = mb_convert_encoding($text, 'UTF-8', 'UTF-8');
    return $text;
}

try {
    require_once 'config/auth.php';
    require_once 'config/database.php';
} catch (Exception $e) {
    die("Ошибка подключения: " . $e->getMessage());
}

// Проверка прав администратора
requireAdmin();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                $username = clean_russian_text($_POST['username']);
                $password = $_POST['password'];
                $client_name = clean_russian_text($_POST['client_name']);
                $telegram_username = clean_russian_text($_POST['telegram_username']);
                $group = $_POST['group'] ?? null;
                
                if (empty($username) || empty($password) || empty($client_name) || empty($telegram_username)) {
                    $error = 'Заполните все обязательные поля';
                } else {
                    try {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO users (username, password, client_name, telegram_username, `group`, role) 
                                VALUES (:username, :password, :client_name, :telegram_username, :group, 'client')";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([
                            'username' => $username,
                            'password' => $hashedPassword,
                            'client_name' => $client_name,
                            'telegram_username' => $telegram_username,
                            'group' => $group
                        ]);
                        $message = 'Пользователь создан успешно';
                    } catch (PDOException $e) {
                        $error = 'Ошибка создания пользователя: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'reset_password':
                $user_id = $_POST['user_id'];
                $new_password = $_POST['new_password'];
                
                if (empty($new_password)) {
                    $error = 'Введите новый пароль';
                } else {
                    try {
                        $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                        $sql = "UPDATE users SET password = :password WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['password' => $hashedPassword, 'id' => $user_id]);
                        $message = 'Пароль изменён успешно';
                    } catch (PDOException $e) {
                        $error = 'Ошибка изменения пароля: ' . $e->getMessage();
                    }
                }
                break;
                
            case 'toggle_status':
                $user_id = $_POST['user_id'];
                $new_status = $_POST['new_status'];
                
                try {
                    $sql = "UPDATE users SET status = :status WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['status' => $new_status, 'id' => $user_id]);
                    $message = 'Статус пользователя изменён';
                } catch (PDOException $e) {
                    $error = 'Ошибка изменения статуса: ' . $e->getMessage();
                }
                break;
                
            case 'update_group':
                $user_id = $_POST['user_id'];
                $new_group = $_POST['new_group'];
                
                try {
                    $sql = "UPDATE users SET `group` = :group WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['group' => $new_group, 'id' => $user_id]);
                    $message = 'Группа пользователя изменена';
                } catch (PDOException $e) {
                    $error = 'Ошибка изменения группы: ' . $e->getMessage();
                }
                break;
                
            case 'delete_request':
                $request_id = $_POST['request_id'];
                
                try {
                    $sql = "DELETE FROM registration_requests WHERE id = :id";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute(['id' => $request_id]);
                    $message = 'Заявка на регистрацию удалена';
                } catch (PDOException $e) {
                    $error = 'Ошибка удаления заявки: ' . $e->getMessage();
                }
                break;
        }
    }
}

// Получение списка пользователей
try {
    $sql = "SELECT * FROM users ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    $users = [];
    $error = 'Ошибка получения списка пользователей: ' . $e->getMessage();
}

// Получение заявок на регистрацию
try {
    $sql = "SELECT * FROM registration_requests ORDER BY created_at DESC";
    $stmt = $pdo->query($sql);
    $requests = $stmt->fetchAll();
} catch (PDOException $e) {
    $requests = [];
}

$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <title>Управление пользователями - Система учёта работ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        /* Специальные стили для страницы управления пользователями */
        .users-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        /* Кнопка «Вверх» */
        .scroll-top-btn {
            position: fixed;
            right: 20px;
            bottom: 24px;
            width: 44px;
            height: 44px;
            border-radius: 50%;
            border: none;
            outline: none;
            cursor: pointer;
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            color: #fff;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            box-shadow: var(--shadow);
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px) scale(.95);
            pointer-events: none;
            transition: opacity .25s ease, transform .25s ease, box-shadow .2s ease;
        }
        .scroll-top-btn.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        .scroll-top-btn:hover { box-shadow: var(--shadow-hover); }
        .scroll-top-btn i { font-size: 1rem; }
        
        .users-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .users-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .users-title i {
            color: var(--accent-color);
        }
        
        .users-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
            transition: all 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--accent-color);
            margin-bottom: 5px;
        }
        
        .stat-label {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .users-sections {
            display: grid;
            gap: 30px;
        }
        
        .section-card {
            background: var(--bg-primary);
            border-radius: 12px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }
        
        .section-header {
            background: var(--bg-secondary);
            padding: 20px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
            margin: 0;
        }
        
        .section-title i {
            color: var(--accent-color);
        }
        
        .section-content {
            padding: 20px;
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 0;
        }
        
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-color);
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-input {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-input:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .users-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .users-table th {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.9rem;
        }
        
        .users-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .users-table tbody tr {
            transition: background-color 0.3s ease;
        }
        
        .users-table tbody tr:hover {
            background: var(--bg-secondary);
        }
        
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-primary {
            background: var(--accent-color);
            color: white;
        }
        
        .badge-secondary {
            background: var(--text-muted);
            color: white;
        }
        
        .badge-success {
            background: var(--success-color);
            color: white;
        }
        
        .badge-danger {
            background: var(--danger-color);
            color: white;
        }
        
        .badge-warning {
            background: var(--warning-color);
            color: var(--text-primary);
        }
        
        .btn-group {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 8px 12px;
            font-size: 0.8rem;
            border-radius: 6px;
        }
        
        .btn-warning {
            background: var(--warning-color);
            color: var(--text-primary);
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-warning:hover {
            background: #e0a800;
            transform: translateY(-1px);
        }
        
        .btn-danger {
            background: #dc3545;
            color: white;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-danger:hover {
            background: #c82333;
            transform: translateY(-1px);
        }
        
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
            background: var(--bg-primary);
            margin: 10% auto;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: var(--shadow-hover);
            position: relative;
        }
        
        .close {
            position: absolute;
            right: 20px;
            top: 20px;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--text-secondary);
            transition: color 0.3s ease;
        }
        
        .close:hover {
            color: var(--text-primary);
        }
        
        .modal-content h2 {
            margin-bottom: 20px;
            color: var(--text-primary);
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        [data-theme="dark"] .alert-success {
            background: #1e4a2e;
            color: #75b798;
            border: 1px solid #2d5a3d;
        }
        
        [data-theme="dark"] .alert-error {
            background: #4a1e1e;
            color: #b77575;
            border: 1px solid #5a2d2d;
        }
        
        @media (max-width: 768px) {
            .users-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .users-table {
                font-size: 0.8rem;
            }
            
            .users-table th,
            .users-table td {
                padding: 10px 8px;
            }
            
            .btn-group {
                flex-direction: column;
                gap: 5px;
            }
        }
    </style>
</head>
<body>
    <!-- Шапка -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="admin" class="logo">
                    <i class="fas fa-arrow-left"></i>
                    Назад
                </a>
            </div>
            
            <div class="header-right">
                <button class="theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
                
                <div class="user-menu">
                    <span class="username">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                    </span>
                    
                    <?php if (isAdmin()): ?>
                        <a href="users_management" class="nav-btn">
                            <i class="fas fa-users"></i>
                            Пользователи
                        </a>
                    <?php endif; ?>
                    
                                    <form method="POST" action="logout" style="display: inline;">
                    <button type="submit" class="logout-btn" style="background: none; border: none; cursor: pointer; color: inherit; font: inherit;">
                        <i class="fas fa-sign-out-alt"></i>
                        Выйти
                    </button>
                </form>
                </div>
            </div>
        </div>
    </header>

    <!-- Основной контент -->
    <main class="users-container">
        <!-- Заголовок -->
        <div class="users-header">
            <h1 class="users-title">
                <i class="fas fa-users"></i>
                Управление пользователями
            </h1>
        </div>

        <!-- Уведомления -->
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-error">
                <i class="fas fa-exclamation-triangle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <!-- Статистика -->
        <div class="users-stats">
            <div class="stat-card">
                <div class="stat-number"><?php echo count($users); ?></div>
                <div class="stat-label">Всего пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($users, fn($u) => $u['role'] === 'admin')); ?></div>
                <div class="stat-label">Администраторов</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count(array_filter($users, fn($u) => $u['status'] === 'active')); ?></div>
                <div class="stat-label">Активных пользователей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?php echo count($requests); ?></div>
                <div class="stat-label">Заявок на регистрацию</div>
            </div>
        </div>

        <!-- Секции -->
        <div class="users-sections">
            <!-- Создание нового пользователя -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user-plus"></i>
                        Создать нового пользователя
                    </h2>
                </div>
                <div class="section-content">
                    <form method="POST" class="form">
                        <input type="hidden" name="action" value="create_user">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username" class="form-label">Логин *</label>
                                <input type="text" id="username" name="username" required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="password" class="form-label">Пароль *</label>
                                <input type="password" id="password" name="password" required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="client_name" class="form-label">Имя клиента *</label>
                                <input type="text" id="client_name" name="client_name" required class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="telegram_username" class="form-label">Telegram username *</label>
                                <input type="text" id="telegram_username" name="telegram_username" required class="form-input" placeholder="@username">
                            </div>
                            
                            <div class="form-group">
                                <label for="group" class="form-label">Группа</label>
                                <select id="group" name="group" class="form-input">
                                    <option value="">Не выбрана</option>
                                    <option value="Исип-05">Исип-05</option>
                                    <option value="Исип-06">Исип-06</option>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Создать пользователя
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Список пользователей -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i>
                        Список пользователей
                    </h2>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Логин</th>
                                    <th>Имя клиента</th>
                                    <th>Telegram</th>
                                    <th>Группа</th>
                                    <th>Роль</th>
                                    <th>Статус</th>
                                    <th>Дата создания</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><strong><?php echo htmlspecialchars($user['username']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($user['client_name']); ?></td>
                                    <td><?php echo htmlspecialchars($user['telegram_username']); ?></td>
                                    <td>
                                        <?php if (!empty($user['group'])): ?>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($user['group']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Не указана</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['role'] === 'admin' ? 'primary' : 'secondary'; ?>">
                                            <?php echo $user['role'] === 'admin' ? 'Админ' : 'Клиент'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $user['status'] === 'active' ? 'success' : 'danger'; ?>">
                                            <?php echo $user['status'] === 'active' ? 'Активен' : 'Неактивен'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($user['created_at'])); ?></td>
                                    <td>
                                        <div class="btn-group">
                                            <button onclick="showResetPassword(<?php echo $user['id']; ?>)" class="btn btn-sm btn-warning" title="Сбросить пароль">
                                                <i class="fas fa-key"></i>
                                            </button>
                                            <button onclick="showChangeGroup(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['group'] ?? ''); ?>')" class="btn btn-sm btn-info" title="Изменить группу">
                                                <i class="fas fa-users"></i>
                                            </button>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="toggle_status">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="new_status" value="<?php echo $user['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                <button type="submit" class="btn btn-sm btn-<?php echo $user['status'] === 'active' ? 'danger' : 'success'; ?>" title="<?php echo $user['status'] === 'active' ? 'Деактивировать' : 'Активировать'; ?>">
                                                    <i class="fas fa-<?php echo $user['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Заявки на регистрацию -->
            <?php if (!empty($requests)): ?>
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-user-clock"></i>
                        Заявки на регистрацию
                    </h2>
                </div>
                <div class="section-content">
                    <div class="table-responsive">
                        <table class="users-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Фамилия и имя</th>
                                    <th>Telegram</th>
                                    <th>Группа</th>
                                    <th>Статус</th>
                                    <th>Дата</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($requests as $request): ?>
                                <tr>
                                    <td><?php echo $request['id']; ?></td>
                                    <td><?php echo htmlspecialchars($request['first_name']); ?></td>
                                    <td><?php echo htmlspecialchars($request['telegram_username']); ?></td>
                                    <td>
                                        <?php if (!empty($request['group'])): ?>
                                            <span class="badge badge-info"><?php echo htmlspecialchars($request['group']); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">Не указана</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $request['status'] === 'pending' ? 'warning' : ($request['status'] === 'approved' ? 'success' : 'danger'); ?>">
                                            <?php echo $request['status'] === 'pending' ? 'Ожидает' : ($request['status'] === 'approved' ? 'Одобрена' : 'Отклонена'); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('d.m.Y H:i', strtotime($request['created_at'])); ?></td>
                                    <td>
                                        <form method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить эту заявку?');">
                                            <input type="hidden" name="action" value="delete_request">
                                            <input type="hidden" name="request_id" value="<?php echo $request['id']; ?>">
                                            <button type="submit" class="btn btn-sm btn-danger" title="Удалить заявку">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Кнопка «Вверх» -->
    <button type="button" class="scroll-top-btn" id="scrollTopBtn" aria-label="Наверх" title="Наверх" style="display:inline-flex;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Модальное окно для сброса пароля -->
    <div id="resetPasswordModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-key"></i> Сброс пароля</h2>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="reset_password">
                <input type="hidden" name="user_id" id="resetUserId">
                
                <div class="form-group">
                    <label for="new_password" class="form-label">Новый пароль</label>
                    <input type="password" id="new_password" name="new_password" required class="form-input">
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Модальное окно для изменения группы -->
    <div id="changeGroupModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-users"></i> Изменение группы</h2>
            <form method="POST" class="form">
                <input type="hidden" name="action" value="update_group">
                <input type="hidden" name="user_id" id="changeGroupUserId">
                
                <div class="form-group">
                    <label for="new_group" class="form-label">Выберите группу</label>
                    <select id="new_group" name="new_group" class="form-input">
                        <option value="">Не выбрана</option>
                        <option value="Исип-05">Исип-05</option>
                        <option value="Исип-06">Исип-06</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeGroupModal()">
                        <i class="fas fa-times"></i> Отмена
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Сохранить
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/app.js"></script>
    <script>
        // Кнопка «Вверх»
        (function(){
            const btn = document.getElementById('scrollTopBtn');
            function onScroll(){
                if (window.scrollY > 300) {
                    btn.classList.add('visible');
                } else {
                    btn.classList.remove('visible');
                }
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            btn.addEventListener('click', function(){
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
            // Инициализация состояния при загрузке
            onScroll();
        })();

        // Убеждаемся, что модальные окна закрыты при загрузке страницы
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('resetPasswordModal').style.display = 'none';
            document.getElementById('changeGroupModal').style.display = 'none';
        });

        function showResetPassword(userId) {
            document.getElementById('resetUserId').value = userId;
            document.getElementById('resetPasswordModal').style.display = 'block';
        }

        function showChangeGroup(userId, currentGroup) {
            document.getElementById('changeGroupUserId').value = userId;
            document.getElementById('new_group').value = currentGroup;
            document.getElementById('changeGroupModal').style.display = 'block';
        }

        function closeModal() {
            document.getElementById('resetPasswordModal').style.display = 'none';
        }

        function closeGroupModal() {
            document.getElementById('changeGroupModal').style.display = 'none';
        }

        // Закрытие модальных окон
        document.querySelectorAll('.close').forEach(function(closeBtn) {
            closeBtn.onclick = function() {
                closeModal();
                closeGroupModal();
            };
        });

        window.onclick = function(event) {
            var resetModal = document.getElementById('resetPasswordModal');
            var groupModal = document.getElementById('changeGroupModal');
            if (event.target == resetModal) {
                closeModal();
            }
            if (event.target == groupModal) {
                closeGroupModal();
            }
        }

        // Закрытие по Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal();
                closeGroupModal();
            }
        });
    </script>
</body>
</html>