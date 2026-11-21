## OpenProject
This container bundles [OpenProject](https://www.openproject.org/), an open source project management software with work packages, Gantt charts, boards, time tracking and more. It ships with its own PostgreSQL database container.

AIO also installs the [OpenProject integration](https://apps.nextcloud.com/apps/integration_openproject) app in Nextcloud for you. Connecting both is a one-time step that is described [below](#connecting-openproject-with-nextcloud).

### Requirements
- **Reverse proxy:** OpenProject needs its own subdomain, which must be `openproject.$NC_DOMAIN`. So if Nextcloud uses `your-domain.com`, OpenProject uses `openproject.your-domain.com`. The easiest way is the [Caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy), which sets this up automatically. You only need to make sure that the DNS record for `openproject.your-domain.com` points at your server. If you use your own reverse proxy (see [reverse-proxy.md](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md)), see [below](#using-your-own-reverse-proxy).
- **Ports:** Make sure that no other service is using port `8060/tcp` and `1234/tcp` on your host, as otherwise the container will fail to start. You can check this with `sudo netstat -tulpn | grep -E ':(8060|1234)\b'`.
- **Reachability:** For the integration with Nextcloud, the Nextcloud container must be able to reach `https://openproject.$NC_DOMAIN` and the OpenProject container must be able to reach `https://$NC_DOMAIN`.

### Setup
1. Enable the OpenProject community container in the AIO interface and start the containers. See [how to add community containers](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers).
2. Wait until OpenProject is ready. The first start takes a few minutes since the database gets initialized. You can follow the progress with `sudo docker logs -f nextcloud-aio-openproject`.
3. Open `https://openproject.your-domain.com` and log in with the username `admin` and the password that you can see next to the container in the AIO interface. You will be asked to change the password right away. Afterwards, the password shown in the AIO interface is not valid anymore.
4. In order for OpenProject to be able to send emails, configure SMTP in OpenProject at **Administration → Emails and notifications**.
5. Connect OpenProject with Nextcloud as described in the next section.

### Connecting OpenProject with Nextcloud
The integration allows to link Nextcloud files to work packages, shows OpenProject notifications in the Nextcloud dashboard and allows to search work packages from Nextcloud. See the [OpenProject documentation](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) for all features.

To set it up, we recommend the [setup script](https://github.com/nextcloud/integration_openproject/blob/master/docs/setting_up_via_shell_script.md) that is provided by the integration:

1. In OpenProject, click on your avatar, open **Account settings → Access tokens** and create a new **API** token.
2. In Nextcloud, open **Personal settings → Security** and create a new app password for your admin user.
3. Run the following command on your server. Replace `your-domain.com` with your Nextcloud domain and fill in your Nextcloud admin username, the app password and the API token:
    ```bash
    sudo docker run --rm -it \
        -e NEXTCLOUD_HOST="https://your-domain.com" \
        -e OPENPROJECT_HOST="https://openproject.your-domain.com" \
        -e NC_ADMIN_USERNAME="admin" \
        -e NC_ADMIN_PASSWORD="<nextcloud-app-password>" \
        -e OP_ADMIN_USERNAME="apikey" \
        -e OP_ADMIN_PASSWORD="<openproject-api-token>" \
        -e OPENPROJECT_STORAGE_NAME="Nextcloud" \
        -e SETUP_PROJECT_FOLDER="false" \
        alpine sh -c 'apk add --no-cache bash curl jq > /dev/null && curl -fsSL https://raw.githubusercontent.com/nextcloud/integration_openproject/v3.3.0/integration_setup.sh -o /setup.sh && bash /setup.sh'
    ```
4. When the script reports that the setup is complete, delete the app password in Nextcloud and the API token in OpenProject again, since they are not needed anymore.

Afterwards, every user can connect their own account in Nextcloud at **Personal settings → OpenProject**.

If you want to use project folders (automatically managed folders for each OpenProject project), see the [OpenProject documentation](https://www.openproject.org/docs/system-admin-guide/integrations/nextcloud/) on how to set them up.

> [!Note]
> If you do not have CLI access to the server, you can run docker commands via a web session by using this community container: https://github.com/nextcloud/all-in-one/tree/main/community-containers/container-management

### Using your own reverse proxy
Point `openproject.$NC_DOMAIN` to port `8060` of this server. Additionally, the real-time collaboration server for documents listens on port `1234` and must be reachable via the `/hocuspocus` path, with the path prefix stripped and websocket support enabled. Serve everything via https and send the `X-Forwarded-Proto` header.

Example for Caddy running on the same host:
```
https://openproject.your-domain.com:443 {
    handle_path /hocuspocus* {
        reverse_proxy localhost:1234
    }
    handle {
        reverse_proxy localhost:8060
    }
}
```

### Backup
The data of OpenProject and its database is automatically included in AIOs backup solution.

### Removing the container
After removing the container, you might also want to remove the integration app from Nextcloud:
```bash
sudo docker exec --user www-data nextcloud-aio-nextcloud php occ app:remove integration_openproject
```

### Repository
https://github.com/opf/openproject

### Maintainer
https://github.com/szaimen
