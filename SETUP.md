# Setup steps

This is Nextcloud AIO ("manual install" method) with all optional features enabled:
Talk, Talk Recording, Collabora, ClamAV, Imaginary, Fulltextsearch, Whiteboard.
Collabora is the only office editor — AIO's OnlyOffice and EuroOffice containers
were removed at Anirban's request (PR #1 review), along with their volumes,
secrets, and `*_ENABLED`/`*_HOST` env vars. No external storage backend (S3/Ceph)
is involved — AIO's own containers handle everything. See **Object storage (S3)**
below for what that would take, and note AIO's own "S3 support" means its bundled
MinIO container for backups, not primary storage on an external endpoint.

All 14 images are pinned to tag `20260805_083533`, which bundles Nextcloud 33.0.7
(per Anirban's request — confirmed by inspecting the image's baked-in
`NEXTCLOUD_VERSION` before pinning, since AIO's own tags don't carry Nextcloud
version numbers). AIO has never shipped a 34.x build; 33.0.7 is the newest version
it actually provides. `NEXTCLOUD_STARTUP_APPS` also includes `integration_google`
and `integration_onedrive` so both come pre-installed on a fresh install — each
still needs its own OAuth app (Google Cloud Console / Azure AD) registered and
configured in Nextcloud's admin settings before it actually connects to anything.

1. `cp .env.example .env` and fill in real values — all the `change-me` secrets need
   unique, good passwords (avoid `@` and `:` in them), and `NC_DOMAIN` needs to be
   the hostname you'll actually use.
   - **On macOS + Docker Desktop**, also change `NEXTCLOUD_MOUNT` and
     `NEXTCLOUD_TRUSTED_CACERTS_DIR` away from their upstream defaults (`/mnt/` and
     `/usr/local/share/ca-certificates/...`). Docker Desktop only bind-mounts paths
     under locations it shares (your home directory, `/Volumes`, `/private`,
     `/tmp`) — anything else fails on first `up` with `mounts denied`. Point both
     at absolute paths under this checkout instead, e.g.
     `/path/to/this/repo/host-mounts/mount`, and `mkdir -p` them first. Linux hosts
     don't have this restriction and can keep the upstream defaults.

2. Generate a self-signed TLS cert for that hostname (swap for a real cert outside
   local dev). It **must** carry a `subjectAltName`: a CN-only cert is rejected
   outright by Node/OpenSSL clients even once the CA is trusted, and the office
   containers then fail to fetch documents with "Download failed" / "The document
   could not be saved". `CA:TRUE` lets the cert act as its own trust anchor, which is
   what makes it installable in the container trust stores in step 4a.
   ```sh
   sudo mkdir -p /etc/nginx/certs
   cat > /tmp/nc-san.cnf <<'EOF'
   [req]
   distinguished_name = dn
   x509_extensions = v3
   prompt = no
   [dn]
   CN = nextcloud.local
   [v3]
   subjectAltName = DNS:nextcloud.local, DNS:localhost, IP:127.0.0.1
   basicConstraints = critical, CA:TRUE
   keyUsage = critical, digitalSignature, keyEncipherment, keyCertSign
   extendedKeyUsage = serverAuth
   EOF
   sudo openssl req -x509 -newkey rsa:2048 -sha256 -days 3650 -nodes \
     -keyout /etc/nginx/certs/nextcloud.local.key \
     -out /etc/nginx/certs/nextcloud.local.crt \
     -config /tmp/nc-san.cnf
   ```
   Adjust the filenames/CN/SAN if you chose a different `NC_DOMAIN`. Verify with:
   ```sh
   openssl x509 -in /etc/nginx/certs/nextcloud.local.crt -noout -ext subjectAltName
   ```
   If that prints "No extensions in certificate", the cert is wrong -- regenerate it.

   Avoid a `.local` hostname for `NC_DOMAIN` on macOS. The OS routes `.local` through
   mDNS/Bonjour and blocks for the full 5s multicast timeout on every lookup mDNS
   cannot answer, even with the name in `/etc/hosts`, so every new connection pays a
   flat 5s penalty. A `.test` name avoids it. Check with:
   `curl -o /dev/null -w '%{time_namelookup}\n' https://<host>/`

