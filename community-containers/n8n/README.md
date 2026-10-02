# n8n Workflow Automation

This container integrates [n8n](https://n8n.io/) into your Nextcloud AIO instance. n8n is a powerful workflow automation tool that allows you to connect various services and applications.

## 📦 Installation

1.  Open the Nextcloud AIO interface (`https://your-domain:8080`).
2.  Go to the **Community Container** section.
3.  Find the container **n8n Workflow Automation** and activate it.
4.  Click **"Save changes"** and start the container using the **"Start and update containers"** button.

After installation, the n8n editor will automatically be accessible at `n8n.YOUR-DOMAIN.com` – **provided you have correctly set up the subdomain in your reverse proxy (e.g., NPMplus)**.

---

## 🔧 Initial Setup (Reverse Proxy)

To make the n8n editor accessible via HTTPS, you need to create an entry for the subdomain `n8n.your-domain.com` in your reverse proxy (e.g., NPMplus).

**Example for NPMplus (recommended settings):**

| Field | Value | Explanation |
|-------|-------|-------------|
| **Domain** | `n8n.your-domain.com` | The subdomain for n8n |
| **Forward Hostname/IP** | `nextcloud-aio-n8n` | The container name in the AIO network |
| **Forward Port** | `5678` | The internal port of n8n |
| **Scheme** | `http` | n8n runs internally with HTTP |
| **Force SSL** | ✅ enable | Enforces HTTPS – **mandatory** |
| **Websocket Support** | ⚠️ optional | Recommended for real-time updates in the editor, but not strictly required |

**SSL Certificate:** NPMplus will automatically obtain a valid Let's Encrypt certificate for the subdomain – you don't need to do anything else.

---

## 🔧 Automatic Configuration (nothing to enter!)

The following settings are **automatically** applied by AIO – you **do not** need to make any manual entries:

| Variable | Value | Explanation |
|----------|------|-------------|
| `N8N_ENCRYPTION_KEY` | Automatically generated | Secure key for workflow data |
| `WEBHOOK_URL` | `https://YOUR-DOMAIN.com/n8n-webhook` | Base URL for webhooks |
| `N8N_EDITOR_BASE_URL` | `https://n8n.YOUR-DOMAIN.com/` | Base URL for the editor |

---

## 💾 Backup

The persistent data of n8n is stored in the Docker volume `nextcloud_aio_n8n`. This volume is automatically integrated into the AIO backup system.

---

## 🔍 Important Notes

- **Subdomain required**: The editor is only accessible via `n8n.YOUR-DOMAIN.com` – set it up in your reverse proxy.
- **HTTPS mandatory**: The container runs internally with HTTP, but the reverse proxy (NPMplus) provides HTTPS. Force SSL **must** be enabled.
- **Internal database**: n8n uses its own SQLite database and does not connect to the Nextcloud database.
- **Webhooks**: The webhook URL is `https://YOUR-DOMAIN.com/n8n-webhook` and is automatically configured by AIO.

---

## 📚 Sources & Further Links

- [n8n Official Website](https://n8n.io/)
- [n8n GitHub Repository](https://github.com/n8n-io/n8n)
- [Nextcloud AIO Community Container Documentation](https://github.com/nextcloud/all-in-one/tree/main/community-containers)

---

## 🧑‍💻 About This Container

This community container was developed and submitted by:

- **c0reloop** ([GitHub](https://github.com/c0reloop)) – *Workflow Automation Integration for Nextcloud AIO*
---

*Last updated: July 2026*
