<?php
// Запускаем сессию СРАЗУ
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'includes/OrderRequest.php';

requireAdmin();

$service = new OrderRequestService($pdo);
$error = '';
$success = '';

// Обработка форм
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['update_settings'])) {
            $settings = [
                'coursework' => [
                    'is_open' => isset($_POST['open_coursework']) ? 1 : 0,
                    'default_price' => (float)($_POST['price_coursework'] ?? 0)
                ],
                'production_practice' => [
                    'is_open' => isset($_POST['open_production_practice']) ? 1 : 0,
                    'default_price' => (float)($_POST['price_production_practice'] ?? 0)
                ],
                'study_practice' => [
                    'is_open' => isset($_POST['open_study_practice']) ? 1 : 0,
                    'default_price' => (float)($_POST['price_study_practice'] ?? 0)
                ],
            ];
            $service->updateSettings($settings);
            $success = 'Настройки обновлены';
        }

        if (isset($_POST['approve_id'])) {
            $id = (int)$_POST['approve_id'];
            $price = (float)($_POST['approve_price'] ?? 0);
            if ($price <= 0) { throw new Exception('Укажите корректную стоимость'); }
            $orderId = $service->approveRequest($id, $price);
            $success = 'Заявка одобрена. Создан заказ #' . $orderId;
        }

        if (isset($_POST['reject_id'])) {
            $id = (int)$_POST['reject_id'];
            $service->rejectRequest($id);
            $success = 'Заявка отклонена';
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

$settings = $service->getSettings();
$pending = $service->listRequests('pending');
$approved = $service->listRequests('approved');
$rejected = $service->listRequests('rejected');
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ImsitShop - Запросы на заказы</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        .card { background: var(--bg-primary); border-radius: 12px; padding: 20px; box-shadow: var(--shadow); }
        .grid { display: grid; gap: 16px; }
        .grid-2 { grid-template-columns: 1fr 1fr; }
        .grid-3 { grid-template-columns: 1fr 1fr 1fr; }
        .table { width:100%; border-collapse: collapse; table-layout: fixed; }
        .table th, .table td { padding: 10px; border-bottom: 1px solid var(--border-color); text-align: center; }
        .badge { padding: 3px 8px; border-radius: 6px; font-size: .85rem; }
        .badge-pending { background: #ffdd57; color: #663c00; }
        .badge-approved { background: #51cf66; color: #0b3d1a; }
        .badge-rejected { background: #ffa8a8; color: #611a15; }
        .controls { display:flex; gap:8px; align-items:center; }
        .muted { color: var(--text-secondary); font-size:.9rem; }
        @media (max-width: 900px) { .container { padding: 0 0.5rem; } }

        /* Красивые инпуты как в фильтрах */
        .filter-input { padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-primary); color: var(--text-primary); font-size: 0.9rem; transition: all .3s; }
        .filter-input:focus { outline:none; border-color: var(--accent-color); box-shadow: 0 0 0 3px rgba(102,126,234,.1); }
        .price-input { width: 140px; text-align: right; }

        /* Аккуратные чекбоксы */
        .controls input[type="checkbox"] { width: 18px; height: 18px; accent-color: var(--accent-color); }

        /* Кнопки действий как на главной */
        .actions-inline { display: inline-flex; gap: 6px; align-items: center; vertical-align: middle; }
        .action-btn.approve:hover { background: var(--success-color); color: #fff; }
        .action-btn.reject:hover { background: var(--danger-color); color: #fff; }
        .actions-cell { white-space: nowrap; text-align: center; }
        .table th.col-actions, .table td.col-actions { width: 260px; }
    </style>
    <script>
        function confirmReject(form){
            if(confirm('Отклонить заявку?')) { form.submit(); }
            return false;
        }
        function toggleTheme(){
            const html = document.documentElement;
            const icon = document.getElementById('theme-icon');
            if (html.getAttribute('data-theme') === 'dark') {
                html.removeAttribute('data-theme');
                icon.className = 'fas fa-moon';
                document.cookie = 'theme=light; path=/; max-age=31536000';
            } else {
                html.setAttribute('data-theme','dark');
                icon.className = 'fas fa-sun';
                document.cookie = 'theme=dark; path=/; max-age=31536000';
            }
        }
        document.addEventListener('DOMContentLoaded', function(){
            const theme = (document.cookie.match(/(?:^|; )theme=([^;]+)/)||[])[1];
            const icon = document.getElementById('theme-icon');
            if (theme === 'dark') { document.documentElement.setAttribute('data-theme','dark'); if(icon) icon.className='fas fa-sun'; }
        });
    </script>
    </head>
<body>
    <header class="header">
        <div class="header-content">
            <div class="header-left">
                <h1 class="logo">
                    <i class="fas fa-chart-line"></i>
                    ImsitShop - Админ панель
                </h1>
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
                    <a href="admin" class="nav-btn"><i class="fas fa-list"></i> Заказы</a>
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
    <div class="container" style="max-width:1200px; margin: 30px auto; padding: 0 16px;">
        <h1 style="margin-bottom: 16px;">Запросы на заказы</h1>

        <?php if ($error): ?>
            <div class="alert alert-error"><i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
            <div class="alert alert-success"><i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card" style="margin-bottom: 20px;">
            <h3 style="margin-bottom: 10px;">Настройки приёма заявок</h3>
            <form method="POST">
                <input type="hidden" name="update_settings" value="1">
                <div class="grid grid-3">
                    <?php
                        $byKey = [];
                        foreach ($settings as $s) { $byKey[$s['work_type']] = $s; }
                        $types = [
                            'coursework' => 'Курсовая работа',
                            'production_practice' => 'Производственная практика',
                            'study_practice' => 'Учебная практика',
                        ];
                        foreach ($types as $key => $label):
                            $cfg = $byKey[$key] ?? ['is_open'=>0,'default_price'=>0];
                    ?>
                    <div class="card">
                        <h4 style="margin-bottom: 8px;"><?php echo $label; ?></h4>
                        <label class="controls">
                            <input type="checkbox" name="open_<?php echo $key; ?>" <?php echo ((int)$cfg['is_open']===1?'checked':''); ?>> Приём заявок открыт
                        </label>
                        <div style="margin-top:8px;" class="controls">
                            <span class="muted">Базовая стоимость:</span>
                            <input type="number" step="0.01" min="0" name="price_<?php echo $key; ?>" value="<?php echo htmlspecialchars($cfg['default_price']); ?>" class="filter-input price-input">
                        </div>
                        <div class="muted" style="margin-top:6px;">Можно поменять на этапе одобрения</div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="margin-top: 12px;">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Сохранить настройки</button>
                </div>
            </form>
        </div>

        <div class="card" style="margin-bottom:20px;">
            <h3>Ожидают обработки</h3>
            <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Клиент</th>
                            <th>Тип</th>
                            <th>Тема</th>
                            <th>Описание</th>
                            <th class="col-actions">Действия</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending as $r): ?>
                        <tr>
                            <td>#<?php echo (int)$r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['work_type']); ?></td>
                            <td><?php echo htmlspecialchars($r['topic_number'] ?? ''); ?></td>
                            <td style="max-width:320px; white-space:break-spaces;">&nbsp;<?php echo htmlspecialchars($r['topic_description'] ?? ''); ?></td>
                            <td class="actions-cell">
                                <form method="POST" class="actions-inline">
                                    <input type="hidden" name="approve_id" value="<?php echo (int)$r['id']; ?>">
                                    <input type="number" step="0.01" min="0" name="approve_price" value="<?php echo htmlspecialchars($service->getDefaultPrice($r['work_type'])); ?>" class="filter-input price-input" placeholder="Цена" style="margin-right:6px;">
                                    <button class="action-btn approve" type="submit" title="Одобрить"><i class="fas fa-check"></i></button>
                                </form>
                                <form method="POST" class="actions-inline" onsubmit="return confirm('Отклонить заявку?');">
                                    <input type="hidden" name="reject_id" value="<?php echo (int)$r['id']; ?>">
                                    <button class="action-btn reject" type="submit" title="Отклонить"><i class="fas fa-times"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($pending)): ?>
                        <tr><td colspan="6" class="muted">Нет заявок</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
        </div>
        <div class="card" style="margin-bottom:20px;">
                <h3>Одобренные</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Клиент</th>
                            <th>Тип</th>
                            <th>Цена</th>
                            <th>Заказ</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($approved as $r): ?>
                        <tr>
                            <td>#<?php echo (int)$r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['work_type']); ?></td>
                            <td><?php echo number_format((float)$r['approved_price'], 2, ',', ' '); ?> ₽</td>
                            <td>#<?php echo (int)$r['approved_order_id']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($approved)): ?>
                        <tr><td colspan="5" class="muted">Нет записей</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        <div class="card" style="margin-bottom:20px;">
                <h3>Отклонённые</h3>
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Клиент</th>
                            <th>Тип</th>
                            <th>Тема</th>
                            <th>Описание</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($rejected as $r): ?>
                        <tr>
                            <td>#<?php echo (int)$r['id']; ?></td>
                            <td><?php echo htmlspecialchars($r['client_name']); ?></td>
                            <td><?php echo htmlspecialchars($r['work_type']); ?></td>
                            <td><?php echo htmlspecialchars($r['topic_number'] ?? ''); ?></td>
                            <td style=\"max-width:320px; white-space:break-spaces;\">&nbsp;<?php echo htmlspecialchars($r['topic_description'] ?? ''); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($rejected)): ?>
                        <tr><td colspan=\"5\" class=\"muted\">Нет записей</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>

