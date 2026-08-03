#!/usr/bin/env bash

if [[ "$1" = -* ]]; then
    echo "Usage $(basename "$0") [PLAYWRIGHT_TESTS_FILE]"
    exit 1
fi

cd "$(dirname "$0")/../.." || {
    echo 'Cannot change to base directory, something is seriously wrong!'
    exit 1
}

DOCO="docker compose -f ./php/tests/compose.yaml"

if [[ $(uname -m) = 'arm64' ]]; then
    export ARM64_SUFFIX='-arm64'
fi

run_tests() {
    export TESTS_FILE="$1"
    export SKIP_DOMAIN_VALIDATION

    if [[ -n "$TEST_CODE_FROM_IMAGE" ]]; then
        profile="code-from-image"
    else
        profile="local-code"
    fi
    
    # Clean up old containers and volumes
    $DOCO --profile $profile down -v --remove-orphans
    docker container rm --force nextcloud-aio-{mastercontainer,apache,notify-push,nextcloud,redis,database,domaincheck,whiteboard,imaginary,talk,collabora,borgbackup} > /dev/null 2>&1
    docker volume rm nextcloud_aio_{mastercontainer,apache,database,database_dump,nextcloud,nextcloud_data,redis,backup_cache,elasticsearch} > /dev/null 2>&1

    echo -e "\n 📣  Running playwright tests for ${TESTS_FILE} with SKIP_DOMAIN_VALIDATION=$SKIP_DOMAIN_VALIDATION and profile '$profile'\n"
    $DOCO --profile $profile run --remove-orphans test-runner-$profile
    exitcode=$?
    if test $exitcode -gt 0; then
        for container in nextcloud-aio-{mastercontainer,borgbackup,desec-mock}; do
            if docker container list --format="{{ .Names }}" | grep -q "$container"; then
                echo -e "\n 📣  Log output from container ${container}:\n"
                docker logs "$container"
            fi
        done
        # Exit on failure so further test files don't even run.
        exit $exitcode
    fi
}


if [[ -n "$1" ]]; then
    if [[ ! -f "$1" ]]; then
        echo "Error: file '$1' does not exist."
        exit 1
    fi
    # Not using coreutils' `realpath --relative-to` here since that is not available on BSD/mac systems.
    fullpath="$(realpath "$1")"
    prefix="$(realpath ./php/tests)"
    relpath="${fullpath#"$prefix"/}"
    
    if test -z "$SKIP_DOMAIN_VALIDATION"; then
        SKIP_DOMAIN_VALIDATION=false
    fi
    run_tests "$relpath"
else
    SKIP_DOMAIN_VALIDATION=false
    run_tests tests/persist-default-config.spec.js
    sleep 1
    SKIP_DOMAIN_VALIDATION=true
    run_tests tests/initial-setup.spec.js
    sleep 1
    SKIP_DOMAIN_VALIDATION=false
    run_tests tests/restore-instance.spec.js
    sleep 1
    run_tests tests/desec-register.spec.js
    sleep 1
    run_tests tests/desec-existing.spec.js
    sleep 1
    run_tests tests/desec-existing-slug.spec.js
fi
