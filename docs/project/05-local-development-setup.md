# 05 — Running the Application on Your Own Computer (Fedora + Docker)

This gets QloApps running locally on your Fedora machine, in a container that matches
production versions exactly: **PHP 8.4** (live server runs 8.4.27) and **MariaDB 11.4**
(live server runs 11.4.13). Matching versions matters — behaviour differences between
MariaDB 10.4 (what most desktop bundles ship) and 11.4 are exactly the kind of thing that
makes a bug appear locally but not in production, or vice versa.

Nothing here touches the live hotel system. The containers talk only to each other and to
`localhost` on your machine.

---

## 1. What you will end up with

| Thing | Value |
| --- | --- |
| Storefront | http://localhost:8080 |
| Back office (admin) | http://localhost:8080/admin-dev/ |
| PHP | 8.4 with `gd, pdo_mysql, soap, zip, curl, openssl, simplexml, dom, phar` (+ `exif`, `mbstring`, `opcache`) |
| Database server | MariaDB 11.4, reachable from your machine on `127.0.0.1:3307` |
| Database A — `qloapps_fresh` | clean install, sample data, safe to break and rebuild |
| Database B — `qloapps_live` | your real production data, imported read-mostly |
| Table prefix | `qlo_` — identical to production |

Two databases on purpose. You asked for a clean install first and your real data second, and
keeping them side by side means you can always compare "what the code does with no data"
against "what it does with 1,934 real bookings". Switching between them is one command (§7).

---

## 2. Before you start — container tooling on Fedora

**Check what you already have before installing anything.** Fedora ships several overlapping
packages, and most of the time one small piece is missing rather than the whole stack.

### 2.1 What is already installed?

```bash
echo "== docker CLI =="; command -v docker && docker --version
echo "== compose plugin =="; docker compose version 2>&1 | head -2
echo "== plugin dir =="; ls /usr/libexec/docker/cli-plugins/ ~/.docker/cli-plugins/ 2>/dev/null
echo "== daemon reachable? =="; docker info >/dev/null 2>&1 && echo "yes" || echo "NO"
echo "== podman =="; command -v podman && podman --version
echo "== podman-compose =="; command -v podman-compose && podman-compose --version 2>&1 | head -1
echo "== dnf version =="; dnf --version | head -1
```

Two things decide which option below you need: whether `docker compose version` works, and
whether the daemon is reachable. Note that **`dnf --version` matters**: Fedora 41 and later
ship **dnf5**, whose `config-manager` syntax differs from dnf4 — see §2.3.

A very common Fedora state is: a working Docker CLI from Fedora's own repositories
(`Docker version 29.x, build N.fcNN`) with `docker-buildx` present but **no compose plugin**.
Fedora does not package `docker-compose-plugin`, because that is a Docker CE repository
package name. That is Option A.

### 2.2 Option A — add the compose plugin to the Docker you already have (fastest)

If `docker info` says `yes` but `docker compose version` fails, this is all you need:

```bash
mkdir -p ~/.docker/cli-plugins
curl -SL https://github.com/docker/compose/releases/latest/download/docker-compose-linux-x86_64 \
     -o ~/.docker/cli-plugins/docker-compose
chmod +x ~/.docker/cli-plugins/docker-compose
docker compose version
```

On an ARM machine substitute `docker-compose-linux-aarch64`. No root, no new repositories,
and nothing conflicts with what is already installed.

Fedora may also package a standalone Compose; worth trying first since it is one command:

```bash
sudo dnf -y install docker-compose
docker-compose version      # note the HYPHEN - this is the standalone binary
```

If that installs the standalone binary only, use **`docker-compose`** (hyphen) instead of
**`docker compose`** (space) in every command in this guide. They are equivalent for our
purposes. To get the space form as well, symlink it into the plugin directory:

```bash
mkdir -p ~/.docker/cli-plugins
ln -s "$(command -v docker-compose)" ~/.docker/cli-plugins/docker-compose
docker compose version
```

**If `docker info` said NO**, the CLI is installed but no daemon is. Fedora's daemon package
is `moby-engine`:

```bash
sudo dnf -y install moby-engine
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
# log out and back in, then:
docker info >/dev/null && echo "daemon OK"
```

### 2.3 Option B — install Docker CE from Docker's own repository

Only needed if you want Docker's official build rather than Fedora's. **Use the syntax that
matches your dnf version** — this is where the instructions most often go wrong on Fedora 41+.

**dnf5 (Fedora 41 and later):**

```bash
sudo dnf -y install dnf-plugins-core
sudo dnf config-manager addrepo --from-repofile=https://download.docker.com/linux/fedora/docker-ce.repo
sudo dnf -y install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
sudo systemctl enable --now docker
sudo usermod -aG docker "$USER"
```

