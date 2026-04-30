# syntax=docker/dockerfile:latest
FROM python:3.14.3-alpine3.23

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

ENV RECORDING_VERSION=v0.2.1
    ALLOW_ALL=false \
    HPB_PROTOCOL=https \
    NC_PROTOCOL=https \
    SKIP_VERIFY=false \
    HPB_PATH=/standalone-signaling/ \
    AIO_LOG_LEVEL=warn

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        xvfb \
        ffmpeg \
        mesa-va-gallium \
        firefox \
        font-noto-all \
        font-noto-cjk \
        font-noto-cjk-extra \
        bind-tools \
        netcat-openbsd \
        git \
        wget \
        shadow \
        pulseaudio \
        openssl \
        build-base \
        linux-headers \
        geckodriver; \
    useradd -d /tmp --system recording -u 122; \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    git clone --recursive https://github.com/nextcloud/nextcloud-talk-recording --depth=1 --single-branch --branch "$RECORDING_VERSION" /src; \
    python3 -m pip install --no-cache-dir /src; \
    rm -rf /src; \
    touch /etc/recording.conf; \
    chown recording:recording -R \
        /tmp /etc/recording.conf; \
    mkdir -p /conf; \
    chmod 777 /conf; \
    chmod 777 /tmp; \
    apk del --no-cache \
        git \
        wget \
        shadow \
        openssl \
        build-base \
        linux-headers;

VOLUME /tmp
WORKDIR /tmp
USER 122
ENTRYPOINT ["/start.sh"]
CMD ["python", "-m", "nextcloud.talk.recording", "--config", "/conf/recording.conf"]

HEALTHCHECK CMD /healthcheck.sh
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    org.opencontainers.image.title="Talk Recording for Nextcloud AIO" \
    org.opencontainers.image.description="Nextcloud Talk recording service for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
