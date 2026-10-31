<?php

return [
    // IP 總量較寬鬆，讓公司或學校共用網路仍可多人註冊。
    'registration' => [
        'per_minute' => (int) env('REGISTRATION_REQUESTS_PER_MINUTE', 20),
        'per_hour' => (int) env('REGISTRATION_REQUESTS_PER_HOUR', 100),
        'per_ip_email_minute' => (int) env('REGISTRATION_REQUESTS_PER_IP_EMAIL_MINUTE', 5),
    ],

    // 登入入口的 IP 總量；帳號與 IP 的失敗次數仍由 AuthService 管理。
    'login' => [
        'per_minute' => (int) env('LOGIN_REQUESTS_PER_MINUTE', 60),
        'per_hour' => (int) env('LOGIN_REQUESTS_PER_HOUR', 1000),
    ],
];
