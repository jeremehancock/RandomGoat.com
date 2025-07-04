<?php

session_start();

// Configuration
$dataFile = '../data/goats.json';
$goatsDir = '../goats/';
$allowedPerPage = [12, 24, 48, 60];
$perPage = isset($_GET['perPage']) && in_array((int) $_GET['perPage'], $allowedPerPage) ? (int) $_GET['perPage'] : 12;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Admin credentials from environment variables
$adminUsername = $_ENV['ADMIN_USERNAME'] ?? getenv('ADMIN_USERNAME');
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD');

// GitHub configuration from environment variables
$githubToken = $_ENV['GITHUB_TOKEN'] ?? getenv('GITHUB_TOKEN');
$githubOwner = $_ENV['GITHUB_OWNER'] ?? getenv('GITHUB_OWNER');
$githubRepo = $_ENV['GITHUB_REPO'] ?? getenv('GITHUB_REPO');
$githubBranch = $_ENV['GITHUB_BRANCH'] ?? getenv('GITHUB_BRANCH') ?? 'main';

// Check if user is logged in
$isLoggedIn = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    if ($username === $adminUsername && $password === $adminPassword) {
        $_SESSION['logged_in'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

// Ensure data directory exists
$dataDir = dirname($dataFile);
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Ensure goats directory exists
if (!is_dir($goatsDir)) {
    mkdir($goatsDir, 0755, true);
}

// Ensure goats.json exists
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, '[]');
}

// Function to check if URL is a Giphy URL
function isGiphyUrl($url)
{
    return (strpos(strtolower($url), 'giphy.com') !== false || strpos(strtolower($url), 'media.giphy.com') !== false);
}

// Function to generate hash-based ID from URL
function generateUrlHash($url, $length = 12)
{
    // Create MD5 hash of the URL and take first N characters
    $hash = md5(trim(strtolower($url)));
    return substr($hash, 0, $length);
}

// Function to extract Giphy ID from URL
function extractGiphyId($url)
{
    $url = trim($url);

    // Remove any trailing parameters or fragments
    $url = strtok($url, '?');
    $url = strtok($url, '#');

    // Check if URL contains a dash (has extra text after ID)
    if (strpos($url, '-') !== false) {
        // Get text after last dash
        $parts = explode('-', $url);
        return end($parts);
    } else {
        // Get text after last slash
        $parts = explode('/', $url);
        return end($parts);
    }
}

// Function to parse tags string into array
function parseTags($tagsString)
{
    if (empty(trim($tagsString))) {
        return [];
    }

    // Split by comma, trim each tag, remove empty tags, and make lowercase for consistency
    $tags = array_map('trim', explode(',', $tagsString));
    $tags = array_filter($tags, function ($tag) {
        return !empty($tag);
    });
    $tags = array_map('strtolower', $tags);

    // Remove duplicates and return
    return array_unique($tags);
}

// Function to download GIF from any URL
function downloadGifFromUrl($url, $destinationPath)
{
    $url = trim($url);

    // Validate URL format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return ['success' => false, 'error' => 'Invalid URL format.'];
    }

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);

    $gifData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $error = curl_error($ch);
    curl_close($ch);

    // Check for cURL errors
    if ($error) {
        return ['success' => false, 'error' => "Network error: {$error}"];
    }

    // Check HTTP response code
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP error: {$httpCode}. Could not download from the provided URL."];
    }

    // Check if content type is appropriate (allow various image types that might be GIFs)
    $allowedTypes = ['image/gif'];
    $isValidContentType = false;
    foreach ($allowedTypes as $type) {
        if (strpos(strtolower($contentType), $type) !== false) {
            $isValidContentType = true;
            break;
        }
    }

    // Verify we got actual GIF data by checking file signature
    if (empty($gifData)) {
        return ['success' => false, 'error' => "No data received from URL."];
    }

    // Check for GIF signature (GIF87a or GIF89a) or other image formats
    $signature = substr($gifData, 0, 6);
    $isGif = (substr($signature, 0, 3) === 'GIF');

    if (!$isGif) {
        return ['success' => false, 'error' => "File does not appear to be a valid image format. Please ensure the URL points directly to a GIF file."];
    }

    // Try to save the file
    $result = file_put_contents($destinationPath, $gifData);

    if ($result === false) {
        return ['success' => false, 'error' => "Failed to save file to local directory."];
    }

    $fileType = 'GIF';

    return [
        'success' => true,
        'size' => $result,
        'data' => $gifData,
        'type' => $fileType
    ];
}

// Function to download GIF from Giphy
function downloadGifFromGiphy($giphyId, $destinationPath)
{
    $giphyUrl = "https://media.giphy.com/media/{$giphyId}/giphy.gif";

    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $giphyUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HEADER, false);

    $gifData = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    // Check for cURL errors
    if ($error) {
        return ['success' => false, 'error' => "Network error: {$error}"];
    }

    // Check HTTP response code
    if ($httpCode !== 200) {
        return ['success' => false, 'error' => "HTTP error: {$httpCode}. GIF may not exist on Giphy."];
    }

    // Verify we got actual GIF data
    if (empty($gifData) || substr($gifData, 0, 3) !== 'GIF') {
        return ['success' => false, 'error' => "Invalid GIF data received from Giphy."];
    }

    // Try to save the file
    $result = file_put_contents($destinationPath, $gifData);

    if ($result === false) {
        return ['success' => false, 'error' => "Failed to save GIF to local directory."];
    }

    return ['success' => true, 'size' => $result, 'data' => $gifData];
}

// GitHub API Functions
function githubApiRequest($endpoint, $method = 'GET', $data = null, $token = null)
{
    $ch = curl_init();

    $headers = [
        'Accept: application/vnd.github.v3+json',
        'User-Agent: Random-Goat-Admin/1.0'
    ];

    if ($token) {
        $headers[] = "Authorization: token {$token}";
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.github.com{$endpoint}",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_TIMEOUT => 30
    ]);

    // Send JSON data for POST, PUT, and DELETE requests
    if ($data && ($method === 'POST' || $method === 'PUT' || $method === 'DELETE')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        $headers[] = 'Content-Type: application/json';
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        return ['success' => false, 'error' => "cURL error: {$error}"];
    }

    $decoded = json_decode($response, true);

    if ($httpCode >= 200 && $httpCode < 300) {
        return ['success' => true, 'data' => $decoded];
    } else {
        $errorMsg = $decoded['message'] ?? "HTTP {$httpCode}";
        $fullError = $errorMsg;

        // Add more context for debugging
        if (isset($decoded['errors'])) {
            $fullError .= " - " . json_encode($decoded['errors']);
        }

        return ['success' => false, 'error' => $fullError, 'http_code' => $httpCode, 'response' => $decoded];
    }
}

function getFileFromGitHub($owner, $repo, $path, $branch, $token)
{
    $endpoint = "/repos/{$owner}/{$repo}/contents/{$path}?ref={$branch}";
    return githubApiRequest($endpoint, 'GET', null, $token);
}

function commitFileToGitHub($owner, $repo, $path, $content, $message, $branch, $token, $sha = null)
{
    $endpoint = "/repos/{$owner}/{$repo}/contents/{$path}";

    $data = [
        'message' => $message,
        'content' => base64_encode($content),
        'branch' => $branch
    ];

    if ($sha) {
        $data['sha'] = $sha;
    }

    return githubApiRequest($endpoint, 'PUT', $data, $token);
}

function addGoatToGitHub($goatId, $gifData, $goatsData, $githubOwner, $githubRepo, $githubBranch, $githubToken)
{
    $results = ['gif' => null, 'json' => null];

    // 1. Commit the GIF file
    $gifPath = "goats/{$goatId}.gif";
    $gifResult = commitFileToGitHub(
        $githubOwner,
        $githubRepo,
        $gifPath,
        $gifData,
        "Add goat GIF: {$goatId}",
        $githubBranch,
        $githubToken
    );

    $results['gif'] = $gifResult;

    if (!$gifResult['success']) {
        return $results;
    }

    // 2. Get current goats.json file to get its SHA
    $jsonPath = "data/goats.json";
    $currentFile = getFileFromGitHub($githubOwner, $githubRepo, $jsonPath, $githubBranch, $githubToken);

    $currentSha = null;
    if ($currentFile['success'] && isset($currentFile['data']['sha'])) {
        $currentSha = $currentFile['data']['sha'];
    }

    // 3. Commit the updated goats.json
    $jsonContent = json_encode($goatsData, JSON_PRETTY_PRINT);
    $jsonResult = commitFileToGitHub(
        $githubOwner,
        $githubRepo,
        $jsonPath,
        $jsonContent,
        "Add goat to list: {$goatId}",
        $githubBranch,
        $githubToken,
        $currentSha
    );

    $results['json'] = $jsonResult;

    return $results;
}

function updateGoatTagsOnGitHub($goatsData, $githubOwner, $githubRepo, $githubBranch, $githubToken, $goatId)
{
    // Get current goats.json file to get its SHA
    $jsonPath = "data/goats.json";
    $currentFile = getFileFromGitHub($githubOwner, $githubRepo, $jsonPath, $githubBranch, $githubToken);

    $currentSha = null;
    if ($currentFile['success'] && isset($currentFile['data']['sha'])) {
        $currentSha = $currentFile['data']['sha'];
    }

    // Commit the updated goats.json
    $jsonContent = json_encode($goatsData, JSON_PRETTY_PRINT);
    return commitFileToGitHub(
        $githubOwner,
        $githubRepo,
        $jsonPath,
        $jsonContent,
        "Update tags for goat: {$goatId}",
        $githubBranch,
        $githubToken,
        $currentSha
    );
}

function deleteGoatFromGitHub($goatId, $goatsData, $githubOwner, $githubRepo, $githubBranch, $githubToken)
{
    $results = ['gif' => null, 'json' => null];

    // 1. Delete the GIF file
    $gifPath = "goats/{$goatId}.gif";
    $gifFile = getFileFromGitHub($githubOwner, $githubRepo, $gifPath, $githubBranch, $githubToken);

    if ($gifFile['success'] && isset($gifFile['data']['sha'])) {
        $deleteData = [
            'message' => "Delete goat GIF: {$goatId}",
            'sha' => $gifFile['data']['sha'],
            'branch' => $githubBranch
        ];

        $deleteEndpoint = "/repos/{$githubOwner}/{$githubRepo}/contents/{$gifPath}";
        $results['gif'] = githubApiRequest($deleteEndpoint, 'DELETE', $deleteData, $githubToken);
    } else {
        $errorMsg = 'GIF file not found in repository';
        if (!$gifFile['success']) {
            $errorMsg .= ': ' . $gifFile['error'];
        }
        $results['gif'] = ['success' => false, 'error' => $errorMsg];
    }

    // 2. Update goats.json (only if GIF deletion was successful)
    if ($results['gif']['success']) {
        $jsonPath = "data/goats.json";
        $currentFile = getFileFromGitHub($githubOwner, $githubRepo, $jsonPath, $githubBranch, $githubToken);

        if ($currentFile['success'] && isset($currentFile['data']['sha'])) {
            $jsonContent = json_encode($goatsData, JSON_PRETTY_PRINT);
            $results['json'] = commitFileToGitHub(
                $githubOwner,
                $githubRepo,
                $jsonPath,
                $jsonContent,
                "Remove goat from list: {$goatId}",
                $githubBranch,
                $githubToken,
                $currentFile['data']['sha']
            );
        } else {
            $errorMsg = 'goats.json file not found in repository';
            if (!$currentFile['success']) {
                $errorMsg .= ': ' . $currentFile['error'];
            }
            $results['json'] = ['success' => false, 'error' => $errorMsg];
        }
    } else {
        // Skip json update if GIF deletion failed
        $results['json'] = ['success' => false, 'error' => 'Skipped due to GIF deletion failure'];
    }

    return $results;
}

