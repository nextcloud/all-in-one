FROM python:3.11.3-alpine3.18

COPY --chmod=775 start.sh /start.sh
COPY --chmod=664 recording.conf /etc/recording.conf

RUN set -ex; \
    apk add --no-cache \
        ca-certificates \
        tzdata \
        bash \
        ffmpeg \
        libpulse \
        bind-tools \
        netcat-openbsd \
        git \
        wget \
        shadow; \
    # xvfb firefox chromium chromium-chromedriver?
    # apk add --no-cache geckodriver --repository http://dl-cdn.alpinelinux.org/alpine/edge/testing; \
    useradd --system recording; \
    git clone --recursive https://github.com/nextcloud/spreed --branch v16.0.3 /src; \
    mv -v /src/recording/pyproject.toml /src/recording/src/pyproject.toml; \
    python3 -m pip install /src/recording/src; \
    rm -rf /src; \
    apk del --no-cache \
        git \
        wget \
        shadow; \
    \
# Give root a random password
    echo "root:$(openssl rand -base64 12)" | chpasswd; \
    \
    chown recording:recording -R \
        /tmp;

USER recording
ENTRYPOINT ["/start.sh"]
CMD ["python", "-m", "nextcloud.talk.recording", "--config", "/etc/recording.conf"]

HEALTHCHECK CMD nc -z localhost 1234 || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
