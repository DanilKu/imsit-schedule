<?php
require_once __DIR__ . '/../config/database.php';

class NotificationManager {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    private function ensureSchema(): void {
        // Таблица уведомлений
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
            is_active BOOLEAN DEFAULT TRUE,
            show_on_login BOOLEAN DEFAULT TRUE,
            show_on_dashboard BOOLEAN DEFAULT TRUE,
            target_role ENUM('all', 'admin', 'client') DEFAULT 'all',
            start_date DATE NULL,
            end_date DATE NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Таблица логов показанных уведомлений
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS notification_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            notification_id INT NOT NULL,
            user_id INT NOT NULL,
            shown_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX (notification_id),
            INDEX (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Проверяем, есть ли хотя бы одно уведомление
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM notifications");
        if ((int)$stmt->fetchColumn() === 0) {
            $this->pdo->exec("INSERT INTO notifications (title, message, type, is_active, show_on_login, show_on_dashboard, target_role) VALUES 
                ('Добро пожаловать в ImsitShop!', 'Учёба без границ — успех без сомнений. Мы рады приветствовать вас в нашей системе учёта работ. Здесь вы можете отслеживать прогресс выполнения ваших заказов, подавать новые заявки и получать уведомления о важных событиях.', 'info', TRUE, TRUE, TRUE, 'all')");
        }
    }

    /**
     * Получить активные уведомления для пользователя
     */
    public function getActiveNotifications(int $userId, string $userRole, string $context = 'dashboard'): array {
        try {
            $today = date('Y-m-d');
            
            $sql = "SELECT n.* FROM notifications n 
                    WHERE n.is_active = TRUE 
                    AND (n.start_date IS NULL OR n.start_date <= :today)
                    AND (n.end_date IS NULL OR n.end_date >= :today)
                    AND (n.target_role = 'all' OR n.target_role = :role)
                    AND n.show_on_" . $context . " = TRUE
                    AND n.id NOT IN (
                        SELECT nl.notification_id 
                        FROM notification_logs nl 
                        WHERE nl.user_id = :user_id
                    )
                    ORDER BY n.created_at DESC";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'today' => $today,
                'role' => $userRole,
                'user_id' => $userId
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Ошибка получения уведомлений: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Отметить уведомление как показанное
     */
    public function markAsShown(int $notificationId, int $userId): bool {
        try {
            $sql = "INSERT INTO notification_logs (notification_id, user_id) VALUES (:notification_id, :user_id)";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'notification_id' => $notificationId,
                'user_id' => $userId
            ]);
        } catch (PDOException $e) {
            error_log("Ошибка отметки уведомления: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить все уведомления (для админ-панели)
     */
    public function getAllNotifications(): array {
        try {
            $sql = "SELECT * FROM notifications ORDER BY created_at DESC";
            $stmt = $this->pdo->query($sql);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Ошибка получения всех уведомлений: " . $e->getMessage());
            return [];
        }
    }

    /**
     * Создать новое уведомление
     */
    public function createNotification(array $data): bool {
        try {
            $sql = "INSERT INTO notifications (title, message, type, is_active, show_on_login, show_on_dashboard, target_role, start_date, end_date) 
                    VALUES (:title, :message, :type, :is_active, :show_on_login, :show_on_dashboard, :target_role, :start_date, :end_date)";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'] ?? 'info',
                'is_active' => $data['is_active'] ?? true,
                'show_on_login' => $data['show_on_login'] ?? true,
                'show_on_dashboard' => $data['show_on_dashboard'] ?? true,
                'target_role' => $data['target_role'] ?? 'all',
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Ошибка создания уведомления: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Обновить уведомление
     */
    public function updateNotification(int $id, array $data): bool {
        try {
            $sql = "UPDATE notifications SET 
                    title = :title, 
                    message = :message, 
                    type = :type, 
                    is_active = :is_active, 
                    show_on_login = :show_on_login, 
                    show_on_dashboard = :show_on_dashboard, 
                    target_role = :target_role, 
                    start_date = :start_date, 
                    end_date = :end_date 
                    WHERE id = :id";
            
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute([
                'id' => $id,
                'title' => $data['title'],
                'message' => $data['message'],
                'type' => $data['type'] ?? 'info',
                'is_active' => $data['is_active'] ?? true,
                'show_on_login' => $data['show_on_login'] ?? true,
                'show_on_dashboard' => $data['show_on_dashboard'] ?? true,
                'target_role' => $data['target_role'] ?? 'all',
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null
            ]);
        } catch (PDOException $e) {
            error_log("Ошибка обновления уведомления: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Удалить уведомление
     */
    public function deleteNotification(int $id): bool {
        try {
            $sql = "DELETE FROM notifications WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            return $stmt->execute(['id' => $id]);
        } catch (PDOException $e) {
            error_log("Ошибка удаления уведомления: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Получить уведомление по ID
     */
    public function getNotificationById(int $id): ?array {
        try {
            $sql = "SELECT * FROM notifications WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ?: null;
        } catch (PDOException $e) {
            error_log("Ошибка получения уведомления: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Очистить логи показанных уведомлений
     */
    public function clearNotificationLogs(int $notificationId = null): bool {
        try {
            if ($notificationId) {
                $sql = "DELETE FROM notification_logs WHERE notification_id = :notification_id";
                $stmt = $this->pdo->prepare($sql);
                return $stmt->execute(['notification_id' => $notificationId]);
            } else {
                $sql = "DELETE FROM notification_logs";
                $stmt = $this->pdo->query($sql);
                return true;
            }
        } catch (PDOException $e) {
            error_log("Ошибка очистки логов уведомлений: " . $e->getMessage());
            return false;
        }
    }
}
