# HeatpumpMonitor.org

## Table of Contents
- [Overview](##Overview)
- [Installation](##Installation)
- [Contributing](##Contributing)
- [Community](##Community)

## Overview
HeatpumpMonitor.org is an open-source community initiative to share and compare real-world heat pump performance data.

### See: [https://heatpumpmonitor.org](https://heatpumpmonitor.org)

![heatpumpmonitor.png](heatpumpmonitor.png)

- You can see a variety of heat pump installations, with information about the installation and the property.
- Displays performance data like Coefficient of Performance (COP) and Seasonal Performance Factor (SPF).
- Stats for combined space heating and hot water, space heating only, water heating only, and cooling.   

## Installation
Prerequisites:
- Apache2 web server
- MariaDB or MySQL database
- PHP (version 7.4 or later)

Install on existing Apache2 server
Clone the repository:

    git clone https://github.com/openenergymonitor/heatpumpmonitor.org.git

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

If you are using Docker

    docker-compose build
    docker-compose up

Site should now be running on http://localhost:8080

## Contributing

We welcome contributions! To contribute:

1. Fork the Repository: Create a fork of the repository on GitHub.
2. Create a Branch: Create a new branch for your feature or bug fix.
3. Make Changes: Implement your changes and write clear commit messages.
4. Push Changes: Push your changes to your forked repository.
5. Create a Pull Request: Submit a pull request to the main repository.

## Community

Having questions or need help with HeatpumpMonitor.org? 
Join the active community forum for discussions, troubleshooting, and sharing experiences:

[OpenEnergyMonitor Community Forum](https://community.openenergymonitor.org/)

