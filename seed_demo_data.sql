-- =============================================================================
-- Web3 Form Aggregator — 示範假資料（可重複匯入）
-- =============================================================================
-- 用途：在「已存在」的 group_09 資料庫填入任務／提交／通知示範資料。
-- 保留：admin、member 測試帳號（密碼不變）。
--
-- 匯入方式（XAMPP phpMyAdmin）：
--   1. 先執行過 database.sql（或已有 group_09）
--   2. 選取資料庫 group_09 → 匯入 → 選此檔 → 執行
--
-- 注意：會刪除所有任務、提交、通知；並刪除 admin/member 以外的使用者後重建示範帳號。
-- =============================================================================

USE `group_09`;

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DELETE FROM `member_notices`;
DELETE FROM `submissions`;
DELETE FROM `tasks`;
DELETE FROM `users` WHERE `username` NOT IN ('admin', 'member');

SET FOREIGN_KEY_CHECKS = 1;

-- 測試帳號（INSERT IGNORE：已存在則保留原 id 與密碼）
INSERT IGNORE INTO `users` (`username`, `password`, `role`) VALUES
('admin', '$2y$10$hZ6zGx7USnfGMrNHTXu8T.Y2B1UcS9XGYXrHmKhO5QW3p4zRzs7PS', 'admin'),
('member', '$2y$10$0k3QlzSWNxmUaVhgusZVb.FXSwvjEvJz9Mmsx9Mw/CPX/wXSnH9XG', 'member');

