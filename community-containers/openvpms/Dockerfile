# syntax=docker/dockerfile:latest
FROM tomcat:9.0-jdk17-temurin-alpine

ARG OPENVPMS_VERSION=2.4.0.1
ARG MARIADB_DRIVER_VERSION=3.4.1
ARG REDISSON_VERSION=4.3.0

RUN set -ex; \
    apk upgrade --no-cache -a; \
    apk add --no-cache \
        bash \
        curl \
        mariadb-client \
        redis \
        unzip; \
    \
    # Change Tomcat's connector port from 8080 to 11001
    sed -i 's/port="8080"/port="11001"/' /usr/local/tomcat/conf/server.xml; \
    \
    # Download MariaDB JDBC driver into Tomcat's shared lib directory
    curl -fsSL -o /usr/local/tomcat/lib/mariadb-java-client.jar \
        "https://repo1.maven.org/maven2/org/mariadb/jdbc/mariadb-java-client/${MARIADB_DRIVER_VERSION}/mariadb-java-client-${MARIADB_DRIVER_VERSION}.jar"; \
    \
    # Download Redisson JARs for Redis-backed Tomcat session management
    curl -fsSL -o /usr/local/tomcat/lib/redisson-all.jar \
        "https://repo1.maven.org/maven2/org/redisson/redisson-all/${REDISSON_VERSION}/redisson-all-${REDISSON_VERSION}.jar"; \
    curl -fsSL -o /usr/local/tomcat/lib/redisson-tomcat-9.jar \
        "https://repo1.maven.org/maven2/org/redisson/redisson-tomcat-9/${REDISSON_VERSION}/redisson-tomcat-9-${REDISSON_VERSION}.jar"; \
    \
    # Remove default webapps
    rm -rf /usr/local/tomcat/webapps/*; \
    \
    # Download and extract OpenVPMS release archive
    curl -fsSL -o /tmp/openvpms-release.zip \
        "https://repository.openvpms.org/releases/org/openvpms/openvpms-release/${OPENVPMS_VERSION}/openvpms-release-${OPENVPMS_VERSION}.zip"; \
    unzip -q /tmp/openvpms-release.zip -d /tmp/openvpms-release; \
    \
    # Extract and deploy the WAR file — fail explicitly if not exactly one WAR is found
    WAR_COUNT="$(find /tmp/openvpms-release -name '*.war' | wc -l)"; \
    if [ "${WAR_COUNT}" -ne 1 ]; then \
        echo "Expected exactly 1 WAR file, found ${WAR_COUNT}"; exit 1; \
    fi; \
    find /tmp/openvpms-release -name '*.war' \
        -exec cp {} /usr/local/tomcat/webapps/openvpms.war \;; \
    \
    # Copy DB setup scripts — fail explicitly if the db directory is not found
    DB_DIR="$(find /tmp/openvpms-release -type d -name 'db' | head -1)"; \
    if [ -z "${DB_DIR}" ]; then \
        echo "DB setup directory not found in release archive"; exit 1; \
    fi; \
    mkdir -p /setup/db; \
    cp -r "${DB_DIR}/"* /setup/db/; \
    \
    # Clean up
    rm -rf /tmp/openvpms-release /tmp/openvpms-release.zip

COPY --chmod=755 entrypoint.sh /entrypoint.sh
COPY --chmod=755 healthcheck.sh /healthcheck.sh

RUN mkdir -p /opt/openvpms/data

VOLUME /opt/openvpms/data

EXPOSE 11001

HEALTHCHECK --interval=30s --timeout=10s --start-period=120s --retries=3 \
    CMD /healthcheck.sh

ENTRYPOINT ["/entrypoint.sh"]

LABEL org.opencontainers.image.title="aio-openvpms" \
      org.opencontainers.image.description="OpenVPMS for Nextcloud AIO" \
      org.opencontainers.image.source="https://github.com/szaimen/aio-openvpms"
