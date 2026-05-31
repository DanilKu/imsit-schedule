<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/NotificationManager.php';

// Проверка прав администратора
requireAdmin();

$notificationManager = new NotificationManager($pdo);
$message = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_notification':
                $data = [
                    'title' => trim($_POST['title']),
                    'message' => trim($_POST['message']),
                    'type' => $_POST['type'],
                    'is_active' => isset($_POST['is_active']),
                    'show_on_login' => isset($_POST['show_on_login']),
                    'show_on_dashboard' => isset($_POST['show_on_dashboard']),
                    'target_role' => $_POST['target_role'],
                    'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null
                ];
                
                if (empty($data['title']) || empty($data['message'])) {
                    $error = 'Заполните все обязательные поля';
                } else {
                    if ($notificationManager->createNotification($data)) {
                        $message = 'Уведомление создано успешно';
                    } else {
                        $error = 'Ошибка создания уведомления';
                    }
                }
                break;
                
            case 'update_notification':
                $id = $_POST['notification_id'];
                $data = [
                    'title' => trim($_POST['title']),
                    'message' => trim($_POST['message']),
                    'type' => $_POST['type'],
                    'is_active' => isset($_POST['is_active']),
                    'show_on_login' => isset($_POST['show_on_login']),
                    'show_on_dashboard' => isset($_POST['show_on_dashboard']),
                    'target_role' => $_POST['target_role'],
                    'start_date' => !empty($_POST['start_date']) ? $_POST['start_date'] : null,
                    'end_date' => !empty($_POST['end_date']) ? $_POST['end_date'] : null
                ];
                
                if (empty($data['title']) || empty($data['message'])) {
                    $error = 'Заполните все обязательные поля';
                } else {
                    if ($notificationManager->updateNotification($id, $data)) {
                        $message = 'Уведомление обновлено успешно';
                    } else {
                        $error = 'Ошибка обновления уведомления';
                    }
                }
                break;
                
            case 'delete_notification':
                $id = $_POST['notification_id'];
                if ($notificationManager->deleteNotification($id)) {
                    $message = 'Уведомление удалено успешно';
                } else {
                    $error = 'Ошибка удаления уведомления';
                }
                break;
                
            case 'clear_logs':
                $notificationId = $_POST['notification_id'] ?? null;
                if ($notificationManager->clearNotificationLogs($notificationId)) {
                    $message = 'Логи очищены успешно';
                } else {
                    $error = 'Ошибка очистки логов';
                }
                break;
        }
    }
}

// Получение всех уведомлений
$notifications = $notificationManager->getAllNotifications();

$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Управление уведомлениями - Система учёта работ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .notifications-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .notifications-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .notifications-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .notifications-title i {
            color: var(--accent-color);
        }
        
        .notifications-grid {
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
        
        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--input-bg);
            color: var(--text-color);
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        
        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--accent-color);
        }
        
        .form-actions {
            display: flex;
            justify-content: flex-end;
            gap: 15px;
        }
        
        .notifications-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        
        .notifications-table th {
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-weight: 600;
            padding: 15px;
            text-align: left;
            border-bottom: 2px solid var(--border-color);
            font-size: 0.9rem;
        }
        
        .notifications-table td {
            padding: 15px;
            border-bottom: 1px solid var(--border-color);
            vertical-align: top;
        }
        
        .notification-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .notification-type.info {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        
        .notification-type.success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .notification-type.warning {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
        }
        
        .notification-type.error {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
        }
        
        .notification-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .notification-status.active {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
        }
        
        .notification-status.inactive {
            background: rgba(108, 117, 125, 0.1);
            color: #6c757d;
        }
        
        .notification-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 0.8rem;
        }
        
        .checkbox-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .checkbox-item input[type="checkbox"] {
            width: auto;
            margin: 0;
        }
    </style>
