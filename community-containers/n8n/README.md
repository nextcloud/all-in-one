# n8n Workflow Automation

Dieser Container integriert [n8n](https://n8n.io/) in deine Nextcloud AIO-Instanz. n8n ist ein leistungsstarkes Tool zur Workflow-Automatisierung, mit dem du verschiedene Dienste und Anwendungen miteinander verbinden kannst.

## 📦 Installation

1.  Öffne die Nextcloud AIO-Oberfläche (`https://deine-domain:8080`).
2.  Gehe zum Bereich **Community Container**.
3.  Suche den Container **n8n Workflow Automation** und aktiviere ihn.
4.  Klicke auf **"Save changes"** und starte den Container über den **"Start and update containers"**-Button [citation:11].

## 🔧 Konfiguration

### Erste Einrichtung

*   Der n8n-Editor ist unter der Subdomain `n8n.deine-domain.de` erreichbar. Du musst die Subdomain in deinem Reverse-Proxy (z.B. NPMplus) entsprechend einrichten und auf den internen Port verweisen.
*   Die Webhook-URL für deine Workflows lautet standardmäßig `https://deine-domain.de/n8n-webhook`.

### Umgebungsvariablen (in der AIO-Oberfläche)

Nach der Installation kannst du in der AIO-Oberfläche unter "Community Container" → "n8n" folgende Einstellungen vornehmen:

*   `N8N_ENCRYPTION_KEY`: Ein sicherer Schlüssel zur Verschlüsselung von Workflow-Daten. Dieser wird bei der Installation automatisch generiert [citation:5].
*   `WEBHOOK_URL`: Die Basis-URL für deine Webhooks. Standardmäßig ist sie auf `https://%NC_DOMAIN%/n8n-webhook` gesetzt, was von AIO automatisch mit deiner Domain gefüllt wird.

## 💾 Backup

Die persistenten Daten von n8n werden im Docker-Volume `nextcloud_aio_n8n` gespeichert. Dieses Volume wird automatisch in das AIO-Backup-System integriert [citation:5][citation:11].

## 🔍 Wichtige Hinweise

*   **Subdomain**: Für den Betrieb wird eine Subdomain (z.B. `n8n.deine-domain.de`) vorausgesetzt. Richte diese in deinem Reverse-Proxy (z.B. NPMplus) ein [citation:2].
*   **Interne Datenbank**: Der Container verwendet eine eigene SQLite-Datenbank und verbindet sich nicht mit der Nextcloud-Datenbank.
*   **Dokumentation**: Die offizielle n8n-Dokumentation findest du unter [https://docs.n8n.io/](https://docs.n8n.io/).

## 📚 Quellen & Weiterführende Links

*   [n8n Offizielle Webseite](https://n8n.io/)
*   [n8n GitHub Repository](https://github.com/n8n-io/n8n)
*   [Nextcloud AIO Community Container Dokumentation](https://github.com/nextcloud/all-in-one/tree/main/community-containers)
*   [Anleitung: Community Container in AIO verwenden](https://nextcloud.com/de/blog/how-to-use-nextcloud-aio-using-community-containers/) [citation:11]
