# n8n Workflow Automation

Dieser Container integriert [n8n](https://n8n.io/) in deine Nextcloud AIO-Instanz. n8n ist ein leistungsstarkes Tool zur Workflow-Automatisierung, mit dem du verschiedene Dienste und Anwendungen miteinander verbinden kannst.

## 📦 Installation

1.  Öffne die Nextcloud AIO-Oberfläche (`https://deine-domain:8080`).
2.  Gehe zum Bereich **Community Container**.
3.  Suche den Container **n8n Workflow Automation** und aktiviere ihn.
4.  Klicke auf **"Save changes"** und starte den Container über den **"Start and update containers"**-Button.

Nach der Installation wird der n8n-Editor automatisch unter `n8n.DEINE-DOMAIN.de` erreichbar sein – **sofern du die Subdomain in deinem Reverse-Proxy (z.B. NPMplus) korrekt eingerichtet hast**.

---

## 🔧 Erste Einrichtung (Reverse-Proxy)

Damit der n8n-Editor über HTTPS erreichbar ist, musst du in deinem Reverse-Proxy (z.B. NPMplus) einen Eintrag für die Subdomain `n8n.deine-domain.de` erstellen.

**Beispiel für NPMplus (empfohlene Einstellungen):**

| Feld | Wert | Erklärung |
|------|------|-----------|
| **Domain** | `n8n.deine-domain.de` | Die Subdomain für n8n |
| **Forward Hostname/IP** | `nextcloud-aio-n8n` | Der Containername im AIO-Netzwerk |
| **Forward Port** | `5678` | Der interne Port von n8n |
| **Scheme** | `http` | n8n läuft intern mit HTTP |
| **Force SSL** | ✅ aktivieren | Erzwingt HTTPS – **zwingend erforderlich** |
| **Websocket Support** | ⚠️ optional | Für Echtzeit-Updates im Editor empfehlenswert, aber nicht zwingend nötig |

**SSL-Zertifikat:** NPMplus besorgt automatisch ein gültiges Let's Encrypt-Zertifikat für die Subdomain – du musst nichts weiter tun.

---

## 🔧 Automatische Konfiguration (nichts eingeben!)

Die folgenden Einstellungen werden von AIO **automatisch** übernommen – du musst **keine** manuellen Eingaben machen:

| Variable | Wert | Erklärung |
|----------|------|-----------|
| `N8N_ENCRYPTION_KEY` | Automatisch generiert | Sicherer Schlüssel für Workflow-Daten |
| `WEBHOOK_URL` | `https://DEINE-DOMAIN.de/n8n-webhook` | Basis-URL für Webhooks |
| `N8N_EDITOR_BASE_URL` | `https://n8n.DEINE-DOMAIN.de/` | Basis-URL für den Editor |

---

## 💾 Backup

Die persistenten Daten von n8n werden im Docker-Volume `nextcloud_aio_n8n` gespeichert. Dieses Volume wird automatisch in das AIO-Backup-System integriert.

---

## 🔍 Wichtige Hinweise

- **Subdomain erforderlich**: Der Editor ist nur über `n8n.DEINE-DOMAIN.de` erreichbar – richte sie in deinem Reverse-Proxy ein.
- **HTTPS Pflicht**: Der Container läuft intern mit HTTP, aber der Reverse-Proxy (NPMplus) stellt HTTPS bereit. Force SSL **muss** aktiviert sein.
- **Interne Datenbank**: n8n verwendet eine eigene SQLite-Datenbank und verbindet sich nicht mit der Nextcloud-Datenbank.
- **Webhooks**: Die Webhook-URL lautet `https://DEINE-DOMAIN.de/n8n-webhook` und wird automatisch von AIO konfiguriert.

---

## 📚 Quellen & Weiterführende Links

- [n8n Offizielle Webseite](https://n8n.io/)
- [n8n GitHub Repository](https://github.com/n8n-io/n8n)
- [Nextcloud AIO Community Container Dokumentation](https://github.com/nextcloud/all-in-one/tree/main/community-containers)

---

## 🧑‍💻 Über diesen Container

Dieser Community-Container wurde entwickelt und eingereicht von:

- **c0reloop** ([GitHub](https://github.com/c0reloop)) – *Workflow Automation Integration for Nextcloud AIO*

Mit der Veröffentlichung dieses Containers wird n8n als erstklassige Automatisierungslösung nahtlos in die Nextcloud AIO-Umgebung integriert. Alle Rechte am Container-Layout und der AIO-Integration liegen beim Autor.

---

*Letzte Aktualisierung: Juli 2026*
