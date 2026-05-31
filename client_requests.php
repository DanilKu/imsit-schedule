<?php
// Начинаем сессию в самом начале
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/auth.php';
require_once 'includes/OrderRequest.php';

$service = new OrderRequestService($pdo);
$error = '';
$success = '';

// Получаем информацию о текущем пользователе
$currentUser = getCurrentUser();
if (!$currentUser) {
    header('Location: login.php');
    exit;
}
$currentUserId = $currentUser['id'];

// Получаем заявки только текущего пользователя
$pending = $service->listRequests('pending', $currentUserId);
$approved = $service->listRequests('approved', $currentUserId);
$rejected = $service->listRequests('rejected', $currentUserId);
$theme = $_COOKIE['theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="ru" data-theme="<?php echo $theme; ?>">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Мои заявки — ImsitShop</title>
  <link rel="icon" href="assets/icons/favicon.svg" type="image/svg+xml">
  <link rel="icon" href="assets/icons/favicon-32x32.png" sizes="32x32" type="image/png">
  <link rel="icon" href="assets/icons/favicon-16x16.png" sizes="16x16" type="image/png">
  <link rel="apple-touch-icon" href="assets/icons/apple-touch-icon.png">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link href="assets/css/style.css" rel="stylesheet">
  <style>
    .container { max-width: 1100px; margin: 20px auto; padding: 0 16px; }
    .card { background: var(--bg-primary); border-radius: 12px; padding: 18px; box-shadow: var(--shadow); }
    .header-row { display:flex; justify-content: space-between; align-items:center; margin-bottom: 12px; }
    .table { width:100%; border-collapse: collapse; table-layout: fixed; }
    .table th, .table td { padding: 10px; border-bottom: 1px solid var(--border-color); text-align:center; }
    /* Бейджи статусов без фона/кружков */
    .status-badge { display:inline-flex; align-items:center; gap:8px; font-weight:700; font-size:1rem; white-space:nowrap; }
    .status-badge i { font-size: 1rem; }
    /* Светлая тема (только цвет текста/иконки) */
    .status-badge.pending { color:#8a6d3b; }
    .status-badge.approved { color:#0b5d1e; }
    .status-badge.rejected { color:#7a1a1a; }
    /* Тёмная тема (более контрастные цвета) */
    [data-theme="dark"] .status-badge.pending { color:#ffcd39; }
    [data-theme="dark"] .status-badge.approved { color:#51cf66; }
    [data-theme="dark"] .status-badge.rejected { color:#ff6b6b; }
    
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

    .header-left {
      font-size: 1.5rem;
      font-weight: bold;
      color: var(--secondary-color);
    }
  </style>
</head>
<body>
<header class="header">
  <div class="header-content">
    <div class="header-left">
      <b>ImsitShop</b>
    </div>
    <div class="header-right">
             <span class="username"><i class="fas fa-user"></i> <?php echo htmlspecialchars($currentUser['client_name'] ?? 'Пользователь'); ?></span>
                <form method="POST" action="logout" style="display:inline;">
        <button type="submit" class="logout-btn" style="background:none; border:none; cursor:pointer; color:inherit; font:inherit;">
          <i class="fas fa-sign-out-alt"></i> Выйти
        </button>
      </form>
    </div>
  </div>
</header>

<main class="container">
  <div class="card">
    <div class="header-row">
      <h2 style="margin:0;">Мои заявки</h2>
      <a href="client_dashboard" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Назад</a>
    </div>

    <table class="table">
             <thead>
         <tr>
           <th>ID</th>
           <th>Тип</th>
           <th>Тема</th>
           <th>Статус</th>
           <th>Заказ</th>
         </tr>
       </thead>
             <tbody>
         <?php 
         $allRequests = array_merge($pending, $approved, $rejected);
         if (empty($allRequests)): ?>
           <tr>
             <td colspan="5" style="text-align: center; padding: 40px;">
               <p style="margin: 0; color: var(--text-muted);">У вас пока нет заявок на заказы</p>
             </td>
           </tr>
         <?php else: ?>
           <?php foreach ($allRequests as $request): ?>
             <tr>
               <td>#<?php echo $request['id']; ?></td>
               <td>
                 <?php 
                 switch($request['work_type']) {
                   case 'coursework': echo 'Курсовая работа'; break;
                   case 'production_practice': echo 'Производственная практика'; break;
                   case 'study_practice': echo 'Учебная практика'; break;
                   default: echo htmlspecialchars($request['work_type'] ?? 'Не указано');
                 }
                 ?>
               </td>
               <td><?php echo htmlspecialchars($request['topic_number'] ?? '—'); ?></td>
               <td>
                 <?php if ($request['status'] === 'pending'): ?>
                   <span class="status-badge pending"><i class="fas fa-clock"></i> Ожидает</span>
                 <?php elseif ($request['status'] === 'approved'): ?>
                   <span class="status-badge approved"><i class="fas fa-check"></i> Одобрена</span>
                 <?php elseif ($request['status'] === 'rejected'): ?>
                   <span class="status-badge rejected"><i class="fas fa-times"></i> Отклонена</span>
                 <?php endif; ?>
               </td>
               <td>
                 <?php if ($request['approved_order_id']): ?>
                   <a href="order_details?id=<?php echo $request['approved_order_id']; ?>" class="btn btn-sm btn-primary">
                     #<?php echo $request['approved_order_id']; ?>
                   </a>
                 <?php else: ?>
                   —
                 <?php endif; ?>
               </td>
             </tr>
           <?php endforeach; ?>
         <?php endif; ?>
       </tbody>
    </table>
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
 </script>



 </body>
</html>

