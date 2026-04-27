#!/bin/bash
set -e

# Validate required environment variables
if [ -z "$BASE_URL" ]; then
    echo "BASE_URL must be provided. Exiting!"
    exit 1
fi

export TZ="${TZ:-Etc/UTC}"
PGDATA="/var/lib/postgresql/data"

# Fix runtime directory permissions (tmpfs mounts start owned by root)
chown postgres:postgres /var/run/postgresql
chmod 775 /var/run/postgresql

# Initialize PostgreSQL data directory on first run
if [ -z "$(ls -A "$PGDATA" 2>/dev/null)" ]; then
    echo "Initializing PostgreSQL database for Windmill..."

    # Ensure the data directory is owned by postgres before initdb
    chown postgres:postgres "$PGDATA"

    # Run initdb as the postgres user
    su postgres -s /bin/bash -c "initdb -D '$PGDATA' --username=postgres --auth-local=trust --auth-host=trust --no-instructions"

    # Allow connections from localhost without a password
    cat > "$PGDATA/pg_hba.conf" << 'EOF'
# TYPE  DATABASE        USER            ADDRESS                 METHOD
local   all             all                                     trust
host    all             all             127.0.0.1/32            trust
host    all             all             ::1/128                 trust
EOF

    # Only listen on localhost; the database is not exposed externally
    cat >> "$PGDATA/postgresql.conf" << 'EOF'
listen_addresses = 'localhost'
EOF

    # Start PostgreSQL temporarily to create the windmill database and user
    su postgres -s /bin/bash -c "pg_ctl -D '$PGDATA' start -w -o '-k /var/run/postgresql'"
    su postgres -s /bin/bash -c "psql -h /var/run/postgresql -c \"CREATE USER windmill;\""
    su postgres -s /bin/bash -c "psql -h /var/run/postgresql -c \"CREATE DATABASE windmill OWNER windmill;\""
    su postgres -s /bin/bash -c "pg_ctl -D '$PGDATA' stop"

    echo "PostgreSQL initialization complete."
fi

exec supervisord -c /supervisord.conf
