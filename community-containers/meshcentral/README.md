# MeshCentral (Remote Management)

This container integrates [MeshCentral](https://meshcentral.com/) into your Nextcloud AIO instance. MeshCentral is an open-source remote management solution that allows you to manage and control computers over the internet.

## 📦 Installation

1.  Open the Nextcloud AIO interface (`https://your-domain:8080`).
2.  Go to the **Community Container** section.
3.  Find the container **MeshCentral (Remote Management)** and activate it.
4.  Click **"Save changes"** and start the container using the **"Start and update containers"** button.

After installation, the MeshCentral web interface will automatically be accessible at `meshcentral.YOUR-DOMAIN.com` – **provided you have correctly set up the subdomain in your reverse proxy (e.g., NPMplus)**.

---

## 🔧 Initial Setup (Reverse Proxy)

To make the MeshCentral web interface accessible via HTTPS, you need to create an entry for the subdomain `meshcentral.your-domain.com` in your reverse proxy (e.g., NPMplus).

**Example for NPMplus (recommended settings):**

| Field | Value | Explanation |
|-------|-------|-------------|
| **Domain** | `meshcentral.your-domain.com` | The subdomain for MeshCentral |
| **Forward Hostname/IP** | `nextcloud-aio-meshcentral` | The container name in the AIO network |
| **Forward Port** | `4430` | The internal port of MeshCentral |
| **Scheme** | `https` | MeshCentral runs internally with HTTPS |
| **Force SSL** | ✅ enable | Enforces HTTPS – **mandatory** |
| **Websocket Support** | ✅ enable | **Required** for agent communication |

**SSL Certificate:** NPMplus will automatically obtain a valid Let's Encrypt certificate for the subdomain – you don't need to do anything else.

---

## 🔧 Automatic Configuration (nothing to enter!)

The following settings are **automatically** applied by AIO – you **do not** need to make any manual entries:

| Variable | Value | Explanation |
|----------|------|-------------|
| `MC_HOSTNAME` | `meshcentral.YOUR-DOMAIN.com` | The hostname for MeshCentral |
| `MC_SESSION_KEY` | Automatically generated | Secure key for sessions |
| `MC_PORT` | `4430` | Internal port MeshCentral listens on |

---

## 💾 Backup

The persistent data of MeshCentral is stored in the Docker volumes `nextcloud_aio_meshcentral_data` and `nextcloud_aio_meshcentral_files`. These volumes are automatically integrated into the AIO backup system.

---

## 🔍 Important Notes

- **Subdomain required**: The web interface is only accessible via `meshcentral.YOUR-DOMAIN.com` – set it up in your reverse proxy.
- **HTTPS mandatory**: The container runs internally with HTTPS, and the reverse proxy (NPMplus) forwards the connection. `Scheme` must be set to `https`.
- **Websocket Support**: **Must be enabled**, as MeshCentral agents use WebSockets for real-time communication.
- **Agents**: The agents (clients) automatically connect via the same domain. The installation command provided by the web interface works without manual changes.

---

## 🛠️ About This Image

This community container is based on a custom Docker image optimized for integration into Nextcloud AIO.

- **Base Image**: [`vegardit/meshcentral:latest`](https://hub.docker.com/r/vegardit/meshcentral) – the official MeshCentral image
- **Customizations**: The image contains a preconfigured `config.json` template that automatically replaces AIO-specific placeholders (`%NC_DOMAIN%`, `%MC_SESSION_KEY%`) with actual values at startup.
- **Source Code**: The Dockerfile and configuration template are available on [GitHub](https://github.com/c0reloop/meshcentral-aio).

**The configuration template (`my-config-template.json`):**

```json
{
  "$schema": "https://raw.githubusercontent.com/Ylianst/MeshCentral/master/meshcentral-config-schema.json",
  "settings": {
    "cert": "${MC_HOSTNAME}",
    "port": 4430,
    "aliasPort": 443,
    "redirPort": 8080,
    "sessionKey": "${MC_SESSION_KEY}",
    "wanOnly": true,
    "allowLoginToken": true,
    "allowFraming": false,
    "trustedProxy": ["127.0.0.1"],
    "AgentPolicy": {
      "skipUpgrade": false,
      "webRTC": true,
      "allowNotification": true,
      "audioAlert": false,
      "allowFraming": false,
      "blockAssertions": true
    }
  },
  "domains": {
    "": {
      "title": "MeshCentral - Remote Management",
      "title2": "by ${MC_HOSTNAME}",
      "certUrl": "https://${MC_HOSTNAME}/",
      "sessionKey": "${MC_SESSION_KEY}",
      "cors": false,
      "login": "${MC_HOSTNAME}",
      "newAccounts": false,
      "cookieIpCheck": true,
      "userNameIsEmail": true,
      "allowLocalLogin": true
    }
  }
}