// Function to read goat data from JSON
function readGoatsData($file)
{
    if (!file_exists($file)) {
        return [];
    }

    $content = file_get_contents($file);
    $data = json_decode($content, true);

    if ($data === null) {
        // If JSON is invalid, return empty array
        return [];
    }

    // Ensure each goat has required fields
    $goats = [];
    foreach ($data as $goat) {
        if (isset($goat['id'])) {
            $shortId = isset($goat['short_id']) ? $goat['short_id'] : createShortId($goat['id']);
            $goats[] = [
                'id' => $goat['id'],
                'short_id' => $shortId,
                'tags' => isset($goat['tags']) ? $goat['tags'] : []
            ];
        }
    }

    return $goats;
}

// Function to save goat data to JSON
function saveGoatsData($file, $goatsData)
{
    $jsonContent = json_encode($goatsData, JSON_PRETTY_PRINT);
    return file_put_contents($file, $jsonContent) !== false;
}

// Function to find goat by ID
function findGoatById($goatsData, $id)
{
    foreach ($goatsData as $index => $goat) {
        if ($goat['id'] === $id) {
            return $index;
        }
    }
    return false;
}

// Function to delete goat files
function deleteGoatFiles($goatId, $goatsDir)
{
    $gifPath = $goatsDir . $goatId . '.gif';
    if (file_exists($gifPath)) {
        return unlink($gifPath);
    }
    return true; // If file doesn't exist, consider it successfully "deleted"
}

// Check GitHub configuration
function checkGitHubConfig($githubToken, $githubOwner, $githubRepo)
{
    if (empty($githubToken) || empty($githubOwner) || empty($githubRepo)) {
        return false;
    }
    return true;
}


function createShortId($longId)
{
    // Create SHA256 hash and take first 5 characters
    $hash = hash('sha256', $longId);
    return substr($hash, 0, 5);
}

// Handle form submissions (only if logged in)
$message = '';
$messageType = '';

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $goatsData = readGoatsData($dataFile);

        switch ($_POST['action']) {
            case 'add':
                $url = trim($_POST['url'] ?? '');
                $tagsString = trim($_POST['tags'] ?? '');
                $tags = parseTags($tagsString);

                if ($url) {
                    // Determine if this is a Giphy URL or direct URL
                    if (isGiphyUrl($url)) {
                        // Handle as Giphy URL
                        $id = extractGiphyId($url);
                        if ($id && findGoatById($goatsData, $id) === false) {
                            // Try to download the GIF from Giphy
                            $gifPath = $goatsDir . $id . '.gif';
                            $downloadResult = downloadGifFromGiphy($id, $gifPath);
                            $shortId = createShortId($id);
                            if ($downloadResult['success']) {
                                // Add to local goats.json
                                $goatsData[] = ['id' => $id, 'short_id' => $shortId, 'tags' => $tags];
                                $localSaveSuccess = saveGoatsData($dataFile, $goatsData);

                                if ($localSaveSuccess) {
                                    $sizeKB = round($downloadResult['size'] / 1024, 1);
                                    $tagsText = !empty($tags) ? ' (Tags: ' . implode(', ', $tags) . ')' : '';
                                    $message = "Goat added! ID: {$id} (Size: {$sizeKB} KB){$tagsText}";

                                    // Try to commit to GitHub if configured
                                    if (checkGitHubConfig($githubToken, $githubOwner, $githubRepo)) {
                                        $githubResults = addGoatToGitHub(
                                            $id,
                                            $downloadResult['data'],
                                            $goatsData,
                                            $githubOwner,
                                            $githubRepo,
                                            $githubBranch,
                                            $githubToken
                                        );

                                        if ($githubResults['gif']['success'] && $githubResults['json']['success']) {
                                            $message .= " ✅ Successfully committed to GitHub!";
                                            $messageType = 'success';
                                        } else {
                                            $message .= " ⚠️ Local save successful, but GitHub sync failed: ";
                                            if (!$githubResults['gif']['success']) {
                                                $message .= "GIF upload failed (" . $githubResults['gif']['error'] . ") ";
                                            }
                                            if (!$githubResults['json']['success']) {
                                                $message .= "goats.json update failed (" . $githubResults['json']['error'] . ")";
                                            }
                                            $messageType = 'warning';
                                        }
                                    } else {
                                        $message .= " (GitHub sync disabled - missing configuration)";
                                        $messageType = 'success';
                                    }
                                } else {
                                    // If saving to file failed, clean up the downloaded GIF
                                    if (file_exists($gifPath)) {
                                        unlink($gifPath);
                                    }
                                    $message = "Error saving goat to database file.";
                                    $messageType = 'error';
                                }
                            } else {
                                $message = "Failed to download GIF from Giphy: " . $downloadResult['error'];
                                $messageType = 'error';
                            }
                        } elseif (findGoatById($goatsData, $id) !== false) {
                            $message = "This Giphy goat already exists in the gallery! ID: {$id}";
                            $messageType = 'warning';
                        } else {
                            $message = "Invalid Giphy URL. Could not extract ID.";
                            $messageType = 'error';
                        }
                    } else {
                        // Handle as direct URL
                        $urlHash = generateUrlHash($url, 12);
                        $id = 'url-' . $urlHash;
                        $shortId = createShortId($id);
                        // Check if this URL has already been added
                        if (findGoatById($goatsData, $id) !== false) {
                            $message = "This URL has already been added to the gallery! ID: {$id}";
                            $messageType = 'warning';
                        } else {
                            // Try to download the file
                            $gifPath = $goatsDir . $id . '.gif';
                            $downloadResult = downloadGifFromUrl($url, $gifPath);

                            if ($downloadResult['success']) {
                                // Add to local goats.json
                                $goatsData[] = ['id' => $id, 'short_id' => $shortId, 'tags' => $tags];
                                $localSaveSuccess = saveGoatsData($dataFile, $goatsData);

                                if ($localSaveSuccess) {
                                    $sizeKB = round($downloadResult['size'] / 1024, 1);
                                    $fileType = $downloadResult['type'] ?? 'Image';
                                    $tagsText = !empty($tags) ? ' (Tags: ' . implode(', ', $tags) . ')' : '';
                                    $message = "Goat added! ID: {$id} (Size: {$sizeKB} KB, Type: {$fileType}){$tagsText}";

                                    // Try to commit to GitHub if configured
                                    if (checkGitHubConfig($githubToken, $githubOwner, $githubRepo)) {
                                        $githubResults = addGoatToGitHub(
                                            $id,
                                            $downloadResult['data'],
                                            $goatsData,
                                            $githubOwner,
                                            $githubRepo,
                                            $githubBranch,
                                            $githubToken
                                        );

                                        if ($githubResults['gif']['success'] && $githubResults['json']['success']) {
                                            $message .= " <br/>✅ Successfully committed to GitHub!";
                                            $messageType = 'success';
                                        } else {
                                            $message .= " ⚠️ Local save successful, but GitHub sync failed: ";
                                            if (!$githubResults['gif']['success']) {
                                                $message .= "File upload failed (" . $githubResults['gif']['error'] . ") ";
                                            }
                                            if (!$githubResults['json']['success']) {
                                                $message .= "goats.json update failed (" . $githubResults['json']['error'] . ")";
                                            }
                                            $messageType = 'warning';
                                        }
                                    } else {
                                        $message .= " (GitHub sync disabled - missing configuration)";
                                        $messageType = 'success';
                                    }
                                } else {
                                    // If saving to file failed, clean up the downloaded file
                                    if (file_exists($gifPath)) {
                                        unlink($gifPath);
                                    }
                                    $message = "Error saving goat to database file.";
                                    $messageType = 'error';
                                }
                            } else {
                                $message = "Failed to download file: " . $downloadResult['error'];
                                $messageType = 'error';
                            }
                        }
                    }
                } else {
                    $message = "Please enter a URL.";
                    $messageType = 'error';
                }
                break;

            case 'update_tags':
                $idToUpdate = $_POST['goat_id'] ?? '';
                $tagsString = trim($_POST['tags'] ?? '');
                $newTags = parseTags($tagsString);
                $goatIndex = findGoatById($goatsData, $idToUpdate);

                if ($goatIndex !== false) {
                    // Update tags in local goats.json
                    $oldTags = $goatsData[$goatIndex]['tags'];
                    $goatsData[$goatIndex]['tags'] = $newTags;
                    $localSaveSuccess = saveGoatsData($dataFile, $goatsData);

                    if ($localSaveSuccess) {
                        $tagsText = !empty($newTags) ? implode(', ', $newTags) : 'none';
                        $message = "Tags updated! New tags: {$tagsText}";

                        // Try to update on GitHub if configured
                        if (checkGitHubConfig($githubToken, $githubOwner, $githubRepo)) {
                            $githubResult = updateGoatTagsOnGitHub(
                                $goatsData,
                                $githubOwner,
                                $githubRepo,
                                $githubBranch,
                                $githubToken,
                                $idToUpdate
                            );

                            if ($githubResult['success']) {
                                $message .= " ✅ Successfully synced to GitHub!";
                                $messageType = 'success';
                            } else {
                                $message .= " ⚠️ Local update successful, but GitHub sync failed: " . $githubResult['error'];
                                $messageType = 'warning';
                            }
                        } else {
                            $message .= " (GitHub sync disabled - missing configuration)";
                            $messageType = 'success';
                        }
                    } else {
                        // Revert the changes if save failed
                        $goatsData[$goatIndex]['tags'] = $oldTags;
                        $message = "Error updating tags in database file.";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Goat not found.";
                    $messageType = 'error';
                }
                break;

            case 'delete':
                $idToDelete = $_POST['goat_id'] ?? '';
                $goatIndex = findGoatById($goatsData, $idToDelete);

                if ($goatIndex !== false) {
                    // Remove from local goats.json
                    array_splice($goatsData, $goatIndex, 1);
                    $localSaveSuccess = saveGoatsData($dataFile, $goatsData);

                    if ($localSaveSuccess) {
                        // Delete local GIF file
                        $deleteFileResult = deleteGoatFiles($idToDelete, $goatsDir);

                        $message = "Goat deleted!";

                        // Try to delete from GitHub if configured
                        if (checkGitHubConfig($githubToken, $githubOwner, $githubRepo)) {
                            $githubResults = deleteGoatFromGitHub(
                                $idToDelete,
                                $goatsData,
                                $githubOwner,
                                $githubRepo,
                                $githubBranch,
                                $githubToken
                            );

                            if ($githubResults['gif']['success'] && $githubResults['json']['success']) {
                                $message .= " ✅ Successfully removed from GitHub!";
                                $messageType = 'success';
                            } else {
                                $message .= " ⚠️ Local deletion successful, but GitHub sync failed: ";
                                if (!$githubResults['gif']['success']) {
                                    $message .= "GIF deletion failed (" . $githubResults['gif']['error'] . ") ";
                                }
                                if (!$githubResults['json']['success']) {
                                    $message .= "goats.json update failed (" . $githubResults['json']['error'] . ")";
                                }
                                $messageType = 'warning';
                            }
                        } else {
                            $message .= " (GitHub sync disabled - missing configuration)";
                            $messageType = 'success';
                        }

                        if (!$deleteFileResult) {
                            $message .= " Note: Local GIF file could not be deleted.";
                        }
                    } else {
                        $message = "Error deleting goat from file.";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Goat not found.";
                    $messageType = 'error';
                }
                break;
        }
    }
}

