## Bahmni Lite
This container bundle sets up [Bahmni Lite](https://www.bahmni.org/), an open-source Electronic Medical Record (EMR) and hospital management system, and auto-configures it for you.

Bahmni Lite includes the following services:
- **OpenMRS** – core EMR application
- **OpenMRS Database** – pre-seeded MySQL database (Bahmni Lite schema)
- **Bahmni Config** – clinic configuration (init container)
- **Bahmni Web** – classic Bahmni EMR frontend (AngularJS)
- **Bahmni Apps Frontend** – new React-based Bahmni frontend
- **Bahmni Lab** – lab results module
- **Implementer Interface** – form/concept builder
- **Reports** + **Reports DB** – reporting service and its database
- **Patient Documents** – document storage and serving
- **Appointments** – appointments module
- **Crater** (PHP + Nginx + DB) – billing/invoicing system
- **Crater Atomfeed** + **Crater Atomfeed DB** – OpenMRS ↔ Crater sync service

### Notes
- You need to configure a reverse proxy in order to use this container bundle, since Bahmni needs a dedicated (sub)domain! For that, you might have a look at https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy or follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md. You need to point the reverse proxy at `nextcloud-aio-bahmni-openmrs:8080` for the core Bahmni/OpenMRS application.
- The core Bahmni EMR is accessible at `/openmrs/` on the OpenMRS container (`nextcloud-aio-bahmni-openmrs`, port `8080`). After starting, visit `http://<your-domain>/openmrs/` and log in with the default credentials: username `admin`, password `Admin123`. **⚠️ Change the default OpenMRS admin password immediately after first login.** The Bahmni database image ships with this well-known default — leaving it in place is a serious security risk. Note: after changing the OpenMRS admin password, you must also update `OPENMRS_ATOMFEED_PASSWORD` in the `nextcloud-aio-bahmni-crater-atomfeed` container to match the new password, otherwise the Crater billing sync will stop working.
- For the full Bahmni UI experience (Bahmni Web, Bahmni Apps Frontend etc.), a reverse proxy must be set up to route the following paths to the correct containers:
  - `/openmrs/` → `nextcloud-aio-bahmni-openmrs:8080`
  - `/bahmni/` → `nextcloud-aio-bahmni-web:80`
  - `/bahmni-new/` → `nextcloud-aio-bahmni-apps-frontend:80`
  - `/bahmni-lab/` → `nextcloud-aio-bahmni-lab:80`
  - `/implementer-interface/` → `nextcloud-aio-bahmni-implementer-interface:80`
  - `/document_images/`, `/uploaded_results/`, `/uploaded-files/` → `nextcloud-aio-bahmni-patient-documents:80`
  - `/appointments/` → `nextcloud-aio-bahmni-appointments:80`
  - `/reports/` → `nextcloud-aio-bahmni-reports:8080`
- The Crater billing system can be reached at `nextcloud-aio-bahmni-crater-nginx:80`. The Crater admin email is `admin@bahmni.org` and the password is shown next to the container in the AIO interface.
- All Bahmni data (patient images, documents, clinical forms, databases) will be automatically included in AIOs backup solution!
- This container bundle requires significant system resources. A minimum of **4 GB RAM** and **2 CPU cores** is recommended; **8 GB RAM** is preferred for production use.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack.

### Repository
https://github.com/Bahmni/bahmni-docker

### Maintainer
https://github.com/Bahmni
