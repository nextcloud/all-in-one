#!/bin/bash

deduplicate_sessions() {
    echo "Deleting duplicate sessions"
    find "/mnt/docker-aio-config/session/" -mindepth 1 -exec grep -qv "$NEW_SESSION_TIME" {} \; -delete
}

compare_times() {
    if [ -f "/mnt/docker-aio-config/data/session_date_file" ]; then
        unset NEW_SESSION_TIME
        NEW_SESSION_TIME="$(cat "/mnt/docker-aio-config/data/session_date_file")"
        if [ -n "$NEW_SESSION_TIME" ] && [ -n "$OLD_SESSION_TIME" ] && [ "$NEW_SESSION_TIME" != "$OLD_SESSION_TIME" ]; then
            deduplicate_sessions
        fi
        OLD_SESSION_TIME="$NEW_SESSION_TIME"
    fi
}

while true; do
    compare_times
    sleep 2
done
