<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 圖片上傳設定
    |--------------------------------------------------------------------------
    |
    | 此檔案定義圖片上傳、處理相關的配置
    |
    */

    /**
     * 最大檔案大小（單位：KB）
     * 預設：10MB (10240 KB)
     */
    'max_size' => env('IMAGE_MAX_SIZE', 10240),

    'max_pixels' => env('IMAGE_MAX_PIXELS', 60000000),

    /**
     * 預覽圖設定
     */
    'preview' => [
        'width' => env('IMAGE_PREVIEW_WIDTH', 1600),
        'height' => env('IMAGE_PREVIEW_HEIGHT', 1600),
        'quality' => env('IMAGE_PREVIEW_QUALITY', 85),
    ],

    /**
     * 縮圖設定
     */
    'thumb' => [
        'width' => env('IMAGE_THUMB_WIDTH', 300),
        'height' => env('IMAGE_THUMB_HEIGHT', 400),
        'quality' => env('IMAGE_THUMB_QUALITY', 75),
    ],

    /**
     * URL 過期時間設定（單位：分鐘）
     */
    'url_expiration_minutes' => [
        'upload' => env('IMAGE_URL_EXPIRATION_UPLOAD', 10), // 上傳時的回應 URL 過期時間
        'default' => env('IMAGE_URL_EXPIRATION_DEFAULT', 60), // 一般查詢時的 URL 過期時間
    ],

    /**
     * 簽署 URL 快取比 URL 本身提早失效的安全時間（分鐘）
     */
    'url_cache_safety_minutes' => env('IMAGE_URL_CACHE_SAFETY_MINUTES', 5),
];
