# HeatpumpMonitor.org

An open source initiative to share and compare heat pump performance data.

### See: [https://heatpumpmonitor.org](https://heatpumpmonitor.org)

Install public site content in /var/www

    sudo ln -s /home/USERNAME/heatpumpmonitor.org/www/ /var/www/heatpumpmonitororg
    
Create a mariadb/mysql database:

    CREATE DATABASE heatpumpmonitor;
    
Copy example.settings.php:

    cp www/example.settings.php settings.php
    
Modify database credentials to match your system

Load public data from heatpumpmonitor.org to create functioning development environment

    php load_dev_env_data.php 

Login using 'Use another account' and username and password: admin:admin
