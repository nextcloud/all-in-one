#!/bin/bash
set -e

# Validate required environment variables
if [ -z "$BASE_URL" ]; then
    echo "BASE_URL must be provided. Exiting!"
    exit 1
fi

export TZ="${TZ:-Etc/UTC}"

# The Docker daemon injects SSL_CERT_FILE=/etc/ssl/certs/ca-certificates.crt and
# SSL_CERT_DIR=/etc/ssl/certs into every container, but /etc/ssl/certs/ is mode 700
# (root only) in the base Windmill image, so uid=1000 cannot traverse it.
# Build a combined, world-readable CA bundle in the writable /tmp tmpfs and
# override all SSL cert env vars so Windmill and its sub-processes use it.
_COMBINED_BUNDLE="/tmp/ca-bundle.crt"
cat /etc/ssl/ca-bundle.crt /etc/ssl/cert.pem > "$_COMBINED_BUNDLE" 2>/dev/null || \
    cat /etc/ssl/ca-bundle.crt > "$_COMBINED_BUNDLE" 2>/dev/null || true
if [ -s "$_COMBINED_BUNDLE" ]; then
    export SSL_CERT_FILE="$_COMBINED_BUNDLE"
    export CURL_CA_BUNDLE="$_COMBINED_BUNDLE"
    export REQUESTS_CA_BUNDLE="$_COMBINED_BUNDLE"
    export NODE_EXTRA_CA_CERTS="$_COMBINED_BUNDLE"
    # Unset SSL_CERT_DIR so rustls-native-certs does not also try to traverse
    # the inaccessible /etc/ssl/certs/ directory.
    unset SSL_CERT_DIR
fi

PGDATA="/var/lib/postgresql/data"

# Initialize PostgreSQL data directory on first run.
# No su/chown needed — we already own PGDATA (the windmill user owns the volume).
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
