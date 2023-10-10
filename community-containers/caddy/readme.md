## Caddy with geoblocking
This container bundles caddy and auto-configures it for you. It also covers https://github.com/nextcloud/all-in-one/tree/main/community-containers/vaultwarden by listening on `bw.$NC_DOMAIN`, if installed.

### Notes
- This container is incompatible with the [npmplus](https://github.com/nextcloud/all-in-one/tree/main/community-containers/npmplus) community container. So make sure that you do not enable both at the same time!
- Make sure that no other service is using port 443 on your host as otherwise the containers will fail to start. You can check this with `sudo netstat -tulpn | grep 443` before installing AIO.
- If you want to use this with https://github.com/nextcloud/all-in-one/tree/main/community-containers/vaultwarden, make sure that you point `bw.your-nc-domain.com` to your server using a cname record so that caddy can get a certificate automatically for vaultwarden.
- After the container was started the first time, you should see a new `nextcloud-aio-caddy` folder and inside there an `allowed-countries.txt` file when you open the files app with the default `admin` user. In there you can adjust the allowed country codes for caddy by adding them to the first line, e.g. `IT FR` would allow access from italy and france. Private ip-ranges are always allowed. Additionally, in order to activate this config, you need to get an account at https://dev.maxmind.com/geoip/geolite2-free-geolocation-data and download the `GeoLite2-Country.mmdb` and upload it with this exact name into the `nextcloud-aio-caddy` folder. Afterwards restart all containers from the AIO interface and your new config should be active!
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers how to add it to the AIO stack

### Repository
https://github.com/szaimen/aio-caddy

### Maintainer
https://github.com/szaimen
