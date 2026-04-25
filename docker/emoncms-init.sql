-- Runs once on first MariaDB data-dir init only.
-- heatpumpmonitor user is created by the image from MYSQL_USER / MYSQL_DATABASE (heatpumpmonitor DB only).
-- Separate emoncms user for the emoncms application schema only.
-- Core emoncms tables are created by the emoncms container (sql_ready.sh + emoncmsdbupdate.php).
CREATE DATABASE IF NOT EXISTS emoncms;
CREATE USER IF NOT EXISTS 'emoncms'@'%' IDENTIFIED BY 'emoncms';
GRANT ALL PRIVILEGES ON emoncms.* TO 'emoncms'@'%';
FLUSH PRIVILEGES;
