# Reverse Proxy Documentation

**Please note:** Publishing the AIO interface with a valid certificate to the public internet is **not** the goal of this documentation! Instead, the main goal is to publish Nextcloud with a valid certificate to the public internet which is **not** running inside the mastercontainer but in a different container! If you need a valid certificate for the AIO interface, see [point 5](#5-optional-get-a-valid-certificate-for-the-aio-interface). 

In order to run Nextcloud behind a reverse proxy, you need to specify the port that the Apache container shall use, add a specific config to your reverse proxy and modify the startup command a bit. All examples below will use port `11000` as example Apache port which will be exposed on the host. Modify it to your needings.

**Attention:** The process to run Nextcloud behind a reverse proxy consists of at least steps 1, 2 and 4:
1. **Configure the reverse proxy! See [point 1](#1-add-this-to-your-reverse-proxy-config)**
1. **Use the in this document provided startup command! See [point 2](#2-use-this-startup-command)**
1. Optional: If the reverse proxy is installed on the same host, you should limit the apache container to only listen on localhost. See [point 3](#3-if-the-reverse-proxy-is-installed-on-the-same-host-you-should-configure-the-apache-container-to-only-listen-on-localhost)
1. **Open the AIO interface. See [point 4](#4-open-the-aio-interface)**
1. Optional: Get a valid certificate for the AIO interface! See [point 5](#5-optional-get-a-valid-certificate-for-the-aio-interface)
1. Optional: How to debug things? See [point 6](#6-how-to-debug-things)

## 1. Add this to your reverse proxy config

**Please note:** Since the Apache container gets spawned by the mastercontainer, there is **NO** way to provide custom docker labels or custom environmental variables for the Apache container. So please do not attempt to do this because you will fail! Only the documented way will work!

### Apache

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Add this as a new Apache site config:

(The config below assumse that you are using certbot to get your certificates. You need to create them first in order to make it work.)

```
<VirtualHost *:80>
    ServerName <your-nc-domain>

    RewriteEngine On
    RewriteCond %{HTTPS} off
    RewriteRule (.*) https://%{HTTP_HOST}%{REQUEST_URI}
    RewriteCond %{SERVER_NAME} =<your-nc-domain>
    RewriteRule ^ https://%{SERVER_NAME}%{REQUEST_URI} [END,NE,R=permanent]
</VirtualHost>

<VirtualHost *:443>
    ServerName <your-nc-domain>

    # Reverse proxy based on https://httpd.apache.org/docs/current/mod/mod_proxy_wstunnel.html
    RewriteEngine On
    ProxyPreserveHost On
    AllowEncodedSlashes NoDecode
    ProxyPass / http://localhost:11000/ nocanon
    RewriteCond %{HTTP:Upgrade} websocket [NC]
    RewriteCond %{HTTP:Connection} upgrade [NC]
    RewriteCond %{THE_REQUEST} "^[a-zA-Z]+ /(.*) HTTP/\d+(\.\d+)?$"
    RewriteRule .? "ws://localhost:11000/%1" [P,L]

    # Enable h2, h2c and http1.1
    Protocols h2 h2c http/1.1

    # SSL
    SSLEngine on
    Include /etc/letsencrypt/options-ssl-apache.conf
    SSLCertificateFile /etc/letsencrypt/live/<your-nc-domain>/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/<your-nc-domain>/privkey.pem

    # Disable HTTP TRACE method.
    TraceEnable off
    <Files ".ht*">
        Require all denied
    </Files>

    # Support big file uploads
    LimitRequestBody 0
</VirtualHost>
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

To make the config work you can run the following command:
`sudo a2enmod rewrite proxy proxy_http proxy_wstunnel ssl headers http2`

</details>

### Caddy (Recommended)

<details>

<summary>click here to expand</summary>

Add this to your Caddyfile:

```
https://<your-nc-domain>:443 {
    reverse_proxy localhost:11000
}
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

**Advice:** You may have a look at [this](https://github.com/nextcloud/all-in-one/discussions/575#discussion-4055615) for a more complete example.

</details>

### Caddy with ACME DNS-challenge

<details>

<summary>click here to expand</summary>

You can get AIO running using the ACME DNS-challenge. Here is how to do it.

1. Follow [this documentation](https://caddy.community/t/how-to-use-dns-provider-modules-in-caddy-2/8148) in order to get a Caddy build that is compatible with your domain provider's DNS challenge.
1. Add this to your Caddyfile:
    ```
    https://<your-nc-domain>:443 {
        reverse_proxy localhost:11000
        tls {
            dns <provider> <key>
        }
    }
    ```
    Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. You also need to adjust `<provider>` and `<key>` to match your case. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)
1. Now continue with [point 2](#2-use-this-startup-command) but additionally, add `-e SKIP_DOMAIN_VALIDATION=true` to the docker run command which will disable the dommain validation (because it is known that the domain validation will not when using the DNS-challenge since no port is publicly opened.

**Advice:** In order to make it work in your home network, you may add the internal ipv4-address of your reverse proxy as A DNS-record to your domain and disable the dns-rebind-protection in your router. Another way it to set up a local dns-server like a pi-hole and set up a custom dns-record for that domain that points to the internal ip-adddress of your reverse proxy (see https://github.com/nextcloud/all-in-one#how-can-i-access-nextcloud-locally). If both is not possible, you may add the domain to the hosts file which is needed then for any devices that shall use the server.

</details>

### Cloudflare Argo Tunnel

<details>

<summary>click here to expand</summary>

Although it does not seems like it is the case but from AIO perspective a Cloudflare Argo Tunnel works like a reverse proxy. Here is how to make it work:

1. Install the Cloudflare Argo Tunnel on the same machine where AIO will be running on and point the Argo Tunnel with the domain that you want to use for AIO to `http://localhost:11000`. If the Argo Tunnel is running on a different machine, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)
1. Now continue with [point 2](#2-use-this-startup-command) but additionally, add `-e SKIP_DOMAIN_VALIDATION=true` to the docker run command which will disable the dommain validation (because it is known that the domain validation will not work behind a Cloudflare Argo Tunnel). So you need to ensure yourself that you've configured everything correctly.

</details>

### HaProxy

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Here is an example HaProxy config:

```
global
    chroot                      /var/haproxy
    log                         /var/run/log audit debug
    lua-prepend-path            /tmp/haproxy/lua/?.lua

defaults
    log     global
    option redispatch -1
    retries 3
    default-server init-addr last,libc

# Frontend: LetsEncrypt_443 ()
frontend LetsEncrypt_443
    bind 0.0.0.0:443 name 0.0.0.0:443 ssl prefer-client-ciphers ssl-min-ver TLSv1.2 ciphers ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-SHA384:ECDHE-ECDSA-AES128-SHA256 ciphersuites TLS_AES_128_GCM_SHA256:TLS_AES_256_GCM_SHA384:TLS_CHACHA20_POLY1305_SHA256 crt-list /tmp/haproxy/ssl/605f6609f106d1.17683543.certlist 
    mode http
    option http-keep-alive
    default_backend acme_challenge_backend
    option forwardfor
    # tuning options
    timeout client 30s

    # logging options
    # ACL: find_acme_challenge
    acl acl_605f6d4b6453d2.03059920 path_beg -i /.well-known/acme-challenge/
    # ACL: Nextcloud
    acl acl_60604e669c3ca4.13013327 hdr(host) -i <your-nc-domain>

    # ACTION: redirect_acme_challenges
    use_backend acme_challenge_backend if acl_605f6d4b6453d2.03059920
    # ACTION: Nextcloud
    use_backend Nextcloud if acl_60604e669c3ca4.13013327


# Frontend: LetsEncrypt_80 ()
frontend LetsEncrypt_80
    bind 0.0.0.0:80 name 0.0.0.0:80 
    mode tcp
    default_backend acme_challenge_backend
    # tuning options
    timeout client 30s

    # logging options
    # ACL: find_acme_challenge
    acl acl_605f6d4b6453d2.03059920 path_beg -i /.well-known/acme-challenge/

    # ACTION: redirect_acme_challenges
    use_backend acme_challenge_backend if acl_605f6d4b6453d2.03059920

# Frontend (DISABLED): 1_HTTP_frontend ()

# Frontend (DISABLED): 1_HTTPS_frontend ()

# Frontend (DISABLED): 0_SNI_frontend ()

# Backend: acme_challenge_backend (Added by Let's Encrypt plugin)
backend acme_challenge_backend
    # health checking is DISABLED
    mode http
    balance source
    # stickiness
    stick-table type ip size 50k expire 30m  
    stick on src
    # tuning options
    timeout connect 30s
    timeout server 30s
    http-reuse safe
    server acme_challenge_host 127.0.0.1:43580 

# Backend: Nextcloud ()
backend Nextcloud
    mode http
    balance source
    server Nextcloud localhost:11000 
```

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

</details>

### Nginx

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

Add this to you nginx config:

```
map $http_upgrade $connection_upgrade {
    default upgrade;
    '' close;
}

server {
    listen 80;
    listen [::]:80;            # comment to disable IPv6

    if ($scheme = "http") {
        return 301 https://$host$request_uri;
    }

    listen 443 ssl http2;
    listen [::]:443 ssl http2; # comment to disable IPv6

    server_name <your-nc-domain>;

    location / {
        resolver localhost;
        proxy_pass http://localhost:11000$request_uri;

        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        client_max_body_size 0;

        # Websocket
        proxy_http_version 1.1;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection $connection_upgrade;
    }

    ssl_certificate /etc/letsencrypt/live/<your-nc-domain>/fullchain.pem;   # managed by certbot on host machine
    ssl_certificate_key /etc/letsencrypt/live/<your-nc-domain>/privkey.pem; # managed by certbot on host machine

    ssl_session_timeout 1d;
    ssl_session_cache shared:MozSSL:10m; # about 40000 sessions
    ssl_session_tickets off;

    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256:ECDHE-ECDSA-AES256-GCM-SHA384:ECDHE-RSA-AES256-GCM-SHA384:ECDHE-ECDSA-CHACHA20-POLY1305:ECDHE-RSA-CHACHA20-POLY1305:DHE-RSA-AES128-GCM-SHA256:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
}

```
    
Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

**Advice:** You may have a look at [this](https://github.com/nextcloud/all-in-one/discussions/588#discussioncomment-2811152) for a more complete example.

</details>

### Nginx-Proxy

<details>

<summary>click here to expand</summary>

Unfortunately it is not possible to configure nginx-proxy in a way that works because it completely relies on environmental variables of the docker containers itself. Providing these variables does not work as stated above.

If you really want to use AIO, we recommend you to switch to caddy. It is simply amazing!<br>
Of course understandable if that is not possible for you.

Apart from that, there is this: [manual-install](https://github.com/nextcloud/all-in-one/tree/main/manual-install)

</details>

### Nginx-Proxy-Manager

<details>

<summary>click here to expand</summary>

See these screenshots for a working config:

![image](https://user-images.githubusercontent.com/75573284/169556183-2999a733-de42-4008-af09-d4151719a474.png)

![image](https://user-images.githubusercontent.com/75573284/169555356-71f32be5-99b5-43ea-8aa7-632c8ef8fad3.png)

![image](https://user-images.githubusercontent.com/75573284/169557664-52db8713-f0ef-42ac-a161-de40280232a3.png)

![image](https://user-images.githubusercontent.com/75573284/169555441-dd9a42f5-aea5-4082-8e26-7adcfa4e6cfa.png)

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also change `<you>@<your-mail-provider-domain>` to a mail address of yours. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

**Advice:** You may have a look at [this](https://github.com/nextcloud/all-in-one/discussions/588#discussioncomment-3040493) for a more complete example.

</details>

### Synology Reverse Proxy

<details>

<summary>click here to expand</summary>

**Disclaimer:** It might be possible that the config below is not working 100% correctly, yet. Improvements to it are very welcome!

See these screenshots for a working config:

![image](https://user-images.githubusercontent.com/89748315/192525606-48cab54b-866e-4964-90a8-15e71bd362fb.png)

![image](https://user-images.githubusercontent.com/89748315/192525681-c06f3b39-f510-458e-b1f2-6b2cd995e24c.png)

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

</details>

### Traefik 2

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
                    url = "http://localhost:11000"
    ```

2. Add to the bottom of the `middlewares.toml` file in the Treafik rules folder the following content:

    ```toml
    [http.middlewares.nc-middlewares-secure-headers]
        [http.middlewares.nc-middlewares-secure-headers.headers]
            hostsProxyHeaders = ["X-Forwarded-Host"]
            sslRedirect = true
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

Of course you need to modify `<your-nc-domain>` in the nextcloud.toml to the domain on which you want to run Nextcloud. Also make sure to adjust the port 11000 to match the chosen APACHE_PORT. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` option (or `network_mode: host` for docker-compose) when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

</details>

### Others

<details>

<summary>click here to expand</summary>

Config examples for other reverse proxies are currently not documented. Pull requests are welcome!

</details>

## 2. Use this startup command

After adjusting your reverse proxy config, use the following command to start AIO:<br>

(For an docker-compose example, see the example further [below](#inspiration-for-a-docker-compose-file).)

```
# For x64 CPUs:
sudo docker run \
--sig-proxy=false \
--name nextcloud-aio-mastercontainer \
--restart always \
--publish 8080:8080 \
-e APACHE_PORT=11000 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest
```

You should also think about limiting the apache container to listen only on localhost in case the reverse proxy is running on the same host by providing an additional environmental variable to this docker run command. See [point 3](#3-if-the-reverse-proxy-is-installed-on-the-same-host-you-should-configure-the-apache-container-to-only-listen-on-localhost).

<details>

<summary>Command for arm64 CPUs like the Raspberry Pi 4</summary>

```
# For arm64 CPUs:
sudo docker run \
--sig-proxy=false \
--name nextcloud-aio-mastercontainer \
--restart always \
--publish 8080:8080 \
-e APACHE_PORT=11000 \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest-arm64
```

</details>

On macOS see https://github.com/nextcloud/all-in-one#how-to-run-aio-on-macos.

<details>

<summary>Command for Windows</summary>

```
docker run ^
--sig-proxy=false ^
--name nextcloud-aio-mastercontainer ^
--restart always ^
--publish 8080:8080 ^
-e APACHE_PORT=11000 ^
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config ^
--volume //var/run/docker.sock:/var/run/docker.sock:ro ^
nextcloud/all-in-one:latest
```

</details>

### Inspiration for a docker-compose file

Simply translate the docker run command into a docker-compose file. You can have a look at [this file](https://github.com/nextcloud/all-in-one/blob/main/docker-compose.yml) for some inspiration but you will need to modify it either way. You can find further examples here: https://github.com/nextcloud/all-in-one/discussions/588

## 3. If the reverse proxy is installed on the same host, you should configure the apache container to only listen on localhost.

Use this envorinmental variable during the initial startup of the mastercontainer to make the apache container only listen on localhost: `-e APACHE_IP_BINDING=127.0.0.1`. **Attention:** This is only recommended to be set if you use `localhost` in your reverse proxy config to connect to your AIO instance. If you use an ip-address, you can either simply skip this step or set it to `0.0.0.0` if you are unsure what the correct value is.

## 4. Open the AIO interface.
After starting AIO, you should be able to access the AIO Interface via `https://ip.address.of.the.host:8080`. Enter your domain that you've entered in the reverse proxy config and you should be done. Please do not forget to open port `3478/TCP` and `3478/UDP` in your firewall/router for the Talk container!

## 5. Optional: get a valid certificate for the AIO interface

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

Of course you need to modify `<your-nc-domain>` to the domain on which you want to run Nextcloud. **Please note:** The above configuration will only work if your reverse proxy is running directly on the host that is running the docker daemon. If the reverse proxy is running in a docker container, you can use the `--network host` when starting the reverse proxy container in order to connect the reverse proxy container to the host network. If that is not an option for you, you can alternatively instead of `localhost` use the ip-address that is displayed after running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (the command only works on Linux)

Afterwards should the AIO interface be accessible via `https://ip.address.of.the.host:8443`. You can alternatively change the domain to a different subdomain by using `https://<your-alternative-domain>:443` instead of `https://<your-nc-domain>:8443` in the Caddyfile and use that to access the AIO interface.

## 6. How to debug things?
If something does not work, follow the steps below:
1. Make sure to exactly follow the whole reverse proxy documentation step-for-step from top to bottom!
1. Make sure that the reverse proxy is running on the host OS or if running in a container, connected to the host network. If that is not possible, substitute `localhost` in the default configurations by the ip-address that you can easily get when running the following command on the host OS: `ip a | grep "scope global" | head -1 | awk '{print $2}' | sed 's|/.*||'` (The command only works on Linux)
1. Make sure that all ports match the chosen APACHE_PORT.
1. Make sure that the mastercontainer is able to spawn other containers. You can do so by checking that the mastercontainer indeed has access to the Docker socket which might not be positioned in one of the suggested directories like `/var/run/docker.sock` but in a different directory, based on your OS and the way how you installed Docker. The mastercontainer logs should help figuring this out. You can have a look at them by running `sudo docker logs nextcloud-aio-mastercontainer` after the container is started the first time.
1. Check if after the mastercontainer was started, the reverse proxy if running inside a container, can reach the provided apache port. You can test this by running `nc -z localhost 11000; echo $?` from inside the reverse proxy container. If the output is `0`, everything works. Alternatively you can of course use instead of `localhost` the ip-address of the host here for the test.
1. Try to configure everything from scratch if it still does not work!
1. As last resort, you may disable the domain validation by adding `-e SKIP_DOMAIN_VALIDATION=true` to the docker run command. But only use this if you are completely sure that you've correctly configured everything!

