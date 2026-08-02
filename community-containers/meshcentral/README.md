# MeshCentral (Remote Management)

Dieser Container integriert [MeshCentral](https://meshcentral.com/) in deine Nextcloud AIO-Instanz. MeshCentral ist eine Open-Source-Remote-Management-Lösung, mit der du Computer über das Internet verwalten und fernsteuern kannst.

## 📦 Installation

1.  Öffne die Nextcloud AIO-Oberfläche (`https://deine-domain:8080`).
2.  Gehe zum Bereich **Community Container**.
3.  Suche den Container **MeshCentral (Remote Management)** und aktiviere ihn.
4.  Klicke auf **"Save changes"** und starte den Container über den **"Start and update containers"**-Button.

Nach der Installation wird die MeshCentral-Weboberfläche automatisch unter `meshcentral.DEINE-DOMAIN.de` erreichbar sein – **sofern du die Subdomain in deinem Reverse-Proxy (z.B. NPMplus) korrekt eingerichtet hast**.

---

## 🔧 Erste Einrichtung (Reverse-Proxy)

Damit die MeshCentral-Weboberfläche über HTTPS erreichbar ist, musst du in deinem Reverse-Proxy (z.B. NPMplus) einen Eintrag für die Subdomain `meshcentral.deine-domain.de` erstellen.

**Beispiel für NPMplus (empfohlene Einstellungen):**

| Feld | Wert | Erklärung |
|------|------|-----------|
| **Domain** | `meshcentral.deine-domain.de` | Die Subdomain für MeshCentral |
| **Forward Hostname/IP** | `nextcloud-aio-meshcentral` | Der Containername im AIO-Netzwerk |
| **Forward Port** | `4430` | Der interne Port von MeshCentral |
| **Scheme** | `https` | MeshCentral läuft intern mit HTTPS |
| **Force SSL** | ✅ aktivieren | Erzwingt HTTPS – **zwingend erforderlich** |
| **Websocket Support** | ✅ aktivieren | **Erforderlich** für die Agenten-Kommunikation |

**SSL-Zertifikat:** NPMplus besorgt automatisch ein gültiges Let's Encrypt-Zertifikat für die Subdomain – du musst nichts weiter tun.

---

## 🔧 Automatische Konfiguration (nichts eingeben!)

Die folgenden Einstellungen werden von AIO **automatisch** übernommen – du musst **keine** manuellen Eingaben machen:

| Variable | Wert | Erklärung |
|----------|------|-----------|
| `MC_HOSTNAME` | `meshcentral.DEINE-DOMAIN.de` | Der Hostname für MeshCentral |
| `MC_SESSION_KEY` | Automatisch generiert | Sicherer Schlüssel für Sitzungen |
| `MC_PORT` | `4430` | Interner Port, auf dem MeshCentral lauscht |

---

## 💾 Backup

Die persistenten Daten von MeshCentral werden in den Docker-Volumes `nextcloud_aio_meshcentral_data` und `nextcloud_aio_meshcentral_files` gespeichert. Diese Volumes werden automatisch in das AIO-Backup-System integriert.

---

## 🔍 Wichtige Hinweise

- **Subdomain erforderlich**: Die Weboberfläche ist nur über `meshcentral.DEINE-DOMAIN.de` erreichbar – richte sie in deinem Reverse-Proxy ein.
- **HTTPS Pflicht**: Der Container läuft intern mit HTTPS, und der Reverse-Proxy (bei mir NPMplus) leitet die Verbindung weiter. `Scheme` muss auf `https` gesetzt sein.
- **Websocket Support**: **Muss aktiviert sein**, da die MeshCentral-Agenten für die Echtzeit-Kommunikation WebSockets verwenden.
- **Agenten**: Die Agenten (Clients) verbinden sich automatisch über die gleiche Domain. Der von der Weboberfläche bereitgestellte Installationsbefehl funktioniert ohne manuelle Änderungen.

---

## 🛠️ Über dieses Image

Dieser Community-Container basiert auf einem eigenen Docker-Image, das für die Integration in Nextcloud AIO optimiert wurde.

- **Basis-Image**: [`vegardit/meshcentral:latest`](https://hub.docker.com/r/vegardit/meshcentral) – das offizielle MeshCentral-Image
- **Anpassungen**: Das Image enthält eine vorkonfigurierte `config.json`-Vorlage, die beim Start automatisch die AIO-spezifischen Platzhalter (`%NC_DOMAIN%`, `%MC_SESSION_KEY%`) durch die tatsächlichen Werte ersetzt.
- **Quellcode**: Das Dockerfile und die Konfigurationsvorlage sind auf [GitHub](https://github.com/c0reloop/meshcentral-aio) einsehbar.

**Die Konfigurationsvorlage (`my-config-template.json`):**

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
      "newAccounts": true,
      "cookieIpCheck": true,
      "userNameIsEmail": true,
      "allowLocalLogin": true
    }
  }
}
```

**Warum ein eigenes Image?**  
Die offizielle MeshCentral-Instanz unterstützt keine automatische Konfiguration über Umgebungsvariablen. Dieses Image wurde entwickelt, um die nahtlose Integration in AIO zu ermöglichen, indem es eine Template-Datei verwendet, die beim Start die korrekte `config.json` generiert.

---

## 📚 Quellen & Weiterführende Links

- [MeshCentral Offizielle Webseite](https://meshcentral.com/)
- [MeshCentral GitHub Repository](https://github.com/Ylianst/MeshCentral)
- [Nextcloud AIO Community Container Dokumentation](https://github.com/nextcloud/all-in-one/tree/main/community-containers)

---

## 🧑‍💻 Über diesen Container

Dieser Community-Container wurde entwickelt und eingereicht von:

- **c0reloop** ([GitHub](https://github.com/c0reloop)) – *Remote Management Integration for Nextcloud AIO*

---

*Letzte Aktualisierung: August 2026*
