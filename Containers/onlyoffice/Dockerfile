# syntax=docker/dockerfile:latest
# From https://github.com/ONLYOFFICE/Docker-DocumentServer/blob/master/Dockerfile
FROM onlyoffice/documentserver:8.1.0.1

# USER root is probably used

HEALTHCHECK CMD nc -z 127.0.0.1 80 || exit 1
LABEL com.centurylinklabs.watchtower.enable="false"
