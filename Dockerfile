FROM php:8.4.8-fpm-bookworm
LABEL maintainer="Edie Pasek"

COPY . /var/www//html/
COPY ./env /var/www/html/.env

RUN sed -i 's/bookworm/testing/'g /etc/apt/sources.list.d/debian.sources \
   && apt-get update --allow-releaseinfo-change && apt-get dist-upgrade -y && apt-get install locales -y \
   && echo "en_US.UTF-8 UTF-8" >> /etc/locale.gen && locale-gen \
   && apt-get install --allow-remove-essential -yf gnupg gnupg-agent wget cron gettext-base dnsutils libmagickwand-dev imagemagick \
   libzip-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev xml-core unzip libssl-dev libonig-dev \
   libicu-dev libxml2 libxml2-dev git jq libxslt-dev ssmtp mailutils vim \
   && docker-php-ext-configure gd --with-freetype=/usr/include/ --with-jpeg=/usr/include/ \
   && docker-php-ext-configure pcntl --enable-pcntl \
   && docker-php-ext-install -j$(nproc) bcmath exif gettext gd zip iconv intl soap mbstring dom shmop sockets sysvmsg sysvsem sysvshm xsl \
   && pecl install imagick mongodb \
   && docker-php-ext-enable exif gettext shmop sockets sysvmsg sysvsem sysvshm xsl zip imagick mongodb \
   && apt-get purge -y --auto-remove -o APT::AutoRemove::RecommendsImportant=false gnupg gnupg-agent \
   && rm -rf /var/lib/apt/lists/* /usr/local/etc/php-fpm.d/* \
   && cd /tmp && php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && php /tmp/composer-setup.php --install-dir=/usr/bin && php -r "unlink('composer-setup.php');" \
   && mv /usr/bin/composer.phar /usr/bin/composer

COPY ./php.ini /usr/local/etc/php/php.ini
RUN git config --global --add safe.directory /var/www/html
