# Setup steps

This is Nextcloud AIO ("manual install" method) with all optional features enabled:
Talk, Talk Recording, Collabora, OnlyOffice, EuroOffice, ClamAV, Imaginary,
Fulltextsearch, Whiteboard. No external storage backend (S3/Ceph) is involved —
AIO's own containers handle everything. See the header comment in
`docker-compose.yml` for why (AIO's S3 support is its own bundled Minio container,
not a way to plug in an external S3 endpoint).

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
   local dev):
   ```sh
   sudo mkdir -p /etc/nginx/certs
   sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
     -keyout /etc/nginx/certs/nextcloud.local.key \
     -out /etc/nginx/certs/nextcloud.local.crt \
     -subj "/CN=nextcloud.local"
   ```
   Adjust the filenames/CN if you chose a different `NC_DOMAIN`.

3. Point the existing host nginx at it using `nginx-nextcloud.conf.sample` as a
   starting point (update `server_name` and the cert paths to match), reload nginx.
   Read the comment at the top of that file — HTTPS here isn't optional, AIO assumes
   it unconditionally.

4. Bring the stack up with every optional profile enabled:
   ```sh
   docker compose --profile collabora --profile talk --profile talk-recording \
     --profile clamav --profile onlyoffice --profile eurooffice --profile imaginary \
     --profile fulltextsearch --profile whiteboard up -d
   ```
   First boot pulls a lot of images (Collabora, Elasticsearch for Fulltextsearch,
   OnlyOffice, EuroOffice, ClamAV's virus DB, etc.) — expect this to take a while
   and use several GB of disk/RAM. Always pass the same `--profile` flags on
   subsequent `up`/`down` calls, or Compose will stop containers it thinks you no
   longer want.

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
  grep -iE "talk|richdocuments|onlyoffice|files_fulltextsearch|files_antivirus"
```
(`-k` skips cert validation for the self-signed cert — drop it once you're on a
real one.)

If step 2 or 3 fails, check `docker compose logs nextcloud-aio-nextcloud` and
`docker compose logs nextcloud-aio-apache`. If step 4 shows an app missing despite
its container being healthy, check that its `*_ENABLED` var in `.env` is `"yes"`
(with quotes) and that you passed its `--profile` flag on `up`.

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
