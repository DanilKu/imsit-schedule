<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/Order.php';
require_once 'includes/WorkStage.php';
require_once 'includes/FileManager.php';

// Проверка авторизации и прав администратора
requireAdmin();

// Инициализация классов
$order = new Order($pdo);
$workStage = new WorkStage($pdo);
$fileManager = new FileManager($pdo);

$error = '';
$success = '';
$orderData = null;
$stages = [];
$files = [];

// Получение ID заказа
$orderId = $_GET['id'] ?? null;
if (!$orderId) {
    header('Location: admin');
    exit();
}

// Получение данных заказа
try {
    $orderData = $order->getById($orderId);
    if (!$orderData) {
        header('Location: admin');
        exit();
    }
    
    // Получение этапов работ
    $stages = $workStage->getByOrderId($orderId);
    
    // Получение файлов
    $files = $fileManager->getByOrderId($orderId);
    
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'update_stage':
                try {
                    $stageId = $_POST['stage_id'];
                    $status = $_POST['status'];
                    $notes = $_POST['notes'] ?? '';
                    
                    $workStage->updateStatus($stageId, $status, $notes);
                    $success = 'Статус этапа обновлен';
                    
                    // Обновление данных
                    $stages = $workStage->getByOrderId($orderId);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'upload_file':
                try {
                    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                        $stageId = $_POST['stage_id'] ?? null;
                        $fileId = $fileManager->uploadFile($_FILES['file'], $orderId, $stageId);
                        $success = 'Файл успешно загружен';
                        
                        // Обновление списка файлов
                        $files = $fileManager->getByOrderId($orderId);
                    } else {
                        throw new Exception('Ошибка загрузки файла');
                    }
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'delete_file':
                try {
                    $fileId = $_POST['file_id'];
                    $fileManager->deleteFile($fileId);
                    $success = 'Файл удален';
                    
                    // Обновление списка файлов
                    $files = $fileManager->getByOrderId($orderId);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
                
            case 'update_work_status':
                try {
                    $workStatus = $_POST['work_status'];
                    $order->setWorkStatus($orderId, $workStatus);
                    $success = 'Статус работы обновлен';
                    
                    // Обновление данных заказа
                    $orderData = $order->getById($orderId);
                } catch (Exception $e) {
                    $error = $e->getMessage();
                }
                break;
        }
    }
}

