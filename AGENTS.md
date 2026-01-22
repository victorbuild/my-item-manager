# AI Agent 設定檔

本檔案定義了本專案的開發規範，供 AI Agent（如 Cursor、Claude Code、Windsurf 等）參考，以確保生成的程式碼符合專案規範。

> **注意**：本檔案遵循 AGENTS.md 開放標準，適用於多種 AI Agent 工具。

---

## 1. 專案基本資訊

### 專案概述
這是一個**物品管理系統**的後端 API 服務以及前端畫面，使用 Laravel + Vue.js 開發。

### 技術棧

#### 後端
- **PHP**: ^8.2
- **Laravel**: ^12.0
- **資料庫**: PostgreSQL
- **圖片處理**: Intervention Image ^3.11
- **雲端儲存**: Google Cloud Storage

#### 前端
- **Vue.js**: ^3.5.13（必須使用 Composition API）
- **Tailwind CSS**: ^4.0.0
- **日期處理**: dayjs ^1.11.13

### 禁止使用的套件
- `moment.js`（改用 `dayjs`）
- PHP 7.x 語法
- Vue 2 Options API
- `var` 宣告（JavaScript）

### 專案結構
```
app/
├── Http/Controllers/Api/    # API 控制器
├── Http/Requests/            # Form Request 驗證
├── Http/Resources/           # API Resource
├── Models/                   # Eloquent 模型
└── Services/                 # Service 層（業務邏輯）
```

---

## 2. 程式碼風格規範

### PHP 後端

#### 命名慣例
- **類別**: PascalCase（例：`ItemController`）
- **方法/變數**: camelCase（例：`getItemById`）
- **常數**: UPPER_SNAKE_CASE（例：`MAX_ITEMS_PER_PAGE`）

#### Laravel 規範
- **Controller**: 單數名詞 + Controller（例：`ItemController`）
- **Service**: 單數名詞 + Service（例：`ItemService`）
- **Form Request**: 動詞 + 名詞 + Request（例：`StoreItemRequest`）
- **Resource**: 單數名詞 + Resource（例：`ItemResource`）

#### PHP 要求
- 遵循 **PSR-12** 標準
- 使用 **Laravel Pint** 格式化
- 所有公開方法要有 **PHPDoc 註解**（中文）
- 使用 **型別提示**和 **返回型別**
- 使用 **Constructor Property Promotion**（PHP 8.0+）
- **不要在註解中任意使用 icon（表情符號）**，保持程式碼專業和簡潔

### JavaScript/Vue.js 前端

#### 命名慣例
- **檔案/元件**: PascalCase（例：`Create.vue`）
- **變數/函數**: camelCase（例：`getItemList`）
- **常數**: UPPER_SNAKE_CASE

#### Vue.js 要求
- 使用 **Vue 3 Composition API**
- 使用 **`<script setup>`** 語法
- 使用 **`const`** 和 **`let`**，禁止 `var`
- 使用 **async/await**
- **不要在註解中任意使用 icon（表情符號）**，保持程式碼專業和簡潔

---

## 3. 架構規範與 SOLID 原則

### 必須遵循 SOLID 原則

所有程式碼必須符合 **SOLID 原則**：

- **S - Single Responsibility Principle（單一職責原則）**
  - 每個類別只負責一件事
  - Controller 只處理 HTTP 請求/回應
  - Service 處理業務邏輯
  - Model 只定義資料結構和關係

- **O - Open/Closed Principle（開放封閉原則）**
  - 對擴展開放，對修改封閉
  - 使用介面或抽象類別定義契約

- **L - Liskov Substitution Principle（里氏替換原則）**
  - 子類別可以替換父類別而不影響功能

- **I - Interface Segregation Principle（介面隔離原則）**
  - 不強迫類別實作不需要的方法
  - 介面應該小而專注

- **D - Dependency Inversion Principle（依賴反轉原則）**
  - 高層模組不依賴低層模組，都依賴抽象
  - 依賴注入，而非直接實例化

### 分層架構

