# Caddy is a requirement
FROM caddy:2.6.2-alpine as caddy

FROM httpd:2.4.55-alpine3.17

RUN set -ex; \
    apk add --no-cache shadow; \
    groupmod -g 333 xfs; \
    usermod -u 333 -g 333 xfs; \
    groupmod -g 33 www-data; \
    usermod -u 33 -g 33 www-data; \
    apk del --no-cache shadow

RUN mkdir -p /mnt/data; \
    chown www-data:www-data /mnt/data;

VOLUME /mnt/data

RUN set -ex; \
    apk add --no-cache \
        bash \
        supervisor \
        wget \
        tzdata \
        ca-certificates \
        openssl \
        netcat-openbsd

COPY --from=caddy /usr/bin/caddy /usr/bin/
RUN chmod +x /usr/bin/caddy

RUN sed -i \
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
                conf/httpd.conf; \
		echo "Include conf/nextcloud.conf" | tee -a conf/httpd.conf; \
                echo "ServerName localhost" | tee -a conf/httpd.conf
		
COPY nextcloud.conf conf

RUN set -ex; \
    rm -rf conf/original conf/original && \
    rm -rf /var/www/html/* && \
    mkdir /var/www && \
    chown -R www-data:www-data /var/www;

RUN mkdir /var/log/supervisord; \
    mkdir /var/run/supervisord; \
    chown www-data:www-data /var/run/supervisord; \
    chown www-data:www-data /var/log/supervisord;

COPY Caddyfile /

COPY start.sh /usr/bin/
COPY healthcheck.sh /usr/bin/
COPY supervisord.conf /
RUN chmod +x /usr/bin/start.sh; \
    chmod +x /usr/bin/healthcheck.sh; \
    chmod +r /supervisord.conf; \
    chown www-data:www-data /Caddyfile; \
    chown -R www-data:www-data /usr/local/apache2; \
    chmod +r -R /usr/local/apache2

# Give root a random password
RUN echo "root:$(openssl rand -base64 12)" | chpasswd

USER www-data

ENTRYPOINT ["start.sh"]
CMD ["/usr/bin/supervisord", "-c", "/supervisord.conf"]

HEALTHCHECK CMD healthcheck.sh
LABEL com.centurylinklabs.watchtower.monitor-only="true"