**dnf4 (Fedora 40 and earlier):**

```bash
sudo dnf config-manager --add-repo https://download.docker.com/linux/fedora/docker-ce.repo
```

with the remaining lines identical. The old `--add-repo` flag **does not exist in dnf5** and
fails with `Unknown argument "--add-repo" for command "config-manager"`.

If Docker has not yet published packages for your Fedora release, the repository resolves
`$releasever` to a version that does not exist and `dnf` reports
`No match for argument: docker-ce`. Either wait, pin an older release in the repo file, or
use Option A or C — which is usually easier than fighting the repository.

Before installing Docker CE, remove anything that would own `/usr/bin/docker` first:

```bash
sudo dnf -y remove docker docker-client docker-client-latest docker-common \
                   docker-latest docker-engine moby-engine podman-docker
```

Then **log out and back in** — the `docker` group membership only applies to new sessions.

### 2.4 Option C — use Podman, which Fedora already gives you

If `podman` and `podman-compose` are both present, you can skip every install step above:

```bash
podman-compose up -d --build
```

and use `podman-compose` (or `podman compose`) wherever this guide says `docker compose`.
The compose file needs no edits.

This project is built to work this way:

- `dev/docker/entrypoint.sh` waits for MariaDB itself, so the compose file uses the short
  `depends_on: [db]` form rather than `condition: service_healthy`, which `podman-compose`
  has supported inconsistently across versions.
- The same entrypoint works out which uid/gid Apache should run as by statting the bind mount,
  so it never has to chown your repository. Under rootless Podman the naive
  `chown -R www-data` would hand `modules/` and `img/` to a host **subuid** such as 100032 and
  you would no longer be able to edit your own source files. It prints what it decided on every
  start; see §10.3.

Missing podman-compose?

```bash
sudo dnf -y install podman podman-compose
```

**Which to choose?** Option A if you already have a working Docker daemon — it is one command
and keeps the ownership model simple. Option C if you would rather not install anything and
are comfortable reading the entrypoint output. Both are supported; the guide's commands are
identical apart from the binary name.

---

## 3. Step 1 — get the code

```bash
cd ~
git clone -b arena/07df51c3-lushviewhotel-2-0 \
    https://github.com/iwani1/lushviewhotel-2.0.git
cd lushviewhotel-2.0
```

That branch holds the QloApps 1.8.0 source tree plus all the project documentation and the
local development tooling described here. Confirm the tooling arrived:

```bash
ls docker-compose.yml dev/docker/ dev/scripts/ dev/migrations/
```

You should see `Dockerfile`, `php.ini`, `entrypoint.sh` under `dev/docker/`; `use-db.sh`,
`import-live-dump.sh`, `set-admin-password.php` under `dev/scripts/`; and the migration pair
under `dev/migrations/`.

---

## 4. Step 2 — build and start

```bash
docker compose up -d --build
```

The first build downloads PHP 8.4, MariaDB 11.4 and compiles four PHP extensions — expect
several minutes. Subsequent starts are seconds.

Watch it come up:

```bash
docker compose ps
docker compose logs -f app        # Ctrl-C to stop following
```

Wait until `qlo-db` reports `healthy`. The app container is configured to wait for that
before starting, so if `docker compose ps` shows both as running, the database is ready.

---

## 5. Step 3 — install (recommended: one command)

QloApps ships a command-line installer, which is more reliable than the web wizard because
every setting is explicit and repeatable. **Copy your existing `config/settings.inc.php`
nowhere — there isn't one yet; the installer creates it.**

```bash
docker compose exec app php install-dev/index_cli.php \
  --step=all \
  --language=en \
  --timezone=Africa/Accra \
  --domain=localhost:8080 \
  --base_uri=/ \
  --db_server=db \
  --db_user=qlo \
  --db_password=qlo_dev_only \
  --db_name=qloapps_fresh \
  --db_clear=0 \
  --db_create=0 \
  --prefix=qlo_ \
  --engine=InnoDB \
  --name="Lush View Hotel" \
  --country=gh \
  --firstname=Admin \
  --lastname=LushView \
  --password='ChangeMe-Local-123' \
  --email=dev@localhost.test \
  --newsletter=0 \
  --license=1
```

