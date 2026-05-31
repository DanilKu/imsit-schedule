<?php
// Webhook для пользовательского Telegram бота
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/blocked_telegram_ids.php';


// Токен пользовательского бота (замените на ваш)
$botToken = '8470730297:AAGh7S8gDlNdtE8aMIIcQ1wrT2eDe7AZj0U';

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Функция отправки сообщения
function sendMessage($chatId, $text, $replyMarkup = null) {
    global $botToken;
    
    $url = "https://api.telegram.org/bot{$botToken}/sendMessage";
    
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// Функция отправки фото
function sendPhoto($chatId, $photoPath, $caption = '', $replyMarkup = null) {
    global $botToken;
    
    error_log("sendPhoto called: chatId=$chatId, photoPath=$photoPath, captionLength=" . strlen($caption));
    
    if (!file_exists($photoPath)) {
        error_log("Photo file does not exist: $photoPath");
        return false;
    }
    
    $url = "https://api.telegram.org/bot{$botToken}/sendPhoto";
    
    // Подготавливаем данные для multipart/form-data
    $data = [
        'chat_id' => $chatId,
        'photo' => new CURLFile($photoPath, 'image/png', basename($photoPath)),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Увеличиваем таймаут для загрузки файла
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    error_log("sendPhoto response: HTTP $httpCode, Error: $error, Response: " . substr($response, 0, 200));
    
    if ($httpCode === 200) {
        $result = json_decode($response, true);
        if ($result && $result['ok']) {
            error_log("Photo sent successfully");
            return $result;
        } else {
            error_log("Telegram API error: " . json_encode($result));
            return false;
        }
    } else {
        error_log("SendPhoto HTTP error: $httpCode, Response: $response");
        return false;
    }
}

// Функция редактирования сообщения
function editMessage($chatId, $messageId, $text, $replyMarkup = null) {
    global $botToken;
    
    error_log("editMessage called: chatId=$chatId, messageId=$messageId, textLength=" . strlen($text));
    
    $url = "https://api.telegram.org/bot{$botToken}/editMessageText";
    
    $data = [
        'chat_id' => $chatId,
        'message_id' => $messageId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("editMessage response: HTTP $httpCode, " . substr($response, 0, 200));
    
    return json_decode($response, true);
}

// Функция получения пользователя по Telegram ID
function getUserByTelegramId($telegramId) {
    global $pdo;
    
    try {
        error_log("getUserByTelegramId called for telegramId: " . $telegramId);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE telegram_id = ? AND status = 'active'");
        $stmt->execute([$telegramId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        error_log("getUserByTelegramId result: " . ($user ? "found user" : "user not found"));
        return $user;
    } catch (Exception $e) {
        error_log("getUserByTelegramId error: " . $e->getMessage());
        return null;
    }
}

// Функция получения расписания группы
function getGroupSchedule($groupName, $week = null) {
    global $pdo;
    
    try {
        error_log("getGroupSchedule called for group: " . $groupName . ", week: " . ($week ?? 'all'));
        
        if ($week) {
            $stmt = $pdo->prepare("
                SELECT * FROM schedule 
                WHERE group_name = ? AND week_number = ?
                ORDER BY day_of_week, lesson_number
            ");
            $stmt->execute([$groupName, $week]);
        } else {
            $stmt = $pdo->prepare("
                SELECT * FROM schedule 
                WHERE group_name = ?
                ORDER BY week_number, day_of_week, lesson_number
            ");
            $stmt->execute([$groupName]);
        }
        
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("getGroupSchedule found " . count($result) . " lessons");
        
        return $result;
    } catch (Exception $e) {
        error_log("getGroupSchedule error: " . $e->getMessage());
        return [];
    }
}

// Функция получения заказов пользователя
function getUserOrders($userId) {
    global $pdo;
    
    try {
        error_log("getUserOrders called with userId: " . $userId);
        
        // Получаем имя пользователя
        $stmt = $pdo->prepare("SELECT client_name FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            error_log("User not found with ID: " . $userId);
            return [];
        }
        
        $clientName = $user['client_name'];
        error_log("Found client name: " . $clientName);
        
        // Получаем заказы по имени клиента
        $stmt = $pdo->prepare("
            SELECT * FROM orders 
            WHERE client_name = ? AND status = 'active'
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$clientName]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found orders: " . count($orders));
        
        // Также получаем заявки пользователя
        $stmt = $pdo->prepare("
            SELECT * FROM order_requests 
            WHERE user_id = ? 
            ORDER BY created_at DESC 
            LIMIT 10
        ");
        $stmt->execute([$userId]);
        $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Found requests: " . count($requests));
        
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
        error_log("Returning " . count($result) . " total items");
        return $result;
    } catch (Exception $e) {
        error_log("Error getting user orders: " . $e->getMessage());
        return [];
    }
}


// Вспомогательные функции для безопасного HTML в Telegram
function tgEscape($text) {
    return htmlspecialchars($text ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tgTruncate($text, $limit) {
    if ($text === null) { return ''; }
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
        if (mb_strlen($text, 'UTF-8') > $limit) {
            return mb_substr($text, 0, $limit - 3, 'UTF-8') . '...';
        }
        return $text;
    }
    return strlen($text) > $limit ? substr($text, 0, $limit - 3) . '...' : $text;
}

// Функция генерации текстового расписания
function generateTextSchedule($schedule, $groupName) {
    error_log("generateTextSchedule called with " . count($schedule) . " lessons for group: " . $groupName);
    
    $response = "📊 <b>Расписание группы " . tgEscape($groupName) . "</b>\n\n";
    
    $dayNames = [
        1 => 'Пн', 2 => 'Вт', 3 => 'Ср', 4 => 'Чт', 5 => 'Пт', 6 => 'Сб'
    ];
    
    // Группируем по дням и неделям
    $weekSchedule = [];
    foreach ($schedule as $lesson) {
        $weekSchedule[$lesson['week_number']][$lesson['day_of_week']][] = $lesson;
    }
    
    error_log("Week schedule structure: " . json_encode(array_keys($weekSchedule)));
    
    // Показываем расписание по неделям (более компактно)
    for ($week = 1; $week <= 2; $week++) {
        if (isset($weekSchedule[$week])) {
            $response .= "📅 <b>{$week} неделя</b>\n";
            
            for ($day = 1; $day <= 6; $day++) {
                if (isset($weekSchedule[$week][$day])) {
                    $response .= "\n📆 <b>{$dayNames[$day]}</b>\n";
                    
                    foreach ($weekSchedule[$week][$day] as $lesson) {
                        // Компактный безопасный формат
                        $lessonNum = (string)($lesson['lesson_number'] ?? '');
                        $subjectRaw = $lesson['subject_name'] ?? '';
                        $subject = tgEscape(tgTruncate($subjectRaw, 40));
                        $room = tgEscape($lesson['room_number'] ?? '');
                        $teacher = tgEscape($lesson['teacher_name'] ?? '');

                        $response .= $lessonNum . "п • " . $subject . "\n";
                        $response .= "   🏢 " . $room . " • 👨‍🏫 " . $teacher . "\n";
                    }
                }
            }
            $response .= "\n";
        }
    }
    
    error_log("generateTextSchedule returning response of length: " . strlen($response));
    return $response;
}


// Обработка сообщений
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $from = $message['from'];
    $telegramId = $from['id'];
    
    // Проверяем, не заблокирован ли пользователь
    if (isTelegramIdBlocked($telegramId)) {
        sendMessage($chatId, "❌ <b>Доступ запрещен</b>\n\nВаш аккаунт заблокирован.");
        http_response_code(200);
        echo 'OK';
        exit();
    }
    
    // Получаем пользователя
    $user = getUserByTelegramId($telegramId);
    
    // Команда /start
    if ($text === '/start') {
        if (!$user) {
            $response = "👋 <b>Добро пожаловать!</b>\n\n";
            $response .= "Для полноценного использования бота необходимо привязать ваш Telegram аккаунт к аккаунту на сайте.\n\n";
            $response .= "Пока вы можете использовать только расписание\n\n";
            $response .= "📋 <b>Привязка Telegram:</b>\n";
            $response .= "1. Зайдите в приложение по кнопке ОТКРЫТЬ ниже\n";
            $response .= "2. Войдите в свой аккаунт\n";
            $response .= "3. В настройках профиля найдите раздел \"Telegram\"\n";
            $response .= "4. Нажмите \"Привязать Telegram\"\n\n";
            $response .= "После привязки вы сможете пользоваться всеми функциями бота! 🚀";
            
            // Кнопки для неавторизованных пользователей
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📅 Расписание', 'callback_data' => 'schedule_guest'],
                        ['text' => '❓ Помощь', 'callback_data' => 'help_guest']
                    ]
                ]
            ];
            
            sendMessage($chatId, $response, $keyboard);
        } else {
            // Главное меню
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📅 Расписание', 'callback_data' => 'schedule_menu'],
                        ['text' => '📋 Мои заказы', 'callback_data' => 'my_orders']
                    ],
                    [
                        ['text' => '⚙️ Настройки', 'callback_data' => 'settings_menu'],
                        ['text' => '❓ Помощь', 'callback_data' => 'help_menu']
                    ]
                ]
            ];
            
            $response = "👋 <b>Привет, {$user['client_name']}!</b>\n\n";
            $response .= "Выберите нужную функцию:";
            
            sendMessage($chatId, $response, $keyboard);
        }
    }
    
    // Команда /help
    elseif ($text === '/help') {
        $response = "❓ <b>Помощь</b>\n\n";
        $response .= "📋 <b>Доступные команды:</b>\n";
        $response .= "/start - Главное меню\n";
        $response .= "/help - Эта справка\n\n";
        $response .= "🔗 <b>Полезные ссылки:</b>\n";
        $response .= "🌐 Сайт: imsit.shop\n";
        $response .= "📢 Канал: @imsitshop\n";
        $response .= "👨‍💻 Техподдержка: @cowgivesmilk\n\n";
        $response .= "💡 <b>Совет:</b> Используйте кнопки меню для быстрого доступа к функциям!";
        
        sendMessage($chatId, $response);
    }
}

// Обработка callback запросов (нажатия на кнопки)
elseif (isset($update['callback_query'])) {
    $callback = $update['callback_query'];
    $chatId = $callback['message']['chat']['id'];
    $messageId = $callback['message']['message_id'];
    $data = $callback['data'];
    $telegramId = $callback['from']['id'];
    
    // Проверяем, не заблокирован ли пользователь
    if (isTelegramIdBlocked($telegramId)) {
        sendMessage($chatId, "❌ <b>Доступ запрещен</b>\n\nВаш аккаунт заблокирован.");
        http_response_code(200);
        echo 'OK';
        exit();
    }
    
    // Получаем пользователя
    $user = getUserByTelegramId($telegramId);
    
    // Обработка для гостевых пользователей (без привязки)
    if (!$user) {
        switch ($data) {
            case 'schedule_guest':
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📅 Сегодня', 'callback_data' => 'schedule_today_guest'],
                            ['text' => '📊 Неделя', 'callback_data' => 'schedule_week_guest']
                        ],
                        [
                            ['text' => '🔙 Назад', 'callback_data' => 'start_guest']
                        ]
                    ]
                ];
                
                $response = "📅 <b>Расписание</b>\n\n";
                $response .= "Выберите период для просмотра расписания:";
                
                editMessage($chatId, $messageId, $response, $keyboard);
                break;
                
            case 'schedule_today_guest':
                $currentDay = date('N'); // 1-7 (Пн-Вс)
                $currentWeek = (date('W') % 2 == 0) ? 1 : 2;
                
                // Показываем расписание для обеих групп
                $groups = ['Исип-05', 'Исип-06'];
                $response = "📅 <b>Расписание на сегодня</b>\n\n";
                
                foreach ($groups as $groupName) {
                    $schedule = getGroupSchedule($groupName, $currentWeek);
                    $todaySchedule = array_filter($schedule, function($lesson) use ($currentDay) {
                        return $lesson['day_of_week'] == $currentDay;
                    });
                    
                    $response .= "👥 <b>{$groupName}</b>\n";
                    if (empty($todaySchedule)) {
                        $response .= "🎉 Нет пар на сегодня\n\n";
                    } else {
                        foreach ($todaySchedule as $lesson) {
                            $response .= "🕐 <b>{$lesson['lesson_number']} пара</b> ({$lesson['start_time']}-{$lesson['end_time']})\n";
                            $response .= "📚 {$lesson['subject_name']}\n";
                            $response .= "🏢 {$lesson['room_number']}\n";
                            $response .= "👨‍🏫 {$lesson['teacher_name']}\n\n";
                        }
                    }
                }
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Назад к расписанию', 'callback_data' => 'schedule_guest']
                        ]
                    ]
                ];
                
                editMessage($chatId, $messageId, $response, $keyboard);
                break;
                
            case 'schedule_week_guest':
                $response = "📊 <b>Расписание на неделю</b>\n\n";
                $response .= "Для просмотра полного расписания на неделю необходимо привязать ваш аккаунт.\n\n";
                $response .= "🔗 <b>Как привязать:</b>\n";
                $response .= "1. Зайдите на сайт imsit.shop\n";
                $response .= "2. Войдите в свой аккаунт\n";
                $response .= "3. В настройках найдите \"Telegram\"\n";
                $response .= "4. Нажмите \"Привязать Telegram\"\n\n";
                $response .= "После привязки вы сможете получать красивые изображения расписания! 🎨";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Назад к расписанию', 'callback_data' => 'schedule_guest']
                        ]
                    ]
                ];
                
                editMessage($chatId, $messageId, $response, $keyboard);
                break;
                
            case 'help_guest':
                $response = "❓ <b>Помощь</b>\n\n";
                $response .= "📋 <b>Доступные функции:</b>\n";
                $response .= "📅 <b>Расписание</b> - просмотр расписания групп\n";
                $response .= "❓ <b>Помощь</b> - эта справка\n\n";
                $response .= "🔗 <b>Полезные ссылки:</b>\n";
                $response .= "🌐 Сайт: imsit.shop\n";
                $response .= "📢 Канал: @imsitshop\n";
                $response .= "👨‍💻 Техподдержка: @cowgivesmilk\n\n";
                $response .= "💡 <b>Совет:</b> Привяжите аккаунт для доступа ко всем функциям!";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '🔙 Назад', 'callback_data' => 'start_guest']
                        ]
                    ]
                ];
                
                editMessage($chatId, $messageId, $response, $keyboard);
                break;
                
            case 'start_guest':
                $response = "👋 <b>Добро пожаловать!</b>\n\n";
                $response .= "Для полноценного использования бота необходимо привязать ваш Telegram аккаунт к аккаунту на сайте.\n\n";
                $response .= "Пока вы можете использовать только расписание\n\n";
                $response .= "📋 <b>Как это сделать:</b>\n";
                $response .= "1. Зайдите на сайт imsit.shop\n";
                $response .= "2. Войдите в свой аккаунт\n";
                $response .= "3. В настройках профиля найдите раздел \"Telegram\"\n";
                $response .= "4. Нажмите \"Привязать Telegram\"\n\n";
                $response .= "После привязки вы сможете пользоваться всеми функциями бота! 🚀";
                
                $keyboard = [
                    'inline_keyboard' => [
                        [
                            ['text' => '📅 Расписание', 'callback_data' => 'schedule_guest'],
                            ['text' => '❓ Помощь', 'callback_data' => 'help_guest']
                        ]
                    ]
                ];
                
                editMessage($chatId, $messageId, $response, $keyboard);
                break;
                
            default:
                $response = "❌ Для использования этой функции необходимо привязать ваш Telegram аккаунт к аккаунту на сайте.";
                editMessage($chatId, $messageId, $response);
                break;
        }
        
        // Отвечаем на callback query для гостей
        $answerUrl = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";
        $answerData = ['callback_query_id' => $callback['id']];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $answerUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($answerData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        curl_exec($ch);
        curl_close($ch);
        
        return;
    }
    
    // Логируем callback data
    error_log("Processing callback data: " . $data);
    error_log("User data: " . json_encode($user));
    
    switch ($data) {
        case 'schedule_menu':
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📅 Сегодня', 'callback_data' => 'schedule_today'],
                        ['text' => '📊 Неделя', 'callback_data' => 'schedule_week']
                    ],
                    [
                        ['text' => '🔙 Назад', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            $response = "📅 <b>Расписание</b>\n\n";
            $response .= "Выберите период:";
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
            
        case 'schedule_today':
            $groupName = $user['group'] ?? 'Исип-06';
            $currentDay = date('N'); // 1-7 (Пн-Вс)
            $currentWeek = (date('W') % 2 == 0) ? 1 : 2;
            
            $schedule = getGroupSchedule($groupName, $currentWeek);
            $todaySchedule = array_filter($schedule, function($lesson) use ($currentDay) {
                return $lesson['day_of_week'] == $currentDay;
            });
            
            if (empty($todaySchedule)) {
                $response = "📅 <b>Расписание на сегодня</b>\n\n";
                $response .= "🎉 У вас нет пар на сегодня!";
            } else {
                $response = "📅 <b>Расписание на сегодня</b>\n\n";
                foreach ($todaySchedule as $lesson) {
                    $response .= "🕐 <b>{$lesson['lesson_number']} пара</b> ({$lesson['start_time']}-{$lesson['end_time']})\n";
                    $response .= "📚 {$lesson['subject_name']}\n";
                    $response .= "🏢 {$lesson['room_number']}\n";
                    $response .= "👨‍🏫 {$lesson['teacher_name']}\n\n";
                }
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Назад к расписанию', 'callback_data' => 'schedule_menu']
                    ]
                ]
            ];
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
            
        case 'schedule_week':
            error_log("Processing schedule_week callback");
            $groupName = $user['group'] ?? 'Исип-06';
            error_log("Group name: " . $groupName);
            
            // Отправляем сообщение с предложением посмотреть в приложении
            $response = "📊 <b>Расписание на неделю</b>\n\n";
            $response .= "Для просмотра полного расписания на неделю используйте приложение:\n";
            $response .= "🌐 imsit.shop\n\n";
            $response .= "Или выберите \"Сегодня\" для просмотра расписания на текущий день.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Назад к расписанию', 'callback_data' => 'schedule_menu']
                    ]
                ]
            ];
            
            error_log("About to edit message with response length: " . strlen($response));
            $editResult = editMessage($chatId, $messageId, $response, $keyboard);
            error_log("Edit message result: " . json_encode($editResult));
            break;
            
        case 'my_orders':
            // Логируем для отладки
            error_log("Processing my_orders for user ID: " . $user['id']);
            
            $orders = getUserOrders($user['id']);
            
            // Логируем результат
            error_log("Found orders: " . count($orders));
            
            if (empty($orders)) {
                $response = "📋 <b>Мои заказы</b>\n\n";
                $response .= "У вас пока нет заказов или заявок.";
            } else {
                $response = "📋 <b>Мои заказы и заявки</b>\n\n";
                foreach ($orders as $item) {
                    $status = '';
                    $icon = '';
                    $type = '';
                    
                    switch ($item['status']) {
                        case 'pending': 
                            $status = '⏳ Ожидает рассмотрения'; 
                            $icon = '📝';
                            $type = 'Заявка';
                            break;
                        case 'in_progress': 
                            $status = '🔄 В работе'; 
                            $icon = '⚙️';
                            $type = 'Заявка';
                            break;
                        case 'completed': 
                            $status = '✅ Завершен'; 
                            $icon = '📦';
                            $type = 'Заказ';
                            break;
                        case 'cancelled': 
                            $status = '❌ Отклонен'; 
                            $icon = '❌';
                            $type = 'Заявка';
                            break;
                        default: 
                            $status = '❓ Неизвестно'; 
                            $icon = '❓';
                            $type = 'Неизвестно';
                    }
                    
                    $response .= "{$icon} <b>{$type} #{$item['id']}</b>\n";
                    $response .= "📝 " . (strlen($item['description']) > 50 ? substr($item['description'], 0, 50) . '...' : $item['description']) . "\n";
                    $response .= "📊 Статус: {$status}\n";
                    
                    if (isset($item['total_price']) && $item['total_price'] > 0) {
                        $response .= "💰 Цена: {$item['total_price']} руб.\n";
                    } elseif (isset($item['approved_price']) && $item['approved_price'] > 0) {
                        $response .= "💰 Цена: {$item['approved_price']} руб.\n";
                    }
                    
                    $response .= "📅 Создан: " . date('d.m.Y H:i', strtotime($item['created_at'])) . "\n\n";
                }
            }
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Назад', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
            
        case 'settings_menu':
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '👥 Выбрать группу', 'callback_data' => 'select_group']
                    ],
                    [
                        ['text' => '🔙 Назад', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            $currentGroup = $user['group'] ?? 'Не выбрана';
            $response = "⚙️ <b>Настройки</b>\n\n";
            $response .= "👥 <b>Текущая группа:</b> {$currentGroup}\n\n";
            $response .= "Выберите действие:";
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
            
        case 'select_group':
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => 'Исип-05', 'callback_data' => 'set_group_Исип-05'],
                        ['text' => 'Исип-06', 'callback_data' => 'set_group_Исип-06']
                    ],
                    [
                        ['text' => '🔙 Назад к настройкам', 'callback_data' => 'settings_menu']
                    ]
                ]
            ];
            
            $response = "👥 <b>Выбор группы</b>\n\n";
            $response .= "Выберите вашу группу:";
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
            
        case 'help_menu':
            $response = "❓ <b>Помощь</b>\n\n";
            $response .= "📋 <b>Доступные функции:</b>\n";
            $response .= "📅 <b>Расписание</b> - просмотр расписания вашей группы\n";
            $response .= "📋 <b>Мои заказы</b> - список ваших заказов и их статусы\n";
            $response .= "⚙️ <b>Настройки</b> - выбор группы по умолчанию\n\n";
            $response .= "🔗 <b>Полезные ссылки:</b>\n";
            $response .= "🌐 Сайт: imsit.shop\n";
            $response .= "📢 Канал: @imsitshop\n";
            $response .= "👨‍💻 Техподдержка: @cowgivesmilk\n\n";
            $response .= "💡 <b>Совет:</b> Используйте кнопки для быстрого доступа к функциям!";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Назад', 'callback_data' => 'main_menu']
                    ]
                ]
            ];
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
            
        case 'main_menu':
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '📅 Расписание', 'callback_data' => 'schedule_menu'],
                        ['text' => '📋 Мои заказы', 'callback_data' => 'my_orders']
                    ],
                    [
                        ['text' => '⚙️ Настройки', 'callback_data' => 'settings_menu'],
                        ['text' => '❓ Помощь', 'callback_data' => 'help_menu']
                    ]
                ]
            ];
            
            $response = "👋 <b>Привет, {$user['client_name']}!</b>\n\n";
            $response .= "Выберите нужную функцию:";
            
            editMessage($chatId, $messageId, $response, $keyboard);
            break;
    }
    
    // Обработка выбора группы
    if (strpos($data, 'set_group_') === 0) {
        $groupName = str_replace('set_group_', '', $data);
        
        try {
            $stmt = $pdo->prepare("UPDATE users SET `group` = ? WHERE telegram_id = ?");
            $stmt->execute([$groupName, $telegramId]);
            
            $response = "✅ <b>Группа изменена!</b>\n\n";
            $response .= "👥 Новая группа: <b>{$groupName}</b>\n\n";
            $response .= "Теперь вы будете получать расписание для этой группы.";
            
            $keyboard = [
                'inline_keyboard' => [
                    [
                        ['text' => '🔙 Назад к настройкам', 'callback_data' => 'settings_menu']
                    ]
                ]
            ];
            
            editMessage($chatId, $messageId, $response, $keyboard);
        } catch (Exception $e) {
            $response = "❌ Ошибка изменения группы. Попробуйте позже.";
            editMessage($chatId, $messageId, $response);
        }
    }
    
    // Логируем завершение обработки
    error_log("Finished processing callback: " . $data);
    
    // Отвечаем на callback query
    $answerUrl = "https://api.telegram.org/bot{$botToken}/answerCallbackQuery";
    $answerData = ['callback_query_id' => $callback['id']];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $answerUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($answerData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    curl_exec($ch);
    curl_close($ch);
}

// Логирование для отладки
error_log("Telegram webhook received: " . json_encode($update));
error_log("Finished processing webhook");

// Простая проверка - записываем в файл
file_put_contents(dirname(__DIR__) . '/telegram_webhook_log.txt', date('Y-m-d H:i:s') . " - " . json_encode($update) . "\n", FILE_APPEND);
?>
