# syntax=docker/dockerfile:latest
FROM php:8.3.20-fpm-alpine3.21

ENV PHP_MEMORY_LIMIT=512M
ENV PHP_UPLOAD_LIMIT=16G
ENV PHP_MAX_TIME=3600
ENV SOURCE_LOCATION=/usr/src/nextcloud
ENV REDIS_DB_INDEX=0

# AIO settings start # Do not remove or change this line!
ENV NEXTCLOUD_VERSION=30.0.10
ENV AIO_TOKEN=123456
ENV AIO_URL=localhost
# AIO settings end # Do not remove or change this line!

COPY --chmod=775 *.sh /
COPY --chmod=774 upgrade.exclude /upgrade.exclude
COPY config/*.php /
COPY supervisord.conf /supervisord.conf
COPY root.motd /root.motd

VOLUME /mnt/ncdata
VOLUME /var/www/html

# Custom: change id of www-data user as it needs to be the same like on old installations
# hadolint ignore=SC2086,DL3003
RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache shadow; \
    deluser www-data; \
    addgroup -g 33 -S www-data; \
    adduser -u 33 -D -S -G www-data www-data; \
    \
# entrypoint.sh and cron.sh dependencies
    apk add --no-cache \
        rsync \
    ; \
# install the PHP extensions we need
# see https://docs.nextcloud.com/server/stable/admin_manual/installation/source_installation.html
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        autoconf \
        freetype-dev \
        gmp-dev \
        icu-dev \
        imagemagick-dev \
        imagemagick-svg \
        imagemagick-heic \
        imagemagick-tiff \
        libevent-dev \
        libjpeg-turbo-dev \
        libmcrypt-dev \
        libmemcached-dev \
        libpng-dev \
        libwebp-dev \
        libxml2-dev \
        libzip-dev \
        openldap-dev \
        pcre-dev \
        postgresql-dev \
    ; \
    \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-configure ftp --with-openssl-dir=/usr; \
    docker-php-ext-configure ldap; \
    docker-php-ext-install -j "$(nproc)" \
        bcmath \
        exif \
        gd \
        gmp \
        intl \
        ldap \
        opcache \
        pcntl \
        pdo_pgsql \
        sysvsem \
        zip \
    ; \
    \
# pecl will claim success even if one install fails, so we need to perform each install separately
    pecl install -o igbinary-3.2.16; \
    pecl install APCu-5.1.24; \
    pecl install -D 'enable-memcached-igbinary="yes"' memcached-3.3.0; \
    pecl install -oD 'enable-redis-igbinary="yes" enable-redis-zstd="yes" enable-redis-lz4="yes"' redis-6.2.0; \
   pecl install -o imagick-3.8.0; \
    \
    docker-php-ext-enable \
        igbinary \
        apcu \
        memcached \
        redis \
    ; \
    rm -r /tmp/pear; \
    \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache --virtual .nextcloud-phpext-rundeps $runDeps; \
    apk del .build-deps; \
    \
    { \
        echo 'apc.serializer=igbinary'; \
        echo 'session.serialize_handler=igbinary'; \
    } >> /usr/local/etc/php/conf.d/docker-php-ext-igbinary.ini; \
    \
# set recommended PHP.ini settings
# see https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/server_tuning.html#enable-php-opcache
    { \
        echo 'opcache.max_accelerated_files=10000'; \
        echo 'opcache.memory_consumption=256'; \
        echo 'opcache.interned_strings_buffer=64'; \
        echo 'opcache.save_comments=1'; \
        echo 'opcache.revalidate_freq=60'; \
        echo 'opcache.jit=1255'; \
        echo 'opcache.jit_buffer_size=8M'; \
    } > /usr/local/etc/php/conf.d/opcache-recommended.ini; \
    \
    echo 'apc.enable_cli=1' >> /usr/local/etc/php/conf.d/docker-php-ext-apcu.ini; \
    \
    { \
        echo 'memory_limit=${PHP_MEMORY_LIMIT}'; \
        echo 'upload_max_filesize=${PHP_UPLOAD_LIMIT}'; \
        echo 'post_max_size=${PHP_UPLOAD_LIMIT}'; \
        echo 'max_execution_time=${PHP_MAX_TIME}'; \
        echo 'max_input_time=${PHP_MAX_TIME}'; \
        echo 'default_socket_timeout=${PHP_MAX_TIME}'; \
    } > /usr/local/etc/php/conf.d/nextcloud.ini; \
    \
    { \
        echo 'session.save_handler = redis'; \
        echo 'session.save_path = "tcp://${REDIS_HOST}:6379?database=${REDIS_DB_INDEX}${REDIS_USER_AUTH}&auth[]=${REDIS_HOST_PASSWORD}"'; \
        echo 'redis.session.locking_enabled = 1'; \
        echo 'redis.session.lock_retries = -1'; \
        echo 'redis.session.lock_wait_time = 10000'; \
        echo 'session.gc_maxlifetime = 86400'; \
    } > /usr/local/etc/php/conf.d/redis-session.ini; \
    \
    mkdir -p /var/www/data; \
    chown -R www-data:root /var/www; \
    chmod -R g=u /var/www; \
    \
# Download Nextcloud archive start # Do not remove or change this line!
    apk add --no-cache --virtual .fetch-deps \
        bzip2 \
        gnupg \
    ; \
    \
    curl -fsSL -o nextcloud.tar.bz2 \
        "https://download.nextcloud.com/server/releases/nextcloud-${NEXTCLOUD_VERSION}.tar.bz2"; \
    curl -fsSL -o nextcloud.tar.bz2.asc \
        "https://download.nextcloud.com/server/releases/nextcloud-${NEXTCLOUD_VERSION}.tar.bz2.asc"; \
    export GNUPGHOME="$(mktemp -d)"; \
# gpg key from https://nextcloud.com/nextcloud.asc
    gpg --batch --keyserver keyserver.ubuntu.com  --recv-keys 28806A878AE423A28372792ED75899B9A724937A; \
    gpg --batch --verify nextcloud.tar.bz2.asc nextcloud.tar.bz2; \
    tar -xjf nextcloud.tar.bz2 -C /usr/src/; \
    gpgconf --kill all; \
    rm nextcloud.tar.bz2.asc nextcloud.tar.bz2; \
    mkdir -p /usr/src/nextcloud/data; \
    mkdir -p /usr/src/nextcloud/custom_apps; \
    chmod +x /usr/src/nextcloud/occ; \
    mkdir -p /usr/src/nextcloud/config; \
    apk del .fetch-deps; \
# Download Nextcloud archive end # Do not remove or change this line!
    mv /*.php /usr/src/nextcloud/config/; \
    \
# Template from https://github.com/nextcloud/docker/blob/master/.examples/dockerfiles/full/fpm-alpine/Dockerfile
    apk add --no-cache \
        ffmpeg \
        procps \
        samba-client \
        supervisor \
#       libreoffice \
    ; \
    \
    apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        imap-dev \
        krb5-dev \
        openssl-dev \
        samba-dev \
        bzip2-dev \
        libpq-dev \
    ; \
    \
    docker-php-ext-configure imap --with-kerberos --with-imap-ssl; \
    docker-php-ext-install \
        bz2 \
        imap \
        pgsql \
        ftp \
    ; \
    pecl install smbclient; \
    docker-php-ext-enable smbclient; \
    \
    runDeps="$( \
        scanelf --needed --nobanner --format '%n#p' --recursive /usr/local/lib/php/extensions \
            | tr ',' '\n' \
            | sort -u \
            | awk 'system("[ -e /usr/local/lib/" $1 " ]") == 0 { next } { print "so:" $1 }' \
    )"; \
    apk add --no-cache --virtual .nextcloud-phpext-rundeps $runDeps; \
    apk del .build-deps; \
    \
    mkdir -p \
    /var/log/supervisord \
    /var/run/supervisord \
    ; \
    chown www-data:root -R /var/log/supervisord; \
    chown www-data:root -R /var/run/supervisord; \
    \
    apk add --no-cache \
        bash \
        netcat-openbsd \
        openssl \
        gnupg \
        git \
        postgresql-client \
        tzdata \
        sudo \
        grep \
        nodejs \
        libreoffice \
        bind-tools \
        imagemagick \
        imagemagick-svg \
        imagemagick-heic \
        imagemagick-tiff \
        coreutils; \
    \
    grep -q '^pm = dynamic' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm = dynamic/pm = ondemand/' /usr/local/etc/php-fpm.d/www.conf; \
# Sync this with max db connections and MaxRequestWorkers
# We don't actually expect so many children but don't want to limit it artificially because people will report issues otherwise.
# Also children will usually be terminated again after the process is done due to the ondemand setting
    sed -i 's/^pm.max_children =.*/pm.max_children = 5000/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|access.log = /proc/self/fd/2|access.log = /proc/self/fd/1|' /usr/local/etc/php-fpm.d/docker.conf; \
    \
# AIO cloning start # Do not remove or change this line!
    rm -rf /tmp/nextcloud-aio && \
    mkdir -p /tmp/nextcloud-aio && \
    cd /tmp/nextcloud-aio && \
    git clone https://github.com/nextcloud-releases/all-in-one.git --depth 1 .; \
    mkdir -p /usr/src/nextcloud/apps/nextcloud-aio; \
    cp -r ./app/* /usr/src/nextcloud/apps/nextcloud-aio/; \
    echo "[ -n \"\$TERM\" ] && cat /root.motd" >> /root/.bashrc; \
# AIO cloning end # Do not remove or change this line!
    \
    chown www-data:root -R /usr/src && \
    chown www-data:root -R /usr/local/etc/php/conf.d && \
    chown www-data:root -R /usr/local/etc/php-fpm.d && \
    chmod -R 777 /tmp; \
    rm -rf /usr/src/nextcloud/apps/updatenotification; \
    \
    mkdir -p /nc-updater; \
    chown -R www-data:www-data /nc-updater; \
    chmod -R 770 /nc-updater

# hadolint ignore=DL3002
USER root
ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
