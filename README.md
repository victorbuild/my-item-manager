# 物品管理系統

## API 文件

本專案使用 [Scribe](https://scribe.knuckles.wtf/) 產生 API 文件。

### 生成 API 文件

```bash
php artisan scribe:generate
```

### 訪問 API 文件

生成文件後，可以透過以下路由訪問：

- **HTML 文件**：`http://localhost:8001/docs`
- **OpenAPI 規格**：`http://localhost:8001/docs.openapi`
- **Postman 集合**：`http://localhost:8001/docs.postman`

## 靜態分析（PHPStan）

本專案使用 [PHPStan](https://phpstan.org/) 搭配 [Larastan](https://github.com/larastan/larastan) 進行靜態程式碼分析，確保程式品質與分層設計遵守團隊規範。

### 執行靜態分析

```bash
./vendor/bin/phpstan analyse --memory-limit=512M
```

### 重新產生 baseline（清除現有記錄後重新建立）

```bash
./vendor/bin/phpstan analyse --generate-baseline --memory-limit=512M
```

baseline 檔案用來記錄初始錯誤狀態，導入初期避免一次修正大量 legacy 問題，建議在整理或修復一批錯誤後更新 baseline。

## 將 GCS 憑證內容寫入 .env

若需將 Google Cloud Storage（GCS）服務帳戶憑證（JSON 檔案）寫入 `.env` 檔案，可使用以下指令將憑證內容轉為單行並複製到剪貼簿：

```sh
openssl base64 -in <gcs-service-account.json> | tr -d '\n' | pbcopy
```

然後在 `.env` 中設定，例如：

```
GOOGLE_CLOUD_KEY_FILE=複製後的內容
```

## 在本地執行 PHP CodeSniffer

專案已設定 `phpcs.xml`，可直接在專案根目錄執行：

```sh
vendor/bin/phpcs
```

這會自動依照 `phpcs.xml` 設定檢查指定目錄與規則。
