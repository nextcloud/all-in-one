#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Shared helpers for the release-*.sh scripts. Source it, don't run it.
# Requires an authenticated `gh` CLI (locally: `gh auth login`, in CI: GH_TOKEN) with write access
# to this repo. RELEASES_REPO is only read from, its workflows are run by the maintainers.

# The variables are used by the scripts that source this file.
# shellcheck disable=SC2034
set -euo pipefail

export GH_REPO="${GH_REPO:-nextcloud/all-in-one}"
RELEASES_REPO="nextcloud-releases/all-in-one"
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
VERSION_FILE="php/templates/includes/aio-version.twig"

die() {
    echo "$*" >&2
    exit 1
}

# Read from main on GitHub, so it doesn't depend on the state of a local checkout.
aio_version() {
    gh api "repos/$GH_REPO/contents/$VERSION_FILE?ref=main" -H "Accept: application/vnd.github.raw"
}

# Prints the date of the commit on main that last changed the AIO version.
version_bump_date() {
    gh api "repos/$GH_REPO/commits?sha=main&path=$VERSION_FILE&per_page=1" --jq '.[0].commit.committer.date'
}

# Usage: assert_succeeded_since REPO WORKFLOW_FILE DATE
# Fails unless the latest run of the workflow was started after DATE and succeeded.
assert_succeeded_since() {
    local status conclusion created
    read -r status conclusion created < <(gh run list -R "$1" -w "$2" -L 1 \
        --json status,conclusion,createdAt --jq '.[0] | "\(.status) \(.conclusion) \(.createdAt)"')
    if [ "$status $conclusion" != "completed success" ] || [[ ! "$created" > "$3" ]]; then
        die "Run https://github.com/$1/actions/workflows/$2 first and wait for it to succeed"
    fi
}

# Usage: milestone_number TITLE  (prints nothing if there is none)
milestone_number() {
    gh api "repos/$GH_REPO/milestones?state=all" --paginate --jq ".[] | select(.title == \"$1\") | .number"
}
