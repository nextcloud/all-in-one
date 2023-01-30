# From https://github.com/containrrr/watchtower/blob/main/dockerfiles/Dockerfile.self-contained
FROM containrrr/watchtower:1.5.3 as watchtower

FROM alpine:3.17.1

RUN apk add --no-cache bash
COPY --from=watchtower /watchtower /

COPY start.sh /
RUN chmod +x /start.sh

USER root
ENTRYPOINT ["/start.sh"]
LABEL com.centurylinklabs.watchtower.monitor-only="true"
