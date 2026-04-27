#!/bin/bash
set -e

# Validate required environment variables
if [ -z "$BASE_URL" ]; then
    echo "BASE_URL must be provided. Exiting!"
    exit 1
fi

export TZ="${TZ:-Etc/UTC}"
PGDATA="/var/lib/postgresql/data"

# Initialize PostgreSQL data directory on first run.
# No su/chown needed — we already own PGDATA (uid=10001 owns the volume).
if [ -z "$(ls -A "$PGDATA" 2>/dev/null)" ]; then
    echo "Initializing PostgreSQL database for Windmill..."

    initdb -D "$PGDATA" \
        --username=windmill \
        --auth-local=trust \
        --auth-host=trust \
        --no-instructions

    # Allow local connections without a password; listen only on localhost
    cat > "$PGDATA/pg_hba.conf" << 'EOF'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
EOF

    cat >> "$PGDATA/postgresql.conf" << 'EOF'
listen_addresses = 'localhost'
EOF

    # Start PostgreSQL temporarily to create the windmill database, then stop it.
    # supervisord will restart it properly afterward.
    pg_ctl -D "$PGDATA" start -w -o "-k /var/run/postgresql"
    psql -h /var/run/postgresql -U windmill postgres \
        -c "CREATE DATABASE windmill OWNER windmill;"
    pg_ctl -D "$PGDATA" stop -w

    echo "PostgreSQL initialization complete."
fi

exec supervisord -c /supervisord.conf
