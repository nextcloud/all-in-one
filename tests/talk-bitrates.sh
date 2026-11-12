#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

set -eo pipefail
cd "$(dirname "$0")/.."
TEST_DIR="$(mktemp -d)"
trap 'rm -rf "$TEST_DIR"' EXIT

# Exercise the startup validation without running Docker or changing the host.
sed -n '/^print_red() {$/,/^}$/p; /^check_if_number() {$/,/^}$/p; /^for TALK_BITRATE_VARIABLE in /,/^done$/p' \
    Containers/mastercontainer/start.sh > "$TEST_DIR/master-bitrates.sh"
validate_talk_bitrates() {
    bash "$TEST_DIR/master-bitrates.sh"
}

unset TALK_MAX_STREAM_BITRATE TALK_MAX_SCREEN_BITRATE
validate_talk_bitrates

for VARIABLE in TALK_MAX_STREAM_BITRATE TALK_MAX_SCREEN_BITRATE; do
    for VALUE in '' 1 08 0001 1048576 2097152 4194304 8388608 9223372036854775807 0009223372036854775807; do
        (
            export "$VARIABLE=$VALUE"
            validate_talk_bitrates
        )
    done
    # shellcheck disable=SC2016
    for VALUE in 0 000 -1 +1 1.5 4M ' ' ' 1' '1 ' $'\t1' $'1\n' $'1\n2' 9223372036854775808 18446744073709551617 999999999999999999999999999999999999999999 '1+1' '$(exit 42)'; do
        if OUTPUT="$(export "$VARIABLE=$VALUE"; validate_talk_bitrates)"; then
            printf 'Unexpectedly accepted %s=%q\n' "$VARIABLE" "$VALUE" >&2
            exit 1
        else
            STATUS=$?
        fi
        [ "$STATUS" -eq 1 ]
        [[ "$OUTPUT" == *"$VARIABLE must be a positive decimal integer in bits per second"* ]]
    done
done

# Run the existing defaults and signaling writer with only its output path redirected.
# shellcheck disable=SC2016
sed -n '/^if \[ -z "$TALK_MAX_STREAM_BITRATE" \]; then$/,/^SIGNALING_CONF$/p' Containers/talk/start.sh \
    | sed "s|/conf/signaling.conf|$TEST_DIR/signaling.conf|" > "$TEST_DIR/signaling.sh"
test -s "$TEST_DIR/signaling.sh"

check_signaling() (
    if [ "$1" != unset ]; then export TALK_MAX_STREAM_BITRATE="$1"; fi
    if [ "$2" != unset ]; then export TALK_MAX_SCREEN_BITRATE="$2"; fi
    validate_talk_bitrates
    bash "$TEST_DIR/signaling.sh"
    awk -v stream="$3" -v screen="$4" '
        /^\[/ { section = $0 }
        /^max(stream|screen)bitrate =/ {
            if (section != "[backend-1]" && section != "[mcu]") exit 1
            expected = $1 == "maxstreambitrate" ? stream : screen
            if ($3 != expected || ++seen[section, $1] != 1) exit 1
            count++
        }
        END { if (count != 4) exit 1 }
    ' "$TEST_DIR/signaling.conf"
)

check_signaling unset unset 1048576 2097152
check_signaling '' '' 1048576 2097152
check_signaling 4194304 unset 4194304 2097152
check_signaling unset 8388608 1048576 8388608
check_signaling 4194304 8388608 4194304 8388608
check_signaling 4194304 8388608 4194304 8388608
check_signaling 8388608 4194304 8388608 4194304
check_signaling unset unset 1048576 2097152
check_signaling '' '' 1048576 2097152

echo 'Talk bitrate validation and signaling tests passed.'
