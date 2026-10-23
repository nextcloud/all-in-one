FROM docker.io/library/composer:2@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc

ARG APCU_VERSION=5.1.28

SHELL ["/bin/ash", "-o", "pipefail", "-c"]
RUN mkdir -p /usr/src/php/ext/apcu \
 && curl -fsSL --retry 5 "https://github.com/krakjoe/apcu/archive/refs/tags/v$APCU_VERSION.tar.gz" \
  | tar -xz --strip-components=1 -C /usr/src/php/ext/apcu \
 && docker-php-ext-install apcu
