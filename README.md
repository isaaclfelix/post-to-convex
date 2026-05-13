# post-to-convex

Local WordPress runs in Docker (MySQL 8 + PHP 8.2 Apache) with the repo root bind-mounted into the container so file changes apply immediately.

## Prerequisites

- [Docker Desktop for Windows](https://docs.docker.com/desktop/setup/install/windows-install/) using the **WSL 2** backend.
- In Docker Desktop: **Settings → Resources → WSL integration** — enable integration for your **Ubuntu** distro.
- An **Ubuntu** WSL distribution where you will run `docker` and `docker compose` (this README assumes that workflow).
- Optional: `git` in WSL if you work from a clone.

## Windows and WSL

Run every command in this document from an **Ubuntu WSL shell**, not from PowerShell or CMD, unless you have deliberately pointed the Docker CLI elsewhere.

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

## Troubleshooting

- **Port already in use** — Set a different `WP_PORT` in `.env`, then `docker compose up -d` again.
- **`docker: command not found` in WSL** — Enable WSL integration for your Ubuntu distro in Docker Desktop, or install the Docker CLI in that distro per Docker’s docs.
- **Slow edits or odd file behavior** — Prefer the project on the WSL Linux filesystem instead of only `/mnt/c`/OneDrive; see [Windows and WSL](#windows-and-wsl).

Additional plugin or theme tooling (Composer, Node, and so on) may add steps later; they are not part of the Compose stack in this repository yet.
