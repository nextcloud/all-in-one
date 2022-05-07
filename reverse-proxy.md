## Reverse Proxy Documentation

**Please note:** Publishing the AIO interface with a valid certificate to the public internet is **not** the goal of this documentation! Instead, the main goal is to publish Nextcloud with a valid certificate to the public internet which is **not** running inside the mastercontainer but in a different container! If you need a valid certificate for the AIO interface, see [point 3](#3-optional-get-a-valid-certificate-for-the-aio-interface). 

In order to run Nextcloud behind a reverse proxy, you need to specify the port that the Apache container shall use, add a specific config to your reverse proxy and modify the startup command a bit. All examples below will use port `11000` as example Apache port which will be exposed on the host. Modify it to your needings.

**Attention** The process to run Nextcloud behind a reverse proxy consists of at least these 2 steps:
1. **Configure the reverse proxy! See [point 1](#1-add-this-to-your-reverse-proxy-config)**
1. **Use the in this document provided startup command! See [point 2](#2-use-this-startup-command)**
- Optional: get a valid certificate for the AIO interface! See [point 3](#3-optional-get-a-valid-certificate-for-the-aio-interface)
- How to debug things? See [point 4](#4-how-to-debug-things)

### 1. Add this to your reverse proxy config

**Please note:** Since the Apache container gets spawned by the mastercontainer, there is **NO** way to provide custom docker labels or custom environmental variables for the Apache container. So please do not attempt to do this because you will fail! Only the documented way will work!

#### Caddy

<details>

<summary>click here to expand</summary>

Add this to your Caddyfile:

```
https://<your-nc-domain>:443 {
    header Strict-Transport-Security max-age=31536000;
    reverse_proxy localhost:11000
}
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `locahost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

</details>

#### Nginx

<details>

<summary>click here to expand</summary>

**Disclaimer:** the config below is not working 100% correctly, yet. See e.g. https://github.com/nextcloud/all-in-one/issues/450, https://github.com/nextcloud/all-in-one/issues/447 and https://github.com/nextcloud/all-in-one/issues/491. Improvements to it are very welcome!

Add this to you nginx config:

```
location / {
        proxy_pass http://localhost:11000;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header Host $host;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        client_max_body_size 0;

        # Websocket
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `locahost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

</details>

#### Nginx-Proxy

<details>

<summary>click here to expand</summary>

Unfortunately it is not possible to configure nginx-proxy in a way that works because it completely relies on environmental variables of the docker containers itself. Providing these variables does not work as stated above.

If you really want to use AIO, we recommend you to switch to caddy. It is simply amazing!<br>
Of course understandable if that is not possible for you.

Apart from that, there is this idea: https://github.com/nextcloud/all-in-one/issues/557<br>
Pull requests are very welcome!

</details>

#### Traefik 2

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

1. Add a `nextcloud.toml` to the Treafik rules folder with the following content:

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
                    url = "http://locahost:11000"
    ```

2. Add to the bottom of the `middlewares.toml` file in the Treafik rules folder the following content:

    ```toml
    [http.middlewares.nc-middlewares-secure-headers]
        [http.middlewares.nc-middlewares-secure-headers.headers]
            hostsProxyHeaders = ["X-Forwarded-Host"]
            sslRedirect = true
            stsSeconds = 63072000
            stsIncludeSubdomains = true
            stsPreload = true
            forceSTSHeader = true
            referrerPolicy = "same-origin"
            X-Robots-Tag = "none"
    ```

3. Add to the bottom of the `middleware-chains.toml` file in the Traefik rules folder the following content:

    ```toml
    [http.middlewares.chain-nc]
        [http.middlewares.chain-nc.chain]
            middlewares = [ "middlewares-rate-limit", "nc-middlewares-secure-headers"]
    ```

---

Of course you need to modify `<your-nc-domain>` in the nextcloud.toml to the domain on which you want to run Nextcloud. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `locahost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

</details>

### 2. Use this startup command

After adjusting your reverse proxy config, use the following command to start AIO:<br>

(For an docker-compose example, see the example further [below](#inspiration-for-a-docker-compose-file).)

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

#### Inspiration for a docker-compose file

Simply translate the docker run command into a docker-compose file. You can have a look at [this file](https://github.com/nextcloud/all-in-one/blob/main/docker-compose.yml) for some inspiration but you will need to modify it either way. You can find further examples here: https://github.com/nextcloud/all-in-one/discussions/588

---

#### How to continue? 
After using the above command, you should be able to access the AIO Interface via `https://ip.address.of.the.host:8080`. Enter your domain that you've entered in the reverse proxy config and you should be done. Please do not forget to open port `3478/TCP` and `3478/UDP` in your firewall/router for the Talk container!

### 3. Optional: get a valid certificate for the AIO interface

If you want to also access your AIO interface publicly with a valid certificate, you can add e.g. the following config to your Caddyfile:

```
https://<your-nc-domain>:8443 {
    reverse_proxy https://localhost:8080 {
        transport http {
            tls_insecure_skip_verify
        }
    }
}
```

Of course you need to modify `<your-nc-domain>` in the nextcloud.toml to the domain on which you want to run Nextcloud. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `locahost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

Afterwards should the AIO interface be accessible via `https://ip.address.of.the.host:8443`. You can alternatively change the domain to a different subdomain by using `https://<your-alternative-domain>:443` instead of `https://<your-nc-domain>:8443` in the Caddyfile and use that to access the AIO interface.

### 4. How to debug things?
If something does not work, follow the steps below:
1. Make sure to exactly follow the whole reverse proxy documentation step-for-step from top to bottom!
1. Make sure that the reverse proxy is running on the host OS or if running in a container, connected to the host network. If that is not possible, substitute `localhost` in the default configurations by the ip-address that you can easily get when running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (The command only works on Linux)
1. Make sure that the mastercontainer is able to spawn other containers. You can do so by checking that the mastercontainer indeed has access to the Docker socket which might not be positioned in one of the suggested directories like `/var/run/docker.sock` but in a different directory, based on your OS and the way how you installed Docker. The mastercontainer logs should help figuring this out. You can have a look at them by running `sudo docker logs nextcloud-aio-mastercontainer` after the container is started the first time.
1. Check if after the mastercontainer was started, the reverse proxy if running inside a container, can reach the provided apache port. You can test this by running `nc -z locahost 11000; echo $?` from inside the reverse proxy container. If the output is `0`, everything works. Alternatively you can of course use instead of `locahost` the ip-address of the host here for the test.
1. Try to configure everything from scratch if it still does not work!
