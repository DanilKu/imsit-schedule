<?php
// FUFLIK BOT
// Bot for FUFLIK club in Brawl Stars

// --- CONFIG ---
// Токен Telegram бота 
$BOT_TOKEN = getenv('BRAWLSTARS_BOT_TOKEN') ?: '8244381929:AAHkGBXY4R55jox_cNWBXf3x4xD6pXMNs_w';

// API ключ Brawl Stars 
$BRAWL_STARS_API_KEY = getenv('BRAWL_STARS_API_KEY') ?: 'YOUR_API_KEY_HERE';

// Тег клуба 
$CLUB_TAG = getenv('BRAWL_STARS_CLUB_TAG') ?: 'YOUR_CLUB_TAG_HERE';

// Базовый URL API Brawl Stars
$BRAWL_STARS_API_URL = 'https://api.brawlstars.com/v1';

// --- TELEGRAM API FUNCTIONS ---
function tg_api($method, $payload) {
    global $BOT_TOKEN;
    $url = "https://api.telegram.org/bot{$BOT_TOKEN}/{$method}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload, JSON_UNESCAPED_UNICODE));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("Telegram API error: HTTP {$httpCode}, Response: " . substr($resp ?? '', 0, 200));
    }
    
    return $resp ? json_decode($resp, true) : null;
}

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

function sendPhoto($chatId, $photo, $caption = null, $replyMarkup = null) {
    $data = [
        'chat_id' => $chatId,
        'photo' => $photo
    ];
    
    if ($caption) {
        $data['caption'] = $caption;
        $data['parse_mode'] = 'HTML';
    }
    
    if ($replyMarkup) {
        $data['reply_markup'] = $replyMarkup;
    }
    
    return tg_api('sendPhoto', $data);
}

// --- BRAWL STARS API FUNCTIONS ---
function getClubInfo($clubTag) {
    global $BRAWL_STARS_API_KEY, $BRAWL_STARS_API_URL;
    
    // Убираем символ # если есть
    $clubTag = ltrim($clubTag, '#');
    
    $url = "{$BRAWL_STARS_API_URL}/clubs/%23{$clubTag}";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $BRAWL_STARS_API_KEY,
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $resp) {
        return json_decode($resp, true);
    } else {
        error_log("Brawl Stars API error: HTTP {$httpCode}, Response: " . substr($resp ?? '', 0, 200));
        return null;
    }
}

function formatClubStats($clubData) {
    if (!$clubData) {
        return "❌ <b>Ошибка</b>\n\nНе удалось получить данные о клубе.";
    }
    
    $name = htmlspecialchars($clubData['name'] ?? 'Неизвестно');
    $tag = htmlspecialchars($clubData['tag'] ?? '');
    $trophies = number_format($clubData['trophies'] ?? 0, 0, ',', ' ');
    $requiredTrophies = number_format($clubData['requiredTrophies'] ?? 0, 0, ',', ' ');
    $type = $clubData['type'] ?? 'unknown';
    $description = htmlspecialchars($clubData['description'] ?? 'Нет описания');
    
    // Тип клуба на русском
    $typeNames = [
        'open' => 'Открытый',
        'inviteOnly' => 'По приглашению',
        'closed' => 'Закрытый'
    ];
    $typeName = $typeNames[$type] ?? $type;
    
    // Информация о членах
    $members = $clubData['members'] ?? [];
    $memberCount = count($members);
    $maxMembers = 30; // Максимум участников в клубе
    
    // Сортируем участников по трофеям (от большего к меньшему)
    usort($members, function($a, $b) {
        return ($b['trophies'] ?? 0) - ($a['trophies'] ?? 0);
    });
    
    $text = "🏆 <b>Статистика клуба</b>\n\n";
    $text .= "📛 <b>Название:</b> {$name}\n";
    $text .= "🏷️ <b>Тег:</b> #{$tag}\n";
    $text .= "💎 <b>Трофеи клуба:</b> {$trophies}\n";
    $text .= "📊 <b>Минимум трофеев:</b> {$requiredTrophies}\n";
    $text .= "👥 <b>Участников:</b> {$memberCount}/{$maxMembers}\n";
    $text .= "🔒 <b>Тип:</b> {$typeName}\n";
    
    if ($description !== 'Нет описания') {
        $text .= "📝 <b>Описание:</b> {$description}\n";
    }
    
    $text .= "\n━━━━━━━━━━━━━━━━━━━━\n";
    $text .= "👥 <b>Участники клуба:</b>\n\n";
    
    if (empty($members)) {
        $text .= "❌ Участники не найдены";
    } else {
        // Показываем топ-20 участников (чтобы не превысить лимит сообщения)
        $topMembers = array_slice($members, 0, 20);
        
        foreach ($topMembers as $index => $member) {
            $memberName = htmlspecialchars($member['name'] ?? 'Неизвестно');
            $memberTrophies = number_format($member['trophies'] ?? 0, 0, ',', ' ');
            $memberRole = $member['role'] ?? 'member';
            
            // Роль на русском
            $roleNames = [
                'president' => '👑 Президент',
                'vicePresident' => '⭐ Вице-президент',
                'senior' => '⭐ Старший',
                'member' => '👤 Участник'
            ];
            $roleName = $roleNames[$memberRole] ?? '👤 Участник';
            
            // Эмодзи для позиции
            $positionEmoji = '';
            if ($index === 0) {
                $positionEmoji = '🥇';
            } elseif ($index === 1) {
                $positionEmoji = '🥈';
            } elseif ($index === 2) {
                $positionEmoji = '🥉';
            } else {
                $positionEmoji = ($index + 1) . '.';
            }
            
            $text .= "{$positionEmoji} <b>{$memberName}</b> - {$memberTrophies} 🏆\n";
            
            // Показываем роль только для лидеров
            if ($memberRole === 'president' || $memberRole === 'vicePresident') {
                $text .= "   {$roleName}\n";
            }
        }
        
        if (count($members) > 20) {
            $text .= "\n... и еще " . (count($members) - 20) . " участников";
        }
    }
    
    $text .= "\n\n🕐 <b>Обновлено:</b> " . date('d.m.Y H:i');
    
    return $text;
}

