<?php

/**
 * Standalone Nextcloud Update Checker
 * Extracted logic from: lib/Updater.php::getUpdateServerResponse()
 */

// 1. Locate and load configuration
$baseDir = getcwd(); // Assumes script is run from Nextcloud root
$configFile = $baseDir . '/config/config.php';
$versionFile = $baseDir . '/version.php';

if (!file_exists($configFile)) {
    die("ERROR - config/config.php not found. Please run this script from your Nextcloud root directory.\n");
}

require $configFile; // Loads $CONFIG variable

// 2. Determine current version information
$internalVersion = $CONFIG['version'];
$buildTime = '';

if (file_exists($versionFile)) {
    require $versionFile;
    // version.php defines $OC_Build
    if (isset($OC_Build)) {
        $buildTime = $OC_Build;
    }
}

// 3. Prepare the Update Server URL
// Defaults found in lib/Updater.php
$updaterServer = $CONFIG['updater.server.url'] ?? 'https://updates.nextcloud.com/updater_server/';
$releaseChannel = $CONFIG['updater.release.channel'] ?? 'stable';

// The API expects dots replaced by 'x'
$versionParam = str_replace('.', 'x', $internalVersion);

// Construct the URL exactly as the Updater class does
$url = $updaterServer . '?version=' .
       $versionParam . 'xxx' .
       $releaseChannel . 'xx' .
       urlencode($buildTime) . 'x' .
       PHP_MAJOR_VERSION . 'x' .
       PHP_MINOR_VERSION . 'x' .
       PHP_RELEASE_VERSION;

// 4. Perform the Request
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_USERAGENT, 'Nextcloud Updater');

// Apply proxy settings if they exist in config.php
if (isset($CONFIG['proxy'])) {
    curl_setopt($ch, CURLOPT_PROXY, $CONFIG['proxy']);
    if (isset($CONFIG['proxyuserpwd'])) {
        curl_setopt($ch, CURLOPT_PROXYUSERPWD, $CONFIG['proxyuserpwd']);
    }
}

$response = curl_exec($ch);
curl_close($ch);

// 5. Parse and Print Result
if ($response) {
    $xml = simplexml_load_string($response);
    if ($xml !== false) {
        $data = get_object_vars($xml);
        if (isset($data['versionstring'])) {
            // The server returns "Nextcloud X.Y.Z", strip the prefix to get just the version
            $versionString = (string)$data['versionstring'];
            $versionOnly = trim(str_ireplace('Nextcloud ', '', $versionString));
            echo $versionOnly . PHP_EOL;
        } else {
            // If no versionstring is returned, it usually means you are up to date
            echo "NO_UPDATE" . PHP_EOL;
        }
    } else {
        echo "ERROR - Invalid XML response from update server." . PHP_EOL;
    }
} else {
    echo "ERROR - Could not contact update server." . PHP_EOL;
}
