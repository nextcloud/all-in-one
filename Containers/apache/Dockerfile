# syntax=docker/dockerfile:latest
FROM caddy:2.11.2-alpine AS caddy

# From https://github.com/docker-library/httpd/blob/master/2.4/alpine/Dockerfile
FROM httpd:2.4.66-alpine3.23

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy

COPY --chown=33:33 Caddyfile /Caddyfile
COPY --chmod=664 nextcloud.conf /usr/local/apache2/conf/nextcloud.conf
COPY --chmod=664 supervisord.conf /supervisord.conf
COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

VOLUME /mnt/data

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache shadow; \
    groupmod -g 33 www-data; \
    usermod -u 33 -g 33 www-data; \
    apk del --no-cache shadow; \
    \
    mkdir -p /mnt/data; \
    chown -R www-data:www-data /mnt/data; \
    chown -R 777 /tmp; \
    \
    apk add --no-cache \
        bash \
        supervisor \
        tzdata \
        ca-certificates \
        openssl \
        bind-tools \
        netcat-openbsd; \
    \
    sed -i \
            -e '/^Listen /d' \
            -e 's/^#\(LoadModule .*mod_rewrite.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_headers.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_proxy.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_proxy_fcgi.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_setenvif.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_env.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_mime.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_dir.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_authz_core.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_alias.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_mpm_event.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_brotli.so\)/\1/' \
            -e 's/^#\(LoadModule .*mod_http2.so\)/\1/' \
            -e 's/\(LoadModule .*mod_mpm_worker.so\)/#\1/' \
            -e 's/\(LoadModule .*mod_mpm_prefork.so\)/#\1/' \
            -e 's/\(ScriptAlias \)/#\1/' \
        /usr/local/apache2/conf/httpd.conf; \
    echo "Include conf/nextcloud.conf" | tee -a /usr/local/apache2/conf/httpd.conf; \
    echo "ServerName localhost" | tee -a /usr/local/apache2/conf/httpd.conf; \
# Tune mpm_event settings; ServerLimit = MaxRequestWorkers / ThreadsPerChild = 400 / 25 = 16
    sed -i 's|MaxRequestWorkers.*|MaxRequestWorkers 400|' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    grep -q '<IfModule mpm_event_module>' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    sed -i '/<IfModule mpm_event_module>/a\ \ \ \ ServerLimit 16' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    sed -i 's|ThreadsPerChild.*|ThreadsPerChild 25|' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    sed -i 's|StartServers.*|StartServers 2|' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    sed -i 's|MinSpareThreads.*|MinSpareThreads 25|' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    sed -i 's|MaxSpareThreads.*|MaxSpareThreads 75|' /usr/local/apache2/conf/extra/httpd-mpm.conf; \
    \
    rm -rf /usr/local/apache2/conf/original /var/www; \
    mkdir -p /var/www; \
    chown -R www-data:www-data /var/www; \
    \
    mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord; \
    chown www-data:www-data /var/run/supervisord; \
    chown www-data:www-data /var/log/supervisord; \
    chmod 777 /var/run/supervisord; \
    chmod 777 /var/log/supervisord; \
    \
    chown -R www-data:www-data /usr/local/apache2; \
    chmod +r -R /usr/local/apache2; \
    mkdir -p /usr/local/apache2/logs; \
    chmod 777 -R /home/www-data; \
    chmod 777 -R /usr/local/apache2/logs; \
    rm -rf /usr/local/apache2/cgi-bin/; \
    \
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    apk --no-cache del openssl

USER 33

ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Apache and Caddy for Nextcloud AIO" \
    org.opencontainers.image.description="Apache HTTP server with Caddy for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
