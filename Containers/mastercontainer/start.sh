#!/bin/bash

# Function to show text in green
print_green() {
    local TEXT="$1"
    printf "%b%s%b\n" "\e[0;92m" "$TEXT" "\e[0m"
}

# Function to show text in red
print_red() {
    local TEXT="$1"
    printf "%b%s%b\n" "\e[0;31m" "$TEXT" "\e[0m"
}

# Function to check if number was provided
check_if_number() {
case "${1}" in
    ''|*[!0-9]*) return 1 ;;
    *) return 0 ;;
esac
}

# Check if running as root user
if [ "$EUID" != "0" ]; then
    print_red "Container does not run as root user. This is not supported."
    exit 1
fi

# Check that the CMD is not overwritten nor set
if [ "$*" != "" ]; then
    print_red "Docker run command for AIO is incorrect as a CMD option was given which is not expected."
    exit 1
fi

# Check if socket is available and readable
if ! [ -a "/var/run/docker.sock" ]; then
    print_red "Docker socket is not available. Cannot continue."
    echo "Please make sure to mount the docker socket into /var/run/docker.sock inside the container!"
    echo "If you did this by purpose because you don't want the container to have access to the docker socket, see https://github.com/nextcloud/all-in-one/tree/main/manual-install."
    exit 1
elif ! mountpoint -q "/mnt/docker-aio-config"; then
    print_red "/mnt/docker-aio-config is not a mountpoint. Cannot proceed!"
    echo "Please make sure to mount the nextcloud_aio_mastercontainer docker volume into /mnt/docker-aio-config inside the container!"
    echo "If you are on TrueNas SCALE, see https://github.com/nextcloud/all-in-one#can-i-run-aio-on-truenas-scale"
    exit 1
elif ! sudo -u www-data test -r /var/run/docker.sock; then
    echo "Trying to fix docker.sock permissions internally..."
    DOCKER_GROUP=$(stat -c '%G' /var/run/docker.sock)
    DOCKER_GROUP_ID=$(stat -c '%g' /var/run/docker.sock)
    # Check if a group with the same group name of /var/run/docker.socket already exists in the container
    if grep -q "^$DOCKER_GROUP:" /etc/group; then
        # If yes, add www-data to that group
        echo "Adding internal www-data to group $DOCKER_GROUP"
        usermod -aG "$DOCKER_GROUP" www-data
    else
        # Delete the docker group for cases when the docker socket permissions changed between restarts
        groupdel docker &>/dev/null

        # If the group doesn't exist, create it
        echo "Creating docker group internally with id $DOCKER_GROUP_ID"
        groupadd -g "$DOCKER_GROUP_ID" docker
        usermod -aG docker www-data
    fi
    if ! sudo -u www-data test -r /var/run/docker.sock; then
        print_red "Docker socket is not readable by the www-data user. Cannot continue."
        exit 1
    fi
fi

# Check if api version is supported
if ! sudo -u www-data docker info &>/dev/null; then
    print_red "Cannot connect to the docker socket. Cannot proceed."
    echo "Did you maybe remove group read permissions for the docker socket? AIO needs them in order to access the docker socket."
    echo "If SELinux is enabled on your host, see https://github.com/nextcloud/all-in-one#are-there-known-problems-when-selinux-is-enabled"
    echo "If you are on TrueNas SCALE, see https://github.com/nextcloud/all-in-one#can-i-run-aio-on-truenas-scale"
    exit 1
fi
API_VERSION_FILE="$(find ./ -name DockerActionManager.php | head -1)"
API_VERSION="$(grep -oP 'const string API_VERSION.*\;' "$API_VERSION_FILE" | grep -oP '[0-9]+.[0-9]+' | head -1)"
# shellcheck disable=SC2001
API_VERSION_NUMB="$(echo "$API_VERSION" | sed 's/\.//')"
LOCAL_API_VERSION_NUMB="$(sudo -u www-data docker version | grep -i "api version" | grep -oP '[0-9]+.[0-9]+' | head -1 | sed 's/\.//')"
if [ -n "$LOCAL_API_VERSION_NUMB" ] && [ -n "$API_VERSION_NUMB" ]; then
    if ! [ "$LOCAL_API_VERSION_NUMB" -ge "$API_VERSION_NUMB" ]; then
        print_red "Docker API v$API_VERSION is not supported by your docker engine. Cannot proceed. Please upgrade your docker engine if you want to run Nextcloud AIO!"
        exit 1
    fi
