# Optional local build only; compose uses image + command (no build). See repo docker-compose.yml.
FROM eclipse-mosquitto:2.0
COPY mosquitto.conf /mosquitto/config/mosquitto.conf
RUN touch /mosquitto/config/passwd \
    && /usr/bin/mosquitto_passwd -b /mosquitto/config/passwd emonpi emonpimqtt2016
