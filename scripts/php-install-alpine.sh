#!/bin/sh

# Nødvendige pakker
apk add --no-cache curl tar openssl xz

# Installerer PHP-extensions med https://github.com/mlocati/docker-php-extension-installer
curl -Lso /usr/local/bin/install-php-extensions https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions
chmod +x /usr/local/bin/install-php-extensions
install-php-extensions gd opcache @composer