</head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="admin" class="logo">
                    <i class="fas fa-arrow-left"></i>
                    Назад к списку
                </a>
            </div>
            
            <div class="header-right">
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
    </header>

    <main class="notifications-container">
        <?php if ($message): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="notifications-header">
            <h1 class="notifications-title">
                <i class="fas fa-bell"></i>
                Управление уведомлениями
            </h1>
        </div>

        <div class="notifications-grid">
            <!-- Создание нового уведомления -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-plus"></i>
                        Создать новое уведомление
                    </h2>
                </div>
                <div class="section-content">
                    <form method="POST" class="form">
                        <input type="hidden" name="action" value="create_notification">
                        
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="title" class="form-label">Заголовок *</label>
                                <input type="text" id="title" name="title" required class="form-input" placeholder="Введите заголовок уведомления">
                            </div>
                            
                            <div class="form-group">
                                <label for="type" class="form-label">Тип уведомления</label>
                                <select id="type" name="type" class="form-select">
                                    <option value="info">Информация</option>
                                    <option value="success">Успех</option>
                                    <option value="warning">Предупреждение</option>
                                    <option value="error">Ошибка</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="target_role" class="form-label">Целевая аудитория</label>
                                <select id="target_role" name="target_role" class="form-select">
                                    <option value="all">Все пользователи</option>
                                    <option value="admin">Только администраторы</option>
                                    <option value="client">Только клиенты</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="start_date" class="form-label">Дата начала показа</label>
                                <input type="date" id="start_date" name="start_date" class="form-input">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date" class="form-label">Дата окончания показа</label>
                                <input type="date" id="end_date" name="end_date" class="form-input">
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label for="message" class="form-label">Сообщение *</label>
                            <textarea id="message" name="message" required class="form-textarea" placeholder="Введите текст уведомления"></textarea>
                        </div>
                        
                        <div class="checkbox-group">
                            <div class="checkbox-item">
                                <input type="checkbox" id="is_active" name="is_active" checked>
                                <label for="is_active">Активно</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="show_on_login" name="show_on_login" checked>
                                <label for="show_on_login">Показывать при входе</label>
                            </div>
                            <div class="checkbox-item">
                                <input type="checkbox" id="show_on_dashboard" name="show_on_dashboard" checked>
                                <label for="show_on_dashboard">Показывать в личном кабинете</label>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i>
                                Создать уведомление
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Список уведомлений -->
            <div class="section-card">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-list"></i>
                        Список уведомлений
                    </h2>
                </div>
                <div class="section-content">
                    <?php if (empty($notifications)): ?>
                        <p class="text-muted">Уведомлений пока нет</p>
                    <?php else: ?>
                        <table class="notifications-table">
                            <thead>
                                <tr>
                                    <th>Заголовок</th>
                                    <th>Тип</th>
                                    <th>Статус</th>
                                    <th>Аудитория</th>
                                    <th>Показ</th>
                                    <th>Даты</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($notifications as $notification): ?>
                                <tr>
                                    <td>
                                        <strong><?php echo htmlspecialchars($notification['title']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo htmlspecialchars(substr($notification['message'], 0, 100)) . (strlen($notification['message']) > 100 ? '...' : ''); ?></small>
                                    </td>
                                    <td>
                                        <span class="notification-type <?php echo $notification['type']; ?>">
                                            <i class="fas fa-<?php echo $notification['type'] === 'info' ? 'info' : ($notification['type'] === 'success' ? 'check' : ($notification['type'] === 'warning' ? 'exclamation-triangle' : 'times')); ?>"></i>
                                            <?php echo ucfirst($notification['type']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="notification-status <?php echo $notification['is_active'] ? 'active' : 'inactive'; ?>">
                                            <i class="fas fa-<?php echo $notification['is_active'] ? 'check' : 'times'; ?>"></i>
                                            <?php echo $notification['is_active'] ? 'Активно' : 'Неактивно'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php 
                                        switch($notification['target_role']) {
                                            case 'all': echo 'Все'; break;
                                            case 'admin': echo 'Администраторы'; break;
                                            case 'client': echo 'Клиенты'; break;
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($notification['show_on_login']): ?>
                                                <i class="fas fa-sign-in-alt" title="При входе"></i>
                                            <?php endif; ?>
                                            <?php if ($notification['show_on_dashboard']): ?>
                                                <i class="fas fa-home" title="В кабинете"></i>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small>
                                            <?php if ($notification['start_date']): ?>
                                                От: <?php echo date('d.m.Y', strtotime($notification['start_date'])); ?><br>
                                            <?php endif; ?>
                                            <?php if ($notification['end_date']): ?>
                                                До: <?php echo date('d.m.Y', strtotime($notification['end_date'])); ?>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <div class="notification-actions">
                                            <button onclick="editNotification(<?php echo $notification['id']; ?>)" class="btn btn-sm btn-primary" title="Редактировать">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Вы уверены, что хотите удалить это уведомление?');">
                                                <input type="hidden" name="action" value="delete_notification">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-danger" title="Удалить">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                            <form method="POST" style="display: inline;" onsubmit="return confirm('Очистить логи показа для этого уведомления?');">
                                                <input type="hidden" name="action" value="clear_logs">
                                                <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                                <button type="submit" class="btn btn-sm btn-secondary" title="Очистить логи">
                                                    <i class="fas fa-history"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

    <!-- Модальное окно для редактирования -->
    <div id="editModal" class="modal" style="display: none;">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2><i class="fas fa-edit"></i> Редактировать уведомление</h2>
            <form method="POST" class="form" id="editForm">
                <input type="hidden" name="action" value="update_notification">
                <input type="hidden" name="notification_id" id="edit_notification_id">
                
                <div class="form-group">
                    <label for="edit_title" class="form-label">Заголовок *</label>
                    <input type="text" id="edit_title" name="title" required class="form-input">
                </div>
                
                <div class="form-group">
                    <label for="edit_message" class="form-label">Сообщение *</label>
                    <textarea id="edit_message" name="message" required class="form-textarea"></textarea>
                </div>
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_type" class="form-label">Тип уведомления</label>
                        <select id="edit_type" name="type" class="form-select">
                            <option value="info">Информация</option>
                            <option value="success">Успех</option>
                            <option value="warning">Предупреждение</option>
                            <option value="error">Ошибка</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_target_role" class="form-label">Целевая аудитория</label>
                        <select id="edit_target_role" name="target_role" class="form-select">
                            <option value="all">Все пользователи</option>
                            <option value="admin">Только администраторы</option>
                            <option value="client">Только клиенты</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_start_date" class="form-label">Дата начала показа</label>
                        <input type="date" id="edit_start_date" name="start_date" class="form-input">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_end_date" class="form-label">Дата окончания показа</label>
                        <input type="date" id="edit_end_date" name="end_date" class="form-input">
                    </div>
                </div>
                
                <div class="checkbox-group">
                    <div class="checkbox-item">
                        <input type="checkbox" id="edit_is_active" name="is_active">
                        <label for="edit_is_active">Активно</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="edit_show_on_login" name="show_on_login">
                        <label for="edit_show_on_login">Показывать при входе</label>
                    </div>
                    <div class="checkbox-item">
                        <input type="checkbox" id="edit_show_on_dashboard" name="show_on_dashboard">
                        <label for="edit_show_on_dashboard">Показывать в личном кабинете</label>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
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
        // Функция редактирования уведомления
        function editNotification(id) {
            // Здесь нужно загрузить данные уведомления через AJAX
            // Пока что просто показываем модальное окно
            document.getElementById('editModal').style.display = 'flex';
            document.getElementById('edit_notification_id').value = id;
        }

        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }

        // Закрытие модального окна
        document.querySelector('.close').onclick = closeEditModal;

        window.onclick = function(event) {
            var modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeEditModal();
            }
        }

        // Закрытие по Escape
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeEditModal();
            }
        });
    </script>
</body>
</html>
