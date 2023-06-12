FROM php:fpm-alpine

# Nødvendige pakker
RUN apk add --no-cache curl bash tar openssl xz

# Installerer PHP-extensions med https://github.com/mlocati/docker-php-extension-installer
RUN curl -Lso /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions && \
    chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions apcu opcache gd imagick zip @composer

ENTRYPOINT [ "/bin/bash" ]