else
    echo "LOCAL_API_VERSION_NUMB or API_VERSION_NUMB are not set correctly. Cannot check if the API version is supported."
    sleep 10
fi

# Check Storage drivers
STORAGE_DRIVER="$(sudo -u www-data docker info | grep "Storage Driver")"
# Check if vfs is used: https://github.com/nextcloud/all-in-one/discussions/1467
if echo "$STORAGE_DRIVER" | grep -q vfs; then
    echo "$STORAGE_DRIVER"
    print_red "Warning: It seems like the storage driver vfs is used. This will lead to problems with disk space and performance and is disrecommended!"
elif echo "$STORAGE_DRIVER" | grep -q fuse-overlayfs; then
    echo "$STORAGE_DRIVER"
    print_red "Warning: It seems like the storage driver fuse-overlayfs is used. Please check if you can switch to overlay2 instead."
fi

# Check if snap install
if sudo -u www-data docker info | grep "Docker Root Dir" | grep "/var/snap/docker/"; then
    print_red "Warning: It looks like your installation uses docker installed via snap."
    print_red "This comes with some limitations and is disrecommended by the docker maintainers."
    print_red "See for example https://github.com/nextcloud/all-in-one/discussions/4890#discussioncomment-10386752"
fi

# Check if startup command was executed correctly
if ! sudo -u www-data docker ps --format "{{.Names}}" | grep -q "^nextcloud-aio-mastercontainer$"; then
    print_red "It seems like you did not give the mastercontainer the correct name? (The 'nextcloud-aio-mastercontainer' container was not found.)
Using a different name is not supported since mastercontainer updates will not work in that case!
If you are on docker swarm and try to run AIO, see https://github.com/nextcloud/all-in-one#can-i-run-this-with-docker-swarm"
    exit 1
elif ! sudo -u www-data docker volume ls --format "{{.Name}}" | grep -q "^nextcloud_aio_mastercontainer$"; then
    print_red "It seems like you did not give the mastercontainer volume the correct name? (The 'nextcloud_aio_mastercontainer' volume was not found.)
Using a different name is not supported since the built-in backup solution will not work in that case!"
    exit 1
elif ! sudo -u www-data docker inspect nextcloud-aio-mastercontainer | grep -q "nextcloud_aio_mastercontainer"; then
    print_red "It seems like you did not attach the 'nextcloud_aio_mastercontainer' volume to the mastercontainer?
This is not supported since the built-in backup solution will not work in that case!"
    exit 1
fi

# Check for other options
if [ -n "$NEXTCLOUD_DATADIR" ]; then
    if [ "$NEXTCLOUD_DATADIR" = "nextcloud_aio_nextcloud_datadir" ]; then
        sleep 1
    elif ! echo "$NEXTCLOUD_DATADIR" | grep -q "^/" || [ "$NEXTCLOUD_DATADIR" = "/" ]; then
        print_red "You've set NEXTCLOUD_DATADIR but not to an allowed value.
The string must start with '/' and must not be equal to '/'. Also allowed is 'nextcloud_aio_nextcloud_datadir'.
It is set to '$NEXTCLOUD_DATADIR'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_MOUNT" ]; then
    if ! echo "$NEXTCLOUD_MOUNT" | grep -q "^/" || [ "$NEXTCLOUD_MOUNT" = "/" ]; then
        print_red "You've set NEXTCLOUD_MOUNT but not to an allowed value.
