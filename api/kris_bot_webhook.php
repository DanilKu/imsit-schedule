<?php
// Webhook для KrisBot
require_once dirname(__DIR__) . '/config/database.php';
require_once dirname(__DIR__) . '/config/blocked_telegram_ids.php';

// Токен бота
$BOT_TOKEN = '7663426513:AAHMco1BuG3dUKwks3lNCfT6NFlIMqOB6ck';

// Функция для отправки запросов к Telegram API
function tg_api($method, $payload) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $resp = curl_exec($ch);
    curl_close($ch);
    return $resp ? json_decode($resp, true) : null;
}

// Функция для отправки сообщения
function sendMessage($chatId, $text, $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'text' => $text,
        'parse_mode' => 'HTML',
        'disable_web_page_preview' => true
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    return tg_api('sendMessage', $data);
}

// Функция для отправки фото
function sendPhoto($chatId, $photoPath, $caption = '', $replyMarkup = null) {
    global $BOT_TOKEN;
    
    $fullPath = dirname(__DIR__) . '/' . $photoPath;
    
    if (!file_exists($fullPath)) {
        error_log("Photo file not found: $fullPath");
        // Если фото не найдено, отправляем только текст
        return sendMessage($chatId, $caption ?: 'Фото не найдено', $replyMarkup);
    }
    
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/sendPhoto";
    
    $data = [
        'chat_id' => $chatId,
        'photo' => new CURLFile($fullPath),
        'caption' => $caption,
        'parse_mode' => 'HTML'
    ];
    
    if ($replyMarkup) {
        $data['reply_markup'] = json_encode($replyMarkup);
    }
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200) {
        return json_decode($response, true);
    } else {
        error_log("SendPhoto error: HTTP $httpCode, Response: " . substr($response, 0, 200));
        // Если отправка фото не удалась, отправляем только текст
        return sendMessage($chatId, $caption ?: 'Ошибка отправки фото', $replyMarkup);
    }
}

// Функция для получения всех активных команд из БД
function getActiveCommands($pdo) {
    try {
        $stmt = $pdo->query("SELECT command_text, command_name, description FROM kris_bot_commands WHERE is_active = 1 ORDER BY command_name");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting commands: " . $e->getMessage());
        return [];
    }
}

// Функция для получения команды по тексту
function getCommandByText($pdo, $commandText) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM kris_bot_commands WHERE command_text = ? AND is_active = 1");
        $stmt->execute([$commandText]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error getting command: " . $e->getMessage());
        return null;
    }
}

