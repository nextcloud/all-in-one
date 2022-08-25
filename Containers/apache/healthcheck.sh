#!/bin/bash

curl -skfI localhost:8000 || exit 1
if [ "$APACHE_PORT" != '443' ]; then
    curl -skfI localhost:"$APACHE_PORT" || exit 1
else
    curl -skfI https://"$NC_DOMAIN":"$APACHE_PORT" || exit 1
fi
