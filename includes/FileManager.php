<?php
require_once 'config/database.php';
require_once 'config/notifications_local.php';

class FileManager {
    private $pdo;
    private $uploadDir = 'uploads/';
    private $trashDir = 'trash/';

    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->createDirectories();
    }

    // Создание необходимых директорий
    private function createDirectories() {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
        if (!is_dir($this->trashDir)) {
            mkdir($this->trashDir, 0755, true);
        }
    }

    // Загрузка файла
    public function uploadFile($file, $orderId, $stageId = null) {
        try {
            if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
                throw new Exception("Файл не был загружен");
            }

            $originalFilename = $file['name'];
            $fileSize = $file['size'];
            $fileType = $file['type'];

            // Генерация уникального имени файла
            $extension = pathinfo($originalFilename, PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filePath = $this->uploadDir . $filename;

            // Перемещение файла
            if (!move_uploaded_file($file['tmp_name'], $filePath)) {
                throw new Exception("Ошибка при сохранении файла");
            }

            // Сохранение информации в базу данных
            $sql = "INSERT INTO files (order_id, stage_id, filename, original_filename, file_path, file_size, file_type)
                    VALUES (:order_id, :stage_id, :filename, :original_filename, :file_path, :file_size, :file_type)";

            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([
                'order_id' => $orderId,
                'stage_id' => $stageId,
                'filename' => $filename,
                'original_filename' => $originalFilename,
                'file_path' => $filePath,
                'file_size' => $fileSize,
                'file_type' => $fileType
            ]);

            $fileId = $this->pdo->lastInsertId();



            return $fileId;
        } catch (Exception $e) {
            throw new Exception("Ошибка загрузки файла: " . $e->getMessage());
        }
    }

    // Получение файлов для заказа
    public function getByOrderId($orderId) {
        try {
            $sql = "SELECT * FROM files WHERE order_id = :order_id AND status = 'active' ORDER BY upload_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['order_id' => $orderId]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения файлов: " . $e->getMessage());
        }
    }

    // Получение файлов для этапа
    public function getByStageId($stageId) {
        try {
            $sql = "SELECT * FROM files WHERE stage_id = :stage_id AND status = 'active' ORDER BY upload_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['stage_id' => $stageId]);

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения файлов: " . $e->getMessage());
        }
    }

    // Удаление файла (мягкое удаление)
    public function deleteFile($fileId) {
        try {
            // Получаем информацию о файле
            $fileInfo = $this->getById($fileId);
            if (!$fileInfo) {
                throw new Exception("Файл не найден");
            }

            // Перемещаем файл в корзину
            $trashPath = $this->trashDir . $fileInfo['filename'];
            if (file_exists($fileInfo['file_path'])) {
                rename($fileInfo['file_path'], $trashPath);
            }

            // Обновляем статус в базе данных
            $sql = "UPDATE files SET status = 'deleted' WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $fileId]);



            return true;
        } catch (Exception $e) {
            throw new Exception("Ошибка удаления файла: " . $e->getMessage());
        }
    }

    // Получение файла по ID
    public function getById($fileId) {
        try {
            $sql = "SELECT * FROM files WHERE id = :id";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute(['id' => $fileId]);

            return $stmt->fetch();
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения файла: " . $e->getMessage());
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

    // Получение размера файла в читаемом формате
    public function formatFileSize($bytes) {
        $units = ['B', 'KB', 'MB', 'GB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= pow(1024, $pow);

        return round($bytes, 2) . ' ' . $units[$pow];
    }

    // Очистка корзины
    public function clearTrash() {
        try {
            $files = glob($this->trashDir . '*');
            foreach ($files as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }

            // Удаляем записи из базы данных
            $sql = "DELETE FROM files WHERE status = 'deleted'";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();



            return count($files);
        } catch (Exception $e) {
            throw new Exception("Ошибка очистки корзины: " . $e->getMessage());
        }
    }

    // Получение файлов в корзине
    public function getTrashFiles() {
        try {
            $sql = "SELECT f.*, o.client_name
                    FROM files f
                    JOIN orders o ON f.order_id = o.id
                    WHERE f.status = 'deleted'
                    ORDER BY f.upload_date DESC";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();

            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Ошибка получения файлов из корзины: " . $e->getMessage());
        }
    }
}