FROM php:7-fpm-alpine

RUN apk add imagemagick git diffutils

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/bin/
RUN install-php-extensions opcache apcu intl gd mysqli
