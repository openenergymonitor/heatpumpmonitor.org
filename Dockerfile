FROM php:8.3-apache
RUN docker-php-ext-install mysqli pdo pdo_mysql

RUN a2enmod rewrite

COPY config/php.ini /usr/local/etc/php/

COPY config/heatpumpmonitororg.conf /etc/apache2/sites-available/heatpumpmonitororg.conf
RUN a2dissite 000-default.conf
RUN a2ensite heatpumpmonitororg

COPY www/example.settings.php www/settings.php

COPY load_dev_env_data.php /var/
# CMD [ "php", "load_dev_env_data.php" ]
