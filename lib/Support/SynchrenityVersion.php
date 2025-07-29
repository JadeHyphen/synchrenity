<?php

declare(strict_types=1);

namespace Synchrenity\Support;

if (!defined('SYNCHRENITY_VERSION')) {
    $composerFile = __DIR__ . '/../../composer.json';

    if (file_exists($composerFile)) {
        $composer = json_decode(file_get_contents($composerFile));

        if (isset($composer->version)) {
            define('SYNCHRENITY_VERSION', $composer->version);
        }
    }
}
