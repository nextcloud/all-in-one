# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/0.105/alpine/Dockerfile
FROM clamav/clamav:0.105.1-7

RUN apk add --no-cache tzdata
COPY clamav.conf /tmp/
RUN cat /tmp/clamav.conf >> /etc/clamav/clamd.conf
