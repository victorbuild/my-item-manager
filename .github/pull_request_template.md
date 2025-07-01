# 🖼️ 圖片瀑布牆、Lightbox 預覽與 Vite 打包優化

## 📝 PR 說明

- 圖片區塊改為瀑布流（Masonry）排列，提升視覺美觀與空間利用
- 新增圖片 Lightbox 預覽，支援放大、拖移、手機雙指縮放、左右切換
- 調整 Vite 打包設定，將第三方套件（vue, vue-router, axios, dayjs, panzoom）分割為 vendor chunk，優化生產環境 JS 檔案大小與載入效能

<!-- 建議：可於此處附上截圖，讓 Reviewer 快速了解 UI/UX 變化
例如：
![masonry-demo](https://user-images.githubusercontent.com/xxx/xxx.png)
-->

## 主要變更

- 圖片牆改為 CSS columns 實現瀑布流
- 新增 Lightbox 預覽功能，支援滑鼠與觸控操作
- 動態載入 panzoom 套件，支援圖片縮放與拖移
- 新增 `build.rollupOptions.output.manualChunks` 設定於 `vite.config.js`
- 讓主程式與第三方依賴分開載入

## 測試方式

1. `npm run build`
2. 確認圖片牆為瀑布流排列，點擊圖片可開啟 Lightbox 並可縮放、拖移、切換
3. 確認 `public/build/assets/vendor-*.js` 檔案產生
4. 網站功能正常，載入速度無異常

## 影響範圍

- 前端圖片顯示與預覽體驗
- 前端打包產物，不影響功能

## 其他說明

- 若需調整 chunk 分割，可再於 `vite.config.js` 修改 `manualChunks` 設定