2a. Copy the cert into `NEXTCLOUD_TRUSTED_CACERTS_DIR` (see `.env`), named
   `<NC_DOMAIN>.crt`. The compose file mounts that directory into the containers that
   call back to Nextcloud over HTTPS -- so the name has to match `NC_DOMAIN`:
   ```sh
   cp /etc/nginx/certs/nextcloud.local.crt host-mounts/trusted-cacerts/nextcloud.local.crt
   ```
   Re-copy this and recreate those containers whenever the cert is regenerated, or
   Collabora will start failing with "Download failed".

3. Point the existing host nginx at it using `nginx-nextcloud.conf.sample` as a
   starting point (update `server_name` and the cert paths to match), reload nginx.
   Read the comment at the top of that file — HTTPS here isn't optional, AIO assumes
   it unconditionally.

4. Bring the stack up with every optional profile enabled. `.env`/`.env.example`
   set `COMPOSE_PROFILES` to the full profile list, so Compose applies it to every
   command automatically — no `--profile` flags needed for `up`, `down`, `stop`, or
   `ps`:
   ```sh
   docker compose up -d
   ```
   First boot pulls a lot of images (Collabora, Elasticsearch for Fulltextsearch,
   ClamAV's virus DB, etc.) — expect this to take a while and use several GB of
   disk/RAM.

   **If `COMPOSE_PROFILES` isn't set** (e.g. a shell that doesn't load `.env`), pass
   the flags explicitly and keep them identical across every `up`/`down`/`stop` call:
   ```sh
   docker compose --profile collabora --profile talk --profile talk-recording \
     --profile clamav --profile imaginary --profile fulltextsearch \
     --profile whiteboard --profile ollama --profile james \
     --profile context-chat up -d
   ```
   A command missing some of these flags doesn't just skip those services — Compose
   treats them as outside the stack entirely for that command. This is not
   theoretical: a bare `docker compose down -v` run without any profile flags once
   deleted the volumes for the four always-on services (Nextcloud, database, redis,
   apache) while silently leaving every profiled service's volume untouched,
   forcing a full reinstall of Talk/Collabora/Context Chat/etc. See **Stopping the
   stack** below — never run `down -v` on this repo unless you actually intend to
   destroy all data.

5. `docker compose logs nextcloud-aio-nextcloud` — confirm it installed cleanly.
   `docker compose ps` — all containers should reach a healthy state eventually
   (Collabora and ClamAV take the longest to pass their healthcheck on first boot).

