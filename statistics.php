<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'config/database.php';
require_once 'includes/Order.php';
require_once 'includes/WorkStage.php';

// Проверка авторизации и прав администратора
requireAdmin();

// Инициализация классов
$order = new Order($pdo);
$workStage = new WorkStage($pdo);

// Получение общей статистики
$statistics = $order->getStatistics();

// Получение статистики по типам работ
$workTypeStats = [];
try {
    $sql = "SELECT 
            work_type,
            COUNT(*) as count,
            SUM(total_price) as total_revenue,
            SUM(paid_amount) as total_paid,
            SUM(debt_amount) as total_debt,
            COUNT(CASE WHEN is_paid = 1 THEN 1 END) as paid_count
            FROM orders 
            WHERE status = 'active' 
            GROUP BY work_type";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $workTypeStats = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Получение статистики по месяцам
$monthlyStats = [];
try {
    $sql = "SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as count,
            SUM(total_price) as revenue,
            SUM(paid_amount) as paid
            FROM orders 
            WHERE status = 'active' 
            AND created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $monthlyStats = $stmt->fetchAll();
} catch (Exception $e) {
    $error = $e->getMessage();
}

// Получение топ клиентов
$topClients = [];
try {
    $sql = "SELECT 
            client_name,
            COUNT(*) as orders_count,
            SUM(total_price) as total_spent
            FROM orders 
            WHERE status = 'active' 
            GROUP BY client_name 
            ORDER BY total_spent DESC 
            LIMIT 10";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $topClients = $stmt->fetchAll();
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
    <title>Статистика - Система учёта работ</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-container {
            max-width: 1400px;
            margin: 0 auto;
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
        
        .stats-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            flex-wrap: wrap;
            gap: 20px;
        }
        
        .stats-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-primary);
        }
        
        .chart-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .chart-container {
            position: relative;
            height: 400px;
            margin-bottom: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card-large {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            box-shadow: var(--shadow);
            text-align: center;
        }
        
        .stat-card-large h3 {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 10px;
        }
        
        .stat-card-large p {
            color: var(--text-secondary);
            font-size: 1rem;
            margin-bottom: 15px;
        }
        
        .stat-card-large .trend {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            font-size: 0.9rem;
        }
        
        .trend.up {
            color: var(--success-color);
        }
        
        .trend.down {
            color: var(--danger-color);
        }
        
        .table-section {
            background: var(--bg-primary);
            border-radius: 12px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: var(--shadow);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--text-primary);
        }
        
        .stats-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .stats-table th {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-weight: 600;
            text-align: left;
            padding: 15px 12px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .stats-table td {
            padding: 12px;
            border-bottom: 1px solid var(--border-light);
            vertical-align: middle;
        }
        
        .stats-table tbody tr:hover {
            background: var(--bg-secondary);
        }
        
        .export-btn {
            background: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }
        
        .export-btn:hover {
            background: var(--accent-hover);
        }
        
        .metric-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .metric-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid var(--border-light);
        }
        
        .metric-item:last-child {
            border-bottom: none;
        }
        
        .metric-label {
            color: var(--text-secondary);
            font-weight: 500;
        }
        
        .metric-value {
            color: var(--text-primary);
            font-weight: 600;
        }
        
        @media (max-width: 768px) {
            .stats-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .chart-container {
                height: 300px;
            }
            
            .stats-grid {
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
        <div class="stats-container">
            <div class="stats-header">
                <h1 class="stats-title">
                    <i class="fas fa-chart-bar"></i>
                    Статистика и аналитика
                </h1>
                
                <div>
                    <button class="export-btn" onclick="exportToExcel()">
                        <i class="fas fa-download"></i>
                        Экспорт в Excel
                    </button>
                </div>
            </div>
            
            <!-- Основные метрики -->
            <div class="stats-grid">
                <div class="stat-card-large">
                    <h3><?php echo $statistics['total_orders']; ?></h3>
                    <p>Всего заказов</p>
                    <div class="trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>+12% за месяц</span>
                    </div>
                </div>
                
                <div class="stat-card-large">
                    <h3><?php echo number_format($statistics['total_revenue'], 0, ',', ' '); ?> ₽</h3>
                    <p>Общая выручка</p>
                    <div class="trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>+8% за месяц</span>
                    </div>
                </div>
                
                <div class="stat-card-large">
                    <h3><?php echo number_format($statistics['total_paid'], 0, ',', ' '); ?> ₽</h3>
                    <p>Получено оплат</p>
                    <div class="trend up">
                        <i class="fas fa-arrow-up"></i>
                        <span>+15% за месяц</span>
                    </div>
                </div>
                
                <div class="stat-card-large">
                    <h3><?php echo number_format($statistics['total_debt'], 0, ',', ' '); ?> ₽</h3>
                    <p>Общий долг</p>
                    <div class="trend down">
                        <i class="fas fa-arrow-down"></i>
                        <span>-5% за месяц</span>
                    </div>
                </div>
            </div>
            
            <!-- График по типам работ -->
            <div class="chart-section">
                <div class="chart-header">
                    <h2 class="chart-title">
                        <i class="fas fa-pie-chart"></i>
                        Распределение по типам работ
                    </h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="workTypeChart"></canvas>
                </div>
                
                <div class="metric-grid">
                    <?php foreach ($workTypeStats as $stat): ?>
                        <div class="metric-item">
                            <span class="metric-label">
                                <?php 
                                switch($stat['work_type']) {
                                    case 'coursework': echo 'Курсовые'; break;
                                    case 'production_practice': echo 'Пр. практика'; break;
                                    case 'study_practice': echo 'Уч. практика'; break;
                                }
                                ?>
                            </span>
                            <span class="metric-value">
                                <?php echo $stat['count']; ?> заказов
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- График доходов по месяцам -->
            <div class="chart-section">
                <div class="chart-header">
                    <h2 class="chart-title">
                        <i class="fas fa-line-chart"></i>
                        Доходы по месяцам
                    </h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
            
            <!-- Статистика по типам работ -->
            <div class="table-section">
                <div class="table-header">
                    <h2 class="table-title">
                        <i class="fas fa-table"></i>
                        Детальная статистика по типам работ
                    </h2>
                </div>
                
                <div class="table-container">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Тип работы</th>
                                <th>Количество</th>
                                <th>Общая стоимость</th>
                                <th>Оплачено</th>
                                <th>Долг</th>
                                <th>Оплаченные</th>
                                <th>Средний чек</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($workTypeStats as $stat): ?>
                                <tr>
                                    <td>
                                        <span class="work-type-badge work-type-<?php echo $stat['work_type']; ?>">
                                            <?php 
                                            switch($stat['work_type']) {
                                                case 'coursework': echo 'Курсовая'; break;
                                                case 'production_practice': echo 'Пр. практика'; break;
                                                case 'study_practice': echo 'Уч. практика'; break;
                                            }
                                            ?>
                                        </span>
                                    </td>
                                    <td><?php echo $stat['count']; ?></td>
                                    <td class="price"><?php echo number_format($stat['total_revenue'], 0, ',', ' '); ?> ₽</td>
                                    <td class="price"><?php echo number_format($stat['total_paid'], 0, ',', ' '); ?> ₽</td>
                                    <td class="price debt"><?php echo number_format($stat['total_debt'], 0, ',', ' '); ?> ₽</td>
                                    <td><?php echo $stat['paid_count']; ?> из <?php echo $stat['count']; ?></td>
                                    <td class="price"><?php echo number_format($stat['total_revenue'] / $stat['count'], 0, ',', ' '); ?> ₽</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Топ клиентов -->
            <div class="table-section">
                <div class="table-header">
                    <h2 class="table-title">
                        <i class="fas fa-users"></i>
                        Топ клиентов по объему заказов
                    </h2>
                </div>
                
                <div class="table-container">
                    <table class="stats-table">
                        <thead>
                            <tr>
                                <th>Клиент</th>
                                <th>Количество заказов</th>
                                <th>Общая сумма</th>
                                <th>Средний чек</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($topClients as $client): ?>
                                <tr>
                                    <td class="client-name">
                                        <strong><?php echo htmlspecialchars($client['client_name']); ?></strong>
                                    </td>
                                    <td><?php echo $client['orders_count']; ?></td>
                                    <td class="price"><?php echo number_format($client['total_spent'], 0, ',', ' '); ?> ₽</td>
                                    <td class="price"><?php echo number_format($client['total_spent'] / $client['orders_count'], 0, ',', ' '); ?> ₽</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Кнопка «Вверх» -->
    <button type="button" class="scroll-top-btn" id="scrollTopBtn" aria-label="Наверх" title="Наверх" style="display:inline-flex;">
        <i class="fas fa-arrow-up"></i>
    </button>

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

        // Данные для графиков
        const workTypeData = {
            labels: [
                <?php foreach ($workTypeStats as $stat): ?>
                    '<?php 
                    switch($stat['work_type']) {
                        case 'coursework': echo 'Курсовые'; break;
                        case 'production_practice': echo 'Пр. практика'; break;
                        case 'study_practice': echo 'Уч. практика'; break;
                    }
                    ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                data: [
                    <?php foreach ($workTypeStats as $stat): ?>
                        <?php echo $stat['count']; ?>,
                    <?php endforeach; ?>
                ],
                backgroundColor: [
                    'rgba(102, 126, 234, 0.8)',
                    'rgba(23, 162, 184, 0.8)',
                    'rgba(40, 167, 69, 0.8)'
                ],
                borderColor: [
                    'rgba(102, 126, 234, 1)',
                    'rgba(23, 162, 184, 1)',
                    'rgba(40, 167, 69, 1)'
                ],
                borderWidth: 2
            }]
        };

        const revenueData = {
            labels: [
                <?php foreach (array_reverse($monthlyStats) as $stat): ?>
                    '<?php echo date('M Y', strtotime($stat['month'] . '-01')); ?>',
                <?php endforeach; ?>
            ],
            datasets: [{
                label: 'Доходы',
                data: [
                    <?php foreach (array_reverse($monthlyStats) as $stat): ?>
                        <?php echo $stat['revenue']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: 'rgba(102, 126, 234, 1)',
                backgroundColor: 'rgba(102, 126, 234, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }, {
                label: 'Оплаты',
                data: [
                    <?php foreach (array_reverse($monthlyStats) as $stat): ?>
                        <?php echo $stat['paid']; ?>,
                    <?php endforeach; ?>
                ],
                borderColor: 'rgba(40, 167, 69, 1)',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4
            }]
        };

        // Создание графиков
        document.addEventListener('DOMContentLoaded', function() {
            // График типов работ
            const workTypeCtx = document.getElementById('workTypeChart').getContext('2d');
            new Chart(workTypeCtx, {
                type: 'doughnut',
                data: workTypeData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    }
                }
            });

            // График доходов
            const revenueCtx = document.getElementById('revenueChart').getContext('2d');
            new Chart(revenueCtx, {
                type: 'line',
                data: revenueData,
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'top',
                            labels: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-primary')
                            }
                        }
                    },
                    scales: {
                        x: {
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary')
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        },
                        y: {
                            ticks: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--text-secondary'),
                                callback: function(value) {
                                    return value.toLocaleString('ru-RU') + ' ₽';
                                }
                            },
                            grid: {
                                color: getComputedStyle(document.documentElement).getPropertyValue('--border-color')
                            }
                        }
                    }
                }
            });
        });

        // Функция экспорта в Excel
        function exportToExcel() {
            const data = [
                ['Тип работы', 'Количество', 'Общая стоимость', 'Оплачено', 'Долг', 'Оплаченные', 'Средний чек'],
                <?php foreach ($workTypeStats as $stat): ?>
                    [
                        '<?php 
                        switch($stat['work_type']) {
                            case 'coursework': echo 'Курсовая'; break;
                            case 'production_practice': echo 'Пр. практика'; break;
                            case 'study_practice': echo 'Уч. практика'; break;
                        }
                        ?>',
                        <?php echo $stat['count']; ?>,
                        <?php echo $stat['total_revenue']; ?>,
                        <?php echo $stat['total_paid']; ?>,
                        <?php echo $stat['total_debt']; ?>,
                        '<?php echo $stat['paid_count']; ?> из <?php echo $stat['count']; ?>',
                        <?php echo round($stat['total_revenue'] / $stat['count']); ?>
                    ],
                <?php endforeach; ?>
            ];
            
            let csv = data.map(row => row.join(',')).join('\n');
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', `статистика_${new Date().toISOString().split('T')[0]}.csv`);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
    </script>
</body>
</html> 