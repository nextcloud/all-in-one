#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Release step 1: bumps the AIO version on main and starts building the develop images.
#
# Usage: release-build.sh X.Y.Z

source "$(dirname "$0")/release-lib.sh"

VERSION="${1:-}"
[[ "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "Usage: $0 X.Y.Z"
cd "$REPO_ROOT"
[ "$(git rev-parse --abbrev-ref HEAD)" = main ] || die "Must be run on the main branch"
[[ "$(git remote get-url origin)" == *"$GH_REPO"* ]] || die "origin must point to $GH_REPO"

echo "$VERSION" > "$VERSION_FILE"
git commit -m "chore(release): bump AIO version to $VERSION" -- "$VERSION_FILE"
git push origin HEAD:main

# The build of the images starts automatically after the repo sync.
gh workflow run repo-sync.yml -R "$RELEASES_REPO"
echo "Building $VERSION, see https://github.com/$RELEASES_REPO/actions/workflows/build_images.yml"
