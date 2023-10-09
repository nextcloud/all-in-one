## NPMplus
This container contains a fork of the Nginx Proxy Manager, which is a WebUI for nginx. It will also automatically create a config and cert for AIO.

### Notes
- Make sure that no other service is using port `80 (tcp)`, `443 (tcp/upd)` or `81 (udp)` on your host as otherwise the containers will fail to start. You can check this with `sudo netstat -tulpn | grep "443\|80\|81"` before installing AIO.
- After the container was started the first time, please check the logs for errors. Then you can open NPMplus on `https://127.0.0.1:81` (`[::1]` also works) and change the password. 
- If you want to use NPMplus behind a domain and outside localhost just create a new proxy host inside the NPMplus which proxies to `https`, `127.0.0.1` and port `81` - all other settings should be the same as for the AIO host.
- The default password is `iArhP1j7p1P6TA92FA2FMbbUGYqwcYzxC4AVEe12Wbi94FY9gNN62aKyF1shrvG4NycjjX9KfmDQiwkLZH1ZDR9xMjiG2QmoHXi` and the default email is `admin@example.com`
- Please change the default login data first, after you can read inside the logs that the default config for AIO is created and there are no errors.
- The PHP options are not supported, as well as all other env options.
- See https://github.com/nextcloud/all-in-one/tree/main/community-containers how to add it to the AIO stack

### Repository and Documentation
https://github.com/ZoeyVid/NPMplus

### Maintainer
https://github.com/Zoey2936
