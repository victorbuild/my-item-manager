# 物品管理系統

[![Laravel](https://img.shields.io/badge/Laravel-12.x-red?logo=laravel)](https://laravel.com)
[![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?logo=php&logoColor=white)](https://php.net)
[![Vue.js](https://img.shields.io/badge/Vue.js-3.5-4FC08D?logo=vue.js&logoColor=white)](https://vuejs.org)
[![codecov](https://codecov.io/gh/victorbuild/my-item-manager/branch/main/graph/badge.svg)](https://codecov.io/gh/victorbuild/my-item-manager)

> 一個簡單好用的物品管理系統，幫助你記錄物品的購買日期、開始使用時間，追蹤保存期限，並在下次購買或決定是否丟棄時提供判斷依據。

## 關於專案

這是一個基於 Laravel 的物品管理系統，提供完整的 RESTful API 和基本的 Web 介面。你可以用它來管理家中的物品、追蹤保存期限、上傳圖片、建立分類，讓空間保持在安心舒適的狀態，了解目前的狀況。

## 功能

- **物品管理**：新增、編輯、刪除物品，記錄名稱、數量、保存期限
- **圖片上傳**：支援多圖片上傳，可選用 Google Cloud Storage 儲存
- **過期提醒**：自動計算即將過期的物品
- **搜尋與篩選**：快速搜尋物品、依分類篩選、彈性排序
- **分類管理**：建立自訂分類，讓物品更有條理
- **統計分析**：多維度統計分析，包含進出平衡（新增 vs 丟棄）、物品狀態分布、價值統計（總支出、有效支出、支出效率）、成本分析（平均每日成本），支援多種時間範圍（本週、本月、近三個月、年度、全部）
- **使用者系統**：註冊登入、個人資料管理、多使用者隔離

## 目錄

- [快速開始](#快速開始)
- [技術棧](#技術棧)
- [安裝與設定](#安裝與設定)
- [測試](#測試)
- [專案結構](#專案結構)
- [API 文件](#api-文件)

## 快速開始

```bash
# 複製專案
git clone git@github.com:victorbuild/my-item-manager.git
cd my-item-manager

# 安裝依賴
composer install
npm install

# 環境設定
cp .env.example .env
php artisan key:generate

# 資料庫遷移（使用 SQLite）
php artisan migrate

# 啟動開發伺服器
php artisan serve
npm run dev
```

訪問 http://localhost:8000 開始使用。

> 💡 **提示**：這是快速開始指南，詳細的安裝步驟和環境設定請參考 [安裝與設定](#安裝與設定)。

## 技術棧

- **後端**：Laravel 12.4 + PHP 8.4
- **資料庫**：PostgreSQL / SQLite
- **前端**：Vue.js 3.5 + Tailwind CSS 4.0
- **圖片儲存**：本地 / Google Cloud Storage
- **認證**：Laravel Sanctum (Token-based)

## 安裝與設定

詳細的安裝步驟和環境設定說明。想快速開始？請參考 [快速開始](#快速開始)。

### 環境需求

- PHP >= 8.4
- Composer
- Node.js >= 18.x
- PostgreSQL >= 14（或 SQLite）

### 安裝步驟

```bash
# 1. 複製專案
git clone git@github.com:victorbuild/my-item-manager.git
cd my-item-manager

# 2. 安裝 PHP 依賴
composer install

# 3. 安裝前端依賴
npm install

# 4. 環境設定
cp .env.example .env
php artisan key:generate

# 5. 資料庫設定（編輯 .env）
# 預設使用 SQLite（開發用）
# DB_CONNECTION=sqlite

# 或使用 PostgreSQL（正式環境）
# DB_CONNECTION=pgsql
# DB_HOST=127.0.0.1
# DB_PORT=5432
# DB_DATABASE=item_manager
# DB_USERNAME=postgres
# DB_PASSWORD=your_password

# 6. 執行資料庫遷移
php artisan migrate

# 7. （可選）填充測試資料
php artisan db:seed

# 8. 編譯前端資源
npm run build
```

### CORS 設定

部署時必須將 `CORS_ALLOWED_ORIGINS` 設定為前端的實際網域，否則瀏覽器會阻擋所有 API 請求。

在 `.env` 中設定：

```env
# 單一網域
CORS_ALLOWED_ORIGINS=https://yourdomain.com

# 多個網域（逗號分隔）
CORS_ALLOWED_ORIGINS=https://yourdomain.com,https://www.yourdomain.com
```

> **注意**：請勿使用 `*`（wildcard）。本專案使用 Sanctum cookie 認證，wildcard 與 `supports_credentials` 同時啟用會造成安全問題。

### Google Cloud Storage 設定（可選）

若需使用 GCS 儲存圖片，請先取得服務帳戶 JSON 憑證，然後執行：

```bash
# 將憑證轉換為 base64 並複製到剪貼簿
openssl base64 -in <gcs-service-account.json> | tr -d '\n' | pbcopy
```

在 `.env` 中設定：

```env
GOOGLE_CLOUD_PROJECT_ID=your-project-id
GOOGLE_CLOUD_STORAGE_BUCKET=your-bucket-name
GOOGLE_CLOUD_KEY_FILE=<貼上剪貼簿內容>
```

### 啟動開發伺服器

安裝完成後，啟動開發伺服器：

```bash
# 啟動 Laravel 開發伺服器（預設 port 8000）
php artisan serve

# 啟動 Vite 開發伺服器（另開終端機）
npm run dev
```

訪問 http://localhost:8000 開始使用。

## 測試

執行測試：

```bash
# 執行所有測試
php artisan test
```


## 專案結構

```
my-item-manager/
├── app/
│   ├── Http/Controllers/Api/  # API 控制器
│   ├── Http/Requests/          # Form Request 驗證
│   ├── Http/Resources/         # API Resource 轉換
│   ├── Models/                 # Eloquent 模型
│   ├── Repositories/           # Repository 層
│   ├── Services/               # Service 層（業務邏輯）
│   └── Policies/               # 授權策略
├── database/
│   ├── migrations/             # 資料庫遷移
│   └── seeders/                # 測試資料填充
├── resources/js/               # Vue.js 前端
├── tests/                      # 測試檔案
└── public/docs/                # API 文件
```

## API 文件

本專案使用 [Scribe](https://scribe.knuckles.wtf/) 自動產生完整的 API 文件。

### 產生 API 文件

```bash
php artisan scribe:generate
```

### 查看文件

產生後可透過以下路由查看：

- **HTML 文件**：http://localhost:8000/docs
- **OpenAPI 規格**：http://localhost:8000/docs.openapi
- **Postman 集合**：http://localhost:8000/docs.postman

完整的 API 端點與使用說明請參考：http://localhost:8000/docs

## 聯絡

如有問題或建議，請透過 [Issues](https://github.com/victorbuild/my-item-manager/issues) 提出。
