DROP DATABASE IF EXISTS `group_09`;
CREATE DATABASE `group_09` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `group_09`;

CREATE TABLE `users` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'member', 'issuer') NOT NULL DEFAULT 'member'
);

CREATE TABLE `tasks` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `summary` VARCHAR(500) NOT NULL DEFAULT '',
  `description` TEXT NOT NULL,
  `cover_image_url` VARCHAR(512) NULL DEFAULT NULL,
  `reward_xp` INT UNSIGNED NOT NULL DEFAULT 0,
  `category` VARCHAR(100) NOT NULL,
  `task_status` ENUM('published', 'ended') NOT NULL DEFAULT 'published',
  `starts_at` DATETIME NOT NULL,
  `ends_at` DATETIME NOT NULL,
  `max_completions` INT UNSIGNED NULL DEFAULT NULL,
  `form_schema` LONGTEXT NULL,
  `created_by` INT UNSIGNED NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `submissions` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `task_id` INT UNSIGNED NOT NULL,
  `status` ENUM('pending', 'approved') NOT NULL DEFAULT 'pending',
  `response_json` LONGTEXT NULL,
  `submitted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reviewed_at` TIMESTAMP NULL DEFAULT NULL,
  `reviewed_by` INT UNSIGNED NULL DEFAULT NULL,
  UNIQUE KEY `uq_user_task` (`user_id`, `task_id`),
  CONSTRAINT `fk_submissions_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_submissions_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
);

CREATE TABLE `member_notices` (
  `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT UNSIGNED NOT NULL,
  `task_id` INT UNSIGNED NOT NULL,
  `message` TEXT NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY `idx_member_notices_user` (`user_id`),
  CONSTRAINT `fk_member_notices_user` FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_member_notices_task` FOREIGN KEY (`task_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE
);

INSERT INTO `users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$hZ6zGx7USnfGMrNHTXu8T.Y2B1UcS9XGYXrHmKhO5QW3p4zRzs7PS', 'admin'),
('member', '$2y$10$0k3QlzSWNxmUaVhgusZVb.FXSwvjEvJz9Mmsx9Mw/CPX/wXSnH9XG', 'member');

INSERT INTO `tasks` (`title`, `summary`, `description`, `reward_xp`, `category`, `task_status`, `starts_at`, `ends_at`, `max_completions`, `form_schema`, `created_by`) VALUES
(
  '範例任務：關注官方頻道',
  '公開摘要：完成指定社群動作即可獲得 XP。登入後可查看完整規則與提交欄位。',
  '完整說明：請依序完成以下步驟並在提交時填寫你的帳號連結供審核。若任務設有自訂欄位（如錢包、IG），請如實填寫。',
  50,
  '社群',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  NULL,
  '[{"key":"ig_handle","label":"Instagram 帳號或連結","type":"text","required":true},{"key":"wallet","label":"錢包地址","type":"text","required":false}]',
  1
);
