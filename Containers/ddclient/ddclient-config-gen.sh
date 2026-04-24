#!/bin/bash
# Automatically generate /config/ddclient.conf for deSEC dynamic DNS when
# NC_DOMAIN and DESEC_TOKEN are provided and no config file exists yet.
#
# This script is executed by the linuxserver base image from /custom-cont-init.d/
# before ddclient starts, so no manual configuration step is required.

if [[ -n "${NC_DOMAIN}" && -n "${DESEC_TOKEN}" && ! -f /config/ddclient.conf ]]; then
    {
        printf 'daemon=300\nsyslog=yes\nssl=yes\n\n'
        printf 'use=web, web=https://checkipv4.dedyn.io/\n\n'
        printf 'protocol=dyndns2\nserver=update.dedyn.io\n'
        printf 'login=%s\npassword=%s\n%s\n' \
            "${NC_DOMAIN}" "${DESEC_TOKEN}" "${NC_DOMAIN}"
    } > /config/ddclient.conf
    echo "deSEC ddclient config auto-generated for domain ${NC_DOMAIN}"
fi
