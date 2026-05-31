<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/Order.php';
require_once 'includes/FileManager.php';

// Проверка авторизации и прав администратора
requireAdmin();

// Инициализация классов
$order = new Order($pdo);
$fileManager = new FileManager($pdo);

$success = '';
$error = '';

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'clear_trash':
                try {
                    $deletedCount = $fileManager->clearTrash();
                    // Удаляем заказы со статусом deleted
                    $stmt = $pdo->prepare("DELETE FROM orders WHERE status = 'deleted'");
                    $stmt->execute();
                    $success = "Корзина очищена. Удалено $deletedCount файлов и все удалённые заказы.";
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'restore_order':
                if (isset($_POST['order_id'])) {
                    try {
                        // Восстановление заказа
                        $sql = "UPDATE orders SET status = 'active' WHERE id = :id";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute(['id' => $_POST['order_id']]);
                        
                        $success = "Заказ восстановлен.";
                    } catch (Exception $e) {
                        $error = $e->getMessage();
                    }
                }
                break;
        }
    }
}

// Получение удаленных заказов
$deletedOrders = [];
try {
    $sql = "SELECT * FROM orders WHERE status = 'deleted' ORDER BY updated_at DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $deletedOrders = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Получение файлов в корзине
$trashFiles = [];
try {
    $trashFiles = $fileManager->getTrashFiles();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Получение темы из настроек
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Корзина - Система учёта работ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .trash-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .trash-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .trash-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .trash-stat-card {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 20px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .trash-stat-card h3 {
            font-size: 2rem;
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .trash-stat-card p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .trash-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .empty-message {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        .empty-message i {
            font-size: 3rem;
            margin-bottom: 15px;
            opacity: 0.5;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }
        
        .file-item:hover {
            background: var(--bg-secondary);
        }
        
        .file-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .file-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            background: var(--accent-color);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.2rem;
        }
        
        .file-details h4 {
            color: var(--text-primary);
            margin-bottom: 5px;
        }
        
        .file-details p {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }
        
        .file-actions {
            display: flex;
            gap: 10px;
        }
        
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid;
        }
        
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            border-color: var(--success-color);
            color: var(--success-color);
        }
        
        .alert-error {
            background: rgba(220, 53, 69, 0.1);
            border-color: var(--danger-color);
            color: var(--danger-color);
        }
        
        @media (max-width: 768px) {
            .trash-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .file-item {
                flex-direction: column;
                align-items: stretch;
                gap: 15px;
            }
            
            .file-actions {
                justify-content: center;
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
                    Назад к списку
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
    <main class="main">
        <div class="trash-container">
            <div class="trash-header">
                <h1><i class="fas fa-trash"></i> Корзина</h1>
                
                <?php if (count($deletedOrders) > 0 || count($trashFiles) > 0): ?>
                    <form method="POST" action="" style="display: inline;">
                        <input type="hidden" name="action" value="clear_trash">
                        <button type="submit" class="btn btn-danger" onclick="return confirm('Вы уверены, что хотите полностью очистить корзину? Это действие нельзя отменить.')">
                            <i class="fas fa-trash-alt"></i>
                            Очистить корзину
                        </button>
                    </form>
                <?php endif; ?>
            </div>
            
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo htmlspecialchars($success); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-triangle"></i>
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <!-- Статистика корзины -->
            <div class="trash-stats">
                <div class="trash-stat-card">
                    <h3><?php echo count($deletedOrders); ?></h3>
                    <p>Удаленных заказов</p>
                </div>
                
                <div class="trash-stat-card">
                    <h3><?php echo count($trashFiles); ?></h3>
                    <p>Удаленных файлов</p>
                </div>
                
                <div class="trash-stat-card">
                    <h3><?php echo count($deletedOrders) + count($trashFiles); ?></h3>
                    <p>Всего элементов</p>
                </div>
            </div>
            
            <!-- Удаленные заказы -->
            <div class="trash-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-clipboard-list"></i>
                        Удаленные заказы
                    </h2>
                </div>
                
                <?php if (empty($deletedOrders)): ?>
                    <div class="empty-message">
                        <i class="fas fa-inbox"></i>
                        <p>Нет удаленных заказов</p>
                    </div>
                <?php else: ?>
                    <div class="table-container">
                        <table class="orders-table">
                            <thead>
                                <tr>
                                    <th>Клиент</th>
                                    <th>Тип работы</th>
                                    <th>Стоимость</th>
                                    <th>Дата удаления</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($deletedOrders as $orderData): ?>
                                    <tr>
                                        <td class="client-name">
                                            <strong><?php echo htmlspecialchars($orderData['client_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="work-type-badge work-type-<?php echo $orderData['work_type']; ?>">
                                                <?php 
                                                switch($orderData['work_type']) {
                                                    case 'coursework': echo 'Курсовая'; break;
                                                    case 'production_practice': echo 'Пр. практика'; break;
                                                    case 'study_practice': echo 'Уч. практика'; break;
                                                }
                                                ?>
                                            </span>
                                        </td>
                                        <td class="price"><?php echo number_format($orderData['total_price'], 0, ',', ' '); ?> ₽</td>
                                        <td><?php echo date('d.m.Y H:i', strtotime($orderData['updated_at'])); ?></td>
                                        <td class="actions">
                                            <form method="POST" action="" style="display: inline;">
                                                <input type="hidden" name="action" value="restore_order">
                                                <input type="hidden" name="order_id" value="<?php echo $orderData['id']; ?>">
                                                <button type="submit" class="action-btn view" title="Восстановить">
                                                    <i class="fas fa-undo"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Удаленные файлы -->
            <div class="trash-section">
                <div class="section-header">
                    <h2 class="section-title">
                        <i class="fas fa-file"></i>
                        Удаленные файлы
                    </h2>
                </div>
                
                <?php if (empty($trashFiles)): ?>
                    <div class="empty-message">
                        <i class="fas fa-file-alt"></i>
                        <p>Нет удаленных файлов</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($trashFiles as $file): ?>
                        <div class="file-item">
                            <div class="file-info">
                                <div class="file-icon">
                                    <i class="fas fa-file"></i>
                                </div>
                                <div class="file-details">
                                    <h4><?php echo htmlspecialchars($file['original_filename']); ?></h4>
                                    <p>
                                        Заказ: <?php echo htmlspecialchars($file['client_name']); ?> | 
                                        Размер: <?php echo $fileManager->formatFileSize($file['file_size']); ?> | 
                                        Дата: <?php echo date('d.m.Y H:i', strtotime($file['upload_date'])); ?>
                                    </p>
                                </div>
                            </div>
                            <div class="file-actions">
                                <button class="action-btn view" title="Просмотр" disabled>
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="action-btn delete" title="Удалить навсегда" disabled>
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html> 