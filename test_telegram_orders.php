<?php
// Тестовый скрипт для проверки функции getUserOrders
require_once 'config/database.php';

// Функция получения заказов пользователя (копия из webhook)
function getUserOrders($userId) {
    global $pdo;
    
    try {
        echo "getUserOrders called with userId: " . $userId . "\n";
        
        // Получаем имя пользователя
        $stmt = $pdo->prepare("SELECT client_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            echo "User not found with ID: " . $userId . "\n";
            return [];
        }
        
        $clientName = $user['client_name'];
        echo "Found client name: " . $clientName . "\n";
        
        // Получаем заказы по имени клиента
        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE client_name = ? AND status = 'active'
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$clientName]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found orders: " . count($orders) . "\n";
        
        // Также получаем заявки пользователя
        $stmt = $pdo->prepare("
            SELECT * FROM order_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo "Found requests: " . count($requests) . "\n";
        
        // Объединяем заказы и заявки
        $allItems = [];
        
        // Добавляем заказы
        foreach ($orders as $order) {
            $allItems[] = [
                'type' => 'order',
                'id' => $order['id'],
                'description' => $order['topic_description'] ?? 'Без описания',
                'status' => 'completed', // Заказы всегда завершены
                'created_at' => $order['created_at'],
                'work_type' => $order['work_type'],
                'total_price' => $order['total_price']
            ];
        }
        
        // Добавляем заявки
        foreach ($requests as $request) {
            $status = '';
            switch ($request['status']) {
                case 'pending': $status = 'pending'; break;
                case 'approved': $status = 'in_progress'; break;
                case 'rejected': $status = 'cancelled'; break;
                default: $status = 'pending';
            }
            
            $allItems[] = [
                'type' => 'request',
                'id' => $request['id'],
                'description' => $request['topic_description'] ?? 'Без описания',
                'status' => $status,
                'created_at' => $request['created_at'],
                'work_type' => $request['work_type'],
                'approved_price' => $request['approved_price']
            ];
        }
        
        // Сортируем по дате создания
        usort($allItems, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        $result = array_slice($allItems, 0, 10);
        echo "Returning " . count($result) . " total items\n";
        return $result;
    } catch (Exception $e) {
        echo "Error getting user orders: " . $e->getMessage() . "\n";
        return [];
    }
}

echo "<h2>Тест функции getUserOrders</h2>";

// Получаем всех пользователей
try {
    $stmt = $pdo->query("SELECT id, client_name, telegram_id FROM users WHERE status = 'active' LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h3>Пользователи в системе:</h3>";
    echo "<ul>";
    foreach ($users as $user) {
        echo "<li>ID: {$user['id']}, Имя: {$user['client_name']}, Telegram: {$user['telegram_id']}</li>";
    }
    echo "</ul>";
    
    // Тестируем функцию для каждого пользователя
    foreach ($users as $user) {
        echo "<h3>Тест для пользователя: {$user['client_name']} (ID: {$user['id']})</h3>";
        echo "<pre>";
        $orders = getUserOrders($user['id']);
        echo "</pre>";
        
        if (empty($orders)) {
            echo "<p>Заказов не найдено</p>";
        } else {
            echo "<h4>Найденные заказы/заявки:</h4>";
            echo "<ul>";
            foreach ($orders as $item) {
                echo "<li>";
                echo "Тип: {$item['type']}, ";
                echo "ID: {$item['id']}, ";
                echo "Статус: {$item['status']}, ";
                echo "Описание: " . (strlen($item['description']) > 50 ? substr($item['description'], 0, 50) . '...' : $item['description']);
                echo "</li>";
            }
            echo "</ul>";
        }
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>
