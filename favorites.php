<?php
// Принудительно отключаем кеширование
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'config/database.php';

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

$favoritesCookieName = 'imsit_favorites';
$favorites = [];
if (isset($_COOKIE[$favoritesCookieName])) {
    $decoded = json_decode($_COOKIE[$favoritesCookieName], true);
    if (is_array($decoded)) {
        $favorites = $decoded;
    }
}

// Нормализуем структуру и фильтруем только существующие в БД
$favorites = array_filter(array_map(function ($item) use ($availableGroups, $availableTeachers) {
    $name = $item['name'] ?? '';
    $type = ($item['type'] ?? 'group') === 'teacher' ? 'teacher' : 'group';
    
    // Проверяем, существует ли в БД
    if ($type === 'group' && in_array($name, $availableGroups)) {
        return ['name' => $name, 'type' => $type];
    } elseif ($type === 'teacher' && in_array($name, $availableTeachers)) {
        return ['name' => $name, 'type' => $type];
    }
    return null; // Удаляем несуществующие
}, $favorites), function($item) {
    return $item !== null;
});

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $name = trim($_POST['favorite_name'] ?? '');
        $type = ($_POST['favorite_type'] ?? 'group') === 'teacher' ? 'teacher' : 'group';
        
        if ($name !== '') {
            // Проверяем существование в БД
            $existsInDB = false;
            if ($type === 'group' && in_array($name, $availableGroups)) {
                $existsInDB = true;
            } elseif ($type === 'teacher' && in_array($name, $availableTeachers)) {
                $existsInDB = true;
            }
            
            if (!$existsInDB) {
                $message = $type === 'group' ? 'Группа не найдена в базе данных' : 'Преподаватель не найден в базе данных';
            } else {
                // Проверяем дубликаты
            $alreadyExists = false;
            foreach ($favorites as $fav) {
                if (mb_strtolower($fav['name']) === mb_strtolower($name) && $fav['type'] === $type) {
                    $alreadyExists = true;
                    break;
                }
            }
            if (!$alreadyExists) {
                $favorites[] = ['name' => $name, 'type' => $type];
                $message = 'Добавлено в избранное';
            } else {
                $message = 'Такой элемент уже в избранном';
                }
            }
        } else {
            $message = 'Выберите группу или преподавателя';
        }
    } elseif ($action === 'remove') {
        $name = $_POST['name'] ?? '';
        $type = $_POST['type'] ?? 'group';
        $favorites = array_values(array_filter($favorites, function ($fav) use ($name, $type) {
            return !($fav['name'] === $name && $fav['type'] === $type);
        }));
        $message = 'Удалено из избранного';
    }

    setcookie($favoritesCookieName, json_encode($favorites, JSON_UNESCAPED_UNICODE), time() + 60 * 60 * 24 * 365, '/');
    header('Location: favorites.php' . ($message ? ('?msg=' . urlencode($message)) : ''));
    exit;
}

