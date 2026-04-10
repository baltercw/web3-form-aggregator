# Web3 Task Aggregator

以 PHP + MySQL 實作的 Web3 任務整合平台：首頁聚合展示任務、管理員發布任務、會員完成任務並寫入提交紀錄。

## 技術棧

- **後端**：PHP（Session）、MySQL（mysqli）
- **前端**：HTML、透過 [Tailwind CSS CDN](https://tailwindcss.com/docs/installation/play-cdn) 內嵌樣式
- **介面**：深色底（`#0b0b0b`）、琥珀色重點、與細格線背景；`index.php`、`login.php`、`register.php`、`dashboard.php`、`issuer_portal.php` 視覺一致
- **密碼**：使用 PHP `password_hash` / `password_verify`（bcrypt）儲存與驗證

## 專案功能

- **首頁**（`index.php`）：公開任務列表（訪客僅見**摘要**、登入後見**完整說明**）、今日摘要；未登入可註冊／登入；已登入依角色顯示「進入後台」或「項目方中心」
- **註冊**（`register.php`）：新會員註冊（角色固定為 `member`）；送出前以 Ajax 檢查帳號是否已被使用
- **登入**（`login.php`）：登入／登出、未登入可回首頁；註冊成功後可帶參數顯示提示
- **後台**（`dashboard.php`，需登入；`issuer` 會被導向項目方中心）
  - `admin`：發布／編輯／刪除**任意**任務、管理使用者角色（可將帳號設為 `issuer`）、任務含**公開摘要**與可選**自訂提交欄位**（JSON `form_schema`）
  - `member`：瀏覽完整任務說明；完成時若任務有自訂欄位則必填，資料寫入 `submissions.response_json`
- **項目方中心**（`issuer_portal.php`，角色 `issuer`）：獨立頁面管理**自己發布**的任務（新增／編輯／刪除）、設計自訂欄位、檢視各任務的會員提交紀錄（進度以「已完成＋提交內容」呈現；**多階段狀態**尚未實作）

## 檔案說明（逐檔功能）

### `index.php`（公開首頁）

- **Session**：啟動 Session，用於判斷是否已登入（僅讀取 `$_SESSION`，不在此頁寫入登入狀態）。
- **資料**：自 `tasks` 讀取全部欄位，依 `created_at` **由新到舊**排序，組成任務陣列供畫面使用。
- **顯示邏輯**：
  - 導覽列依登入狀態顯示「註冊／登入」或「進入後台／項目方中心／登出」。
  - Hero 區說明平台定位；右側 **Today Snapshot** 顯示任務總數與簡要狀態（有無任務）。
  - **任務展示區**：訪客顯示 `summary`（無則截斷 `description`）並提示登入後看全文；已登入顯示完整 `description`。
  - 頁尾說明項目方為邀請制（請聯絡管理員）。
- **安全與輔助**：輸出經 `htmlspecialchars`；內建 `formatTaskDate()` 將資料庫時間轉成易讀英文短日期（失敗時回傳原字串）。
- **介面**：與全站一致的深色主題、琥珀重點色、Tailwind CDN。

### `login.php`（登入／登出入口）

- **Session**：與資料庫連線；處理登入前後的 Session 生命週期。
- **GET `?action=logout`**：`session_unset()`、`session_destroy()` 後導回本頁（乾淨登出）。
- **GET `?registered=1`**：註冊成功導回時顯示「註冊成功，請登入」提示（不含敏感資料）。
- **已登入導向**：若已存在 `user_id` 與 `role`，`issuer` 導向 **`issuer_portal.php`**，其餘導向 **`dashboard.php`**。
- **POST 登入**：
  - 驗證帳號、密碼非空。
  - 以 **預處理陳述式**依 `username` 查詢一筆使用者；密碼以 **`password_verify`** 與資料庫內的雜湊比對。
  - 成功則寫入 `$_SESSION['user_id']`、`$_SESSION['username']`、`$_SESSION['role']`，再依角色導向 `issuer_portal.php` 或 `dashboard.php`；失敗則設定錯誤訊息於畫面。
- **畫面**：登入表單、錯誤／成功提示、測試帳號說明、前往註冊與返回首頁連結；頂部導覽含註冊入口；底部說明項目方邀請制。

### `register.php`（會員註冊）

- **Session**：已登入者依角色導向 `issuer_portal.php` 或 `dashboard.php`。
- **POST 註冊**：
  - 驗證帳號格式（3～50 字元，僅 `a-z`、`A-Z`、`0-9`、`_`）、密碼長度至少 6、兩次密碼一致。
  - 再以預處理陳述式檢查 `username` 是否已存在；通過後以 `password_hash(..., PASSWORD_DEFAULT)` 寫入 `users`，**角色固定為 `member`**。
  - 成功後 **302 到 `login.php?registered=1`**。
- **Ajax（按下「註冊」時）**：表單 `submit` 預設行為攔截後，先 `fetch` 呼叫 `api/check_username.php`（POST `username`）；若帳號已存在則顯示錯誤且不送出表單；若可用則以程式觸發表單 **原生送出**（略過再次觸發 `submit` 事件），將密碼一併 POST 至本頁完成註冊。
- **畫面**：與登入頁相同視覺系統；含前往登入、返回首頁／頂部導覽。

### `api/check_username.php`（帳號是否可用，JSON API）

- **方法**：僅接受 **POST**（非 POST 回傳 405）。
- **輸入**：`username`（表單欄位）；空白或過長回傳 `available: false`。
- **輸出**：JSON，例如 `{ "ok": true, "available": true, "message": "ok" }`；資料庫錯誤時 `ok: false` 與 500。
- **實作**：`require` 上層 `db.php`，以 `SELECT 1 ... LIMIT 1` + `store_result()` 判斷是否已有相同 `username`（不依賴 `mysqlnd` 的 `get_result`）。

### `dashboard.php`（需登入之後台）

- **閘道**：未同時具備 `user_id` 與 `role` 時導向 `login.php`；角色為 **`issuer` 時一律導向 `issuer_portal.php`**（與全站後台分離）。
- **身份變數**：自 Session 取得 `$userId`、`$role`、`$username`。
- **`admin`**：
  - **發布／編輯任務**：含 `summary`（首頁訪客用）、`description`、`form_schema`（自訂欄位，由表單列動態產生 JSON）、`created_by` 設為目前管理員。
  - **刪除任務**：`DELETE` 任意任務。
  - **使用者角色**：`set_user_role` 可將帳號設為 `admin`／`member`／`issuer`。
- **`member`**：
  - **完成任務**：若該任務有 `form_schema`，所有欄位必填，寫入 `submissions.response_json`；否則僅寫入完成紀錄。
  - 重複完成仍受 **UNIQUE (`user_id`,`task_id`)** 限制。
- **資料載入**：任務列表 LEFT JOIN 建立者帳號；會員載入已完成 `task_id`。
- **畫面**：頂部導覽含首頁、登出；`admin` 另含使用者表、全部任務表。

### `issuer_portal.php`（項目方中心，僅 `issuer`）

- **閘道**：未登入或非 `issuer` 導向登入頁。
- **任務 CRUD**：僅限 `created_by = 自己` 的列；可設與後台相同之摘要、說明、XP、分類、自訂欄位。
- **提交紀錄**：`?submissions={task_id}` 檢視該任務之 `submissions` 與 `response_json`（會員帳號、時間、填寫內容）。

### `db.php`（資料庫連線模組）

- **設定來源**：優先讀取環境變數 `DB_HOST`、`DB_USER`、`DB_PASS`、`DB_NAME`；未設定時預設為本機 `127.0.0.1`、使用者 `root`、密碼空字串、資料庫名 `group_09`（與 `database.sql` 預設庫名一致）。
- **連線**：建立 `mysqli` 實例；失敗時 `die` 並輸出連線錯誤訊息。
- **編碼**：`set_charset('utf8mb4')`，避免中文與表情符號亂碼。
- **使用方式**：由各頁 `require_once './db.php'` 取得全域 `$conn`；**不含**業務邏輯或 HTML。

### `database.sql`（資料庫結構與種子資料）

- **範圍**：會 `DROP` 後重建資料庫 `group_09`（匯入前請確認無需保留舊資料）。
- **`users`**：`username` 唯一、密碼 bcrypt、`role` 為 `admin` | `member` | **`issuer`**。
- **`tasks`**：`title`、`summary`、`description`、`reward_xp`、`category`、`form_schema`（**LONGTEXT**，存 JSON 字串，相容 MariaDB／舊版 MySQL）、`created_by`（FK `users`）、`created_at`。
- **`submissions`**：可選 `response_json`（**LONGTEXT**，存 JSON 字串）；`user_id`+`task_id` 唯一；外鍵級聯刪除。
- **種子資料**：`admin`／`member` 測試帳號；並附一筆含自訂欄位之範例任務（建立者為 admin）。

### `README.md`（本文件）

- 說明專案目的、技術棧、各檔職責、資料庫概要、安裝步驟與測試帳號；不參與執行階段邏輯。

## 資料庫設計（摘要）

- **`users`**：帳號、密碼、角色（`admin` / `member` / `issuer`）。
- **`tasks`**：標題、公開摘要、完整說明、XP、分類、自訂表單 schema、建立者、建立時間。
- **`submissions`**：完成紀錄＋可選 `response_json`；**`user_id` + `task_id` 唯一**。

## 快速開始

專案使用**一個**資料庫（`db.php` 的 `$dbName`，預設 `group_09`）。結構與種子資料請匯入 **`database.sql`**（會 `DROP` 後重建該庫；**MariaDB** 相容：`form_schema`／`response_json` 使用 `LONGTEXT` 存 JSON 字串）。

若你**必須保留舊資料**而不能整包匯入，請自行在 phpMyAdmin 用 `ALTER TABLE` 補齊與 `database.sql` 相同的欄位與外鍵（需自行對照腳本）。

1. **匯入資料庫**  
   phpMyAdmin「匯入」`database.sql`，或命令列：`mysql -u root -p < database.sql`（路徑依你的環境調整）。
2. **設定連線**  
   編輯 `db.php`：主機、帳號、密碼、資料庫名稱（須與匯入的庫名一致）。
3. **啟動環境**（擇一）  
   XAMPP／WAMP 的 Apache，或 `php -S localhost:8000`。
4. **瀏覽**  
   例如 `http://localhost/web3-form-aggregator/`

## 測試帳號

- 管理員：`admin` / `admin123456`
- 會員：`member` / `member123456`
- **項目方**：請以管理員登入 `dashboard.php`，於「使用者與角色」將任一帳號改為 `issuer` 後，該帳號登入會進入 `issuer_portal.php`。

## 備註

- 若你手邊是**舊版明文密碼資料庫**，請重新匯入目前的 `database.sql`，或自行將 `users.password` 更新為 `password_hash` 產生的雜湊，否則 `login.php` 無法驗證登入。
- 正式上線仍建議補上 HTTPS、CSRF 防護、登入嘗試限制與更嚴格的密碼政策。
