<?php
// Скрипт для генерации PNG иконок из SVG

// Проверяем наличие GD библиотеки
if (!extension_loaded('gd')) {
    die('GD библиотека не загружена');
}

// Функция для создания PNG из SVG (упрощенная версия)
function createIconFromSVG($size, $filename) {
    // Создаем изображение
    $image = imagecreatetruecolor($size, $size);
    
    // Включаем прозрачность
    imagealphablending($image, false);
    imagesavealpha($image, true);
    
    // Заливаем прозрачным фоном
    $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
    imagefill($image, 0, 0, $transparent);
    
    // Создаем градиенты и цвета
    $magenta = imagecolorallocate($image, 255, 0, 255);
    $purple = imagecolorallocate($image, 128, 0, 255);
    $cyan = imagecolorallocate($image, 0, 255, 255);
    $blue = imagecolorallocate($image, 0, 128, 255);
    $darkPurple = imagecolorallocate($image, 26, 0, 51);
    $darkBlue = imagecolorallocate($image, 0, 26, 51);
    
    // Рисуем большой ромб (повернутый квадрат)
    $centerX = $size / 2;
    $centerY = $size / 2;
    $diamondSize = $size * 0.4;
    
    // Создаем ромб с градиентом
    for ($i = 0; $i < $diamondSize; $i++) {
        $ratio = $i / $diamondSize;
        $r = (int)(255 * (1 - $ratio) + 128 * $ratio);
        $g = (int)(0 * (1 - $ratio) + 0 * $ratio);
        $b = (int)(255 * (1 - $ratio) + 255 * $ratio);
        $color = imagecolorallocate($image, $r, $g, $b);
        
        // Рисуем контур ромба
        $points = array(
            $centerX, $centerY - $i,
            $centerX + $i, $centerY,
            $centerX, $centerY + $i,
            $centerX - $i, $centerY
        );
        imagepolygon($image, $points, 4, $color);
    }
    
    // Рисуем внутренний темный ромб
    $innerSize = $diamondSize * 0.6;
    $innerPoints = array(
        $centerX, $centerY - $innerSize,
        $centerX + $innerSize, $centerY,
        $centerX, $centerY + $innerSize,
        $centerX - $innerSize, $centerY
    );
    imagefilledpolygon($image, $innerPoints, 4, $darkPurple);
    
    // Рисуем маленький квадрат
    $squareSize = $size * 0.15;
    $squareX = $centerX + $size * 0.15;
    $squareY = $centerY - $size * 0.15;
    
    // Создаем квадрат с градиентом
    for ($i = 0; $i < $squareSize; $i++) {
        $ratio = $i / $squareSize;
        $r = (int)(0 * (1 - $ratio) + 0 * $ratio);
        $g = (int)(255 * (1 - $ratio) + 128 * $ratio);
        $b = (int)(255 * (1 - $ratio) + 255 * $ratio);
        $color = imagecolorallocate($image, $r, $g, $b);
        
        imagerectangle($image, 
            $squareX - $i, $squareY - $i,
            $squareX + $squareSize + $i, $squareY + $squareSize + $i,
            $color);
    }
    
    // Внутренний темный квадрат
    $innerSquareSize = $squareSize * 0.7;
    $innerSquareX = $squareX + ($squareSize - $innerSquareSize) / 2;
    $innerSquareY = $squareY + ($squareSize - $innerSquareSize) / 2;
    imagefilledrectangle($image, 
        $innerSquareX, $innerSquareY,
        $innerSquareX + $innerSquareSize, $innerSquareY + $innerSquareSize,
        $darkBlue);
    
    // Сохраняем изображение
    imagepng($image, $filename);
    imagedestroy($image);
    
    return true;
}

// Генерируем иконки разных размеров
$sizes = [
    16 => 'favicon-16x16.png',
    32 => 'favicon-32x32.png',
    180 => 'apple-touch-icon.png'
];

foreach ($sizes as $size => $filename) {
    $path = "assets/icons/" . $filename;
    if (createIconFromSVG($size, $path)) {
        echo "✅ Создана иконка {$filename} ({$size}x{$size})\n";
    } else {
        echo "❌ Ошибка создания иконки {$filename}\n";
    }
}

echo "\n🎉 Все иконки успешно созданы!\n";
?>

