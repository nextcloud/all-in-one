FROM docker.io/library/composer:2@sha256:4ed71c38c6d788eb4e4e3747b5332f5b40d5b5a6fcadd5062a1a70986a118e0a

ARG APCU_VERSION=5.1.28

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN mkdir -p /usr/src/php/ext/apcu \
 && curl -fsSL --retry 5 "https://github.com/krakjoe/apcu/archive/refs/tags/v$APCU_VERSION.tar.gz" \
  | tar -xz --strip-components=1 -C /usr/src/php/ext/apcu \
 && docker-php-ext-install apcu
