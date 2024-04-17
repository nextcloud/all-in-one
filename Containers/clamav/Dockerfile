# syntax=docker/dockerfile:latest

# DL3029 warning: Do not use --platform flag with FROM
# hadolint global ignore=DL3029

# Step 1: Setup base image based on arch

# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.3/alpine/Dockerfile

FROM --platform=linux/amd64 clamav/clamav:1.3.0-47 AS build-amd64

# DL3018 warning: Pin versions in apk add. Instead of `apk add <package>` use `apk add <package>=<version>`
# hadolint ignore=DL3018
RUN set -ex; \
    apk add --no-cache tzdata;

# Probably from this file: https://github.com/Cisco-Talos/clamav-docker/blob/main/clamav/1.3/debian/Dockerfile
FROM --platform=linux/arm64 clamav/clamav-debian:1.3.0-24 AS build-arm64

# DL3008 warning: Pin versions in apt get install. Instead of `apt-get install <package>` use `apt-get install <package>=<version>`
# hadolint ignore=DL3008
RUN set -ex; \
    apt-get update; \
    apt-get install --no-install-recommends -y \
        tzdata \
    ; \
    rm -vrf /var/lib/apt/lists/*;

# Step 2: Configure clamav

# DL3006 warning: Always tag the version of an image explicitly
# hadolint ignore=DL3006
FROM build-${TARGETARCH}

COPY clamav.conf /tmp/clamav.conf

RUN cat /tmp/clamav.conf >> /etc/clamav/clamd.conf; \
    rm /tmp/clamav.conf; \
    mkdir -p /var/run/clamav /run/lock; \
    chown -R clamav:clamav /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock; \
    chmod 777 -R /var/run/clamav /run/clamav /var/log/clamav /var/lock /run/lock /tmp


VOLUME /var/lib/clamav

USER clamav

LABEL com.centurylinklabs.watchtower.enable="false"
