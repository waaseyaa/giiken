<?php

declare(strict_types=1);

/*
 * Repoint vendor/bin/waaseyaa at the giiken-local wrapper (bin/waaseyaa).
 *
 * Composer installs vendor/bin/waaseyaa as a proxy to the waaseyaa/cli
 * package's bin, which does not load .env and resolves projectRoot relative
 * to its own vendor location. That path lands in
 * vendor/waaseyaa/cli/storage/waaseyaa.sqlite and falls through to
 * APP_ENV=production, tripping the DatabaseBootstrapper "must already
 * exist" guard.
 *
 * This script runs from composer's post-install-cmd / post-update-cmd and
 * replaces vendor/bin/waaseyaa with a symlink pointing at ../../bin/waaseyaa
 * so both invocations (./bin/waaseyaa and ./vendor/bin/waaseyaa) resolve to
 * the same .env-loading wrapper.
 *
 * Idempotent: safe to run repeatedly. Becomes a one-line delete once
 * waaseyaa/framework#1226 lands. See waaseyaa/giiken#65.
 */

$projectRoot = dirname(__DIR__);
$wrapper     = $projectRoot . '/bin/waaseyaa';
$vendorBin   = $projectRoot . '/vendor/bin';
$target      = $vendorBin . '/waaseyaa';
$linkDest    = '../../bin/waaseyaa';

if (!file_exists($wrapper)) {
    fwrite(STDERR, "repoint-vendor-bin: bin/waaseyaa does not exist; skipping.\n");
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
