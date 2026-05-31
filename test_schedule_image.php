<?php
// Тест генерации изображения расписания

echo "<h2>Тест генерации изображения расписания</h2>";

// Проверяем GD библиотеку
if (!extension_loaded('gd')) {
    echo "<p>❌ GD библиотека не установлена!</p>";
    echo "<p>Установите GD библиотеку для работы с изображениями.</p>";
    exit;
}

echo "<p>✅ GD библиотека установлена</p>";

// Проверяем функции
$functions = ['imagecreate', 'imagecolorallocate', 'imagefill', 'imagestring', 'imagerectangle', 'imagepng', 'imagedestroy'];
foreach ($functions as $func) {
    if (function_exists($func)) {
        echo "<p>✅ Функция $func доступна</p>";
    } else {
        echo "<p>❌ Функция $func недоступна</p>";
    }
}

// Тестируем создание простого изображения
echo "<h3>Тест создания изображения</h3>";

$width = 400;
$height = 300;
$image = imagecreate($width, $height);

if (!$image) {
    echo "<p>❌ Не удалось создать изображение</p>";
    exit;
}

echo "<p>✅ Изображение создано</p>";

// Цвета
$bgColor = imagecolorallocate($image, 15, 23, 42);
$textColor = imagecolorallocate($image, 226, 232, 240);

if (!$bgColor || !$textColor) {
    echo "<p>❌ Не удалось выделить цвета</p>";
    imagedestroy($image);
    exit;
}

echo "<p>✅ Цвета выделены</p>";

// Заливаем фон
if (!imagefill($image, 0, 0, $bgColor)) {
    echo "<p>❌ Не удалось залить фон</p>";
    imagedestroy($image);
    exit;
}

echo "<p>✅ Фон залит</p>";

// Добавляем текст
if (!imagestring($image, 5, 50, 50, "Тест расписания", $textColor)) {
    echo "<p>❌ Не удалось добавить текст</p>";
    imagedestroy($image);
    exit;
}

echo "<p>✅ Текст добавлен</p>";

// Создаем папку temp
$tempDir = __DIR__ . '/temp';
if (!file_exists($tempDir)) {
    if (!mkdir($tempDir, 0755, true)) {
        echo "<p>❌ Не удалось создать папку temp</p>";
        imagedestroy($image);
        exit;
    }
    echo "<p>✅ Папка temp создана</p>";
} else {
    echo "<p>✅ Папка temp существует</p>";
}

// Сохраняем изображение
$filename = "test_schedule_" . time() . ".png";
$filepath = $tempDir . "/" . $filename;

if (!imagepng($image, $filepath)) {
    echo "<p>❌ Не удалось сохранить изображение</p>";
    imagedestroy($image);
    exit;
}

echo "<p>✅ Изображение сохранено: $filepath</p>";

// Проверяем размер файла
$fileSize = filesize($filepath);
echo "<p>📊 Размер файла: " . number_format($fileSize) . " байт</p>";

// Показываем изображение
echo "<h3>Результат:</h3>";
echo "<img src='temp/$filename' alt='Тестовое изображение' style='border: 1px solid #ccc; max-width: 100%;'>";

// Очищаем память
imagedestroy($image);

echo "<h3>Тест завершен!</h3>";
echo "<p>Если все тесты прошли успешно, то генерация изображений должна работать.</p>";
?>
