#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Release step 3: promotes the beta images to latest, marks the release as stable and closes
# its milestone.
#
# Usage: release-stable.sh

source "$(dirname "$0")/release-lib.sh"

VERSION="$(aio_version)"
MILESTONE="$(milestone_number "$VERSION")"

[ -n "$MILESTONE" ] || die "There is no milestone called $VERSION"
[ "$(gh release view "v$VERSION" --json isPrerelease --jq .isPrerelease)" = true ] \
    || die "v$VERSION is not a pre-release"
assert_idle "$RELEASES_REPO" promote-to-beta.yml

run_workflow_and_wait "$RELEASES_REPO" promote-to-latest.yml

gh release edit "v$VERSION" --prerelease=false --latest --title "v$VERSION"
gh api -X PATCH "repos/$GH_REPO/milestones/$MILESTONE" -f state=closed > /dev/null
