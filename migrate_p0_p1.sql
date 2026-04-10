-- P0/P1：提交審核、任務上下架與時間窗、名額、駁回通知表
-- 在既有 group_09 資料庫執行（備份後再跑）

USE `group_09`;

-- 任務：狀態、台灣時間視窗（存 DATETIME，應用程式以 Asia/Taipei 解讀）、核准名額上限
ALTER TABLE `tasks`
  ADD COLUMN `task_status` ENUM('published', 'ended') NOT NULL DEFAULT 'published' AFTER `category`,
  ADD COLUMN `starts_at` DATETIME NULL AFTER `task_status`,
  ADD COLUMN `ends_at` DATETIME NULL AFTER `starts_at`,
  ADD COLUMN `max_completions` INT UNSIGNED NULL DEFAULT NULL AFTER `ends_at`;

UPDATE `tasks`
SET
  `starts_at` = COALESCE(`starts_at`, `created_at`),
  `ends_at` = COALESCE(`ends_at`, DATE_ADD(`created_at`, INTERVAL 365 DAY))
WHERE `starts_at` IS NULL OR `ends_at` IS NULL;

ALTER TABLE `tasks`
  MODIFY `starts_at` DATETIME NOT NULL,
  MODIFY `ends_at` DATETIME NOT NULL;

-- 駁回後刪除 submission，改由此表通知會員（含駁回原因）
CREATE TABLE IF NOT EXISTS `member_notices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `task_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_member_notices_user` (`user_id`),
  CONSTRAINT `fk_member_notices_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_notices_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
);

-- submissions：由 completed 改為 pending / approved，並加審核欄位
ALTER TABLE `submissions`
  MODIFY `status` ENUM('pending', 'approved', 'rejected', 'completed') NOT NULL DEFAULT 'pending';

UPDATE `submissions` SET `status` = 'approved' WHERE `status` = 'completed';

ALTER TABLE `submissions`
  MODIFY `status` ENUM('pending', 'approved') NOT NULL DEFAULT 'pending';

ALTER TABLE `submissions`
  ADD COLUMN `reviewed_at` TIMESTAMP NULL DEFAULT NULL AFTER `submitted_at`,
  ADD COLUMN `reviewed_by` INT UNSIGNED NULL DEFAULT NULL AFTER `reviewed_at`;

ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submissions_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL;
