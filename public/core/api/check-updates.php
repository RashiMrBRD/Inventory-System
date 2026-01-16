<?php
/**
 * API Endpoint: Check for Updates
 * 
 * Checks the GitHub repository for the latest version and compares it with the current version.
 */

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Helper\SessionHelper;
use App\Controller\AuthController;

// Ensure session is started
SessionHelper::start();

// Enforce authentication
$auth = new AuthController();
if (!$auth->isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

// Get current version from request
$input = json_decode(file_get_contents('php://input'), true);
$currentVersion = $input['current_version'] ?? '0.3.7';

// GitHub repository configuration
$githubRepo = getenv('GITHUB_REPO') ?? 'RashiMrBRD/Inventory-System';
$githubToken = getenv('GITHUB_TOKEN') ?? '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';

if (empty($githubToken)) {
    echo json_encode([
        'success' => false,
        'message' => 'GitHub token not configured. Please set GITHUB_TOKEN environment variable.'
    ]);
    exit;
}

try {
    // Fetch latest release from GitHub API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://api.github.com/repos/{$githubRepo}/releases/latest");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'User-Agent: Inventory-Management-System',
        'Authorization: token ' . $githubToken
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Failed to fetch release information from GitHub');
    }

    $release = json_decode($response, true);
    
    if (!isset($release['tag_name'])) {
        throw new Exception('Invalid response from GitHub API');
    }

    $latestVersion = ltrim($release['tag_name'], 'v');
    $downloadUrl = $release['zipball_url'] ?? null;
    $releaseNotes = $release['body'] ?? '';

    // Compare versions
    $updateAvailable = version_compare($latestVersion, $currentVersion, '>');

    echo json_encode([
        'success' => true,
        'update_available' => $updateAvailable,
        'current_version' => $currentVersion,
        'latest_version' => $latestVersion,
        'download_url' => $downloadUrl,
        'release_notes' => $releaseNotes
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
