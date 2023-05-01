# Caddy is a requirement
FROM caddy:2.6.4-alpine as caddy

FROM httpd:2.4.57-alpine3.17

COPY --from=caddy /usr/bin/caddy /usr/bin/caddy

COPY --chown=www-data:www-data Caddyfile /Caddyfile
COPY --chmod=664 nextcloud.conf /usr/local/apache2/conf/nextcloud.conf
COPY --chmod=664 nextcloud /nextcloud
COPY --chmod=664 caddy-imports/* /caddy-imports/
COPY --chmod=664 supervisord.conf /supervisord.conf
COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

VOLUME /mnt/data

RUN set -ex; \
    apk add --no-cache shadow; \
    groupmod -g 333 xfs; \
    usermod -u 333 -g 333 xfs; \
    groupmod -g 33 www-data; \
    usermod -u 33 -g 33 www-data; \
    apk del --no-cache shadow; \
    \
    mkdir -p /mnt/data; \
    chown -R www-data:www-data /mnt/data; \
    \
    apk add --no-cache \
        bash \
        supervisor \
        tzdata \
        ca-certificates \
        openssl \
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
            -e 's/\(LoadModule .*mod_mpm_worker.so\)/#\1/' \
            -e 's/\(LoadModule .*mod_mpm_prefork.so\)/#\1/' \
        /usr/local/apache2/conf/httpd.conf; \
    echo "Include conf/nextcloud.conf" | tee -a /usr/local/apache2/conf/httpd.conf; \
    echo "ServerName localhost" | tee -a /usr/local/apache2/conf/httpd.conf; \
    \
    rm -rf /usr/local/apache2/conf/original /var/www; \
    mkdir -p /var/www; \
    chown -R www-data:www-data /var/www; \
    \
    mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord; \
    chown www-data:www-data /var/run/supervisord; \
    chown www-data:www-data /var/log/supervisord; \
    \
    chown -R www-data:www-data /usr/local/apache2; \
    chmod +r -R /usr/local/apache2; \
    \
    echo "root:$(openssl rand -base64 12)" | chpasswd

USER www-data

ENTRYPOINT ["/start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.monitor-only="true"
