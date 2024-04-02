# syntax=docker/dockerfile:latest

# Step 1: Setup base image based on arch

# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.3/alpine/Dockerfile
FROM --platform=linux/amd64 clamav/clamav:1.3.0-44 as build-amd64

RUN set -ex; \
    apk add --no-cache tzdata;

# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.3/debian/Dockerfile
FROM --platform=linux/arm64 clamav/clamav-debian:1.3.0-24 as build-arm64

RUN set -ex; \
    apt-get update; \
    apt-get install --no-install-recommends -y \
        tzdata \
    ; \
    rm -vrf /var/lib/apt/lists/*;

# Step 2: Configure clamav

FROM build-${TARGETARCH}

RUN cat /tmp/clamav.conf >> /etc/clamav/clamd.conf; \
    rm /tmp/clamav.conf; \
    mkdir -p /var/run/clamav /run/lock; \
    chown -R clamav:clamav /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock; \
    chmod 777 -R /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock /tmp


COPY clamav.conf /tmp/clamav.conf

VOLUME /var/lib/clamav

USER clamav

LABEL com.centurylinklabs.watchtower.enable="false"
