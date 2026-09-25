#!/bin/bash

# Periodically refresh the AIO docker network ranges used by caddy's @denied
# blocks. When they change - e.g. the network was recreated with a different
# subnet, or an IPv6 subnet was added to it - restart the caddy services so that
# they pick up the new values.
#
# The initial env file is written by start.sh before dinit starts, so the
# first iteration below normally reports "unchanged" and does not restart caddy.
while true; do
    sleep 60
    if test "$(bash /resolve-caddy-network-env.sh)" = "changed"; then
        echo "AIO docker network ranges changed, restarting caddy to apply them"
        # dinitctl only accepts a single service per call. It talks to the system daemon by
        # default as this runs as root (see dinit.d/caddy-network-watcher).
        for service in caddy-internal caddy-acme; do
            dinitctl restart "$service"
        done
    fi
done
