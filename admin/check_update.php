<?php
require_once '../includes/config.php';
require_once '../includes/functions.php';

$user_role = getUserRole();
$user_id = $_SESSION['user_id'];
// Check if user is admin
checkPermission(['admin']);

// Current version
$current_version = include_once '../version.php';

// GitHub API information
$github_username = 'agskanchana';
$github_repo = 'qa_reporter';
$github_branch = 'main';

// Debug information (only show if we're troubleshooting)
$debug = isset($_GET['debug']);

if ($debug) {
    echo '<div style="background:#f5f5f5; padding:10px; margin:10px 0; border:1px solid #ccc;">';
    echo '<h3>Debug Information</h3>';
    echo '<p>PHP Version: ' . phpversion() . '</p>';
    echo '<p>Current version: ' . $current_version . '</p>';
    echo '</div>';
}

// Initialize variables
$error = '';
$update_available = false;
$latest_version = $current_version;
$download_url = '';
$release_notes = '';
$update_message = '';
$update_status = '';

// Check for updates using GitHub API
try {
    // Try the releases endpoint first (preferred)
    $githubApiUrl = "https://api.github.com/repos/{$github_username}/{$github_repo}/releases/latest";

    $ch = curl_init($githubApiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'QA Reporter Update Checker');
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $releaseData = json_decode($response, true);

        // Get version from tag (remove 'v' prefix if present)
        $latestVersion = $releaseData['tag_name'] ?? 'Unknown';
        if (substr($latestVersion, 0, 1) === 'v') {
            $latestVersion = substr($latestVersion, 1);
        }

        $releaseUrl = $releaseData['html_url'] ?? '#';
        $downloadUrl = $releaseData['zipball_url'] ?? '#';
        $releaseNotes = $releaseData['body'] ?? 'No release notes available.';

        // Check if update is available
        if (version_compare($latestVersion, $current_version, '>')) {
            $update_available = true;
            $latest_version = $latestVersion;
            $download_url = $downloadUrl;
            $release_notes = $releaseNotes;
        }
    } else if ($httpCode === 404) {
        // If no releases, fall back to checking the main branch
        $githubApiUrl = "https://api.github.com/repos/{$github_username}/{$github_repo}/contents/version.php?ref={$github_branch}";

        $ch = curl_init($githubApiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'QA Reporter Update Checker');
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Accept: application/vnd.github.v3+json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200 && $response) {
            $fileData = json_decode($response, true);
            if (isset($fileData['content'])) {
                $content = base64_decode($fileData['content']);

                // Extract version from the version.php file
                if (preg_match('/return\s+[\'"]([\d\.]+)[\'"];/', $content, $matches)) {
                    $latestVersion = $matches[1];

                    // Check if update is available
                    if (version_compare($latestVersion, $current_version, '>')) {
                        $update_available = true;
                        $latest_version = $latestVersion;
                        $download_url = "https://github.com/{$github_username}/{$github_repo}/archive/refs/heads/{$github_branch}.zip";
                        $release_notes = "Update to version {$latestVersion} from the {$github_branch} branch.";
                    }
                } else {
                    $error = 'Could not extract version information from version.php';
                }
            } else {
                $error = 'Invalid response from GitHub API';
            }
        } else {
            $error = 'Failed to check for updates. GitHub API returned status code: ' . $httpCode;
        }
    } else {
        $error = 'Failed to check for updates. GitHub API returned status code: ' . $httpCode;
    }
} catch (Exception $e) {
    $error = 'Error checking for updates: ' . $e->getMessage();
}

