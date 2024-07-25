# PHP Docker Controller

This is the code for the PHP Docker controller.

## How to run

Running this locally requires Docker Engine on the same machine. 
If this is the case, just execute the following command:

```bash
composer install
sudo SKIP_DOMAIN_VALIDATION=true composer run dev # sudo is required to access docker socket
```

You can then access the web interface at `localhost:8080`.

If you have an error that says `Couldn't connect to server for http://127.0.0.1/v1.41/networks/create` makes sure a `nextcloud-aio-mastercontainer` is running.
You can start it with the following command:

```bash
sudo docker run \
--rm \
--name nextcloud-aio-mastercontainer \
--volume nextcloud_aio_mastercontainer:/mnt/docker-aio-config \
--volume /var/run/docker.sock:/var/run/docker.sock:ro \
nextcloud/all-in-one:latest
```

## Commands

| Command                                 | Description                            |
|-----------------------------------------|----------------------------------------|
| `composer run dev`                      | Starts the development server          |
| `composer run psalm`                    | Run Psalm static analysis              |
| `composer run psalm:update-baseline`    | Run Psalm with `--update-baseline` arg |
| `composer run lint`                     | Run PHP Syntax check                   |
| `composer run lint:twig`                | Run Twig Syntax check                  |
| `composer run php-deprecation-detector` | Run PHP Deprecation Detector           |


