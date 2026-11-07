FROM docker.io/library/composer:2@sha256:8de2184c6de224f4689e8f80ed5f5edaf0030b6b8b7839a22a2bcb6722ac87d0

ARG APCU_VERSION=5.1.28

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN mkdir -p /usr/src/php/ext/apcu \
 && curl -fsSL --retry 5 "https://github.com/krakjoe/apcu/archive/refs/tags/v$APCU_VERSION.tar.gz" \
  | tar -xz --strip-components=1 -C /usr/src/php/ext/apcu \
 && docker-php-ext-install apcu
