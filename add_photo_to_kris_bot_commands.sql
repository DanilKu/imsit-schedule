-- Добавление поля для хранения пути к фото в командах KrisBot
ALTER TABLE `kris_bot_commands` 
ADD COLUMN `photo_path` varchar(255) DEFAULT NULL COMMENT 'Путь к фото для команды' AFTER `response_text`;

