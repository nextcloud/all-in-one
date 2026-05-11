# syntax=docker/dockerfile:latest
# SPDX-FileCopyrightText: 2025 Nextcloud GmbH <https://nextcloud.com>
# SPDX-License-Identifier: AGPL-3.0-only
FROM alpine:3.23.4

RUN set -ex; \
    apk upgrade --no-cache -a

LABEL org.opencontainers.image.title="Alpine for Nextcloud AIO" \
    org.opencontainers.image.description="Minimal Alpine Linux image for Nextcloud All-in-One" \
    org.opencontainers.image.url="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.source="https://github.com/nextcloud/all-in-one" \
    org.opencontainers.image.vendor="Nextcloud" \
    org.opencontainers.image.documentation="https://github.com/nextcloud/all-in-one/blob/main/readme.md"
