#!/bin/bash
set -ex

touch /mnt/data/initdb.failed

psql -v ON_ERROR_STOP=1 --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" <<-EOSQL
	CREATE USER "oc_$POSTGRES_USER" WITH PASSWORD '$POSTGRES_PASSWORD' CREATEDB;
	ALTER DATABASE "$POSTGRES_DB" OWNER TO "oc_$POSTGRES_USER";
EOSQL

rm /mnt/data/initdb.failed

set +ex