function formatShortClubStats($clubData) {
    if (!$clubData) {
        return "❌ Не удалось получить данные о клубе.";
    }
    
    $name = htmlspecialchars($clubData['name'] ?? 'Неизвестно');
    $trophies = number_format($clubData['trophies'] ?? 0, 0, ',', ' ');
    $members = $clubData['members'] ?? [];
    $memberCount = count($members);
    
    $text = "🏆 <b>{$name}</b>\n";
    $text .= "💎 Трофеи: {$trophies}\n";
    $text .= "👥 Участников: {$memberCount}/30";
    
    return $text;
}

// --- MAIN WEBHOOK HANDLER ---
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
    $text = trim($message['text'] ?? '');
    $from = $message['from'];
    
    // Проверяем токен бота
    if ($BOT_TOKEN === 'YOUR_BOT_TOKEN_HERE') {
        sendMessage($chatId, "❌ <b>Ошибка конфигурации</b>\n\nТокен бота не настроен. Обратитесь к администратору.");
        http_response_code(200);
        echo 'OK';
        exit;
    }
    
    // Проверяем API ключ
    if ($BRAWL_STARS_API_KEY === 'YOUR_API_KEY_HERE') {
        sendMessage($chatId, "❌ <b>Ошибка конфигурации</b>\n\nAPI ключ Brawl Stars не настроен. Обратитесь к администратору.");
        http_response_code(200);
        echo 'OK';
        exit;
    }
    
    // Проверяем тег клуба
    if ($CLUB_TAG === 'YOUR_CLUB_TAG_HERE') {
        sendMessage($chatId, "❌ <b>Ошибка конфигурации</b>\n\nТег клуба не настроен. Обратитесь к администратору.");
        http_response_code(200);
        echo 'OK';
        exit;
    }
    
    // Команда /start
    if ($text === '/start' || strpos($text, '/start') === 0) {
        $keyboard = [
            'keyboard' => [
                [['text' => '🏆 Статистика клуба']],
                [['text' => '📊 Краткая статистика']],
                [['text' => '❓ Помощь']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        $response = "👋 <b>Привет!</b>\n\n";
        $response .= "Я бот для просмотра статистики клуба Brawl Stars!\n\n";
        $response .= "📋 <b>Доступные команды:</b>\n";
        $response .= "/start - Начать работу\n";
        $response .= "/club или /stats - Полная статистика клуба\n";
        $response .= "/short - Краткая статистика\n";
        $response .= "/help - Помощь\n\n";
        $response .= "💡 Используйте кнопки ниже для быстрого доступа.";
        
        sendMessage($chatId, $response, $keyboard);
    }
    
    // Команда /help
    elseif ($text === '/help' || $text === '❓ Помощь') {
        $keyboard = [
            'keyboard' => [
                [['text' => '🏆 Статистика клуба']],
                [['text' => '📊 Краткая статистика']],
                [['text' => '❓ Помощь']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        $response = "📚 <b>Помощь по боту</b>\n\n";
        $response .= "🤖 <b>Что я умею:</b>\n";
        $response .= "• Показывать статистику клуба Brawl Stars\n";
        $response .= "• Отображать список участников с трофеями\n";
        $response .= "• Показывать информацию о клубе\n\n";
        $response .= "🔧 <b>Команды:</b>\n";
        $response .= "/start - Начать работу\n";
        $response .= "/club или /stats - Полная статистика клуба\n";
        $response .= "/short - Краткая статистика\n";
        $response .= "/help - Эта справка\n\n";
        $response .= "💡 <b>Совет:</b> Используйте кнопки для быстрого доступа к функциям.";
        
        sendMessage($chatId, $response, $keyboard);
    }
    
    // Команда /club или /stats - полная статистика
    elseif ($text === '/club' || $text === '/stats' || $text === '🏆 Статистика клуба') {
        $keyboard = [
            'keyboard' => [
                [['text' => '🏆 Статистика клуба']],
                [['text' => '📊 Краткая статистика']],
                [['text' => '❓ Помощь']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        // Отправляем сообщение о загрузке
        $loadingMsg = sendMessage($chatId, "⏳ Загрузка статистики клуба...", $keyboard);
        $loadingMsgId = $loadingMsg['result']['message_id'] ?? null;
        
        // Получаем данные о клубе
        $clubData = getClubInfo($CLUB_TAG);
        
        if ($clubData) {
            $statsText = formatClubStats($clubData);
            
            // Если сообщение слишком длинное, разбиваем на части
            if (mb_strlen($statsText) > 4096) {
                // Разбиваем на части
                $parts = str_split($statsText, 4000);
                foreach ($parts as $index => $part) {
                    if ($index === 0 && $loadingMsgId) {
                        // Редактируем первое сообщение
                        tg_api('editMessageText', [
                            'chat_id' => $chatId,
                            'message_id' => $loadingMsgId,
                            'text' => $part,
                            'parse_mode' => 'HTML',
                            'reply_markup' => json_encode($keyboard)
                        ]);
                    } else {
                        sendMessage($chatId, $part, $keyboard);
                    }
                }
            } else {
                // Редактируем сообщение о загрузке
                if ($loadingMsgId) {
                    tg_api('editMessageText', [
                        'chat_id' => $chatId,
                        'message_id' => $loadingMsgId,
                        'text' => $statsText,
                        'parse_mode' => 'HTML',
                        'reply_markup' => json_encode($keyboard)
                    ]);
                } else {
                    sendMessage($chatId, $statsText, $keyboard);
                }
            }
        } else {
            $errorText = "❌ <b>Ошибка</b>\n\nНе удалось получить данные о клубе.\n\n";
            $errorText .= "Возможные причины:\n";
            $errorText .= "• Неверный тег клуба\n";
            $errorText .= "• Проблемы с API Brawl Stars\n";
            $errorText .= "• Неверный API ключ\n\n";
            $errorText .= "Попробуйте позже или обратитесь к администратору.";
            
            if ($loadingMsgId) {
                tg_api('editMessageText', [
                    'chat_id' => $chatId,
                    'message_id' => $loadingMsgId,
                    'text' => $errorText,
                    'parse_mode' => 'HTML',
                    'reply_markup' => json_encode($keyboard)
                ]);
            } else {
                sendMessage($chatId, $errorText, $keyboard);
            }
        }
    }
    
    // Команда /short - краткая статистика
    elseif ($text === '/short' || $text === '📊 Краткая статистика') {
        $keyboard = [
            'keyboard' => [
                [['text' => '🏆 Статистика клуба']],
                [['text' => '📊 Краткая статистика']],
                [['text' => '❓ Помощь']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        $clubData = getClubInfo($CLUB_TAG);
        
        if ($clubData) {
            $statsText = formatShortClubStats($clubData);
            sendMessage($chatId, $statsText, $keyboard);
        } else {
            sendMessage($chatId, "❌ Не удалось получить данные о клубе.", $keyboard);
        }
    }
    
    // Неизвестная команда
    else {
        $keyboard = [
            'keyboard' => [
                [['text' => '🏆 Статистика клуба']],
                [['text' => '📊 Краткая статистика']],
                [['text' => '❓ Помощь']]
            ],
            'resize_keyboard' => true,
            'one_time_keyboard' => false
        ];
        
        $response = "❓ <b>Неизвестная команда</b>\n\n";
        $response .= "Используйте /help для просмотра доступных команд.";
        
        sendMessage($chatId, $response, $keyboard);
    }
}

// Обрабатываем callback_query (нажатия на inline-кнопки)
if (isset($update['callback_query'])) {
    $callbackQuery = $update['callback_query'];
    $chatId = $callbackQuery['message']['chat']['id'];
    $data = $callbackQuery['data'];
    $queryId = $callbackQuery['id'];
    
    // Отвечаем на callback
    tg_api('answerCallbackQuery', ['callback_query_id' => $queryId]);
    
    // Здесь можно добавить обработку callback данных
}

// Логируем обновления для отладки (опционально)
// error_log("BrawlStars bot webhook received: " . $input);

http_response_code(200);
echo 'OK';
?>

