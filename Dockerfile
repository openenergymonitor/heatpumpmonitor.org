FROM php:8.0-apache
RUN docker-php-ext-install mysqli

RUN a2enmod rewrite

COPY config/php.ini /usr/local/etc/php/

COPY config/heatpumpmonitororg.conf /etc/apache2/sites-available/heatpumpmonitororg.conf
RUN a2dissite 000-default.conf
RUN a2ensite heatpumpmonitororg

# COPY load_dev_env_data.php load_dev_env_data.php
# CMD [ "php", "load_dev_env_data.php" ]
