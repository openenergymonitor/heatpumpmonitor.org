# HeatpumpMonitor.org

An open source initiative to share and compare heat pump performance data.

### See: [https://heatpumpmonitor.org](https://heatpumpmonitor.org)

![heatpumpmonitor.png](heatpumpmonitor.png)

## Install on existing Apache2 server

Install public site content in /var/www

    sudo ln -s /home/USERNAME/heatpumpmonitor.org/www/ /var/www/heatpumpmonitororg
    
Create a mariadb/mysql database:

    CREATE DATABASE heatpumpmonitor;
    
Copy example.settings.php:

    cp www/example.settings.php settings.php
    
Modify database credentials to match your system

Load public data from heatpumpmonitor.org to create functioning development environment

    php load_dev_env_data.php

Login using 'Self hosted data' and username and password: admin:admin

## Run using Docker

    docker-compose build
    docker-compose up

Site should now be running on http://localhost:8080
