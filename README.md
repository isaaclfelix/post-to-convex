# post-to-convex

Contributors: see [CONTRIBUTING.md](./CONTRIBUTING.md) for Git branch naming, [Commitlint](https://github.com/conventional-changelog/commitlint) (conventional commits), and Husky hooks.

Local WordPress runs in Docker (MySQL 8 + PHP 8.2 Apache) with the repo root bind-mounted into the container so file changes apply immediately.

## Prerequisites

-   **Ubuntu on WSL 2** with the **Docker Engine** and **Docker Compose plugin** installed inside that distro (see [WSL and Docker setup](#wsl-and-docker-setup-without-docker-desktop) below). Do **not** use Docker Desktop for this project.
-   **Windows PHP 8.2** on `PATH` for editor PHPCS/PHPCBF (`php -v` in PowerShell should report 8.2.x). New contributors: [PHP 8.2 for Windows](https://windows.php.net/download/) or `winget install -e --id PHP.PHP.8.2`.
-   Recommended **Cursor/VS Code extensions** (install manually from workspace prompts): [PHP Sniffer](https://marketplace.visualstudio.com/items?itemName=wongjn.php-sniffer), [PHP Debug](https://marketplace.visualstudio.com/items?itemName=xdebug.php-debug) by Xdebug, and optionally Intelephense.
-   Optional: `git` in WSL if you work from a clone.

## WSL and Docker setup (without Docker Desktop)

This project expects you to run `docker` and `docker compose` from an **Ubuntu WSL 2** shell with the Docker daemon running **inside that distro**.

We do **not** recommend [Docker Desktop for Windows](https://docs.docker.com/desktop/setup/install/windows-install/). In practice it is often slower (especially with bind mounts from `/mnt/c` or OneDrive), uses more RAM and CPU in the background, and its WSL integration, file sharing, and resource limits are easy to misconfigure. Installing the Docker CLI and Engine directly in Ubuntu WSL is simpler, faster for day-to-day development, and matches how this README is written.

### 1. Enable Windows optional features

Run **PowerShell as Administrator** and enable the components WSL 2 needs. A reboot may be required before the next step.

```powershell
dism.exe /online /enable-feature /featurename:Microsoft-Windows-Subsystem-Linux /all /norestart
dism.exe /online /enable-feature /featurename:VirtualMachinePlatform /all /norestart
```

Restart Windows, then set WSL 2 as the default:

```powershell
wsl --set-default-version 2
```

On Windows 11 (or recent Windows 10 builds), you can also install WSL and these features in one step:

```powershell
wsl --install
```

That command enables the required features, installs the WSL 2 kernel, and can install Ubuntu. If you already have WSL, skip to installing or selecting Ubuntu below.

### 2. Install Ubuntu on WSL

**Command line (Administrator PowerShell):**

```powershell
wsl --install -d Ubuntu
```

If Ubuntu is already listed but not default:

```powershell
wsl -l -v
wsl --set-default Ubuntu
```

Open **Ubuntu** from the Start menu and finish the initial username/password prompt.

### 3. Enable systemd in WSL (required for Docker Engine)

The Docker daemon is managed by **systemd** on Linux. In your Ubuntu WSL shell:

```bash
sudo tee /etc/wsl.conf > /dev/null <<'EOF'
[boot]
systemd=true
EOF
```

From **PowerShell** (any window), shut down WSL so the change applies:

```powershell
wsl --shutdown
```

Open **Ubuntu** again and confirm systemd is running:

```bash
systemctl is-system-running
```

You should see `running` or `degraded` (either is fine for local development).

### 4. Install Docker CLI and Engine in Ubuntu WSL

Follow the [official Docker Engine install for Ubuntu](https://docs.docker.com/engine/install/ubuntu/) inside your WSL distro. Summary:

```bash
sudo apt-get update
sudo apt-get install -y ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
sudo apt-get install -y docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

Add your user to the `docker` group so you can run Docker without `sudo`:

```bash
sudo usermod -aG docker $USER
```

Close the Ubuntu terminal, open a new one (or run `newgrp docker`), then verify:

```bash
docker --version
docker compose version
docker run --rm hello-world
```

If `docker run` fails with a permission error, log out of WSL completely (`wsl --shutdown` from PowerShell, then reopen Ubuntu) and try again.

### 5. Optional: start Docker on login

Docker Engine usually starts with systemd. If the daemon is not running:

```bash
sudo systemctl enable --now docker
```

## Windows and WSL

Almost every command in this document should be run from an **Ubuntu WSL shell**, not from PowerShell or CMD.

If the project lives on the Windows drive (for example under `C:\Users\...`), open it from WSL via `/mnt/c/Users/...`. Paths with spaces must be quoted when you `cd`, for example:

```bash
cd "/mnt/c/Users/Your Name/projects/post-to-convex"
```

Bind mounts from `/mnt/c` (including OneDrive folders) can be slower and sometimes fussier with file watchers than a tree on the Linux side. If you hit slowness or odd I/O behavior, consider cloning or copying the repo under your Linux home directory (for example `~/code/post-to-convex`) and running Docker from there.

## First-time setup

1. Copy the example environment file and edit secrets:

    ```bash
    cp .env.example .env
    ```

2. Edit `.env` and set at least `MYSQL_PASSWORD` and `MYSQL_ROOT_PASSWORD` to strong values. Optionally change `WP_PORT` (host port for WordPress) or `WORDPRESS_TABLE_PREFIX`. Keep `MYSQL_*` values consistent with what WordPress expects; they are wired in `docker-compose.yml`. See comments in `.env.example` for details.

Do not commit `.env`; it is listed in `.gitignore`.

## Run and stop

From the repository root (in Ubuntu WSL):

```bash
docker compose up -d --build
```

The first run builds the WordPress image from the `Dockerfile` (WordPress PHP 8.2 Apache, plus Composer and WP-CLI). MySQL starts first; WordPress waits until the database is healthy.

Follow logs:

```bash
docker compose logs -f wordpress
# or
docker compose logs -f db
```

Stop containers:

```bash
docker compose down
```

Data in the named volume `db_data` survives `docker compose down`. To remove the database volume as well, use `docker compose down -v` (this deletes MySQL data for this project).

## Use the site

Open `http://localhost:<WP_PORT>` in a browser. With the default in `.env.example`, that is [http://localhost:8080](http://localhost:8080).

On first visit, complete the normal WordPress installation unless you already have a configured `wp-config.php` from a previous run.

## PHP coding standards

WordPress **6.9.4** on **PHP 8.2** (Docker image and Windows PHP for the editor). PHPCS/PHPCBF use [WordPress Coding Standards](https://github.com/WordPress-Coding-Standards/WordPress-Coding-Standards) from the **repository root** (repo-wide scan; plugin prefix/i18n rules apply only under `wp-content/plugins/post-to-convex/`).

1. **Install tooling** (from repo root in WSL, with containers running):

    ```bash
    docker compose up -d --build
    docker compose exec -u root -w /var/www/html wordpress composer install
    ```

    **Composer runs only inside the WordPress container** (it is included in the [`Dockerfile`](Dockerfile)). Do not install Composer on Windows. Use **`-u root`** so Composer can write `vendor/` on the bind-mounted repo (the container process otherwise runs as `www-data`). On Windows, PHP Sniffer uses `phpSniffer.executablesFolder: "bin"` — it invokes `phpcs` / `phpcbf` in that folder and Windows resolves [`bin/phpcs.bat`](bin/phpcs.bat) and [`bin/phpcbf.bat`](bin/phpcbf.bat), which call PHP 8.2 to run the Linux-installed `vendor/bin` scripts.

    Plugin `composer install` under `wp-content/plugins/post-to-convex/` is still only for **PHPUnit** (also via Docker, as root — see [Running unit tests](#running-unit-tests)).

2. **Lint or fix from WSL**:

    ```bash
    chmod +x bin/php-lint.sh   # first time only
    ./bin/php-lint.sh
    ./bin/php-lint.sh --fix
    ```

    Or inside the container: `composer run lint:php` / `composer run lint:php:fix` with `-w /var/www/html`.

3. **Editor (Windows)**: With PHP 8.2 on `PATH` and **PHP Sniffer** installed (no Windows Composer), open a `.php` file — diagnostics and format-on-save use `.vscode/settings.json`, `bin/phpcs.bat` / `bin/phpcbf.bat`, and root `vendor/` from step 1 in Docker.

Configuration: [`.phpcs.xml.dist`](.phpcs.xml.dist). Local overrides: copy to `phpcs.xml` (gitignored).

**Note:** Do not use a `/wp-*.php` exclude in PHPCS on Windows — it matches every path under `wp-content\…\.php` and silently skips your plugin. This repo lists root WordPress PHP entrypoints explicitly instead.

## Xdebug debugging

Xdebug runs in the **WordPress container** (PHP 8.2), not on Windows PHP. The IDE uses **PHP Debug** (`xdebug.php-debug`) to listen on port **9003**.

1. Rebuild after pulling Dockerfile changes:

    ```bash
    docker compose up -d --build
    ```

2. Verify Xdebug in the container:

    ```bash
    docker compose exec wordpress php -i | grep xdebug.mode
    ```

    Expect `xdebug.mode => debug`.

3. In Cursor/VS Code: start **Listen for Xdebug** (`.vscode/launch.json`).

4. Trigger a request: [Xdebug browser extension](https://xdebug.org/docs/step_debug#browser-extensions), or append `?XDEBUG_TRIGGER=1` to the site URL.

5. Set breakpoints under `wp-content/plugins/post-to-convex/`. Paths map `/var/www/html` ↔ workspace root.

Settings are baked into the image via `docker/php/conf.d/xdebug.ini` (`COPY` in the `Dockerfile`, `start_with_request=trigger`).

## Running unit tests

The **post-to-convex** plugin uses PHPUnit with the standard WordPress test harness. Run everything below from a shell inside the WordPress container (paths assume the repo root is mounted at `/var/www/html` as in `docker-compose.yml`).

1. **Start the stack** — From the repository root (for example in Ubuntu WSL), ensure containers are up:

    ```bash
    docker compose up -d
    ```

2. **Open a root shell in the WordPress container** — The test installer may need root to write under `/tmp` and to install dependencies:

    ```bash
    docker exec -u root -it wp bash
    ```

3. **Go to the plugin directory:**

    ```bash
    cd wp-content/plugins/post-to-convex/
    ```

4. **Install PHP dev dependencies:**

    ```bash
    composer install
    ```

5. **Install the WordPress test library and create the test database** — Arguments are: `DB_NAME` `DB_USER` `DB_PASSWORD` `DB_HOST` `WP_VERSION` `SKIP_DB_CREATE`. These match the Compose service `db` and typical credentials from `.env` (adjust if yours differ). Re-run this step when you change WordPress version or database settings.

    ```bash
    TMPDIR=/var/www/html/wp-content/plugins/post-to-convex/tmp ./bin/install-wp-tests.sh wordpress wordpress wordpress db 6.9.4 true
    ```

6. **Run PHPUnit:**

    ```bash
    composer run test
    ```

For more verbose output you can use `composer run test:verbose`.

## Troubleshooting

-   **Port already in use** — Set a different `WP_PORT` in `.env`, then `docker compose up -d` again.
-   **`docker: command not found` in WSL** — Install Docker Engine in Ubuntu per [WSL and Docker setup](#wsl-and-docker-setup-without-docker-desktop). Confirm you are in the `docker` group (`groups` should list `docker`).
-   **`Cannot connect to the Docker daemon`** — Run `sudo systemctl start docker` in Ubuntu, or ensure `systemd=true` in `/etc/wsl.conf` and run `wsl --shutdown` from PowerShell before reopening Ubuntu.
-   **Slow edits or odd file behavior** — Prefer the project on the WSL Linux filesystem instead of only `/mnt/c`/OneDrive; see [Windows and WSL](#windows-and-wsl).

Plugin development tooling: [PHP coding standards](#php-coding-standards), [Xdebug debugging](#xdebug-debugging), and [Running unit tests](#running-unit-tests).
