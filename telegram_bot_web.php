<?php
session_start();
require_once 'config/database.php';
require_once 'config/auth.php';

// Проверяем авторизацию
if (!isAuthenticated()) {
    header('Location: login.php');
    exit;
}

$user = getCurrentUser();
$userId = $user['id'];
$userName = $user['name'] ?? 'Пользователь';
$userGroup = $user['group_name'] ?? 'Группа не выбрана';

// Функция получения расписания группы
function getGroupSchedule($groupName, $week = null) {
    global $pdo;
    
    try {
        $sql = "SELECT * FROM schedule WHERE group_name = :group_name";
        $params = ['group_name' => $groupName];
        
        if ($week !== null) {
            $sql .= " AND week_number = :week";
            $params['week'] = $week;
        }
        
        $sql .= " ORDER BY week_number, day_of_week, lesson_number";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return $result;
    } catch (Exception $e) {
        error_log("getGroupSchedule error: " . $e->getMessage());
        return [];
    }
}

// Функция получения заказов пользователя
function getUserOrders($userId) {
    global $pdo;
    
    try {
        // Получаем имя клиента из таблицы users
        $userStmt = $pdo->prepare("SELECT name FROM users WHERE id = :user_id");
        $userStmt->execute(['user_id' => $userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [];
        }
        
        $clientName = $user['name'];
        
        // Получаем заказы из таблицы orders
        $ordersStmt = $pdo->prepare("SELECT * FROM orders WHERE client_name = :client_name ORDER BY created_at DESC");
        $ordersStmt->execute(['client_name' => $clientName]);
        $orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Получаем запросы из таблицы order_requests
        $requestsStmt = $pdo->prepare("SELECT * FROM order_requests WHERE user_id = :user_id ORDER BY created_at DESC");
        $requestsStmt->execute(['user_id' => $userId]);
        $requests = $requestsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Объединяем и сортируем
        $allOrders = array_merge($orders, $requests);
        usort($allOrders, function($a, $b) {
            $dateA = $a['created_at'] ?? $a['date_created'] ?? '1970-01-01';
            $dateB = $b['created_at'] ?? $b['date_created'] ?? '1970-01-01';
            return strtotime($dateB) - strtotime($dateA);
        });
        
        return $allOrders;
    } catch (Exception $e) {
        error_log("Error getting user orders: " . $e->getMessage());
        return [];
    }
}

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch ($_POST['action']) {
        case 'get_schedule_today':
            $schedule = getGroupSchedule($userGroup);
            $today = date('N'); // 1-7 (понедельник-воскресенье)
            $todaySchedule = array_filter($schedule, function($lesson) use ($today) {
                return $lesson['day_of_week'] == $today;
            });
            
            echo json_encode([
                'success' => true,
                'schedule' => array_values($todaySchedule),
                'group' => $userGroup
            ]);
            exit;
            
        case 'get_schedule_week':
            echo json_encode([
                'success' => true,
                'message' => 'Для просмотра полного расписания на неделю используйте приложение: imsit.shop'
            ]);
            exit;
            
        case 'get_orders':
            $orders = getUserOrders($userId);
            echo json_encode([
                'success' => true,
                'orders' => $orders
            ]);
            exit;
            
        case 'get_settings':
            echo json_encode([
                'success' => true,
                'user' => [
                    'name' => $userName,
                    'group' => $userGroup
                ]
            ]);
            exit;
    }
}
?>

