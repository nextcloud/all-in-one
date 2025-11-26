# syntax=docker/dockerfile:latest
# Probably from this file: https://github.com/nextcloud/whiteboard/blob/main/Dockerfile
FROM ghcr.io/nextcloud-releases/whiteboard:v1.4.2

USER root
RUN set -ex; \
    apk add --no-cache bash; \
    chmod 777 -R /tmp; \
    if [ -f /usr/lib/chromium/chrome_crashpad_handler ] && [ ! -f /usr/lib/chromium/chrome_crashpad_handler.real ]; then \
        mv /usr/lib/chromium/chrome_crashpad_handler /usr/lib/chromium/chrome_crashpad_handler.real; \
        printf '%s\n' '#!/bin/sh' "exec /usr/lib/chromium/chrome_crashpad_handler.real --no-periodic-tasks --database=\"\${CRASHPAD_DATABASE:-/tmp/chrome-crashpad}\" \"\$@\"" >/usr/lib/chromium/chrome_crashpad_handler; \
        chmod +x /usr/lib/chromium/chrome_crashpad_handler; \
    fi
USER 65534

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

HEALTHCHECK CMD /healthcheck.sh

WORKDIR /tmp

ENTRYPOINT ["/start.sh"]

LABEL com.centurylinklabs.watchtower.enable="false" \
    org.label-schema.vendor="Nextcloud"
