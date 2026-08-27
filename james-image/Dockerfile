# James on PostgreSQL instead of the embedded Derby the stock image defaults to
# (Anirban's request on PR #1). This is the only image in this stack that is built
# rather than pulled, and it exists for one reason: apache/james:jpa-3.8.2 bundles
# ONLY derby-10.14.2.0.jar, and OpenJPA resolves JDBC drivers off the JVM
# classpath, which is /root/libs/* (see /root/jib-classpath-file in the image).
#
# /root/extensions-jars is NOT a substitute: James loads that directory with its own
# Guice classloader for mailets/matchers, so a JDBC driver dropped there is invisible
# to DriverManager. The jar has to land in /root/libs.
#
# There is also no apache/james:postgres-3.8.x to switch to. Upstream's postgres
# distribution starts at postgres-3.9.0, which is a different app with a different
# config layout, not a drop-in for the jpa-3.8.2 conf/ directory this repo ships.
FROM apache/james:jpa-3.8.2

# Pinned deliberately. Bump intentionally and re-test, rather than tracking latest.
ARG POSTGRES_JDBC_VERSION=42.7.4
ADD --chmod=644 \
    https://repo1.maven.org/maven2/org/postgresql/postgresql/${POSTGRES_JDBC_VERSION}/postgresql-${POSTGRES_JDBC_VERSION}.jar \
    /root/libs/postgresql.jar
