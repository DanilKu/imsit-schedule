<?php
require_once 'config/database.php';
require_once 'config/notifications_local.php';

class WorkStage {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Получение этапов для заказа
    public function getByOrderId($orderId) {
        try {
            $sql = "SELECT * FROM work_stages WHERE order_id = :order_id ORDER BY id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['order_id' => $orderId]);
            
            $results = $stmt->fetchAll();
            
            // Преобразуем булевы значения для всех этапов
            foreach ($results as &$result) {
                $result['is_completed'] = (bool)$result['is_completed'];
                $result['is_in_progress'] = (bool)$result['is_in_progress'];
            }
            
            return $results;
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения этапов: " . $e->getMessage());
        }
    }
    
    // Обновление статуса этапа
    public function updateStatus($id, $status, $notes = null) {
        try {
            $sql = "UPDATE work_stages SET 
                    is_completed = :is_completed,
                    is_in_progress = :is_in_progress,
                    notes = :notes
                    WHERE id = :id";
            
            $isCompleted = ($status === 'completed');
            $isInProgress = ($status === 'in_progress');
            
            // Преобразуем булевы значения в целые числа
            $isCompletedInt = $isCompleted ? 1 : 0;
            $isInProgressInt = $isInProgress ? 1 : 0;
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'is_completed' => $isCompletedInt,
                'is_in_progress' => $isInProgressInt,
                'notes' => $notes
            ]);
            
            // Проверяем, нужно ли обновить общий статус заказа
            $stageInfo = $this->getById($id);
            $this->checkAndUpdateOrderStatus($stageInfo['order_id']);
            
            return true;
        } catch (PDOException $e) {
            throw new Exception("Ошибка обновления этапа: " . $e->getMessage());
        }
    }
    
    // Получение этапа по ID
    public function getById($id) {
        try {
            $sql = "SELECT * FROM work_stages WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $id]);
            
            $result = $stmt->fetch();
            
            // Преобразуем булевы значения
            if ($result) {
                $result['is_completed'] = (bool)$result['is_completed'];
                $result['is_in_progress'] = (bool)$result['is_in_progress'];
            }
            
            return $result;
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения этапа: " . $e->getMessage());
        }
    }
    
    // Получение информации о заказе
    private function getOrderInfo($orderId) {
        try {
            $sql = "SELECT client_name FROM orders WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $orderId]);
            
            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения информации о заказе: " . $e->getMessage());
        }
    }
    
    // Получение статистики по этапам
    public function getStageStatistics($orderId) {
        try {
            $sql = "SELECT 
                    COUNT(*) as total_stages,
                    COUNT(CASE WHEN is_completed = 1 THEN 1 END) as completed_stages,
                    COUNT(CASE WHEN is_in_progress = 1 THEN 1 END) as in_progress_stages
                    FROM work_stages WHERE order_id = :order_id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['order_id' => $orderId]);
            
            $stats = $stmt->fetch();
            $stats['progress_percentage'] = $stats['total_stages'] > 0 ? 
                round(($stats['completed_stages'] / $stats['total_stages']) * 100) : 0;
            
            return $stats;
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения статистики этапов: " . $e->getMessage());
        }
    }
    
    // Проверка и обновление общего статуса заказа на основе этапов
    private function checkAndUpdateOrderStatus($orderId) {
        try {
            // Получаем статистику по этапам
            $stats = $this->getStageStatistics($orderId);
            
            // Определяем новый статус заказа
            $newOrderStatus = 'pending';
            
            if ($stats['completed_stages'] == $stats['total_stages'] && $stats['total_stages'] > 0) {
                // Все этапы завершены
                $newOrderStatus = 'completed';
            } elseif ($stats['in_progress_stages'] > 0 || $stats['completed_stages'] > 0) {
                // Есть этапы в работе или завершенные
                $newOrderStatus = 'in_progress';
            }
            
            // Обновляем статус заказа, если он изменился
            $currentOrderStatus = $this->getOrderStatus($orderId);
            if ($currentOrderStatus !== $newOrderStatus) {
                $sql = "UPDATE orders SET work_status = :status WHERE id = :id";
                $stmt = $this->pdo->prepare($sql);
                $stmt->execute([
                    'id' => $orderId,
                    'status' => $newOrderStatus
                ]);
                

            }
            
        } catch (PDOException $e) {
            // Логируем ошибку, но не прерываем основную операцию
            error_log("Ошибка проверки статуса заказа: " . $e->getMessage());
        }
    }
    
    // Получение текущего статуса заказа
    private function getOrderStatus($orderId) {
        try {
            $sql = "SELECT work_status FROM orders WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $orderId]);
            
            $result = $stmt->fetch();
            return $result ? $result['work_status'] : 'pending';
        } catch (PDOException $e) {
            return 'pending';
        }
    }
    
    // Получение всех этапов с информацией о заказах
    public function getAllWithOrders($filters = []) {
        try {
            $sql = "SELECT ws.*, o.client_name, o.work_type 
                    FROM work_stages ws 
                    JOIN orders o ON ws.order_id = o.id 
                    WHERE o.status = 'active'";
            $params = [];
            
            if (!empty($filters['stage_type'])) {
                $sql .= " AND ws.stage_type = :stage_type";
                $params['stage_type'] = $filters['stage_type'];
            }
            
            if (!empty($filters['is_completed'])) {
                $sql .= " AND ws.is_completed = :is_completed";
                // Преобразуем строковое значение в целое число
                $params['is_completed'] = (int)$filters['is_completed'];
            }
            
            if (!empty($filters['work_type'])) {
                $sql .= " AND o.work_type = :work_type";
                $params['work_type'] = $filters['work_type'];
            }
            
            $sql .= " ORDER BY o.priority DESC, o.created_at DESC, ws.id";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            
            $results = $stmt->fetchAll();
            
            // Преобразуем булевы значения для всех этапов
            foreach ($results as &$result) {
                $result['is_completed'] = (bool)$result['is_completed'];
                $result['is_in_progress'] = (bool)$result['is_in_progress'];
            }
            
            return $results;
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения этапов: " . $e->getMessage());
        }
    }
} 