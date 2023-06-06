# From https://github.com/ONLYOFFICE/Docker-DocumentServer/blob/master/Dockerfile
FROM onlyoffice/documentserver:7.3.3.50

# USER root is probably used

HEALTHCHECK CMD nc -z localhost 80 || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
