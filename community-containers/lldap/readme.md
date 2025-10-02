## Light LDAP server
This container bundles LLDAP server and auto-configures your Nextcloud instance for you.

### Notes
- In order to access your LLDAP web interface outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `ldap.$NC_DOMAIN` to redirect to your Lldap. You need to point the reverse proxy at port 17170 of this server.
- After adding and starting the container, you can log in to the lldap web interface by using the username `admin` and the secret that you can see next to the container in the AIO interface.
- It automatically configures your Nextcloud instance to use the LLDAP server for user authentication.
- For advanced configurations, see how to configure a client with lldap https://github.com/lldap/lldap#client-configuration
- Also, see how Nextcloud's LDAP application works https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/user_auth_ldap.html
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/lldap/lldap

### Maintainer
https://github.com/docjyj
