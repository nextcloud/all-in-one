# Faster CVE fixes / fewer scanner findings in AIO images

## Current state (as of this repo)

| Kind | Containers | Base |
|---|---|---|
| Own builds on Alpine 3.24 | alpine, apache (httpd), borgbackup, clamav, docker-socket-proxy (haproxy), domaincheck, imaginary, mastercontainer (php), nextcloud (php), notify-push, postgresql, redis, talk, talk-recording (python), watchtower | official Docker Hub `*-alpine3.24` images, all run `apk upgrade --no-cache -a` |
| Thin wrappers around vendor images | collabora, collabora-online, onlyoffice, eurooffice, fulltextsearch (Elastic UBI, runs `microdnf update`), harp, whiteboard | whatever upstream ships (Debian/Ubuntu/UBI) |
| Copied upstream binaries | talk (nats, eturnal, spreed-signaling), mastercontainer (docker cli, caddy), apache (caddy) | Go/Erlang toolchain version chosen by upstream |

How fixes reach users today:

- Base image *tags* are bumped by Dependabot (`.github/dependabot.yml`, daily). Dependabot only reacts to **new tags**. A distro package fix (e.g. openssl in Alpine) usually does **not** produce a new `alpine:3.24.x`/`php:…-alpine3.24` tag quickly, so no PR appears.
- `apk upgrade` only helps when an image is actually **rebuilt**. Images are built only when someone runs the `repo-sync`/`build_images` workflow in `nextcloud-releases/all-in-one`, then promoted develop → beta → latest (readme: "around 2 weeks").
- `onlyoffice` and `collabora-online` are not in the Dependabot docker list.

So the biggest delay is **process (rebuild + promotion cadence)**, not the choice of base image. Alpine is already one of the better bases for scanner counts.

---

## Option 1 – Rebuild regularly and when fixes exist (highest impact, low effort)

1. **Scheduled rebuild** (e.g. nightly to `develop`, weekly to `latest`) with `docker buildx build --pull --no-cache`, even when no file changed. This picks up every distro fix that `apk upgrade` / `microdnf update` can pull.
2. **Scan-triggered rebuild**: run Trivy or Grype daily against the published `latest` images with `--ignore-unfixed`. If a *fixable* HIGH/CRITICAL CVE shows up, trigger a rebuild automatically. This turns "fix available upstream" → "fix in our image" into hours.
3. **Security fast-track**: a rebuild with *identical* component versions (only refreshed OS packages) carries little regression risk. Allow it to skip the 2-week beta soak, or shorten it to a smoke test (the existing Playwright tests). Full soak stays for version bumps.
4. A pure rebuild changes the image digest. Check that the mastercontainer's update check offers it to users like any other update.

Tools: `aquasecurity/trivy-action`, `anchore/scan-action` (Grype), both free. Upload SARIF results to GitHub code scanning to track trends.

## Option 2 – Pin base images by digest

Use `FROM alpine:3.24.2@sha256:…`. Dependabot and Renovate both open a PR when the digest behind the tag changes (an upstream rebuild with fixed packages), not only when the tag changes. Bonus: builds become reproducible. Cost: more PRs (auto-merge them after CI, as the `dependabot-approve-merge.yml` workflow already does for some updates).

Also add the missing directories (`onlyoffice`, `collabora-online`) to Dependabot.

## Option 3 – Fewer packages in the final image (fewer findings, no base change)

Every package that's not installed is a CVE that can't be reported.

- Make sure build deps (`$PHPIZE_DEPS`, `-dev` packages, compilers, `git`) are removed in the same layer or, better, built in a separate stage. Only copy `.so` files and runtime libraries into the final stage.
- Drop tools only used for debugging (curl, bash, shadow, etc.) where scripts don't need them. Check per container.
- Go binaries (watchtower, imaginary build, nats, spreed-signaling, docker cli, caddy): scanners report **Go stdlib** CVEs embedded in the binary. Fix by building them from source with the current Go toolchain (as watchtower/imaginary already do) instead of copying upstream binaries built with older Go. Running `govulncheck` shows whether a reported CVE is actually reachable.
- Static Go/Rust services (notify-push, domaincheck, nats, signaling) could run on `scratch` or `gcr.io/distroless/static` with zero OS packages. This only helps if they don't need a shell for start/healthcheck scripts, which most AIO containers do.

## Option 4 – Switch base images to hardened / minimal-CVE images