The string must start with '/' and must not be equal to '/'.
It is set to '$NEXTCLOUD_MOUNT'."
        exit 1
    elif [ "$NEXTCLOUD_MOUNT" = "/mnt/ncdata" ] || echo "$NEXTCLOUD_MOUNT" | grep -q "^/mnt/ncdata/"; then
        print_red "'/mnt/ncdata' and '/mnt/ncdata/' are not allowed as values for NEXTCLOUD_MOUNT."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_DATADIR" ] && [ -n "$NEXTCLOUD_MOUNT" ]; then
    if [ "$NEXTCLOUD_DATADIR" = "$NEXTCLOUD_MOUNT" ]; then
        print_red "NEXTCLOUD_DATADIR and NEXTCLOUD_MOUNT are not allowed to be equal."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_UPLOAD_LIMIT" ]; then
    if ! echo "$NEXTCLOUD_UPLOAD_LIMIT" | grep -q '^[0-9]\+G$'; then
        print_red "You've set NEXTCLOUD_UPLOAD_LIMIT but not to an allowed value.
The string must start with a number and end with 'G'.
It is set to '$NEXTCLOUD_UPLOAD_LIMIT'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_MAX_TIME" ]; then
    if ! echo "$NEXTCLOUD_MAX_TIME" | grep -q '^[0-9]\+$'; then
        print_red "You've set NEXTCLOUD_MAX_TIME but not to an allowed value.
The string must be a number. E.g. '3600'.
It is set to '$NEXTCLOUD_MAX_TIME'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_MEMORY_LIMIT" ]; then
    if ! echo "$NEXTCLOUD_MEMORY_LIMIT" | grep -q '^[0-9]\+M$'; then
        print_red "You've set NEXTCLOUD_MEMORY_LIMIT but not to an allowed value.
The string must start with a number and end with 'M'.
It is set to '$NEXTCLOUD_MEMORY_LIMIT'."
        exit 1
    fi
fi
if [ -n "$APACHE_PORT" ]; then
    if ! check_if_number "$APACHE_PORT"; then
        print_red "You provided an Apache port but did not only use numbers.
It is set to '$APACHE_PORT'."
        exit 1
    elif ! [ "$APACHE_PORT" -le 65535 ] || ! [ "$APACHE_PORT" -ge 1 ]; then
        print_red "The provided Apache port is invalid. It must be between 1 and 65535"
        exit 1
    fi
fi
if [ -n "$APACHE_IP_BINDING" ]; then
    if ! echo "$APACHE_IP_BINDING" | grep -q '^[0-9]\+\.[0-9]\+\.[0-9]\+\.[0-9]\+$\|^[0-9a-f:]\+$\|^@INTERNAL$'; then
        print_red "You provided an ip-address for the apache container's ip-binding but it was not a valid ip-address.
It is set to '$APACHE_IP_BINDING'."
        exit 1
    fi
fi
if [ -n "$APACHE_ADDITIONAL_NETWORK" ]; then
    if ! echo "$APACHE_ADDITIONAL_NETWORK" | grep -q "^[a-zA-Z0-9._-]\+$"; then
        print_red "You've set APACHE_ADDITIONAL_NETWORK but not to an allowed value.
It needs to be a string with letters, numbers, hyphens and underscores.
It is set to '$APACHE_ADDITIONAL_NETWORK'."
        exit 1
    fi
fi
if [ -n "$TALK_PORT" ]; then
    if ! check_if_number "$TALK_PORT"; then
        print_red "You provided an Talk port but did not only use numbers.
It is set to '$TALK_PORT'."
        exit 1
    elif ! [ "$TALK_PORT" -le 65535 ] || ! [ "$TALK_PORT" -ge 1 ]; then
        print_red "The provided Talk port is invalid. It must be between 1 and 65535"
        exit 1
    fi
fi
if [ -n "$APACHE_PORT" ] && [ -n "$TALK_PORT" ]; then
    if [ "$APACHE_PORT" = "$TALK_PORT" ]; then
        print_red "APACHE_PORT and TALK_PORT are not allowed to be equal."
        exit 1
    fi