// Handle update process
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update']) && $update_available) {
    try {
        // Create temp directory
        $temp_dir = sys_get_temp_dir() . '/qa_reporter_update_' . time();
        if (!file_exists($temp_dir)) {
            mkdir($temp_dir, 0755, true);
        }

        // Download ZIP file
        $zipFile = $temp_dir . '/update.zip';

        $ch = curl_init($download_url);
        $fp = fopen($zipFile, 'wb');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'QA Reporter Update Downloader');
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $success = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if (!$success || $httpCode !== 200 || !file_exists($zipFile)) {
            throw new Exception('Failed to download the update package');
        }

        // Extract ZIP
        $zip = new ZipArchive;
        if ($zip->open($zipFile) !== true) {
            throw new Exception('Failed to open the update package');
        }

        $extractDir = $temp_dir . '/extract';
        if (!file_exists($extractDir)) {
            mkdir($extractDir, 0755, true);
        }

        $zip->extractTo($extractDir);
        $zip->close();

        // Find the extracted directory (usually with repo name and branch)
        $extracted_folders = glob($extractDir . '/*', GLOB_ONLYDIR);
        if (empty($extracted_folders)) {
            throw new Exception('No extracted folders found');
        }

        $source_dir = $extracted_folders[0];
        $install_dir = dirname(__DIR__);

        // Create backup
        $backup_dir = sys_get_temp_dir() . '/qa_reporter_backup_' . date('Y-m-d_H-i-s');
        if (!file_exists($backup_dir)) {
            mkdir($backup_dir, 0755, true);
        }

        // Files to exclude from update
        $exclude_files = [
            '.git',
            '.gitignore',
            'config.php',
            '.htaccess',
            'includes/config.php',
            'composer.json',
            'composer.lock',
            'vendor',
            'version.php'
        ];

        // Backup and copy files
        copyFiles($install_dir, $backup_dir, $exclude_files);
        copyFiles($source_dir, $install_dir, $exclude_files);

        // Update version file
        file_put_contents($install_dir . '/version.php', "<?php\nreturn '$latest_version';");

        // Clean up
        removeDir($temp_dir);

        $update_message = "Update to version $latest_version completed successfully!";
        $update_status = 'success';

        // Refresh page after 3 seconds to show updated content
        header("Refresh: 3; url=" . $_SERVER['PHP_SELF']);

    } catch (Exception $e) {
        $update_message = 'Update failed: ' . $e->getMessage();
        $update_status = 'danger';
    }
}

// Helper functions for file operations
function copyFiles($source, $destination, $exclude = []) {
    $source = rtrim($source, '/\\') . '/';
    $destination = rtrim($destination, '/\\') . '/';

    if (!is_dir($source)) {
        return false;
    }

    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $dir = dir($source);
    while (false !== ($file = $dir->read())) {
        if ($file === '.' || $file === '..') {
            continue;
        }

        $src_path = $source . $file;
        $dest_path = $destination . $file;
        $relative_path = substr($src_path, strlen($source));

        // Skip excluded files
        if (in_array($file, $exclude) || in_array($relative_path, $exclude)) {
            continue;
        }

        if (is_dir($src_path)) {
            copyFiles($src_path, $dest_path, $exclude);
        } else {
            copy($src_path, $dest_path);
        }
    }

    $dir->close();
    return true;
}

function removeDir($dir) {
    if (!file_exists($dir)) {
        return;
    }

    if (!is_dir($dir)) {
        unlink($dir);
        return;
    }

    $files = array_diff(scandir($dir), ['.', '..']);
    foreach ($files as $file) {
        $path = $dir . '/' . $file;
        if (is_dir($path)) {
            removeDir($path);
        } else {
            unlink($path);
        }
    }

    rmdir($dir);
}

// Include header
include_once '../includes/header.php';
?>

<div class="container mt-4">
    <div class="row">
        <div class="col">
            <h1>System Updates</h1>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($update_message)): ?>
                <div class="alert alert-<?php echo $update_status; ?>">
                    <?php echo htmlspecialchars($update_message); ?>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <h5>Version Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Current Version:</strong> <?php echo htmlspecialchars($current_version); ?></p>
                    <p><strong>Latest Version:</strong> <?php echo htmlspecialchars($latest_version); ?></p>

                    <?php if ($update_available): ?>
                        <div class="alert alert-info">
                            <h5>Update Available!</h5>
                            <p>A new version (<?php echo htmlspecialchars($latest_version); ?>) is available.</p>

                            <?php if (!empty($release_notes)): ?>
                                <h6>Release Notes:</h6>
                                <div class="release-notes">
                                    <?php echo nl2br(htmlspecialchars($release_notes)); ?>
                                </div>
                            <?php endif; ?>

                            <form method="post" class="mt-3">
                                <button type="submit" name="update" class="btn btn-primary">
                                    <i class="bi bi-cloud-download"></i> Update Now
                                </button>
                            </form>
                        </div>
                    <?php else: ?>
                        <div class="alert alert-success">
                            <p>Your system is up to date.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <a href="../dashboard.php" class="btn btn-secondary">
                <i class="bi bi-arrow-left"></i> Back to Dashboard
            </a>

            <?php if (!$debug): ?>
                <a href="?debug=1" class="btn btn-outline-info ms-2">
                    <i class="bi bi-bug"></i> Show Debug Info
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

