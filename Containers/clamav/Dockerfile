# Probably from this file: https://github.com/Cisco-Talos/clamav/blob/main/Dockerfile
FROM clamav/clamav:1.0.0-1

RUN apk add --update --no-cache tzdata
COPY clamav.conf /tmp/
RUN cat /tmp/clamav.conf >> /etc/clamav/clamd.conf