fi
if [ -n "$WATCHTOWER_DOCKER_SOCKET_PATH" ]; then
    if ! echo "$WATCHTOWER_DOCKER_SOCKET_PATH" | grep -q "^/" || echo "$WATCHTOWER_DOCKER_SOCKET_PATH" | grep -q "/$"; then
        print_red "You've set WATCHTOWER_DOCKER_SOCKET_PATH but not to an allowed value.
The string must start with '/' and must not end with '/'.
It is set to '$WATCHTOWER_DOCKER_SOCKET_PATH'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_TRUSTED_CACERTS_DIR" ]; then
    if ! echo "$NEXTCLOUD_TRUSTED_CACERTS_DIR" | grep -q "^/" || echo "$NEXTCLOUD_TRUSTED_CACERTS_DIR" | grep -q "/$"; then
        print_red "You've set NEXTCLOUD_TRUSTED_CACERTS_DIR but not to an allowed value.
It should be an absolute path to a directory that starts with '/' but not end with '/'.
It is set to '$NEXTCLOUD_TRUSTED_CACERTS_DIR '."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_STARTUP_APPS" ]; then
    if ! echo "$NEXTCLOUD_STARTUP_APPS" | grep -q "^[a-z0-9 _-]\+$"; then
        print_red "You've set NEXTCLOUD_STARTUP_APPS but not to an allowed value.
It needs to be a string. Allowed are small letters a-z, 0-9, spaces, hyphens and '_'.
It is set to '$NEXTCLOUD_STARTUP_APPS'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_ADDITIONAL_APKS" ]; then
    if ! echo "$NEXTCLOUD_ADDITIONAL_APKS" | grep -q "^[a-z0-9 ._-]\+$"; then
        print_red "You've set NEXTCLOUD_ADDITIONAL_APKS but not to an allowed value.
It needs to be a string. Allowed are small letters a-z, digits 0-9, spaces, hyphens, dots and '_'.
It is set to '$NEXTCLOUD_ADDITIONAL_APKS'."
        exit 1
    fi
fi
if [ -n "$NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS" ]; then
    if ! echo "$NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS" | grep -q "^[a-z0-9 ._-]\+$"; then
        print_red "You've set NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS but not to an allowed value.
It needs to be a string. Allowed are small letters a-z, digits 0-9, spaces, hyphens, dots and '_'.
It is set to '$NEXTCLOUD_ADDITIONAL_PHP_EXTENSIONS'."
        exit 1
    fi
fi
if [ -n "$AIO_COMMUNITY_CONTAINERS" ]; then
    read -ra AIO_CCONTAINERS <<< "$AIO_COMMUNITY_CONTAINERS"
    for container in "${AIO_CCONTAINERS[@]}"; do
        if ! [ -d "/var/www/docker-aio/community-containers/$container" ]; then
            print_red "The community container $container was not found!"
            FAIL_CCONTAINERS=1
        fi
    done
    if [ -n "$FAIL_CCONTAINERS" ]; then
        print_red "You've set AIO_COMMUNITY_CONTAINERS but at least one container was not found.
It is set to '$AIO_COMMUNITY_CONTAINERS'."
        exit 1
    fi
fi

# Check if ghcr.io is reachable
# Solves issues like https://github.com/nextcloud/all-in-one/discussions/5268
if ! curl --no-progress-meter https://ghcr.io/v2/ >/dev/null; then
    print_red "Could not reach https://ghcr.io."
    echo "Most likely is something blocking access to it."
    echo "You should be able to fix this by following https://dockerlabs.collabnix.com/intermediate/networking/Configuring_DNS.html"
    echo "Another solution is using https://github.com/nextcloud/all-in-one/tree/main/manual-install"
    exit 1
fi

# Check that no changes have been made to timezone settings since AIO only supports running in Etc/UTC timezone
if [ -n "$TZ" ]; then
    print_red "The environmental variable TZ has been set which is not supported by AIO since it only supports running in the default Etc/UTC timezone!"
    echo "The correct timezone can be set in the AIO interface later on!"
    # Disable exit since it seems to be by default set on unraid and we dont want to break these instances
    # exit 1
