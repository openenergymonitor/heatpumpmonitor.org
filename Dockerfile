FROM php:8.4-apache
RUN docker-php-ext-install mysqli

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

COPY config/php.ini /usr/local/etc/php/

COPY config/heatpumpmonitororg.conf /etc/apache2/sites-available/heatpumpmonitororg.conf
RUN a2dissite 000-default.conf
RUN a2ensite heatpumpmonitororg

COPY www/example.settings.php www/settings.php

COPY load_dev_env_data.php /var/
# CMD [ "php", "load_dev_env_data.php" ]
