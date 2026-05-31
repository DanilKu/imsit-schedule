<?php
// Скрипт для принудительного обновления кеша
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Очистка кеша</h1>";

// Добавляем заголовки для предотвращения кеширования
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

echo "<p>Кеш принудительно обновлен!</p>";
echo "<p>Время: " . date('Y-m-d H:i:s') . "</p>";
echo "<p>Все пользователи получат обновленную версию страницы.</p>";

// Создаем файл с меткой времени для принудительного обновления
file_put_contents('cache_version.txt', time());

echo "<p>Метка версии обновлена: " . time() . "</p>";
echo "<p><a href='shedule2.php'>Перейти к расписанию</a></p>";
?>
