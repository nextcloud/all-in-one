#!/bin/bash

if [ "$AIO_LOG_LEVEL" = 'debug' ]; then
    set -x
fi

set -e

touch "$DUMP_DIR/initialization.failed"

# Do not print the password via xtrace
{ set +x; } 2>/dev/null
psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
	-v "pg_new_password=$POSTGRES_PASSWORD" <<-EOSQL
	CREATE USER "oc_$POSTGRES_USER" WITH PASSWORD :'pg_new_password' CREATEDB;
	ALTER DATABASE "$POSTGRES_DB" OWNER TO "oc_$POSTGRES_USER";
	GRANT ALL PRIVILEGES ON DATABASE "$POSTGRES_DB" TO "oc_$POSTGRES_USER";
	GRANT ALL PRIVILEGES ON SCHEMA public TO "oc_$POSTGRES_USER";
EOSQL

rm "$DUMP_DIR/initialization.failed"

set +e
