#!/bin/sh

occ() {
  sudo docker exec -u www-data nextcloud-aio-nextcloud php /var/www/html/occ "$@"
}

DOMAIN=$(occ config:system:get overwritehost)
BASE_DN="dc=${DOMAIN//./,dc=}"

echo "Nextcloud instance found"
echo "Domain: $DOMAIN"
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