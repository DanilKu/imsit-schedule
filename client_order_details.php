<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Проверяем, не запущена ли уже сессия
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

// Мягкая проверка авторизации
if (!isset($_SESSION['user_id'])) {
    // Вместо выхода, создаем временную сессию для тестирования
    echo "<div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 10px; border-radius: 5px;'>";
    echo "<h3>⚠️ Предупреждение: Вы не авторизованы</h3>";
    echo "<p>Это может быть причиной проблем с отображением страницы.</p>";
    echo "<p><a href='login.php'>Войти в систему</a> | <a href='client_dashboard.php'>Вернуться к заказам</a></p>";
    echo "</div>";
    
    // Создаем временные данные для тестирования
    $tempUser = [
        'client_name' => 'Гость',
        'telegram_username' => ''
    ];
}

// Получение информации о текущем пользователе
$currentUser = null;
try {
    require_once 'config/auth.php';
    if (function_exists('getCurrentUser')) {
        $currentUser = getCurrentUser();
    }
} catch (Exception $e) {
    // Игнорируем ошибки auth.php
}

if (!$currentUser) {
    // Создаем простой объект пользователя из сессии
    if (isset($tempUser)) {
        $currentUser = $tempUser;
    } else {
        $currentUser = [
            'client_name' => $_SESSION['username'] ?? 'Неизвестный пользователь',
            'telegram_username' => $_SESSION['telegram_username'] ?? ''
        ];
    }
}

// Получение ID заказа
$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$order_id) {
    echo "<h1>Ошибка</h1>";
    echo "<p>ID заказа не указан. <a href='client_dashboard.php'>Вернуться к заказам</a></p>";
    exit;
}

// Получение информации о заказе
try {
    $sql = "SELECT * FROM orders WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id' => $order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        echo "<h1>Ошибка</h1>";
        echo "<p>Заказ с ID $order_id не найден. <a href='client_dashboard.php'>Вернуться к заказам</a></p>";
        exit;
    }
    
    // Получение файлов заказа
    $sql = "SELECT * FROM files WHERE order_id = :order_id ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['order_id' => $order_id]);
    $files = $stmt->fetchAll();
    
} catch (PDOException $e) {
    echo "<h1>Ошибка базы данных</h1>";
    echo "<p>Ошибка: " . $e->getMessage() . "</p>";
    echo "<p><a href='client_dashboard.php'>Вернуться к заказам</a></p>";
    exit;
}