| Option | What it is | Pros | Cons for AIO |
|---|---|---|---|
| **Stay on Alpine** (current) | musl, small, fast secdb updates | Few packages, low scanner noise, team knows it | Fix speed depends on our rebuild cadence (Option 1) |
| **Debian slim / Ubuntu** | glibc, big ecosystem | Compatibility | Many "unfixed/won't fix" CVEs in scanner output → **more** findings. Not recommended. |
| **Wolfi** (`cgr.dev/chainguard/wolfi-base`, free) | Chainguard's glibc "undistro", apk-based, packages rebuilt continuously, CVE fixes usually within days | Very low CVE counts, glibc, same `apk` workflow as Alpine, free base | Package names/availability differ from Alpine (php extensions, janus, clamav, borg…), so each package needs checking. Rolling: no "3.24 stable branch", so upgrades can be surprising. |
| **Chainguard Images** (`cgr.dev/chainguard/php`, `postgres`, `redis`, …) | Prebuilt hardened images on Wolfi, signed, SBOMs | Near-zero CVEs, daily rebuilds | Free tier gives only `latest`/`latest-dev`. Pinned versions (e.g. php 8.4, postgres 18) need a paid subscription. Default images have no shell (`-dev` variants have one). |
| **Docker Hardened Images (DHI)** | Docker's hardened Alpine- and Debian-based images (php, postgres, redis, httpd, python, …) | Drop-in-ish replacement for the official images we use now, near-zero CVEs, SBOM + VEX attached. Docker made the catalogue free in late 2025 (check current terms; SLA-backed fixes are a paid tier). | Non-root and shell-less by default, so start scripts and `apk add` steps need `-dev` variants or multi-stage builds |
| **Google distroless** | Debian-based, no shell/package manager | Tiny | Debian CVE cadence, no shell, only fits static binaries (see Option 3) |
| **Ubuntu Chiselled / UBI micro** | Minimal Ubuntu/RHEL slices | Vendor-backed fixes | No gain over Alpine for AIO, and a new toolchain to learn |
| **Bitnami** | – | – | Moved to paid "Bitnami Secure Images" in 2025. Avoid. |

Commercial alternatives (Minimus, Echo, Root.io, RapidFort) exist but add a vendor dependency, which doesn't fit an OSS project.

Realistic path: try **DHI or Wolfi on one or two own-built images** (e.g. `redis` and `postgresql`, which install few extras) and compare Trivy counts and build friction before touching `nextcloud`/`mastercontainer`.

## Option 5 – Vendor images (Collabora, OnlyOffice, Euro-Office, Elasticsearch, HaRP, Whiteboard)

These make up most findings and we control them least.

- Where the image has a shell and package manager, add an OS upgrade step (`apt-get update && apt-get upgrade -y`, like fulltextsearch already does with `microdnf update`) and rebuild on schedule (Option 1). Collabora `code` no longer has a shell, so only a version bump helps there.
- Elasticsearch also publishes **Wolfi-based** images (`docker.elastic.co/elasticsearch/elasticsearch-wolfi`). Check availability for the version used. They usually show far fewer CVEs than the UBI/Docker Hub image.
- Report scanner findings upstream (Collabora, OnlyOffice, Nextcloud HaRP/Whiteboard images are partly under Nextcloud's own influence).

## Option 6 – Reduce *false* findings (scanner hygiene)

- Publish **SBOM and provenance attestations** (`docker buildx build --sbom=true --provenance=true`). Scanners then use exact package data instead of guessing.
- Publish **OpenVEX** statements for CVEs that are investigated and not exploitable in AIO (e.g. vulnerable function never called). Trivy and Grype honour VEX, so users' scanners report fewer issues. This needs per-CVE human review.
- Use `--ignore-unfixed` in our own gating so CI fails only on actionable issues.

---

## Suggested order

1. Scheduled + scan-triggered rebuilds with a security fast-track (Option 1). Biggest effect, no Dockerfile changes.
2. Digest pinning + Dependabot coverage for all directories (Option 2).
3. Trim final images and rebuild copied Go binaries with current Go (Option 3).
4. Pilot DHI or Wolfi on a simple container, then decide (Option 4).
5. SBOM/VEX publishing (Option 6) once the counts are down.

Options 1, 2 and 4 touch the build pipeline in `nextcloud-releases/all-in-one` and several containers, so per `AGENTS.md` open a ticket to align with maintainers before implementing.
