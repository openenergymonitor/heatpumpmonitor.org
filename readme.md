# HeatpumpMonitor.org

An open source initiative to share and compare heat pump performance data.

### See: [https://heatpumpmonitor.org](https://heatpumpmonitor.org)

![heatpumpmonitor.png](heatpumpmonitor.png)

------------------------------------------------------------------------------------------------------------------

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

Access the site by putting http://localhost/heatpumpmonitororg/ in the browser.

Login using 'Self hosted data' and username and password: admin:admin

-----------------------------------------------------------------------------------------------------------------------

## Run using Docker

Running the system through Docker might not using as much effort since the system already has its own Docker config.

1. Locate the location of the file in your workspace first using command below in the terminal.
(Remember, file location and name might be different due to user's own customization)

    C:\\workspace\heatpumpmonitor.org

2. Proceed by inserting these two commands.

    docker-compose build
    docker-compose up

Site should now be running on http://localhost:8080


