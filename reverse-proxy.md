## Reverse Proxy Documentation

**Please note:** Publishing the AIO interface with a valid certificate to the public internet is **NOT** the goal of this documentation! Instead, the main goal is to publish Nextcloud with a valid certificate to the public internet which is **NOT** running inside the mastercontainer but in a different container! If you need a valid certificate for the AIO interface, see [point 3](#3-optional-get-a-valid-certificate-for-the-aio-interface). 

In order to run Nextcloud behind a reverse proxy, you need to specify the port that the Apache container shall use, add a specific config to your reverse proxy and modify the startup command a bit. All examples below will use port `11000` as example Apache port. Modify it to your needings.

⚠ **Attention** The process to run Nextcloud behind a reverse proxy consists of at least these 2 steps:
1. **Configure the reverse proxy! See [point 1](#1-add-this-to-your-reverse-proxy-config)**
1. **Use the in this document provided startup command! See [point 2](#2-use-this-startup-command)**
- Optional: get a valid certificate for the AIO interface! See [point 3](#3-optional-get-a-valid-certificate-for-the-aio-interface)
- How to debug things? See [point 4](#4-how-to-debug-things)

### 1. Add this to your reverse proxy config

⚠ **Please note:** Since the Apache container gets spawned by the mastercontainer, there is **NO** way to provide custom docker labels or custom environmental variables for the Apache container. So please do not attempt to do this because you will fail!

#### Caddy

<details>

<summary>click here to expand</summary>

Add this to your Caddyfile:

```
https://<your-nc-domain>:443 {
    header Strict-Transport-Security max-age=31536000;
    reverse_proxy <private.ip.address.of.the.host>:11000
}
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also you need to modify `<private.ip.address.of.the.host>` to the private ip-address of the host that is running the docker daemon. **Advice:** the `nextcloud-aio-mastercontainer` is **NOT** running the docker daemon. The host itself is running the docker daemon.

</details>

#### Nginx

<details>

<summary>click here to expand</summary>

**Disclaimer:** the config below is not working 100% correctly, yet. See e.g. https://github.com/nextcloud/all-in-one/issues/450, https://github.com/nextcloud/all-in-one/issues/447 and https://github.com/nextcloud/all-in-one/issues/491. Improvements to it are very welcome!

Add this to you nginx config:

```
location / {
        proxy_pass http://<private.ip.address.of.the.host>:11000;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;

        # Websocket
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }
```

Of course SSL needs to be set up as well e.g. by using certbot and your domain must be also added inside the nginx config. You will also need to modify `<private.ip.address.of.the.host>` to the private ip-address of the host that is running the docker daemon. **Advice:** the `nextcloud-aio-mastercontainer` is **NOT** running the docker daemon. The host itself is running the docker daemon.

</details>

#### Nginx-Proxy

<details>

<summary>click here to expand</summary>

Unfortunately it is not possible to configure nginx-proxy in a way that works because it completely relies on environmental variables of the docker containers itself. Providing these does not work as stated above.

</details>

#### Traefik 2

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Add a `nc.toml` to the Treafik rules folder with the following content:

```toml
[http.routers]
    [http.routers.nc-rtr]
        entryPoints = ["https"]
        rule = "Host(<your-nc-domain>)"
        service = "nc-svc"
        middlewares = ["chain-no-auth"]
        [http.routers.nc-rtr.tls]
            certresolver = "le"

[http.services]
    [http.services.nc-svc]
        [http.services.nc-svc.loadBalancer]
            passHostHeader = true
            [[http.services.nc-svc.loadBalancer.servers]]
                url = "http://<private.ip.address.of.the.host>:11000"
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. You will also need to modify `<private.ip.address.of.the.host>` to the private ip-address of the host that is running the docker daemon. **Advice:** the `nextcloud-aio-mastercontainer` is **NOT** running the docker daemon. The host itself is running the docker daemon.

</details>

### 2. Use this startup command

After adjusting your reverse proxy config, use the following command to start AIO:

```
# For x64 CPUs:
sudo docker run -it \
--name nextcloud-aio-mastercontainer \
--restart always \
-p 8080:8080 \
-e APACHE_PORT=11000 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest
```

<details>

<summary>Command for arm64 CPUs like the Raspberry Pi 4</summary>

```
# For arm64 CPUs:
sudo docker run -it \
--name nextcloud-aio-mastercontainer \
--restart always \
-p 8080:8080 \
-e APACHE_PORT=11000 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest-arm64
```

</details>

On macOS see https://github.com/nextcloud/all-in-one#how-to-run-it-on-macos.

<details>

<summary>Command for Windows</summary>

```
docker run -it ^
--name nextcloud-aio-mastercontainer ^
--restart always ^
-p 8080:8080 ^
-e APACHE_PORT=11000 ^
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config ^
--volume //var/run/docker.sock:/var/run/docker.sock:ro ^
nextcloud/all-in-one:latest
```

</details>

<details>

<summary>Inspiration for a docker-compose file</summary>

Simply translate the docker run command into a docker-compose file. You can have a look at [this file](https://github.com/nextcloud/all-in-one/blob/main/docker-compose.yml) for some inspiration but you will need to modify it either way.

</details>

---

#### How to continue? 
After using the above command, you should be able to access the AIO Interface via `https://private.ip.address.of.the.host:8080`. Enter your domain that you've entered in the reverse proxy config and you should be done. Please do not forget to open port `3478/TCP` and `3478/UDP` in your firewall/router for the Talk container!

### 3. Optional: get a valid certificate for the AIO interface

If you want to also access your AIO interface publicly with a valid certificate, you can add e.g. the following config to your Caddyfile:

```
https://<your-nc-domain>:8443 {
    reverse_proxy https://<private.ip.address.of.the.host>:8080 {
        transport http {
            tls_insecure_skip_verify
        }
    }
}
```

Of course, you also need to modify `<your-nc-domain>` to the domain that you want to use. You will also need to modify `<private.ip.address.of.the.host>` to the private ip-address of the host that is running the docker daemon. **Advice:** the `nextcloud-aio-mastercontainer` is **NOT** running the docker daemon. The host itself is running the docker daemon.

Afterwards should the AIO interface be accessible via `https://<your-nc-domain>:8443`. You can alternatively change the domain to a different subdomain by using `https://<your-alternative-domain>:443` in the Caddyfile and use that to access the AIO interface.

### 4. How to debug things?
If something does not work, follow the steps below:
1. Make sure to exactly follow the whole reverse proxy documentation step-for-step from top to bottom!
1. Find out if you can ping the private ip-address of the host that is running the docker daemon from inside the reverse proxy container (if runing the reverse proxy in a container). **Advice:** the `nextcloud-aio-mastercontainer` is **NOT** running the docker daemon. The host itself is running the docker daemon.
1. Try to configure everything from scratch if it still does not work!
    <!-- - If not, you need to make that possible. In worst case, you need to use the `--network host` option when starting the reverse proxy container (if the reverse proxy is running inside a container) -->
