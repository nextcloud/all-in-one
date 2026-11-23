FROM docker.io/library/composer:2@sha256:50ea4d33fdcde1adb0f045547295faa1548b33872e5f789dae5b02ce2fb5de93

ARG APCU_VERSION=5.1.28
ARG APCU_SHA256=0e60ba9d0fa4021c57a70c071f3d8f71de236275d084e560d5959fec4edd8c32

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN curl -fsSL --retry 5 -o /tmp/apcu.tar.gz "https://github.com/krakjoe/apcu/archive/refs/tags/v$APCU_VERSION.tar.gz" \
 && echo "$APCU_SHA256  /tmp/apcu.tar.gz" | sha256sum -c - \
 && mkdir -p /usr/src/php/ext/apcu \
 && tar -xzf /tmp/apcu.tar.gz --strip-components=1 -C /usr/src/php/ext/apcu \
 && rm /tmp/apcu.tar.gz \
 && docker-php-ext-install apcu
