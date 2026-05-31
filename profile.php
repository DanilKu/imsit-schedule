<?php
// Принудительно отключаем кеширование
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';
require_once 'includes/ScheduleManager.php';

date_default_timezone_set('Europe/Moscow');

// инфа о пользователе
$currentUser = null;

// Проверяем авторизацию
if (isset($_SESSION['user_id'])) {
    try {
        require_once 'config/auth.php';
        $currentUser = getCurrentUser();
    } catch (Exception $e) {
        $currentUser = null;
    }
}

// Загружаем все группы и преподавателей из schedule_all
$availableGroups = [];
$availableTeachers = [];
try {
    $stmt = $pdo->query("SELECT DISTINCT group_name FROM schedule_all ORDER BY group_name");
    $availableGroups = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $stmt = $pdo->query("SELECT DISTINCT teacher_name FROM schedule_all WHERE teacher_name IS NOT NULL AND teacher_name != '' ORDER BY teacher_name");
    $availableTeachers = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (Exception $e) {
    // игнорируем, список останется пустым
}

// Определяем выбранную группу или преподавателя
$userGroup = null;
$selectedTeacher = null;
$viewMode = 'group';

// 1. проверка get параметра группы
if (isset($_GET['group']) && in_array($_GET['group'], $availableGroups)) {
    $userGroup = $_GET['group'];
    $viewMode = 'group';
    setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
    setcookie('view_mode', 'group', time() + (30 * 24 * 60 * 60), '/');
    setcookie('selected_teacher', '', time() - 3600, '/');
    $_SESSION['selected_group'] = $userGroup;
}
// 2. проверка get параметра преподавателя
elseif (isset($_GET['teacher']) && in_array($_GET['teacher'], $availableTeachers)) {
    $selectedTeacher = $_GET['teacher'];
    $viewMode = 'teacher';
    setcookie('selected_teacher', $selectedTeacher, time() + (30 * 24 * 60 * 60), '/');
    setcookie('view_mode', 'teacher', time() + (30 * 24 * 60 * 60), '/');
    setcookie('selected_group', '', time() - 3600, '/');
    $_SESSION['selected_teacher'] = $selectedTeacher;
}
// 3. проверка куки группы
elseif (isset($_COOKIE['selected_group']) && in_array($_COOKIE['selected_group'], $availableGroups)) {
    $userGroup = $_COOKIE['selected_group'];
    $viewMode = 'group';
    $_SESSION['selected_group'] = $userGroup;
}
// 4. проверка куки преподавателя
elseif (isset($_COOKIE['selected_teacher']) && in_array($_COOKIE['selected_teacher'], $availableTeachers)) {
    $selectedTeacher = $_COOKIE['selected_teacher'];
    $viewMode = 'teacher';
    $_SESSION['selected_teacher'] = $selectedTeacher;
}
// 5. проверка группы авторизованного пользователя
elseif ($currentUser && isset($currentUser['group']) && in_array($currentUser['group'], $availableGroups)) {
    $userGroup = $currentUser['group'];
    $viewMode = 'group';
    setcookie('selected_group', $userGroup, time() + (30 * 24 * 60 * 60), '/');
    $_SESSION['selected_group'] = $userGroup;
}

// Получаем статистику просмотров
$weekStartDate = (new DateTimeImmutable('monday this week', new DateTimeZone('Europe/Moscow')))->format('Y-m-d');
$currentViews = 0;
$currentRank = null;
$totalViews = 0;

// Создаем таблицу если её нет
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS group_activity (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(255) NOT NULL,
        week_start DATE NOT NULL,
        views INT NOT NULL DEFAULT 0,
        UNIQUE KEY uniq_group_week (group_name, week_start)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {
    // игнорируем
}

if ($viewMode === 'group' && $userGroup) {
    // Получаем просмотры текущей группы за текущую неделю
    try {
        $stmt = $pdo->prepare("SELECT views FROM group_activity WHERE group_name = :group_name AND week_start = :week_start");
        $stmt->execute([
            'group_name' => $userGroup,
            'week_start' => $weekStartDate
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentViews = $result ? (int)$result['views'] : 0;
    } catch (Exception $e) {
        $currentViews = 0;
    }

    // Получаем общее количество просмотров за все недели
    try {
        $stmt = $pdo->prepare("SELECT SUM(views) as total FROM group_activity WHERE group_name = :group_name");
        $stmt->execute(['group_name' => $userGroup]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalViews = $result ? (int)$result['total'] : 0;
    } catch (Exception $e) {
        $totalViews = 0;
    }

    // Получаем позицию в топе за текущую неделю
    try {
        $stmt = $pdo->prepare("SELECT group_name, views, 
            (SELECT COUNT(*) + 1 FROM group_activity g2 
             WHERE g2.week_start = :week_start AND g2.views > g1.views) as rank
            FROM group_activity g1 
            WHERE group_name = :group_name AND week_start = :week_start");
        $stmt->execute([
            'group_name' => $userGroup,
            'week_start' => $weekStartDate
        ]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentRank = $result ? (int)$result['rank'] : null;
    } catch (Exception $e) {
        $currentRank = null;
    }
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>imsitID - Профиль</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin="">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/main.css?v=<?php echo time(); ?>">
    <script src="https://telegram.org/js/telegram-web-app.js"></script>
    <style>
      .g-class {
        background: linear-gradient(180deg, #0B1220 0%, #353535 100%) !important;
        background-repeat: no-repeat !important;
        background-attachment: fixed !important;
        background-size: 100% 200vh !important;
        background-position: top center !important;
        margin: 0 !important;
        min-height: 100vh !important;
        height: 100% !important;
        font-family: 'Montserrat', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', 'Apple Color Emoji', 'Segoe UI Emoji';
      }
      
      @media (max-width: 768px) {
        .g-class {
          background-attachment: scroll !important;
          background-size: 100% 200vh !important;
          background-position: top center !important;
          min-height: -webkit-fill-available !important;
        }
      }

      .FullScreen {
        display: flex;
        min-height: 100vh;
        min-height: -webkit-fill-available;
        width: 100%;
        justify-content: center;
      }

      .On-header {
        width: 100%;
        max-width: 420px;
        padding-left: 1rem;
        padding-right: 1rem;
        padding-bottom: calc(80px + env(safe-area-inset-bottom));
      }

      .Header {
        margin-left: -1rem;
        margin-right: -1rem;
      }

      .Header-container {
        position: relative;
        width: 100%;
        overflow: visible;
        border-radius: 25px;
        background: none;
        min-height: 60px;
      }

      .Def-Header {
        display: flex;
        height: 60px;
        align-items: center;
        justify-content: space-between;
        padding-left: 1rem;
        padding-right: 1.5rem;
      }

      .Def-Header-Container {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        user-select: none;
      }

      .Def-Header-Text {
        background-clip: text;
        font-size: 15px;
        font-weight: 600;
        line-height: 18px;
        color: transparent;
        background-image: linear-gradient(90deg, #FFFFFF 0%, #999999 100%);
      }

      /* Profile Content */
      .profile-container {
        margin-top: 0.5rem;
        padding-bottom: 1rem;
      }

      .profile-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1rem;
      }

      .profile-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 1rem;
        margin-bottom: 1.5rem;
      }

      .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        border: 3px solid rgba(255, 255, 255, 0.2);
        position: relative;
      }

      .profile-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
      }

      .profile-avatar .avatar-fallback {
        font-size: 48px;
        color: rgba(255, 255, 255, 0.6);
      }

      .profile-name {
        font-size: 20px;
        font-weight: 600;
        color: #ffffff;
        text-align: center;
      }

      .profile-info {
        display: flex;
        flex-direction: column;
        gap: 1rem;
      }

      .info-item {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
      }

      .info-item-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
      }

      .info-item-icon.group {
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
      }

      .info-item-icon.teacher {
        background: rgba(168, 85, 247, 0.2);
        color: #d8b4fe;
      }

      .info-item-content {
        flex: 1;
        min-width: 0;
      }

      .info-item-label {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 0.25rem;
      }

      .info-item-value {
        font-size: 15px;
        font-weight: 600;
        color: #ffffff;
      }

      .info-item-action {
        padding: 0.5rem 1rem;
        background: rgba(59, 130, 246, 0.2);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 8px;
        color: #60a5fa;
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.2s ease;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
      }

      .info-item-action:hover {
        background: rgba(59, 130, 246, 0.3);
        border-color: rgba(59, 130, 246, 0.5);
        color: #93c5fd;
      }

      .info-item-empty {
        text-align: center;
        color: rgba(255, 255, 255, 0.5);
        font-size: 14px;
        padding: 0.5rem 0;
      }

      .stats-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 1rem;
        margin-top: 1rem;
      }

      .stats-title {
        font-size: 13px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 0.75rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .stats-title i {
        color: rgba(255, 255, 255, 0.5);
      }

      .stats-grid {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
      }

      .stat-item {
        background: rgba(255, 255, 255, 0.03);
        border: 1px solid rgba(255, 255, 255, 0.08);
        border-radius: 10px;
        padding: 0.75rem;
        text-align: center;
      }

      .stat-value {
        font-size: 20px;
        font-weight: 700;
        color: #ffffff;
        margin-bottom: 0.25rem;
      }

      .stat-label {
        font-size: 11px;
        color: rgba(255, 255, 255, 0.6);
      }

      .stat-rank {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        padding: 0.25rem 0.5rem;
        background: rgba(59, 130, 246, 0.2);
        border: 1px solid rgba(59, 130, 246, 0.3);
        border-radius: 8px;
        font-size: 12px;
        color: #60a5fa;
        font-weight: 600;
        margin-top: 0.25rem;
      }

      /* Modal styles from id2.php */
      .modal-overlay {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 9998;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
      }

      .modal-card {
        background: rgba(15, 23, 42, 0.95);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 16px;
        padding: 1.5rem;
        max-width: 24rem;
        width: 100%;
        max-height: 90vh;
        overflow-y: auto;
        position: relative;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
      }

      .group-btn {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 0.75rem;
        width: 100%;
        text-align: left;
        transition: all 0.2s ease;
      }

      .group-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.2);
      }

      .group-icon {
        width: 2rem;
        height: 2rem;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.9rem;
      }

      .btn {
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        background: rgba(255, 255, 255, 0.05);
        padding: 0.6rem 1rem;
        border-radius: 12px;
        text-decoration: none;
        font-weight: 600;
        font-size: 0.9rem;
        transition: all 0.2s ease;
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        cursor: pointer;
      }

      .btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.2);
      }

      .h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: #ffffff;
        margin: 0;
      }

      .small {
        font-size: 0.8rem;
        color: rgba(255, 255, 255, 0.6);
      }

      .mb-4 {
        margin-bottom: 1rem;
      }

      .space-y-3 > * + * {
        margin-top: 0.75rem;
      }

      /* Bottom Navigation */
      .bottom-navigation {
        position: fixed;
        bottom: 0;
        left: 50%;
        transform: translateX(-50%);
        width: 100%;
        max-width: 420px;
        background: #1a1a1a;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 25px;
        display: flex;
        justify-content: space-around;
        align-items: center;
        padding: 0.5rem 0;
        padding-bottom: calc(0.5rem + env(safe-area-inset-bottom));
        z-index: 1000;
        box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.3);
      }

      .nav-item {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.5rem 0.75rem;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.6);
        transition: all 0.2s ease;
        position: relative;
        flex: 1;
        max-width: 25%;
        cursor: pointer;
        background: transparent;
        border: none;
      }

      .nav-item:hover {
        color: rgba(255, 255, 255, 0.8);
      }

      .nav-item.active {
        color: #3b82f6;
      }

      .nav-item.active .nav-icon {
        color: #3b82f6;
      }

      .nav-icon {
        font-size: 20px;
        color: inherit;
        transition: color 0.2s ease;
      }

      .nav-label {
        font-size: 11px;
        font-weight: 500;
        color: inherit;
        text-align: center;
        line-height: 1.2;
      }

      .nav-avatar {
        width: 24px;
        height: 24px;
        border-radius: 50%;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        position: relative;
        flex-shrink: 0;
      }

      .nav-avatar img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        display: block;
      }

      .nav-icon-fallback {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.6);
        display: block;
      }

      .nav-item.active .nav-avatar {
        background: rgba(59, 130, 246, 0.2);
        border: 2px solid #3b82f6;
      }

      .nav-item.active .nav-icon-fallback {
        color: #3b82f6;
      }

      @media (max-width: 480px) {
        .profile-avatar {
          width: 80px;
          height: 80px;
        }

        .profile-name {
          font-size: 18px;
        }

        .nav-label {
          font-size: 10px;
        }

        .nav-icon {
          font-size: 18px;
        }

        .nav-item {
          padding: 0.4rem 0.5rem;
        }
      }
    </style>