// Получение статистики этапов
$stageStats = [];
if ($orderData) {
    try {
        $stageStats = $workStage->getStageStatistics($orderId);
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Получение темы из настроек
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Детали заказа - Система учёта работ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .details-container {
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .order-header {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .order-title {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .order-title h1 {
            color: var(--text-primary);
            font-size: 1.8rem;
        }
        
        .order-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .meta-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .meta-item:last-child {
            border-bottom: none;
        }
        
        .meta-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .meta-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        .progress-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .progress-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .progress-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: var(--bg-secondary);
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 15px;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, var(--accent-color), var(--accent-hover));
            transition: width 0.3s ease;
        }
        
        .stages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .stage-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }
        
        .stage-card:hover {
            box-shadow: var(--shadow);
        }
        
        .stage-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .stage-name {
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .stage-status {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .stage-status.pending {
            background: rgba(173, 181, 189, 0.2);
            color: var(--text-muted);
        }
        
        .stage-status.in-progress {
            background: rgba(255, 193, 7, 0.2);
            color: var(--warning-color);
        }
        
        .stage-status.completed {
            background: rgba(40, 167, 69, 0.2);
            color: var(--success-color);
        }
        
        .stage-actions {
            margin-top: 15px;
        }
        
        .stage-notes {
            margin-top: 10px;
            padding: 10px;
            background: var(--bg-secondary);
            border-radius: 6px;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }
        
        .files-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .files-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .files-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .upload-form {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .file-input {
            flex: 1;
            min-width: 200px;
        }
        
        .files-list {
            display: grid;
            gap: 10px;
        }
        
        .file-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
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
        
        @media (max-width: 768px) {
            .order-title {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stages-grid {
                grid-template-columns: 1fr;
            }
            
            .files-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .upload-form {
                flex-direction: column;
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
                        <a href="users_management.php" class="nav-btn">
                            <i class="fas fa-users"></i>
                            Пользователи
                        </a>
                    <?php endif; ?>
                    
                                    <form method="POST" action="logout.php" style="display: inline;">
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
        <div class="details-container">
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
            
            <?php if ($orderData): ?>
                <!-- Заголовок заказа -->
                <div class="order-header">
                    <div class="order-title">
                        <h1>
                            <i class="fas fa-clipboard-list"></i>
                            Заказ #<?php echo $orderData['id']; ?> - <?php echo htmlspecialchars($orderData['client_name']); ?>
                        </h1>
                        <div>
                            <a href="edit_order.php?id=<?php echo $orderData['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-edit"></i>
                                Редактировать
                            </a>
                        </div>
                    </div>
                    
                    <div class="order-meta">
                        <div class="meta-item">
                            <span class="meta-label">Тип работы:</span>
                            <span class="meta-value">
                                <span class="work-type-badge work-type-<?php echo $orderData['work_type']; ?>">
                                    <?php 
                                    switch($orderData['work_type']) {
                                        case 'coursework': echo 'Курсовая'; break;
                                        case 'production_practice': echo 'Пр. практика'; break;
                                        case 'study_practice': echo 'Уч. практика'; break;
                                    }
                                    ?>
                                </span>
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Семестр:</span>
                            <span class="meta-value">
                                <span class="semester-badge semester-<?php echo $orderData['semester'] ?? '6'; ?>">
                                    <?php echo $orderData['semester'] ?? '6'; ?> семестр
                                </span>
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Номер темы:</span>
                            <span class="meta-value"><?php echo htmlspecialchars($orderData['topic_number'] ?? '-'); ?></span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Общая стоимость:</span>
                            <span class="meta-value"><?php echo number_format($orderData['total_price'], 0, ',', ' '); ?> ₽</span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Оплачено:</span>
                            <span class="meta-value"><?php echo number_format($orderData['paid_amount'], 0, ',', ' '); ?> ₽</span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Остаток:</span>
                            <span class="meta-value <?php echo $orderData['debt_amount'] > 0 ? 'debt' : ''; ?>">
                                <?php echo number_format($orderData['debt_amount'], 0, ',', ' '); ?> ₽
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Статус оплаты:</span>
                            <span class="meta-value">
                                <?php if ($orderData['is_paid']): ?>
                                    <span style="color: var(--success-color);">Оплачен</span>
                                <?php else: ?>
                                    <span style="color: var(--danger-color);">Не оплачен</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Приоритет:</span>
                            <span class="meta-value">
                                <?php if ($orderData['priority']): ?>
                                    <span style="color: var(--priority-color);">Высокий</span>
                                <?php else: ?>
                                    <span>Обычный</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Статус работы:</span>
                            <span class="meta-value">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="action" value="update_work_status">
                                    <select name="work_status" onchange="this.form.submit()" style="padding: 4px 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-primary); color: var(--text-primary);">
                                        <option value="pending" <?php echo ($orderData['work_status'] ?? 'pending') === 'pending' ? 'selected' : ''; ?>>Не начата</option>
                                        <option value="in_progress" <?php echo ($orderData['work_status'] ?? 'pending') === 'in_progress' ? 'selected' : ''; ?>>В работе</option>
                                        <option value="completed" <?php echo ($orderData['work_status'] ?? 'pending') === 'completed' ? 'selected' : ''; ?>>Завершена</option>
                                    </select>
                                </form>
                                
                            </span>
                        </div>
                        
                        <div class="meta-item">
                            <span class="meta-label">Дата создания:</span>
                            <span class="meta-value"><?php echo date('d.m.Y H:i', strtotime($orderData['created_at'])); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($orderData['topic_description']): ?>
                        <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid var(--border-color);">
                            <h3 style="color: var(--text-primary); margin-bottom: 10px;">Описание темы:</h3>
                            <p style="color: var(--text-secondary); line-height: 1.6;">
                                <?php echo nl2br(htmlspecialchars($orderData['topic_description'])); ?>
                            </p>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Прогресс работ -->
                <div class="progress-section">
                    <div class="progress-header">
                        <h2 class="progress-title">
                            <i class="fas fa-tasks"></i>
                            Прогресс работ
                        </h2>
                        <div>
                            <span style="color: var(--text-secondary);">
                                <?php echo $stageStats['completed_stages'] ?? 0; ?> из <?php echo $stageStats['total_stages'] ?? 0; ?> этапов завершено
                            </span>
                        </div>
                    </div>
                    
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?php echo $stageStats['progress_percentage'] ?? 0; ?>%"></div>
                    </div>
                    
                    <div class="stages-grid">
                        <?php foreach ($stages as $stage): ?>
                            <div class="stage-card">
                                <div class="stage-header">
                                    <span class="stage-name"><?php echo htmlspecialchars($stage['stage_name']); ?></span>
                                    <span class="stage-status <?php echo $stage['is_completed'] ? 'completed' : ($stage['is_in_progress'] ? 'in-progress' : 'pending'); ?>">
                                        <?php 
                                        if ($stage['is_completed']) {
                                            echo 'Завершён';
                                        } elseif ($stage['is_in_progress']) {
                                            echo 'В работе';
                                        } else {
                                            echo 'Не начат';
                                        }
                                        ?>
                                    </span>
                                </div>
                                
                                <form method="POST" action="" class="stage-actions">
                                    <input type="hidden" name="action" value="update_stage">
                                    <input type="hidden" name="stage_id" value="<?php echo $stage['id']; ?>">
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; margin-bottom: 5px; color: var(--text-secondary); font-size: 0.9rem;">
                                            Статус:
                                        </label>
                                        <select name="status" style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-primary); color: var(--text-primary);">
                                            <option value="pending" <?php echo (!$stage['is_completed'] && !$stage['is_in_progress']) ? 'selected' : ''; ?>>Не начат</option>
                                            <option value="in_progress" <?php echo $stage['is_in_progress'] ? 'selected' : ''; ?>>В работе</option>
                                            <option value="completed" <?php echo $stage['is_completed'] ? 'selected' : ''; ?>>Завершён</option>
                                        </select>
                                    </div>
                                    
                                    <div style="margin-bottom: 10px;">
                                        <label style="display: block; margin-bottom: 5px; color: var(--text-secondary); font-size: 0.9rem;">
                                            Заметки:
                                        </label>
                                        <textarea name="notes" placeholder="Дополнительные заметки..." style="width: 100%; padding: 8px; border: 1px solid var(--border-color); border-radius: 4px; background: var(--bg-primary); color: var(--text-primary); min-height: 60px; resize: vertical;"><?php echo htmlspecialchars($stage['notes'] ?? ''); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                                        <i class="fas fa-save"></i>
                                        Обновить
                                    </button>
                                </form>
                                
                                <?php if ($stage['notes']): ?>
                                    <div class="stage-notes">
                                        <strong>Заметки:</strong><br>
                                        <?php echo nl2br(htmlspecialchars($stage['notes'])); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Файлы -->
                <div class="files-section">
                    <div class="files-header">
                        <h2 class="files-title">
                            <i class="fas fa-file"></i>
                            Файлы
                        </h2>
                        
                        <form method="POST" action="" enctype="multipart/form-data" class="upload-form">
                            <input type="hidden" name="action" value="upload_file">
                            <input type="file" name="file" class="file-input" required>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-upload"></i>
                                Загрузить
                            </button>
                        </form>
                    </div>
                    
                    <?php if (empty($files)): ?>
                        <div class="empty-message">
                            <i class="fas fa-file-alt"></i>
                            <p>Нет загруженных файлов</p>
                        </div>
                    <?php else: ?>
                        <div class="files-list">
                            <?php foreach ($files as $file): ?>
                                <div class="file-item">
                                    <div class="file-info">
                                        <div class="file-icon">
                                            <i class="fas fa-file"></i>
                                        </div>
                                        <div class="file-details">
                                            <h4><?php echo htmlspecialchars($file['original_filename']); ?></h4>
                                            <p>
                                                Размер: <?php echo $fileManager->formatFileSize($file['file_size']); ?> | 
                                                Дата: <?php echo date('d.m.Y H:i', strtotime($file['upload_date'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                    <div class="file-actions">
                                        <a href="<?php echo $file['file_path']; ?>" target="_blank" class="action-btn view" title="Скачать">
                                            <i class="fas fa-download"></i>
                                        </a>
                                        <form method="POST" action="" style="display: inline;">
                                            <input type="hidden" name="action" value="delete_file">
                                            <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                            <button type="submit" class="action-btn delete" title="Удалить" onclick="return confirm('Удалить файл?')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
</body>
</html> 