if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Избранное — imsitID</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
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

      .content-area {
        margin-top: 0.5rem;
        padding-bottom: 1rem;
      }

      .favorites-container {
        padding: 1rem;
      }

      .favorites-card {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
        margin-bottom: 1rem;
      }

      .favorites-title {
        font-size: 20px;
            font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.5rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .favorites-description {
            font-size: 14px;
        color: rgba(255, 255, 255, 0.6);
        margin-bottom: 1.5rem;
        }

        .message {
        margin-bottom: 1rem;
        padding: 0.75rem 1rem;
            border-radius: 12px;
        background: rgba(16, 185, 129, 0.15);
        border: 1px solid rgba(16, 185, 129, 0.3);
        color: #10b981;
            font-size: 13px;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .message.error {
        background: rgba(239, 68, 68, 0.15);
        border-color: rgba(239, 68, 68, 0.3);
        color: #fca5a5;
      }

        .favorites-list {
            display: flex;
            flex-direction: column;
        gap: 0.75rem;
        margin-bottom: 1.5rem;
        }

        .favorite-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        transition: all 0.2s ease;
      }

      .favorite-item:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.15);
      }

      .favorite-item-content {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex: 1;
        min-width: 0;
      }

      .favorite-item-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
      }

      .favorite-item-icon.group {
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
      }

      .favorite-item-icon.teacher {
        background: rgba(168, 85, 247, 0.2);
        color: #d8b4fe;
      }

      .favorite-item-info {
        flex: 1;
        min-width: 0;
      }

      .favorite-item-name {
            font-size: 15px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.25rem;
        word-break: break-word;
        }

      .favorite-item-type {
            font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
      }

      .favorite-item-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        }

      .favorite-item-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
        padding: 0.5rem;
        border-radius: 8px;
        background: rgba(59, 130, 246, 0.2);
        border: 1px solid rgba(59, 130, 246, 0.3);
        color: #60a5fa;
        text-decoration: none;
        transition: all 0.2s ease;
            font-size: 14px;
      }

      .favorite-item-link:hover {
        background: rgba(59, 130, 246, 0.3);
        border-color: rgba(59, 130, 246, 0.4);
        color: #93c5fd;
        }

      .favorite-item-remove {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        padding: 0.5rem;
        border-radius: 8px;
        background: rgba(239, 68, 68, 0.15);
        border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        cursor: pointer;
        transition: all 0.2s ease;
        font-size: 14px;
        border: none;
      }

      .favorite-item-remove:hover {
        background: rgba(239, 68, 68, 0.25);
        color: #f87171;
      }

      .empty-state {
        text-align: center;
        padding: 2rem 1rem;
        color: rgba(255, 255, 255, 0.6);
      }

      .empty-state-icon {
        font-size: 48px;
        color: rgba(255, 255, 255, 0.3);
        margin-bottom: 1rem;
      }

      .empty-state-text {
        font-size: 15px;
        margin-bottom: 1.5rem;
      }

      .add-favorite-section {
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 20px;
        padding: 1.5rem;
      }

      .add-favorite-title {
        font-size: 16px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
      }

      .add-favorite-buttons {
            display: flex;
            flex-direction: column;
        gap: 0.75rem;
      }

      .add-favorite-btn {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1rem;
        border-radius: 12px;
        background: rgba(255, 255, 255, 0.05);
        border: 1px solid rgba(255, 255, 255, 0.1);
        color: #ffffff;
        text-decoration: none;
        transition: all 0.2s ease;
        cursor: pointer;
      }

      .add-favorite-btn:hover {
        background: rgba(255, 255, 255, 0.08);
        border-color: rgba(255, 255, 255, 0.15);
      }

      .add-favorite-btn-icon {
        width: 36px;
        height: 36px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        flex-shrink: 0;
      }

      .add-favorite-btn-icon.group {
        background: rgba(59, 130, 246, 0.2);
        color: #93c5fd;
      }

      .add-favorite-btn-icon.teacher {
        background: rgba(168, 85, 247, 0.2);
        color: #d8b4fe;
      }

      .add-favorite-btn-content {
        flex: 1;
        text-align: left;
      }

      .add-favorite-btn-label {
        font-size: 15px;
        font-weight: 600;
        color: #ffffff;
        margin-bottom: 0.25rem;
      }

      .add-favorite-btn-desc {
        font-size: 12px;
        color: rgba(255, 255, 255, 0.6);
        }

      /* Modal styles */
      #groupSelectionModal, #teacherSelectionModal {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.7);
        backdrop-filter: blur(4px);
        z-index: 9998;
        display: none;
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

      .modal-close-btn {
        position: absolute;
        top: 1rem;
        right: 1rem;
        background: rgba(255,255,255,0.1);
        border: 1px solid rgba(255,255,255,0.2);
            border-radius: 12px;
        width: 2rem;
        height: 2rem;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #fff;
        cursor: pointer;
        transition: all 0.2s ease;
        z-index: 10;
      }

      .modal-close-btn:hover {
        background: rgba(255,255,255,0.2);
      }

      .group-btn, .teacher-btn {
        padding: 0.75rem !important;
        border-radius: 12px !important;
        background: rgba(255, 255, 255, 0.05) !important;
        border: 1px solid rgba(255, 255, 255, 0.1) !important;
        color: #ffffff !important;
        width: 100%;
        text-align: left;
        cursor: pointer;
        transition: all 0.2s ease;
      }

      .group-btn:hover, .teacher-btn:hover {
        background: rgba(255, 255, 255, 0.08) !important;
        border-color: rgba(255, 255, 255, 0.2) !important;
      }

      .group-icon {
        width: 2rem !important;
        height: 2rem !important;
        font-size: 0.9rem !important;
        border-radius: 10px !important;
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
                                Избранное
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Content Area -->
            <div class="content-area">
                <div class="favorites-container">
                    <div class="favorites-card">
                        <div class="favorites-title">
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                            Избранное
                        </div>
                        <div class="favorites-description">
                            Храните любимые группы и преподавателей, чтобы быстро переходить к расписанию.
                        </div>

        <?php if ($message): ?>
                            <div class="message<?php echo (strpos($message, 'не найден') !== false || strpos($message, 'не найдена') !== false) ? ' error' : ''; ?>">
                                <i class="fas <?php echo (strpos($message, 'не найден') !== false || strpos($message, 'не найдена') !== false) ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                                <?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?>
                            </div>
        <?php endif; ?>

        <?php if (empty($favorites)): ?>
            <div class="empty-state">
                                <div class="empty-state-icon">
                                    <i class="fas fa-star"></i>
                                </div>
                                <div class="empty-state-text">
                                    Пока что список пуст.<br>Добавьте свою первую группу или преподавателя.
                                </div>
            </div>
        <?php else: ?>
            <div class="favorites-list">
                <?php foreach ($favorites as $fav): ?>
                    <div class="favorite-item">
                                        <div class="favorite-item-content">
                                            <div class="favorite-item-icon <?php echo $fav['type']; ?>">
                                                <i class="fas <?php echo $fav['type'] === 'teacher' ? 'fa-chalkboard-teacher' : 'fa-users'; ?>"></i>
                                            </div>
                                            <div class="favorite-item-info">
                                                <div class="favorite-item-name">
                                                    <?php echo htmlspecialchars($fav['name'], ENT_QUOTES, 'UTF-8'); ?>
                                                </div>
                                                <div class="favorite-item-type">
                                                    <?php echo $fav['type'] === 'teacher' ? 'Преподаватель' : 'Группа'; ?>
                                                </div>
                                            </div>
                        </div>
                                        <div class="favorite-item-actions">
                                            <a href="id2.php?<?php echo $fav['type'] === 'teacher' ? 'teacher' : 'group'; ?>=<?php echo urlencode($fav['name']); ?>" class="favorite-item-link" title="Открыть расписание">
                                                <i class="fas fa-calendar-alt"></i>
                                            </a>
                                            <form method="post" style="display: inline;" onsubmit="return confirm('Удалить из избранного?');">
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="name" value="<?php echo htmlspecialchars($fav['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            <input type="hidden" name="type" value="<?php echo htmlspecialchars($fav['type'], ENT_QUOTES, 'UTF-8'); ?>">
                                                <button type="submit" class="favorite-item-remove" title="Удалить из избранного">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                        </form>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="add-favorite-section">
                        <div class="add-favorite-title">
                            <i class="fas fa-plus-circle"></i>
                            Добавить в избранное
                        </div>
                        <div class="add-favorite-buttons">
                            <button type="button" class="add-favorite-btn" onclick="showGroupSelectionModal()">
                                <div class="add-favorite-btn-icon group">
                                    <i class="fas fa-users"></i>
                                </div>
                                <div class="add-favorite-btn-content">
                                    <div class="add-favorite-btn-label">Выбрать группу</div>
                                    <div class="add-favorite-btn-desc">Добавить группу из списка</div>
                                </div>
                                <i class="fas fa-chevron-right" style="color: rgba(255, 255, 255, 0.4); font-size: 14px;"></i>
                            </button>
                            <button type="button" class="add-favorite-btn" onclick="showTeacherSelectionModal()">
                                <div class="add-favorite-btn-icon teacher">
                                    <i class="fas fa-chalkboard-teacher"></i>
                                </div>
                                <div class="add-favorite-btn-content">
                                    <div class="add-favorite-btn-label">Выбрать преподавателя</div>
                                    <div class="add-favorite-btn-desc">Добавить преподавателя из списка</div>
                                </div>
                                <i class="fas fa-chevron-right" style="color: rgba(255, 255, 255, 0.4); font-size: 14px;"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal для выбора группы -->
    <div id="groupSelectionModal" onclick="if(event.target === this) closeModal('groupSelectionModal')">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <button class="modal-close-btn" onclick="closeModal('groupSelectionModal')">
                <i class="fas fa-times" style="font-size: 12px;"></i>
            </button>
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(99,102,241,0.7), rgba(67,56,202,0.7));color:#fff;">
                    <i class="fas fa-users" style="font-size: 24px;"></i>
                </div>
                <h2 style="margin:0 0 0.5rem; color: #ffffff; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-users" style="margin-right: 8px;"></i>Выберите группу
                </h2>
                <p style="color: rgba(255, 255, 255, 0.6); font-size: 13px; margin: 0;">Выбор сохраняется в избранном</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem;">
                <input type="text" id="groupSearch" placeholder="🔍 Поиск группы..." style="width: 100%; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem; box-sizing: border-box;" onkeyup="filterGroups()">
            </div>
            <div id="groupsList" class="space-y-3" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableGroups)): ?>
                    <?php foreach ($availableGroups as $group): ?>
                        <button onclick="selectGroup('<?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>')" class="group-btn group-item" data-name="<?php echo htmlspecialchars($group, ENT_QUOTES, 'UTF-8'); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(59,130,246,0.2);">
                                    <i class="fas fa-users" style="color:#93c5fd;font-size:16px;"></i>
                                </div>
                                <div style="min-width:0; flex: 1;">
                                    <div style="color:#fff;font-weight:600;" class="group-name"><?php echo htmlspecialchars($group); ?></div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.6);">Группа</div>
                                </div>
                            </div>
                        </button>
                <?php endforeach; ?>
                <?php else: ?>
                    <div style="color:rgba(255,255,255,0.6); text-align:center; padding: 1rem; font-size: 13px;">Группы не найдены</div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Modal для выбора преподавателя -->
    <div id="teacherSelectionModal" onclick="if(event.target === this) closeModal('teacherSelectionModal')">
        <div class="modal-card" onclick="event.stopPropagation()" style="position: relative;">
            <button class="modal-close-btn" onclick="closeModal('teacherSelectionModal')">
                <i class="fas fa-times" style="font-size: 12px;"></i>
            </button>
            <div style="text-align:center;" class="mb-4">
                <div style="width:4rem;height:4rem;margin:0 auto 1rem;border-radius:9999px;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg, rgba(168,85,247,0.7), rgba(59,130,246,0.7));color:#fff;">
                    <i class="fas fa-chalkboard-teacher" style="font-size: 24px;"></i>
                </div>
                <h2 style="margin:0 0 0.5rem; color: #ffffff; font-size: 18px; font-weight: 600;">
                    <i class="fas fa-chalkboard-teacher" style="margin-right: 8px;"></i>Выберите преподавателя
                </h2>
                <p style="color: rgba(255, 255, 255, 0.6); font-size: 13px; margin: 0;">Выбор сохраняется в избранном</p>
            </div>
            <div style="position: relative; margin-bottom: 0.5rem;">
                <input type="text" id="teacherSearch" placeholder="🔍 Поиск преподавателя..." style="width: 100%; padding: 0.75rem; border: 1px solid rgba(255,255,255,0.2); border-radius: 12px; background: rgba(255,255,255,0.1); color: white; font-size: 0.875rem; box-sizing: border-box;" onkeyup="filterTeachers()">
            </div>
            <div class="space-y-3" id="teachersList" style="max-height: 300px; overflow-y: auto;">
                <?php if (!empty($availableTeachers)): ?>
                    <?php foreach ($availableTeachers as $teacher): ?>
                        <button onclick="selectTeacher('<?php echo htmlspecialchars($teacher, ENT_QUOTES, 'UTF-8'); ?>')" class="teacher-btn teacher-item" data-name="<?php echo htmlspecialchars($teacher, ENT_QUOTES, 'UTF-8'); ?>">
                            <div style="display:flex; align-items:center; gap:0.75rem; width:100%;">
                                <div class="group-icon" style="background: rgba(168,85,247,0.2);">
                                    <i class="fas fa-chalkboard-teacher" style="color:#d8b4fe;font-size:16px;"></i>
                                </div>
                                <div style="min-width:0; flex: 1;">
                                    <div style="color:#fff;font-weight:600;" class="teacher-name"><?php echo htmlspecialchars($teacher); ?></div>
                                    <div style="font-size: 12px; color: rgba(255,255,255,0.6);">Преподаватель</div>
                                </div>
                            </div>
                        </button>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div style="color:rgba(255,255,255,0.6); text-align:center; padding: 1rem; font-size: 13px;">Преподаватели не найдены</div>
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
        function showGroupSelectionModal() {
            document.getElementById('groupSelectionModal').style.display = 'flex';
        }

        function showTeacherSelectionModal() {
            document.getElementById('teacherSelectionModal').style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
            // Очищаем поля поиска
            if (modalId === 'groupSelectionModal') {
                document.getElementById('groupSearch').value = '';
                filterGroups();
            } else if (modalId === 'teacherSelectionModal') {
                document.getElementById('teacherSearch').value = '';
                filterTeachers();
            }
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

        function selectGroup(groupName) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'add';
            form.appendChild(actionInput);
            
            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'favorite_name';
            nameInput.value = groupName;
            form.appendChild(nameInput);
            
            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'favorite_type';
            typeInput.value = 'group';
            form.appendChild(typeInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        function selectTeacher(teacherName) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'add';
            form.appendChild(actionInput);
            
            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'favorite_name';
            nameInput.value = teacherName;
            form.appendChild(nameInput);
            
            const typeInput = document.createElement('input');
            typeInput.type = 'hidden';
            typeInput.name = 'favorite_type';
            typeInput.value = 'teacher';
            form.appendChild(typeInput);
            
            document.body.appendChild(form);
            form.submit();
        }

        // Загрузка аватара Telegram пользователя
        function loadTelegramAvatar() {
            const navAvatar = document.getElementById('navAvatar');
            if (!navAvatar) return;

            if (navAvatar.querySelector('img')) return;

            let photoUrl = null;

            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                const webApp = window.Telegram.WebApp;
                const initData = webApp.initDataUnsafe;
                
                if (initData) {
                    if (initData.user && initData.user.photo_url) {
                        photoUrl = initData.user.photo_url;
                    } else if (initData.user && initData.user.photo) {
                        photoUrl = initData.user.photo;
                    } else if (initData.photo_url) {
                        photoUrl = initData.photo_url;
                    }
                }
            }

            if (!photoUrl) {
                try {
                    const savedAvatar = localStorage.getItem('telegram_avatar_url');
                    if (savedAvatar) {
                        photoUrl = savedAvatar;
                    }
                } catch (e) {}
            }

            if (photoUrl) {
                const img = document.createElement('img');
                img.src = photoUrl;
                img.alt = 'Profile';
                img.style.width = '100%';
                img.style.height = '100%';
                img.style.objectFit = 'cover';
                img.style.borderRadius = '50%';
                img.onload = function() {
                    const fallbackIcon = navAvatar.querySelector('.nav-icon-fallback');
                    if (fallbackIcon) {
                        fallbackIcon.style.display = 'none';
                    }
                };
                img.onerror = function() {
                    this.style.display = 'none';
                };
                navAvatar.appendChild(img);
                
                if (photoUrl && photoUrl !== localStorage.getItem('telegram_avatar_url')) {
                    try {
                        localStorage.setItem('telegram_avatar_url', photoUrl);
                    } catch (e) {}
                }
                return;
            }

            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
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
                    if (data.success && data.user && data.user.photo_url) {
                        const img = document.createElement('img');
                        img.src = data.user.photo_url;
                        img.alt = 'Profile';
                        img.style.width = '100%';
                        img.style.height = '100%';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '50%';
                        img.onload = function() {
                            const fallbackIcon = navAvatar.querySelector('.nav-icon-fallback');
                            if (fallbackIcon) {
                                fallbackIcon.style.display = 'none';
                            }
                        };
                        img.onerror = function() {
                            this.style.display = 'none';
                        };
                        navAvatar.appendChild(img);
                        
                        try {
                            localStorage.setItem('telegram_avatar_url', data.user.photo_url);
                        } catch (e) {}
                    }
                })
                .catch(error => {
                    console.log('Avatar API fetch error:', error);
                });
            }
        }

        function isInTelegramWebApp() {
            return typeof window.Telegram !== 'undefined' && 
                   window.Telegram.WebApp && 
                   window.Telegram.WebApp.initDataUnsafe &&
                   window.Telegram.WebApp.initDataUnsafe.user;
        }

        document.addEventListener('DOMContentLoaded', function() {
            if (isInTelegramWebApp()) {
                loadTelegramAvatar();
                setTimeout(loadTelegramAvatar, 100);
                setTimeout(loadTelegramAvatar, 500);
                setTimeout(loadTelegramAvatar, 1000);
            } else {
                try {
                    const savedAvatar = localStorage.getItem('telegram_avatar_url');
                    if (savedAvatar) {
                        const navAvatar = document.getElementById('navAvatar');
                        if (navAvatar && !navAvatar.querySelector('img')) {
                            const img = document.createElement('img');
                            img.src = savedAvatar;
                            img.alt = 'Profile';
                            img.style.width = '100%';
                            img.style.height = '100%';
                            img.style.objectFit = 'cover';
                            img.style.borderRadius = '50%';
                            img.onload = function() {
                                const fallbackIcon = navAvatar.querySelector('.nav-icon-fallback');
                                if (fallbackIcon) {
                                    fallbackIcon.style.display = 'none';
                                }
                            };
                            img.onerror = function() {
                                this.style.display = 'none';
                            };
                            navAvatar.appendChild(img);
                        }
                    }
                } catch (e) {}
            }
        });

        function initTelegramWebApp() {
            if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
                const webApp = window.Telegram.WebApp;
                webApp.ready();
                webApp.expand();
                setTimeout(loadTelegramAvatar, 100);
                setTimeout(loadTelegramAvatar, 500);
            }
        }

        if (typeof window.Telegram !== 'undefined' && window.Telegram.WebApp) {
            initTelegramWebApp();
        } else {
            window.addEventListener('load', function() {
                setTimeout(initTelegramWebApp, 100);
            });
        }
    </script>
</body>
</html>
