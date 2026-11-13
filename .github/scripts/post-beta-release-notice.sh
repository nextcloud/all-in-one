#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later

# Posts a "released with <version> Beta" comment in all issues closed as completed and all merged pull requests of the
# milestone that is named like the tag of the latest beta release (e.g. v15.1.0). Items that already have the comment
# are skipped, so the script can safely be run again.
#
# Needs the GitHub CLI (gh), authenticated with a token that can comment on issues and pull requests.
# Set DRY_RUN=1 to only print what would be commented.
#
# Example: DRY_RUN=1 ./.github/scripts/post-beta-release-notice.sh

set -eu -o pipefail

repo="${GITHUB_REPOSITORY:-nextcloud/all-in-one}"

version="$(gh api "repos/$repo/releases?per_page=100" \
    --jq '[.[] | select(.prerelease and (.draft | not) and (.name | endswith(" Beta")))][0].tag_name // empty')"
if [ -z "$version" ]; then
    echo "No beta release found." >&2
    exit 1
fi
echo "Latest beta release: $version"

if [ -t 0 ]; then
    read -r -p "Continue? [y/N] " answer
    if [ "$answer" != y ] && [ "$answer" != Y ]; then
        echo "Aborted."
        exit 1
    fi
fi

milestone="$(gh api --paginate "repos/$repo/milestones?state=all&per_page=100" \
    --jq ".[] | select(.title == \"$version\") | .number")"
if [ -z "$milestone" ]; then
    echo "No milestone named $version found." >&2
    exit 1
fi

body="This is now released with $version Beta. Testing and feedback is welcome! See https://github.com/nextcloud/all-in-one#how-to-switch-the-channel"

# The issues endpoint also returns pull requests; those were closed successfully if they were merged.
items="$(gh api --paginate "repos/$repo/issues?milestone=$milestone&state=closed&per_page=100" \
    --jq '.[] | select(.state_reason == "completed" or .pull_request.merged_at != null) | [.number, .html_url, .title] | @tsv')"
if [ -z "$items" ]; then
    echo "No completed issues or merged pull requests found in milestone $version." >&2
    exit 1
fi

while IFS=$'\t' read -r number url title; do
    item="#$number $title ($url)"
    if gh api --paginate "repos/$repo/issues/$number/comments?per_page=100" --jq '.[].body' | grep -qxF "$body"; then
        echo "Already commented, skipping: $item"
    elif [ "${DRY_RUN:-}" = 1 ]; then
        echo "Would comment on: $item"
    else
        gh api --silent "repos/$repo/issues/$number/comments" -f body="$body"
        echo "Commented on: $item"
    fi
done <<< "$items"
