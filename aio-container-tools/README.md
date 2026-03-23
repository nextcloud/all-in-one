# aio-container-tools

Standalone tools for Nextcloud AIO containers, for tasks that shouldn't be executed in a shell environment
(e.g. due to string handling issues).

Golang was choosen because it doesn't require additional runtimes in the containers, and has a pretty easy
syntax that is comprehensible even for people without much experience with the language.

The tools should be built in the container image build process, so they are built for the correct target
platform in multi-arch builds. See below for an example.

## Build process

To include the binary of `aio-pg-healhcheck` into your container image, include such a snippet into your Containerfile:

```dockerfile
FROM docker.io/library/golang:alpine AS golang-builder

# hadolint ignore=DL3022
COPY --from=aio-container-tools . /tmp/aio-container-tools/
RUN cd /tmp/aio-container-tools \
 && go build -o /usr/local/bin/aio-pg-healthcheck ./cmd/aio-pg-healthcheck

FROM your-base-image
COPY --from=golang-builder /usr/local/bin/aio-pg-healthcheck /usr/local/bin/
```

To build it you now have to pass the aio-container-tools directory as additional, named build-context like this:

```bash
docker build \
  --build-context aio-container-tools=/path/to/all-in-one/aio-container-tools \
  .
```

#### Remote git variant (without local clone of this repo)

```bash
docker build \
  --build-context aio-container-tools="https://github.com/nextcloud-releases/all-in-one.git#main:aio-container-tools" \
  .
```
