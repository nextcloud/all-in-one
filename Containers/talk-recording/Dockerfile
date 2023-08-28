FROM python:3.11.5-alpine3.18

COPY --chmod=775 start.sh /start.sh

ENV RECORDING_VERSION v17.0.3
ENV ALLOW_ALL false
ENV HPB_PROTOCOL https
ENV SKIP_VERIFY false
ENV HPB_PATH /standalone-signaling/

RUN set -ex; \
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
        openssl; \
    # chromium chromium-chromedriver?
    apk add --no-cache geckodriver --repository http://dl-cdn.alpinelinux.org/alpine/edge/testing; \
    useradd -d /tmp --system recording; \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    git clone --recursive https://github.com/nextcloud/spreed --depth=1 --single-branch --branch "$RECORDING_VERSION" /src; \
    mv -v /src/recording/pyproject.toml /src/recording/src/pyproject.toml; \
    python3 -m pip install /src/recording/src; \
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
        openssl;

WORKDIR /tmp
USER recording
ENTRYPOINT ["/start.sh"]
CMD ["python", "-m", "nextcloud.talk.recording", "--config", "/conf/recording.conf"]

HEALTHCHECK CMD nc -z localhost 1234 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
