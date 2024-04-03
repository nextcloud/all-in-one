## Light LDAP server
This container bundles LLDAP server and auto-configures your nextcloud instance for you.

### Notes
- In order to access your LLDAP web interface outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `ldap.$NC_DOMAIN` to redirect to your Lldap. You need to point the reverse proxy at port 17170 of this server.
- After adding and starting the container, you can log in to the lldap web interface by using the password that you can retrieve via `sudo docker inspect nextcloud-aio-lldap | grep LLDAP_JWT_SECRET`.
- Also, you need to run the following script one time in order to activate the ldap config in nextcloud so that Nextcloud uses lldap as user backend. You can see a [nextcloud example configuration provide by LLDAP](https://github.com/lldap/lldap/blob/main/example_configs/nextcloud.md)<br>
    First, you need to retrieve the LLDAP admin password via `sudo docker inspect nextcloud-aio-lldap | grep LLDAP_LDAP_USER_PASS`. This will be used later on which you need to type in or copy and paste.
    ```bash
    # Now go into the container
    sudo docker exec --user www-data -it nextcloud-aio-nextcloud bash
    ```
    Now inside the container:
    ```bash
    # Get Base
    BASE_DN="dc=${NC_DOMAIN//./,dc=}"
    
    # Create a new empty ldap config
    CONF_NAME=$(php /var/www/html/occ ldap:create-empty-config -p)
  
    # Set the ldap password
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapAgentPassword "<your-password>"

    # Set the ldap config
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapAgentName                "uid=ro_admin,ou=people,$BASE_DN"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapBase                     "$BASE_DN"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapBaseGroups               "$BASE_DN"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapBaseUsers                "$BASE_DN"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapCacheTTL                 600
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapConfigurationActive      1
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapEmailAttribute           "mail"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapExperiencedAdmin         0
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGidNumber                "gidNumber"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGroupDisplayName         "cn"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGroupFilter              "(&(objectclass=groupOfUniqueNames))"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGroupFilterGroups        ""
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGroupFilterMode          0
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGroupFilterObjectclass   "groupOfUniqueNames"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapGroupMemberAssocAttr     "uniqueMember"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapHost                     "nextcloud-aio-lldap"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapLoginFilterAttributes    "uid"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapLoginFilterEmail         0
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapLoginFilterUsername      1
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapMatchingRuleInChainState "unknown"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapNestedGroups             0
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapPagingSize               500
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapPort                     3890
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapTLS                      0
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUserAvatarRule           "default"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUserDisplayName          "displayname"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUserFilter               "(&(objectClass=person)(uid=%uid))"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUserFilterMode           1
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUserFilterObjectclass    "person"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUuidGroupAttribute       "auto"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" ldapUuidUserAttribute        "auto"
    php /var/www/html/occ ldap:set-config "$CONF_NAME" turnOnPasswordChange         0

    # Test the ldap config
    php /var/www/html/occ ldap:test-config "$NAME"
  
    # Exit the container shell
    exit
    ```
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/lldap/lldap

### Maintainer
https://github.com/docjyj
