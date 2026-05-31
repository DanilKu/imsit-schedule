<?php
// config/telegram_auth.php
// Класс для работы с Telegram Web App авторизацией

require_once 'auth.php';
require_once 'database.php';
require_once 'blocked_telegram_ids.php';

class TelegramAuth {
    private $pdo;
    private $botToken; // Токен бота для проверки initData (опционально)
    
    public function __construct($pdo, $botToken = null) {
        $this->pdo = $pdo;
        $this->botToken = $botToken;
    }
    
    /**
     * Получение данных пользователя из Telegram Web App
     */
    public function getTelegramUserData() {
        // Проверяем, что мы в Telegram Web App
        if (!$this->isTelegramWebApp()) {
            return null;
        }
        
        // Получаем данные пользователя из различных источников
        $userData = null;
        
        // 1. Из JSON POST запроса (основной способ для Telegram Web App)
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            // Проверяем initData
            if (isset($input['initData'])) {
                $initData = $input['initData'];
                $userData = $this->parseInitData($initData);
            }
            
            // Проверяем initDataUnsafe (новый способ)
            if (!$userData && isset($input['initDataUnsafe'])) {
                $userData = $input['initDataUnsafe'];
            }
            
            // Проверяем прямые данные пользователя
            if (!$userData && isset($input['user'])) {
                $userData = $input['user'];
            }
        }
        
        // 2. Из обычного POST
        if (!$userData) {
            if (isset($_POST['initData'])) {
                $userData = $this->parseInitData($_POST['initData']);
            } elseif (isset($_POST['initDataUnsafe'])) {
                $userData = $_POST['initDataUnsafe'];
            } elseif (isset($_POST['user'])) {
                $userData = $_POST['user'];
            }
        }
        
        // 3. Из GET параметров
        if (!$userData) {
            if (isset($_GET['initData'])) {
                $userData = $this->parseInitData($_GET['initData']);
            } elseif (isset($_GET['initDataUnsafe'])) {
                $userData = $_GET['initDataUnsafe'];
            } elseif (isset($_GET['user'])) {
                $userData = $_GET['user'];
            }
        }
        
