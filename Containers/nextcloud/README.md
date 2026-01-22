# Nextcloud All-in-One ``nextcloud`` Container

This folder contains the OCI/Docker container definition, along with associated resources and configuration files, for building the `nextcloud` container as part of the [Nextcloud All-in-One](https://github.com/nextcloud/all-in-one) project. This container hosts PHP and the Nextcloud Server application.

## Overview

The Nextcloud container provides the core Nextcloud application environment, including the necessary dependencies and configuration for seamless integration into the All-in-One stack. The container hosts:

- The PHP SAPI/backend (php-fpm)
- Nextcloud background jobs and scheduled tasks, which are handled via cron
- Miscellaneous minor support services specific to AIO's Nextcloud deployment (health and exec)

## Contents

- **Dockerfile**: Instructions for building the Nextcloud container image.
- **Entrypoint script**: The `start.sh` script is used for container initialization and runtime configuration before starting supervisord.
- **Nextcloud configuration files**: Specific to running in a containerized setting and/or within AIO.
- **Supervisor**: The `supervisord.conf` file defines the long-running services hosted within the container (php-fpm, cron, etc.).

## Usage

This container is intended to be used as part of the All-in-One deployment and is not meant to be used on its own. Among other requirements, it needs a web server container (which AIO provides in a dedicated Apache container). It is designed to be orchestrated by the [All-in-One mastercontainer](https://github.com/nextcloud/all-in-one/tree/main/Containers/mastercontainer) or used within an [AIO Manual Installation](https://github.com/nextcloud/all-in-one/tree/main/manual-install) or [AIO Helm chart](https://github.com/nextcloud/all-in-one/tree/main/nextcloud-aio-helm-chart).

## Documentation

- [Nextcloud All-in-One Documentation](https://github.com/nextcloud/all-in-one#readme)
- [Nextcloud Documentation](https://docs.nextcloud.com/)

## Contributing

Contributions are welcome! Please follow the Nextcloud project's guidelines and submit pull requests or issues via the main repository.

## License

This folder and its contents are licensed under the [GNU AGPLv3](https://www.gnu.org/licenses/agpl-3.0.html), in line with the rest of Nextcloud All-in-One.
