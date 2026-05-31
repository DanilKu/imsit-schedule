<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/Order.php';

class OrderRequestService {
    private PDO $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureSchema();
    }

    private function ensureSchema(): void {
        // Таблица настроек заявок
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_request_settings (
            work_type VARCHAR(64) PRIMARY KEY,
            is_open TINYINT(1) NOT NULL DEFAULT 1,
            default_price DECIMAL(12,2) NOT NULL DEFAULT 0
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        // Инициализация настроек по умолчанию, если пусто
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM order_request_settings");
        if ((int)$stmt->fetchColumn() === 0) {
            $defaults = [
                ['coursework', 1, 0],
                ['production_practice', 1, 0],
                ['study_practice', 1, 0],
            ];
            $ins = $this->pdo->prepare("INSERT INTO order_request_settings (work_type, is_open, default_price) VALUES (:work_type, :is_open, :default_price)");
            foreach ($defaults as [$type, $open, $price]) {
                $ins->execute(['work_type' => $type, 'is_open' => $open, 'default_price' => $price]);
            }
        }

        // Таблица заявок
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS order_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            client_name VARCHAR(255) NOT NULL,
            work_type VARCHAR(64) NOT NULL,
            semester TINYINT NOT NULL,
            topic_number VARCHAR(64) NULL,
            topic_description TEXT NULL,
            status ENUM('pending','approved','rejected','deleted') NOT NULL DEFAULT 'pending',
            approved_order_id INT NULL,
            approved_price DECIMAL(12,2) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX (status),
            INDEX (work_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function getSettings(): array {
        $stmt = $this->pdo->query("SELECT * FROM order_request_settings ORDER BY work_type");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateSettings(array $settings): void {
        $upd = $this->pdo->prepare("UPDATE order_request_settings SET is_open = :is_open, default_price = :default_price WHERE work_type = :work_type");
        foreach ($settings as $workType => $conf) {
            $upd->execute([
                'is_open' => !empty($conf['is_open']) ? 1 : 0,
                'default_price' => (float)($conf['default_price'] ?? 0),
                'work_type' => $workType
            ]);
        }
    }

    public function isTypeOpen(string $workType): bool {
        $stmt = $this->pdo->prepare("SELECT is_open FROM order_request_settings WHERE work_type = :wt");
        $stmt->execute(['wt' => $workType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? ((int)$row['is_open'] === 1) : false;
    }

    public function getDefaultPrice(string $workType): float {
        $stmt = $this->pdo->prepare("SELECT default_price FROM order_request_settings WHERE work_type = :wt");
        $stmt->execute(['wt' => $workType]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ? (float)$row['default_price'] : 0.0;
    }

    public function createRequest(array $data): int {
        // Валидация
        $allowedTypes = ['coursework', 'production_practice', 'study_practice'];
        if (!in_array($data['work_type'] ?? '', $allowedTypes, true)) {
            throw new Exception('Недопустимый тип работы');
        }
        if ((string)($data['semester'] ?? '') !== '7') {
            throw new Exception('Заявки доступны только для 7 семестра');
        }
        if (!$this->isTypeOpen($data['work_type'])) {
            throw new Exception('Приём заявок для выбранного типа работ временно закрыт');
        }

        $stmt = $this->pdo->prepare("INSERT INTO order_requests (user_id, client_name, work_type, semester, topic_number, topic_description) 
            VALUES (:user_id, :client_name, :work_type, :semester, :topic_number, :topic_description)");
        $stmt->execute([
            'user_id' => (int)$data['user_id'],
            'client_name' => $data['client_name'],
            'work_type' => $data['work_type'],
            'semester' => 7,
            'topic_number' => $data['topic_number'] ?? null,
            'topic_description' => $data['topic_description'] ?? null,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    public function listRequests(string $status = 'pending', ?int $userId = null): array {
        if ($userId !== null) {
            // Фильтрация по пользователю
            $stmt = $this->pdo->prepare("SELECT * FROM order_requests WHERE status = :status AND user_id = :user_id ORDER BY created_at DESC");
            $stmt->execute(['status' => $status, 'user_id' => $userId]);
        } else {
            // Все заявки (для админ-панели)
            $stmt = $this->pdo->prepare("SELECT * FROM order_requests WHERE status = :status ORDER BY created_at DESC");
            $stmt->execute(['status' => $status]);
        }
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function approveRequest(int $id, float $price): int {
        // Получаем заявку
        $stmt = $this->pdo->prepare("SELECT * FROM order_requests WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $id]);
        $req = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$req) {
            throw new Exception('Заявка не найдена или уже обработана');
        }

        // Создаём заказ
        $order = new Order($this->pdo);
        $orderId = $order->create([
            'client_name' => $req['client_name'],
            'topic_number' => $req['topic_number'],
            'topic_description' => $req['topic_description'],
            'work_type' => $req['work_type'],
            'semester' => 7,
            'total_price' => $price,
            'paid_amount' => 0,
        ]);

        // Обновляем заявку
        $upd = $this->pdo->prepare("UPDATE order_requests SET status = 'approved', approved_order_id = :oid, approved_price = :price WHERE id = :id");
        $upd->execute(['oid' => $orderId, 'price' => $price, 'id' => $id]);

        return (int)$orderId;
    }

    public function rejectRequest(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE order_requests SET status = 'rejected' WHERE id = :id AND status = 'pending'");
        $stmt->execute(['id' => $id]);
    }

    public function deleteRequest(int $id): void {
        $stmt = $this->pdo->prepare("UPDATE order_requests SET status = 'deleted' WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }
}
