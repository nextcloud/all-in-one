#!/bin/bash
set -ex

touch "$DUMP_DIR/initialization.failed"

POSTGRES_DB_OWNER="oc_$POSTGRES_USER" /usr/local/bin/aio-pg-init

rm "$DUMP_DIR/initialization.failed"

set +ex
