# From https://github.com/containrrr/watchtower/blob/main/dockerfiles/Dockerfile.self-contained
FROM containrrr/watchtower:latest as watchtower

FROM alpine:latest

RUN apk add --update --no-cache bash
COPY --from=watchtower /watchtower /

COPY start.sh /
RUN chmod +x /start.sh

USER root
ENTRYPOINT ["/start.sh"]
