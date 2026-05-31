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
date_default_timezone_set('Europe/Moscow');

// Проверка авторизации и прав администратора
requireAdmin();

// Инициализация классов
$order = new Order($pdo);
$workStage = new WorkStage($pdo);
$fileManager = new FileManager($pdo);

// Получение фильтров
$filters = [
    'work_type' => $_GET['work_type'] ?? '',
    'is_paid' => $_GET['is_paid'] ?? '',
    'priority' => $_GET['priority'] ?? '',
    'semester' => $_GET['semester'] ?? '',
    'search' => $_GET['search'] ?? ''
];

// Получение выбранного семестра для статистики
$selectedSemester = $_GET['semester'] ?? null;

// Получение данных
$orders = $order->getAll($filters);
$statistics = $order->getStatistics($selectedSemester);

// Обработка действий
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'delete_order':
                if (isset($_POST['order_id'])) {
                    $order->delete($_POST['order_id']);
                    header('Location: admin');
                    exit();
                }
                break;
                
            case 'toggle_priority':
                if (isset($_POST['order_id'])) {
                    $orderData = $order->getById($_POST['order_id']);
                    $newPriority = !$orderData['priority'];
                    $order->setPriority($_POST['order_id'], $newPriority);
                    header('Location: admin');
                    exit();
                }
                break;
                
            case 'update_work_status':
                if (isset($_POST['order_id']) && isset($_POST['work_status'])) {
                    $order->setWorkStatus($_POST['order_id'], $_POST['work_status']);
                    header('Location: admin');
                    exit();
                }
                break;
        }
    }
}

