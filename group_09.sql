-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- 主機： 127.0.0.1
-- 產生時間： 2026-06-07 08:57:16
-- 伺服器版本： 10.4.32-MariaDB
-- PHP 版本： 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- 資料庫： `group_09`
--
CREATE DATABASE IF NOT EXISTS `group_09` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `group_09`;

-- --------------------------------------------------------

--
-- 資料表結構 `member_notices`
--

CREATE TABLE `member_notices` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `message` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `member_notices`
--

INSERT INTO `member_notices` (`id`, `user_id`, `task_id`, `message`, `created_at`) VALUES
(1, 2, 2, '你的提交未通過審核：交易雜湊無法在測試網查詢到紀錄，請確認後可再次提交（若任務仍開放）。', '2026-06-07 06:49:04');

-- --------------------------------------------------------

--
-- 資料表結構 `submissions`
--

CREATE TABLE `submissions` (
  `id` int(10) UNSIGNED NOT NULL,
  `user_id` int(10) UNSIGNED NOT NULL,
  `task_id` int(10) UNSIGNED NOT NULL,
  `status` enum('pending','approved') NOT NULL DEFAULT 'pending',
  `response_json` longtext DEFAULT NULL,
  `submitted_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `reviewed_at` timestamp NULL DEFAULT NULL,
  `reviewed_by` int(10) UNSIGNED DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `submissions`
--

INSERT INTO `submissions` (`id`, `user_id`, `task_id`, `status`, `response_json`, `submitted_at`, `reviewed_at`, `reviewed_by`) VALUES
(1, 2, 1, 'approved', '{\"discord_handle\":\"member#0001\",\"wallet\":\"0x0000000000000000000000000000000000000001\"}', '2026-06-07 06:49:04', '2026-06-07 06:49:04', 1),
(2, 13, 1, 'pending', '{\"discord_handle\":\"demo1#1234\",\"wallet\":\"\"}', '2026-06-07 06:49:04', NULL, NULL),
(3, 2, 2, 'pending', '{\"wallet_address\":\"0xabcdefabcdefabcdefabcdefabcdefabcdefabcd\",\"tx_hash\":\"0xdeadbeef\",\"onchain_note\":\"Sepolia test\"}', '2026-06-07 06:49:04', NULL, NULL),
(4, 2, 5, 'approved', '{\"ig_handle\":\"@member_demo\"}', '2026-06-07 06:49:04', '2026-02-15 02:00:00', 1),
(5, 13, 6, 'approved', '{\"wallet\":\"0x1111111111111111111111111111111111111111\"}', '2026-06-07 06:49:04', '2026-06-07 06:49:04', 1),
(6, 14, 6, 'approved', '{\"wallet\":\"0x2222222222222222222222222222222222222222\"}', '2026-06-07 06:49:04', '2026-06-07 06:49:04', 1),
(7, 15, 6, 'approved', '{\"wallet\":\"0x3333333333333333333333333333333333333333\"}', '2026-06-07 06:49:04', '2026-06-07 06:49:04', 1),
(8, 16, 6, 'approved', '{\"wallet\":\"0x4444444444444444444444444444444444444444\"}', '2026-06-07 06:49:04', '2026-06-07 06:49:04', 1),
(9, 2, 7, 'pending', '{\"wallet\":\"0xmembermembermembermembermembermemberme\",\"proof_url\":\"https://example.com/nft-proof\"}', '2026-06-07 06:49:04', NULL, NULL);

-- --------------------------------------------------------

--
-- 資料表結構 `tasks`
--

CREATE TABLE `tasks` (
  `id` int(10) UNSIGNED NOT NULL,
  `title` varchar(150) NOT NULL,
  `summary` varchar(500) NOT NULL DEFAULT '',
  `description` text NOT NULL,
  `cover_image_url` varchar(512) DEFAULT NULL,
  `reward_xp` int(10) UNSIGNED NOT NULL DEFAULT 0,
  `category` varchar(100) NOT NULL,
  `task_status` enum('published','ended') NOT NULL DEFAULT 'published',
  `starts_at` datetime NOT NULL,
  `ends_at` datetime NOT NULL,
  `max_completions` int(10) UNSIGNED DEFAULT NULL,
  `form_schema` longtext DEFAULT NULL,
  `created_by` int(10) UNSIGNED DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `tasks`
--

INSERT INTO `tasks` (`id`, `title`, `summary`, `description`, `cover_image_url`, `reward_xp`, `category`, `task_status`, `starts_at`, `ends_at`, `max_completions`, `form_schema`, `created_by`, `created_at`) VALUES
(1, '關注官方 Discord 頻道', '加入官方 Discord 並於頻道打招呼，截圖或填寫帳號供審核。', '步驟：\n1. 加入 Discord 邀請連結（課程公告）。\n2. 於 #introduction 發一句自我介紹。\n3. 提交你的 Discord 使用者名稱。\n\n此任務不要求簽署交易或私鑰。', NULL, 80, '社群', 'published', '2026-01-01 00:00:00', '2027-12-31 23:59:59', NULL, '[{\"key\":\"discord_handle\",\"label\":\"Discord 使用者名稱\",\"type\":\"text\",\"required\":true},{\"key\":\"wallet\",\"label\":\"錢包地址（選填）\",\"type\":\"text\",\"required\":false}]', 1, '2026-06-07 06:49:04'),
(2, '鏈上互動：提交交易雜湊', '完成一筆測試網轉帳並提交 Tx Hash，供管理員核對。', '請在 Sepolia 測試網完成一筆轉帳至公告地址，並填寫：\n- 錢包地址\n- 交易雜湊 (tx hash)\n\n僅用於任務驗證，不會要求簽署額外交易。', NULL, 150, 'On-chain', 'published', '2026-01-01 00:00:00', '2027-06-30 23:59:59', 100, '[{\"key\":\"wallet_address\",\"label\":\"錢包地址\",\"type\":\"text\",\"required\":true},{\"key\":\"tx_hash\",\"label\":\"交易雜湊 Tx Hash\",\"type\":\"text\",\"required\":true},{\"key\":\"onchain_note\",\"label\":\"備註\",\"type\":\"textarea\",\"required\":false}]', 1, '2026-06-07 06:49:04'),
(3, '新手任務：平台使用問卷', '填寫簡短問卷，協助我們改善任務列表體驗。', '請誠實填寫以下欄位。勾選條款代表同意活動說明與資料僅用於課程專題統計。', NULL, 30, '問卷', 'published', '2026-01-01 00:00:00', '2027-12-31 23:59:59', NULL, '[{\"key\":\"email\",\"label\":\"聯絡 Email\",\"type\":\"email\",\"required\":true},{\"key\":\"feedback\",\"label\":\"建議與回饋\",\"type\":\"textarea\",\"required\":true},{\"key\":\"terms\",\"label\":\"同意活動條款\",\"type\":\"checkbox\",\"required\":true}]', 1, '2026-06-07 06:49:04'),
(4, 'Web3 研討會早鳥登記', '活動尚未開始，可先收藏；開始後方可提交報名表單。', '研討會將於 2026/06 舉辦，開始後請填寫姓名與參與方式。目前僅供列表展示「尚未開始」狀態。', NULL, 120, '活動', 'published', '2026-06-01 00:00:00', '2026-12-31 23:59:59', 200, '[{\"key\":\"full_name\",\"label\":\"姓名\",\"type\":\"text\",\"required\":true},{\"key\":\"attend_url\",\"label\":\"報名證明連結\",\"type\":\"url\",\"required\":false}]', 1, '2026-06-07 06:49:04'),
(5, '春節社群活動（已結束）', '此任務已結束，僅供首頁「已結束」篩選示範。', '活動已於 2026/02/28 結束，無法再提交。若你曾參與，提交紀錄仍會保留於後台。', NULL, 60, '社群', 'ended', '2026-01-01 00:00:00', '2026-02-28 23:59:59', NULL, '[{\"key\":\"ig_handle\",\"label\":\"Instagram 帳號\",\"type\":\"text\",\"required\":true}]', 1, '2026-06-07 06:49:04'),
(6, 'Alpha 白名單（名額 5，剩 1）', '名額即將額滿：已核准 4 筆，僅剩 1 個名額。', '填寫錢包地址申請白名單。名額以「已核准」數量計算，額滿後無法再提交。', NULL, 200, 'Whitelist', 'published', '2026-01-01 00:00:00', '2027-12-31 23:59:59', 5, '[{\"key\":\"wallet\",\"label\":\"錢包地址\",\"type\":\"text\",\"required\":true}]', 1, '2026-06-07 06:49:04'),
(7, '項目方任務：NFT 持有者驗證', '由項目方 issuer 發布，需填寫錢包與持有證明連結。', '請確認你持有指定系列 NFT，並提交錢包與 OpenSea 或其他證明連結。項目方將於 issuer 後台審核。', NULL, 300, 'NFT', 'published', '2026-01-01 00:00:00', '2027-12-31 23:59:59', 50, '[{\"key\":\"wallet\",\"label\":\"錢包地址\",\"type\":\"text\",\"required\":true},{\"key\":\"proof_url\",\"label\":\"持有證明連結\",\"type\":\"url\",\"required\":true}]', 12, '2026-06-07 06:49:04'),
(8, 'DeFi 互動挑戰（高 XP）', '高獎勵任務，適合測試首頁「XP 最高」排序。', '請描述你使用的 DeFi 協議與操作摘要（文字即可，不需實際鏈上驗證）。', NULL, 500, 'DeFi', 'published', '2026-01-01 00:00:00', '2027-12-31 23:59:59', NULL, '[{\"key\":\"protocol\",\"label\":\"協議名稱\",\"type\":\"text\",\"required\":true},{\"key\":\"summary\",\"label\":\"操作摘要\",\"type\":\"textarea\",\"required\":true}]', 1, '2026-06-07 06:49:04'),
(9, 'KYC 示範任務（課程用）', '含 KYC 關鍵欄位，用於首頁 web3 標籤示範。', '此為課程示範，請勿填寫真實證件資料；可填寫測試用假資料。', NULL, 100, '合規', 'published', '2026-01-01 00:00:00', '2027-12-31 23:59:59', 20, '[{\"key\":\"legal_name\",\"label\":\"姓名\",\"type\":\"text\",\"required\":true},{\"key\":\"kyc_id\",\"label\":\"證件末四碼（示範）\",\"type\":\"text\",\"required\":true},{\"key\":\"kyc_agree\",\"label\":\"同意隱私說明\",\"type\":\"checkbox\",\"required\":true}]', 1, '2026-06-07 06:49:04');

-- --------------------------------------------------------

--
-- 資料表結構 `users`
--

CREATE TABLE `users` (
  `id` int(10) UNSIGNED NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('admin','member','issuer') NOT NULL DEFAULT 'member'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- 傾印資料表的資料 `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`) VALUES
(1, 'admin', '$2y$10$hZ6zGx7USnfGMrNHTXu8T.Y2B1UcS9XGYXrHmKhO5QW3p4zRzs7PS', 'admin'),
(2, 'member', '$2y$10$0k3QlzSWNxmUaVhgusZVb.FXSwvjEvJz9Mmsx9Mw/CPX/wXSnH9XG', 'member'),
(12, 'issuer', '$2y$10$MtQPNU5BBOFiU/2QJYf/Bet4au63xX.iHZldiKY2NU6MaClUg2bZG', 'issuer'),
(13, 'demo1', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member'),
(14, 'demo2', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member'),
(15, 'demo3', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member'),
(16, 'demo4', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member');

--
-- 已傾印資料表的索引
--

--
-- 資料表索引 `member_notices`
--
ALTER TABLE `member_notices`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_member_notices_user` (`user_id`),
  ADD KEY `fk_member_notices_task` (`task_id`);

--
-- 資料表索引 `submissions`
--
ALTER TABLE `submissions`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_user_task` (`user_id`,`task_id`),
  ADD KEY `fk_submissions_task` (`task_id`),
  ADD KEY `fk_submissions_reviewer` (`reviewed_by`);

--
-- 資料表索引 `tasks`
--
ALTER TABLE `tasks`
  ADD PRIMARY KEY (`id`),
  ADD KEY `fk_tasks_creator` (`created_by`);

--
-- 資料表索引 `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- 在傾印的資料表使用自動遞增(AUTO_INCREMENT)
--

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `member_notices`
--
ALTER TABLE `member_notices`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `submissions`
--
ALTER TABLE `submissions`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `tasks`
--
ALTER TABLE `tasks`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- 使用資料表自動遞增(AUTO_INCREMENT) `users`
--
ALTER TABLE `users`
  MODIFY `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=17;

--
-- 已傾印資料表的限制式
--

--
-- 資料表的限制式 `member_notices`
--
ALTER TABLE `member_notices`
  ADD CONSTRAINT `fk_member_notices_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_member_notices_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `submissions`
--
ALTER TABLE `submissions`
  ADD CONSTRAINT `fk_submissions_reviewer` FOREIGN KEY (`reviewed_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_submissions_task` FOREIGN KEY (`task_id`) REFERENCES `tasks` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_submissions_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- 資料表的限制式 `tasks`
--
ALTER TABLE `tasks`
  ADD CONSTRAINT `fk_tasks_creator` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
