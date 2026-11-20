#!/bin/bash
# SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
# SPDX-License-Identifier: AGPL-3.0-or-later
#
# Runs the release workflows of this repo and of nextcloud-releases/all-in-one one after another,
# for maintainers to run locally on a checkout of main.
# All work happens in workflows on GitHub, this script only starts them and waits for them. If it
# gets interrupted (e.g. by network problems), the workflows continue and running it again picks
# up where it stopped, without starting workflows twice.
#
# Usage: release.sh beta VERSION NEXTCLOUD_VERSION  bumps the version, builds and tests the images
#                                                   and publishes the beta
#        release.sh final                           publishes the beta images and release as latest
#   VERSION: the new AIO version, e.g. 15.2.0
#   NEXTCLOUD_VERSION: the Nextcloud version that new instances can install directly, e.g. 35

# shellcheck source=.github/scripts/release-lib.sh
source "$(dirname "$0")/release-lib.sh"

POLL_SECONDS="${POLL_SECONDS:-30}"

warn() {
    echo "$*" >&2
}

confirm() {
    local answer
    read -rp "$* [y/N] " answer
    [ "$answer" = y ] || die "Run this script again when you are ready"
}

# Usage: ensure_run REPO WORKFLOW_FILE RUN_TITLE SINCE [ARGS FOR `gh workflow run`...]
# Waits for a successful run of the workflow that was started after SINCE, and starts one if
# there is none. Prints the time that run finished.
ensure_run() {
    local repo="$1" workflow="$2" title="$3" since="$4"
    shift 4
    local skip_id=0 triggered="" run id state updated
    while true; do
        if ! run="$(gh run list -R "$repo" -w "$workflow" -L 20 --json databaseId,displayTitle,status,conclusion,createdAt,updatedAt \
            --jq "[.[] | select(.displayTitle == \"$title\" and .createdAt > \"$since\" and .databaseId > $skip_id)][0]
                  // empty | \"\(.databaseId) \(.status)/\(.conclusion) \(.updatedAt)\"")"; then
            warn "Could not reach GitHub, trying again"
            sleep "$POLL_SECONDS"
            continue
        fi
        if [ -z "$run" ]; then
            # Started again only if the previous start didn't create a run within 5 minutes, e.g.
            # because the request didn't reach GitHub.
            if [ -z "$triggered" ] || ((SECONDS - triggered > 300)); then
                warn "Starting $title (https://github.com/$repo/actions/workflows/$workflow)"
                gh workflow run "$workflow" -R "$repo" "$@" >&2 || warn "Could not start it, trying again"
                triggered="$SECONDS"
            fi
            sleep "$POLL_SECONDS"
            continue
        fi
        read -r id state updated <<< "$run"
        case "$state" in
            completed/success)
                warn "$title succeeded"
                echo "$updated"
                return
                ;;
            completed/*)
                # A run that failed before this script was started is run again, one started by
                # this script is not.
                [ -z "$triggered" ] || die "$title failed: https://github.com/$repo/actions/runs/$id"
                warn "The last run of $title failed (https://github.com/$repo/actions/runs/$id), starting it again"
                skip_id="$id"
                ;;
            *)
                warn "Waiting for $title: https://github.com/$repo/actions/runs/$id"
                sleep "$POLL_SECONDS"
                ;;
        esac
    done
}

# Commits the new version (signed off by you) and pushes it to main.
bump_version() {
    cd "$REPO_ROOT" || die "Could not change to $REPO_ROOT"
    [ "$(git rev-parse --abbrev-ref HEAD)" = main ] || die "Check out the main branch first"
    [[ "$(git remote get-url origin)" == *"$GH_REPO"* ]] || die "origin must point to $GH_REPO"
    git pull --rebase origin main
    # Skipped if a previous run committed it but couldn't push it.
    if [ "$(cat "$VERSION_FILE")" != "$1" ]; then
        echo "$1" > "$VERSION_FILE"
        git commit -s -m "chore(release): bump AIO version to $1" -- "$VERSION_FILE"
    fi
    git push origin HEAD:main
}

beta() {
    local version="$1" installable="$2" since
    [[ "$version" =~ ^[0-9]+\.[0-9]+\.[0-9]+$ && "$installable" =~ ^[0-9]+(\.[0-9]+)*$ ]] \
        || die "Usage: $0 beta VERSION NEXTCLOUD_VERSION"
    if gh release view "v$version" > /dev/null 2>&1; then
        die "v$version was already released"
    fi
    if [ "$(aio_version)" != "$version" ]; then
        bump_version "$version"
    fi
    since="$(version_bump_date)"
    echo "Releasing $version"

    since="$(ensure_run "$RELEASES_REPO" build_images.yml "Build images from main" "$since" -f branch=main)"
    confirm "Did the smoke test on the develop channel pass and does Collabora open?"
    since="$(ensure_run "$GH_REPO" playwright-on-workflow-dispatch.yml "Playwright Tests" "$since")"
    since="$(ensure_run "$RELEASES_REPO" promote-to-beta.yml "Promote to beta" "$since")"
    ensure_run "$GH_REPO" release-publish-beta.yml "Release - publish beta" "$since" \
        -f nextcloud_version="$installable" > /dev/null
    echo "Published https://github.com/$GH_REPO/releases/tag/v$version"
}

final() {
    local version since yaml_pr
    version="$(aio_version)"
    [ "$(gh release view "v$version" --json isPrerelease --jq .isPrerelease)" = true ] \
        || die "v$version is not a beta release"
    since="$(gh release view "v$version" --json createdAt --jq .createdAt)"
    confirm "Were no new bugs reported for a week since v$version Beta?"

    since="$(ensure_run "$RELEASES_REPO" promote-to-latest.yml "Promote beta to latest" "$since" -f source=beta)"
    yaml_pr="$(gh pr list --head aio-yaml-update --state open --json url --jq '.[].url')"
    if [ -n "$yaml_pr" ]; then
        confirm "Did you merge $yaml_pr?"
    fi
    ensure_run "$GH_REPO" release-make-final.yml "Release - make final" "$since" > /dev/null
    echo "Published https://github.com/$GH_REPO/releases/tag/v$version"
}

case "${1:-}" in
    beta) beta "${2:-}" "${3:-}" ;;
    final) final ;;
    *) die "Usage: $0 beta VERSION NEXTCLOUD_VERSION | $0 final" ;;
esac
