## NPMplus
This container contains a fork of the Nginx Proxy Manager, which is a WebUI for nginx. It will also automatically create a config and cert for AIO.

### Notes
- This container is incompatible with the [caddy](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy) community container. So make sure that you do not enable both at the same time!
- You can ignore the NPM configuration of the reverse-proxy.md. The NPMplus fork already contains the changes of the advanced tab.
- Make sure that no other service is using port `443 (tcp/upd)` or `81 (tcp)` on your host as otherwise the containers will fail to start. You can check this with `sudo netstat -tulpn | grep "443\|81"` before installing AIO.
- Please change the default login data first, after you can read inside the logs that the default config for AIO is created and there are no errors.
- After the container was started the first time, please check the logs for errors. Then you can open NPMplus on `https://<ip>:81` and change the password. 
- The default password is `iArhP1j7p1P6TA92FA2FMbbUGYqwcYzxC4AVEe12Wbi94FY9gNN62aKyF1shrvG4NycjjX9KfmDQiwkLZH1ZDR9xMjiG2QmoHXi` and the default email is `admin@example.org`
- If you want to use NPMplus behind a domain and outside localhost just create a new proxy host inside the NPMplus which proxies to `https`, `127.0.0.1` and port `81` - all other settings should be the same as for the AIO host.
- If you want to set env options from this [compose.yaml](https://github.com/ZoeyVid/NPMplus/blob/develop/compose.yaml), please set them inside the `.env` file which you can find in the `nextcloud_aio_npmplus` volume
- The data (certs, configs, etc.) of NPMplus will be automatically included in AIOs backup solution!
- **Important:** you always need to enable https for your hosts, since `DISABLE_HTTP` is set to true
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers how to add it to the AIO stack

### Repository and Documentation
https://github.com/ZoeyVid/NPMplus

### Maintainer
https://github.com/Zoey2936
