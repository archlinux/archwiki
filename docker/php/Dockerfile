FROM php:8.4-fpm-alpine

RUN apk add imagemagick git diffutils

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions opcache apcu intl gd mysqli

RUN echo -e "error_reporting = E_ALL & ~E_DEPRECATED" > $PHP_INI_DIR/conf.d/error_reporting.ini
