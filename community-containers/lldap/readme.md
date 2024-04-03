## Light LDAP server
This container bundles LLDAP server and auto-configures your nextcloud instance for you.

### Notes
- In order to access your Lldap web interface outside the local network, you have to set up your own reverse proxy. You can set up a reverse proxy following [these instructions](https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md) OR use the [Caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container that will automatically configure `ldap.$NC_DOMAIN` to redirect to your Lldap. You need to point the reverse proxy at port 17170 of this server.
- After adding and starting the container, you can log in to the lldap web interface by using the password that you can retrieve via `sudo docker inspect nextcloud-aio-lldap | grep LLDAP_JWT_SECRET`.
- Also, you need to run the following script one time in order to activate the ldap config in nextcloud so that Nextcloud uses lldap as user backend.<br>
    First, you need to retrieve the lldap admin password via `sudo docker inspect nextcloud-aio-lldap | grep LLDAP_LDAP_USER_PASS`. This will be used later on which you need to type in or copy- and paste.
    ```bash
    # Now go into the container
    sudo docker exec --user www-data -it nextcloud-aio-nextcloud bash
    ```
    Now inside the container:
    ```bash
    occ() {
        sudo docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ "$@"
    }

    BASE_DN="dc=${NC_DOMAIN//./,dc=}"

    echo "Nextcloud instance found"
    echo "Domain: $NC_DOMAIN"
    echo "Base DN: $BASE_DN"

    read -sp "Type the password for the LDAP admin user: " PASSWORD

    echo "Setting up LDAP"

    occ ldap:create-empty-config

    occ ldap:set-config s01 ldapAgentName                "uid=ro_admin,ou=people,$BASE_DN"
    occ ldap:set-config s01 ldapAgentPassword            "$PASSWORD"
    occ ldap:set-config s01 ldapBase                     "$BASE_DN"
    occ ldap:set-config s01 ldapBaseGroups               "$BASE_DN"
    occ ldap:set-config s01 ldapBaseUsers                "$BASE_DN"
    occ ldap:set-config s01 ldapCacheTTL                 600
    occ ldap:set-config s01 ldapConfigurationActive      1
    occ ldap:set-config s01 ldapEmailAttribute           "mail"
    occ ldap:set-config s01 ldapExperiencedAdmin         0
    occ ldap:set-config s01 ldapGidNumber                "gidNumber"
    occ ldap:set-config s01 ldapGroupDisplayName         "cn"
    occ ldap:set-config s01 ldapGroupFilter              "(&(objectclass=groupOfUniqueNames))"
    occ ldap:set-config s01 ldapGroupFilterGroups        ""
    occ ldap:set-config s01 ldapGroupFilterMode          0
    occ ldap:set-config s01 ldapGroupFilterObjectclass   "groupOfUniqueNames"
    occ ldap:set-config s01 ldapGroupMemberAssocAttr     "uniqueMember"
    occ ldap:set-config s01 ldapHost                     "nextcloud-aio-lldap"
    occ ldap:set-config s01 ldapLoginFilterAttributes    "uid"
    occ ldap:set-config s01 ldapLoginFilterEmail         0
    occ ldap:set-config s01 ldapLoginFilterUsername      1
    occ ldap:set-config s01 ldapMatchingRuleInChainState "unknown"
    occ ldap:set-config s01 ldapNestedGroups             0
    occ ldap:set-config s01 ldapPagingSize               500
    occ ldap:set-config s01 ldapPort                     3890
    occ ldap:set-config s01 ldapTLS                      0
    occ ldap:set-config s01 ldapUserAvatarRule           "default"
    occ ldap:set-config s01 ldapUserDisplayName          "displayname"
    occ ldap:set-config s01 ldapUserFilter               "(&(objectClass=person)(uid=%uid))"
    occ ldap:set-config s01 ldapUserFilterMode           1
    occ ldap:set-config s01 ldapUserFilterObjectclass    "person"
    occ ldap:set-config s01 ldapUuidGroupAttribute       "auto"
    occ ldap:set-config s01 ldapUuidUserAttribute        "auto"
    occ ldap:set-config s01 turnOnPasswordChange         0

    # Exit the container shell
    exit
    ```
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository
https://github.com/lldap/lldap

### Maintainer
https://github.com/docjyj
