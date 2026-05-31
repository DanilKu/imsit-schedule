<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/Order.php';
require_once 'config/notifications_local.php';

// Проверка авторизации и прав администратора
requireAdmin();

// Инициализация класса
$order = new Order($pdo);

$error = '';
$success = '';

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'client_name' => trim($_POST['client_name'] ?? ''),
            'topic_number' => trim($_POST['topic_number'] ?? ''),
            'topic_description' => trim($_POST['topic_description'] ?? ''),
            'work_type' => $_POST['work_type'] ?? '',
            'semester' => $_POST['semester'] ?? '6',
            'total_price' => floatval($_POST['total_price'] ?? 0),
            'paid_amount' => floatval($_POST['paid_amount'] ?? 0)
        ];
        
        // Валидация
        if (empty($data['client_name'])) {
            throw new Exception('Имя клиента обязательно для заполнения');
        }
        
        if (empty($data['work_type'])) {
            throw new Exception('Выберите тип работы');
        }
        
        if (empty($data['semester']) || !in_array((string)$data['semester'], ['6','7','8'], true)) {
            throw new Exception('Выберите корректный семестр');
        }
        
        if ($data['total_price'] <= 0) {
            throw new Exception('Стоимость работы должна быть больше нуля');
        }
        
        if ($data['paid_amount'] > $data['total_price']) {
            throw new Exception('Оплаченная сумма не может быть больше общей стоимости');
        }
        
        // Создание заказа
        $orderId = $order->create($data);
        
        // Отправка уведомления в Telegram
        try {
            require_once 'api/telegram_notifications.php';
            $telegram = new TelegramNotifications();
            
            // Получаем полную информацию о заказе для уведомления
            $orderData = $order->getById($orderId);
            if ($orderData) {
                $telegram->notifyNewOrder($orderData);
            }
        } catch (Exception $e) {
            // Логируем ошибку, но не прерываем выполнение
            error_log("Telegram notification error: " . $e->getMessage());
        }
        
        $success = 'Заказ успешно создан!';
        
        // Перенаправление через 2 секунды
        header("refresh:2;url=admin");
        
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
    <title>Добавить заказ - Система учёта работ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .form-container {
            max-width: 800px;
            margin: 0 auto;
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 30px;
            box-shadow: var(--shadow);
        }
        
        .form-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .form-header h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .form-header p {
            color: var(--text-secondary);
        }
        
        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group.full-width {
            grid-column: 1 / -1;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .form-control.error {
            border-color: var(--danger-color);
        }
        
        .form-control.success {
            border-color: var(--success-color);
        }
        
        textarea.form-control {
            resize: vertical;
            min-height: 100px;
        }
        
        .form-actions {
            grid-column: 1 / -1;
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--border-color);
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
        
        .price-info {
            background: var(--bg-secondary);
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
        }
        
        .price-info .debt {
            color: var(--danger-color);
            font-weight: 600;
        }
        
        .price-info .paid {
            color: var(--success-color);
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
                flex-direction: column;
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
        <div class="form-container">
            <div class="form-header">
                <h1><i class="fas fa-plus"></i> Добавить новый заказ</h1>
                <p>Заполните информацию о новом заказе</p>
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
            
            <form method="POST" action="" id="orderForm">
                <div class="form-grid">
                    <!-- Имя клиента -->
                    <div class="form-group">
                        <label for="client_name">
                            <i class="fas fa-user"></i>
                            Фамилия и имя клиента *
                        </label>
                        <input type="text" id="client_name" name="client_name" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['client_name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <!-- Номер темы -->
                    <div class="form-group">
                        <label for="topic_number">
                            <i class="fas fa-hashtag"></i>
                            Номер темы
                        </label>
                        <input type="text" id="topic_number" name="topic_number" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['topic_number'] ?? ''); ?>">
                    </div>
                    
                    <!-- Тип работы -->
                    <div class="form-group">
                        <label for="work_type">
                            <i class="fas fa-file-alt"></i>
                            Тип работы *
                        </label>
                        <select id="work_type" name="work_type" class="form-control" required>
                            <option value="">Выберите тип работы</option>
                            <option value="coursework" <?php echo ($_POST['work_type'] ?? '') === 'coursework' ? 'selected' : ''; ?>>
                                Курсовая работа
                            </option>
                            <option value="production_practice" <?php echo ($_POST['work_type'] ?? '') === 'production_practice' ? 'selected' : ''; ?>>
                                Производственная практика
                            </option>
                            <option value="study_practice" <?php echo ($_POST['work_type'] ?? '') === 'study_practice' ? 'selected' : ''; ?>>
                                Учебная практика
                            </option>
                        </select>
                    </div>
                    
                    <!-- Семестр -->
                    <div class="form-group">
                        <label for="semester">
                            <i class="fas fa-calendar-alt"></i>
                            Семестр *
                        </label>
                        <select id="semester" name="semester" class="form-control" required>
                            <option value="">Выберите семестр</option>
                            <option value="6" <?php echo ($_POST['semester'] ?? '6') === '6' ? 'selected' : ''; ?>>
                                6 семестр
                            </option>
                            <option value="7" <?php echo ($_POST['semester'] ?? '6') === '7' ? 'selected' : ''; ?>>
                                7 семестр
                            </option>
                            <option value="8" <?php echo ($_POST['semester'] ?? '6') === '8' ? 'selected' : ''; ?>>
                                8 семестр
                            </option>
                        </select>
                    </div>
                    
                    <!-- Общая стоимость -->
                    <div class="form-group">
                        <label for="total_price">
                            <i class="fas fa-ruble-sign"></i>
                            Общая стоимость (₽) *
                        </label>
                        <input type="number" id="total_price" name="total_price" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['total_price'] ?? ''); ?>" 
                               min="0" step="0.01" required>
                    </div>
                    
                    <!-- Оплаченная сумма -->
                    <div class="form-group">
                        <label for="paid_amount">
                            <i class="fas fa-credit-card"></i>
                            Оплаченная сумма (₽)
                        </label>
                        <input type="number" id="paid_amount" name="paid_amount" class="form-control" 
                               value="<?php echo htmlspecialchars($_POST['paid_amount'] ?? '0'); ?>" 
                               min="0" step="0.01">
                        <div class="price-info" id="priceInfo" style="display: none;">
                            <div>Остаток к оплате: <span class="debt" id="debtAmount">0 ₽</span></div>
                            <div>Статус: <span id="paymentStatus">Не оплачен</span></div>
                        </div>
                    </div>
                    
                    <!-- Описание темы -->
                    <div class="form-group full-width">
                        <label for="topic_description">
                            <i class="fas fa-align-left"></i>
                            Описание темы
                        </label>
                        <textarea id="topic_description" name="topic_description" class="form-control" 
                                  placeholder="Подробное описание темы работы..."><?php echo htmlspecialchars($_POST['topic_description'] ?? ''); ?></textarea>
                    </div>
                </div>
                
                <div class="form-actions">
                    <a href="admin" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                        Отмена
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i>
                        Создать заказ
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script src="assets/js/app.js"></script>
    <script>
        // Расчет остатка к оплате
        function calculateDebt() {
            const totalPrice = parseFloat(document.getElementById('total_price').value) || 0;
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            const debtAmount = totalPrice - paidAmount;
            
            const priceInfo = document.getElementById('priceInfo');
            const debtElement = document.getElementById('debtAmount');
            const statusElement = document.getElementById('paymentStatus');
            
            if (totalPrice > 0) {
                priceInfo.style.display = 'block';
                debtElement.textContent = debtAmount.toLocaleString('ru-RU') + ' ₽';
                
                if (paidAmount >= totalPrice) {
                    statusElement.textContent = 'Полностью оплачен';
                    statusElement.className = 'paid';
                } else if (paidAmount > 0) {
                    statusElement.textContent = 'Частично оплачен';
                    statusElement.className = '';
                } else {
                    statusElement.textContent = 'Не оплачен';
                    statusElement.className = '';
                }
            } else {
                priceInfo.style.display = 'none';
            }
        }
        
        // Обработчики событий
        document.getElementById('total_price').addEventListener('input', calculateDebt);
        document.getElementById('paid_amount').addEventListener('input', calculateDebt);
        
        // Валидация формы
        document.getElementById('orderForm').addEventListener('submit', function(e) {
            const totalPrice = parseFloat(document.getElementById('total_price').value) || 0;
            const paidAmount = parseFloat(document.getElementById('paid_amount').value) || 0;
            
            if (paidAmount > totalPrice) {
                e.preventDefault();
                alert('Оплаченная сумма не может быть больше общей стоимости!');
                return false;
            }
        });
        
        // Инициализация при загрузке
        document.addEventListener('DOMContentLoaded', function() {
            calculateDebt();
            
            // Фокус на первое поле
            document.getElementById('client_name').focus();
        });
    </script>
</body>
</html> 