#!/bin/bash
#
# Custom occ commands, run inside nextcloud-aio-nextcloud by AIO's own
# /run-exec-commands.sh (supervisord program "run-exec-commands"), which invokes
# whatever NEXTCLOUD_EXEC_COMMANDS contains once Apache is reachable. That is the
# hook upstream provides for exactly this -- see
# https://github.com/nextcloud/all-in-one/blob/main/Containers/nextcloud/entrypoint.sh
# -- so nothing here needs a forked entrypoint or a custom image.
#
# Bind-mounted read-only and referenced as `NEXTCLOUD_EXEC_COMMANDS=bash
# /nextcloud-exec-commands.sh` rather than inlined into the env var, so the script
# stays reviewable and diffable instead of being a YAML string.
#
# Runs as www-data. Must be idempotent: it executes on every container start.

set -euo pipefail

occ() { php /var/www/html/occ "$@"; }

# AIO waits for Apache before calling us, but not for the install/upgrade to
# finish, and occ refuses to do anything useful until it has.
until occ status 2>/dev/null | grep -q "installed: true"; do
    echo "exec-commands: waiting for Nextcloud install to finish..."
    sleep 5
done

# IMPORTANT: AIO's run-exec-commands.sh activates Collabora's config only in its
# else-branch, i.e. only when NEXTCLOUD_EXEC_COMMANDS is unset. Setting that var
# takes over the whole hook, so this call has to be repeated here or Collabora
# silently loses its WOPI config on every start.
if [ "${COLLABORA_ENABLED:-}" = "yes" ]; then
    echo "exec-commands: activating Collabora config..."
    occ richdocuments:activate-config
fi

# The nc_aio_tools bind mount only puts the app on disk; Nextcloud still has to be
# told it exists. Replaces the one-time manual `occ app:enable` step.
if ! occ app:list --enabled | grep -q 'nc_aio_tools'; then
    echo "exec-commands: enabling nc_aio_tools..."
    occ app:enable nc_aio_tools
fi

# Replaces the former nextcloud-aio-post-install one-shot service. Applies to new
# AND existing accounts. Leave DEFAULT_QUOTA blank to skip entirely.
if [ -n "${DEFAULT_QUOTA:-}" ]; then
    echo "exec-commands: setting default quota to ${DEFAULT_QUOTA}..."
    occ config:app:set files default_quota --value="$DEFAULT_QUOTA"
    occ user:list | sed -n 's/^  - \([^:]*\):.*/\1/p' | while read -r user; do
        occ user:setting "$user" files quota "$DEFAULT_QUOTA"
    done
fi

# Default apps, in Anirban's stated priority order (PR #1 review). These have to be
# enabled for the custom styling to apply to them.
#
# Two mechanisms, deliberately both:
#   NEXTCLOUD_STARTUP_APPS installs these on a FRESH install only -- AIO runs that
#   list once, on first startup, so it does nothing for an instance that already
#   exists. This loop is what makes the set hold on every start, and it also
#   re-enables anything an admin turned off by accident.
#
# app:enable is a no-op when the app is already on, so this stays quiet in the
# normal case. Apps absent from disk are reported and skipped rather than failing
# the whole hook -- mail and previewgenerator come from the app store and need
# `occ app:install`, which needs outbound network and is NOT done here on purpose
# (a hook that reaches the internet on every container start is its own problem).
if [ -n "${NEXTCLOUD_DEFAULT_APPS:-}" ]; then
    for app in $(echo "$NEXTCLOUD_DEFAULT_APPS" | tr ',' ' '); do
        if ! occ app:list --enabled | grep -q "^  - ${app}:"; then
            if occ app:enable "$app" >/dev/null 2>&1; then
                echo "exec-commands: enabled ${app}"
            else
                echo "exec-commands: ${app} not present on disk, skipping (occ app:install ${app} to add it)"
            fi
        fi
    done

    # Landing page. `defaultapp` is a comma-separated fallback chain, first ENABLED
    # entry wins, so the same priority order works directly. Note this only controls
    # where users land: the order of icons in the top bar is a per-user setting
    # (core/apporder) with no admin-level default, so it cannot be set from here.
    occ config:system:set defaultapp --value="$NEXTCLOUD_DEFAULT_APPS"
fi

# ClamAV scan limits (Anirban's request: 100 MB).
#
# NOTE: the MAX_SIZE env var on nextcloud-aio-clamav is INERT -- that image's
# /start.sh never reads it, and its clamd.conf ships hardcoded 2000M values. The
# limits Nextcloud actually enforces are these two files_antivirus app settings, so
# this is the only place setting them has any effect.
#   av_max_file_size     -- files larger than this are skipped entirely (-1 = no cap)
#   av_stream_max_length -- how much of a file is streamed to clamd
# Both are bytes. Files above the cap are accepted by Nextcloud WITHOUT being
# scanned, so raising it trades throughput for coverage.
if [ -n "${CLAMAV_MAX_FILE_SIZE:-}" ]; then
    echo "exec-commands: capping ClamAV scanning at ${CLAMAV_MAX_FILE_SIZE} bytes..."
    occ config:app:set files_antivirus av_max_file_size --value="$CLAMAV_MAX_FILE_SIZE"
    occ config:app:set files_antivirus av_stream_max_length --value="$CLAMAV_MAX_FILE_SIZE"
fi

echo "exec-commands: done."
