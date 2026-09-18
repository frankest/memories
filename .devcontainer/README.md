# Memories Development Container

This is a Nextcloud development container with Memories pre-installed.

After the container starts up, follow these steps:

1. Disable the built-in PHP extension of VS Code. Search for `@builtin php-language-features` in the extensions tab and disable it.
1. You can log in to Nextcloud using the following credentials:
   - Username: `admin`
   - Password: `admin`

Note: MariaDB is set up automatically (db=`nextcloud`, user=`nextcloud`, password=`nextcloud`); Adminer for graphical database management is available on port 8080.

To run OCC commands in the container, use the following command:

```bash
sudo -E -u www-data php /var/www/html/occ <command>
```

To watch changes in UI build:
    
```bash
make watch-js
```

Note: Nextcloud automatically caches app assets (including javascript) based on the version number, so you'll have to force-reload your browser window. Alternatively, to ensure caches are invalidated, you can:

1. Change the version number in `appinfo/info.xml`
2. Build the app using `make watch-js` (or `make build-js`/`make build-js-production` for a static build)
3. Inform nextcloud of the upgrade via `sudo -E -u www-data php /var/www/html/occ upgrade`
## Local WSL debugging

Open this checkout in WSL, then use **Dev Containers: Reopen in Container**.
The first start installs dependencies and initializes a separate Nextcloud.
Use http://localhost:8088 (admin / admin); Adminer is on http://localhost:8089.
These ports are bound to loopback. The Compose project is named memories_debug.

In a container terminal, run `make watch-js` and leave it running.
For PHP debugging, select **Listen for Xdebug (container)** and press F5.
Set a breakpoint in a Memories controller and request its route with
`XDEBUG_SESSION_START=memories` as a query parameter. Xdebug connects to the
debug adapter inside the same container; port 9003 is not published.

PHP tests use `make php-test`; type checking uses `make js-lint`.
The default credentials are for this local development instance only.
