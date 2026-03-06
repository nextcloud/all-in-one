## Prometheus Nextcloud Exporter

A Prometheus exporter that collects metrics from your Nextcloud instance for monitoring and alerting.

### How to install

See the [Community Containers documentation](https://github.com/nextcloud/all-in-one/tree/main/community-containers#community-containers) for instructions on how to install this in your Nextcloud All-in-One setup.

### Security & Access

**Important:** This container is configured to bind only to `127.0.0.1` (localhost) for security reasons. Prometheus exporters typically don't include authentication, so direct network exposure is not recommended.

#### Access Options

1. **With Caddy Container (Recommended)**: If you also install the [Caddy community container](https://github.com/nextcloud/all-in-one/tree/main/community-containers/caddy), it will automatically configure secure HTTPS access to your metrics with authentication at `metrics.your-domain.com`

   **Getting Authentication Credentials**: 
   - **Username**: Always `metrics`
   - **Password**: After deploying the nextcloud-exporter container, the automatically generated password will be displayed in the AIO interface. Look for it in the container section below the container name "Prometheus Nextcloud Exporter". 

2. **Custom Reverse Proxy**: Set up your own reverse proxy (nginx, Apache, etc.) to provide HTTPS and authentication. See configuration guides:
   - [NGINX Authentication](https://nginx.org/en/docs/http/ngx_http_auth_basic_module.html) + [Reverse Proxy](https://docs.nginx.com/nginx/admin-guide/web-server/reverse-proxy/)
   - [Apache Authentication](https://httpd.apache.org/docs/2.4/howto/auth.html) + [Reverse Proxy](https://httpd.apache.org/docs/2.4/mod/mod_proxy.html)
   - [Traefik BasicAuth](https://doc.traefik.io/traefik/middlewares/http/basicauth/)
   - [Prometheus Security Best Practices](https://prometheus.io/docs/operating/security/)

3. **Direct Local Access**: Access metrics directly from the server at `http://127.0.0.1:9205/metrics` (no authentication)

### What it monitors
- User activity (active users hourly, daily)
- File counts and storage usage
- System health and database size
- App statistics and update availability
- Nextcloud performance metrics

### Prometheus Configuration

For **local server access** (if Prometheus runs on the same server):
```yaml
scrape_configs:
  - job_name: 'nextcloud'
    scrape_interval: 90s
    static_configs:
      - targets: ['127.0.0.1:9205']
    metrics_path: /metrics
    scheme: http
```

For **Caddy integration** (secure external access):
```yaml
scrape_configs:
  - job_name: 'nextcloud'
    scrape_interval: 90s
    static_configs:
      - targets: ['metrics.your-domain.com']
    metrics_path: /
    scheme: https
    basic_auth:
      username: 'metrics'
      password: 'your-generated-password'
```

### Visualization

Compatible with Grafana for creating monitoring dashboards:
- Pre-built dashboard available: [Grafana Dashboard #20716](https://grafana.com/grafana/dashboards/20716-nextcloud/)

### Repository
https://github.com/xperimental/nextcloud-exporter

### Maintainer
https://github.com/grotax
