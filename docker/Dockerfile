# 開発用イメージ（本番のさくらのレンタルサーバでは使わない）
FROM php:8.3-apache

RUN docker-php-ext-install pdo_mysql opcache

# Windows / Mac ではフォルダ共有が遅く、毎回 PHP ファイルを読み直すと画面が重くなる。
# OPcache でコンパイル結果を保持し、変更の確認は2秒おきに抑える（編集は2秒以内に反映される）。
COPY php.ini /usr/local/etc/php/conf.d/zz-dev.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && a2enmod rewrite

WORKDIR /var/www/html