Expect `-- Installation successfull! --` (the misspelling is upstream's).

### Why each flag is set that way

Every flag below was checked against `install-dev/classes/datas.php`, which defines the
complete argument list and its defaults.

| Flag | Why |
| --- | --- |
| `--db_server=db` | Inside the compose network the database host is the service name `db`. **`localhost` will not work** — that is the single most common failure. |
| `--db_clear=0` | The default is **`1`, which means "drop existing tables"**. Set to `0` so a typo can never destroy data. Keep it at 0 always. |
| `--db_create=0` | Compose already created `qloapps_fresh` via `MARIADB_DATABASE`. |
| `--prefix=qlo_` | Matches production exactly, so SQL you write locally runs unchanged against the live data later. |
| `--timezone=Africa/Accra` | The default is `Europe/Paris`. Production is already `Africa/Accra`; local should match or every date you test is shifted. |
| `--domain=localhost:8080` | The port is required. `controllerConsole.php:106` puts this value into `$_SERVER['HTTP_HOST']`, and `install.php:481-482` stores it as `PS_SHOP_DOMAIN`. `Tools::getHttpHost()` keeps the port, so generated URLs point back at 8080. Omit it and every link in the app points at port 80. |
| `--country=gh` | `Country::getByIso()` uppercases its input (`classes/Country.php:172`), so `gh` matches the seed row `iso_code="GH"`, `call_prefix="233"`. This also loads the **Ghana localization pack** — `install.php:417` first tries `api.qloapps.com`, and when that fails it falls back to `localization/gh.xml` (`install.php:422-425`), which is the pack written for this project: GHS plus the Act 1151 taxes (VAT 15%, NHIL 2.5%, GETFund 2.5%) combined additively, not compounded. Installing locally is how you verify that pack. |
| `--newsletter=0` | Default is `1`, which subscribes `dev@localhost.test` to QloApps marketing. |
| `--license=1` | Accepts the OSL 3.0 licence; without it the CLI installer stops and asks. |

### Alternative: the web installer

If you prefer clicking, open **http://localhost:8080/install-dev/** and work through the
wizard. Use exactly the same values as the table above — particularly `db` as the server
name, `qlo_` as the prefix, and `localhost:8080` as the domain.

The requirements screen should be entirely green. It checks PHP 8.2–8.5, the seven required
extensions, `memory_limit ≥ 128M`, `max_execution_time ≥ 500`, `upload_max_filesize ≥ 16M`,
`allow_url_fopen = On`, and recursive write permission on twelve directories
(`install-dev/controllers/http/system.php`). `dev/docker/php.ini` exceeds every numeric
minimum and `dev/docker/entrypoint.sh` fixes ownership on all twelve paths, so nothing
should fail. If something does, see §10.

---

## 6. Step 4 — log in and check it works

Back office: **http://localhost:8080/admin-dev/**
Email: `dev@localhost.test`  Password: `ChangeMe-Local-123`

Storefront: **http://localhost:8080**

Run through this checklist — it confirms the things the project depends on:

1. **Back office loads** and shows the QloApps dashboard.
2. **Preferences → General** shows shop name `Lush View Hotel`.
3. **Localization → Localization** shows country **Ghana**, currency **GHS (₵)**,
   timezone **Africa/Accra**.
4. **Localization → Taxes** shows the five Ghana tax rules from `localization/gh.xml`.
5. **Modules** lists the hotel modules, including `hotelreservationsystem`.
6. Create a **room type** and a **room**, then make a test booking end to end:
   book → confirm → pay → check in → check out. This is the first of the four workflows the
   project brief requires you to verify (§10 of the brief).

---

## 7. Step 5 — load your real production data

Only do this once the fresh install works. Real data on a laptop is a data-protection
responsibility under Ghana's Data Protection Act 2012 (Act 843): 1,257 guest records and
2,058 payment rows. Keep the machine encrypted and never commit the dump.

### 7.1 Get the dump onto your machine

Export it fresh from cPanel → phpMyAdmin → `lushview_qloapp` → **Export** → **Custom** →
select all tables → format **SQL** → go. Save it somewhere **outside** the repository, for
example `~/backups/live_db.sql`.

Exporting fresh is better than reusing an old copy, and you should keep this export as your
own offline backup regardless of what happens with the GitHub repository.

Verify it is the same shape as the one this project analysed:

```bash
grep -c '^CREATE TABLE' ~/backups/live_db.sql     # expect 300
sha256sum ~/backups/live_db.sql                   # an old copy of the dump we analysed was
                                                  # 5fc7f3ffaf53fe2682f17de52c287047acc3f37e7c568ab5e0dff1f36e299edd
```

A different hash just means the live database has changed since — that is expected and fine.

### 7.2 Import it

```bash
dev/scripts/import-live-dump.sh ~/backups/live_db.sql
```

The script validates the file before writing anything, then creates `qloapps_live`,
imports into it, and applies migration 001 (§9). It is hardcoded to `qloapps_live` and to
the local `db` service — it has no host parameter, so it **cannot** be aimed at production.
`qloapps_fresh` is never touched.

### 7.3 Switch the app to the real data

```bash
dev/scripts/use-db.sh live
```

Switch back at any time with `dev/scripts/use-db.sh fresh`. Run it with no argument to see
which one is active. The script rewrites `_DB_NAME_` in `config/settings.inc.php` and clears
`cache/class_index.php` plus the compiled Smarty templates — clearing is not optional,
because stale cache makes the switch look like it did nothing.

---

## 8. Everyday commands

> **If you are on Podman (the Fedora path from §2.4), read this once.** Every command in this
> document written as `docker compose exec …` becomes `podman-compose exec …`, and
> `docker compose up|down|stop|logs` becomes `podman-compose up|down|stop|logs`. Everything
> else — arguments, service names (`app`, `db`), paths — is identical. So
> `docker compose exec app php …` is `podman-compose exec app php …` on your machine.
> The helper scripts in `dev/scripts/` detect the engine themselves and need no substitution.

```bash
docker compose up -d                # start
docker compose stop                 # stop, keep everything
docker compose down                 # remove containers, KEEP the database
docker compose down -v              # remove containers AND DELETE all data
docker compose logs -f app          # Apache + PHP errors
docker compose logs -f db           # MariaDB
docker compose restart app          # after changing php.ini or the Dockerfile
docker compose exec app bash        # a shell inside the container
docker compose exec db mariadb -uqlo -pqlo_dev_only qloapps_fresh   # SQL prompt
```

Your code edits are live immediately — the repository is bind-mounted, so saving a file on
your machine changes what the container serves. Two exceptions:

- **PHP changes** are picked up on the next request (opcache is set to revalidate every
  request in `dev/docker/php.ini`).
- **Template (`.tpl`) changes** need the Smarty cache cleared. Do it inside the container and
  preserve the `index.php` guards; under rootless Podman a host-side `rm` cannot delete the
  subtrees Apache created — see §10.3.2:
  ```bash
  podman-compose exec app sh -c 'find /var/www/html/cache/smarty/compile /var/www/html/cache/smarty/cache -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'
  ```
- **Adding a class or an override** needs the class index rebuilt:
  ```bash
  podman-compose exec app rm -f /var/www/html/cache/class_index.php
  ```

To connect a database GUI (DBeaver, DataGrip) from your host: host `127.0.0.1`, port `3307`,
user `qlo`, password `qlo_dev_only`.

---

## 9. The password problem — read this before you try to log in to real data

**You cannot log in with your real staff passwords on the imported data.** This is not a bug
and not something you did wrong.

QloApps' legacy password scheme is `md5(_COOKIE_KEY_ . $password)`. `_COOKIE_KEY_` is a
random per-installation salt stored in `config/settings.inc.php` on the live server. That
file was never exported (it is excluded from the repository and from database dumps), and
your local install generated a **different** random key. The imported hashes are therefore
salted with a key nobody has. They are intact, just unverifiable here.

Fix it by setting a password you know:

```bash
# see which staff accounts exist in the imported data
docker compose exec app php dev/scripts/set-admin-password.php --list

# set a known password for one of them
docker compose exec app php dev/scripts/set-admin-password.php someone@lushviewhotel.net 'LocalDev-2026!'
```

The script reads your local credentials and cookie key from `config/settings.inc.php`, checks
the **actual width** of the `passwd` column before choosing a scheme, writes the hash, then
reads it back and verifies it. It refuses to run unless the database host is local.

Add `--customer` to reset a storefront customer account instead of a staff account.

### Why migration 001 is applied during import

`import-live-dump.sh` applies `dev/migrations/001_widen_password_columns.up.sql`
automatically. This is the first real finding of the local setup work, and it is a **blocker
for the eventual upgrade**, not just a local convenience:

- The live 1.7.0 schema stores `qlo_employee.passwd` and `qlo_customer.passwd` as
  **`varchar(32)`** — exactly an MD5 digest.
- QloApps 1.8.0 hashes with **bcrypt as its primary method**
  (`classes/PasswordHashing.php`, `password_hash($passwd, PASSWORD_BCRYPT)`), and a bcrypt
  digest is **60 characters**. That is why 1.8.0's own install schema declares
  `passwd varchar(60)` (`install-dev/data/db_structure.sql:605` and `:791`).
- On every successful login, 1.8.0 re-hashes and writes the new value back
  (`classes/Employee.php:303-305`, `classes/Customer.php:413-415`).
- **Nothing in this repository widens the column.** `install-dev/upgrade/sql/` stops at
  `1.6.1.0.sql` and `modules/hotelreservationsystem/upgrade/` stops at `upgrade-1.6.0.php`,
  so there is no 1.7.0 → 1.8.0 migration at all.

Writing 60 characters into a `varchar(32)` column fails. How it fails depends on `sql_mode`:

| Server mode | What happens |
| --- | --- |
| **STRICT** (MariaDB's default since 10.2) | The UPDATE is rejected with error 1406. Because `classes/db/DbPDO.php:69` builds the PDO handle **without `PDO::ATTR_ERRMODE`**, the error is silent — login still works, but the password stays MD5 and the automatic upgrade to bcrypt **never happens**. |
| **NON-STRICT** | The value is **truncated to 32 characters**. It still looks like bcrypt, so `isPrimaryHash()` believes the account is already migrated, but `password_verify()` can never match it. The account is **permanently locked out** and the original MD5 hash is destroyed. |

So: widen the columns **before** 1.8.0 code touches the data. Widening is non-destructive —
every existing 32-character MD5 hash still fits in `varchar(60)` and stays valid, because
`PasswordHashing::validateHash()` tries every supported method and re-hashes on next login.

> **Correction to earlier project documentation.** `docs/audit/full-application-audit.md` §5
> and `TASKS.md` row A20 stated that the 1.8.0 upgrade migrates passwords to bcrypt
> automatically as each user logs in, needing no script. That is **only true once the columns
> are widened.** Against the live schema as it stands today the re-hash silently fails (or
> truncates). Both documents have been corrected.

---

## 10. Troubleshooting

### 10.0 `docker compose` fails immediately — "unknown shorthand flag: 'd' in -d"

This is the most common first failure on Fedora, so it comes first.

**Symptom**

```console
$ docker compose up -d --build
unknown shorthand flag: 'd' in -d

Usage:  docker [OPTIONS] COMMAND [ARG...]

$ docker compose exec app php install-dev/index_cli.php --step=all ...
unknown flag: --step

Usage:  docker [OPTIONS] COMMAND [ARG...]
```

**Cause.** `docker compose` (version 2) is a *CLI plugin*, not part of the `docker` binary.
These errors mean the plugin is missing, so `docker` does not understand `compose` as a
subcommand and instead tries to parse the flags that follow it (`-d`, `--step`) as its own —
and rejects them. The two usual reasons on Fedora:

1. `docker-compose-plugin` was never installed (only `docker-ce-cli`, or nothing at all), or
2. your `docker` is actually **Podman** through Fedora's `podman-docker` compatibility
   package, which prints usage as `docker [...]` and has no compose backend installed.

**This is not a problem with the commands or with the repository.** No container was created,
no volume was created, and nothing needs cleaning up. Fix the tooling and re-run the same
commands unchanged.

**Diagnose — paste this as one block**

```bash
echo "== what is 'docker' =="; command -v docker; readlink -f "$(command -v docker)"; docker --version
echo "== compose v2 plugin =="; docker compose version 2>&1 | head -3
echo "== plugin directories =="; ls /usr/libexec/docker/cli-plugins/ ~/.docker/cli-plugins/ 2>/dev/null
echo "== standalone binaries =="; command -v docker-compose podman podman-compose 2>/dev/null
echo "== installed packages =="; rpm -q docker-ce docker-ce-cli docker-compose-plugin podman podman-docker podman-compose 2>/dev/null
```

How to read it:

- If `readlink -f` resolves to `/usr/bin/podman`, you have `podman-docker`, not Docker.
- If `docker compose version` prints something like `Docker Compose version v2.29.7`, the
  plugin is present and the cause is elsewhere — re-check that you ran the command from the
  repository root, where `docker-compose.yml` lives.
- If `rpm -q docker-compose-plugin` reports "not installed", that is the whole problem.

**Fixes.** All three are written out in §2, with the correct syntax per dnf version. In short:

- **§2.2 Option A — add the compose plugin to the Docker you already have.** Usually the whole
  fix, needs no root and no new repository. Fedora packages its own Docker CLI, and it ships
  `docker-buildx` but *not* the compose plugin; Fedora does not package
  `docker-compose-plugin` at all, because that is a Docker CE repository package name. So
  `sudo dnf install docker-compose-plugin` will report "No match for argument" no matter how
  correct it looks.
- **§2.3 Option B — install Docker CE from Docker's repository.** Note that **dnf5 (Fedora 41+)
  uses `dnf config-manager addrepo --from-repofile=URL`**. The older
  `--add-repo` flag does not exist there and fails with
  `Unknown argument "--add-repo" for command "config-manager"`.
- **§2.4 Option C — use Podman**, which Fedora already provides. If `podman` and
  `podman-compose` are both installed, there is nothing to install at all.

**A note on a real diagnosis.** This exact failure was reported from a Fedora 44 machine
showing `Docker version 29.9.2, build 2.fc44`, `/usr/libexec/docker/cli-plugins/` containing
only `docker-buildx`, `podman-docker` *not* installed, and both `podman` and `podman-compose`
present. That is Fedora's own Docker CLI with no compose plugin — Option A or Option C, not a
Docker CE reinstall. The `build N.fcNN` suffix is the giveaway: Docker CE reports a git hash
there instead.

The compose file no longer depends on `depends_on: condition: service_healthy`, which older
`podman-compose` releases handled inconsistently. `dev/docker/entrypoint.sh` now waits for
MariaDB itself, so all three options behave the same. See §10.3 for how file ownership differs
between them.

### 10.1 "Permission denied" reading files, or the installer says folders are not writable

This is **SELinux**, which is enforcing by default on Fedora. The bind mounts in
`docker-compose.yml` carry a `:z` suffix, which relabels them so the container can read
them. If you have edited the compose file and removed those, put them back.

To confirm SELinux is the cause:

```bash
sudo ausearch -m avc -ts recent | grep docker
getenforce                     # "Enforcing" means SELinux is active
```

A temporary test (re-enable afterwards) is `sudo setenforce 0`. If that fixes it, the `:z`
labels are missing or the entrypoint did not run.

Write access is arranged at container start by `dev/docker/entrypoint.sh`. It does **not** chown
your working copy (see §10.3 for why that would be harmful); it either runs Apache as the uid
that already owns the mount, or adds group-write to the twelve paths the installer checks. If
you added a new directory that needs writing, add it to `QLO_WRITABLE_PATHS` in that file.

The entrypoint logs exactly what it decided, which is the fastest way to diagnose this:

```bash
podman-compose logs app | grep qlo-entrypoint      # or: docker compose logs app | ...
```

If the self-check line says Apache cannot write `/var/www/html/config`, it prints the actual
uid, gid and mode so you can see which side of the mismatch you are on.

### 10.2 The installer cannot connect to the database

Almost always `--db_server=localhost`. Inside the container, `localhost` is the container
itself. It must be **`db`**. Test connectivity:

```bash
docker compose exec app bash -c 'mariadb -h db -uqlo -pqlo_dev_only -e "SHOW DATABASES;"'
```

You should see `qloapps_fresh`. If that fails, the db container is not healthy — check
`docker compose logs db`.

### 10.3 File ownership — and why the container never chowns your repository

Everything under the project root is bind-mounted, so any ownership or permission change the
container makes happens to your **real working copy**. This environment is deliberately built
never to change ownership.

What each runtime does to uid 33 (`www-data`):

| Runtime | container uid 0 | container `www-data` (uid 33) |
| --- | --- | --- |
| Docker (rootful) | host root | host uid 33 |
| Podman (rootless) | **your host user** | a host **subuid**, e.g. 100032 |

So `chown -R www-data` would transfer tracked source files to a uid that is not you — under
rootless Podman an unmapped subuid, after which you could no longer edit your own code and git
would flag every file. Chowning the repository *root* would be worse: you would lose the
ability to create files in your own clone.

**What `dev/docker/entrypoint.sh` does instead.** It stats the bind mount to find out who
already owns your files, then takes one of two paths:

1. **The mount is owned by a non-zero uid** — true under rootful Docker, and under rootless
   Podman with `--userns=keep-id`. The entrypoint registers that uid/gid in the container's
   `/etc/passwd` and `/etc/group` if needed, and runs Apache *as that identity*. The process
   then owns the files, so **nothing on your host is chowned or chmod'd at all**.
2. **The mount appears owned by uid 0** — rootless Podman's default user namespace, where
   container root *is* your host user. Apache cannot run as uid 0 (see below), so the
   entrypoint runs Apache as `www-data` with the mount's **group** and adds group-write
   (`chmod -R g+rwX`) to the twelve paths the installer checks. Ownership is untouched, and
   because git does not track group permission bits, `git status` stays clean.

The twelve paths come from `install-dev/controllers/http/system.php:88-103`. `.git` is never
touched — changing the owner of git internals corrupts the repository.

**Why Apache is not simply run as root under rootless Podman.** It is tempting, because
container root *is* your host user and the files would already have the right owner. httpd
refuses it outright — `modules/arch/unix/mod_unixd.c`, `unixd_set_user()`:

```c
#if !defined (BIG_SECURITY_HOLE) && !defined (OS2)
    if (ap_unixd_config.user_id == 0) {
        return "Error:\tApache has not been designed to serve pages while\n"
                "\trunning as root. ...";
    }
#endif
```

The check is compiled out only with `-DBIG_SECURITY_HOLE`, which the Debian package does not
use. `unixd_set_group()` has **no** equivalent check, which is what makes path 2 possible.

A second constraint from the same file, `set_group_privs()`, is that a numeric `User #uid` must
have an `/etc/passwd` entry or Apache aborts with `APLOGNO(02155) getpwuid`. That is why the
entrypoint registers the uid instead of passing it straight through.

**Verify it on first start:**

```bash
podman-compose logs app | grep qlo-entrypoint      # or: docker compose logs app | ...
```

Path 1 looks like this:

```
[qlo-entrypoint] Bind mount is owned by uid=1000 gid=1000.
[qlo-entrypoint] Running Apache AS that identity (you:you); no host ownership or permission changes needed.
[qlo-entrypoint] Self-check: Apache (uid 1000, gid 1000) can write /var/www/html/config.
```

Path 2 looks like this:

```
[qlo-entrypoint] Bind mount appears owned by uid=0 gid=0.
[qlo-entrypoint] Apache cannot run as uid 0, so running as www-data:root and adding group-write.
[qlo-entrypoint] Self-check: Apache (uid 33, gid 0) can write /var/www/html/config.
```

The entrypoint **refuses to start Apache** if the resolved user would be root or has no passwd
entry, and warns with the actual uid/gid/mode if the writability self-check fails — so a
misconfiguration surfaces as one clear line rather than a wall of PHP permission errors
halfway through the install.

**Files Apache creates** (in `cache/`, `img/`, `log/`, `upload/`) are owned by whatever uid it
runs as. Under path 2 that is container uid 33, which appears on your host as a subuid in your
range:

```bash
podman unshare cat /proc/self/uid_map
```

Those paths are all gitignored, and you own their parent directories, so you can delete them
from the host normally. To have them arrive owned by you instead, use §10.3.1.

#### 10.3.1 Optional: make container-written files belong to you (rootless Podman)

Create `docker-compose.podman.yml` next to `docker-compose.yml`:

```yaml
services:
  app:
    userns_mode: keep-id
```

Then start with both files:

```bash
podman-compose -f docker-compose.yml -f docker-compose.podman.yml up -d --build
```

`keep-id` maps your host uid to the same number inside the container, so the bind mount stops
looking like it is owned by uid 0 and the entrypoint takes path 1: Apache runs as you, files it
writes are owned by you, and no group-write chmod is needed.

This file is deliberately **not committed** — `userns_mode: keep-id` is Podman-specific and
Docker rejects it. If your `podman-compose` version does not support the key, stay on the
default; path 2 works fine without it.

Finally, SELinux `:z` relabelling behaves the same under both runtimes, and both bind mounts in
`docker-compose.yml` carry it.

#### 10.3.2 Clearing caches when the host says "Permission denied"

Under rootless Podman's default user namespace, everything Apache writes (compiled Smarty
templates, uploaded images, logs) is owned by a container uid that appears on your host as a
subuid in your range. The entrypoint grants group-write to the directories it knows about at
boot, but subtrees Apache creates *later* arrive as `drwxr-xr-x` owned by that subuid, so from
the host you can read them and cannot delete inside them:

```
rm: cannot remove 'cache/smarty/compile/6e/c3/f2/...tpl.php': Permission denied
```

That is expected and harmless — the cache is regenerable — but clear it from somewhere that
*is* root inside the user namespace:

```bash
# inside the app container (preferred; qlo-app is the fixed container name)
podman-compose exec app sh -c 'rm -f /var/www/html/cache/class_index.php; find /var/www/html/cache/smarty/compile /var/www/html/cache/smarty/cache -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'

# or from the host, borrowing the container's user namespace
podman unshare sh -c 'rm -f cache/class_index.php; find cache/smarty/compile cache/smarty/cache -mindepth 1 -maxdepth 1 ! -name index.php -exec rm -rf {} +'
```

`dev/scripts/use-db.sh` performs the container-side clear itself when it switches databases.

From the container's first start the entrypoint also runs Apache with `umask 002` in this
configuration, so *newly* created files and directories are group-writable (775/664) and your
host user — who belongs to that group — can manage them directly. Subtrees created before that
change need one of the two commands above a single time.

### 10.4 Port 8080 or 3307 already in use

```bash
ss -ltnp | grep -E ':8080|:3307'
```

Change the left-hand side of the port mappings in `docker-compose.yml`
(`"127.0.0.1:8081:80"`), then re-run the install with `--domain=localhost:8081`.

### 10.5 Starting over completely

```bash
docker compose down -v          # deletes BOTH databases
rm -f config/settings.inc.php .htaccess
git status                      # confirm nothing else was generated that you want to keep
docker compose up -d --build
```

Then re-run the install command from §5.

### 10.6 Friendly URLs give 404s

The root `.htaccess` is generated by the installer and requires Apache's `AllowOverride All`,
which `dev/docker/Dockerfile` sets (the stock PHP image leaves it at `None`, which silently
breaks rewriting). If URLs still 404, regenerate them from the back office:
**Preferences → SEO & URLs → "Set up rewrite URLs"**, save.

---

### 10.7 Podman pulls the wrong registry — `manifest unknown` for `mariadb:11.4`

Symptom, during `podman-compose up -d --build`:

```
Resolved "mariadb" as an alias (/etc/containers/registries.conf.d/000-shortnames.conf)
Trying to pull registry.fedoraproject.org/mariadb:11.4...
Error: reading manifest 11.4 in registry.fedoraproject.org/mariadb: manifest unknown
```

Podman resolves a bare image name through its **short-names** table, and Fedora ships
`/etc/containers/registries.conf.d/000-shortnames.conf`, which maps `mariadb` to
`registry.fedoraproject.org/mariadb`. That registry is Fedora's own package-oriented registry
and it does not publish an `11.4` tag, so the pull fails.

This is why the `app` service built fine while `db` did not: Fedora's table happens to map
`php` to `docker.io`, so the two services resolved to different registries. The
`"qlo-db" is not a valid container, cannot be used as a dependency` error that follows is just a
cascade — the database image never arrived, so the app could not start.

The fix is already in this repository: `docker-compose.yml` names the image
`docker.io/library/mariadb:11.4`, and `dev/docker/Dockerfile` uses
`FROM docker.io/library/php:8.4-apache-bookworm`. A fully qualified reference bypasses
short-name resolution on every runtime, so Docker and Podman behave identically. If you add
another service, qualify its image the same way.

To see what your own system would resolve a short name to:

```bash
grep -rn "^mariadb=" /etc/containers/registries.conf.d/ 2>/dev/null
podman info --format '{{.Registries}}'
```

## 11. Safety rules for local development

These come from §9 of the project brief and are not negotiable:

1. **Never point local tooling at the live server.** `import-live-dump.sh` and
   `set-admin-password.php` both refuse a non-local database host on purpose. Do not work
   around that.
2. **Never use the live database credentials locally**, and never put them in
   `docker-compose.yml`. The credentials in that file are throwaway values for a container
   that is not reachable from your network (both ports bind to `127.0.0.1`).
3. **Never commit the dump.** Root `.gitignore` ignores `*.sql`; the only exception is the
   structure-only reference in `docs/project/`, which provably contains no rows.
4. **Staging must not send real guest email.** After importing live data, go to
   **Preferences → Email** and set the "never send emails" option, and to
   **Preferences → Orders** and disable any payment gateway. The live configuration has
   `PS_MAIL_METHOD = 1` (SMTP). If you import it and the mail settings still point at real
   infrastructure, a local test booking can email a real guest. **Check this before your
   first test booking on imported data.**
5. **Take a fresh export before any migration experiment** (§7.1), so you can always
   re-import from a known state.

---

## 12. Files added for this environment

| Path | Purpose |
| --- | --- |
| `docker-compose.yml` | MariaDB 11.4 + PHP 8.4/Apache, SELinux-labelled mounts, loopback-only ports |
| `dev/docker/Dockerfile` | builds the required PHP extensions, enables `mod_rewrite`, sets `AllowOverride All` |
| `dev/docker/php.ini` | exceeds every installer minimum; timezone `Africa/Accra`; opcache revalidating |
| `dev/docker/entrypoint.sh` | waits for MariaDB to accept connections; stats the bind mount and runs Apache as the uid that already owns it (registering that uid/gid in the container's passwd/group), falling back to `www-data` plus group-write on the twelve installer-checked directories when the mount looks owned by uid 0 — which Apache refuses to run as; never chowns the working copy or `.git`; verifies the result and reports it on every start |
| `dev/docker/initdb/README.md` | optional first-run SQL drop point (left empty) |
| `dev/scripts/use-db.sh` | switch between `qloapps_fresh` and `qloapps_live`, clearing caches |
| `dev/scripts/import-live-dump.sh` | validated, production-incapable import of the live dump |
| `dev/scripts/set-admin-password.php` | set a usable local password; picks bcrypt or MD5 from the real column width |
| `dev/migrations/001_widen_password_columns.up.sql` | widen `passwd` to `varchar(60)` so bcrypt fits |
| `dev/migrations/001_widen_password_columns.down.sql` | rollback, with a pre-flight check that refuses to truncate live bcrypt hashes |

`dev/` is now included in the CI PHP syntax check (`.github/workflows/ci.yml`), so
`set-admin-password.php` is linted on PHP 8.2, 8.3 and 8.4 on every push.
