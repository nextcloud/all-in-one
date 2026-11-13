#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Run after update-yaml.sh and update-helm.sh in a disposable checkout.
set -eo pipefail
cd "$(dirname "$0")/.."

TEST_DIR="$(mktemp -d)"
trap 'rm -rf "$TEST_DIR"' EXIT

for VARIABLE in TALK_MAX_STREAM_BITRATE TALK_MAX_SCREEN_BITRATE; do
    if [ "$VARIABLE" = TALK_MAX_STREAM_BITRATE ]; then DEFAULT=1048576; else DEFAULT=2097152; fi
    [ "$(grep -c "^$VARIABLE=" manual-install/sample.conf)" -eq 1 ]
    grep -q "^$VARIABLE=\"$DEFAULT\" .*# Maximum .*bitrate in bits per second\.$" manual-install/sample.conf
    [ "$(yq "[.services.\"nextcloud-aio-talk\".environment[] | select(. == \"$VARIABLE\")] | length" manual-install/latest.yml)" -eq 1 ]
    [ "$(yq "[.services[] | .environment[] | select(. == \"$VARIABLE\")] | length" manual-install/latest.yml)" -eq 1 ]
    [ "$(grep -c "^$VARIABLE:" nextcloud-aio-helm-chart/values.yaml)" -eq 1 ]
    [ "$(yq ".$VARIABLE" nextcloud-aio-helm-chart/values.yaml)" = "$DEFAULT" ]
    [ "$(grep -c "name: $VARIABLE$" nextcloud-aio-helm-chart/templates/nextcloud-aio-talk-deployment.yaml)" -eq 1 ]
done

check_rendered_bitrates() {
    local STREAM="$1" SCREEN="$2"
    shift 2
    helm template talk-bitrates nextcloud-aio-helm-chart --set-string TALK_ENABLED=yes "$@" > "$TEST_DIR/rendered.yaml"
    for VARIABLE in TALK_MAX_STREAM_BITRATE TALK_MAX_SCREEN_BITRATE; do
        [ "$(grep -c "name: $VARIABLE$" "$TEST_DIR/rendered.yaml")" -eq 1 ]
    done
    [ "$(yq 'select(.kind == "Deployment" and .metadata.name == "nextcloud-aio-talk") | .spec.template.spec.containers[].env[] | select(.name == "TALK_MAX_STREAM_BITRATE") | .value' "$TEST_DIR/rendered.yaml")" = "$STREAM" ]
    [ "$(yq 'select(.kind == "Deployment" and .metadata.name == "nextcloud-aio-talk") | .spec.template.spec.containers[].env[] | select(.name == "TALK_MAX_SCREEN_BITRATE") | .value' "$TEST_DIR/rendered.yaml")" = "$SCREEN" ]
}

check_rendered_bitrates 1048576 2097152
check_rendered_bitrates 4194304 2097152 --set-string TALK_MAX_STREAM_BITRATE=4194304
check_rendered_bitrates 1048576 8388608 --set-string TALK_MAX_SCREEN_BITRATE=8388608
check_rendered_bitrates 4194304 8388608 --set-string TALK_MAX_STREAM_BITRATE=4194304 --set-string TALK_MAX_SCREEN_BITRATE=8388608
check_rendered_bitrates '' '' --set-string TALK_MAX_STREAM_BITRATE= --set-string TALK_MAX_SCREEN_BITRATE=
helm template talk-bitrates nextcloud-aio-helm-chart --set-string TALK_ENABLED=no \
    --set-string TALK_MAX_STREAM_BITRATE=4194304 --set-string TALK_MAX_SCREEN_BITRATE=8388608 > "$TEST_DIR/rendered.yaml"
if grep -q 'name: TALK_MAX_.*_BITRATE$' "$TEST_DIR/rendered.yaml"; then
    echo 'Bitrate overrides must not enable Talk or reach other containers.' >&2
    exit 1
fi

echo 'Talk bitrate generator tests passed.'
