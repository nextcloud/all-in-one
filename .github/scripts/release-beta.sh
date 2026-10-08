#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Release step 2: runs the E2E tests, promotes the develop images to beta, creates the beta
# pre-release and moves on to a new `next` milestone.
#
# Usage: release-beta.sh NEXTCLOUD_VERSION
#   NEXTCLOUD_VERSION: the Nextcloud version that new instances can install directly, e.g. 35

source "$(dirname "$0")/release-lib.sh"

INSTALLABLE="${1:-}"
[[ "$INSTALLABLE" =~ ^[0-9]+(\.[0-9]+)*$ ]] || die "Usage: $0 NEXTCLOUD_VERSION"
VERSION="$(aio_version)"
INCLUDED="$(sed -n 's/^ENV NEXTCLOUD_VERSION=//p' "$REPO_ROOT/Containers/nextcloud/Dockerfile")"
OLD_MILESTONE="$(milestone_number next)"

# Check everything up front instead of failing after the images were promoted.
[ -n "$INCLUDED" ] || die "Could not read NEXTCLOUD_VERSION from the Nextcloud Dockerfile"
[ -n "$OLD_MILESTONE" ] || die "There is no milestone called next"
[ -z "$(milestone_number "$VERSION")" ] || die "Milestone $VERSION already exists"
if gh release view "v$VERSION" > /dev/null 2>&1; then die "Release v$VERSION already exists"; fi
assert_idle "$RELEASES_REPO" build_images.yml

run_workflow_and_wait "$GH_REPO" playwright-on-workflow-dispatch.yml
run_workflow_and_wait "$RELEASES_REPO" promote-to-beta.yml

gh release create "v$VERSION" --target main --title "v$VERSION Beta" --prerelease \
    --generate-notes --discussion-category Releases \
    --notes "Nextcloud $INCLUDED is included but new instances can install Nextcloud $INSTALLABLE directly"

gh api -X PATCH "repos/$GH_REPO/milestones/$OLD_MILESTONE" -f title="$VERSION" > /dev/null
NEW_MILESTONE="$(gh api -X POST "repos/$GH_REPO/milestones" -f title=next --jq .number)"
for number in $(gh api "repos/$GH_REPO/issues?milestone=$OLD_MILESTONE&state=open" --paginate --jq '.[].number'); do
    gh api -X PATCH "repos/$GH_REPO/issues/$number" -F milestone="$NEW_MILESTONE" > /dev/null
done
