FROM php:8.4-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    freetype \
    libjpeg-turbo \
    libpng \
    libwebp \
    libzip \
    imagemagick \
    shadow \
    libreoffice \
    openjdk17-jre-headless \
    fontconfig \
    ttf-dejavu \
    ttf-liberation \
    dbus

RUN mkdir -p /home/www-data/.config/libreoffice && \
    chown -R www-data:www-data /home/www-data

RUN apk add --no-cache --virtual .build-deps \
    build-base \
    autoconf \
    linux-headers \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    imagemagick-dev \
    libtool \
    pkgconfig \
    oniguruma-dev && \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp && \
    docker-php-ext-install gd zip bcmath mysqli mbstring opcache pcntl sockets && \
    pecl install imagick && \
    docker-php-ext-enable imagick && \
    apk del .build-deps && \
    rm -rf /tmp/* /var/cache/apk/*

# bolt.so — проприетарное расширение Битрикс (PHP 8.4, API=20240924)
COPY bolt.so /usr/local/lib/php/extensions/no-debug-non-zts-20240924/bolt.so
RUN echo "extension=bolt.so" > /usr/local/etc/php/conf.d/bolt.ini

ARG PUID=1000
ARG PGID=1000
RUN groupmod -g ${PGID} www-data && \
    usermod -u ${PUID} -g ${PGID} www-data

RUN mkdir -p /var/lib/php/sessions && \
    chown -R www-data:www-data /var/lib/php/sessions && \
    chmod 700 /var/lib/php/sessions

COPY php.ini /usr/local/etc/php/conf.d/bitrix.ini

WORKDIR /var/www/bitrix

USER www-data

EXPOSE 9000

CMD ["php-fpm"]
