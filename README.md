# Web3 Form Aggregator

Web3 任務表單整合平台（PHP + MySQL）。  
聚合多種任務，提供訪客瀏覽、會員提交、管理員審核、項目方管理的完整流程，並在 UI 上強調 web3 使用者的安全感與透明度。

---

## 我已完成的功能

- **使用者系統**
  - 註冊、登入、登出
  - 角色分流：`admin` / `member` / `issuer`
  - 依角色導向不同後台頁面（管理員後台 / 項目方中心 / 會員任務頁）

- **任務系統**
  - 建立任務：標題、公開摘要、完整說明
  - 分類與獎勵：自訂 `category`，設定任務 XP（`reward_xp`）
  - 封面圖：可選填 `cover_image_url`，顯示於會員填寫 Modal 上方

- **任務規則**
  - 時間區間：開始時間 `starts_at`、截止時間 `ends_at`（以台灣時區 `Asia/Taipei` 為主）
  - 狀態控制：`task_status = published / ended`（可手動結束任務）
  - 名額上限：`max_completions`，依「已核准數量」動態計算剩餘名額
  - 任務開放邏輯由 `task_submission_gate()` 控制（時間、狀態、名額都會檢查）

- **自訂表單欄位**
  - JSON `form_schema` 描述任務的提交欄位
  - 支援欄位型態：`text`、`textarea`、`url`、`email`、`checkbox`
  - 可設定必填 / 選填
  - 管理員與項目方都可以透過後台 UI 建立與編輯欄位（內建欄位 Builder）
  - 後端以 `form_schema_normalize_list()`、`form_schema_collect_responses()` 做資料驗證與收集

- **提交流程**
  - 會員登入後，在後台任務列表中點選「開啟填寫視窗」以 Modal 方式提交
  - 提交後會寫入 `submissions`：`status = pending`
  - 管理員可在 `dashboard.php` 審核全站提交
  - 項目方可在 `issuer_portal.php` 針對自己任務的提交進行審核
  - 審核結果：
    - 核准：更新 `submissions.status = approved`
    - 駁回：刪除該筆提交，並在 `member_notices` 建立站內通知（說明駁回原因）

- **通知與會員端體驗**
  - 會員登入後可在 `dashboard.php` 看到各任務卡片與自己的提交狀態（未提交 / 審核中 / 已通過）
  - 若被駁回，會收到站內通知（獨立區塊，可點「知道了」關閉）
  - 若任務已通過審核，會清理瀏覽器端的草稿暫存

- **UI / UX 設計**
  - 全站採暗色主題 + 琥珀色重點色（Tailwind CSS + Inter）
  - 首頁 `index.php`：Hero 區 + 任務列表 + Today Snapshot
  - 任務列表支援：分類篩選 / 狀態篩選 / 排序（最新發布、XP 最高、開始時間近、截止時間近）
  - 任務卡重點層級：
    - 類別 / 開放狀態 Badge
    - 標題
    - 時間（台灣時間）
    - 名額與 XP
    - 任務介紹 / 摘要
    - web3 標籤（是否需要錢包、是否有 on-chain、是否涉及 KYC）
  - 會員提交使用同頁 Modal，避免離開列表
  - Modal 內分為兩個區塊：
    - 任務介紹：封面、摘要、時間/名額、web3 標籤、完整說明
    - 填寫欄位：任務所需的自訂欄位表單
  - Checkbox 欄位會額外顯示提示文字：「勾選代表同意活動條款」
  - 欄位輸入草稿會以 `localStorage` 暫存（關閉 Modal 或重新整理頁面不會馬上消失）
  - **全站動態背景**（`includes/background_decor.php`）
    - `index.php` 使用 `hero`：CSS 流動光暈 + 全屏低密度 Canvas 節點網（極淡）
    - 登入／註冊／後台使用 `subtle`：僅較弱的 CSS 光暈，不載入 Canvas
    - 網格線保留，opacity 略降（約 0.16～0.18），避免與光暈搶視覺
    - 首頁捲動超過 Hero 時，粒子層會漸淡，任務列表區可讀性優先
    - **無障礙／效能**：`prefers-reduced-motion: reduce` 時停用手繪與 blob 動畫；寬度 ≤ 768px 不啟用 Canvas（僅保留 CSS 光暈）；分頁在背景時暫停動畫

