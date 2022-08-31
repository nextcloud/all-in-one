#!/bin/bash

while true; do
    while [ "$(find "/mnt/docker-aio-config/session/" -mindepth 1 -exec grep "aio_authenticated|[a-z]:1" {} \; | wc -l)" -gt 1 ]; do
        # First delete all session files that are not authenticated
        unset SESSION_FILES
        SESSION_FILES="$(find "/mnt/docker-aio-config/session/" -mindepth 1)"
        unset SESSION_FILES_ARRAY
        mapfile -t SESSION_FILES_ARRAY <<< "$SESSION_FILES"
        for SESSION_FILE in "${SESSION_FILES_ARRAY[@]}"; do
            if [ -f "$SESSION_FILE" ] && ! grep -q "aio_authenticated|[a-z]:1" "$SESSION_FILE"; then
                rm "$SESSION_FILE"
            fi
        done

        # Second clean up all sessions that are authenticated
        echo "Deleting duplicate sessions"
        unset OLDEST_FILE
        set -x
        # shellcheck disable=SC2012
        OLDEST_FILE="$(ls -t "/mnt/docker-aio-config/session/" | tail -1)"
        rm "/mnt/docker-aio-config/session/$OLDEST_FILE"
        set +x
    done
    sleep 5
done