$clientName = $currentUser['client_name'];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Заказ #<?php echo $order['id']; ?> - <?php echo htmlspecialchars($clientName); ?></title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        :root {
            --primary-color: #007bff;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --text-color: #333;
            --text-muted: #6c757d;
            --border-color: #dee2e6;
            --bg-color: #fff;
            --card-bg: #fff;
            --shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        [data-theme="dark"] {
            --text-color: #e9ecef;
            --text-muted: #adb5bd;
            --border-color: #495057;
            --bg-color: #212529;
            --card-bg: #343a40;
            --light-color: #495057;
            --shadow: 0 2px 4px rgba(0,0,0,0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-color);
            line-height: 1.6;
            transition: background-color 0.3s, color 0.3s;
        }

        .header {
            background: var(--card-bg);
            box-shadow: var(--shadow);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
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

        .logo {
            font-size: 1.5rem;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            text-decoration: none;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .theme-toggle {
            background: none;
            border: none;
            color: var(--text-color);
            font-size: 1.2rem;
            cursor: pointer;
            padding: 0.5rem;
            border-radius: 50%;
            transition: background-color 0.3s;
        }

        .theme-toggle:hover {
            background-color: var(--light-color);
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

        .main {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .order-header {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .order-title {
            font-size: 2rem;
            margin-bottom: 1rem;
            color: var(--text-color);
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .order-status {
            padding: 0.5rem 1.2rem;
            border-radius: 20px;
            font-size: 0.9rem;
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
            background: var(--card-bg);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            padding: 1rem;
            background: var(--light-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .detail-label {
            font-weight: bold;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .detail-value {
            color: var(--text-color);
            font-size: 1.1rem;
        }

        .btn {
            padding: 0.5rem 1rem;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: background-color 0.3s;
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
        }

        .btn-primary:hover {
            background: #0056b3;
        }

        .btn-secondary {
            background: var(--secondary-color);
            color: white;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

        .btn-sm {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }

        .order-files {
            background: var(--card-bg);
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
        }

        .files-grid {
            display: grid;
            gap: 1rem;
        }

        .file-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: var(--light-color);
            border-radius: 8px;
            border: 1px solid var(--border-color);
        }

        .file-icon {
            font-size: 1.5rem;
            color: var(--primary-color);
            width: 40px;
            text-align: center;
        }

        .file-info {
            flex: 1;
        }

        .file-name {
            font-weight: bold;
            color: var(--text-color);
            margin-bottom: 0.25rem;
        }

        .file-date {
            font-size: 0.875rem;
            color: var(--text-muted);
        }

        .file-actions {
            display: flex;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .header-content {
                padding: 0 1rem;
            }

            .main {
                padding: 1rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <!-- Шапка -->
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <a href="client_dashboard.php" class="logo">
                    <i class="fas fa-arrow-left"></i>
                    Назад к заказам
                </a>
            </div>
            
            <div class="header-right">
                <button class="theme-toggle" onclick="toggleTheme()">
                    <i class="fas fa-moon" id="theme-icon"></i>
                </button>
                
                <div class="user-menu">
                    <span class="username">
                        <i class="fas fa-user"></i>
                        <?php echo htmlspecialchars($_SESSION['username'] ?? 'Пользователь'); ?>
                    </span>
                    
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
        <!-- Заголовок заказа -->
        <div class="order-header">
            <div class="order-title">
                <i class="fas fa-file-alt"></i>
                Заказ #<?php echo $order['id']; ?>
                <span class="order-status status-<?php echo $order['work_status'] ?? 'pending'; ?>">
                    <?php 
                    $workStatus = $order['work_status'] ?? 'pending';
                    switch($workStatus) {
                        case 'pending': echo 'Ожидает'; break;
                        case 'in_progress': echo 'В работе'; break;
                        case 'completed': echo 'Завершён'; break;
                        case 'done': echo 'Выполнен'; break;
                        case 'finished': echo 'Завершён'; break;
                        default: echo 'Ожидает';
                    }
                    ?>
                </span>
            </div>
        </div>

        <!-- Детали заказа -->
        <div class="order-details">
            <h2 style="margin-bottom: 1.5rem; color: var(--text-color);">
                <i class="fas fa-info-circle"></i> Детали заказа
            </h2>
            
            <div class="detail-grid">
                <div class="detail-item">
                    <div class="detail-label">Номер (темы)</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($order['topic_number'] ?? 'Не указан'); ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Тема</div>
                    <div class="detail-value">
                        <?php echo htmlspecialchars($order['topic_description'] ?? 'Не указана'); ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Тип работы</div>
                    <div class="detail-value">
                        <?php 
                        if (!empty($order['work_type'])) {
                            switch($order['work_type']) {
                                case 'coursework': echo 'Курсовая работа'; break;
                                case 'production_practice': echo 'Производственная практика'; break;
                                case 'study_practice': echo 'Учебная практика'; break;
                                default: echo htmlspecialchars($order['work_type']);
                            }
                        } else {
                            echo 'Не указан';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="detail-item">
                    <div class="detail-label">Дата создания</div>
                    <div class="detail-value">
                        <?php echo date('d.m.Y H:i', strtotime($order['created_at'])); ?>
                    </div>
                </div>
                
                <?php if (!empty($order['deadline'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Дедлайн</div>
                    <div class="detail-value">
                        <?php echo date('d.m.Y', strtotime($order['deadline'])); ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($order['semester'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Семестр</div>
                    <div class="detail-value">
                        <?php echo $order['semester']; ?> семестр
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($order['total_price'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Стоимость</div>
                    <div class="detail-value">
                        <?php echo number_format($order['total_price'], 0, ',', ' '); ?> ₽
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($order['paid_amount'])): ?>
                <div class="detail-item">
                    <div class="detail-label">Оплачено</div>
                    <div class="detail-value">
                        <?php echo number_format($order['paid_amount'], 0, ',', ' '); ?> ₽
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Файлы заказа -->
        <?php if (!empty($files)): ?>
        <div class="order-files">
            <h2 style="margin-bottom: 1.5rem; color: var(--text-color);">
                <i class="fas fa-paperclip"></i> Файлы заказа
            </h2>
            
            <div class="files-grid">
                <?php foreach ($files as $file): ?>
                    <div class="file-item">
                        <div class="file-icon">
                            <?php 
                            $extension = pathinfo($file['filename'], PATHINFO_EXTENSION);
                            switch(strtolower($extension)) {
                                case 'pdf': echo '<i class="fas fa-file-pdf"></i>'; break;
                                case 'doc':
                                case 'docx': echo '<i class="fas fa-file-word"></i>'; break;
                                case 'xls':
                                case 'xlsx': echo '<i class="fas fa-file-excel"></i>'; break;
                                case 'ppt':
                                case 'pptx': echo '<i class="fas fa-file-powerpoint"></i>'; break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif': echo '<i class="fas fa-file-image"></i>'; break;
                                case 'zip':
                                case 'rar': echo '<i class="fas fa-file-archive"></i>'; break;
                                default: echo '<i class="fas fa-file"></i>';
                            }
                            ?>
                        </div>
                        <div class="file-info">
                            <div class="file-name"><?php echo htmlspecialchars($file['filename']); ?></div>
                            <div class="file-date"><?php echo isset($file['uploaded_at']) ? date('d.m.Y H:i', strtotime($file['uploaded_at'])) : (isset($file['created_at']) ? date('d.m.Y H:i', strtotime($file['created_at'])) : 'Неизвестно'); ?></div>
                        </div>
                        <div class="file-actions">
                            <a href="download_file.php?id=<?php echo $file['id']; ?>" class="btn btn-primary btn-sm" title="Скачать">
                                <i class="fas fa-download"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Кнопки действий -->
        <div style="text-align: center; margin-top: 2rem;">
            <a href="client_dashboard.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Назад к заказам
            </a>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
        // Функция переключения темы
        function toggleTheme() {
            const body = document.body;
            const themeIcon = document.getElementById('theme-icon');
            
            if (body.getAttribute('data-theme') === 'dark') {
                body.removeAttribute('data-theme');
                themeIcon.className = 'fas fa-moon';
                document.cookie = 'theme=light; path=/; max-age=31536000';
            } else {
                body.setAttribute('data-theme', 'dark');
                themeIcon.className = 'fas fa-sun';
                document.cookie = 'theme=dark; path=/; max-age=31536000';
            }
        }

        // Установка темы при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            const theme = getCookie('theme');
            const themeIcon = document.getElementById('theme-icon');
            
            if (theme === 'dark') {
                document.body.setAttribute('data-theme', 'dark');
                themeIcon.className = 'fas fa-sun';
            }
        });

        // Функция получения cookie
        function getCookie(name) {
            const value = `; ${document.cookie}`;
            const parts = value.split(`; ${name}=`);
            if (parts.length === 2) return parts.pop().split(';').shift();
        }
    </script>
</body>
</html> 