#### Controller 層
- 僅處理 HTTP 請求和回應
- 不包含業務邏輯
- 使用 Form Request 驗證
- 使用 Resource 轉換回應
- 注入 Service，不直接使用 Model

#### Service 層
- 包含所有業務邏輯
- 優先使用 **Model 方法**，過於複雜才使用 Query Builder
- 方法保持單一職責
- 使用型別提示

#### Model 層
- 定義 Eloquent 關係和屬性
- 不包含複雜業務邏輯
- 使用 Accessors/Mutators 處理資料轉換

### 資料庫查詢規範

- **優先使用 Model 方法**（如 `Item::create()`, `$item->update()`）
- 簡單查詢使用 **Eloquent 方法**（如 `where()`, `find()`, `first()`）
- **過於複雜的查詢**才考慮使用 Query Builder 或原生 SQL
- 避免 N+1 查詢（使用 Eager Loading）

---

## 4. 安全規範

### 絕對禁止
- 在程式碼中寫死 API key、密碼、token
- 使用 `eval()` 執行動態程式碼
- 未驗證的使用者輸入直接使用
- 將敏感資訊提交到 Git

### 必須遵守
- 敏感資訊從環境變數讀取（`.env`）
- 使用 Eloquent ORM（避免 SQL Injection）
- 所有輸入都要驗證（Form Request）
- API 路由要有權限驗證（Laravel Sanctum）

---

## 5. API 回應格式

所有成功回應統一使用 `data` 欄位，不區分單複數。

### 列表回應（多筆資料）
```json
{
  "success": true,
  "message": "取得成功",
  "data": [ ... ]
}
```

### 單一物件回應
```json
{
  "success": true,
  "message": "操作成功",
  "data": { ... }
}
```

### 分頁回應
```json
{
  "success": true,
  "message": "取得成功",
  "meta": { "current_page": 1, "per_page": 20, "total": 100 },
  "data": [ ... ]
}
```

### 其他資料回應（統計、設定等）
```json
{
  "success": true,
  "message": "取得成功",
  "data": { ... }
}
```

### 錯誤回應
```json
{
  "success": false,
  "message": "錯誤訊息",
  "errors": {
    "field": ["錯誤詳情"]
  }
}
```

---

## 6. Git Commit 規範

遵循 **Conventional Commits** 規範：

- `feat:` 新功能
- `fix:` 修復 bug
- `refactor:` 重構
- `test:` 測試相關
- `docs:` 文件更新

---

## 7. 測試規範

### 測試命名慣例
- **使用 `it_` 開頭命名測試方法**（2026 年主流）
- 格式：`it_should_[預期行為]_when_[條件]`
- 範例：`it_should_attach_images_when_valid_images_provided()`
- 需要在方法上加上 `@test` 註解讓 PHPUnit 識別

---

## 8. 當遇到不確定的情況

1. **先暫停，不要猜測**
2. **詢問使用者**
3. **參考專案中已有的類似實作**

---

## 檢查清單

### PHP 後端
- [ ] 符合 PSR-12 標準
- [ ] 遵循 SOLID 原則
- [ ] 使用適當的分層架構（Controller → Service → Model）
- [ ] 優先使用 Model 方法，複雜查詢才用 Query Builder
- [ ] 有 PHPDoc 註解（中文）
- [ ] 註解中不使用 icon（表情符號）
- [ ] 避免 N+1 查詢
- [ ] 使用 Form Request 驗證
- [ ] 使用 Resource 轉換回應

### JavaScript/Vue.js 前端
- [ ] 使用 Vue 3 Composition API
- [ ] 使用 `<script setup>` 語法
- [ ] 避免使用 `var`
- [ ] 使用 dayjs（不使用 moment.js）
- [ ] 註解中不使用 icon（表情符號）

### 安全
- [ ] 沒有寫死敏感資訊
- [ ] 使用 Eloquent ORM
- [ ] 驗證所有使用者輸入

### Git Commit
- [ ] Commit 訊息遵循 Conventional Commits 規範
- [ ] Commit 訊息清楚描述變更內容

---

**最後更新**: 2026-01-22  
**版本**: 1.1.0
