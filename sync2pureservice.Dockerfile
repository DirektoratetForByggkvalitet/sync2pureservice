FROM php:cli-alpine

# Nødvendige pakker
RUN apk add --no-cache curl bash tar openssl xz git lz4

# Installerer PHP-extensions med https://github.com/mlocati/docker-php-extension-installer
ADD --chmod=0755 https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN install-php-extensions @composer opcache bcmath bz2 gd msgpack igbinary lz4 redis ldap pcntl gettext mysqli pdo_mysql intl exif zip imagick 

ENTRYPOINT [ "top", "-b" ]
