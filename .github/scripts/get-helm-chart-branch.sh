#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Prints the branch that the Helm chart for ghcr.io/nextcloud-releases/all-in-one:<docker-tag> has to be updated on:
# release-<major>.<minor> for the AIO version of the commit that the image was built from, if that branch exists,
# and main otherwise. The commit is read from the image's org.opencontainers.image.revision label.
#
# Prints nothing if the tip of that branch has another AIO version, since the chart would then get the version and
# templates of that other version. Images without the label belong to main, as before the label was introduced.
#
# Needs to run in a checkout of nextcloud/all-in-one that has all remote branches (fetch-depth: 0).
#
# Example: ./.github/scripts/get-helm-chart-branch.sh 20261027_120000

set -eu -o pipefail

if [ "$#" -ne 1 ]; then
    echo "Usage: $0 <docker-tag>" >&2
    exit 1
fi

IMAGE="ghcr.io/nextcloud-releases/all-in-one:$1"

aio_version() {
    git show "$1:php/templates/includes/aio-version.twig" | grep -oE '[0-9]+\.[0-9]+\.[0-9]+' | head -1
}

REVISION="$(docker buildx imagetools inspect "$IMAGE" --format '{{json .Image}}' | jq --raw-output '
    (if has("architecture") then [.] else [.[]] end)[]
    | select(.os == "linux" and .architecture == "amd64")
    | .config.Labels["org.opencontainers.image.revision"] // ""')"

if [ -z "$REVISION" ]; then
    echo "$IMAGE has no revision label, so its Helm chart belongs to main." >&2
    echo "main"
    exit 0
fi

VERSION="$(aio_version "$REVISION")"
BRANCH="release-${VERSION%.*}"
if ! git rev-parse --verify --quiet "refs/remotes/origin/$BRANCH" > /dev/null; then
    BRANCH="main"
fi

TIP_VERSION="$(aio_version "refs/remotes/origin/$BRANCH")"
if [ "$TIP_VERSION" != "$VERSION" ]; then
    echo "$IMAGE was built from $REVISION ($VERSION), but $BRANCH is at $TIP_VERSION, so its Helm chart isn't updated." >&2
    exit 0
fi

echo "$BRANCH"
