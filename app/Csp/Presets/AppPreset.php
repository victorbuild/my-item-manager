<?php

namespace App\Csp\Presets;

use Spatie\Csp\Directive;
use Spatie\Csp\Keyword;
use Spatie\Csp\Policy;
use Spatie\Csp\Preset;

/**
 * 應用程式 CSP 白名單設定
 *
 * 涵蓋 reCAPTCHA、Tailwind inline style、bunny.net 字型、Google Cloud Storage 圖片。
 */
class AppPreset implements Preset
{
    /**
     * 設定 CSP 指令白名單
     */
    public function configure(Policy $policy): void
    {
        $policy
            // 預設所有資源只允許同源，未被其他指令覆蓋的資源類型皆套用此規則
            ->add(Directive::DEFAULT, Keyword::SELF)

            // JavaScript 來源
            // 'self'：應用程式自身的 JS（Vite 打包產物）
            // google.com / gstatic.com：reCAPTCHA v3 需要從 Google 載入驗證腳本
            ->add(Directive::SCRIPT, Keyword::SELF)
            ->add(Directive::SCRIPT, 'https://www.google.com')
            ->add(Directive::SCRIPT, 'https://www.gstatic.com')

            // CSS 來源
            // 'self'：應用程式自身的 CSS
            // 'unsafe-inline'：Tailwind CSS 4 會產生 inline style，目前無法避免
            // fonts.bunny.net：welcome.blade.php 載入 Instrument Sans 字型的 CSS
            ->add(Directive::STYLE, Keyword::SELF)
            ->add(Directive::STYLE, Keyword::UNSAFE_INLINE)
            ->add(Directive::STYLE, 'https://fonts.bunny.net')

            // 字型檔案來源
            // 'self'：本地字型
            // fonts.bunny.net：Instrument Sans 字型檔（woff2 等）
            ->add(Directive::FONT, Keyword::SELF)
            ->add(Directive::FONT, 'https://fonts.bunny.net')

            // 圖片來源
            // 'self'：本地上傳的圖片
            // data:：base64 編碼的圖片（部分 UI 元件或預覽縮圖）
            // storage.googleapis.com：Google Cloud Storage 儲存的物品圖片
            ->add(Directive::IMG, Keyword::SELF)
            ->add(Directive::IMG, 'data:')
            ->add(Directive::IMG, 'https://storage.googleapis.com')

            // iframe 來源
            // google.com：reCAPTCHA v3 在背景建立隱藏 iframe 進行行為分析
            ->add(Directive::FRAME, 'https://www.google.com')

            // fetch / XHR / WebSocket 連線目標
            // 'self'：所有 API 請求只打自己的後端
            // google.com：reCAPTCHA v3 驗證時會透過 XHR 打 Google 的 /recaptcha/api2/clr
            ->add(Directive::CONNECT, Keyword::SELF)
            ->add(Directive::CONNECT, 'https://www.google.com')

            // 禁止 Flash、Java Applet 等舊式 Plugin，現代瀏覽器已不支援，全面封鎖
            ->add(Directive::OBJECT, Keyword::NONE)

            // 限制 <base> 標籤只能設定同源 URL，防止攻擊者注入 <base> 竄改相對路徑
            ->add(Directive::BASE, Keyword::SELF);
    }
}
