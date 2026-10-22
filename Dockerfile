# syntax=docker/dockerfile:1

# 將擴充套件的編譯工作獨立出來，正式映像檔只複製編譯結果，不帶入開發用函式庫。
FROM php:8.5.12-fpm-trixie AS extensions
ARG USE_TW_MIRROR=false
# 僅在建置時明確啟用台灣鏡像；保留官方來源備份，安全更新仍使用官方來源。
RUN case "$USE_TW_MIRROR" in \
      true) cp /etc/apt/sources.list.d/debian.sources /etc/apt/debian.sources.official \
        && sed -i 's|://deb.debian.org/debian$|://ftp.tw.debian.org/debian|' /etc/apt/sources.list.d/debian.sources ;; \
      false) ;; \
      *) echo 'USE_TW_MIRROR 僅接受 true 或 false' >&2; exit 1 ;; \
    esac
RUN apt-get update && apt-get install -y --no-install-recommends \
       libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
       libpq-dev libzip-dev libicu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j2 pdo_pgsql bcmath zip gd exif intl pcntl \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && rm -rf /var/lib/apt/lists/*

# 開發與正式環境共用相同的 PHP 擴充套件與 libvips，減少環境差異。
# 與編譯階段使用相同的 Debian 基底，避免擴充套件與系統函式庫不相容。
# libvips-tools 提供圖片轉檔指令；移除基底內的編譯工具，減少不必要的套件。
FROM php:8.5.12-fpm-trixie AS runtime
ARG USE_TW_MIRROR=false
# runtime 是獨立的基底階段，因此也需要套用鏡像選項；後續階段會繼承此設定。
RUN case "$USE_TW_MIRROR" in \
      true) cp /etc/apt/sources.list.d/debian.sources /etc/apt/debian.sources.official \
        && sed -i 's|://deb.debian.org/debian$|://ftp.tw.debian.org/debian|' /etc/apt/sources.list.d/debian.sources ;; \
      false) ;; \
      *) echo 'USE_TW_MIRROR 僅接受 true 或 false' >&2; exit 1 ;; \
    esac
RUN apt-get update && apt-get install -y --no-install-recommends \
       libfreetype6 libjpeg62-turbo libpng16-16t64 libwebp7 \
       libpq5 libzip5 libicu76 libvips-tools \
    && apt-get purge -y --auto-remove \
       autoconf dpkg-dev file g++ gcc libc6-dev make pkg-config re2c \
    && rm -rf /var/lib/apt/lists/*
COPY --from=extensions /usr/local/lib/php/extensions/ /usr/local/lib/php/extensions/
COPY --from=extensions /usr/local/etc/php/conf.d/ /usr/local/etc/php/conf.d/
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini
WORKDIR /var/www/html/my-item-manager

# 本機開發使用 --target development，保留 Composer、Git 與解壓縮工具。
# 正式階段不繼承此階段，因此不會帶入這些開發工具。
FROM runtime AS development
RUN apt-get update && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Node.js 只負責打包前端；正式映像檔只需要打包結果，不需要 Node.js 或 node_modules。
# 先安裝鎖定版本的相依套件，讓只修改前端程式碼時可以沿用套件安裝快取。
FROM node:22-bookworm-slim AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

# 在建置階段安裝正式環境需要的 PHP 套件，並整合前端產物。
# COPY 的範圍由 .dockerignore 排除憑證、環境設定與本機檔案，新增敏感檔案時仍須檢查。
FROM development AS backend
COPY . .
COPY --from=frontend /app/public/build ./public/build
RUN composer install --no-dev --no-interaction --optimize-autoloader \
    && chown -R www-data:www-data storage bootstrap/cache

# 預設建置最後的 production 階段，只帶入執行所需的環境與應用程式。
# 以 Supervisor 管理 Nginx 與 PHP-FPM，讓同一個容器提供 HTTP 服務。
FROM runtime AS production
# 配合非 root 執行，將 Nginx PID 放在可寫入的 /tmp，並調整必要目錄權限。
RUN apt-get update && apt-get install -y --no-install-recommends nginx supervisor \
    && sed -i '/^user /d; s|pid /run/nginx.pid;|pid /tmp/nginx.pid;|' /etc/nginx/nginx.conf \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx \
    && rm -rf /var/lib/apt/lists/*
COPY --from=backend /var/www/html/my-item-manager /var/www/html/my-item-manager
COPY .cicd/nginx.conf /etc/nginx/sites-enabled/default
COPY .cicd/supervisord.conf /etc/supervisor/supervisord.conf
RUN chown -R www-data:www-data storage bootstrap/cache
# 正式服務使用低權限帳號與 8080 連接埠，降低程序遭入侵後的影響範圍。
USER www-data
EXPOSE 8080
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisor/supervisord.conf"]
