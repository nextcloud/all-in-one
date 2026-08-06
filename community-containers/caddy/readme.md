# Caddy community container

This container bundles [caddy](https://caddyserver.com/) and auto-configures it for you as a reverse proxy. It automatically obtains SSL certificates from let's encrypt

> [!Caution]
> - This container is incompatible with the [npmplus](https://github.com/nextcloud/all-in-one/tree/main/community-containers/npmplus) community container. So make sure that you do not enable both at the same time!
> - Make sure that no other service is using port 443/tcp on your host as otherwise the containers will fail to start. You can check this with `sudo netstat -tulpn | grep 443` before installing this container
> - the default `admin` user needs to be present, i.e. it can not be deleted because caddy configuration can be done there. 

## Supported community containers

This container configures subdomains for a number of community containers. 

> [!Important]
> You need to set the correct DNS records for this to work
> 
> Example:
> - Your domain is `your-nc-domain.com` and you want to use the vaultwarden container.
> - Then you need to set CNAME record for the subdomain `bw.your-nc-domain.com` to point to your main domain `your-nc-domain.com`.
> - Alternatively, you can configure the A/AAAA records of `bw.your-nc-domain.com` to point to the public IP-address of your Nextcloud AIO server.


| Container                                                                                                           | Subdomain                        | Geoblocking | IP Allow List | Authentication |
|---------------------------------------------------------------------------------------------------------------------|----------------------------------|-------------|---------------|----------------|
| [azuracast](https://github.com/nextcloud/all-in-one/tree/main/community-containers/azuracast)                       | `radio.your-nc-domain.com`       | ✅           |               |                |
| [glances](https://github.com/nextcloud/all-in-one/tree/main/community-containers/glances)                           | `glances.your-nc-domain.com`     | ✅           |               | ✅             |
| [jellyfin](https://github.com/nextcloud/all-in-one/tree/main/community-containers/jellyfin)                         | `media.your-nc-domain.com`       | ✅           |               |                |
| [joplin-server](https://github.com/nextcloud/all-in-one/tree/main/community-containers/joplin-server)               | `joplin.your-nc-domain.com`      | ✅           |               |                |
| [lldap](https://github.com/nextcloud/all-in-one/tree/main/community-containers/lldap)                               | `ldap.your-nc-domain.com`        | ✅           | ✅            |                |
| [LocalAI](https://github.com/nextcloud/all-in-one/tree/main/community-containers/local-ai)                          | `ai.your-nc-domain.com`          | ✅           |               |                |
| [nextcloud-exporter](https://github.com/nextcloud/all-in-one/tree/main/community-containers/nextcloud-exporter)     | `metrics.your-nc-domain.com`     | ✅           |               | ✅             |
| [nocodb](https://github.com/nextcloud/all-in-one/tree/main/community-containers/nocodb)                             | `tables.your-nc-domain.com`      | ✅           |               |                |
| [seerr](https://github.com/nextcloud/all-in-one/tree/main/community-containers/jellyseerr)                          | `requests.your-nc-domain.com`    | ✅           |               |                |
| [stalwart](https://github.com/nextcloud/all-in-one/tree/main/community-containers/stalwart)                         | `mail.your-nc-domain.com`        | ✅           | ✅            |                |
| [vaultwarden](https://github.com/nextcloud/all-in-one/tree/main/community-containers/vaultwarden)                   | `bw.your-nc-domain.com`          | ✅           | ✅            |                |

## Geoblocking

 - After the container was started the first time, log in as default `admin` user. You should see a new `nextcloud-aio-caddy` folder and inside there an `allowed-countries.txt` file
 - In there you can adjust the allowed country codes for caddy by adding them to the first line, e.g. `IT FR` would allow access from italy and france.
 - Additionally, in order to activate this config, you need to get an account at https://dev.maxmind.com/geoip/geolite2-free-geolocation-data
 - download the `GeoLite2-Country.mmdb` from there and upload it with this exact name into the `nextcloud-aio-caddy` folder.
 - Afterwards restart all containers from the AIO interface and your new config should be active

> [!Warning]
> - no entry in that file disables blocking
> - Private ip-ranges are always allowed.

## IP allow lists

 - Some containers allow for setting allowed IP addresses
 - This way you can secure administration interfaces from the external access
 - After the container was started the first time, log in as default `admin` user. You should see a new `nextcloud-aio-caddy` folder
 - put one of the following files there with the allowed IPs
     - allowed-IPs-vaultwarden.txt
     - allowed-IPs-stalwart.txt
     - allowed-IPs-lldap.txt
 - In there you can adjust the allowed IPs by adding them to the first line, e.g. `11.22.33.44 192.168.1.0/24` will allow access from those IPs

> [!Warning]
> - missing files or no entries in that file means no IP restriction is applied

## Authentication

- Some containers have randomly created access passwords for additional security
- Those secrets will be shown in the AIO interface after installation

## Custom configuration

It is possible to add configuration for even more services and subdomains. 

> [!Caution]
> - Errors in config will result in caddy not starting at all
> - Be sure to know what you are doing, the risk is all yours
> - You should probably check everything before breaking stuff. Use at least a service like https://abacktools.com/tools/data/validators/caddy-config-validator

There are 2 different approaches:

1. within nextcloud
    - After the container was started the first time, log in as default `admin` user. You should see a new `nextcloud-aio-caddy` folder
    - create a sub folder `caddy-imports`
    - in there you can add one or more \*.txt files
    - These will be imported on container startup
    - if this fails, caddy won't come up and you can not correct yourself
    - in that case open this txt-file on your server using the command line
    - if you did not change the default location of Nextcloud's Datadir then you will find it here:  `/var/lib/docker/volumes/nextcloud_aio_nextcloud_data/_data/admin/files/nextcloud-aio-caddy/caddy-imports`
2. inside the Caddy container
    - You can alternatively add your own Caddy configurations inside the Caddy container
    - on your CLI:
        - `sudo docker exec -it nextcloud-aio-caddy bash`
        - `cd /data/caddy-imports/`
    - in there you can add one or more \*.txt files
    - These will be imported on container startup

> [!Note]
> If you do not have CLI access to the server run docker commands via a web session by using this community container: https://github.com/nextcloud/all-in-one/tree/main/community-containers/container-management

Simple example for a custom configuration:

```
https://subdomain.your-nc-domain.com:443 {
    # actual redirection to port 1234 of container testme
    reverse_proxy testme:1234
    # TLS options
    tls {
        issuer acme {
            disable_http_challenge
        }
    }
}
```

More complex example for a custom configuration:
```
https://subdomain.your-nc-domain.com:443 {

    # Geofilter will be added by caddy in the next line, if you keep it
    # import GEOFILTER

    # own IP filter for that configuration, only those are allowed
    @public_networks not remote_ip 11.22.33.44 192.168.1.0/24
        respond @public_networks 403 {
            close
        }

    # actual redirection to port 1234 of container testme
    reverse_proxy testme:1234

    # TLS options
    tls {
        issuer acme {
            disable_http_challenge
        }
    }

    # own username and password for that configuration
    # password is hashed by bcrypt algorithm
    basic_auth {
        # Username "Bob", password "hiccup"
        Bob $2a$14$Zkx19XLiW6VYouLHR5NmfOFU0z2GTNmpkT/5qqR7hx4IjWJPDhjvG
    }
}
```


## Running caddy behind a proxy

- The container also supports the proxy protocol inside caddy. That means that you can run a supported web server in front of port 443/tcp and use the proxy protocol.
- You can enable this by configuring the `APACHE_IP_BINDING` environmental variable for the mastercontainer and set it to an ip-address from which the protocol shall be accepted.
- ⚠️ Note that the initial domain validation will not work correctly if you want to use the proxy protocol. So make sure to skip the domain validation in that case. See the [documentation](https://github.com/nextcloud/all-in-one#how-to-skip-the-domain-validation).

## Notes

- Starting with AIO v12, the Talk port that was usually exposed on port 3478 is now set to port 443 udp and tcp and reachable via `your-nc-domain.com`. For the changes to become activated, you need to go to `https://your-nc-domain.com/settings/admin/talk` and delete all turn and stun servers. Then restart the containers and the new config should become active.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack
- If you want to remove the container again and revert back to the default, you need to disable the container via the AIO-interface and follow https://github.com/nextcloud/all-in-one/blob/main/reverse-proxy.md#8-removing-the-reverse-proxy

### Repository
https://github.com/szaimen/aio-caddy

### Maintainer
https://github.com/szaimen