-- 示範用帳號（密碼皆為 demo123456，僅供展示名額／審核，非核心測試帳號）
INSERT INTO `users` (`username`, `password`, `role`) VALUES
('issuer', '$2y$10$MtQPNU5BBOFiU/2QJYf/Bet4au63xX.iHZldiKY2NU6MaClUg2bZG', 'issuer'),
('demo1', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member'),
('demo2', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member'),
('demo3', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member'),
('demo4', '$2y$10$a3xYFQNj.XmPKAwrhQmvxOtTToYs/6nNeSNIrjf5wRFVF/gwGm5Bi', 'member');

-- 任務（created_by：1=admin, 3=issuer；執行後 issuer 通常為 id 3，若不同請自行調整）
INSERT INTO `tasks` (
  `title`, `summary`, `description`, `cover_image_url`, `reward_xp`, `category`,
  `task_status`, `starts_at`, `ends_at`, `max_completions`, `form_schema`, `created_by`
) VALUES
(
  '關注官方 Discord 頻道',
  '加入官方 Discord 並於頻道打招呼，截圖或填寫帳號供審核。',
  '步驟：\n1. 加入 Discord 邀請連結（課程公告）。\n2. 於 #introduction 發一句自我介紹。\n3. 提交你的 Discord 使用者名稱。\n\n此任務不要求簽署交易或私鑰。',
  NULL,
  80,
  '社群',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  NULL,
  '[{"key":"discord_handle","label":"Discord 使用者名稱","type":"text","required":true},{"key":"wallet","label":"錢包地址（選填）","type":"text","required":false}]',
  1
),
(
  '鏈上互動：提交交易雜湊',
  '完成一筆測試網轉帳並提交 Tx Hash，供管理員核對。',
  '請在 Sepolia 測試網完成一筆轉帳至公告地址，並填寫：\n- 錢包地址\n- 交易雜湊 (tx hash)\n\n僅用於任務驗證，不會要求簽署額外交易。',
  NULL,
  150,
  'On-chain',
  'published',
  '2026-01-01 00:00:00',
  '2027-06-30 23:59:59',
  100,
  '[{"key":"wallet_address","label":"錢包地址","type":"text","required":true},{"key":"tx_hash","label":"交易雜湊 Tx Hash","type":"text","required":true},{"key":"onchain_note","label":"備註","type":"textarea","required":false}]',
  1
),
(
  '新手任務：平台使用問卷',
  '填寫簡短問卷，協助我們改善任務列表體驗。',
  '請誠實填寫以下欄位。勾選條款代表同意活動說明與資料僅用於課程專題統計。',
  NULL,
  30,
  '問卷',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  NULL,
  '[{"key":"email","label":"聯絡 Email","type":"email","required":true},{"key":"feedback","label":"建議與回饋","type":"textarea","required":true},{"key":"terms","label":"同意活動條款","type":"checkbox","required":true}]',
  1
),
(
  'Web3 研討會早鳥登記',
  '活動尚未開始，可先收藏；開始後方可提交報名表單。',
  '研討會將於 2026/06 舉辦，開始後請填寫姓名與參與方式。目前僅供列表展示「尚未開始」狀態。',
  NULL,
  120,
  '活動',
  'published',
  '2026-06-01 00:00:00',
  '2026-12-31 23:59:59',
  200,
  '[{"key":"full_name","label":"姓名","type":"text","required":true},{"key":"attend_url","label":"報名證明連結","type":"url","required":false}]',
  1
),
(
  '春節社群活動（已結束）',
  '此任務已結束，僅供首頁「已結束」篩選示範。',
  '活動已於 2026/02/28 結束，無法再提交。若你曾參與，提交紀錄仍會保留於後台。',
  NULL,
  60,
  '社群',
  'ended',
  '2026-01-01 00:00:00',
  '2026-02-28 23:59:59',
  NULL,
  '[{"key":"ig_handle","label":"Instagram 帳號","type":"text","required":true}]',
  1
),
(
  'Alpha 白名單（名額 5，剩 1）',
  '名額即將額滿：已核准 4 筆，僅剩 1 個名額。',
  '填寫錢包地址申請白名單。名額以「已核准」數量計算，額滿後無法再提交。',
  NULL,
  200,
  'Whitelist',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  5,
  '[{"key":"wallet","label":"錢包地址","type":"text","required":true}]',
  1
),
(
  '項目方任務：NFT 持有者驗證',
  '由項目方 issuer 發布，需填寫錢包與持有證明連結。',
  '請確認你持有指定系列 NFT，並提交錢包與 OpenSea 或其他證明連結。項目方將於 issuer 後台審核。',
  NULL,
  300,
  'NFT',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  50,
  '[{"key":"wallet","label":"錢包地址","type":"text","required":true},{"key":"proof_url","label":"持有證明連結","type":"url","required":true}]',
  (SELECT `id` FROM `users` WHERE `username` = 'issuer' LIMIT 1)
),
(
  'DeFi 互動挑戰（高 XP）',
  '高獎勵任務，適合測試首頁「XP 最高」排序。',
  '請描述你使用的 DeFi 協議與操作摘要（文字即可，不需實際鏈上驗證）。',
  NULL,
  500,
  'DeFi',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  NULL,
  '[{"key":"protocol","label":"協議名稱","type":"text","required":true},{"key":"summary","label":"操作摘要","type":"textarea","required":true}]',
  1
),
(
  'KYC 示範任務（課程用）',
  '含 KYC 關鍵欄位，用於首頁 web3 標籤示範。',
  '此為課程示範，請勿填寫真實證件資料；可填寫測試用假資料。',
  NULL,
  100,
  '合規',
  'published',
  '2026-01-01 00:00:00',
  '2027-12-31 23:59:59',
  20,
  '[{"key":"legal_name","label":"姓名","type":"text","required":true},{"key":"kyc_id","label":"證件末四碼（示範）","type":"text","required":true},{"key":"kyc_agree","label":"同意隱私說明","type":"checkbox","required":true}]',
  1
);

-- 提交示範（user：member=2, demo1~4=依實際 id；以下假設 admin=1 member=2 demo1=4 demo2=5 demo3=6 demo4=7 issuer=3）
-- 若你的 id 不同，請在 phpMyAdmin 依 users 表調整 user_id 後再執行，或僅依賴下方子查詢寫法

INSERT INTO `submissions` (`user_id`, `task_id`, `status`, `response_json`, `reviewed_at`, `reviewed_by`) VALUES
((SELECT `id` FROM `users` WHERE `username` = 'member' LIMIT 1), 1, 'approved', '{"discord_handle":"member#0001","wallet":"0x0000000000000000000000000000000000000001"}', NOW(), 1),
((SELECT `id` FROM `users` WHERE `username` = 'demo1' LIMIT 1), 1, 'pending', '{"discord_handle":"demo1#1234","wallet":""}', NULL, NULL),
((SELECT `id` FROM `users` WHERE `username` = 'member' LIMIT 1), 2, 'pending', '{"wallet_address":"0xabcdefabcdefabcdefabcdefabcdefabcdefabcd","tx_hash":"0xdeadbeef","onchain_note":"Sepolia test"}', NULL, NULL),
((SELECT `id` FROM `users` WHERE `username` = 'member' LIMIT 1), 5, 'approved', '{"ig_handle":"@member_demo"}', '2026-02-15 10:00:00', 1),
((SELECT `id` FROM `users` WHERE `username` = 'demo1' LIMIT 1), 6, 'approved', '{"wallet":"0x1111111111111111111111111111111111111111"}', NOW(), 1),
((SELECT `id` FROM `users` WHERE `username` = 'demo2' LIMIT 1), 6, 'approved', '{"wallet":"0x2222222222222222222222222222222222222222"}', NOW(), 1),
((SELECT `id` FROM `users` WHERE `username` = 'demo3' LIMIT 1), 6, 'approved', '{"wallet":"0x3333333333333333333333333333333333333333"}', NOW(), 1),
((SELECT `id` FROM `users` WHERE `username` = 'demo4' LIMIT 1), 6, 'approved', '{"wallet":"0x4444444444444444444444444444444444444444"}', NOW(), 1),
((SELECT `id` FROM `users` WHERE `username` = 'member' LIMIT 1), (SELECT `id` FROM `tasks` WHERE `title` LIKE '項目方任務%' LIMIT 1), 'pending', '{"wallet":"0xmembermembermembermembermembermemberme","proof_url":"https://example.com/nft-proof"}', NULL, NULL);

INSERT INTO `member_notices` (`user_id`, `task_id`, `message`) VALUES
(
  (SELECT `id` FROM `users` WHERE `username` = 'member' LIMIT 1),
  2,
  '你的提交未通過審核：交易雜湊無法在測試網查詢到紀錄，請確認後可再次提交（若任務仍開放）。'
);

-- 完成
SELECT 'seed_demo_data.sql 匯入完成' AS `status`,
  (SELECT COUNT(*) FROM `tasks`) AS `tasks`,
  (SELECT COUNT(*) FROM `submissions`) AS `submissions`,
  (SELECT COUNT(*) FROM `users`) AS `users`;
