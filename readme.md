# HeatpumpMonitor.org

Source code for table of heat pumps with public dashboards

Install public site content in /var/www

    sudo ln -s /home/oem/heatpumpmonitor.org/www/ /var/www/hpmon
    
Setup crontab  to pull in list (e.g once an hour):

    0 * * * * /home/oem/heatpumpmonitor.org/update.sh >> /home/oem/hpmon.log

