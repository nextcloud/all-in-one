## Light LDAP server
This container bundles LLDAP server and auto-configures your Nextcloud instance for you.

### Notes
- In order to access your LLDAP web interface outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `ldap.$NC_DOMAIN` to redirect to your Lldap. You need to point the reverse proxy at port 17170 of this server.
- After adding and starting the container, you can log in to the lldap web interface by using the username `admin` and the secret that you can see next to the container in the AIO interface.
- To configure Nextcloud, you can use the generic configuration proposed below.
- For advanced configurations, see how to configure a client with lldap https://github.com/lldap/lldap#client-configuration
- Also, see how Nextcloud's LDAP application works https://docs.nextcloud.com/server/latest/admin_manual/configuration_user/user_auth_ldap.html
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Generic Nextcloud LDAP config
Functionality with this configuration:
- User and group management.
- Login via username (or email) and password.
- Profile picture sync.
- Synchronization of administrator accounts (via the lldap_admin group).

> For simplicity, this configuration is done via the command line (don't worry, it's very simple).

First, you need to retrieve the LLDAP admin password, this will be used later on. Which you need to type in or copy and paste:
```bash
sudo docker inspect nextcloud-aio-lldap | grep LLDAP_LDAP_USER_PASS
```

Now go into the Nextcloud container:<br>
**Please note:** If you do not have CLI access to the server, you can now run docker commands via a web session by using this community container: https://github.com/nextcloud/all-in-one/tree/main/community-containers/container-management. This script below can be run from inside the container-management container via `bash /lldap.sh`.
```bash
sudo docker exec --user www-data -it nextcloud-aio-nextcloud bash
```
Now inside the container:
```bash
# Get Base
BASE_DN="dc=${NC_DOMAIN//./,dc=}"

# Create a new empty ldap config
CONF_NAME=$(php /var/www/html/occ ldap:create-empty-config -p)

# Check that the base DN matches your domain and retrieve your configuration name
echo "Base DN: '$BASE_DN', Config name: '$CONF_NAME'"

# Set the ldap password
php /var/www/html/occ ldap:set-config $CONF_NAME ldapAgentPassword "<your-password>"

# Set the ldap config: Host and connection
php /var/www/html/occ ldap:set-config $CONF_NAME ldapAdminGroup       lldap_admin
php /var/www/html/occ ldap:set-config $CONF_NAME ldapAgentName        "cn=admin,ou=people,$BASE_DN"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapBase             "$BASE_DN"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapHost             "ldap://nextcloud-aio-lldap"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapPort             3890
php /var/www/html/occ ldap:set-config $CONF_NAME ldapTLS              0
php /var/www/html/occ ldap:set-config $CONF_NAME turnOnPasswordChange 0

# Set the ldap config: Users
php /var/www/html/occ ldap:set-config $CONF_NAME ldapBaseUsers             "ou=people,$BASE_DN"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapEmailAttribute        mail
php /var/www/html/occ ldap:set-config $CONF_NAME ldapGidNumber             gidNumber
php /var/www/html/occ ldap:set-config $CONF_NAME ldapLoginFilter           "(&(|(objectclass=person))(|(uid=%uid)(|(mailPrimaryAddress=%uid)(mail=%uid))))"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapLoginFilterEmail      1
php /var/www/html/occ ldap:set-config $CONF_NAME ldapLoginFilterUsername   1
php /var/www/html/occ ldap:set-config $CONF_NAME ldapUserAvatarRule        default
php /var/www/html/occ ldap:set-config $CONF_NAME ldapUserDisplayName       cn
php /var/www/html/occ ldap:set-config $CONF_NAME ldapUserFilter            "(|(objectclass=person))"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapUserFilterMode        0
php /var/www/html/occ ldap:set-config $CONF_NAME ldapUserFilterObjectclass person

# Set the ldap config: Groups
php /var/www/html/occ ldap:set-config $CONF_NAME ldapBaseGroups                "ou=groups,$BASE_DN"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapGroupDisplayName          cn
php /var/www/html/occ ldap:set-config $CONF_NAME ldapGroupFilter               "(&(|(objectclass=groupOfUniqueNames)))"
php /var/www/html/occ ldap:set-config $CONF_NAME ldapGroupFilterMode           0
php /var/www/html/occ ldap:set-config $CONF_NAME ldapGroupFilterObjectclass    groupOfUniqueNames
php /var/www/html/occ ldap:set-config $CONF_NAME ldapGroupMemberAssocAttr      uniqueMember
php /var/www/html/occ ldap:set-config $CONF_NAME useMemberOfToDetectMembership 1

# Optional : Check the configuration
#php /var/www/html/occ ldap:show-config $CONF_NAME

# Test the ldap config
php /var/www/html/occ ldap:test-config $CONF_NAME

# Enable ldap config
php /var/www/html/occ ldap:set-config $CONF_NAME ldapConfigurationActive 1

# Exit the container shell
exit
```
It's done ! All you have to do is go to the Nextcloud administration interface to see the magic of LDAP.

### Repository
https://github.com/lldap/lldap

### Maintainer
https://github.com/docjyj
