<?php

declare(strict_types=1);

/*
 * Repoint vendor/bin/waaseyaa at the giiken-local wrapper (bin/giiken).
 *
 * Composer installs vendor/bin/waaseyaa as a proxy to the waaseyaa/cli
 * package's bin, which does not load .env and resolves projectRoot relative
 * to its own vendor location. That path lands in
 * vendor/waaseyaa/cli/storage/waaseyaa.sqlite and falls through to
 * APP_ENV=production, tripping the DatabaseBootstrapper "must already
 * exist" guard.
 *
 * The giiken wrapper lives at bin/giiken (renamed from bin/waaseyaa to
 * avoid a Composer bin-dir collision that was silently overwriting the
 * wrapper on install/update). This script keeps vendor/bin/waaseyaa
 * working by symlinking it to bin/giiken, so muscle-memory invocations
 * of ./vendor/bin/waaseyaa still load .env correctly. The canonical
 * entry point is ./bin/giiken.
 *
 * Runs from composer's post-install-cmd / post-update-cmd.
 * Idempotent: safe to run repeatedly. Becomes a one-line delete once
 * waaseyaa/framework#1226 lands. See waaseyaa/giiken#65.
 */

$projectRoot = dirname(__DIR__);
$wrapper     = $projectRoot . '/bin/giiken';
$vendorBin   = $projectRoot . '/vendor/bin';
$target      = $vendorBin . '/waaseyaa';
$linkDest    = '../../bin/giiken';

if (!file_exists($wrapper)) {
    fwrite(STDERR, "repoint-vendor-bin: bin/giiken does not exist; skipping.\n");
    exit(0);
}

if (!is_dir($vendorBin)) {
    fwrite(STDERR, "repoint-vendor-bin: vendor/bin does not exist; skipping.\n");
    exit(0);
}

if (is_link($target) && readlink($target) === $linkDest) {
    echo "repoint-vendor-bin: vendor/bin/waaseyaa already points at {$linkDest}\n";
    exit(0);
}

@unlink($target);

if (!symlink($linkDest, $target)) {
    fwrite(STDERR, "repoint-vendor-bin: failed to create symlink at {$target}\n");
    exit(1);
}

echo "repoint-vendor-bin: vendor/bin/waaseyaa -> {$linkDest}\n";
