FROM php:8.4-apache
RUN docker-php-ext-install mysqli

# Install GD and EXIF extensions for image manipulation (required for thumbnails)
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libwebp-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install gd \
    && docker-php-ext-install exif

RUN a2enmod rewrite

RUN apt-get update \
    && apt-get -y install locales \
    && apt-get -y install gettext \
    && apt-get -y install poedit \
    && sed -i -e 's/# en_US.UTF-8 UTF-8/en_US.UTF-8 UTF-8/' /etc/locale.gen \
    && sed -i -e 's/# en_US ISO-8859-1/en_US ISO-8859-1/' /etc/locale.gen \
    && sed -i -e 's/# en_US.ISO-8859-15 ISO-8859-15/en_US.ISO-8859-15 ISO-8859-15/' /etc/locale.gen \
    && dpkg-reconfigure --frontend=noninteractive locales \
    && update-locale \
    && docker-php-ext-configure gettext \
    && docker-php-ext-install gettext

RUN pecl install xdebug 

COPY config/php.ini /usr/local/etc/php/
COPY config/xdebug.ini /usr/local/etc/php/conf.d/xdebug.ini

COPY config/heatpumpmonitororg.conf /etc/apache2/sites-available/heatpumpmonitororg.conf

RUN a2dissite 000-default.conf
RUN a2ensite heatpumpmonitororg

COPY www/example.settings.php www/settings.php

COPY load_dev_env_data.php /var/
COPY generate_thumbnails.php /var/
# CMD [ "php", "load_dev_env_data.php" ]
