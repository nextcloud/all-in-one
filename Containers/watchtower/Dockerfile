# From https://github.com/containrrr/watchtower/blob/main/dockerfiles/Dockerfile.self-contained
FROM containrrr/watchtower:1.4.0 as watchtower

FROM alpine:3.15.0

RUN apk add --update --no-cache bash
COPY --from=watchtower /watchtower /

COPY start.sh /
RUN chmod +x /start.sh

USER root
ENTRYPOINT ["/start.sh"]