// Функция для получения реальной погоды в Краснодаре
function getWeatherKrasnodar() {
    $city = 'Krasnodar';
    
    // Пробуем несколько вариантов URL
    $urls = [
        "https://wttr.in/{$city}?format=j1&lang=ru",
        "https://wttr.in/{$city}?format=j1",
        "http://wttr.in/{$city}?format=j1&lang=ru"
    ];
    
    foreach ($urls as $url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
                'Accept-Language: ru-RU,ru;q=0.9'
            ]
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);
        
        // Логируем ошибки для отладки
        if ($curlError) {
            error_log("Weather API curl error: " . $curlError);
        }
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['current_condition'][0])) {
                $current = $data['current_condition'][0];
                $location = $data['nearest_area'][0]['areaName'][0]['value'] ?? 'Краснодар';
                
                // Получаем температуру
                $temp = $current['temp_C'] ?? 'N/A';
                $feelsLike = $current['FeelsLikeC'] ?? 'N/A';
                
                // Описание погоды
                $desc = '';
                if (isset($current['lang_ru'][0]['value'])) {
                    $desc = $current['lang_ru'][0]['value'];
                } elseif (isset($current['weatherDesc'][0]['value'])) {
                    $desc = $current['weatherDesc'][0]['value'];
                } else {
                    $desc = 'Не указано';
                }
                
                // Влажность
                $humidity = $current['humidity'] ?? 'N/A';
                
                // Ветер
                $windSpeed = $current['windspeedKmph'] ?? 'N/A';
                $windDir = $current['winddir16Point'] ?? '';
                
                // Давление
                $pressure = $current['pressure'] ?? 'N/A';
                
                // Видимость
                $visibility = $current['visibility'] ?? 'N/A';
                
                // Эмодзи в зависимости от погоды
                $emoji = '🌤️';
                $weatherCode = $current['weatherCode'] ?? '';
                if (in_array($weatherCode, ['113'])) {
                    $emoji = '☀️';
                } elseif (in_array($weatherCode, ['116', '119', '122'])) {
                    $emoji = '⛅';
                } elseif (in_array($weatherCode, ['143', '248', '260'])) {
                    $emoji = '🌫️';
                } elseif (in_array($weatherCode, ['176', '263', '266', '281', '284', '293', '296', '299', '302', '305', '308', '311', '311', '314', '353', '356', '359', '362', '365'])) {
                    $emoji = '🌧️';
                } elseif (in_array($weatherCode, ['179', '182', '185', '227', '230', '320', '323', '326', '329', '332', '335', '338', '350', '368', '371', '374', '377'])) {
                    $emoji = '❄️';
                } elseif (in_array($weatherCode, ['200', '386', '389'])) {
                    $emoji = '⛈️';
                }
                
                $responseText = "{$emoji} <b>Погода в {$location}</b>\n\n";
                $responseText .= "🌡️ <b>Температура:</b> {$temp}°C\n";
                $responseText .= "💨 <b>Ощущается как:</b> {$feelsLike}°C\n";
                $responseText .= "☁️ <b>Условия:</b> {$desc}\n\n";
                $responseText .= "💧 <b>Влажность:</b> {$humidity}%\n";
                $responseText .= "🌬️ <b>Ветер:</b> {$windSpeed} км/ч {$windDir}\n";
                $responseText .= "📊 <b>Давление:</b> {$pressure} мбар\n";
                $responseText .= "👁️ <b>Видимость:</b> {$visibility} км\n\n";
                $responseText .= "🕐 <b>Обновлено:</b> " . date('d.m.Y H:i');
                
                return $responseText;
            }
        }
        
        // Небольшая задержка перед следующей попыткой
        if ($url !== end($urls)) {
            usleep(500000); // 0.5 секунды
        }
    }
    
    // Если curl не сработал, пробуем через file_get_contents
    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            'method' => 'GET',
            'header' => [
                'Accept: application/json',
                'Accept-Language: ru-RU,ru;q=0.9'
            ]
        ]
    ]);
    
    try {
        $url = "https://wttr.in/{$city}?format=j1&lang=ru";
        $response = @file_get_contents($url, false, $context);
        
        if ($response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['current_condition'][0])) {
                $current = $data['current_condition'][0];
                $location = $data['nearest_area'][0]['areaName'][0]['value'] ?? 'Краснодар';
                
                $temp = $current['temp_C'] ?? 'N/A';
                $feelsLike = $current['FeelsLikeC'] ?? 'N/A';
                
                $desc = '';
                if (isset($current['lang_ru'][0]['value'])) {
                    $desc = $current['lang_ru'][0]['value'];
                } elseif (isset($current['weatherDesc'][0]['value'])) {
                    $desc = $current['weatherDesc'][0]['value'];
                } else {
                    $desc = 'Не указано';
                }
                
                $humidity = $current['humidity'] ?? 'N/A';
                $windSpeed = $current['windspeedKmph'] ?? 'N/A';
                $windDir = $current['winddir16Point'] ?? '';
                $pressure = $current['pressure'] ?? 'N/A';
                $visibility = $current['visibility'] ?? 'N/A';
                
                $emoji = '🌤️';
                $weatherCode = $current['weatherCode'] ?? '';
                if (in_array($weatherCode, ['113'])) {
                    $emoji = '☀️';
                } elseif (in_array($weatherCode, ['116', '119', '122'])) {
                    $emoji = '⛅';
                } elseif (in_array($weatherCode, ['143', '248', '260'])) {
                    $emoji = '🌫️';
                } elseif (in_array($weatherCode, ['176', '263', '266', '281', '284', '293', '296', '299', '302', '305', '308', '311', '311', '314', '353', '356', '359', '362', '365'])) {
                    $emoji = '🌧️';
                } elseif (in_array($weatherCode, ['179', '182', '185', '227', '230', '320', '323', '326', '329', '332', '335', '338', '350', '368', '371', '374', '377'])) {
                    $emoji = '❄️';
                } elseif (in_array($weatherCode, ['200', '386', '389'])) {
                    $emoji = '⛈️';
                }
                
                $responseText = "{$emoji} <b>Погода в {$location}</b>\n\n";
                $responseText .= "🌡️ <b>Температура:</b> {$temp}°C\n";
                $responseText .= "💨 <b>Ощущается как:</b> {$feelsLike}°C\n";
                $responseText .= "☁️ <b>Условия:</b> {$desc}\n\n";
                $responseText .= "💧 <b>Влажность:</b> {$humidity}%\n";
                $responseText .= "🌬️ <b>Ветер:</b> {$windSpeed} км/ч {$windDir}\n";
                $responseText .= "📊 <b>Давление:</b> {$pressure} мбар\n";
                $responseText .= "👁️ <b>Видимость:</b> {$visibility} км\n\n";
                $responseText .= "🕐 <b>Обновлено:</b> " . date('d.m.Y H:i');
                
                return $responseText;
            }
        }
    } catch (Exception $e) {
        error_log("Weather API file_get_contents error: " . $e->getMessage());
    }
    
    // Если все попытки не удались, логируем
    error_log("Weather API failed: HTTP {$httpCode}, Response: " . substr($response ?? '', 0, 200));
    
    return null;
}

