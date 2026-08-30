# James JDBC driver

`apache/james:jpa-3.8.2` bundles only `derby-10.14.2.0.jar`, and OpenJPA resolves JDBC
drivers off the JVM classpath. The image's `/root/jib-classpath-file` is:

    /root/resources:/root/classes:/root/libs/*

That last entry is a wildcard, so a jar mounted into `/root/libs` is on the classpath
with no image build. `docker-compose.yml` mounts this one as a single file, which
leaves the image's other 281 jars in place; mounting the whole directory would hide
them and James would not start.

`/root/extensions-jars` is not an alternative. James loads that directory with its own
Guice classloader for mailets and matchers, so a driver dropped there is invisible to
`DriverManager`.

The jar is committed rather than downloaded so that a fresh clone can `docker compose
up` with nothing fetched first. Anirban asked on PR #1 for no custom image build, and
this is the trade that buys.

## Provenance

    https://repo1.maven.org/maven2/org/postgresql/postgresql/42.7.4/postgresql-42.7.4.jar

    sha1   264310fd7b2cd76738787dc0b9f7ea2e3b11adc1
    sha256 188976721ead8e8627eb6d8389d500dccc0c9bebd885268a3047180274a6031e

The sha1 matches Maven Central's published `.sha1` for that artifact. To bump the
version, download the new jar, check its sha1 against Maven Central's, replace the file,
update the mount in `docker-compose.yml` and this file, and restart James. Do it
deliberately and re-test rather than tracking latest. PostgreSQL JDBC is BSD-licensed.