- **Web3 專屬提示（僅 UI 層級）**
  - 目前 DB 結構沒有獨立的「錢包 / on-chain / KYC」欄位，而是由 `form_schema` 的欄位 key 做啟發式判斷
  - 在任務卡與 Modal 顯示：
    - 是否需要輸入錢包地址（但不會要求鏈上簽名）
    - 任務是否可能涉及 on-chain 資料（例如需要填 tx hash 等）
    - 是否有 KYC 相關欄位
  - 並在 UI 顯示固定安心文案：「此任務不會要求你簽署交易、不會要求私鑰。」

---

## 專案結構（主要檔案）

- `index.php`：
  - 首頁任務列表與篩選（分類、狀態、排序）
  - 任務卡片摘要與 web3 標籤
  - 訪客與已登入使用者看到的內容會不同（登入者可看到完整說明）

- `login.php` / `register.php`：
  - 登入與註冊頁面
  - 依照帳號角色導向 `dashboard.php` 或 `issuer_portal.php`

- `dashboard.php`（管理員 / 會員共用）：
  - 管理員視角：
    - 新增 / 編輯任務（含「基本資料」、「時間與名額」、「表單欄位設定」三個段落）
    - 送出前會跳出「總覽 / 確認視窗」，顯示標題、XP、時間區間、名額、狀態供確認
    - 管理所有使用者角色（將帳號設為 `issuer`、`admin`、`member`）
    - 檢視全站待審核提交並進行核准 / 駁回
    - 管理所有任務（編輯 / 刪除）
  - 會員視角：
    - 檢視可參與任務清單
    - 查看每個任務的時間、名額、XP、完整說明與提交狀態
    - 透過 Modal 填寫自訂表單並提交審核
    - 查看被駁回的通知（含理由）

- `issuer_portal.php`（項目方中心）：
  - 使用者角色為 `issuer` 時進入
  - 建立 / 編輯自己的任務（表單段落設計與管理員類似）
  - 送出前同樣有「總覽 / 確認視窗」確認標題、XP、時間區間與名額
  - 檢視自己每個任務的提交紀錄，獨立審核「自己任務」的會員提交
  - 列表顯示每個任務的總提交數與已核准數

- `admin_stats.php`（管理員資料統計）：
  - 依時間範圍、分類篩選任務
  - 顯示提交量、核准數、通過率與名額消耗率

- `includes/task_helpers.php`：
  - 時區與時間格式相關 helper（台北時間）
  - 任務開放條件判斷 `task_submission_gate()`
  - 依 `form_schema` 啟發式推斷 web3 標籤：`task_web3_flags_from_schema()`、`task_web3_flags_from_task()`

- `includes/form_schema_helpers.php`：
  - 任務表單 schema 正規化與後端驗證
  - `form_schema_normalize_list()`：清理欄位列表（type、label、required 轉成布林）
  - `form_schema_collect_responses()`：驗證 `$_POST['response']`，回傳是否通過與整理後的 key/value

- `includes/site_header.php` / `includes/head_common.php` / `includes/site_footer.php`：
  - 共用頁首、麵包屑、字型、動畫與 footer
  - 手機版選單、登入/註冊入口等

- `db.php`：
  - MySQL 連線設定
  - 預設從環境變數讀取：`DB_HOST`、`DB_USER`、`DB_PASS`、`DB_NAME`
  - 若 `DB_PASS` 未設定，預設為空字串

- `database.sql`：
  - 完整 schema 初始化（會建立 `group_09` 資料庫並丟棄同名舊 DB）
  - 主要資料表：
    - `users`：帳號與角色
    - `tasks`：任務主表
    - `submissions`：會員提交紀錄
    - `member_notices`：會員通知
  - 內含一組 `admin` 與 `member` 測試帳號，以及一個範例任務
