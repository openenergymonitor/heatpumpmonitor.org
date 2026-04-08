#!/bin/sh
# Same steps as docker-compose.yml mqtt `command` (run inside container if debugging).
touch /mosquitto/config/passwd
/usr/bin/mosquitto_passwd -b /mosquitto/config/passwd emonpi emonpimqtt2016
