<?php
require_once 'config/database.php';
require_once 'config/notifications_local.php';

class Order {
    private $pdo;

    public function __construct($pdo) {
        $this->pdo = $pdo;
    }

    // Создание нового заказа
    public function create($data) {
        try {
            $sql = "INSERT INTO orders (client_name, topic_number, topic_description, work_type, semester, total_price, paid_amount)
                    VALUES (:client_name, :topic_number, :topic_description, :work_type, :semester, :total_price, :paid_amount)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'client_name' => $data['client_name'],
                'topic_number' => $data['topic_number'] ?? null,
                'topic_description' => $data['topic_description'] ?? null,
                'work_type' => $data['work_type'],
                'semester' => $data['semester'] ?? '6',
                'total_price' => $data['total_price'],
                'paid_amount' => $data['paid_amount'] ?? 0
            ]);

            $orderId = $this->pdo->lastInsertId();

            // Создание этапов работ
            $this->createDefaultStages($orderId, $data['work_type']);

            // Проверка на полную оплату и установка приоритета
            if (($data['paid_amount'] ?? 0) >= $data['total_price']) {
                $this->setPriority($orderId, true);
            }

            return $orderId;
        } catch (PDOException $e) {
            throw new Exception("Ошибка создания заказа: " . $e->getMessage());
        }
    }

    // Получение всех заказов
    public function getAll($filters = []) {
        try {
            $sql = "SELECT * FROM orders WHERE status = 'active'";
            $params = [];

            if (!empty($filters['work_type'])) {
                $sql .= " AND work_type = :work_type";
                $params['work_type'] = $filters['work_type'];
            }

            if (!empty($filters['semester'])) {
                $sql .= " AND semester = :semester";
                $params['semester'] = $filters['semester'];
            }

            if (isset($filters['is_paid']) && $filters['is_paid'] !== '') {
                if ($filters['is_paid'] === '1') {
                    $sql .= " AND ABS(paid_amount - total_price) < 0.01 AND total_price > 0";
                } else {
                    $sql .= " AND (total_price - paid_amount) >= 0.01 AND total_price > 0";
                }
            }

            if (!empty($filters['priority'])) {
                $sql .= " AND priority = :priority";
                // Преобразуем строковое значение в целое число
                $params['priority'] = (int)$filters['priority'];
            }

            if (!empty($filters['search'])) {
                $sql .= " AND client_name LIKE :search";
                $params['search'] = "%{$filters['search']}%";
            }

            $sql .= " ORDER BY priority DESC, created_at DESC";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            $results = $stmt->fetchAll();

            // Преобразуем priority в булево значение для всех заказов
            foreach ($results as &$result) {
                $result['priority'] = (bool)$result['priority'];
            }

            return $results;
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения заказов: " . $e->getMessage());
        }
    }

    // Получение заказа по ID
    public function getById($id) {
        try {
            $sql = "SELECT * FROM orders WHERE id = :id AND status = 'active'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);

            $result = $stmt->fetch();

            // Преобразуем priority в булево значение
            if ($result) {
                $result['priority'] = (bool)$result['priority'];
            }

            return $result;
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения заказа: " . $e->getMessage());
        }
    }

    // Обновление заказа
    public function update($id, $data) {
        try {
            $sql = "UPDATE orders SET
                    client_name = :client_name,
                    topic_number = :topic_number,
                    topic_description = :topic_description,
                    work_type = :work_type,
                    semester = :semester,
                    total_price = :total_price,
                    paid_amount = :paid_amount
                    WHERE id = :id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'client_name' => $data['client_name'],
                'topic_number' => $data['topic_number'] ?? null,
                'topic_description' => $data['topic_description'] ?? null,
                'work_type' => $data['work_type'],
                'semester' => $data['semester'] ?? '6',
                'total_price' => $data['total_price'],
                'paid_amount' => $data['paid_amount'] ?? 0
            ]);

            // Проверка на полную оплату и установка приоритета
            if (($data['paid_amount'] ?? 0) >= $data['total_price']) {
                $this->setPriority($id, true);
            }

            return true;
        } catch (PDOException $e) {
            throw new Exception("Ошибка обновления заказа: " . $e->getMessage());
        }
    }

    // Удаление заказа (мягкое удаление)
    public function delete($id) {
        try {
            $sql = "UPDATE orders SET status = 'deleted' WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);

            return true;
        } catch (PDOException $e) {
            throw new Exception("Ошибка удаления заказа: " . $e->getMessage());
        }
    }

    // Установка приоритета
    public function setPriority($id, $priority) {
        try {
            $sql = "UPDATE orders SET priority = :priority WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            // Преобразуем булево значение в целое число (0 или 1)
            $priorityInt = $priority ? 1 : 0;
            $stmt->execute(['id' => $id, 'priority' => $priorityInt]);

            return true;
        } catch (PDOException $e) {
            throw new Exception("Ошибка установки приоритета: " . $e->getMessage());
        }
    }

    // Установка статуса работы
    public function setWorkStatus($id, $status) {
        try {
            $sql = "UPDATE orders SET work_status = :status WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id, 'status' => $status]);

            // Автоматическое обновление статусов всех этапов
            $this->syncWorkStagesStatus($id, $status);

            return true;
        } catch (PDOException $e) {
            throw new Exception("Ошибка установки статуса работы: " . $e->getMessage());
        }
    }

    // Синхронизация статусов этапов с общим статусом работы
    private function syncWorkStagesStatus($orderId, $workStatus) {
        try {
            // Определяем новые статусы для этапов
            $stageStatus = 'pending'; // по умолчанию
            $isCompleted = false;
            $isInProgress = false;

            switch ($workStatus) {
                case 'completed':
                    $stageStatus = 'completed';
                    $isCompleted = true;
                    $isInProgress = false;
                    break;
                case 'in_progress':
                    $stageStatus = 'in_progress';
                    $isCompleted = false;
                    $isInProgress = true;
                    break;
                case 'pending':
                default:
                    $stageStatus = 'pending';
                    $isCompleted = false;
                    $isInProgress = false;
                    break;
            }

            // Обновляем все этапы для данного заказа
            $sql = "UPDATE work_stages SET
                    is_completed = :is_completed,
                    is_in_progress = :is_in_progress
                    WHERE order_id = :order_id";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'order_id' => $orderId,
                'is_completed' => $isCompleted ? 1 : 0,
                'is_in_progress' => $isInProgress ? 1 : 0
            ]);

        } catch (PDOException $e) {
            // Логируем ошибку, но не прерываем основную операцию
            error_log("Ошибка синхронизации этапов: " . $e->getMessage());
        }
    }

    // Получение статистики
    public function getStatistics($semester = null) {
        try {
            $sql = "SELECT
                    COUNT(*) as total_orders,
                    SUM(total_price) as total_revenue,
                    SUM(paid_amount) as total_paid,
                    SUM(debt_amount) as total_debt,
                    COUNT(CASE WHEN is_paid = 1 THEN 1 END) as paid_orders,
                    COUNT(CASE WHEN priority = 1 THEN 1 END) as priority_orders,
                    COUNT(CASE WHEN work_status = 'completed' THEN 1 END) as completed_orders,
                    COUNT(CASE WHEN work_status = 'in_progress' THEN 1 END) as in_progress_orders,
                    COUNT(CASE WHEN work_status = 'pending' THEN 1 END) as pending_orders
                    FROM orders WHERE status = 'active'";

            $params = [];
            if ($semester) {
                $sql .= " AND semester = :semester";
                $params['semester'] = $semester;
            }

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);

            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения статистики: " . $e->getMessage());
        }
    }

    // Создание этапов работ по умолчанию
    private function createDefaultStages($orderId, $workType) {
        $stages = [
            ['stage_name' => '1 Глава', 'stage_type' => 'chapter1'],
            ['stage_name' => '2 Глава', 'stage_type' => 'chapter2'],
            ['stage_name' => '3,4 Глава', 'stage_type' => 'chapter34'],
            ['stage_name' => 'Приложение', 'stage_type' => 'application']
        ];

        if ($workType === 'coursework') {
            $stages[] = ['stage_name' => 'ТЗ', 'stage_type' => 'terms_of_reference'];
        }

        foreach ($stages as $stage) {
            $sql = "INSERT INTO work_stages (order_id, stage_name, stage_type) VALUES (:order_id, :stage_name, :stage_type)";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'order_id' => $orderId,
                'stage_name' => $stage['stage_name'],
                'stage_type' => $stage['stage_type']
            ]);
        }
    }
}