// Получение темы из настроек
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>ImsitShop - Админ панель</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        /* Custom styles for admin panel */
        .admin-card {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            transition: all 0.3s ease;
        }
        
        .admin-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .stat-card-modern {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .stat-card-modern:hover {
            background: rgba(255, 255, 255, 0.08);
            transform: translateY(-2px);
        }
        
        .btn-modern {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .btn-modern:hover {
            background: rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-1px);
            color: white;
        }
        
        .btn-primary-modern {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.8), rgba(147, 51, 234, 0.8));
            border-color: rgba(59, 130, 246, 0.3);
        }
        
        .btn-primary-modern:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 1), rgba(147, 51, 234, 1));
        }
        
        .table-modern {
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            overflow: hidden;
            width: 100%;
            table-layout: fixed;
        }
        
        .table-modern th {
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.9);
            font-weight: 600;
            padding: 1rem 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            text-align: center;
            font-size: 0.875rem;
        }
        
        .table-modern td {
            padding: 0.875rem 0.75rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            text-align: center;
            font-size: 0.875rem;
            vertical-align: middle;
        }
        
        /* Column widths */
        .table-modern th:nth-child(1),
        .table-modern td:nth-child(1) { width: 10%; text-align: left; } /* Имя */
        .table-modern th:nth-child(2),
        .table-modern td:nth-child(2) { width: 5%; } /* Номер */
        .table-modern th:nth-child(3),
        .table-modern td:nth-child(3) { width: 9%; } /* Тип */
        .table-modern th:nth-child(4),
        .table-modern td:nth-child(4) { width: 7%; } /* Семестр */
        .table-modern th:nth-child(5),
        .table-modern td:nth-child(5) { width: 5%; } /* Оплата */
        .table-modern th:nth-child(6),
        .table-modern td:nth-child(6) { width: 7%; } /* Цена */
        .table-modern th:nth-child(7),
        .table-modern td:nth-child(7) { width: 7%; } /* Оплатили */
        .table-modern th:nth-child(8),
        .table-modern td:nth-child(8) { width: 5%; } /* Долг */
        .table-modern th:nth-child(9),
        .table-modern td:nth-child(9) { width: 5%; } /* Работа */
        .table-modern th:nth-child(10),
        .table-modern td:nth-child(10) { width: 4%; } /* 1Г */
        .table-modern th:nth-child(11),
        .table-modern td:nth-child(11) { width: 4%; } /* 2Г */
        .table-modern th:nth-child(12),
        .table-modern td:nth-child(12) { width: 4%; } /* 3,4Г */
        .table-modern th:nth-child(13),
        .table-modern td:nth-child(13) { width: 4%; } /* App */
        .table-modern th:nth-child(14),
        .table-modern td:nth-child(14) { width: 12%; } /* Действия */
        
        .table-modern tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Work Type Colors */
        .work-type-coursework {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
            color: #60a5fa;
            border: 1px solid rgba(59, 130, 246, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .work-type-production_practice {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .work-type-study_practice {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
            color: #fb923c;
            border: 1px solid rgba(251, 146, 60, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Semester Colors */
        .semester-6 {
            background: linear-gradient(135deg, rgba(168, 85, 247, 0.2), rgba(147, 51, 234, 0.2));
            color: #a855f7;
            border: 1px solid rgba(168, 85, 247, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .semester-7 {
            background: linear-gradient(135deg, rgba(236, 72, 153, 0.2), rgba(219, 39, 119, 0.2));
            color: #ec4899;
            border: 1px solid rgba(236, 72, 153, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .semester-8 {
            background: linear-gradient(135deg, rgba(14, 165, 233, 0.2), rgba(2, 132, 199, 0.2));
            color: #0ea5e9;
            border: 1px solid rgba(14, 165, 233, 0.3);
            padding: 0.25rem 0.75rem;
            border-radius: 8px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Payment Status Colors */
        .status-paid {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-unpaid {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            color: #f87171;
            border: 1px solid rgba(239, 68, 68, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-in-progress {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
            color: #fb923c;
            border: 1px solid rgba(251, 146, 60, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .status-pending {
            background: linear-gradient(135deg, rgba(156, 163, 175, 0.2), rgba(107, 114, 128, 0.2));
            color: #9ca3af;
            border: 1px solid rgba(156, 163, 175, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Stage Status Colors */
        .stage-status.completed {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
            color: #4ade80;
            border: 1px solid rgba(34, 197, 94, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stage-status.in-progress {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
            color: #fb923c;
            border: 1px solid rgba(251, 146, 60, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        .stage-status.pending {
            background: linear-gradient(135deg, rgba(156, 163, 175, 0.2), rgba(107, 114, 128, 0.2));
            color: #9ca3af;
            border: 1px solid rgba(156, 163, 175, 0.3);
            padding: 0.25rem 0.5rem;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 500;
        }
        
        /* Action Buttons */
        .action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s ease;
            margin: 0 2px;
            font-size: 0.8rem;
        }
        
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-1px);
        }
        
        .action-btn.edit {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.2), rgba(147, 51, 234, 0.2));
            border-color: rgba(59, 130, 246, 0.3);
            color: #60a5fa;
        }
        
        .action-btn.edit:hover {
            background: linear-gradient(135deg, rgba(59, 130, 246, 0.4), rgba(147, 51, 234, 0.4));
            color: #93c5fd;
        }
        
        .action-btn.view {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.2), rgba(16, 185, 129, 0.2));
            border-color: rgba(34, 197, 94, 0.3);
            color: #4ade80;
        }
        
        .action-btn.view:hover {
            background: linear-gradient(135deg, rgba(34, 197, 94, 0.4), rgba(16, 185, 129, 0.4));
            color: #6ee7b7;
        }
        
        .action-btn.priority {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.2), rgba(249, 115, 22, 0.2));
            border-color: rgba(251, 146, 60, 0.3);
            color: #fb923c;
        }
        
        .action-btn.priority:hover {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.4), rgba(249, 115, 22, 0.4));
            color: #fdba74;
        }
        
        .action-btn.delete {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.2), rgba(220, 38, 38, 0.2));
            border-color: rgba(239, 68, 68, 0.3);
            color: #f87171;
        }
        
        .action-btn.delete:hover {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.4), rgba(220, 38, 38, 0.4));
            color: #fca5a5;
        }
        
        /* Priority Row */
        .priority-row {
            background: linear-gradient(135deg, rgba(251, 146, 60, 0.05), rgba(249, 115, 22, 0.05));
            border-left: 3px solid #fb923c;
        }
        
        .priority-icon {
            color: #fb923c;
            margin-left: 0.5rem;
        }
        
        /* Debt Amount */
        .debt {
            color: #f87171;
            font-weight: 600;
        }
        
        /* Price Styling */
        .price {
            font-weight: 600;
            color: #e2e8f0;
        }
        
        /* Text overflow handling */
        .table-modern td {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .table-modern td:nth-child(1) {
            white-space: normal;
            text-overflow: unset;
            overflow: visible;
            line-height: 1.2;
            padding: 0.5rem 0.75rem;
        }
        
        /* Name display in two lines */
        .client-name {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.125rem;
        }
        
        .client-name .last-name {
            font-weight: 600;
            font-size: 0.875rem;
            color: #e2e8f0;
        }
        
        .client-name .first-name {
            font-weight: 400;
            font-size: 0.8rem;
            color: #94a3b8;
        }
        
        .client-name {
            position: relative;
        }
        
        .priority-icon {
            position: absolute;
            top: 0.25rem;
            right: 0.25rem;
            font-size: 0.75rem;
            color: #fb923c;
        }
        
        /* Badge sizing */
        .work-type-coursework,
        .work-type-production_practice,
        .work-type-study_practice,
        .semester-6,
        .semester-7,
        .semester-8,
        .status-paid,
        .status-unpaid,
        .status-in-progress,
        .status-pending,
        .stage-status.completed,
        .stage-status.in-progress,
        .stage-status.pending {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .filter-modern {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            padding: 0.75rem 1rem;
        }
        
        .filter-modern::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        
        .filter-modern:focus {
            outline: none;
            border-color: rgba(59, 130, 246, 0.5);
            background: rgba(255, 255, 255, 0.15);
        }
        
        .scroll-top-btn {
            position: fixed;
            right: 20px;
            bottom: 24px;
            width: 48px;
            height: 48px;
            border-radius: 50%;
            border: none;
            outline: none;
            cursor: pointer;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            transform: translateY(10px) scale(0.95);
            pointer-events: none;
            transition: all 0.3s ease;
        }
        
        .scroll-top-btn.visible {
            opacity: 1;
            transform: translateY(0) scale(1);
            pointer-events: auto;
        }
        
        .scroll-top-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px) scale(1.05);
        }
        
        /* Mobile responsiveness */
        @media (max-width: 768px) {
            .stats-grid-modern {
                grid-template-columns: 1fr 1fr;
                gap: 1rem;
            }
            
            .control-panel-modern {
                flex-direction: column;
                gap: 1rem;
            }
            
            .filters-modern {
                flex-direction: column;
                gap: 0.75rem;
            }
            
            .table-container {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                border-radius: 12px;
            }
            
            .table-modern {
                min-width: 1200px;
            }
            
            .table-modern th,
            .table-modern td {
                padding: 0.75rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .action-btn {
                width: 28px;
                height: 28px;
                margin: 0 1px;
                font-size: 0.75rem;
            }
            
            .client-name .last-name {
                font-size: 0.8rem;
            }
            
            .client-name .first-name {
                font-size: 0.75rem;
            }
            
            .work-type-coursework,
            .work-type-production_practice,
            .work-type-study_practice,
            .semester-6,
            .semester-7,
            .semester-8 {
                font-size: 0.625rem;
                padding: 0.125rem 0.5rem;
            }
        }
        
        @media (max-width: 480px) {
            .stats-grid-modern {
                grid-template-columns: 1fr;
            }
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
        <div class="max-w-[90rem] flex mr-auto ml-auto items-center justify-between">
            <div class="flex items-center gap-3">
                <div class="relative grid h-12 w-12 place-items-center rounded-xl bg-gradient-to-br from-indigo-500/70 to-fuchsia-500/70 text-white ring-1 ring-white/20 shadow-lg">
                    <i class="fas fa-chart-line text-xl"></i>
                </div>
                <div>
                    <h1 class="text-[22px] sm:text-2xl font-semibold tracking-tight">ImsitShop - Админ панель</h1>
                    <p class="text-sm text-slate-300">Управление заказами и пользователями</p>
                </div>
            </div>
            <div class="flex gap-2 items-center">
                <!-- Time -->
                <div class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md">
                    <i class="fas fa-clock h-4 w-4"></i>
                    <span id="moscow-time" class="tabular-nums"></span>
                </div>
                
                <!-- User -->
                <div class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md">
                    <i class="fas fa-user h-4 w-4"></i>
                        <?php echo htmlspecialchars($_SESSION['username']); ?>
                </div>
                
                <!-- Logout -->
                <form method="POST" action="logout" class="inline">
                    <button type="submit" class="inline-flex items-center gap-2 rounded-full bg-white/5 px-3.5 py-2 text-sm text-slate-200 ring-1 ring-white/10 backdrop-blur-md hover:bg-white/10 hover:ring-white/20 active:scale-[0.98] transition">
                        <i class="fas fa-sign-out-alt h-4 w-4"></i>
                        Выйти
                    </button>
                </form>
            </div>
        </div>
    </header>

    <main class="px-4 pb-24 sm:px-6">
        <section class="mx-auto max-w-[90rem] space-y-6">
            <!-- Statistics Section -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl ring-1 ring-white/10">
                <div class="absolute inset-x-0 -top-24 h-48 bg-gradient-to-b from-white/10 to-transparent"></div>
                <div class="p-5 sm:p-7">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-emerald-500/70 to-teal-500/70 text-white ring-1 ring-white/20 shadow-lg">
                            <i class="fas fa-chart-bar text-lg"></i>
                        </div>
                        <h2 class="text-xl font-semibold tracking-tight">Статистика</h2>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="stat-card-modern">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 grid place-items-center rounded-lg bg-blue-500/20 text-blue-300 ring-1 ring-blue-400/30">
                                    <i class="fas fa-clipboard-list text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-white"><?php echo $statistics['total_orders']; ?></h3>
                                    <p class="text-sm text-slate-300">Всего заказов</p>
                                </div>
                    </div>
                </div>
                
                        <div class="stat-card-modern">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 grid place-items-center rounded-lg bg-emerald-500/20 text-emerald-300 ring-1 ring-emerald-400/30">
                                    <i class="fas fa-ruble-sign text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-white"><?php echo number_format($statistics['total_revenue'], 0, ',', ' '); ?> ₽</h3>
                                    <p class="text-sm text-slate-300">Общая сумма</p>
                    </div>
                    </div>
                </div>
                
                        <div class="stat-card-modern">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 grid place-items-center rounded-lg bg-green-500/20 text-green-300 ring-1 ring-green-400/30">
                                    <i class="fas fa-check-circle text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-white"><?php echo number_format($statistics['total_paid'], 0, ',', ' '); ?> ₽</h3>
                                    <p class="text-sm text-slate-300">Оплачено</p>
                    </div>
                    </div>
                </div>
                
                        <div class="stat-card-modern">
                            <div class="flex items-center gap-3">
                                <div class="h-10 w-10 grid place-items-center rounded-lg bg-red-500/20 text-red-300 ring-1 ring-red-400/30">
                                    <i class="fas fa-exclamation-triangle text-lg"></i>
                                </div>
                                <div>
                                    <h3 class="text-2xl font-bold text-white"><?php echo number_format($statistics['total_debt'], 0, ',', ' '); ?> ₽</h3>
                                    <p class="text-sm text-slate-300">Долг</p>
                                </div>
                            </div>
                    </div>
                    </div>
                </div>
            </div>

            <!-- Control Panel -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl ring-1 ring-white/10">
                <div class="absolute inset-x-0 -top-24 h-48 bg-gradient-to-b from-white/10 to-transparent"></div>
                <div class="p-5 sm:p-7">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-purple-500/70 to-pink-500/70 text-white ring-1 ring-white/20 shadow-lg">
                            <i class="fas fa-cogs text-lg"></i>
                        </div>
                        <h2 class="text-xl font-semibold tracking-tight">Панель управления</h2>
                    </div>
                    
                    <div class="space-y-6">
                        <!-- Main Action Button -->
                        <div class="flex justify-center lg:justify-start">
                            <a href="add_order" class="btn-modern btn-primary-modern text-lg px-8 py-4">
                    <i class="fas fa-plus"></i>
                    Добавить заказ
                </a>
                        </div>
                
                        <!-- Filters Row -->
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-3">
                    <input type="text" id="search" placeholder="Поиск по имени..." 
                                   value="<?php echo htmlspecialchars($filters['search']); ?>" class="filter-modern">
                    
                            <select id="work-type-filter" class="filter-modern">
                        <option value="">Все типы работ</option>
                        <option value="coursework" <?php echo $filters['work_type'] === 'coursework' ? 'selected' : ''; ?>>Курсовая</option>
                        <option value="production_practice" <?php echo $filters['work_type'] === 'production_practice' ? 'selected' : ''; ?>>Производственная практика</option>
                        <option value="study_practice" <?php echo $filters['work_type'] === 'study_practice' ? 'selected' : ''; ?>>Учебная практика</option>
                    </select>
                    
                            <select id="payment-filter" class="filter-modern">
                        <option value="">Все заказы</option>
                        <option value="1" <?php echo $filters['is_paid'] === '1' ? 'selected' : ''; ?>>Оплаченные</option>
                        <option value="0" <?php echo $filters['is_paid'] === '0' ? 'selected' : ''; ?>>Неоплаченные</option>
                    </select>
                    
                            <select id="priority-filter" class="filter-modern">
                        <option value="">Все приоритеты</option>
                        <option value="1" <?php echo $filters['priority'] === '1' ? 'selected' : ''; ?>>Приоритетные</option>
                        <option value="0" <?php echo $filters['priority'] === '0' ? 'selected' : ''; ?>>Обычные</option>
                    </select>
                    
                            <select id="semester-filter" class="filter-modern">
                        <option value="">Все семестры</option>
                        <option value="6" <?php echo $filters['semester'] === '6' ? 'selected' : ''; ?>>6 семестр</option>
                        <option value="7" <?php echo $filters['semester'] === '7' ? 'selected' : ''; ?>>7 семестр</option>
                        <option value="8" <?php echo $filters['semester'] === '8' ? 'selected' : ''; ?>>8 семестр</option>
                    </select>
            </div>
            
                        <!-- Action Buttons -->
                        <div class="flex flex-wrap justify-center lg:justify-start gap-3">
                            <button class="btn-modern" onclick="toggleSemester()">
                        <i class="fas fa-calendar-alt"></i>
                        <?php 
                        if ($selectedSemester) {
                            echo $selectedSemester . ' семестр';
                        } else {
                            echo 'Все время';
                        }
                        ?>
                    </button>
                
                <?php if (isAdmin()): ?>
                                <a href="users_management" class="btn-modern">
                        <i class="fas fa-users"></i>
                        Пользователи
                    </a>
                <?php endif; ?>
                
                            <a href="trash.php" class="btn-modern">
                    <i class="fas fa-trash"></i>
                    Корзина
                </a>
                
                            <a href="statistics" class="btn-modern">
                    <i class="fas fa-chart-bar"></i>
                    Статистика
                </a>

                            <a href="request_order" class="btn-modern">
                                <i class="fas fa-plus"></i>
                                Запросы на заказы
                            </a>
                            
                            <a href="notifications_management" class="btn-modern">
                                <i class="fas fa-bell"></i>
                                Уведомления
                            </a>
                            
                            <a href="schedule_management" class="btn-modern">
                                <i class="fas fa-calendar-alt"></i>
                                Расписание
                            </a>
                            
                            
                            <a href="telegram_settings" class="btn-modern">
                                <i class="fab fa-telegram"></i>
                                Telegram
                            </a>
                            
                            <a href="teachers_management" class="btn-modern">
                                <i class="fas fa-chalkboard-teacher"></i>
                                Преподаватели
                            </a>
                            
                            <a href="teacher_schedule_management" class="btn-modern">
                                <i class="fas fa-calendar-plus"></i>
                                Расписание преподавателей
                            </a>
                            
                            <a href="telegram_bot_web.php" class="btn-modern" target="_blank">
                                <i class="fas fa-robot"></i>
                                Telegram Bot Web
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Orders Table -->
            <div class="relative overflow-hidden rounded-2xl border border-white/10 bg-white/5 backdrop-blur-xl shadow-2xl ring-1 ring-white/10">
                <div class="absolute inset-x-0 -top-24 h-48 bg-gradient-to-b from-white/10 to-transparent"></div>
                <div class="p-5 sm:p-7">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="relative grid h-10 w-10 place-items-center rounded-xl bg-gradient-to-br from-orange-500/70 to-red-500/70 text-white ring-1 ring-white/20 shadow-lg">
                            <i class="fas fa-list text-lg"></i>
                        </div>
                        <h2 class="text-xl font-semibold tracking-tight">Заказы</h2>
                    </div>
                    
            <div class="table-container">
                        <table class="table-modern w-full">
                    <thead>
                        <tr>
                            <th>Имя</th>
                            <th>Номер</th>
                            <th>Тип</th>
                            <th>Семестр</th>
                            <th>Оплата</th>
                            <th>Цена</th>
                            <th>Оплатили</th>
                            <th>Долг</th>
                            <th>Работа</th>
                            <th>1 Г</th>
                            <th>2 Г</th>
                            <th>3,4 Г</th>
                            <th>App</th>
                            <th>Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $orderData): ?>
                            <?php 
                            $stages = $workStage->getByOrderId($orderData['id']);
                            $stageMap = [];
                            foreach ($stages as $stage) {
                                $stageMap[$stage['stage_type']] = $stage;
                            }
                            ?>
                            <tr class="<?php echo $orderData['priority'] ? 'priority-row' : ''; ?>">
                                <td class="client-name">
                                    <?php 
                                    $fullName = htmlspecialchars($orderData['client_name']);
                                    $nameParts = explode(' ', $fullName, 2);
                                    $lastName = $nameParts[0] ?? '';
                                    $firstName = $nameParts[1] ?? '';
                                    ?>
                                    <div class="last-name"><?php echo $lastName; ?></div>
                                    <div class="first-name"><?php echo $firstName; ?></div>
                                    <?php if ($orderData['priority']): ?>
                                        <i class="fas fa-star priority-icon"></i>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($orderData['topic_number'] ?? '-'); ?></td>
                                <td>
                                    <span class="work-type-<?php echo $orderData['work_type']; ?>">
                                        <?php 
                                        switch($orderData['work_type']) {
                                            case 'coursework': echo 'Курсовая'; break;
                                            case 'production_practice': echo 'Пр. практика'; break;
                                            case 'study_practice': echo 'Уч. практика'; break;
                                        }
                                        ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="semester-<?php echo $orderData['semester'] ?? '6'; ?>">
                                        <?php echo $orderData['semester'] ?? '6'; ?> сем.
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $orderData['is_paid'] ? 'status-paid' : 'status-unpaid'; ?>">
                                        <i class="fas fa-<?php echo $orderData['is_paid'] ? 'check' : 'times'; ?>"></i>
                                    </span>
                                </td>
                                <td class="price"><?php echo number_format($orderData['total_price'], 0, ',', ' '); ?> ₽</td>
                                <td class="price"><?php echo number_format($orderData['paid_amount'], 0, ',', ' '); ?> ₽</td>
                                <td class="price <?php echo $orderData['debt_amount'] > 0 ? 'debt' : ''; ?>">
                                    <?php echo number_format($orderData['debt_amount'], 0, ',', ' '); ?> ₽
                                </td>
                                <td>
                                    <?php 
                                    $workStatus = $orderData['work_status'] ?? 'pending';
                                    $statusClass = $workStatus === 'completed' ? 'status-paid' : ($workStatus === 'in_progress' ? 'status-in-progress' : 'status-pending');
                                    $statusIcon = $workStatus === 'completed' ? 'check' : ($workStatus === 'in_progress' ? 'play' : 'clock');
                                    ?>
                                    <span class="<?php echo $statusClass; ?>">
                                        <i class="fas fa-<?php echo $statusIcon; ?>"></i>
                                    </span>
                                </td>
                                <td>
                                    <?php if (isset($stageMap['chapter1'])): ?>
                                        <span class="stage-status <?php echo $stageMap['chapter1']['is_completed'] ? 'completed' : ($stageMap['chapter1']['is_in_progress'] ? 'in-progress' : 'pending'); ?>">
                                            <i class="fas fa-<?php echo $stageMap['chapter1']['is_completed'] ? 'check' : ($stageMap['chapter1']['is_in_progress'] ? 'play' : 'clock'); ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($stageMap['chapter2'])): ?>
                                        <span class="stage-status <?php echo $stageMap['chapter2']['is_completed'] ? 'completed' : ($stageMap['chapter2']['is_in_progress'] ? 'in-progress' : 'pending'); ?>">
                                            <i class="fas fa-<?php echo $stageMap['chapter2']['is_completed'] ? 'check' : ($stageMap['chapter2']['is_in_progress'] ? 'play' : 'clock'); ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($stageMap['chapter34'])): ?>
                                        <span class="stage-status <?php echo $stageMap['chapter34']['is_completed'] ? 'completed' : ($stageMap['chapter34']['is_in_progress'] ? 'in-progress' : 'pending'); ?>">
                                            <i class="fas fa-<?php echo $stageMap['chapter34']['is_completed'] ? 'check' : ($stageMap['chapter34']['is_in_progress'] ? 'play' : 'clock'); ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if (isset($stageMap['application'])): ?>
                                        <span class="stage-status <?php echo $stageMap['application']['is_completed'] ? 'completed' : ($stageMap['application']['is_in_progress'] ? 'in-progress' : 'pending'); ?>">
                                            <i class="fas fa-<?php echo $stageMap['application']['is_completed'] ? 'check' : ($stageMap['application']['is_in_progress'] ? 'play' : 'clock'); ?>"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="actions">
                                    <a href="edit_order?id=<?php echo $orderData['id']; ?>" class="action-btn edit" title="Редактировать">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="order_details?id=<?php echo $orderData['id']; ?>" class="action-btn view" title="Просмотр">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <button onclick="togglePriority(<?php echo $orderData['id']; ?>)" class="action-btn priority" title="Приоритет">
                                        <i class="fas fa-star"></i>
                                    </button>
                                    <button onclick="deleteOrder(<?php echo $orderData['id']; ?>)" class="action-btn delete" title="Удалить">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Кнопка «Вверх» -->
    <button type="button" class="scroll-top-btn" id="scrollTopBtn" aria-label="Наверх" title="Наверх" style="display:inline-flex;">
        <i class="fas fa-arrow-up"></i>
    </button>

    <script>
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
        
        // Функция для отображения времени по МСК
        function updateMoscowTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ru-RU', {
                timeZone: 'Europe/Moscow',
                hour12: false,
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('moscow-time').textContent = timeString;
        }
        
        // Обновляем время каждую секунду
        updateMoscowTime();
        setInterval(updateMoscowTime, 1000);

        // Автоматическое обновление времени расписания каждую минуту
        setInterval(async function() {
            try {
                await fetch('api/auto_update_schedule_time.php');
            } catch (error) {
                console.error('Ошибка автоматического обновления времени:', error);
            }
        }, 60000); // Каждую минуту
    </script>

    <script src="https://unpkg.com/lucide@latest"></script>
    <script src="assets/js/app.js"></script>
</body>
</html> 