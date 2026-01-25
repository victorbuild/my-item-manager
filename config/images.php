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

    /**
     * 預覽圖設定
     */
    'preview' => [
        'width' => env('IMAGE_PREVIEW_WIDTH', 600),
        'height' => env('IMAGE_PREVIEW_HEIGHT', 800),
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
];
