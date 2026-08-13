<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../auth.php';

$user = getAuthenticatedUser();
if (!$user || $user['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

$repo = "moon9ibulbul/Astral-Tele";
$versionFile = __DIR__ . '/../version.txt';
$githubApiBase = "https://api.github.com/repos/$repo";
$githubRawBase = "https://raw.githubusercontent.com/$repo";

function getLocalVersion() {
    global $versionFile;
    if (file_exists($versionFile)) {
        return trim(file_get_contents($versionFile));
    }
    // Fallback to git if available
    $output = [];
    $return_var = 0;
    exec("git rev-parse HEAD", $output, $return_var);
    if ($return_var === 0 && isset($output[0])) {
        return trim($output[0]);
    }
    return null;
}

function fetchGithub($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Astral-Tele-Updater');
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        return null;
    }
    return json_decode($response, true);
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'check') {
    $localVersion = getLocalVersion();
    if (!$localVersion) {
        echo json_encode(['error' => 'Could not determine local version.']);
        exit;
    }

    $latestCommit = fetchGithub("$githubApiBase/commits/main");
    if (!$latestCommit) {
        echo json_encode(['error' => 'Could not fetch latest version from GitHub.']);
        exit;
    }

    $latestVersion = $latestCommit['sha'];

    if ($localVersion === $latestVersion) {
        echo json_encode([
            'update_available' => false,
            'current_version' => $localVersion,
            'latest_version' => $latestVersion,
            'message' => 'You are on the latest version.'
        ]);
        exit;
    }

    // Fetch changes
    $comparison = fetchGithub("$githubApiBase/compare/$localVersion...$latestVersion");
    if (!$comparison) {
        echo json_encode(['error' => 'Could not fetch changelog from GitHub.']);
        exit;
    }

    $changelog = [];
    foreach ($comparison['commits'] as $commit) {
        $changelog[] = [
            'sha' => $commit['sha'],
            'message' => $commit['commit']['message'],
            'author' => $commit['commit']['author']['name'],
            'date' => $commit['commit']['author']['date']
        ];
    }

    echo json_encode([
        'update_available' => true,
        'current_version' => $localVersion,
        'latest_version' => $latestVersion,
        'changelog' => $changelog,
        'files_changed' => count($comparison['files'] ?? [])
    ]);
    exit;

} elseif ($action === 'update') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        exit;
    }

    $localVersion = getLocalVersion();
    $latestCommit = fetchGithub("$githubApiBase/commits/main");

    if (!$localVersion || !$latestCommit) {
        http_response_code(500);
        echo json_encode(['error' => 'Version check failed during update.']);
        exit;
    }

    $latestVersion = $latestCommit['sha'];
    if ($localVersion === $latestVersion) {
        echo json_encode(['success' => true, 'message' => 'Already on the latest version.']);
        exit;
    }

    $comparison = fetchGithub("$githubApiBase/compare/$localVersion...$latestVersion");
    if (!$comparison || !isset($comparison['files'])) {
        http_response_code(500);
        echo json_encode(['error' => 'Could not determine files to update.']);
        exit;
    }

    $rootDir = __DIR__ . '/../';
    $updatedFiles = 0;
    $errors = [];

    foreach ($comparison['files'] as $file) {
        $filename = $file['filename'];
        $status = $file['status'];

        $localFilePath = $rootDir . $filename;

        if ($status === 'removed') {
            if (file_exists($localFilePath)) {
                if (!unlink($localFilePath)) {
                    $errors[] = "Failed to delete: $filename";
                } else {
                    $updatedFiles++;
                }
            }
        } else {
            // added, modified, renamed
            $rawUrl = "$githubRawBase/$latestVersion/$filename";

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $rawUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_USERAGENT, 'Astral-Tele-Updater');
            $fileContent = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                $dir = dirname($localFilePath);
                if (!is_dir($dir)) {
                    mkdir($dir, 0755, true);
                }
                if (file_put_contents($localFilePath, $fileContent) !== false) {
                    $updatedFiles++;
                } else {
                    $errors[] = "Failed to write: $filename";
                }
            } else {
                $errors[] = "Failed to download: $filename (HTTP $httpCode)";
            }
        }
    }

    if (empty($errors)) {
        file_put_contents($versionFile, $latestVersion);
        echo json_encode([
            'success' => true,
            'message' => "Successfully updated $updatedFiles files to version $latestVersion.",
            'new_version' => $latestVersion
        ]);
    } else {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Update partially failed.',
            'details' => $errors
        ]);
    }
    exit;

} else {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid action']);
    exit;
}