// Get current goat data and filter for pagination
$allGoatsData = $isLoggedIn ? readGoatsData($dataFile) : [];

// Filter goats based on search
$filteredGoatsData = $allGoatsData;
if ($search && $isLoggedIn) {
    $searchLower = strtolower($search);
    $filteredGoatsData = array_filter($allGoatsData, function ($goat) use ($searchLower) {
        // Search in ID
        if (stripos($goat['id'], $searchLower) !== false) {
            return true;
        }

        // Search in short ID
        if (stripos($goat['short_id'], $searchLower) !== false) {
            return true;
        }

        // Search in tags
        foreach ($goat['tags'] as $tag) {
            if (stripos($tag, $searchLower) !== false) {
                return true;
            }
        }

        return false;
    });
}

$totalGoats = count($filteredGoatsData);
$totalPages = ceil($totalGoats / $perPage);
$offset = ($page - 1) * $perPage;
$currentGoats = array_slice($filteredGoatsData, $offset, $perPage);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Goat Admin</title>
    <meta charset="UTF-8">
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐐</text></svg>"
        type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Looking for random goat gifs? Look no further!" />
    <!-- Performance hints for faster loading -->
    <link rel="preconnect" href="https://media.giphy.com">
    <link rel="dns-prefetch" href="https://media.giphy.com">
    <style>
        :root {
            --bg-primary: #0f0f0f;
            --bg-secondary: #1a1a1a;
            --bg-tertiary: #2a2a2a;
            --text-primary: #ffffff;
            --text-secondary: #b3b3b3;
            --text-muted: #6b7280;
            --accent-primary: #5b21b6;
            --accent-hover: #4c1d95;
            --accent-secondary: #6366f1;
            --success: #059669;
            --success-hover: #047857;
            --error: #dc2626;
            --error-hover: #b91c1c;
            --warning: #d97706;
            --warning-hover: #b45309;
            --border: #374151;
            --shadow: rgba(0, 0, 0, 0.3);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .header {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
        }

        .header-content {
            flex: 1;
            min-width: 0;
            /* Allows text to wrap properly */
        }

        .header-content h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 2rem;
        }

        .header-wrapper {
            display: flex;
            justify-content: space-between;
            width: 100%;
            flex-direction: column;
            gap: 12px;
        }

        .header-bottom-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
        }

        .stats {
            color: var(--text-secondary);
            font-size: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
        }

        .stats-text {
            flex: 1;
        }

        /* Per page selector in stats */
        .stats .per-page-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stats .per-page-container label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-primary);
            text-align: center;
            font-size: 12px;
        }

        .stats .per-page-container select {
            width: 100%;
            max-width: 100px;
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
            text-align: center;
        }

        .stats .per-page-container select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.15);
        }

        .github-status {
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            height: max-content;
        }

        .github-status.enabled {
            background: rgba(5, 150, 105, 0.15);
            color: #10b981;
            border: 1px solid rgba(5, 150, 105, 0.3);
            align-self: flex-end;
        }

        .github-status.disabled {
            background: rgba(217, 119, 6, 0.15);
            color: #fbbf24;
            border: 1px solid rgba(217, 119, 6, 0.3);
        }

        /* Per page selector in header */
        .per-page-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .per-page-container label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-primary);
            text-align: center;
            font-size: 12px;
        }

        .per-page-container select {
            width: 100%;
            max-width: 100px;
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
            text-align: center;
        }

        .per-page-container select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.15);
        }

        .logout-btn {
            background: var(--error);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            box-shadow: 0 3px 6px rgba(220, 38, 38, 0.4);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            width: 40px;
            height: 40px;
            flex-shrink: 0;
            align-self: flex-start;
            position: absolute;
            right: 0;
        }

        .logout-btn:hover {
            background: var(--error-hover);
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(220, 38, 38, 0.5);
        }

        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid;
        }

        .message.success {
            background: rgba(5, 150, 105, 0.15);
            color: #10b981;
            border-color: var(--success);
        }

        .message.error {
            background: rgba(220, 38, 38, 0.15);
            color: #f87171;
            border-color: var(--error);
        }

        .message.warning {
            background: rgba(217, 119, 6, 0.15);
            color: #fbbf24;
            border-color: var(--warning);
        }

        .controls {
            background: var(--bg-secondary);
            padding: 24px;
            border-radius: 12px;
            box-shadow: 0 4px 6px var(--shadow);
            margin-bottom: 20px;
            border: 1px solid var(--border);
        }

        .controls-grid {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 15px;
            align-items: center;
            justify-content: center;
            width: 100%;
        }

        .clear-search-container {
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-btn {
            background: var(--accent-primary);
            color: #ffffff;
            padding: 16px 24px;
            border: none;
            border-radius: 10px;
            cursor: pointer;
            font-size: 15px;
            font-weight: 600;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 8px rgba(91, 33, 182, 0.3);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            min-height: 56px;
            flex: 1;
            white-space: nowrap;
        }

        .action-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(91, 33, 182, 0.4);
        }

        .action-btn.search {
            background: var(--success);
            box-shadow: 0 4px 8px rgba(5, 150, 105, 0.3);
        }

        .action-btn.search:hover {
            background: var(--success-hover);
            box-shadow: 0 6px 12px rgba(5, 150, 105, 0.4);
        }

        /* Per page selector */
        .per-page-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        .per-page-container label {
            display: block;
            margin-bottom: 6px;
            font-weight: 500;
            color: var(--text-primary);
            text-align: center;
            font-size: 12px;
        }

        .per-page-container select {
            width: 100%;
            max-width: 100px;
            padding: 8px 12px;
            border: 2px solid var(--border);
            border-radius: 6px;
            font-size: 13px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
            text-align: center;
        }

        .per-page-container select:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.15);
        }

        /* Style select dropdown arrow */
        select {
            appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 4 5"><path fill="%23ffffff" d="M2 0L0 2h4zm0 5L0 3h4z"/></svg>');
            background-repeat: no-repeat;
            background-position: right 12px center;
            background-size: 12px;
            padding-right: 40px;
        }

        select option {
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 8px 12px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }

        input[type="url"],
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        input[type="url"]:focus,
        input[type="text"]:focus,
        input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.15);
        }

        .btn {
            background: var(--accent-primary);
            color: #ffffff;
            padding: 12px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 700;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 3px 6px rgba(91, 33, 182, 0.4);
            min-width: 110px;
            min-height: 44px;
            width: auto;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
        }

        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 5px 10px rgba(91, 33, 182, 0.5);
        }

        .btn.success {
            background: var(--success);
            box-shadow: 0 3px 6px rgba(5, 150, 105, 0.4);
            color: #ffffff;
        }

        .btn.success:hover {
            background: var(--success-hover);
            box-shadow: 0 5px 10px rgba(5, 150, 105, 0.5);
        }

        .btn.danger {
            background: var(--error);
            box-shadow: 0 3px 6px rgba(220, 38, 38, 0.4);
            color: #ffffff;
        }

        .btn.danger:hover {
            background: var(--error-hover);
            box-shadow: 0 5px 10px rgba(220, 38, 38, 0.5);
        }

        .btn.warning {
            background: var(--warning);
            color: #ffffff;
            box-shadow: 0 3px 6px rgba(217, 119, 6, 0.4);
        }

        .btn.warning:hover {
            background: var(--warning-hover);
            box-shadow: 0 5px 10px rgba(217, 119, 6, 0.5);
        }

        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            box-shadow: 0 3px 6px rgba(55, 65, 81, 0.3);
            font-weight: 600;
            min-width: 110px;
            min-height: 44px;
            flex-shrink: 0;
            padding: 12px 20px;
            border-radius: 8px;
            font-size: 14px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            box-sizing: border-box;
        }

        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--text-secondary);
            box-shadow: 0 5px 10px rgba(55, 65, 81, 0.4);
            color: var(--text-primary);
        }

        /* Optimized gallery for better performance */
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
            justify-content: center;
            /* Better rendering performance */
            contain: layout style;
        }

        /* Optimized goat items for better performance */
        .goat-item {
            max-width: 350px;
            width: 100%;
            justify-self: center;
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: visible;
            box-shadow: 0 4px 6px var(--shadow);
            border: 1px solid var(--border);
            /* Faster, smoother transitions */
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1),
                box-shadow 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            /* Hardware acceleration */
            transform: translateZ(0);
            will-change: transform;
            /* Better layout performance */
            contain: layout style;
        }

        .goat-item:hover {
            transform: translateY(-4px) translateZ(0);
            box-shadow: 0 8px 25px var(--shadow);
        }

        /* Optimized image container for better loading experience */
        .goat-image-container {
            position: relative;
            width: 100%;
            height: 220px;
            border-radius: 12px 12px 0 0;
            overflow: hidden;
            background: var(--bg-tertiary);
            /* Hardware acceleration for smoother transitions */
            transform: translateZ(0);
            will-change: transform;
        }

        /* OPTIMIZED image styles - MAXIMUM SPEED */
        .goat-gif {
            width: 100%;
            height: 100%;
            object-fit: cover;
            /* INSTANT transition - no delay */
            transition: opacity 0.05s ease;
            opacity: 0;
            /* Hardware acceleration */
            transform: translateZ(0);
            backface-visibility: hidden;
            /* Performance hint for browsers */
            will-change: opacity;
            /* Better image rendering */
            image-rendering: auto;
            image-rendering: crisp-edges;
            image-rendering: -webkit-optimize-contrast;
        }

        .goat-gif.loaded {
            opacity: 1;
        }

        .goat-gif.error {
            opacity: 0.5;
            filter: grayscale(100%);
        }

        /* INSTANT loading placeholder */
        .image-placeholder {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            /* Simple solid background - no animation for max speed */
            background: var(--bg-tertiary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 24px;
            opacity: 1;
            /* INSTANT transition */
            transition: opacity 0.05s ease;
            /* Hardware acceleration */
            transform: translateZ(0);
        }

        .image-placeholder.hidden {
            opacity: 0;
            pointer-events: none;
        }

        /* INSTANT error state transitions */
        .image-error {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--bg-tertiary);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-size: 14px;
            text-align: center;
            padding: 20px;
            opacity: 0;
            /* INSTANT transition */
            transition: opacity 0.05s ease;
            /* Hardware acceleration */
            transform: translateZ(0);
        }

        .image-error.visible {
            opacity: 1;
        }

        .image-error-icon {
            font-size: 32px;
            margin-bottom: 8px;
            opacity: 0.7;
        }

        /* INSTANT progress bar */
        .loading-progress {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0%;
            height: 3px;
            background: linear-gradient(90deg, var(--accent-primary), var(--accent-secondary));
            /* INSTANT transition */
            transition: width 0.05s ease;
            border-radius: 0 3px 0 0;
            /* Hardware acceleration */
            transform: translateZ(0);
            will-change: width;
        }

        /* Optimized retry button */
        .retry-btn {
            background: var(--accent-primary);
            color: #ffffff;
            border: none;
            padding: 6px 12px;
            border-radius: 4px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 8px;
            /* Faster transition */
            transition: all 0.15s ease;
            /* Hardware acceleration */
            transform: translateZ(0);
        }

        .retry-btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px) translateZ(0);
        }

        .retry-btn:active {
            transform: translateY(0) scale(0.98) translateZ(0);
        }

        .goat-info {
            padding: 20px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .goat-id {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 8px;
            background: var(--bg-tertiary);
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }

        .goat-short-id {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 15px;
            background: var(--bg-tertiary);
            padding: 8px 12px;
            border-radius: 6px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
            border: 1px solid rgba(91, 33, 182, 0.3);
            position: relative;
        }

        .id-text {
            flex: 1;
            word-break: break-all;
        }

        /* Optimized copy button animations */
        .copy-id-btn {
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 2px 4px;
            border-radius: 4px;
            font-size: 12px;
            /* Faster transition */
            transition: all 0.15s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 20px;
            height: 20px;
            flex-shrink: 0;
            /* Hardware acceleration */
            transform: translateZ(0);
        }

        .copy-id-btn:hover {
            background: var(--bg-secondary);
            color: var(--text-primary);
            transform: scale(1.1) translateZ(0);
        }

        .copy-id-btn:active {
            transform: scale(0.95) translateZ(0);
        }

        .copy-id-btn.copied {
            color: var(--success);
            animation: copySuccess 0.2s ease;
        }

        /* Faster copy success animation */
        @keyframes copySuccess {
            0% {
                transform: scale(1) translateZ(0);
            }

            50% {
                transform: scale(1.15) translateZ(0);
            }

            100% {
                transform: scale(1) translateZ(0);
            }
        }

        .goat-tags {
            margin-bottom: 15px;
        }

        .tag {
            display: inline-block;
            background: var(--accent-primary);
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin: 2px 4px 2px 0;
            text-transform: lowercase;
        }

        .goat-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            gap: 8px;
        }

        .goat-links {
            display: flex;
            gap: 8px;
            align-items: center;
        }

        .goat-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border-radius: 6px;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 1.5rem;
            font-weight: 700;
            border: 1px solid var(--border);
        }

        .goat-link.randomgoat {
            background: var(--accent-primary);
            color: #ffffff;
            box-shadow: 0 2px 4px rgba(91, 33, 182, 0.3);
        }

        .goat-link.randomgoat:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
            box-shadow: 0 3px 6px rgba(91, 33, 182, 0.4);
        }

        .goat-action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .btn-small {
            padding: 8px 12px;
            font-size: 12px;
            min-width: auto;
            min-height: 32px;
            border-radius: 6px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* Custom Tooltip Styles */
        .tooltip {
            position: relative;
            z-index: 1001;
        }

        .tooltip::before {
            content: attr(data-tooltip);
            position: absolute;
            bottom: 120%;
            left: 50%;
            transform: translateX(-50%);
            background: var(--bg-primary);
            color: var(--text-primary);
            padding: 8px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
            border: 1px solid var(--border);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1002;
            pointer-events: none;
        }

        .tooltip::after {
            content: '';
            position: absolute;
            bottom: 112%;
            left: 50%;
            transform: translateX(-50%);
            width: 0;
            height: 0;
            border-left: 5px solid transparent;
            border-right: 5px solid transparent;
            border-top: 5px solid var(--bg-primary);
            opacity: 0;
            visibility: hidden;
            transition: all 0.2s ease;
            z-index: 1002;
            pointer-events: none;
        }

        .tooltip:hover::before,
        .tooltip:hover::after {
            opacity: 1;
            visibility: visible;
        }

        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 12px;
            margin: 40px 0;
        }

        .pagination a,
        .pagination span {
            padding: 10px 16px;
            border: 2px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            border-radius: 8px;
            transition: all 0.3s;
            background: var(--bg-secondary);
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(55, 65, 81, 0.2);
            min-width: 44px;
            min-height: 44px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .pagination a:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(91, 33, 182, 0.4);
            color: #ffffff;
        }

        .pagination .current {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            box-shadow: 0 2px 4px rgba(91, 33, 182, 0.3);
            color: #ffffff;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 80px 20px;
            color: var(--text-secondary);
        }

        .empty-state h3 {
            margin-bottom: 12px;
            font-size: 1.5rem;
            color: var(--text-primary);
        }

        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(4px);
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.3s ease-out;
        }

        /* Disable interactions with background when modal is open */
        body.modal-open {
            overflow: hidden;
        }

        body.modal-open .container {
            pointer-events: none;
        }

        body.modal-open .modal {
            pointer-events: all;
        }

        .modal-content {
            background: var(--bg-secondary);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            max-width: 500px;
            width: 90%;
            animation: slideIn 0.3s ease-out;
        }

        .modal-header {
            text-align: center;
            margin-bottom: 24px;
        }

        .modal-header h2 {
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .modal-header p {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .modal-buttons {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }

        .login-error {
            color: var(--error);
            font-size: 14px;
            margin-top: 12px;
            text-align: center;
        }

        /* Form modal specific styles */
        .form-modal .modal-content {
            max-width: 600px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group:last-of-type {
            margin-bottom: 0;
        }

        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 24px;
        }

        .form-buttons .btn {
            flex: 0 0 auto;
        }

        /* Edit Tags Modal Specific Styles */
        .edit-tags-modal .modal-content {
            max-width: 600px;
        }

        .current-tags-container {
            margin-bottom: 20px;
        }

        .current-tags-container h4 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
        }

        .current-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-bottom: 15px;
            min-height: 32px;
            padding: 12px;
            background: var(--bg-tertiary);
            border-radius: 8px;
            border: 1px solid var(--border);
        }

        .current-tags.empty {
            align-items: center;
            justify-content: center;
            color: var(--text-muted);
            font-style: italic;
            font-size: 14px;
        }

        .editable-tag {
            display: inline-flex;
            align-items: center;
            background: var(--accent-primary);
            color: #ffffff;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            gap: 6px;
            text-transform: lowercase;
            animation: tagFadeIn 0.2s ease-out;
        }

        .remove-tag-btn {
            background: rgba(255, 255, 255, 0.2);
            border: none;
            color: #ffffff;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
            transition: background 0.2s ease;
            line-height: 1;
        }

        .remove-tag-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }

        .add-tags-container {
            margin-bottom: 20px;
        }

        .add-tags-container h4 {
            margin-bottom: 10px;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 600;
        }

        #newTagsInput {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
        }

        #newTagsInput:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(91, 33, 182, 0.15);
        }

        @keyframes tagFadeIn {
            from {
                opacity: 0;
                transform: scale(0.8);
            }

            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        /* Loading state for Add Goat button */
        .btn.loading {
            position: relative;
            color: transparent;
            pointer-events: none;
        }

        .btn.loading::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-top: 2px solid #ffffff;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% {
                transform: translate(-50%, -50%) rotate(0deg);
            }

            100% {
                transform: translate(-50%, -50%) rotate(360deg);
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }

            to {
                opacity: 1;
            }
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Mobile App-like Design - Enhanced & Professional */
        @media (max-width: 768px) {
            :root {
                --mobile-primary: #0a0a0b;
                --mobile-surface: #161618;
                --mobile-surface-elevated: #1e1e20;
                --mobile-accent: #007AFF;
                --mobile-accent-hover: #0056CC;
                --mobile-text-primary: #ffffff;
                --mobile-text-secondary: #8e8e93;
                --mobile-border: #2c2c2e;
                --mobile-success: #30d158;
                --mobile-error: #ff453a;
                --mobile-warning: #ff9f0a;
                --mobile-shadow: 0 2px 20px rgba(0, 0, 0, 0.3);
                --mobile-shadow-elevated: 0 8px 32px rgba(0, 0, 0, 0.4);
            }

            body {
                background: var(--mobile-primary);
                font-family: -apple-system, BlinkMacSystemFont, 'SF Pro Display', 'Segoe UI', Roboto, sans-serif;
                padding: 0;
                margin: 0;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }

            .container {
                padding: 0;
                max-width: 100%;
                margin: 0;
                min-height: 100vh;
                display: flex;
                flex-direction: column;
            }

            .header {
                margin-bottom: 10px;
            }

            /* App-like Header with Status Bar */
            .header {
                background: linear-gradient(135deg, var(--mobile-surface) 0%, var(--mobile-surface-elevated) 100%);
                backdrop-filter: blur(20px);
                -webkit-backdrop-filter: blur(20px);
                border: none;
                border-radius: 0;
                margin: 0;
                padding: 20px 24px 24px 24px;
                box-shadow: var(--mobile-shadow);
                position: sticky;
                top: 0;
                z-index: 100;
                border-bottom: 1px solid var(--mobile-border);
            }

            .header-content {
                position: relative;
            }

            .header-content h1 {
                font-size: 1.75rem;
                font-weight: 700;
                margin-bottom: 8px;
            }

            .header-wrapper {
                gap: 16px;
            }

            .header-bottom-row {
                flex-direction: column;
                gap: 12px;
                align-items: stretch;
            }

            .stats {
                font-size: 14px;
                color: var(--mobile-text-secondary);
                font-weight: 500;
                padding: 12px 16px;
                background: rgba(255, 255, 255, 0.05);
                border-radius: 12px;
                border: 1px solid var(--mobile-border);
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 12px;
                order: 1;
            }

            .stats-text {
                flex: 1;
                font-size: 13px;
                line-height: 1.4;
            }

            .stats .per-page-container {
                flex-shrink: 0;
                padding: 0;
                background: transparent;
                border: none;
                border-radius: 0;
            }

            .stats .per-page-container label {
                font-size: 10px;
                color: var(--mobile-text-secondary);
                margin-bottom: 4px;
                font-weight: 500;
            }

            .stats .per-page-container select {
                background: var(--mobile-surface);
                border: 1px solid var(--mobile-border);
                color: var(--mobile-text-primary);
                border-radius: 6px;
                padding: 4px 8px;
                font-size: 12px;
                max-width: 60px;
                text-align: center;
            }

            .github-status {
                padding: 10px 16px;
                border-radius: 12px;
                font-size: 13px;
                font-weight: 600;
                border: 1px solid;
                text-align: center;
                order: 2;
            }

            .github-status.enabled {
                background: rgba(48, 209, 88, 0.1);
                color: var(--mobile-success);
                border-color: rgba(48, 209, 88, 0.3);
                align-self: center;
                margin-top: 10px;
            }

            .github-status.disabled {
                background: rgba(255, 159, 10, 0.1);
                color: var(--mobile-warning);
                border-color: rgba(255, 159, 10, 0.3);
            }

            .logout-btn {
                position: absolute;
                top: 12px;
                right: 16px;
                width: 36px;
                height: 36px;
                background: rgba(255, 69, 58, 0.9);
                backdrop-filter: blur(10px);
                border-radius: 18px;
                font-size: 14px;
                box-shadow: 0 4px 12px rgba(255, 69, 58, 0.3);
                z-index: 10;
            }

            .logout-btn:hover {
                background: var(--mobile-error);
                transform: scale(1.05);
            }

            /* Enhanced Controls Section */
            .controls {
                margin: 0 20px 20px 20px;
                padding: 20px;
                background: var(--mobile-surface);
                border-radius: 20px;
                border: 1px solid var(--mobile-border);
                box-shadow: var(--mobile-shadow);
            }

            .controls-grid {
                flex-direction: column;
                gap: 16px;
            }

            .action-buttons {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                width: 100%;
            }

            .action-btn {
                padding: 18px 20px;
                border-radius: 16px;
                font-size: 16px;
                font-weight: 600;
                min-height: 58px;
                border: none;
                position: relative;
                overflow: hidden;
                backdrop-filter: blur(10px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .action-btn:first-child {
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #5856d6 100%);
                box-shadow: 0 4px 20px rgba(0, 122, 255, 0.3);
            }

            .action-btn.search {
                background: linear-gradient(135deg, var(--mobile-success) 0%, #32d74b 100%);
                box-shadow: 0 4px 20px rgba(48, 209, 88, 0.3);
            }

            .action-btn:active {
                transform: scale(0.95);
            }

            .clear-search-container {
                width: 100%;
                margin-top: 8px;
            }

            .clear-search-container .btn {
                width: 100%;
                background: rgba(255, 255, 255, 0.1);
                color: var(--mobile-text-primary);
                border: 1px solid var(--mobile-border);
                border-radius: 14px;
                padding: 14px;
                backdrop-filter: blur(10px);
            }

            /* Enhanced Form Inputs */
            input[type="url"],
            input[type="text"],
            input[type="password"] {
                padding: 16px 20px;
                font-size: 16px;
                border-radius: 14px;
                border: 1px solid var(--mobile-border);
                background: rgba(255, 255, 255, 0.05);
                color: var(--mobile-text-primary);
                backdrop-filter: blur(10px);
                transition: all 0.3s ease;
            }

            input[type="url"]:focus,
            input[type="text"]:focus,
            input[type="password"]:focus {
                border-color: var(--mobile-accent);
                background: rgba(255, 255, 255, 0.08);
                box-shadow: 0 0 0 4px rgba(0, 122, 255, 0.1);
                transform: translateY(-2px);
            }

            /* Enhanced Buttons */
            .btn,
            .btn-secondary {
                padding: 16px 24px;
                font-size: 16px;
                font-weight: 600;
                border-radius: 14px;
                min-height: 52px;
                backdrop-filter: blur(10px);
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                border: none;
            }

            .btn {
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #5856d6 100%);
                box-shadow: 0 4px 20px rgba(0, 122, 255, 0.25);
            }

            .btn.success {
                background: linear-gradient(135deg, var(--mobile-success) 0%, #32d74b 100%);
                box-shadow: 0 4px 20px rgba(48, 209, 88, 0.25);
            }

            .btn.danger {
                background: linear-gradient(135deg, var(--mobile-error) 0%, #ff6b6b 100%);
                box-shadow: 0 4px 20px rgba(255, 69, 58, 0.25);
            }

            .btn.warning {
                background: linear-gradient(135deg, var(--mobile-warning) 0%, #ffcc02 100%);
                box-shadow: 0 4px 20px rgba(255, 159, 10, 0.25);
            }

            .btn-secondary {
                background: rgba(255, 255, 255, 0.1);
                color: var(--mobile-text-primary);
                border: 1px solid var(--mobile-border);
                box-shadow: 0 4px 20px rgba(255, 255, 255, 0.05);
            }

            .btn:active,
            .btn-secondary:active {
                transform: scale(0.95);
            }

            /* Enhanced Gallery Cards */
            .gallery {
                padding: 0 20px 200px 20px;
                grid-template-columns: 1fr;
                gap: 20px;
                margin-bottom: 0;
            }

            .goat-item {
                max-width: 100%;
                background: var(--mobile-surface);
                border-radius: 24px;
                border: 1px solid var(--mobile-border);
                box-shadow: var(--mobile-shadow);
                overflow: hidden;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            }

            .goat-item:active {
                transform: scale(0.98);
                box-shadow: var(--mobile-shadow-elevated);
            }

            .goat-image-container {
                height: 280px;
                border-radius: 24px 24px 0 0;
                background: linear-gradient(135deg, var(--mobile-surface) 0%, var(--mobile-surface-elevated) 100%);
            }

            .goat-info {
                padding: 20px;
            }

            .goat-id,
            .goat-short-id {
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid var(--mobile-border);
                border-radius: 12px;
                padding: 12px 16px;
                font-size: 12px;
                margin-bottom: 12px;
                backdrop-filter: blur(10px);
            }

            .goat-short-id {
                border-color: rgba(0, 122, 255, 0.3);
                background: rgba(0, 122, 255, 0.1);
            }

            .copy-id-btn {
                background: rgba(255, 255, 255, 0.1);
                border-radius: 8px;
                padding: 6px;
                min-width: 28px;
                height: 28px;
                color: var(--mobile-text-secondary);
                backdrop-filter: blur(5px);
                transition: all 0.2s ease;
            }

            .copy-id-btn:hover {
                background: rgba(255, 255, 255, 0.2);
                color: var(--mobile-text-primary);
                transform: scale(1.1);
            }

            .tag {
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #5856d6 100%);
                color: white;
                padding: 6px 12px;
                border-radius: 16px;
                font-size: 12px;
                font-weight: 600;
                margin: 4px 6px 4px 0;
                box-shadow: 0 2px 8px rgba(0, 122, 255, 0.25);
            }

            .goat-actions {
                margin-top: 16px;
                gap: 12px;
                align-items: stretch;
            }

            .goat-link {
                width: 48px;
                height: 48px;
                border-radius: 16px;
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #5856d6 100%);
                color: white;
                font-size: 20px;
                box-shadow: 0 4px 16px rgba(0, 122, 255, 0.3);
                border: none;
                transition: all 0.3s ease;
            }

            .goat-link:active {
                transform: scale(0.9);
            }

            .goat-action-buttons {
                flex: 1;
                gap: 8px;
            }

            .goat-action-buttons .btn {
                flex: 1;
                padding: 12px 16px;
                font-size: 14px;
                height: 48px;
                min-height: 48px;
                border-radius: 16px;
                font-weight: 600;
            }

            /* Enhanced Messages */
            .message {
                margin: 20px 20px 160px 20px;
                padding: 20px;
                border-radius: 16px;
                font-size: 15px;
                border: 1px solid;
                backdrop-filter: blur(10px);
                box-shadow: var(--mobile-shadow);
            }

            .message.success {
                background: rgba(48, 209, 88, 0.1);
                color: var(--mobile-success);
                border-color: rgba(48, 209, 88, 0.3);
            }

            .message.error {
                background: rgba(255, 69, 58, 0.1);
                color: var(--mobile-error);
                border-color: rgba(255, 69, 58, 0.3);
            }

            .message.warning {
                background: rgba(255, 159, 10, 0.1);
                color: var(--mobile-warning);
                border-color: rgba(255, 159, 10, 0.3);
            }

            /* Enhanced Pagination - Mobile Optimized */
            .pagination {
                padding: 16px 20px;
                margin: 0;
                gap: 6px;
                justify-content: center;
                background: var(--mobile-surface);
                border-top: 1px solid var(--mobile-border);
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                z-index: 50;
                backdrop-filter: blur(20px);
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
                overflow-x: auto;
                scrollbar-width: none;
                -ms-overflow-style: none;
                white-space: nowrap;
                min-height: 70px;
            }

            .pagination::-webkit-scrollbar {
                display: none;
            }

            .pagination a,
            .pagination span {
                padding: 8px 12px;
                min-width: 36px;
                min-height: 36px;
                border-radius: 10px;
                font-size: 13px;
                font-weight: 600;
                background: rgba(255, 255, 255, 0.1);
                border: 1px solid var(--mobile-border);
                color: var(--mobile-text-primary);
                transition: all 0.3s ease;
                backdrop-filter: blur(10px);
                flex-shrink: 0;
                display: inline-flex;
                align-items: center;
                justify-content: center;
            }

            .pagination a:active {
                transform: scale(0.95);
            }

            .pagination .current {
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #5856d6 100%);
                border-color: var(--mobile-accent);
                color: white;
                box-shadow: 0 4px 16px rgba(0, 122, 255, 0.3);
            }

            /* Enhanced Modals - Slide-up Style (Not Fullscreen) */
            .modal.show {
                padding: 0;
                align-items: flex-end;
                justify-content: center;
            }

            .modal-content {
                width: 100%;
                max-width: none;
                border-radius: 20px 20px 0 0;
                background: var(--mobile-primary);
                padding: 24px 24px 100px 24px;
                position: relative;
                animation: slideUpIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                border: 1px solid var(--mobile-border);
                box-shadow: 0 -10px 40px rgba(0, 0, 0, 0.5);
                max-height: 85vh;
                overflow-y: auto;
                margin-bottom: 0;
            }

            @keyframes slideUpIn {
                from {
                    transform: translateY(100%);
                    opacity: 0;
                }

                to {
                    transform: translateY(0);
                    opacity: 1;
                }
            }

            .modal-header {
                margin-bottom: 24px;
                padding-bottom: 16px;
                border-bottom: 1px solid var(--mobile-border);
                text-align: center;
            }

            .modal-header h2 {
                font-size: 1.25rem;
                font-weight: 700;
                margin-bottom: 8px;
                color: var(--mobile-text-primary);
            }

            .modal-header p {
                color: var(--mobile-text-secondary);
                font-size: 14px;
            }

            .form-buttons {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 16px 24px;
                background: var(--mobile-surface);
                border-top: 1px solid var(--mobile-border);
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                backdrop-filter: blur(20px);
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            }

            .modal-buttons {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                padding: 16px 24px;
                background: var(--mobile-surface);
                border-top: 1px solid var(--mobile-border);
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 12px;
                backdrop-filter: blur(20px);
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            }

            /* Enhanced Empty State */
            .empty-state {
                padding: 60px 20px 200px 20px;
                margin: 20px;
                background: var(--mobile-surface);
                border-radius: 20px;
                border: 1px solid var(--mobile-border);
                text-align: center;
                box-shadow: var(--mobile-shadow);
            }

            .empty-state h3 {
                font-size: 1.25rem;
                margin-bottom: 16px;
                color: var(--mobile-text-primary);
                font-weight: 700;
            }

            .empty-state p {
                font-size: 15px;
                line-height: 1.5;
                margin-bottom: 12px;
                color: var(--mobile-text-secondary);
            }

            /* Login Form Enhancements */
            .login-error {
                font-size: 14px;
                margin-top: 20px;
                padding: 16px 20px;
                background: rgba(255, 69, 58, 0.1);
                border-radius: 12px;
                border: 1px solid rgba(255, 69, 58, 0.3);
                color: var(--mobile-error);
                text-align: center;
            }

            /* Enhanced Tag Editing */
            .current-tags {
                min-height: 48px;
                padding: 12px 16px;
                background: rgba(255, 255, 255, 0.05);
                border: 1px solid var(--mobile-border);
                border-radius: 12px;
                backdrop-filter: blur(10px);
            }

            .editable-tag {
                font-size: 13px;
                padding: 6px 12px;
                gap: 6px;
                background: linear-gradient(135deg, var(--mobile-accent) 0%, #5856d6 100%);
                border-radius: 16px;
                box-shadow: 0 2px 8px rgba(0, 122, 255, 0.25);
            }

            .remove-tag-btn {
                width: 18px;
                height: 18px;
                font-size: 12px;
                background: rgba(255, 255, 255, 0.2);
                backdrop-filter: blur(5px);
            }

            #newTagsInput {
                padding: 16px 20px;
                font-size: 16px;
                border-radius: 12px;
                border: 1px solid var(--mobile-border);
                background: rgba(255, 255, 255, 0.05);
                backdrop-filter: blur(10px);
            }

            /* Safe area adjustments for notched devices */
            @supports(padding: max(0px)) {
                .header {
                    padding-top: max(20px, env(safe-area-inset-top));
                }

                body {
                    padding-bottom: max(0px, env(safe-area-inset-bottom));
                }

                .pagination {
                    padding-bottom: max(20px, calc(20px + env(safe-area-inset-bottom)));
                    height: auto;
                    min-height: 60px;
                }

                .form-buttons,
                .modal-buttons {
                    padding-bottom: max(24px, calc(24px + env(safe-area-inset-bottom)));
                }

                /* Extra padding for content areas on devices with safe area insets */
                .gallery {
                    margin-bottom: 80px;
                }

                .empty-state {
                    margin-bottom: 80px;
                }
            }

            /* Reduce motion for accessibility */
            @media (prefers-reduced-motion: reduce) {

                .goat-item,
                .action-btn,
                .btn,
                .btn-secondary,
                .modal-content {
                    transition: none !important;
                    animation: none !important;
                }

                .goat-item:active,
                .action-btn:active,
                .btn:active {
                    transform: none !important;
                }
            }

            /* Dark mode optimizations */
            @media (prefers-color-scheme: dark) {
                .header {
                    background: linear-gradient(135deg, rgba(22, 22, 24, 0.95) 0%, rgba(30, 30, 32, 0.95) 100%);
                }

                .pagination {
                    background: rgba(22, 22, 24, 0.95);
                }

                .form-buttons,
                .modal-buttons {
                    background: rgba(22, 22, 24, 0.95);
                }
            }

            /* High contrast mode support */
            @media (prefers-contrast: high) {

                .goat-item,
                .controls,
                .message {
                    border-width: 2px;
                }

                .btn,
                .action-btn {
                    border: 2px solid currentColor;
                }
            }

            /* Touch feedback improvements */
            .action-btn,
            .btn,
            .btn-secondary,
            .goat-link,
            .copy-id-btn {
                -webkit-tap-highlight-color: transparent;
                user-select: none;
                -webkit-user-select: none;
                -moz-user-select: none;
                touch-action: manipulation;
            }

            /* Improved scrolling */
            .container {
                -webkit-overflow-scrolling: touch;
                overflow-x: hidden;
                scroll-behavior: smooth;
            }

            /* Loading states */
            .btn.loading::after {
                border-color: rgba(255, 255, 255, 0.2);
                border-top-color: white;
            }
        }

        /* Tablet landscape optimizations */
        @media (max-width: 1024px) and (orientation: landscape) and (min-width: 769px) {
            .gallery {
                grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
                gap: 20px;
                padding: 0 40px 40px 40px;
            }

            .goat-image-container {
                height: 200px;
            }

            .header {
                padding: 24px 40px;
            }

            .controls {
                margin: 0 40px 24px 40px;
            }
        }

        /* Very small screens */
        @media (max-width: 480px) {
            .header {
                padding: 16px 20px 10px 20px;
                margin-bottom: 10px;
            }

            .header-content h1 {
                font-size: 1.5rem;
            }

            .controls {
                margin: 0 16px 16px 16px;
                padding: 16px;
            }

            .gallery {
                padding: 0 16px 16px 16px;
            }

            .goat-image-container {
                height: 260px;
            }

            .message {
                margin: 16px;
            }

            .empty-state {
                margin: 16px;
                padding: 40px 16px 190px 16px;
            }
        }

        /* For users who prefer reduced motion - instant everything */
        @media (prefers-reduced-motion: reduce) {

            .goat-gif,
            .image-placeholder,
            .image-error,
            .loading-progress {
                transition: none !important;
                animation: none !important;
            }

            .goat-item {
                transition: none;
            }

            .goat-item:hover {
                transform: none;
            }

            .action-btn {
                transition: none;
            }

            .action-btn:hover {
                transform: none;
            }
        }
    </style>
</head>

<body>
    <?php if (!$isLoggedIn): ?>
        <!-- Login Modal -->
        <div class="modal show">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🐐 Admin Login</h2>
                </div>
                <form method="POST">
                    <div class="form-group" style="margin-bottom: 10px;">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group" style="margin-bottom: 20px;">
                        <label for="password">Password:</label>
                        <input type="password" id="password" name="password" required>
                    </div>
                    <button type="submit" name="login" class="btn success" style="width: 100%;">Login</button>
                    <?php if (isset($loginError)): ?>
                        <div class="login-error"><?php echo htmlspecialchars($loginError); ?></div>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="container">
            <div class="header">
                <div class="header-content">
                    <div class="header-wrapper">
                        <div style="position: relative;">
                            <a href="?logout=1" class="logout-btn">
                                <svg class="logout-icon" xmlns="http://www.w3.org/2000/svg" viewBox="-2 -2 28 28"
                                    fill="none" stroke="currentColor" stroke-width="2" style="margin-left: 5px;">
                                    <path d="M7 4 A 8 8 0 0 0 7 20" stroke-linecap="round"></path>
                                    <polyline points="14 7 19 12 14 17" stroke-linecap="round" stroke-linejoin="round">
                                    </polyline>
                                    <line x1="19" y1="12" x2="7" y2="12" stroke-linecap="round"></line>
                                </svg>
                            </a>
                        </div>
                        <h1><a href="/admin" style="text-decoration: none; color: inherit;">🐐 Random Goat Admin</a></h1>
                    </div>
                    <div class="stats">
                        <div class="stats-text">
                            <?php if ($search): ?>
                                Search: "<?php echo htmlspecialchars($search); ?>" -
                                <?php echo $totalGoats; ?> result<?php echo $totalGoats !== 1 ? 's' : ''; ?> found |
                            <?php else: ?>
                                Total Goats: <?php echo count($allGoatsData); ?> |
                            <?php endif; ?>
                            Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
                        </div>
                        <div class="per-page-container">
                            <form method="GET" id="perPageForm">
                                <?php if ($search): ?>
                                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                                <?php endif; ?>
                                <label for="perPage">Per Page</label>
                                <select id="perPage" name="perPage">
                                    <?php foreach ($allowedPerPage as $option): ?>
                                        <option value="<?php echo $option; ?>" <?php echo $perPage == $option ? 'selected' : ''; ?>>
                                            <?php echo $option; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </form>
                        </div>
                    </div>
                    <div class="header-wrapper">
                        <div class="header-bottom-row">
                            <div
                                class="github-status <?php echo checkGitHubConfig($githubToken, $githubOwner, $githubRepo) ? 'enabled' : 'disabled'; ?>">
                                <?php if (checkGitHubConfig($githubToken, $githubOwner, $githubRepo)): ?>
                                    ✅ GitHub Sync: <a
                                        href="https://github.com/<?php echo htmlspecialchars($githubOwner . '/' . $githubRepo); ?>"
                                        style="text-decoration: none; color: inherit;"
                                        target="_blank"><?php echo htmlspecialchars($githubOwner . '/' . $githubRepo); ?></a>
                                <?php else: ?>
                                    ⚠️ GitHub Sync: Disabled
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <div class="controls">
                <div class="controls-grid">
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <button type="button" class="action-btn" onclick="showAddGoatModal()">
                            📥 Add Goat
                        </button>
                        <button type="button" class="action-btn search" onclick="showSearchModal()">
                            🔍 Find Goat
                        </button>
                    </div>

                    <!-- Clear Search Button (if search is active) -->
                    <?php if ($search): ?>
                        <div class="clear-search-container">
                            <a href="?<?php echo $perPage != 12 ? 'perPage=' . $perPage : ''; ?>" class="btn btn-secondary">
                                Clear Search
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (empty($currentGoats)): ?>
                <div class="empty-state">
                    <?php if ($search): ?>
                        <h3>No goats found</h3>
                        <p>No goats match your search for "<?php echo htmlspecialchars($search); ?>"</p>
                        <p><a href="?" style="color: var(--accent-primary);">Clear search</a> to see all goats</p>
                    <?php else: ?>
                        <h3>No goats found</h3>
                        <p>Add some goats using the forms above!</p>
                        <p style="font-size: 13px; margin-top: 10px;">🔗 Add from Giphy or any direct GIF URL</p>
                        <p style="font-size: 13px;">🏷️ Add tags to organize and search your goats</p>
                        <p style="font-size: 13px;">📥 Images are downloaded and stored locally</p>
                        <p style="font-size: 13px;">🔗 URL-based IDs prevent duplicate imports</p>
                        <?php if (checkGitHubConfig($githubToken, $githubOwner, $githubRepo)): ?>
                            <p style="font-size: 13px;">🚀 Files will be synced to GitHub repository</p>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="gallery">
                    <?php foreach ($currentGoats as $goat): ?>
                        <div class="goat-item">
                            <div class="goat-image-container">
                                <!-- Loading placeholder -->
                                <div class="image-placeholder">
                                    🐐
                                </div>

                                <!-- Error state -->
                                <div class="image-error">
                                    <div class="image-error-icon">⚠️</div>
                                    <div>Failed to load</div>
                                    <button class="retry-btn" onclick="retryImage(this)">Retry</button>
                                </div>

                                <!-- Loading progress bar -->
                                <div class="loading-progress"></div>

                                <!-- Actual image -->
                                <img data-src="../goats/<?php echo htmlspecialchars($goat['id']); ?>.gif" alt="Goat GIF"
                                    class="goat-gif lazy-image" loading="lazy" width="350" height="220"
                                    data-goat-id="<?php echo htmlspecialchars($goat['id']); ?>">
                            </div>
                            <div class="goat-info">
                                <!-- Main ID -->
                                <div class="goat-id">
                                    <span class="id-text">ID: <?php echo htmlspecialchars($goat['id']); ?></span>
                                    <button type="button" class="copy-id-btn"
                                        onclick="copyToClipboard('<?php echo htmlspecialchars($goat['id']); ?>', this)"
                                        title="Copy ID to clipboard">📋</button>
                                </div>

                                <!-- Short ID -->
                                <div class="goat-short-id">
                                    <span class="id-text">Short ID: <?php echo htmlspecialchars($goat['short_id']); ?></span>
                                    <button type="button" class="copy-id-btn"
                                        onclick="copyToClipboard('<?php echo htmlspecialchars($goat['short_id']); ?>', this)"
                                        title="Copy Short ID to clipboard">📋</button>
                                </div>

                                <?php if (strpos($goat['id'], 'url-') === 0): ?>
                                    <small
                                        style="color: var(--text-muted); font-size: 10px; margin-bottom: 10px; display: block;">Direct
                                        URL Import</small>
                                <?php endif; ?>

                                <?php if (!empty($goat['tags'])): ?>
                                    <div class="goat-tags">
                                        <?php foreach ($goat['tags'] as $tag): ?>
                                            <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>

                                <div class="goat-actions">
                                    <div class="goat-links">
                                        <a href="https://rdgt.co/<?php echo htmlspecialchars($goat['short_id']); ?>" target="_blank"
                                            class="goat-link randomgoat tooltip" data-tooltip="View on Random Goat">🐐</a>
                                    </div>
                                    <div class="goat-action-buttons">
                                        <button type="button" class="btn warning btn-small"
                                            onclick="showEditTagsModal('<?php echo htmlspecialchars($goat['id']); ?>', <?php echo htmlspecialchars(json_encode($goat['tags'])); ?>)">
                                            🏷️ Tags
                                        </button>
                                        <button type="button" class="btn danger btn-small"
                                            onclick="showDeleteModal('<?php echo htmlspecialchars($goat['id']); ?>')">
                                            🗑️ Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php
                        // Build URL parameters string to preserve search and perPage settings
                        $urlParams = [];
                        if ($search) {
                            $urlParams[] = 'search=' . urlencode($search);
                        }
                        if ($perPage != 12) {
                            $urlParams[] = 'perPage=' . $perPage;
                        }
                        $paramString = !empty($urlParams) ? '&' . implode('&', $urlParams) : '';

                        // Enhanced pagination logic to limit to maximum 7 buttons total (mobile-friendly)
                        $maxButtons = 7;

                        // Determine which navigation buttons we need
                        $hasFirst = $page > 1;
                        $hasPrevious = $page > 1;
                        $hasNext = $page < $totalPages;
                        $hasLast = $page < $totalPages;

                        // Count navigation buttons
                        $navButtons = ($hasFirst ? 1 : 0) + ($hasPrevious ? 1 : 0) + ($hasNext ? 1 : 0) + ($hasLast ? 1 : 0);
                        $maxPageButtons = $maxButtons - $navButtons;

                        // Ensure we have at least 1 page button (the current page)
                        $maxPageButtons = max(1, $maxPageButtons);

                        // If we have enough space for all pages, show them all
                        if ($totalPages <= $maxPageButtons) {
                            $startPage = 1;
                            $endPage = $totalPages;
                        } else {
                            // Smart pagination - center around current page
                            $halfRange = floor($maxPageButtons / 2);
                            $startPage = max(1, $page - $halfRange);
                            $endPage = min($totalPages, $startPage + $maxPageButtons - 1);

                            // Adjust if we're too close to the end
                            if ($endPage - $startPage + 1 < $maxPageButtons) {
                                $startPage = max(1, $endPage - $maxPageButtons + 1);
                            }
                        }
                        ?>

                        <?php if ($hasFirst): ?>
                            <a href="?page=1<?php echo $paramString; ?>">First</a>
                        <?php endif; ?>

                        <?php if ($hasPrevious): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $paramString; ?>">Prev</a>
                        <?php endif; ?>

                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $paramString; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>

                        <?php if ($hasNext): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $paramString; ?>">Next</a>
                        <?php endif; ?>

                        <?php if ($hasLast): ?>
                            <a href="?page=<?php echo $totalPages; ?><?php echo $paramString; ?>">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Add Goat Modal -->
        <div id="addGoatModal" class="modal form-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>📥 Add Goat from URL</h2>
                    <p>Add a new goat from Giphy or any direct image URL</p>
                </div>
                <form method="POST" id="addGoatForm">
                    <input type="hidden" name="action" value="add">
                    <div class="form-group">
                        <label for="url">Image URL:</label>
                        <input type="url" id="url" name="url" placeholder="Giphy URL or direct GIF URL" required>
                    </div>
                    <div class="form-group">
                        <label for="tags">Tags (optional):</label>
                        <input type="text" id="tags" name="tags" placeholder="funny, cute, dancing (comma-separated)">
                        <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">
                            🏷️ Add tags to make goats easier to find
                        </small>
                    </div>
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 10px; display: block;">
                        🔗 <strong>Giphy:</strong> Uses Giphy ID (e.g., cMso9wDwqSy3e)<br>
                        🌐 <strong>Direct:</strong> Uses URL hash (prevents duplicates)<br>
                    </small>
                    <div class="form-buttons">
                        <button type="button" class="btn btn-secondary" onclick="hideAddGoatModal()">Cancel</button>
                        <button type="submit" class="btn success" id="addGoatBtn">📥 Add Goat</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Search Modal -->
        <div id="searchModal" class="modal form-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🔍 Find Goat</h2>
                    <p>Search for goats by ID, Short ID, or tags</p>
                </div>
                <form method="GET" id="searchForm">
                    <?php if ($perPage != 12): ?>
                        <input type="hidden" name="perPage" value="<?php echo $perPage; ?>">
                    <?php endif; ?>
                    <div class="form-group">
                        <label for="search">Search by ID, Short ID, or Tags:</label>
                        <input type="text" id="search" name="search" placeholder="Enter goat ID, short ID, or tag..."
                            value="<?php echo htmlspecialchars($search); ?>">
                        <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">
                            🔍 Search Giphy IDs, url-HASH patterns, short IDs (5-char), or tags
                        </small>
                    </div>
                    <div class="form-buttons">
                        <button type="button" class="btn btn-secondary" onclick="hideSearchModal()">Cancel</button>
                        <button type="submit" class="btn">🔍 Search</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Delete Goat</h2>
                    <p>Are you sure you want to delete this goat?</p>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                    <button type="button" class="btn danger" onclick="confirmDelete()">
                        🗑️ Delete
                    </button>
                </div>
            </div>
        </div>

        <!-- Edit Tags Modal -->
        <div id="editTagsModal" class="modal edit-tags-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🏷️ Edit Tags</h2>
                    <p>Manage tags for goat: <strong id="editTagsGoatId"></strong></p>
                </div>

                <div class="current-tags-container">
                    <h4>Current Tags:</h4>
                    <div id="currentTags" class="current-tags"></div>
                </div>

                <div class="add-tags-container">
                    <h4>Add New Tags:</h4>
                    <input type="text" id="newTagsInput" placeholder="Enter new tags">
                    <small style="color: var(--text-muted); font-size: 12px; margin-top: 5px; display: block;">
                        💡 Type tags and press Enter to add them
                    </small>
                </div>

                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="hideEditTagsModal()">Cancel</button>
                    <button type="button" class="btn success" onclick="saveTagChanges()">
                        💾 Save Tags
                    </button>
                </div>
            </div>
        </div>

        <!-- Hidden form for deletion -->
        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="goat_id" id="deleteGoatId">
        </form>

        <!-- Hidden form for tag updates -->
        <form id="updateTagsForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="update_tags">
            <input type="hidden" name="goat_id" id="updateTagsGoatId">
            <input type="hidden" name="tags" id="updateTagsValue">
        </form>
    <?php endif; ?>

    <script>
        let goatToDelete = '';
        let currentEditingGoatId = '';
        let currentTags = [];

        // Modal functions for Add Goat
        function showAddGoatModal() {
            document.getElementById('addGoatModal').classList.add('show');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            // Removed auto-focus to let users see the modal content first
        }

        function hideAddGoatModal() {
            document.getElementById('addGoatModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            // Reset form
            document.getElementById('addGoatForm').reset();
        }

        // Modal functions for Search
        function showSearchModal() {
            document.getElementById('searchModal').classList.add('show');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
            // Removed auto-focus to let users see the modal content first
        }

        function hideSearchModal() {
            document.getElementById('searchModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
        }

        // OPTIMIZED Lazy Loading with Intersection Observer - MAXIMUM SPEED
        class LazyImageLoader {
            constructor() {
                this.imageObserver = null;
                this.loadedImages = new Set();
                this.failedImages = new Set();
                this.loadingImages = new Set();

                this.init();
            }

            init() {
                // Check if Intersection Observer is supported
                if ('IntersectionObserver' in window) {
                    this.imageObserver = new IntersectionObserver((entries, observer) => {
                        entries.forEach(entry => {
                            if (entry.isIntersecting) {
                                this.loadImage(entry.target);
                                observer.unobserve(entry.target);
                            }
                        });
                    }, {
                        // Increased rootMargin for faster preloading
                        rootMargin: '100px 0px',
                        threshold: 0.01
                    });

                    // Observe all lazy images
                    this.observeImages();
                } else {
                    // Fallback for older browsers
                    this.loadAllImages();
                }
            }

            observeImages() {
                const lazyImages = document.querySelectorAll('.lazy-image:not(.loaded)');
                lazyImages.forEach(img => {
                    this.imageObserver.observe(img);
                });
            }

            loadImage(img) {
                const container = img.closest('.goat-image-container');
                const placeholder = container.querySelector('.image-placeholder');
                const errorElement = container.querySelector('.image-error');
                const progressBar = container.querySelector('.loading-progress');
                const goatId = img.dataset.goatId;

                // Skip if already loaded, failed, or currently loading
                if (this.loadedImages.has(goatId) ||
                    this.failedImages.has(goatId) ||
                    this.loadingImages.has(goatId)) {
                    return;
                }

                // Mark as loading
                this.loadingImages.add(goatId);

                // Show minimal initial progress (no artificial animation)
                progressBar.style.width = '10%';

                // Load directly on the actual image element
                img.onload = () => {
                    // Image loaded successfully - show IMMEDIATELY with no delays
                    img.classList.add('loaded');
                    placeholder.classList.add('hidden');
                    errorElement.classList.remove('visible');

                    // Hide progress bar immediately - no artificial delay
                    progressBar.style.width = '0%';

                    // Update tracking
                    this.loadedImages.add(goatId);
                    this.loadingImages.delete(goatId);
                };

                img.onerror = () => {
                    // Image failed to load
                    progressBar.style.width = '0%';
                    placeholder.classList.add('hidden');
                    errorElement.classList.add('visible');
                    img.classList.add('error');

                    // Update tracking
                    this.failedImages.add(goatId);
                    this.loadingImages.delete(goatId);
                };

                // Start loading immediately - no artificial progress animation
                img.src = img.dataset.src;

                // Simple progress indication that completes when image actually loads
                // This just provides visual feedback but doesn't delay anything
                progressBar.style.width = '50%';
            }

            retryImage(goatId) {
                // Remove from failed and loading sets
                this.failedImages.delete(goatId);
                this.loadingImages.delete(goatId);

                const img = document.querySelector(`[data-goat-id="${goatId}"]`);
                if (img) {
                    const container = img.closest('.goat-image-container');
                    const placeholder = container.querySelector('.image-placeholder');
                    const errorElement = container.querySelector('.image-error');

                    // Reset states
                    img.classList.remove('error', 'loaded');
                    img.src = ''; // Clear any previous src
                    placeholder.classList.remove('hidden');
                    errorElement.classList.remove('visible');

                    // Retry loading immediately
                    this.loadImage(img);
                }
            }

            loadAllImages() {
                // Fallback for browsers without Intersection Observer
                const lazyImages = document.querySelectorAll('.lazy-image:not(.loaded)');
                lazyImages.forEach(img => {
                    this.loadImage(img);
                });
            }

            // Method to aggressively preload images for fastest experience
            preloadCriticalImages() {
                const criticalImages = document.querySelectorAll('.lazy-image:not(.loaded)');
                // Load first 6 images immediately if they're anywhere near the viewport
                Array.from(criticalImages).slice(0, 6).forEach(img => {
                    const rect = img.getBoundingClientRect();
                    if (rect.top < window.innerHeight + 200) {
                        this.loadImage(img);
                    }
                });
            }

            // Aggressive preloading method
            preloadAllVisibleImages() {
                const allImages = document.querySelectorAll('.lazy-image:not(.loaded)');
                allImages.forEach(img => {
                    const rect = img.getBoundingClientRect();
                    // Preload anything within 300px of viewport
                    if (rect.top < window.innerHeight + 300 && rect.bottom > -300) {
                        this.loadImage(img);
                    }
                });
            }
        }

        // Initialize lazy loading when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            window.lazyLoader = new LazyImageLoader();

            // Immediately preload critical images - no delay
            window.lazyLoader.preloadCriticalImages();

            // Optional: Aggressively preload all visible images after 50ms
            setTimeout(() => {
                window.lazyLoader.preloadAllVisibleImages();
            }, 50);
        });

        // Optimized scroll handling for maximum responsiveness
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            // Immediate action for scroll
            if (window.lazyLoader) {
                window.lazyLoader.preloadAllVisibleImages();
            }

            // Debounced action for bottom detection
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                if ((window.innerHeight + window.scrollY) >= document.body.offsetHeight - 400) {
                    // User is near bottom - could implement next page preloading
                    console.log('Near bottom - could preload next page');
                }
            }, 50);
        }, { passive: true }); // Passive listener for better performance

        // Global retry function for error buttons
        function retryImage(button) {
            const container = button.closest('.goat-image-container');
            const img = container.querySelector('.lazy-image');
            const goatId = img.dataset.goatId;

            if (window.lazyLoader) {
                window.lazyLoader.retryImage(goatId);
            }
        }

        // Copy to clipboard functionality
        function copyToClipboard(text, button) {
            // Try the modern clipboard API first
            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(text).then(() => {
                    showCopySuccess(button);
                }).catch(() => {
                    // Fallback to older method
                    fallbackCopyToClipboard(text, button);
                });
            } else {
                // Fallback for older browsers or non-secure contexts
                fallbackCopyToClipboard(text, button);
            }
        }

        function fallbackCopyToClipboard(text, button) {
            // Create a temporary textarea element
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();

            try {
                document.execCommand('copy');
                showCopySuccess(button);
            } catch (err) {
                console.error('Failed to copy text: ', err);
                showCopyError(button);
            }

            document.body.removeChild(textArea);
        }

        function showCopySuccess(button) {
            const originalText = button.textContent;
            button.textContent = '✅';
            button.classList.add('copied');

            setTimeout(() => {
                button.textContent = originalText;
                button.classList.remove('copied');
            }, 1500);
        }

        function showCopyError(button) {
            const originalText = button.textContent;
            button.textContent = '❌';

            setTimeout(() => {
                button.textContent = originalText;
            }, 1500);
        }

        // Tag editing functions
        function showEditTagsModal(goatId, tags) {
            currentEditingGoatId = goatId;
            currentTags = [...tags]; // Clone the array

            document.getElementById('editTagsGoatId').textContent = goatId;
            document.getElementById('editTagsModal').classList.add('show');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';

            updateCurrentTagsDisplay();
            document.getElementById('newTagsInput').value = '';
            // Removed auto-focus to let users see the modal content first
        }

        function hideEditTagsModal() {
            document.getElementById('editTagsModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            currentEditingGoatId = '';
            currentTags = [];
        }

        function updateCurrentTagsDisplay() {
            const container = document.getElementById('currentTags');

            if (currentTags.length === 0) {
                container.className = 'current-tags empty';
                container.innerHTML = 'No tags assigned';
                return;
            }

            container.className = 'current-tags';
            container.innerHTML = '';

            currentTags.forEach((tag, index) => {
                const tagElement = document.createElement('div');
                tagElement.className = 'editable-tag';
                tagElement.innerHTML = `
                    ${tag}
                    <button type="button" class="remove-tag-btn" onclick="removeTag(${index})" title="Remove tag">×</button>
                `;
                container.appendChild(tagElement);
            });
        }

        function removeTag(index) {
            if (index >= 0 && index < currentTags.length) {
                currentTags.splice(index, 1);
                updateCurrentTagsDisplay();
            }
        }

        function addTag(tagText) {
            const tag = tagText.trim().toLowerCase();
            if (tag && !currentTags.includes(tag)) {
                currentTags.push(tag);
                updateCurrentTagsDisplay();
                return true;
            }
            return false;
        }

        function saveTagChanges() {
            document.getElementById('updateTagsGoatId').value = currentEditingGoatId;
            document.getElementById('updateTagsValue').value = currentTags.join(', ');
            document.getElementById('updateTagsForm').submit();
        }

        // Handle tag input events
        document.addEventListener('DOMContentLoaded', function () {
            const tagInput = document.getElementById('newTagsInput');

            if (tagInput) {
                // Handle Enter key and comma input
                tagInput.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ',') {
                        e.preventDefault();
                        const tagText = this.value.trim();
                        if (tagText) {
                            if (addTag(tagText)) {
                                this.value = '';
                            }
                        }
                    }
                });

                // Handle pasting multiple tags
                tagInput.addEventListener('paste', function (e) {
                    setTimeout(() => {
                        const pastedText = this.value;
                        const tags = pastedText.split(',').map(tag => tag.trim()).filter(tag => tag);

                        if (tags.length > 1) {
                            this.value = '';
                            let addedCount = 0;
                            tags.forEach(tag => {
                                if (addTag(tag)) {
                                    addedCount++;
                                }
                            });
                        }
                    }, 10);
                });

                // Handle blur event to add remaining text as tag
                tagInput.addEventListener('blur', function () {
                    const tagText = this.value.trim();
                    if (tagText) {
                        if (addTag(tagText)) {
                            this.value = '';
                        }
                    }
                });
            }

            // Auto-submit form when items per page changes
            const perPageSelect = document.getElementById('perPage');
            if (perPageSelect) {
                perPageSelect.addEventListener('change', function () {
                    // Add loading state
                    this.classList.add('loading');

                    // Get the perPage form this select belongs to
                    const form = document.getElementById('perPageForm');
                    if (form) {
                        // Reset to page 1 when changing items per page
                        const pageInput = document.createElement('input');
                        pageInput.type = 'hidden';
                        pageInput.name = 'page';
                        pageInput.value = '1';
                        form.appendChild(pageInput);

                        // Submit the form
                        form.submit();
                    }
                });
            }
        });

        // Delete modal functions
        function showDeleteModal(goatId) {
            goatToDelete = goatId;
            document.getElementById('deleteModal').classList.add('show');
            document.body.classList.add('modal-open');
            document.body.style.overflow = 'hidden';
        }

        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            goatToDelete = '';
        }

        function confirmDelete() {
            if (goatToDelete) {
                document.getElementById('deleteGoatId').value = goatToDelete;
                document.getElementById('deleteForm').submit();
            }
        }

        // Close modals when clicking outside
        document.getElementById('addGoatModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideAddGoatModal();
            }
        });

        document.getElementById('searchModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideSearchModal();
            }
        });

        document.getElementById('deleteModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });

        document.getElementById('editTagsModal').addEventListener('click', function (e) {
            if (e.target === this) {
                hideEditTagsModal();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                hideAddGoatModal();
                hideSearchModal();
                hideDeleteModal();
                hideEditTagsModal();
            }
        });

        // Add loading state to Add Goat form
        document.getElementById('addGoatForm').addEventListener('submit', function (e) {
            const btn = document.getElementById('addGoatBtn');
            const originalText = btn.innerHTML;

            btn.classList.add('loading');
            btn.innerHTML = '<?php echo checkGitHubConfig($githubToken, $githubOwner, $githubRepo) ? "Processing..." : "Processing..."; ?>';
            btn.disabled = true;

            // Reset button state if form submission fails or returns to page
            setTimeout(function () {
                btn.classList.remove('loading');
                btn.innerHTML = originalText;
                btn.disabled = false;
            }, 30000); // Reset after 30 seconds max
        });
    </script>
</body>

</html>