6. Add an entry for `NC_DOMAIN` to your machine's `/etc/hosts` pointing at
   `127.0.0.1` (it's not real DNS — nothing else will resolve it), then visit
   `https://<NC_DOMAIN>`, log in with `admin` / `NEXTCLOUD_PASSWORD` from `.env`.

## Verifying it actually works

```sh
# 1. Nextcloud is installed and responding (adjust hostname to your NC_DOMAIN)
curl -k https://nextcloud.local/status.php
# expect: "installed":true

# 2. Upload a file through the real API a user would hit
echo "test file" > /tmp/test.txt
curl -k -u admin:<NEXTCLOUD_PASSWORD> -T /tmp/test.txt \
  https://nextcloud.local/remote.php/dav/files/admin/test.txt
# expect: HTTP 201

# 3. Read it back
curl -k -u admin:<NEXTCLOUD_PASSWORD> \
  https://nextcloud.local/remote.php/dav/files/admin/test.txt
# expect: HTTP 200, body "test file"

# 4. Confirm the optional apps actually got enabled, not just the containers started
docker compose exec -u www-data nextcloud-aio-nextcloud php occ app:list --enabled | \
  grep -iE "talk|richdocuments|files_fulltextsearch|files_antivirus"
```
(`-k` skips cert validation for the self-signed cert — drop it once you're on a
real one.)

If step 2 or 3 fails, check `docker compose logs nextcloud-aio-nextcloud` and
`docker compose logs nextcloud-aio-apache`. If step 4 shows an app missing despite
its container being healthy, check that its `*_ENABLED` var in `.env` is `"yes"`
(with quotes) and that you passed its `--profile` flag on `up`.

## Stopping the stack

With `COMPOSE_PROFILES` set in `.env` (see step 4 above), plain `docker compose`
commands already apply to every service. If you're running without it loaded,
pass the same explicit `--profile` flags used on `up` to every command below, or
Compose only touches the non-profiled services and leaves the rest running.

Stop and remove the containers, keeping all data (named volumes persist):
```sh
docker compose down
```

Or just stop them (containers stick around, slightly faster to bring back with `up`):
```sh
docker compose stop
```

**Never run `docker compose down -v`** unless you deliberately want to destroy
every named volume — Nextcloud's install, its database, all uploaded files, the
Context Chat embeddings, everything. There is no per-service confirmation prompt.
If you genuinely need to wipe and reinstall from scratch, running it *with*
`COMPOSE_PROFILES` set (or the full explicit `--profile` list) at least makes it
destroy everything consistently instead of silently destroying an inconsistent
subset — which is what happened on 2026-08-25: a flagless `down -v` deleted only
the always-on services' volumes (Nextcloud/database/redis/apache), leaving every
profiled service (Ollama, Context Chat's embeddings, ClamAV's virus DB, Talk,
Collabora, ...) running against data from a Nextcloud install that no longer
existed, and required manually reinstalling every optional app from scratch.

## Local AI assistant (Ollama)

Per Anirban's request: a free, local LLM backend for Nextcloud's Assistant app, for
local testing only — a commercial API (OpenAI/Anthropic) is meant to replace this
when deploying to a real server, by changing `integration_openai`'s admin settings
(nothing else needs to change).

No GPU passthrough under Docker Desktop on Mac, so this runs on CPU — keep to small
models, and expect the first request after a period of inactivity to be slow (model
load + waiting for Nextcloud's background job cron tick, which can take a couple of
minutes) before it warms up.

1. Bring up the container (add `--profile ollama` to the `docker compose up` command
   above) and pull the model set in `.env`'s `OLLAMA_MODEL`:
   ```sh
   docker compose exec nextcloud-aio-ollama ollama pull llama3.2:3b
   ```
2. `assistant` and `integration_openai` are in `NEXTCLOUD_STARTUP_APPS`, so a fresh
   install gets them automatically — but that list only runs once, on first startup,
   and installing an app doesn't configure it. On an already-provisioned instance
   (or to point `integration_openai` at Ollama), run:
   ```sh
   CT=nextcloud-aio-nextcloud
   docker compose exec -u www-data $CT php occ app:install integration_openai
   docker compose exec -u www-data $CT php occ app:enable assistant
   docker compose exec -u www-data $CT php occ config:app:set integration_openai url --value="http://nextcloud-aio-ollama:11434/v1"
   docker compose exec -u www-data $CT php occ config:app:set integration_openai service_name --value="Ollama (local)"
   docker compose exec -u www-data $CT php occ config:app:set integration_openai api_key --value="ollama"
   docker compose exec -u www-data $CT php occ config:app:set integration_openai request_timeout --value="120"
   docker compose exec -u www-data $CT php occ config:app:set integration_openai default_completion_model_id --value="llama3.2:3b"
   docker compose exec -u www-data $CT php occ config:app:set integration_openai llm_provider_enabled --value="1"
   docker compose exec -u www-data $CT php occ config:app:set integration_openai chat_endpoint_enabled --value="1"
   ```
   (`api_key` can be any non-empty value — Ollama doesn't check it.)
3. Test in the UI: log in, open the Assistant (sparkle icon in the top nav, or via
   the Apps list), pick "Free prompt", and submit something.

Only the LLM/chat provider is enabled above. Translate, Generate image, and
Transcribe/Text-to-speech are visible in the Assistant UI but won't work — each
needs its own separate provider config (`translation_provider_enabled`,
`t2i_provider_enabled`, `stt_provider_enabled`/`tts_provider_enabled` plus a model
capable of that task) which isn't set up here.

## Context Chat (Q&A over Nextcloud documents)

Lets the Assistant app answer questions using the content of a user's own files as
context, instead of just the bare LLM. This is separate infrastructure from the
Ollama wiring above: it needs AppAPI (Nextcloud's framework for running external-app
"ExApps") plus a running `context_chat_backend` container that does the actual
embedding/retrieval, reusing the existing Ollama model as its text-to-text provider.

Real Nextcloud AIO (the mastercontainer version) gives AppAPI a Docker socket so it
can spin ExApp containers up/down itself. This repo deliberately doesn't do that —
no container here has docker socket access. Instead, `context_chat_backend` runs as
a normal profile-gated service like Ollama/James above (`nextcloud-aio-context-chat-backend`
in `docker-compose.yml`), and gets registered with AppAPI as a **manual-install**
deploy daemon: Nextcloud only ever calls it over plain HTTP on the shared bridge
network, it never starts/stops/manages the container.

**Hardware**: the CPU-only embedding path needs ~12GB RAM and 4+ AVX2-capable cores
(per Nextcloud's own docs) on top of whatever the rest of this stack is already
using. On Docker Desktop for Mac, raise the VM's memory allocation (Settings →
Resources) before enabling this profile, or the container OOMs during model
download/load. There's no GPU passthrough here, same limitation as Ollama above.

1. Bring up the container (add `--profile context-chat` to the `docker compose up`
   command), then install AppAPI and the `context_chat` PHP app (must match the
   backend's version at the major.minor level — both are pinned to the `5.4.x`
   line here):
   ```sh
   CT=nextcloud-aio-nextcloud
   docker compose exec -u www-data $CT php occ app:install app_api
   docker compose exec -u www-data $CT php occ app:install context_chat
   ```
2. Register a manual-install deploy daemon. `nextcloud-aio-context-chat-backend`
   (the container's own hostname on the compose network) is what Nextcloud will
   actually connect to — AppAPI combines this host with the port from the ExApp
   registration below to build the URL it calls:
   ```sh
   docker compose exec -u www-data $CT php occ app_api:daemon:register \
     manual_install "Manual Install" manual-install http \
     nextcloud-aio-context-chat-backend "https://${NC_DOMAIN}"
   ```
3. Register the backend as an ExApp against that daemon. The `secret` here MUST
   match `CONTEXT_CHAT_BACKEND_SECRET` in `.env` exactly — it's the shared HMAC key
   the two sides use to authenticate each other:
   ```sh
   SECRET=$(grep "^CONTEXT_CHAT_BACKEND_SECRET=" .env | cut -d= -f2-)
   docker compose exec -u www-data $CT php occ app_api:app:register \
     context_chat_backend manual_install --wait-finish --json-info \
     "{\"id\":\"context_chat_backend\",\"name\":\"Context Chat Backend\",\"daemon_config_name\":\"manual_install\",\"version\":\"5.4.1\",\"secret\":\"$SECRET\",\"port\":10034}"
   ```
   This blocks until the backend responds to a heartbeat and finishes its `/init`
   step (downloading embedding models from Hugging Face on first run — can take a
   while). If it times out, check `docker compose logs nextcloud-aio-context-chat-backend`
   first; a heartbeat failure almost always means the two containers can't reach
   each other on the compose network, not a config typo.
4. Confirm it's live, then let Nextcloud's normal background job cron (already
   running for this instance) do the initial indexing of existing files:
   ```sh
   docker compose exec -u www-data $CT php occ app_api:app:list
   docker compose exec -u www-data $CT php occ app:list --enabled | grep -i context_chat
   ```
5. Test in the UI: Assistant app → a task type that shows "Context Chat" as an
   available Q&A option. Indexing runs in the background, so a freshly uploaded
   file may not be queryable for a few minutes.

**Known gaps to expect when testing this** (confirmed against `context_chat_backend`'s
source, not just its docs):
- Legacy binary `.xls` isn't in its file-loader map (only `.xlsx`/`.xlsm`/`.ods` are)
  — expect it to fail to parse.
- Scanned/rasterized PDFs have no OCR step in the pipeline — pypdf only pulls an
  existing text layer, so a scanned PDF indexes as empty/unsearchable even though
  the upload itself succeeds.
- Non-file content only shows up if the source app implements Context Chat's
  `IContentProvider` interface — as of this writing only Mail and Bookmarks do
  upstream. Polls does not, so "ask Context Chat about poll results" isn't
  possible against a stock Polls install.
- Create/edit-in-place/delete on indexed files IS expected to work: the PHP app
  syncs those changes to the backend via an internal actions queue for reindexing.

## Outbound email (Apache James)

Per Anirban's request: SMTP with TLS, so Nextcloud can actually send transactional
email (invites, password resets, share notifications) in local dev. This is an
outbound relay only — no MX records, no inbound mail, no mailbox management beyond
the one relay account and the one test recipient below.

`james-conf/` in this repo is Apache James's stock `jpa-3.8.2` config with only two
changes: `domainlist.xml`'s `defaultDomain` (set to `NC_DOMAIN`), and a
`keystore` file for TLS (gitignored — generated below, like the nginx cert). James's
default config already has a `587` listener with `startTLS` + mandatory SMTP AUTH
enabled out of the box — that's the one Nextcloud uses; ports `25`/`465` are
James's untouched defaults and aren't used here.

1. Generate the keystore (skip if `james-conf/keystore` already exists):
   ```sh
   openssl req -x509 -newkey rsa:2048 -nodes -days 365 \
     -keyout /tmp/james-key.pem -out /tmp/james-cert.pem -subj "/CN=nextcloud-aio-james"
   openssl pkcs12 -export -in /tmp/james-cert.pem -inkey /tmp/james-key.pem \
     -name james -out james-conf/keystore -password pass:james72laBalle
   rm -f /tmp/james-key.pem /tmp/james-cert.pem
   ```
   (`james72laBalle` is the secret already referenced for this keystore in
   `james-conf/smtpserver.xml`'s stock config — change both together if you want a
   different one.)
2. Bring up the container (add `--profile james`), then add the mail domain and the
   relay account James will authenticate as (password from `.env`'s
   `JAMES_SMTP_PASSWORD`):
   ```sh
   JAMES_SMTP_PASSWORD=$(grep "^JAMES_SMTP_PASSWORD=" .env | cut -d= -f2-)
   docker compose exec nextcloud-aio-james james-cli AddDomain nextcloud.local
   docker compose exec nextcloud-aio-james james-cli AddUser "nextcloud@nextcloud.local" "$JAMES_SMTP_PASSWORD"
   ```
3. Point Nextcloud at it:
   ```sh
   CT=nextcloud-aio-nextcloud
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpmode --value="smtp"
   docker compose exec -u www-data $CT php occ config:system:set mail_sendmailmode --value="smtp"
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpsecure --value="tls"
   docker compose exec -u www-data $CT php occ config:system:set mail_smtphost --value="nextcloud-aio-james"
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpport --value="587" --type=integer
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpauth --value=true --type=boolean
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpname --value="nextcloud@nextcloud.local"
   docker compose exec -u www-data $CT php occ config:system:set mail_smtppassword --value="$JAMES_SMTP_PASSWORD"
   docker compose exec -u www-data $CT php occ config:system:set mail_domain --value="nextcloud.local"
   docker compose exec -u www-data $CT php occ config:system:set mail_from_address --value="nextcloud"
   ```
   The self-signed keystore above means PHP's mailer will refuse the STARTTLS
   handshake with "certificate verify failed" unless told to accept it — same
   underlying issue as the browser's "not secure" warning, just on the SMTP side
   instead of HTTPS:
   ```sh
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpstreamoptions ssl allow_self_signed --value=true --type=boolean
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpstreamoptions ssl verify_peer --value=false --type=boolean
   docker compose exec -u www-data $CT php occ config:system:set mail_smtpstreamoptions ssl verify_peer_name --value=false --type=boolean
   ```
4. Test with the real "forgot password" flow (needs a recipient mailbox to exist on
   James, and the Nextcloud user to have that email set):
   ```sh
   docker compose exec nextcloud-aio-james james-cli AddUser "admin@nextcloud.local" "some-password"
   docker compose exec -u www-data $CT php occ user:setting admin settings email admin@nextcloud.local
   ```
   Then use the "Forgot password?" link on the login page, or confirm the transport
   works directly with:
   ```sh
   docker compose logs nextcloud-aio-james --tail=20
   ```
   A successful send shows `Successfully spooled mail ... from nextcloud@nextcloud.local`
   followed by `Local delivered mail ... successfully`.

## Document signing (LibreSign)

Digital signatures on PDFs, backed by LibreSign's own certificate authority (CFSSL) —
each signer gets a personal certificate/private key issued by that CA, protected by a
signature password they set themselves under Personal settings.

LibreSign's binaries (JSignPdf, PDFtk, CFSSL) need a JVM plus a few native tools that
aren't in the base image, so they're added via `NEXTCLOUD_ADDITIONAL_APKS` in `.env`:
`ghostscript` (PDF rendering), `openjdk17-jre-headless` (bare `openjdk` isn't a valid
Alpine package — use a versioned one), and `poppler-utils` (`pdfinfo`/`pdfsig`, used
for signature validation and page dimension detection). These install fresh on every
container start since they live in the container's root filesystem, not a persisted
volume — no rebuild needed, just restart `nextcloud-aio-nextcloud` after editing
`.env`.

1. Install the app and download its signing binaries (needs the packages above
   already present — restart the container first if you just added them):
   ```sh
   CT=nextcloud-aio-nextcloud
   docker compose exec $CT php occ app:install libresign
   docker compose exec $CT php occ libresign:install --all --architecture=x86_64
   docker compose exec $CT php occ config:app:set libresign certificate_engine --value=cfssl
   ```
2. Generate the root certificate authority. This identity gets embedded in every
   document signed from here on — regenerating it later invalidates certificates
   already issued to users, so use real org details, not placeholders, before any
   non-throwaway use:
   ```sh
   docker compose exec $CT php occ libresign:configure:cfssl \
     --cn="<team/org name>" -o="<organization>" -c="<country code, e.g. IN>"
   ```
3. Verify everything resolved correctly:
   ```sh
   docker compose exec $CT php occ libresign:configure:check
   ```
   All rows should show `success` — if `poppler` shows `info`/not-working right after
   adding the apk package, the check result was cached from before the restart; just
   re-run it.
4. Each user who wants to sign sets their own signature password once, under Personal
   settings → LibreSign, which issues them a personal certificate from the root CA.
   From there, requesting/completing a signature works from a file's context menu in
   the Files app.

## User limit enforcement (Nextcloud AIO Tools)

Custom Nextcloud PHP app (not from the app store — lives in this repo at
`nextcloud-custom-apps/nc_aio_tools`, bind-mounted into both
`nextcloud-aio-nextcloud` and `nextcloud-aio-apache` under
`custom_apps/nc_aio_tools`) that caps the total number of user accounts at
the `NC_USER_LIMIT` value in `.env`.

- Listens on `OCP\User\Events\BeforeUserCreatedEvent` and throws a
  `HintException` once the account count would reach the limit. That event
  fires from `IUserManager::createUser()`, the single code path shared by the
  Settings → Users web UI, `occ user:add`, and the provisioning API — so all
  three are blocked the same way, and the web UI surfaces the exception's hint
  text as an error toast.
- Shows the number of remaining free user slots in the bottom-left corner of
  Settings → Users (or "Unlimited users" if `NC_USER_LIMIT` is blank/unset).
- The bind mount only shadows the `nc_aio_tools` subdirectory of
  `custom_apps`, not the whole directory, so it doesn't hide the
  `integration_google`/`integration_onedrive` apps already installed there.

Enable it once per instance (the bind mount alone doesn't register the app
with Nextcloud):
```sh
docker compose exec -u www-data nextcloud-aio-nextcloud php occ app:enable nc_aio_tools
```

Set the limit in `.env` and restart the `nextcloud-aio-nextcloud` container to
pick it up (it's read live via `getenv()` on every check, not cached):
```sh
# .env
NC_USER_LIMIT=25
```
Leave it blank to disable enforcement entirely — the free-slot badge then
reads "Unlimited users".

## Object storage (S3) — where it does and doesn't apply

Everything in this stack currently stores files on local disk (named Docker
volumes). Three separate places came up in review; they are not three separate
decisions:

- **Nextcloud primary storage** (`NEXTCLOUD_DATADIR` → `/mnt/ncdata`). The only
  real decision. Nextcloud supports S3 as primary object storage, but AIO exposes
  no env var for it — it is an `objectstore` block written into `config.php`, and
  it is chosen at *install* time. Switching an install that already holds files is
  a data migration, not a config change. Placeholders for the values it needs are
  in `.env.example` under **Optional: S3 primary object storage**, commented out
  and unused until someone provides a bucket (or a MinIO container is added here).
- **Talk recordings** (`nextcloud_aio_talk_recording:/tmp`). Not a storage choice.
  The recorder writes an interim file to that volume, then uploads the finished
  recording into Nextcloud through the normal API — so recordings land wherever
  Nextcloud files land. Point Nextcloud at S3 and recordings follow automatically.
- **Whiteboard backup** (`BACKUP_DIR=/tmp`). A crash-recovery dump of in-progress
  boards, not user-visible file storage. Upstream `nextcloud/whiteboard` supports a
  filesystem path only; there is no S3 option to switch on.

Note this is unrelated to AIO's own "S3 support", which means AIO's bundled MinIO
container for *backups*, not primary storage on an external endpoint.

## Known limitations (accepted for this stage)

- No backup solution configured (AIO has an optional Backup container; not enabled
  here).
- Self-signed cert generated manually above — swap for a real one (e.g. Let's
  Encrypt) outside local dev.
- All state lives in named Docker volumes (`nextcloud_aio_*`) with no redundancy —
  losing them loses everything.
- `NC_DOMAIN` being a fake/local-only hostname (not real DNS) means every container
  that needs to resolve it has to be told about it explicitly. The `talk` service
  already has an `extra_hosts` entry for this (its WebRTC/TURN server otherwise
  crash-loops with "Invalid TURN address") — if a real domain with real DNS
  replaces `NC_DOMAIN` later, that `extra_hosts` line becomes unnecessary but
  harmless.
