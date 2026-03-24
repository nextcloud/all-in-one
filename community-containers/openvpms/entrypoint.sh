#!/bin/bash
set -e

# Wait for the MariaDB database to be ready
echo "Waiting for database at ${DB_HOST} to be ready..."
until mariadb -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" -e "SELECT 1" >/dev/null 2>&1; do
    echo "Database not yet available, retrying in 3 seconds..."
    sleep 3
done
echo "Database is ready."

# Wait for Redis to be ready
echo "Waiting for Redis at ${REDIS_HOST}:6379 to be ready..."
until redis-cli -h "${REDIS_HOST}" ping 2>/dev/null | grep -q "PONG"; do
    echo "Redis not yet available, retrying in 3 seconds..."
    sleep 3
done
echo "Redis is ready."

# Write the Redisson configuration for Redis-backed session management
cat > /usr/local/tomcat/conf/redisson.yaml <<EOF
singleServerConfig:
  address: "redis://${REDIS_HOST}:6379"
EOF

# Write the JNDI datasource configuration, substituting env vars at runtime
cat > /usr/local/tomcat/conf/context.xml <<EOF
<?xml version="1.0" encoding="UTF-8"?>
<Context>
    <Resource name="jdbc/openvpms"
              auth="Container"
              type="javax.sql.DataSource"
              driverClassName="org.mariadb.jdbc.Driver"
              url="jdbc:mariadb://${DB_HOST}/${DB_NAME}?useSSL=false&amp;allowPublicKeyRetrieval=true&amp;characterEncoding=UTF-8"
              username="${DB_USER}"
              password="${DB_PASSWORD}"
              maxTotal="20"
              maxIdle="10"
              maxWaitMillis="-1"/>
    <Manager className="org.redisson.tomcat.RedissonSessionManager"
             configPath="/usr/local/tomcat/conf/redisson.yaml"
             readMode="REDIS"
             updateMode="DEFAULT"/>
</Context>
EOF

# Initialise the database schema on first run only
INIT_FLAG="/opt/openvpms/data/.db-initialized"
if [ ! -f "${INIT_FLAG}" ]; then
    echo "First run detected – initialising OpenVPMS database schema..."
    SQL_SCRIPTS="$(find /setup/db -name '*.sql' | sort)"
    if [ -n "${SQL_SCRIPTS}" ]; then
        while IFS= read -r sql_file; do
            echo "Applying ${sql_file}..."
            mariadb -h "${DB_HOST}" -u "${DB_USER}" -p"${DB_PASSWORD}" "${DB_NAME}" < "${sql_file}"
        done <<< "${SQL_SCRIPTS}"
        touch "${INIT_FLAG}"
        echo "Database schema initialised successfully."
    else
        echo "Warning: no SQL setup scripts found under /setup/db"
    fi
fi

echo "Starting OpenVPMS on port 11001..."
exec catalina.sh run
