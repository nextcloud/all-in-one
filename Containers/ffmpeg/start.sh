#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
    export FFMPEG_OVER_IP_SERVER_DEBUG=true
fi

if [ -z "$FFMPEG_SECRET" ]; then
    echo "You need to provide the FFMPEG_SECRET."
    exit 1
fi

export FFMPEG_OVER_IP_SERVER_ADDRESS="0.0.0.0:$PORT"
export FFMPEG_OVER_IP_SERVER_AUTH_SECRET="$FFMPEG_SECRET"
export FFMPEG_OVER_IP_SERVER_LOG=stdout

echo "FFmpeg has started"

exec ffmpeg-over-ip-server "$@"
