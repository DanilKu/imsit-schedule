<?php
// Тест новой генерации изображения расписания
require_once 'config/database.php';

// Подключаем отдельные функции для тестирования
require_once 'test_schedule_functions.php';

// Устанавливаем заголовок для HTML
header('Content-Type: text/html; charset=utf-8');

echo "<h1>Тест новой генерации изображения расписания</h1>";

// Проверяем GD библиотеку
if (!extension_loaded('gd')) {
    echo "❌ GD библиотека не загружена<br>";
    exit;
} else {
    echo "✅ GD библиотека загружена<br>";
}

// Проверяем шрифт
$fontPath = __DIR__ . '/assets/fonts/DejaVuSans.ttf';
echo "🔍 Проверяем путь: $fontPath<br>";
echo "🔍 Текущая директория: " . __DIR__ . "<br>";
echo "🔍 Содержимое папки assets: " . (is_dir(__DIR__ . '/assets') ? 'существует' : 'не существует') . "<br>";
echo "🔍 Содержимое папки assets/fonts: " . (is_dir(__DIR__ . '/assets/fonts') ? 'существует' : 'не существует') . "<br>";

if (!file_exists($fontPath)) {
    echo "❌ Шрифт не найден: $fontPath<br>";
    exit;
} else {
    echo "✅ Шрифт найден: $fontPath<br>";
}

// Получаем расписание
try {
    $stmt = $pdo->prepare("SELECT * FROM schedule_all WHERE group_name = '22-СПО-ИСиП-06' ORDER BY week_number, day_of_week, lesson_number LIMIT 50");
    $stmt->execute();
    $schedule_all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✅ Найдено пар: " . count($schedule_all) . "<br>";
    
    if (empty($schedule)) {
        echo "❌ Расписание пустое<br>";
        exit;
    }
    
    // Тестируем генерацию изображения
    echo "<h2>Генерация изображения...</h2>";
    $imagePath = generateScheduleImage($schedule_all, '22-СПО-ИСиП-06');
    
    if ($imagePath && file_exists($imagePath)) {
        echo "✅ Изображение сгенерировано: $imagePath<br>";
        echo "<img src='temp/" . basename($imagePath) . "' style='max-width: 100%; border: 1px solid #ccc;'><br>";
        
        // Удаляем тестовый файл
        unlink($imagePath);
        echo "✅ Тестовый файл удален<br>";
    } else {
        echo "❌ Ошибка генерации изображения<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
}

echo "<h2>Тест завершен</h2>";
?>
