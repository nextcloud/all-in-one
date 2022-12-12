# Probably from here https://github.com/elastic/elasticsearch/blob/main/distribution/docker/src/docker/Dockerfile
FROM elasticsearch:7.17.8

RUN elasticsearch-plugin install --batch ingest-attachment

HEALTHCHECK CMD curl -skfI localhost:9200 || exit 1
