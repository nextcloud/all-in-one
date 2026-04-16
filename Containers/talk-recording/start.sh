#!/bin/bash

# Variables
if [ -z "$NC_DOMAIN" ]; then
    echo "You need to provide the NC_DOMAIN."
    exit 1
elif [ -z "$RECORDING_SECRET" ]; then
    echo "You need to provide the RECORDING_SECRET."
    exit 1
elif [ -z "$INTERNAL_SECRET" ]; then
    echo "You need to provide the INTERNAL_SECRET."
    exit 1
fi

if [ -z "$HPB_DOMAIN" ]; then
    export HPB_DOMAIN="$NC_DOMAIN"
fi

# Delete all contents on startup to start fresh
rm -fr /tmp/{*,.*}

# Detect available hardware for transcoding and build the [ffmpeg] config section accordingly
FFMPEG_SECTION="[ffmpeg]
# common = ffmpeg -loglevel level+warning -n
# outputaudio = -c:a libopus
# outputvideo = -c:v libvpx -deadline:v realtime -crf 10 -b:v 1M
extensionaudio = .ogg
extensionvideo = .webm"

# Check for NVIDIA GPU hardware encoding (NVENC)
if [ -e "/dev/nvidia0" ] && ffmpeg -hide_banner -encoders 2>/dev/null | grep -q "h264_nvenc"; then
    echo "NVIDIA GPU detected, enabling h264_nvenc hardware transcoding"
    FFMPEG_SECTION="[ffmpeg]
outputvideo = -c:v h264_nvenc -preset p4
outputaudio = -c:a aac
extensionaudio = .m4a
extensionvideo = .mp4"
# Check for VA-API render node (Intel/AMD open source drivers)
elif [ -r "/dev/dri/renderD128" ] && ffmpeg -hide_banner -encoders 2>/dev/null | grep -q "h264_vaapi"; then
    echo "DRI device detected, enabling h264_vaapi hardware transcoding"
    FFMPEG_SECTION="[ffmpeg]
common = ffmpeg -loglevel level+warning -n -vaapi_device /dev/dri/renderD128
outputvideo = -vf format=nv12,hwupload -c:v h264_vaapi
outputaudio = -c:a aac
extensionaudio = .m4a
extensionvideo = .mp4"
fi

cat << RECORDING_CONF > "/conf/recording.conf"
[logs]
# 30 means Warning
level = 30

[http]
listen = 0.0.0.0:1234

[backend]
allowall = ${ALLOW_ALL}
# The secret below is still needed if allowall is set to true, also it doesn't hurt to be here
secret = ${RECORDING_SECRET}
backends = backend-1
skipverify = ${SKIP_VERIFY}
maxmessagesize = 1024
videowidth = 1920
videoheight = 1080
directory = /tmp

[backend-1]
url = ${NC_PROTOCOL}://${NC_DOMAIN}
secret = ${RECORDING_SECRET}
skipverify = ${SKIP_VERIFY}

[signaling]
signalings = signaling-1

[signaling-1]
url = ${HPB_PROTOCOL}://${HPB_DOMAIN}${HPB_PATH}
internalsecret = ${INTERNAL_SECRET}

${FFMPEG_SECTION}

[recording]
browser = firefox
driverPath = /usr/bin/geckodriver
browserPath = /usr/bin/firefox
RECORDING_CONF

exec "$@"
