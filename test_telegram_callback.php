<?php
// Тестовый скрипт для проверки callback обработки
require_once 'config/database.php';

// Симулируем callback данные
$testCallback = [
    'callback_query' => [
        'id' => 'test_123',
        'from' => [
            'id' => 123456789, // Замените на реальный Telegram ID
            'first_name' => 'Test',
            'username' => 'testuser'
        ],
        'message' => [
            'message_id' => 1,
            'chat' => [
                'id' => 123456789
            ]
        ],
        'data' => 'my_orders'
    ]
];

echo "<h2>Тест обработки callback</h2>";

// Проверяем, есть ли пользователь с таким Telegram ID
$telegramId = $testCallback['callback_query']['from']['id'];
echo "<p>Тестируем Telegram ID: {$telegramId}</p>";

try {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ? AND status = 'active'");
    $stmt->execute([$telegramId]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        echo "<p>✅ Пользователь найден: {$user['client_name']} (ID: {$user['id']})</p>";
        
        // Тестируем функцию getUserOrders
        echo "<h3>Тест функции getUserOrders:</h3>";
        echo "<pre>";
        
        // Копируем функцию getUserOrders
        function testGetUserOrders($userId) {
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
                        'status' => 'completed',
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
        
        $orders = testGetUserOrders($user['id']);
        echo "</pre>";
        
        if (empty($orders)) {
            echo "<p>❌ Заказов не найдено</p>";
        } else {
            echo "<p>✅ Найдено заказов/заявок: " . count($orders) . "</p>";
            echo "<h4>Детали:</h4>";
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
        
    } else {
        echo "<p>❌ Пользователь с Telegram ID {$telegramId} не найден</p>";
        echo "<p>Проверьте, что пользователь привязал свой Telegram аккаунт</p>";
    }
    
} catch (Exception $e) {
    echo "<p>Ошибка: " . $e->getMessage() . "</p>";
}

echo "<h3>Инструкции:</h3>";
echo "<ol>";
echo "<li>Замените Telegram ID в коде на ваш реальный ID</li>";
echo "<li>Убедитесь, что ваш аккаунт привязан в системе</li>";
echo "<li>Проверьте, что у вас есть заказы или заявки</li>";
echo "</ol>";
?>
