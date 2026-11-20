#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Creates the recurring release ticket from .github/release-issue-template.md.
# Requires an authenticated `gh` CLI (locally: `gh auth login`, in CI: GH_TOKEN).
#
# Usage: create-release-issue.sh [--dry-run]

set -euo pipefail

export GH_REPO="${GH_REPO:-nextcloud/all-in-one}"
TITLE="publish new images after recent PRs are merged and tested"
LABEL="overview"
MILESTONE="next"
TEMPLATE="$(dirname "$0")/../release-issue-template.md"

PREVIOUS="$(gh issue list --state all --label "$LABEL" --search "in:title \"$TITLE\"" --limit 20 \
    --json number,title --jq "[.[] | select(.title == \"$TITLE\")][0].number // empty")"
if [ -z "$PREVIOUS" ]; then
    echo "Could not find the previous release ticket" >&2
    exit 1
fi

# Drop the leading comment block and fill in the placeholders.
BODY="$(sed '1,/^-->$/d' "$TEMPLATE" | sed "s/{{PREVIOUS_ISSUE}}/$PREVIOUS/g")"

if [ "${1:-}" = "--dry-run" ]; then
    printf 'Repo: %s\nTitle: %s\nLabel: %s\nMilestone: %s\n\n%s\n' "$GH_REPO" "$TITLE" "$LABEL" "$MILESTONE" "$BODY"
    exit 0
fi

URL="$(gh issue create --title "$TITLE" --label "$LABEL" --milestone "$MILESTONE" --body "$BODY")"
gh issue comment "$PREVIOUS" --body "Follow-up: $URL"
echo "$URL"
