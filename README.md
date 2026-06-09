# my-project — Drupal 11 site

A Drupal 11 site managed with **DDEV** and **Composer**. Site configuration is
version-controlled with Drupal's configuration management (`config/sync/`).

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
ddev drush config:export -y
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

## Configuration management

- Structure and settings (content types, fields, views, blocks, roles, …) are
  **configuration**, stored as YAML in `config/sync/`.
- Export admin-UI changes with `ddev drush config:export`, commit them; others
  apply them with `ddev drush config:import` (or `ddev drush deploy`).
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

## Useful commands

```bash
ddev drush deploy            # updatedb + config:import + cache rebuild + post-deploy hooks
ddev drush config:status     # show differences between the database and config/sync
ddev drush cr                # rebuild caches
ddev composer require drupal/MODULE   # add a contrib module
```
