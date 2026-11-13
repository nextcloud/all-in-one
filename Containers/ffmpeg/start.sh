#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
    DEBUG=true
else
    DEBUG=false
fi

if [ -z "$FFMPEG_SECRET" ]; then
    echo "You need to provide the FFMPEG_SECRET."
    exit 1
fi

hw_device_works() {
    ffmpeg -hide_banner -loglevel error -nostdin -init_hw_device "$1" -f lavfi -i nullsrc=s=64x64 -frames:v 1 -f null - > /dev/null 2>&1
}

# Use hardware decoding if a GPU is usable. ffmpeg falls back to software decoding for codecs that the GPU does not support.
if hw_device_works cuda; then
    echo "Nvidia GPU detected, enabling cuda hardware decoding"
    HWACCEL="-hwaccel cuda"
# The render node may also belong to a GPU without a usable VA-API driver (e.g. Nvidia), so test it
elif [ -r "/dev/dri/renderD128" ] && hw_device_works vaapi=va:/dev/dri/renderD128; then
    echo "DRI device detected, enabling vaapi hardware decoding"
    HWACCEL="-hwaccel vaapi -hwaccel_device /dev/dri/renderD128"
fi

# Rewrites are only supported in the config file. The hardware acceleration options need to be placed in front of the input.
REWRITES=""
if [ -n "$HWACCEL" ]; then
    REWRITES="[\"-i\", \"$HWACCEL -i\"]"
fi

CONFIG_FILE=/tmp/ffmpeg-over-ip.server.jsonc
rm -f "$CONFIG_FILE"
(
    umask 077
    cat << EOF > "$CONFIG_FILE"
{
  "address": "0.0.0.0:$PORT",
  "authSecret": "$FFMPEG_SECRET",
  "log": "stdout",
  "debug": $DEBUG,
  "rewrites": [
    $REWRITES
  ]
}
EOF
)

echo "FFmpeg has started"

exec ffmpeg-over-ip-server --config "$CONFIG_FILE" "$@"
