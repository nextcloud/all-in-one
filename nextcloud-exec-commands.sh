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

echo "exec-commands: done."
