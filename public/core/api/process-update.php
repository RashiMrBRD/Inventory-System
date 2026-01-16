<?php
/**
 * Background Process: Download and Install Update
 * 
 * Downloads the update from GitHub, extracts it, and replaces files.
 * This script runs in the background after the download-update API endpoint is called.
 */

if ($argc < 4) {
    error_log('[Update] Missing arguments');
    exit(1);
}

$downloadUrl = $argv[1];
$version = $argv[2];
$progressFile = $argv[3];

function updateProgress($status, $progress, $message) {
    global $progressFile;
    file_put_contents($progressFile, json_encode([
        'status' => $status,
        'progress' => $progress,
        'message' => $message
    ]));
}

try {
    // GitHub token
    $githubToken = getenv('GITHUB_TOKEN') ?? '';
    if (empty($githubToken)) {
        throw new Exception('GitHub token not configured');
    }

    // Temp directory
    $tempDir = __DIR__ . '/../../../temp/updates';
    $zipFile = $tempDir . '/update_' . $version . '.zip';
    $extractDir = $tempDir . '/extracted_' . $version;

    updateProgress('downloading', 10, 'Downloading update from GitHub...');

    // Download the zip file
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $downloadUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Inventory-Management-System',
        'Authorization: token ' . $githubToken
    ]);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 300);

    $zipData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200 || empty($zipData)) {
        throw new Exception('Failed to download update from GitHub');
    }

    // Save zip file
    file_put_contents($zipFile, $zipData);

    updateProgress('extracting', 30, 'Extracting files...');

    // Create extract directory
    if (!is_dir($extractDir)) {
        mkdir($extractDir, 0755, true);
    }

    // Extract zip file
    $zip = new ZipArchive();
    if ($zip->open($zipFile) !== true) {
        throw new Exception('Failed to open zip file');
    }

    $zip->extractTo($extractDir);
    $zip->close();

    updateProgress('installing', 50, 'Installing files...');

    // Find the extracted directory (GitHub creates a directory with the repo name)
    $extractedDirs = glob($extractDir . '/*', GLOB_ONLYDIR);
    if (empty($extractedDirs)) {
        throw new Exception('No directory found in extracted files');
    }

    $sourceDir = $extractedDirs[0];
    $targetDir = __DIR__ . '/../../..';

    // Copy files to target directory
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    $totalFiles = iterator_count($iterator);
    $copiedFiles = 0;

    foreach ($iterator as $item) {
        $relativePath = substr($item->getPathname(), strlen($sourceDir) + 1);
        $targetPath = $targetDir . '/' . $relativePath;

        if ($item->isDir()) {
            if (!is_dir($targetPath)) {
                mkdir($targetPath, 0755, true);
            }
        } else {
            // Skip certain files/directories
            if (strpos($relativePath, 'temp/') === 0 ||
                strpos($relativePath, 'vendor/') === 0 ||
                strpos($relativePath, 'node_modules/') === 0 ||
                $relativePath === '.env' ||
                $relativePath === 'config/app.php') {
                continue;
            }

            copy($item->getPathname(), $targetPath);
        }

        $copiedFiles++;
        $progress = 50 + floor(($copiedFiles / $totalFiles) * 40);
        updateProgress('installing', $progress, "Installing files ({$copiedFiles}/{$totalFiles})...");
    }

    updateProgress('updating_config', 90, 'Updating configuration...');

    // Update app version in config
    $configFile = $targetDir . '/config/app.php';
    if (file_exists($configFile)) {
        $configContent = file_get_contents($configFile);
        $configContent = preg_replace(
            "/'app_version'\s*=>\s*'[^']+'/",
            "'app_version' => '{$version}'",
            $configContent
        );
        file_put_contents($configFile, $configContent);
    }

    // Cleanup
    updateProgress('cleanup', 95, 'Cleaning up temporary files...');
    unlink($zipFile);
    $this->deleteDirectory($extractDir);

    updateProgress('completed', 100, 'Update installed successfully!');

} catch (Exception $e) {
    updateProgress('failed', 0, 'Update failed: ' . $e->getMessage());
    error_log('[Update] Error: ' . $e->getMessage());
}

function deleteDirectory($dir) {
    if (!file_exists($dir)) {
        return;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        if ($fileinfo->isDir()) {
            rmdir($fileinfo->getPathname());
        } else {
            unlink($fileinfo->getPathname());
        }
    }

    rmdir($dir);
}