- `seed_demo_data.sql`：
  - **在既有 `group_09` 上匯入**（不會 DROP 資料庫）
  - **保留** `admin`、`member` 帳號與密碼
  - 清空並重建任務、提交、通知，並加入 8 筆示範任務與多筆審核狀態

---

## 開發技術

- **後端**：原生 PHP 7.3.x（無框架）
- **資料庫**：MySQL（`group_09` 資料庫）
- **前端樣式**：Tailwind CSS（CDN 版本）
- **字型**：Inter（Google Fonts）
- **前端互動**：原生 JavaScript（Modal、動態欄位、草稿暫存、確認視窗）

---

## 快速啟動

1. **安裝環境**
   - 建議使用 XAMPP / WAMP（Windows）或對應 LAMP/MAMP 環境
   - PHP 版本需支援 7.3 以上（本機為 7.3.3 測試）

2. **建立資料庫**
   - 進入 phpMyAdmin 或 MySQL CLI，執行 `database.sql`
   - 注意：會直接 `DROP DATABASE group_09`，請勿在正式環境直接執行

3. **（選用）匯入示範假資料**
   - 若首頁任務太少、想測試篩選／名額／審核：在 phpMyAdmin 選 `group_09` → **匯入** → `seed_demo_data.sql`
   - 可重複執行；會刪除舊任務與提交，但 **不會刪除** `admin`、`member`
   - 也可用 MySQL CLI：  
     `"C:\Myspace\Dev_Tools\xampp\mysql\bin\mysql.exe" -u root group_09 < seed_demo_data.sql`

4. **設定資料庫連線**
   - 方式一：直接編輯 `db.php`
     - `DB_HOST`：預設 `127.0.0.1`
     - `DB_USER`：預設 `root`
     - `DB_PASS`：若未設定環境變數則預設為空字串（依你的 MySQL 安裝狀況修改）
     - `DB_NAME`：預設 `group_09`
   - 方式二：設定環境變數（適合部署環境）
     - `DB_HOST`、`DB_USER`、`DB_PASS`、`DB_NAME`

5. **啟動伺服器**
   - 使用 XAMPP Apache：將專案放在 `htdocs` 下，例如：  
     `C:\Myspace\Dev_Tools\xampp\htdocs\web3-form-aggregator`
   - 在瀏覽器開啟：  
     `http://localhost/web3-form-aggregator/index.php`

---

## 使用流程簡述

- **訪客**
  - 進入 `index.php` 瀏覽任務列表
  - 可使用分類/狀態/排序快速找到想執行的任務
  - 點擊登入 / 註冊切換到帳號系統

- **會員 (`member`)**
  - 登入後進入 `dashboard.php` 的會員視圖
  - 查看可參與任務、自己的提交狀態與通知
  - 透過 Modal 填寫表單，提交給管理員或項目方審核

- **項目方 (`issuer`)**
  - 登入後進入 `issuer_portal.php`
  - 建立新任務或編輯既有任務
  - 檢視各任務的提交紀錄並進行審核

- **管理員 (`admin`)**
  - 登入後進入 `dashboard.php` 的管理員視圖
  - 建立/編輯/刪除任務
  - 管理使用者角色
  - 審核全站所有任務的提交
  - 在 `admin_stats.php` 檢視任務提交與通過率統計

---

## 測試帳號

**核心測試帳號（`database.sql` 與 `seed_demo_data.sql` 皆保留）**

| 帳號 | 密碼 | 角色 |
|------|------|------|
| `admin` | `admin123456` | 管理員 |
| `member` | `member123456` | 會員 |

**匯入 `seed_demo_data.sql` 後額外提供**

| 帳號 | 密碼 | 角色 | 用途 |
|------|------|------|------|
| `issuer` | `issuer123456` | 項目方 | 測試 `issuer_portal.php` |
| `demo1`～`demo4` | `demo123456` | 會員 | 示範名額已滿、多筆審核等 |

（亦可於 UI 註冊後，由管理員在後台調整角色。）

---

## 順便記一筆

正式上線建議用 HTTPS，並視需要加強 CSRF、登入嘗試限制與密碼政策。