<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
    <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
    <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
    <meta name="theme-color" content="#0f172a">
    <title>Telegram Bot Web - imsitID</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg,rgb(5, 20, 86) 0%,rgb(60, 28, 92) 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .container {
            background: #1a1a1a;
            border-radius: 20px;
            padding: 30px;
            max-width: 400px;
            width: 100%;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
        }

        .greeting {
            color: #fff;
            font-size: 18px;
            margin-bottom: 10px;
        }

        .instruction {
            color: #a0a0a0;
            font-size: 14px;
        }

        .menu-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .menu-btn {
            background: #2a2a2a;
            border: none;
            border-radius: 15px;
            padding: 20px;
            color: #fff;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            text-decoration: none;
        }

        .menu-btn:hover {
            background: #3a3a3a;
            transform: translateY(-2px);
        }

        .menu-btn:active {
            transform: translateY(0);
        }

        .menu-icon {
            font-size: 24px;
        }

        .content {
            background: #2a2a2a;
            border-radius: 15px;
            padding: 20px;
            margin-top: 20px;
            min-height: 200px;
            display: none;
        }

        .content.active {
            display: block;
        }

        .content h3 {
            color: #fff;
            margin-bottom: 15px;
            font-size: 18px;
        }

        .schedule-item {
            background: #3a3a3a;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            color: #fff;
        }

        .lesson-number {
            font-weight: bold;
            color: #4ade80;
            margin-bottom: 5px;
        }

        .lesson-subject {
            font-size: 16px;
            margin-bottom: 5px;
        }

        .lesson-details {
            font-size: 14px;
            color: #a0a0a0;
        }

        .orders-item {
            background: #3a3a3a;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            color: #fff;
        }

        .order-title {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .order-status {
            padding: 4px 8px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
        }

        .status-pending {
            background: #fbbf24;
            color: #000;
        }

        .status-processing {
            background: #3b82f6;
            color: #fff;
        }

        .status-completed {
            background: #10b981;
            color: #fff;
        }

        .status-cancelled {
            background: #ef4444;
            color: #fff;
        }

        .back-btn {
            background: #4ade80;
            color: #000;
            border: none;
            border-radius: 10px;
            padding: 12px 20px;
            font-weight: 500;
            cursor: pointer;
            margin-top: 15px;
            width: 100%;
        }

        .back-btn:hover {
            background: #22c55e;
        }

        .loading {
            text-align: center;
            color: #a0a0a0;
            padding: 20px;
        }

        .error {
            color: #ef4444;
            text-align: center;
            padding: 20px;
        }

        .success {
            color: #10b981;
            text-align: center;
            padding: 20px;
        }

        .settings-item {
            background: #3a3a3a;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 10px;
            color: #fff;
        }

        .settings-label {
            font-weight: bold;
            margin-bottom: 5px;
        }

        .settings-value {
            color: #a0a0a0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="greeting">👋 Привет, <?php echo htmlspecialchars($userName); ?>!</div>
            <div class="instruction">Выберите нужную функцию:</div>
        </div>

        <div class="menu-grid">
            <button class="menu-btn" onclick="showSchedule()">
                <div class="menu-icon">📅</div>
                <div>Расписание</div>
            </button>
            
            <button class="menu-btn" onclick="showOrders()">
                <div class="menu-icon">📋</div>
                <div>Мои заказы</div>
            </button>
            
            <button class="menu-btn" onclick="showSettings()">
                <div class="menu-icon">⚙️</div>
                <div>Настройки</div>
            </button>
            
            <button class="menu-btn" onclick="showHelp()">
                <div class="menu-icon">❓</div>
                <div>Помощь</div>
            </button>
        </div>

        <div id="content" class="content">
            <!-- Контент будет загружаться динамически -->
        </div>
    </div>

    <script>
        let currentView = null;

        function showSchedule() {
            currentView = 'schedule';
            showContent();
            
            const content = document.getElementById('content');
            content.innerHTML = `
                <h3>📅 Расписание</h3>
                <div class="loading">Загрузка расписания...</div>
            `;
            
            // Показываем кнопки выбора
            setTimeout(() => {
                content.innerHTML = `
                    <h3>📅 Расписание</h3>
                    <button class="menu-btn" onclick="getScheduleToday()" style="margin-bottom: 10px;">
                        <div class="menu-icon">📅</div>
                        <div>Сегодня</div>
                    </button>
                    <button class="menu-btn" onclick="getScheduleWeek()" style="margin-bottom: 15px;">
                        <div class="menu-icon">📊</div>
                        <div>Неделя</div>
                    </button>
                    <button class="back-btn" onclick="hideContent()">🔙 Назад к меню</button>
                `;
            }, 500);
        }

        function getScheduleToday() {
            const content = document.getElementById('content');
            content.innerHTML = `
                <h3>📅 Расписание на сегодня</h3>
                <div class="loading">Загрузка расписания...</div>
            `;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_schedule_today'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.schedule.length > 0) {
                        let scheduleHtml = '';
                        data.schedule.forEach(lesson => {
                            scheduleHtml += `
                                <div class="schedule-item">
                                    <div class="lesson-number">Пара ${lesson.lesson_number}</div>
                                    <div class="lesson-subject">${lesson.subject_name}</div>
                                    <div class="lesson-details">
                                        🕐 ${lesson.start_time} - ${lesson.end_time}<br>
                                        🏢 Аудитория: ${lesson.room_number}<br>
                                        👨‍🏫 ${lesson.teacher_name || 'Преподаватель не указан'}
                                    </div>
                                </div>
                            `;
                        });
                        content.innerHTML = `
                            <h3>📅 Расписание на сегодня (${data.group})</h3>
                            ${scheduleHtml}
                            <button class="back-btn" onclick="showSchedule()">🔙 Назад к расписанию</button>
                        `;
                    } else {
                        content.innerHTML = `
                            <h3>📅 Расписание на сегодня</h3>
                            <div class="success">🎉 Сегодня пар нет!</div>
                            <button class="back-btn" onclick="showSchedule()">🔙 Назад к расписанию</button>
                        `;
                    }
                } else {
                    content.innerHTML = `
                        <h3>📅 Расписание на сегодня</h3>
                        <div class="error">❌ Ошибка загрузки расписания</div>
                        <button class="back-btn" onclick="showSchedule()">🔙 Назад к расписанию</button>
                    `;
                }
            })
            .catch(error => {
                content.innerHTML = `
                    <h3>📅 Расписание на сегодня</h3>
                    <div class="error">❌ Ошибка загрузки расписания</div>
                    <button class="back-btn" onclick="showSchedule()">🔙 Назад к расписанию</button>
                `;
            });
        }

        function getScheduleWeek() {
            const content = document.getElementById('content');
            content.innerHTML = `
                <h3>📊 Расписание на неделю</h3>
                <div class="loading">Загрузка...</div>
            `;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_schedule_week'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    content.innerHTML = `
                        <h3>📊 Расписание на неделю</h3>
                        <div class="success">
                            ${data.message}<br><br>
                            🌐 <a href="shedule2.php" style="color: #4ade80;">Открыть приложение</a>
                        </div>
                        <button class="back-btn" onclick="showSchedule()">🔙 Назад к расписанию</button>
                    `;
                }
            });
        }

        function showOrders() {
            currentView = 'orders';
            showContent();
            
            const content = document.getElementById('content');
            content.innerHTML = `
                <h3>📋 Мои заказы</h3>
                <div class="loading">Загрузка заказов...</div>
            `;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_orders'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.orders.length > 0) {
                        let ordersHtml = '';
                        data.orders.forEach(order => {
                            const status = order.status || order.order_status || 'pending';
                            const statusClass = getStatusClass(status);
                            const statusText = getStatusText(status);
                            
                            ordersHtml += `
                                <div class="orders-item">
                                    <div class="order-title">${order.title || order.description || 'Заказ'}</div>
                                    <div class="order-details">
                                        <span class="order-status ${statusClass}">${statusText}</span><br>
                                        📅 ${order.created_at || order.date_created || 'Дата не указана'}
                                    </div>
                                </div>
                            `;
                        });
                        content.innerHTML = `
                            <h3>📋 Мои заказы</h3>
                            ${ordersHtml}
                            <button class="back-btn" onclick="hideContent()">🔙 Назад к меню</button>
                        `;
                    } else {
                        content.innerHTML = `
                            <h3>📋 Мои заказы</h3>
                            <div class="success">📭 У вас пока нет заказов</div>
                            <button class="back-btn" onclick="hideContent()">🔙 Назад к меню</button>
                        `;
                    }
                } else {
                    content.innerHTML = `
                        <h3>📋 Мои заказы</h3>
                        <div class="error">❌ Ошибка загрузки заказов</div>
                        <button class="back-btn" onclick="hideContent()">🔙 Назад к меню</button>
                    `;
                }
            });
        }

        function showSettings() {
            currentView = 'settings';
            showContent();
            
            const content = document.getElementById('content');
            content.innerHTML = `
                <h3>⚙️ Настройки</h3>
                <div class="loading">Загрузка настроек...</div>
            `;
            
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=get_settings'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    content.innerHTML = `
                        <h3>⚙️ Настройки</h3>
                        <div class="settings-item">
                            <div class="settings-label">👤 Имя пользователя</div>
                            <div class="settings-value">${data.user.name}</div>
                        </div>
                        <div class="settings-item">
                            <div class="settings-label">🎓 Группа</div>
                            <div class="settings-value">${data.user.group}</div>
                        </div>
                        <div class="settings-item">
                            <div class="settings-label">🔗 Telegram</div>
                            <div class="settings-value">
                                <a href="telegram_settings_user.php" style="color: #4ade80;">Настроить привязку</a>
                            </div>
                        </div>
                        <button class="back-btn" onclick="hideContent()">🔙 Назад к меню</button>
                    `;
                }
            });
        }

        function showHelp() {
            currentView = 'help';
            showContent();
            
            const content = document.getElementById('content');
            content.innerHTML = `
                <h3>❓ Помощь</h3>
                <div class="settings-item">
                    <div class="settings-label">📢 Канал</div>
                    <div class="settings-value">
                        <a href="https://t.me/imsit_channel" target="_blank" style="color: #4ade80;">@imsit_channel</a>
                    </div>
                </div>
                <div class="settings-item">
                    <div class="settings-label">👨‍💻 Техподдержка</div>
                    <div class="settings-value">
                        <a href="https://t.me/cowgivesmilk" target="_blank" style="color: #4ade80;">@cowgivesmilk</a>
                    </div>
                </div>
                <div class="settings-item">
                    <div class="settings-label">💡 Совет</div>
                    <div class="settings-value">Используйте кнопки меню для быстрого доступа к функциям!</div>
                </div>
                <button class="back-btn" onclick="hideContent()">🔙 Назад к меню</button>
            `;
        }

        function showContent() {
            document.getElementById('content').classList.add('active');
        }

        function hideContent() {
            document.getElementById('content').classList.remove('active');
            currentView = null;
        }

        function getStatusClass(status) {
            switch(status.toLowerCase()) {
                case 'pending': return 'status-pending';
                case 'processing': return 'status-processing';
                case 'completed': return 'status-completed';
                case 'cancelled': return 'status-cancelled';
                default: return 'status-pending';
            }
        }

        function getStatusText(status) {
            switch(status.toLowerCase()) {
                case 'pending': return 'В ожидании';
                case 'processing': return 'В обработке';
                case 'completed': return 'Выполнено';
                case 'cancelled': return 'Отменено';
                default: return 'В ожидании';
            }
        }
    </script>
</body>
</html>
