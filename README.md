# 物品管理系統

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
