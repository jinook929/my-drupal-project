<?php

/**
 * Quicksilver hook: apply code changes to the running site after a code sync/deploy.
 *
 * `drush deploy` runs the deploy steps in the required order:
 *   updatedb -> cache:rebuild -> config:import -> cache:rebuild -> deploy:hook
 *
 * Without this, pushing code to Pantheon only ships the files — database schema
 * updates and config/sync YAML changes never take effect on the live site.
 *
 * Runs in the webphp runtime (drush is on PATH, CWD is the docroot). webphp hooks
 * are capped at 120s; drush deploy on this site fits well within that.
 * ponytail: single `drush deploy`; split into explicit steps only if the 120s cap is hit.
 */

echo "[quicksilver] Running drush deploy...\n";
passthru('drush deploy -y', $exit_code);

if ($exit_code !== 0) {
  echo "[quicksilver] drush deploy FAILED (exit code {$exit_code}).\n";
  exit($exit_code);
}

echo "[quicksilver] drush deploy complete.\n";
