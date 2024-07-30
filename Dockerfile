FROM php:cli-alpine

# Nødvendige pakker
RUN apk add --no-cache curl bash tar openssl xz git lz4

# Installerer PHP-extensions med https://github.com/mlocati/docker-php-extension-installer
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions apcu opcache bcmath igbinary memcached gd zip @composer pdo mbstring xml curl ctype dom pcre openssl session tokenizer imagick/imagick@master redis

ENTRYPOINT [ "top", "-b" ]
