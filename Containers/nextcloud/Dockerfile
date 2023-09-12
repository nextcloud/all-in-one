FROM php:8.1.23-fpm-alpine3.18

ENV PHP_MEMORY_LIMIT 512M
ENV PHP_UPLOAD_LIMIT 10G
ENV PHP_MAX_TIME 3600
ENV NEXTCLOUD_VERSION 27.0.2
ENV AIO_TOKEN 123456
ENV AIO_URL localhost

COPY --chmod=775 *.sh /
COPY --chmod=774 upgrade.exclude /upgrade.exclude
COPY config/*.php /
COPY supervisord.conf /supervisord.conf

VOLUME /mnt/ncdata
VOLUME /var/www/html

# Custom: change id of www-data user as it needs to be the same like on old installations
RUN set -ex; \
    apk add --no-cache shadow; \
    deluser www-data; \
    groupmod -g 333 xfs; \
    usermod -u 333 -g 333 xfs; \
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
    pecl install APCu-5.1.22; \
    pecl install memcached-3.2.0; \
    pecl install redis-6.0.0; \
    pecl install imagick-3.7.0; \
    \
    docker-php-ext-enable \
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
    apk add --virtual .nextcloud-phpext-rundeps $runDeps; \
    apk del .build-deps; \
    \
# set recommended PHP.ini settings
# see https://docs.nextcloud.com/server/stable/admin_manual/configuration_server/server_tuning.html#enable-php-opcache
    { \
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
    } > /usr/local/etc/php/conf.d/nextcloud.ini; \
    \
    mkdir -p /var/www/data; \
    chown -R www-data:root /var/www; \
    chmod -R g=u /var/www; \
    \
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
    mv /*.php /usr/src/nextcloud/config/; \
    apk del .fetch-deps; \
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
    apk add --virtual .nextcloud-phpext-rundeps $runDeps; \
    apk del .build-deps; \
    \
    mkdir -p \
    /var/log/supervisord \
    /var/run/supervisord \
    ; \
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
        coreutils; \
    \
    grep -q '^pm = dynamic' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm = dynamic/pm = ondemand/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.max_children =.*/pm.max_children = 80/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.start_servers =.*/pm.start_servers = 2/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.min_spare_servers =.*/pm.min_spare_servers = 1/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's/^pm.max_spare_servers =.*/pm.max_spare_servers = 3/' /usr/local/etc/php-fpm.d/www.conf; \
    sed -i 's|access.log = /proc/self/fd/2|access.log = /proc/self/fd/1|' /usr/local/etc/php-fpm.d/docker.conf; \
    \
    rm -rf /tmp/nextcloud-aio && \
    mkdir -p /tmp/nextcloud-aio && \
    cd /tmp/nextcloud-aio && \
    git clone https://github.com/nextcloud-releases/all-in-one.git --depth 1 .; \
    mkdir -p /usr/src/nextcloud/apps/nextcloud-aio; \
    cp -r ./app/* /usr/src/nextcloud/apps/nextcloud-aio/; \
    \
    chown www-data:root -R /usr/src && \
    chown www-data:root -R /usr/local/etc/php/conf.d && \
    chown www-data:root -R /usr/local/etc/php-fpm.d && \
    chmod -R 777 /tmp; \
    rm -r /usr/src/nextcloud/apps/updatenotification; \
    \
    mkdir -p /nc-updater; \
    chown -R www-data:www-data /nc-updater; \
    chmod -R 770 /nc-updater; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd

USER root
ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK --start-period=60s CMD sudo -E -u www-data bash /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false"
