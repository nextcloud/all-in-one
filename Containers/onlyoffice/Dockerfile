# From https://github.com/ONLYOFFICE/Docker-DocumentServer/blob/master/Dockerfile
FROM onlyoffice/documentserver:7.2.2.56

HEALTHCHECK CMD nc -z localhost 80 || exit 1
LABEL com.centurylinklabs.watchtower.monitor-only="true"