fi
if mountpoint -q /etc/localtime; then
    print_red "/etc/localtime has been mounted into the container which is not allowed because AIO only supports running in the default Etc/UTC timezone!"
    echo "The correct timezone can be set in the AIO interface later on!"
    exit 1
fi
if mountpoint -q /etc/timezone; then
    print_red "/etc/timezone has been mounted into the container which is not allowed because AIO only supports running in the default Etc/UTC timezone!"
    echo "The correct timezone can be set in the AIO interface later on!"
    exit 1
fi

# Check if unsupported env are set (but don't exit as it would break many instances)
if [ -n "$APACHE_DISABLE_REWRITE_IP" ]; then
    print_red "The environmental variable APACHE_DISABLE_REWRITE_IP has been set which is not supported by AIO. Please remove it!"
fi
if [ -n "$NEXTCLOUD_TRUSTED_DOMAINS" ]; then
    print_red "The environmental variable NEXTCLOUD_TRUSTED_DOMAINS has been set which is not supported by AIO. Please remove it!"
fi
if [ -n "$TRUSTED_PROXIES" ]; then
    print_red "The environmental variable TRUSTED_PROXIES has been set which is not supported by AIO. Please remove it!"
fi

# Add important folders
mkdir -p /mnt/docker-aio-config/data/
mkdir -p /mnt/docker-aio-config/session/
mkdir -p /mnt/docker-aio-config/caddy/
mkdir -p /mnt/docker-aio-config/certs/ 

# Adjust permissions for all instances
chmod 770 -R /mnt/docker-aio-config
chmod 777 /mnt/docker-aio-config
chown www-data:www-data -R /mnt/docker-aio-config/data/
chown www-data:www-data -R /mnt/docker-aio-config/session/
chown www-data:www-data -R /mnt/docker-aio-config/caddy/
chown root:root -R /mnt/docker-aio-config/certs/

# Don't allow access to the AIO interface from the Nextcloud container
# Probably more cosmetic than anything but at least an attempt
if ! grep -q '# nextcloud-aio-block' /etc/apache2/httpd.conf; then
    cat << APACHE_CONF >> /etc/apache2/httpd.conf
# nextcloud-aio-block-start
<Location />
order allow,deny
deny from nextcloud-aio-nextcloud.nextcloud-aio
allow from all
</Location>
# nextcloud-aio-block-end
APACHE_CONF
fi

# Adjust certs
GENERATED_CERTS="/mnt/docker-aio-config/certs"
TMP_CERTS="/etc/apache2/certs"
mkdir -p "$GENERATED_CERTS"
cd "$GENERATED_CERTS" || exit 1
if ! [ -f ./ssl.crt ] && ! [ -f ./ssl.key ]; then
    openssl req -new -newkey rsa:4096 -days 3650 -nodes -x509 -subj "/C=DE/ST=BE/L=Local/O=Dev/CN=nextcloud.local" -keyout ./ssl.key -out ./ssl.crt
fi
if [ -f ./ssl.crt ] && [ -f ./ssl.key ]; then
    cd "$TMP_CERTS" || exit 1
    rm ./ssl.crt
    rm ./ssl.key
    cp "$GENERATED_CERTS/ssl.crt" ./
    cp "$GENERATED_CERTS/ssl.key" ./
fi

print_green "Initial startup of Nextcloud All-in-One complete!
You should be able to open the Nextcloud AIO Interface now on port 8080 of this server!
E.g. https://internal.ip.of.this.server:8080
⚠️ Important: do always use an ip-address if you access this port and not a domain as HSTS might block access to it later!

If your server has port 80 and 8443 open and you point a domain to your server, you can get a valid certificate automatically by opening the Nextcloud AIO Interface via:
https://your-domain-that-points-to-this-server.tld:8443"

# Set the timezone to Etc/UTC
export TZ=Etc/UTC

# Fix apache startup
rm -f /var/run/apache2/httpd.pid

# Fix the Caddyfile format
caddy fmt --overwrite /Caddyfile

# Fix caddy log 
chmod 777 /root

# Start supervisord
/usr/bin/supervisord -c /supervisord.conf