</head>
<body class="g-class">
    <div class="FullScreen" style="box-sizing: border-box;">
        <div class="On-header">
            <!-- Header -->
            <div class="Header">
                <div class="Header-container">
                    <div class="Def-Header">
                        <div class="Def-Header-Container">
                            <div class="Def-Header-Text">
                                imsitID - Профиль
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Profile Content -->
            <div class="profile-container">
                <div class="profile-card">
                    <div class="profile-header">
                        <div class="profile-avatar" id="profileAvatar">
                            <i class="fas fa-user avatar-fallback"></i>
                        </div>
                        <div class="profile-name" id="profileName">Загрузка...</div>
                    </div>

                    <div class="profile-info">
                        <?php if ($viewMode === 'group' && $userGroup): ?>
                            <div class="info-item">
                                <div class="info-item-icon group">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="info-item-content">
                                    <div class="info-item-label">Группа</div>
                                    <div class="info-item-value"><?php echo htmlspecialchars($userGroup); ?></div>
                                </div>
                                <a href="javascript:void(0)" class="info-item-action" onclick="showGroupSelectionModal()">
                                    <i class="fas fa-edit"></i>
                                    <span>Сменить</span>
                                </a>
                            </div>
                        <?php elseif ($viewMode === 'teacher' && $selectedTeacher): ?>
                            <div class="info-item">
                                <div class="info-item-icon teacher">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="info-item-content">
                                    <div class="info-item-label">Преподаватель</div>
                                    <div class="info-item-value"><?php echo htmlspecialchars($selectedTeacher); ?></div>
                                </div>
                                <a href="javascript:void(0)" class="info-item-action" onclick="showTeacherSelectionModal()">
                                    <i class="fas fa-edit"></i>
                                    <span>Сменить</span>
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="info-item">
                                <div class="info-item-icon group">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="info-item-content">
                                    <div class="info-item-label">Группа или преподаватель</div>
                                    <div class="info-item-empty">Не выбрано</div>
                                </div>
                                <a href="javascript:void(0)" class="info-item-action" onclick="showGroupSelectionModal()">
                                    <i class="fas fa-plus"></i>
                                    <span>Выбрать</span>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($viewMode === 'group' && $userGroup && ($currentViews > 0 || $totalViews > 0)): ?>
                        <div class="stats-card">
                            <div class="stats-title">
                                <i class="fas fa-chart-line"></i>
                                <span>Статистика просмотров</span>
                            </div>
                            <div class="stats-grid">
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($currentViews, 0, ',', ' '); ?></div>
                                    <div class="stat-label">За эту неделю</div>
                                    <?php if ($currentRank): ?>
                                        <div class="stat-rank">
                                            <i class="fas fa-trophy"></i>
                                            <span>Место #<?php echo $currentRank; ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-value"><?php echo number_format($totalViews, 0, ',', ' '); ?></div>
                                    <div class="stat-label">Всего просмотров</div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal для выбора группы -->
    <div id="groupSelectionModal" style="display: none;" class="modal-overlay" onclick="if(event.target === this) closeModal('groupSelectionModal')">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <!-- Крестик для закрытия -->
            <button onclick="closeModal('groupSelectionModal')" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease; z-index: 10;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times" style="font-size: 12px;"></i></button>
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(99,102,241,0.7), rgba(67,56,202,0.7));color:#fff;"><i class="fas fa-users" style="font-size: 24px;"></i></div>
                <h2 class="h2" style="margin:0 0 0.5rem;"><i class="fas fa-users" style="margin-right: 8px;"></i>Выберите группу</h2>
                <p class="small">Выбор сохраняется в течение 30 дней</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem; display:flex; justify-content: space-between; gap:0.5rem; align-items:center;">
                <input type="text" id="groupSearch" placeholder="🔍 Поиск группы..." style="flex:1; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.5rem; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem;" onkeyup="filterGroups()">
                <button type="button" class="btn" style="padding:0.6rem 0.8rem; font-size:0.8rem; white-space:nowrap;" onclick="switchToTeacherFromGroup()"><i class="fas fa-chalkboard-teacher" style="margin-right: 4px;"></i>Препод</button>
            </div>
            <div id="groupsList" class="space-y-3" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableGroups)): ?>
                    <?php foreach ($availableGroups as $group): ?>
                        <button onclick="selectGroup('<?php echo htmlspecialchars($group); ?>')" class="group-btn group-item" data-name="<?php echo htmlspecialchars($group); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(59,130,246,0.2);"><i class="fas fa-users" style="color:#93c5fd;font-size:16px;"></i></div>
                                <div style="min-width:0;">
                                    <div style="color:#fff;font-weight:600;" class="group-name"><?php echo htmlspecialchars($group); ?></div>
                                </div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small" style="color:rgba(255,255,255,0.6); text-align:center;">Группы не найдены</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal для выбора преподавателя -->
    <div id="teacherSelectionModal" style="display: none;" class="modal-overlay" onclick="if(event.target === this) closeModal('teacherSelectionModal')">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <!-- Крестик для закрытия -->
            <button onclick="closeModal('teacherSelectionModal')" style="position: absolute; top: 1rem; right: 1rem; background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; width: 2rem; height: 2rem; display: flex; align-items: center; justify-content: center; color: #fff; cursor: pointer; transition: all 0.2s ease; z-index: 10;" onmouseover="this.style.background='rgba(255,255,255,0.2)'" onmouseout="this.style.background='rgba(255,255,255,0.1)'"><i class="fas fa-times" style="font-size: 12px;"></i></button>
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(168,85,247,0.7), rgba(59,130,246,0.7));color:#fff;"><i class="fas fa-chalkboard-teacher" style="font-size: 24px;"></i></div>
                <h2 class="h2" style="margin:0 0 0.5rem;"><i class="fas fa-chalkboard-teacher" style="margin-right: 8px;"></i>Выберите преподавателя</h2>
                <p class="small">Просмотр расписания преподавателя</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem; display:flex; justify-content: space-between; gap:0.5rem; align-items:center;">
                <input type="text" id="teacherSearch" placeholder="🔍 Поиск преподавателя..." style="flex:1; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 0.5rem; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem;" onkeyup="filterTeachers()">
                <button type="button" class="btn" style="padding:0.6rem 0.8rem; font-size:0.8rem; white-space:nowrap;" onclick="switchToGroupFromTeacher()"><i class="fas fa-users" style="margin-right: 4px;"></i>Группы</button>
            </div>
            <div class="space-y-3" id="teachersList" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableTeachers)): ?>
                    <?php foreach ($availableTeachers as $teacher): ?>
                        <button onclick="selectTeacher('<?php echo htmlspecialchars($teacher); ?>')" class="group-btn teacher-item" data-name="<?php echo htmlspecialchars($teacher); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(168,85,247,0.2);"><i class="fas fa-chalkboard-teacher" style="color:#d8b4fe;font-size:16px;"></i></div>
                                <div style="min-width:0;">
                                    <div style="color:#fff;font-weight:600;" class="teacher-name"><?php echo htmlspecialchars($teacher); ?></div>
                                </div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="small" style="color:rgba(255,255,255,0.6); text-align:center;">Преподаватели не найдены</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Bottom Navigation -->
    <div class="bottom-navigation">
        <a href="main_test.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'main_test.php' ? ' active' : ''; ?>">
            <i class="fas fa-home nav-icon"></i>
            <span class="nav-label">Главная</span>
        </a>
        <a href="id.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'id.php' || basename($_SERVER['PHP_SELF']) === 'id2.php' ? ' active' : ''; ?>">
            <i class="fas fa-calendar-alt nav-icon"></i>
            <span class="nav-label">Расписание</span>
        </a>
        <a href="imsitmaps.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'imsitmaps.php' ? ' active' : ''; ?>">
            <i class="fas fa-map nav-icon"></i>
            <span class="nav-label">Карта</span>
        </a>
        <a href="profile.php" class="nav-item<?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? ' active' : ''; ?>">
            <div class="nav-avatar" id="navAvatar">
                <i class="fas fa-user nav-icon-fallback"></i>
            </div>
            <span class="nav-label">Профиль</span>
        </a>
    </div>

    <script>
        // Загрузка данных пользователя из Telegram
        function loadTelegramUserData() {
            const profileAvatar = document.getElementById('profileAvatar');
            const profileName = document.getElementById('profileName');
            const navAvatar = document.getElementById('navAvatar');

            let userName = 'Пользователь';
            let photoUrl = null;

            // Получаем данные из Telegram WebApp
            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                const webApp = window.Telegram.WebApp;
                const initData = webApp.initDataUnsafe;
                
                if (initData && initData.user) {
                    const user = initData.user;
                    
                    // Имя пользователя
                    if (user.first_name) {
                        userName = user.first_name;
                        if (user.last_name) {
                            userName += ' ' + user.last_name;
                        }
                    } else if (user.username) {
                        userName = '@' + user.username;
                    }

                    // Фото пользователя
                    if (user.photo_url) {
                        photoUrl = user.photo_url;
                    } else if (user.photo) {
                        photoUrl = user.photo;
                    }
                }
            }

            // Обновляем имя
            if (profileName) {
                profileName.textContent = userName;
            }

            // Загружаем аватар
            if (photoUrl) {
                // Для профиля
                if (profileAvatar) {
                    const img = document.createElement('img');
                    img.src = photoUrl;
                    img.alt = userName;
                    img.onload = function() {
                        const fallback = profileAvatar.querySelector('.avatar-fallback');
                        if (fallback) {
                            fallback.style.display = 'none';
                        }
                    };
                    img.onerror = function() {
                        this.style.display = 'none';
                    };
                    profileAvatar.appendChild(img);
                }

                // Для навигации
                if (navAvatar && !navAvatar.querySelector('img')) {
                    const img = document.createElement('img');
                    img.src = photoUrl;
                    img.alt = userName;
                    img.onload = function() {
                        const fallback = navAvatar.querySelector('.nav-icon-fallback');
                        if (fallback) {
                            fallback.style.display = 'none';
                        }
                    };
                    img.onerror = function() {
                        this.style.display = 'none';
                    };
                    navAvatar.appendChild(img);
                }

                // Сохраняем в localStorage
                try {
                    localStorage.setItem('telegram_avatar_url', photoUrl);
                    localStorage.setItem('telegram_user_name', userName);
                } catch (e) {}
            } else {
                // Пытаемся загрузить из localStorage
                try {
                    const savedAvatar = localStorage.getItem('telegram_avatar_url');
                    const savedName = localStorage.getItem('telegram_user_name');
                    
                    if (savedName && profileName) {
                        profileName.textContent = savedName;
                    }

                    if (savedAvatar) {
                        if (profileAvatar && !profileAvatar.querySelector('img')) {
                            const img = document.createElement('img');
                            img.src = savedAvatar;
                            img.alt = savedName || 'Profile';
                            img.onload = function() {
                                const fallback = profileAvatar.querySelector('.avatar-fallback');
                                if (fallback) {
                                    fallback.style.display = 'none';
                                }
                            };
                            profileAvatar.appendChild(img);
                        }

                        if (navAvatar && !navAvatar.querySelector('img')) {
                            const img = document.createElement('img');
                            img.src = savedAvatar;
                            img.alt = savedName || 'Profile';
                            img.onload = function() {
                                const fallback = navAvatar.querySelector('.nav-icon-fallback');
                                if (fallback) {
                                    fallback.style.display = 'none';
                                }
                            };
                            navAvatar.appendChild(img);
                        }
                    }
                } catch (e) {}
            }

            // Пытаемся получить через API
            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp && !photoUrl) {
                fetch('api/check_telegram_auth.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        initDataUnsafe: window.Telegram.WebApp.initDataUnsafe
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.user) {
                        if (data.user.photo_url && profileAvatar && !profileAvatar.querySelector('img')) {
                            const img = document.createElement('img');
                            img.src = data.user.photo_url;
                            img.alt = data.user.first_name || 'Profile';
                            img.onload = function() {
                                const fallback = profileAvatar.querySelector('.avatar-fallback');
                                if (fallback) {
                                    fallback.style.display = 'none';
                                }
                            };
                            profileAvatar.appendChild(img);
                        }

                        if (data.user.photo_url && navAvatar && !navAvatar.querySelector('img')) {
                            const img = document.createElement('img');
                            img.src = data.user.photo_url;
                            img.alt = data.user.first_name || 'Profile';
                            img.onload = function() {
                                const fallback = navAvatar.querySelector('.nav-icon-fallback');
                                if (fallback) {
                                    fallback.style.display = 'none';
                                }
                            };
                            navAvatar.appendChild(img);
                        }

                        if (data.user.first_name && profileName) {
                            let name = data.user.first_name;
                            if (data.user.last_name) {
                                name += ' ' + data.user.last_name;
                            }
                            profileName.textContent = name;
                        }

                        try {
                            if (data.user.photo_url) {
                                localStorage.setItem('telegram_avatar_url', data.user.photo_url);
                            }
                            if (data.user.first_name) {
                                localStorage.setItem('telegram_user_name', data.user.first_name);
                            }
                        } catch (e) {}
                    }
                })
                .catch(error => {
                    console.log('API error:', error);
                });
            }
        }

        // Функции для работы с модалками
        function showGroupSelectionModal() {
            document.getElementById('groupSelectionModal').style.display = 'flex';
        }

        function showTeacherSelectionModal() {
            document.getElementById('teacherSelectionModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function switchToTeacherFromGroup() {
            closeModal('groupSelectionModal');
            setTimeout(showTeacherSelectionModal, 100);
        }

        function switchToGroupFromTeacher() {
            closeModal('teacherSelectionModal');
            setTimeout(showGroupSelectionModal, 100);
        }

        function selectGroup(groupName) {
            window.location.href = '?group=' + encodeURIComponent(groupName);
        }

        function selectTeacher(teacherName) {
            window.location.href = '?teacher=' + encodeURIComponent(teacherName);
        }

        function filterGroups() {
            const q = (document.getElementById('groupSearch').value || '').toLowerCase();
            document.querySelectorAll('#groupsList .group-item').forEach(it => {
                const name = (it.getAttribute('data-name') || '').toLowerCase();
                it.style.display = name.includes(q) ? '' : 'none';
            });
        }

        function filterTeachers() {
            const q = (document.getElementById('teacherSearch').value || '').toLowerCase();
            document.querySelectorAll('#teachersList .teacher-item').forEach(it => {
                const name = (it.getAttribute('data-name') || '').toLowerCase();
                it.style.display = name.includes(q) ? '' : 'none';
            });
        }

        // Инициализация
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                window.Telegram.WebApp.ready();
                window.Telegram.WebApp.expand();
            }
            loadTelegramUserData();
            setTimeout(loadTelegramUserData, 500);
            setTimeout(loadTelegramUserData, 1500);
        });
    </script>
</body>
</html>
