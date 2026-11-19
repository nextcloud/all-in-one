# syntax=docker/dockerfile:latest
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
FROM debian:13.7-slim AS ffmpeg-over-ip

ARG TARGETARCH
ENV FFMPEG_OVER_IP_VERSION=v6.0.0 \
    FFMPEG_OVER_IP_SHA256_AMD64=80382cea884f6a1dc2490bb9677815c7bcecd0d3e3d78b13791e62afc4daec8b \
    FFMPEG_OVER_IP_SHA256_ARM64=72b282349b72b76c052b6d2afa73ed798dda6cd9e42a6a71f35936fa726bf68a

SHELL ["/bin/bash", "-o", "pipefail", "-c"]
# hadolint ignore=DL3008
RUN set -ex; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        ca-certificates \
        curl \
        unzip; \
    case "$TARGETARCH" in \
        amd64) sha256="$FFMPEG_OVER_IP_SHA256_AMD64" ;; \
        arm64) sha256="$FFMPEG_OVER_IP_SHA256_ARM64" ;; \
        *) echo "Unsupported architecture: $TARGETARCH"; exit 1 ;; \
    esac; \
    curl -fsSL -o /tmp/ffmpeg-over-ip-server.zip \
        "https://github.com/steelbrain/ffmpeg-over-ip/releases/download/$FFMPEG_OVER_IP_VERSION/linux-$TARGETARCH-ffmpeg-over-ip-server.zip"; \
    echo "$sha256  /tmp/ffmpeg-over-ip-server.zip" | sha256sum -c -; \
    unzip /tmp/ffmpeg-over-ip-server.zip -d /opt/ffmpeg-over-ip

# The ffmpeg and ffprobe binaries of ffmpeg-over-ip are linked against glibc so alpine cannot be used
FROM debian:13.7-slim
# libva and libdrm are loaded at runtime by ffmpeg, the drivers are needed for hardware acceleration via /dev/dri
# hadolint ignore=DL3008
RUN set -ex; \
    apt-get update; \
    apt-get upgrade -y; \
    apt-get install -y --no-install-recommends \
        tzdata \
        netcat-openbsd \
        libva2 \
        libva-drm2 \
        libdrm2 \
        mesa-va-drivers; \
    if [ "$(dpkg --print-architecture)" = amd64 ]; then \
        apt-get install -y --no-install-recommends \
            intel-media-va-driver \
            i965-va-driver; \
    fi; \
    rm -rf /var/lib/apt/lists/*

# ffmpeg-over-ip-server expects the patched ffmpeg and ffprobe next to its own binary
COPY --from=ffmpeg-over-ip /opt/ffmpeg-over-ip /opt/ffmpeg-over-ip
COPY --chmod=775 start.sh /start.sh
COPY --chmod=775 healthcheck.sh /healthcheck.sh

ENV PATH="/opt/ffmpeg-over-ip:$PATH" \
    PORT=5050 \
    AIO_LOG_LEVEL=warn

USER 65534:65534

ENTRYPOINT ["/start.sh"]

HEALTHCHECK CMD ["/healthcheck.sh"]
LABEL com.centurylinklabs.watchtower.enable="false" \
    wud.watch="false" \
    dockhand.update="false" \
    org.opencontainers.image.title="FFmpeg for Nextcloud AIO" \
    org.opencontainers.image.description="Video preview service for Nextcloud All-in-One based on ffmpeg-over-ip" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
