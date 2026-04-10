-- 任務封面圖 URL（選填，供會員填寫 modal 顯示）
USE `group_09`;

ALTER TABLE `tasks`
  ADD COLUMN `cover_image_url` VARCHAR(512) NULL DEFAULT NULL AFTER `description`;
