#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Shared helpers for the release-*.sh scripts. Source it, don't run it.
# Requires an authenticated `gh` CLI (locally: `gh auth login`, in CI: GH_TOKEN)
# with write access to this repo and permission to run workflows in RELEASES_REPO.

set -euo pipefail

export GH_REPO="${GH_REPO:-nextcloud/all-in-one}"
RELEASES_REPO="nextcloud-releases/all-in-one"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
VERSION_FILE="$REPO_ROOT/php/templates/includes/aio-version.twig"

die() {
    echo "$*" >&2
    exit 1
}

aio_version() {
    cat "$VERSION_FILE"
}

# Usage: assert_idle REPO WORKFLOW_FILE
assert_idle() {
    local busy
    busy="$(gh run list -R "$1" -w "$2" -L 20 --json status --jq '[.[] | select(.status != "completed")] | length')"
    [ "$busy" = 0 ] || die "A run of $2 in $1 is still in progress, try again later"
}

# Usage: run_workflow_and_wait REPO WORKFLOW_FILE
run_workflow_and_wait() {
    local before id=""
    before="$(gh run list -R "$1" -w "$2" -L 1 --json databaseId --jq '.[0].databaseId // 0')"
    gh workflow run "$2" -R "$1"
    # `gh workflow run` doesn't return the run ID, so wait for a run newer than the last known one.
    while [ -z "$id" ]; do
        sleep 5
        id="$(gh run list -R "$1" -w "$2" -L 1 --json databaseId --jq ".[] | select(.databaseId > $before) | .databaseId")"
    done
    gh run watch "$id" -R "$1" --exit-status
}

# Usage: milestone_number TITLE  (prints nothing if there is none)
milestone_number() {
    gh api "repos/$GH_REPO/milestones?state=all" --paginate --jq ".[] | select(.title == \"$1\") | .number"
}