// Функция для создания клавиатуры с командами
function createCommandsKeyboard($pdo) {
    $commands = getActiveCommands($pdo);
    $keyboard = [];
    $row = [];
    
    foreach ($commands as $index => $cmd) {
        $row[] = ['text' => $cmd['command_name']];
        // По 2 кнопки в ряд
        if (count($row) == 2 || $index == count($commands) - 1) {
            $keyboard[] = $row;
            $row = [];
        }
    }
    
    // Добавляем кнопку "Список команд"
    if (!empty($keyboard)) {
        $keyboard[] = [['text' => '📋 Список команд']];
    }
    
    return [
        'keyboard' => $keyboard,
        'resize_keyboard' => true,
        'one_time_keyboard' => false
    ];
}

// Получаем данные от Telegram
$input = file_get_contents('php://input');
$update = json_decode($input, true);

if (!$update) {
    http_response_code(400);
    exit('Invalid JSON');
}

// Обрабатываем сообщения
if (isset($update['message'])) {
    $message = $update['message'];
    $chatId = $message['chat']['id'];
    $text = $message['text'] ?? '';
    $from = $message['from'];
    $telegramId = $from['id'] ?? null;
    
    // Проверяем, не заблокирован ли пользователь
    if ($telegramId && isTelegramIdBlocked($telegramId)) {
        sendMessage($chatId, "❌ <b>Доступ запрещен</b>\n\nВаш аккаунт заблокирован.");
        http_response_code(200);
        echo 'OK';
        exit();
    }
    
    // Команда /start
    if ($text === '/start' || $text === '/start@KrisBot') {
        $keyboard = createCommandsKeyboard($pdo);
        
        $response = "👋 <b>Привет!</b>\n\n";
        $response .= "Я <b>KrisBot</b> - универсальный бот для различных задач!\n\n";
        $response .= "📋 <b>Доступные команды:</b>\n";
        $response .= "/start - Начать работу\n";
        $response .= "/help - Помощь\n";
        $response .= "/list - Список всех команд\n\n";
        $response .= "💡 Используйте кнопки ниже для быстрого доступа к командам.";
        
        sendMessage($chatId, $response, $keyboard);
    }
    
    // Команда /help
    elseif ($text === '/help' || $text === '/help@KrisBot') {
        $keyboard = createCommandsKeyboard($pdo);
        
        $response = "📚 <b>Помощь по KrisBot</b>\n\n";
        $response .= "🤖 <b>Что я умею:</b>\n";
        $response .= "• Выполнять различные команды\n";
        $response .= "• Отвечать на ваши вопросы\n";
        $response .= "• Предоставлять полезную информацию\n\n";
        $response .= "🔧 <b>Основные команды:</b>\n";
        $response .= "/start - Начать работу\n";
        $response .= "/help - Эта справка\n";
        $response .= "/list - Список всех доступных команд\n\n";
        $response .= "💡 Используйте кнопки ниже для быстрого доступа.";
        
        sendMessage($chatId, $response, $keyboard);
    }
    
    // Команда /list
    elseif ($text === '/list' || $text === '/list@KrisBot') {
        $commands = getActiveCommands($pdo);
        $keyboard = createCommandsKeyboard($pdo);
        
        $response = "📋 <b>Список доступных команд:</b>\n\n";
        
        if (empty($commands)) {
            $response .= "❌ Команды пока не добавлены.\n\n";
            $response .= "💡 Администратор может добавить команды через админ-панель.";
        } else {
            foreach ($commands as $cmd) {
                $response .= "• <b>{$cmd['command_text']}</b>";
                if ($cmd['description']) {
                    $response .= " - {$cmd['description']}";
                }
                $response .= "\n";
            }
        }
        
        sendMessage($chatId, $response, $keyboard);
    }
    
    // Специальная обработка команды /weather - получаем реальную погоду
    elseif ($text === '/weather' || $text === '/weather@KrisBot') {
        $keyboard = createCommandsKeyboard($pdo);
        $weatherText = getWeatherKrasnodar();
        
        if ($weatherText) {
            sendMessage($chatId, $weatherText, $keyboard);
        } else {
            $response = "❌ <b>Ошибка получения погоды</b>\n\n";
            $response .= "Не удалось получить данные о погоде. Попробуйте позже.";
            sendMessage($chatId, $response, $keyboard);
        }
    }
    
    // Обработка динамических команд из БД
    else {
        $command = getCommandByText($pdo, $text);
        
        // Специальная обработка для команды /weather из БД
        if ($command && $command['command_text'] === '/weather') {
            $keyboard = createCommandsKeyboard($pdo);
            $weatherText = getWeatherKrasnodar();
            
            if ($weatherText) {
                sendMessage($chatId, $weatherText, $keyboard);
            } else {
                // Если не удалось получить погоду, отправляем текст из БД
                sendMessage($chatId, $command['response_text'], $keyboard);
            }
        } elseif ($command) {
            // Отправляем ответ команды (с фото, если есть)
            $keyboard = createCommandsKeyboard($pdo);
            if (!empty($command['photo_path'])) {
                sendPhoto($chatId, $command['photo_path'], $command['response_text'], $keyboard);
            } else {
                sendMessage($chatId, $command['response_text'], $keyboard);
            }
        } else {
            // Проверяем, не нажата ли кнопка с названием команды
            $commands = getActiveCommands($pdo);
            $found = false;
            
            foreach ($commands as $cmd) {
                if ($text === $cmd['command_name']) {
                    $command = getCommandByText($pdo, $cmd['command_text']);
                    if ($command) {
                        // Специальная обработка для кнопки "Погода"
                        if ($command['command_text'] === '/weather') {
                            $weatherText = getWeatherKrasnodar();
                            if ($weatherText) {
                                sendMessage($chatId, $weatherText, createCommandsKeyboard($pdo));
                            } else {
                                sendMessage($chatId, $command['response_text'], createCommandsKeyboard($pdo));
                            }
                        } else {
                            // Отправляем ответ команды (с фото, если есть)
                            $keyboard = createCommandsKeyboard($pdo);
                            if (!empty($command['photo_path'])) {
                                sendPhoto($chatId, $command['photo_path'], $command['response_text'], $keyboard);
                            } else {
                                sendMessage($chatId, $command['response_text'], $keyboard);
                            }
                        }
                        $found = true;
                        break;
                    }
                }
            }
            
            // Если команда не найдена
            if (!$found) {
                $keyboard = createCommandsKeyboard($pdo);
                $response = "❓ <b>Неизвестная команда</b>\n\n";
                $response .= "Используйте /list для просмотра всех доступных команд.\n";
                $response .= "Или /help для получения справки.";
                
                sendMessage($chatId, $response, $keyboard);
            }
        }
    }
}

// Обрабатываем callback_query (нажатия на inline-кнопки, если будут добавлены)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];
    $queryId = $callbackQuery['id'];
    $telegramId = $callbackQuery['from']['id'] ?? null;
    
    // Проверяем, не заблокирован ли пользователь
    if ($telegramId && isTelegramIdBlocked($telegramId)) {
        sendMessage($chatId, "❌ <b>Доступ запрещен</b>\n\nВаш аккаунт заблокирован.");
        http_response_code(200);
        echo 'OK';
        exit();
    }
    
    // Отвечаем на callback
    tg_api('answerCallbackQuery', ['callback_query_id' => $queryId]);
    
    // Здесь можно добавить обработку callback данных
}

// Логируем обновления для отладки
error_log("KrisBot webhook received: " . $input);

http_response_code(200);
echo 'OK';
?>