        return $userData;
    }
    
    /**
     * Парсинг initData для получения данных пользователя
     */
    private function parseInitData($initData) {
        if (!$initData) {
            return null;
        }
        
        // Парсим данные
        parse_str($initData, $data);
        
        if (!isset($data['user'])) {
            return null;
        }
        
        // Декодируем JSON данные пользователя
        $userData = json_decode($data['user'], true);
        
        return $userData;
    }
    
    /**
     * Проверка, что мы находимся в Telegram Web App
     */
    public function isTelegramWebApp() {
        // Проверяем различные признаки Telegram Web App
        
        // 1. Проверяем User-Agent (может быть разным в разных версиях Telegram)
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $isTelegramUA = strpos($userAgent, 'TelegramBot') !== false || 
                       strpos($userAgent, 'Telegram') !== false ||
                       strpos($userAgent, 'WebApp') !== false;
        
        // 2. Проверяем специальные параметры
        $hasTelegramParams = isset($_GET['tgWebAppData']) || 
                            isset($_POST['tgWebAppData']) ||
                            isset($_GET['tgWebAppVersion']) ||
                            isset($_POST['tgWebAppVersion']) ||
                            isset($_GET['tgWebAppPlatform']) ||
                            isset($_POST['tgWebAppPlatform']);
        
        // 3. Проверяем наличие initData в запросе
        $hasInitData = false;
        
        // Из JSON POST
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['initData'])) {
            $hasInitData = true;
        }
        
        // Из обычного POST/GET
        if (!$hasInitData && (isset($_POST['initData']) || isset($_GET['initData']))) {
            $hasInitData = true;
        }
        
        // 4. Проверяем заголовки
        $hasTelegramHeaders = isset($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN']) ||
                             isset($_SERVER['HTTP_X_TELEGRAM_INIT_DATA']) ||
                             isset($_SERVER['HTTP_X_TELEGRAM_WEBAPP_DATA']);
        
        // 5. Проверяем Referer (может содержать telegram.org)
        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        $isTelegramReferer = strpos($referer, 'telegram.org') !== false ||
                            strpos($referer, 't.me') !== false;
        
        // 6. Проверяем Origin
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $isTelegramOrigin = strpos($origin, 'telegram.org') !== false ||
                           strpos($origin, 't.me') !== false;
        
        // 7. Проверяем наличие JavaScript переменных (через заголовки)
        $hasTelegramJS = isset($_SERVER['HTTP_X_TELEGRAM_WEBAPP_VERSION']) ||
                        isset($_SERVER['HTTP_X_TELEGRAM_WEBAPP_PLATFORM']);
        
        // Логируем для отладки
        error_log("Telegram Web App Detection: UA=$isTelegramUA, Params=$hasTelegramParams, InitData=$hasInitData, Headers=$hasTelegramHeaders, Referer=$isTelegramReferer, Origin=$isTelegramOrigin, JS=$hasTelegramJS");
        error_log("User-Agent: " . $userAgent);
        error_log("Referer: " . $referer);
        error_log("Origin: " . $origin);
        
        return $isTelegramUA || $hasTelegramParams || $hasInitData || $hasTelegramHeaders || $isTelegramReferer || $isTelegramOrigin || $hasTelegramJS;
    }
    
    /**
     * Автоматический вход по Telegram ID
     */
    public function autoLoginByTelegramId($telegramId) {
        try {
            // Проверяем, не заблокирован ли пользователь
            if (isTelegramIdBlocked($telegramId)) {
                return false;
            }
            
            $sql = "SELECT * FROM users WHERE telegram_id = :telegram_id AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['telegram_id' => $telegramId]);
            
            $user = $stmt->fetch();
            
            if ($user) {
                // Автоматический вход
                $_SESSION['authenticated'] = true;
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['client_name'] = $user['client_name'];
                $_SESSION['telegram_username'] = $user['telegram_username'];
                $_SESSION['telegram_id'] = $user['telegram_id'];
                $_SESSION['group'] = $user['group'] ?? null;
                
                return true;
            }
            
            return false;
        } catch (PDOException $e) {
            error_log("Ошибка автоматического входа: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Привязка Telegram ID к существующему аккаунту
     */
    public function linkTelegramToAccount($username, $telegramId, $telegramUsername = null) {
        try {
            // Сначала проверяем, что пользователь существует и активен
            $checkSql = "SELECT id FROM users WHERE username = :username AND status = 'active'";
            $checkStmt = $this->pdo->prepare($checkSql);
            $checkStmt->execute(['username' => $username]);
            
            if (!$checkStmt->fetch()) {
                return false; // Пользователь не найден или неактивен
            }
            
            // Проверяем, что telegram_id еще не привязан к другому аккаунту
            $checkTelegramSql = "SELECT id FROM users WHERE telegram_id = :telegram_id";
            $checkTelegramStmt = $this->pdo->prepare($checkTelegramSql);
            $checkTelegramStmt->execute(['telegram_id' => $telegramId]);
            
            if ($checkTelegramStmt->fetch()) {
                return false; // Telegram ID уже привязан
            }
            
            // Привязываем Telegram ID к аккаунту
            $sql = "UPDATE users SET telegram_id = :telegram_id, telegram_username = :telegram_username WHERE username = :username";
            $stmt = $this->pdo->prepare($sql);
            $result = $stmt->execute([
                'telegram_id' => (int)$telegramId, // Приводим к int для BIGINT поля
                'telegram_username' => $telegramUsername,
                'username' => $username
            ]);
            
            // Дополнительная проверка: убеждаемся, что данные сохранились
            if ($result) {
                $checkSql = "SELECT telegram_id FROM users WHERE username = :username";
                $checkStmt = $this->pdo->prepare($checkSql);
                $checkStmt->execute(['username' => $username]);
                $updatedUser = $checkStmt->fetch();
                
                if (!$updatedUser || $updatedUser['telegram_id'] != (int)$telegramId) {
                    error_log("Ошибка: telegram_id не сохранился в БД. Ожидалось: " . (int)$telegramId . ", получено: " . ($updatedUser['telegram_id'] ?? 'null'));
                    return false;
                }
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Ошибка привязки Telegram: " . $e->getMessage());
            error_log("SQL Error Info: " . json_encode($stmt->errorInfo()));
            return false;
        }
    }
    
    /**
     * Проверка и автоматический вход при загрузке страницы
     */
    public function checkAndAutoLogin() {
        // Если уже авторизован, ничего не делаем
        if (isAuthenticated()) {
            return true;
        }
        
        // Получаем данные пользователя из Telegram
        $telegramUser = $this->getTelegramUserData();
        
        if (!$telegramUser || !isset($telegramUser['id'])) {
            return false;
        }
        
        $telegramId = $telegramUser['id'];
        
        // Пытаемся автоматически войти
        if ($this->autoLoginByTelegramId($telegramId)) {
            return true;
        }
        
        // Если автоматический вход не удался, сохраняем данные для привязки
        $_SESSION['telegram_user_data'] = $telegramUser;
        
        return false;
    }
    
    /**
     * Получение данных Telegram пользователя из сессии
     */
    public function getStoredTelegramData() {
        return $_SESSION['telegram_user_data'] ?? null;
    }
    
    /**
     * Очистка данных Telegram из сессии
     */
    public function clearStoredTelegramData() {
        unset($_SESSION['telegram_user_data']);
    }
    
    /**
     * Проверка валидности initData (базовая проверка)
     */
    public function validateInitData($initData) {
        if (empty($initData)) {
            return false;
        }
        
        // Парсим данные
        parse_str($initData, $data);
        
        // Проверяем наличие обязательных полей
        if (!isset($data['user']) || !isset($data['auth_date'])) {
            return false;
        }
        
        // Проверяем, что auth_date не слишком старый (24 часа)
        $authDate = (int)$data['auth_date'];
        $currentTime = time();
        
        if ($currentTime - $authDate > 86400) { // 24 часа
            return false;
        }
        
        return true;
    }
    
    /**
     * Получение информации о Telegram пользователе для отображения
     */
    public function getTelegramUserInfo() {
        $telegramUser = $this->getStoredTelegramData();
        
        if (!$telegramUser) {
            return null;
        }
        
        return [
            'id' => $telegramUser['id'],
            'first_name' => $telegramUser['first_name'] ?? '',
            'last_name' => $telegramUser['last_name'] ?? '',
            'username' => $telegramUser['username'] ?? '',
            'language_code' => $telegramUser['language_code'] ?? 'ru'
        ];
    }
}
