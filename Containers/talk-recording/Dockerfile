# syntax=docker/dockerfile:latest
FROM python:3.13.7-alpine3.22

COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

ENV RECORDING_VERSION=v0.1
ENV ALLOW_ALL=false
ENV HPB_PROTOCOL=https
ENV NC_PROTOCOL=https
ENV SKIP_VERIFY=false
ENV HPB_PATH=/standalone-signaling/

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        xvfb \
        ffmpeg \
        firefox \
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
    org.label-schema.vendor="Nextcloud"
