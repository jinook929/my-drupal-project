# my-project — The Waterfall Handbook (Drupal 11)

**The Waterfall Handbook**, a Drupal 11 site managed with **DDEV** and **Composer**.
Site configuration is version-controlled with Drupal's configuration management
(`config/sync/`).

## Requirements

- [DDEV](https://ddev.com/) (with Docker)
- Git
- A copy of the project database (see [Database & content](#database--content))

## Getting started (new developer)

```bash
# 1. Clone the repository
git clone https://github.com/jinook929/my-drupal-project.git
cd my-drupal-project

# 2. Start the environment and restore Composer dependencies.
#    Drupal core and contrib modules/themes are NOT in Git — they are restored here.
ddev start
ddev composer install

# 3. Import a database. Content is NOT in Git (see "Database & content").
ddev import-db --file=path/to/db.sql.gz

# 4. Apply the latest configuration from Git.
ddev drush config:import -y
ddev drush cache:rebuild

# 5. Open the site.
ddev launch
```

## What is (and isn't) in Git

| In Git | Restored separately (not in Git) |
| --- | --- |
| `composer.json` / `composer.lock` (dependency versions) | `vendor/`, `web/core/`, `web/modules/contrib/`, `web/themes/contrib/` → `composer install` |
| `config/sync/*.yml` (site configuration) | the database / content → DB import |
| `web/modules/custom/`, `web/themes/custom/` (custom code) | `web/sites/*/settings.ddev.php` (secrets, DDEV-generated) |
| `web/sites/default/settings.php` (shared, no secrets) | `web/sites/*/files/` (uploaded files) |

Three layers — **Code** (Composer + custom), **Configuration** (`config/sync`),
**Content** (database). Code and configuration travel through Git; content does not.

## Site structure

- **Content types** — `waterfall` (the main content: hike duration/difficulty,
  location, images, official website, brochure), plus the standard `article`
  and `page`.
- **Taxonomies** — `location`, `hiking_difficulty` (with an icon field), `tags`.
- **User roles** — `content_editor`, `waterfall_specialist` (besides the
  standard administrator/authenticated/anonymous). Visitors can self-register
  without email verification (`user.settings`).
- **Custom block types** — `brochure_block` (body + waterfall reference).
- **Themes** — `waterfall_olivero` (custom Olivero subtheme, front end) and
  Claro (admin). Both use `waterfallhandbook_logo.png` as logo/favicon.
  Custom CSS lives in `web/themes/custom/waterfall_olivero/css/global-styles.css`.
- **Key contrib modules** — Admin Toolbar, Pathauto (auto URL aliases),
  Structure Sync (shares taxonomy terms via config), Gin + Mercury (admin UI),
  Drush.

## Daily workflow

```bash
# Before starting work — get the latest code + config.
git checkout main && git pull
ddev composer install        # if composer.lock changed
ddev drush deploy            # updatedb + config:import + cache rebuild

# Start a feature branch.
git checkout -b feature/short-description

# ...make changes: edit custom code AND/OR configure via the admin UI...

# Capture admin-UI configuration changes into Git.
ddev drush config:export -y   # (= ddev drush cex -y)
git add config web/modules/custom composer.json composer.lock
git commit -m "Describe the change"
git push -u origin feature/short-description
# → open a Pull Request, get it reviewed, merge to main.
```

After a PR merges, everyone updates:

```bash
git checkout main && git pull
ddev drush deploy
```

> **Pantheon auto-deploy:** every push to `main` triggers a GitHub Actions
> workflow (`.github/workflows/deploy-pantheon.yml`) that force-pushes to
> Pantheon's Git remote automatically. No manual Pantheon push needed.

## Deployment (Pantheon)

The site is hosted on Pantheon's free **Sandbox** plan.

| Environment | URL |
| --- | --- |
| Dev | https://dev-nz-waterfalls.pantheonsite.io |

### How auto-deploy works

`.github/workflows/deploy-pantheon.yml` runs on every push to `main`:
1. Checks out the repo
2. Authenticates with Pantheon via `PANTHEON_SSH_PRIVATE_KEY` (GitHub Secret)
3. Force-pushes `main → master` on Pantheon's Git remote
4. Pantheon's **Integrated Composer** builds `vendor/`, `web/core/`, contrib
   modules/themes automatically — nothing extra to do

### Terminus (Pantheon CLI)

Terminus is installed at `~/terminus`. Log in once with a machine token
(generate at `https://dashboard.pantheon.io/machine-token/create`):

```bash
~/terminus auth:login --machine-token=<token>
```

Useful Terminus commands:

```bash
~/terminus remote:drush nz-waterfalls.dev -- drush deploy    # config:import + cr on Pantheon
~/terminus remote:drush nz-waterfalls.dev -- cache:rebuild   # (= cr) rebuild Pantheon caches
~/terminus remote:drush nz-waterfalls.dev -- config:status   # check config drift on Pantheon
~/terminus env:info nz-waterfalls.dev                        # show environment info / URL
```

### Syncing the database to Pantheon

After major local DB changes (new content, bulk edits):

```bash
# 1. Export local DB
ddev export-db --file=/tmp/nz-waterfalls.sql.gz

# 2. Import to Pantheon (drops existing DB first)
~/terminus remote:drush nz-waterfalls.dev -- sql:drop --yes
gunzip -c /tmp/nz-waterfalls.sql.gz | ~/terminus remote:drush nz-waterfalls.dev -- sql:cli

# 3. Rebuild caches
~/terminus remote:drush nz-waterfalls.dev -- cache:rebuild
```

### Syncing user-uploaded files to Pantheon

`web/sites/default/files/` is not in Git. Sync manually when new media is
uploaded locally (exclude auto-generated asset dirs):

```bash
rsync -rlvz \
  --exclude=css --exclude=js --exclude=styles --exclude=php --exclude=sync \
  -e "ssh -p 2222 -i ~/.ssh/id_rsa_pantheon" \
  web/sites/default/files/ \
  "dev.c32f5a15-f706-44a5-9139-a84a44f88374@appserver.dev.c32f5a15-f706-44a5-9139-a84a44f88374.drush.in:files/"
```

The SSH key for Pantheon is `~/.ssh/id_rsa_pantheon` (RSA — Pantheon does not
support ed25519 keys). The corresponding public key is registered in
Pantheon → User settings → SSH Keys.

## Configuration management

- Structure and settings (content types, fields, views, blocks, roles, …) are
  **configuration**, stored as YAML in `config/sync/`.
- Export admin-UI changes with `ddev drush config:export` (alias `cex`), commit
  them; others apply them with `ddev drush config:import` (alias `cim`) or
  `ddev drush deploy`.
- `config_sync_directory` is set to `../config/sync` in
  `web/sites/default/settings.php` (tracked in Git, so the location is identical
  for everyone).
- Never change configuration directly on production — change it locally, export,
  and deploy. Editing production in the UI causes configuration drift.

## Database & content

Content (nodes, users, media, files) is **not** in Git. Share it via a database dump:

```bash
ddev export-db --file=db.sql.gz        # create a dump
ddev import-db --file=db.sql.gz        # load a dump

# Always sanitize after importing a production copy
# (scrubs emails, passwords, sessions).
ddev drush sql:sanitize -y
```

Designate a shared, sanitized dump location for the team (e.g. cloud storage), or
configure Drush site aliases so developers can run `ddev drush sql:sync @prod @self`.
For a small set of reusable demo content, consider the **Default Content** module
or a **Recipe** instead of a full dump.

**Exception: taxonomy terms.** Terms are content, but this project shares them
through Git with the **Structure Sync** module (`structure_sync.data` in
`config/sync/`):

```bash
ddev drush export:taxonomies   # (= ddev drush et) capture terms into config — then config:export & commit
ddev drush import:taxonomies   # (= ddev drush it) load terms after pulling config
```

## Useful commands

```bash
ddev drush deploy            # updatedb + config:import + cache rebuild + post-deploy hooks
ddev drush config:status     # show differences between the database and config/sync
ddev drush config:export     # (= ddev drush cex) export active config (DB) to config/sync
ddev drush config:import     # (= ddev drush cim) import config/sync into the database (CAUTION: overwrites DB config)
ddev drush cache:rebuild     # (= ddev drush cr) rebuild caches
ddev composer require drupal/MODULE   # add a contrib module
```
