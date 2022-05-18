# Probably from this file: https://github.com/Cisco-Talos/clamav/blob/main/Dockerfile
FROM clamav/clamav:0.104.2-3

RUN apk add --update --no-cache tzdata
COPY clamav.conf /tmp/
RUN cat /tmp/clamav.conf >> /etc/clamav/clamd.conf
