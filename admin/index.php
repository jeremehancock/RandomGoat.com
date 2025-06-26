<?php

session_start();

// Configuration
$dataFile = '../data/goats.txt';
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Admin credentials from environment variables
$adminUsername = $_ENV['ADMIN_USERNAME'] ?? getenv('ADMIN_USERNAME');
$adminPassword = $_ENV['ADMIN_PASSWORD'] ?? getenv('ADMIN_PASSWORD');

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

// Ensure goats.txt exists
if (!file_exists($dataFile)) {
    file_put_contents($dataFile, '');
}

// Function to extract Giphy ID from URL
function extractGiphyId($url) {
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

// Function to read goat IDs
function readGoatIds($file) {
    if (!file_exists($file)) {
        return [];
    }
    $content = file_get_contents($file);
    $ids = array_filter(array_map('trim', explode("\n", $content)));
    return array_unique($ids);
}

// Function to save goat IDs
function saveGoatIds($file, $ids) {
    $content = implode("\n", array_unique(array_filter($ids)));
    return file_put_contents($file, $content) !== false;
}

// Handle form submissions (only if logged in)
$message = '';
$messageType = '';

if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $goatIds = readGoatIds($dataFile);
        
        switch ($_POST['action']) {
            case 'add':
                $url = trim($_POST['giphy_url'] ?? '');
                if ($url) {
                    $id = extractGiphyId($url);
                    if ($id && !in_array($id, $goatIds)) {
                        $goatIds[] = $id;
                        if (saveGoatIds($dataFile, $goatIds)) {
                            $message = "Goat added successfully! ID: $id";
                            $messageType = 'success';
                        } else {
                            $message = "Error saving goat to file.";
                            $messageType = 'error';
                        }
                    } elseif (in_array($id, $goatIds)) {
                        $message = "This goat already exists in the gallery.";
                        $messageType = 'warning';
                    } else {
                        $message = "Invalid Giphy URL. Could not extract ID.";
                        $messageType = 'error';
                    }
                } else {
                    $message = "Please enter a Giphy URL.";
                    $messageType = 'error';
                }
                break;
                
            case 'delete':
                $idToDelete = $_POST['goat_id'] ?? '';
                if ($idToDelete && in_array($idToDelete, $goatIds)) {
                    $goatIds = array_diff($goatIds, [$idToDelete]);
                    if (saveGoatIds($dataFile, $goatIds)) {
                        $message = "Goat deleted successfully!";
                        $messageType = 'success';
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

// Get current goat IDs and pagination info
$allGoatIds = $isLoggedIn ? readGoatIds($dataFile) : [];

// Filter goats based on search
$filteredGoatIds = $allGoatIds;
if ($search && $isLoggedIn) {
    $filteredGoatIds = array_filter($allGoatIds, function($id) use ($search) {
        return stripos($id, $search) !== false;
    });
}

$totalGoats = count($filteredGoatIds);
$totalPages = ceil($totalGoats / $perPage);
$offset = ($page - 1) * $perPage;
$currentGoats = array_slice($filteredGoatIds, $offset, $perPage);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Random Goat Admin</title>
    <meta charset="UTF-8">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐐</text></svg>" type="image/svg+xml">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Looking for random goat gifs? Look no further!"/>
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
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .header-content h1 {
            color: var(--text-primary);
            margin-bottom: 10px;
            font-size: 2rem;
        }
        
        .stats {
            color: var(--text-secondary);
            font-size: 14px;
        }
        
        .logout-btn {
            background: var(--error);
            color: #ffffff;
            padding: 12px 20px;
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
            min-width: 110px;
            min-height: 44px;
            flex-shrink: 0;
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            align-items: stretch;
        }
        
        .control-section {
            background: var(--bg-tertiary);
            padding: 20px;
            border-radius: 8px;
            border: 1px solid var(--border);
            display: flex;
            flex-direction: column;
            min-height: 200px;
        }
        
        .control-section h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-size: 1.25rem;
        }
        
        .control-section form {
            display: flex;
            flex-direction: column;
            flex: 1;
        }
        
        .form-content {
            flex: 1;
        }
        
        .form-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            margin-top: auto;
            padding-top: 20px;
            min-height: 64px;
            align-items: flex-end;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: var(--text-primary);
        }
        
        input[type="url"], input[type="text"], input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 2px solid var(--border);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input[type="url"]:focus, input[type="text"]:focus, input[type="password"]:focus {
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
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
            justify-content: center;
        }
        
        .goat-item {
            max-width: 350px;
            width: 100%;
            justify-self: center;
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: visible;
            box-shadow: 0 4px 6px var(--shadow);
            border: 1px solid var(--border);
            transition: transform 0.3s, box-shadow 0.3s;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
        }
        
        .goat-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px var(--shadow);
        }
        
        .goat-gif {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 12px 12px 0 0;
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
            margin-bottom: 15px;
            background: var(--bg-tertiary);
            padding: 8px 12px;
            border-radius: 6px;
            flex: 1;
        }
        
        .goat-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
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
            font-size: 16px;
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
        
        .pagination a, .pagination span {
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
            z-index: 1000;
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
        
        .modal-content {
            background: var(--bg-secondary);
            padding: 32px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            max-width: 400px;
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
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
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

		@media (max-width: 768px) {
			body {
				background: var(--bg-primary);
				padding: 0;
				margin: 0;
			}
			
			.container {
				padding: 0;
				max-width: 100%;
				margin: 0;
			}
			
			/* App-like header */
			.header {
				background: var(--bg-secondary);
				margin: 0;
				border-radius: 0 0 20px 20px;
				padding: 20px 20px 25px 20px;
				box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
				position: sticky;
				top: 0;
				z-index: 100;
				backdrop-filter: blur(10px);
				border: none;
				border-bottom: 1px solid var(--border);
				flex-direction: row;
				align-items: flex-start;
				justify-content: space-between;
			}
			
			.header-content {
				flex: 1;
			}
			
			.header-content h1 {
				font-size: 1.75rem;
				margin-bottom: 8px;
				text-align: left;
			}
			
			.stats {
				text-align: left;
				font-size: 13px;
				margin-bottom: 0;
			}
			
			.logout-btn {
				width: auto;
				max-width: none;
				margin: 0;
				padding: 8px 16px;
				font-size: 12px;
				border-radius: 8px;
				min-width: 70px;
				min-height: 36px;
				flex-shrink: 0;
				margin-left: 12px;
			}
			
			/* App-like controls section */
			.controls {
				margin: 20px 16px;
				border-radius: 16px;
				padding: 20px;
				box-shadow: 0 2px 12px rgba(0, 0, 0, 0.1);
			}
			
			.controls-grid {
				grid-template-columns: 1fr;
				gap: 20px;
			}
			
			.control-section {
				border-radius: 12px;
				padding: 20px;
				min-height: auto;
			}
			
			.control-section h3 {
				font-size: 1.1rem;
				margin-bottom: 16px;
				text-align: center;
			}
			
			/* Mobile-optimized forms */
			.form-group {
				margin-bottom: 16px;
			}
			
			input[type="url"], input[type="text"], input[type="password"] {
				padding: 16px;
				font-size: 16px; /* Prevents zoom on iOS */
				border-radius: 12px;
				border: 2px solid var(--border);
				background: var(--bg-primary);
			}
			
			input[type="url"]:focus, input[type="text"]:focus, input[type="password"]:focus {
				border-color: var(--accent-primary);
				box-shadow: 0 0 0 4px rgba(91, 33, 182, 0.1);
			}
			
			/* App-like buttons */
			.btn, .btn-secondary {
				padding: 16px 24px;
				font-size: 15px;
				font-weight: 600;
				border-radius: 12px;
				min-height: 52px;
				width: 100%;
				justify-content: center;
				transition: all 0.2s ease;
				touch-action: manipulation;
			}
			
			.form-buttons {
				flex-direction: row;
				gap: 12px;
				margin-top: 20px;
				padding-top: 16px;
			}
			
			.form-buttons .btn {
				flex: 1;
			}
			
			/* Mobile gallery - card-like layout */
			.gallery {
				padding: 0 16px;
				grid-template-columns: 1fr;
				gap: 16px;
				margin-bottom: 20px;
			}
			
			.goat-item {
				max-width: 100%;
				border-radius: 16px;
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
				overflow: hidden;
				background: var(--bg-secondary);
				border: 1px solid var(--border);
			}
			
			.goat-item:hover {
				transform: none; /* Disable hover effects on mobile */
				box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
			}
			
			.goat-gif {
				height: 250px;
				border-radius: 16px 16px 0 0;
			}
			
			.goat-info {
				padding: 16px;
			}
			
			.goat-id {
				font-size: 11px;
				padding: 8px 12px;
				border-radius: 8px;
				margin-bottom: 12px;
				background: var(--bg-tertiary);
			}
			
			.goat-actions {
				align-items: center;
				gap: 12px;
			}
			
			.goat-links {
				flex: 1;
			}
			
			.goat-link {
				width: 44px;
				height: 44px;
				border-radius: 12px;
				font-size: 18px;
				touch-action: manipulation;
			}
			
			.goat-actions .btn {
				width: auto;
				min-width: 80px;
				padding: 12px 16px;
				font-size: 14px;
				min-height: 44px;
			}
			
			/* Mobile pagination */
			.pagination {
				padding: 20px 16px;
				margin: 0;
				gap: 6px;
				justify-content: center;
				flex-wrap: wrap;
				overflow-x: visible;
				max-width: 100%;
			}
			
			.pagination a, .pagination span {
				padding: 10px 12px;
				min-width: 40px;
				min-height: 40px;
				border-radius: 8px;
				font-size: 13px;
				flex-shrink: 1;
				touch-action: manipulation;
				text-align: center;
				display: flex;
				align-items: center;
				justify-content: center;
				white-space: nowrap;
				overflow: hidden;
				text-overflow: ellipsis;
			}
			
			/* Mobile modal improvements */
			.modal-content {
				margin: 20px 16px;
				padding: 24px;
				border-radius: 20px;
				max-width: none;
				width: calc(100% - 32px);
				box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
			}
			
			.modal-header h2 {
				font-size: 1.5rem;
				margin-bottom: 12px;
			}
			
			.modal-buttons {
				flex-direction: row;
				gap: 12px;
				margin-top: 24px;
			}
			
			.modal-buttons .btn {
				flex: 1;
				margin: 0;
			}
			
			/* Message styling for mobile */
			.message {
				margin: 16px;
				padding: 16px;
				border-radius: 12px;
				font-size: 14px;
			}
			
			/* Empty state mobile */
			.empty-state {
				padding: 60px 20px;
				margin: 20px 16px;
				background: var(--bg-secondary);
				border-radius: 16px;
				border: 1px solid var(--border);
			}
			
			.empty-state h3 {
				font-size: 1.25rem;
				margin-bottom: 12px;
			}
			
			.empty-state p {
				font-size: 14px;
				line-height: 1.5;
				margin-bottom: 8px;
			}
			
			/* Touch-friendly tooltips - disable on mobile */
			.tooltip::before,
			.tooltip::after {
				display: none;
			}
			
			/* Login form mobile optimization */
			.modal.show .modal-content form .btn {
				width: 100%;
				margin-top: 8px;
			}
			
			.login-error {
				font-size: 13px;
				margin-top: 16px;
				padding: 12px;
				background: rgba(220, 38, 38, 0.1);
				border-radius: 8px;
				border: 1px solid var(--error);
			}
			
			/* Safe area adjustments for notched devices */
			@supports(padding: max(0px)) {
				.header {
				    padding-top: max(20px, env(safe-area-inset-top));
				}
				
				body {
				    padding-bottom: max(0px, env(safe-area-inset-bottom));
				}
			}
			
			/* Dark mode adjustments for mobile */
			@media (prefers-color-scheme: dark) {
				.header {
				    backdrop-filter: blur(20px);
				    background: rgba(26, 26, 26, 0.95);
				}
			}
			
			/* Improved scrolling on mobile */
			.container {
				-webkit-overflow-scrolling: touch;
				overflow-x: hidden;
			}
			
			/* Better button feedback */
			.btn:active, .btn-secondary:active, .goat-link:active {
				transform: scale(0.98);
				transition: transform 0.1s ease;
			}
			
			/* Improved form field focus */
			input:focus {
				transform: scale(1.02);
				transition: transform 0.2s ease;
			}
		}

		/* Additional mobile-specific styles for very small screens */
		@media (max-width: 480px) {
			.header-content h1 {
				font-size: 1.5rem;
			}
			
			.logout-btn {
				padding: 6px 12px;
				font-size: 11px;
				min-width: 60px;
				min-height: 32px;
			}
			
			.controls {
				margin: 16px 12px;
				padding: 16px;
			}
			
			.control-section {
				padding: 16px;
			}
			
			.gallery {
				padding: 0 12px;
				gap: 12px;
			}
			
			.goat-gif {
				height: 220px;
			}
			
			.pagination {
				padding: 16px 12px;
				gap: 4px;
			}
			
			.pagination a, .pagination span {
				padding: 8px 10px;
				min-width: 36px;
				min-height: 36px;
				font-size: 12px;
			}
			
			.modal-content {
				margin: 16px 12px;
				padding: 20px;
				width: calc(100% - 24px);
			}
			
			.message {
				margin: 12px;
			}
			
			.empty-state {
				margin: 16px 12px;
				padding: 40px 16px;
			}
		}

		/* Landscape mobile optimization */
		@media (max-width: 768px) and (orientation: landscape) {
			.header {
				position: relative;
				border-radius: 0;
				padding: 16px 20px;
				flex-direction: row;
				justify-content: space-between;
				align-items: flex-start;
			}
			
			.header-content h1 {
				font-size: 1.5rem;
				margin-bottom: 4px;
				text-align: left;
			}
			
			.stats {
				margin-bottom: 0;
				text-align: left;
			}
			
			.logout-btn {
				padding: 6px 12px;
				max-width: none;
				width: auto;
				min-width: 60px;
				min-height: 32px;
				font-size: 11px;
				margin-left: 12px;
			}
			
			.gallery {
				grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
				gap: 16px;
			}
			
			.goat-gif {
				height: 200px;
			}
			
			/* Landscape pagination adjustments */
			.pagination {
				padding: 16px 20px;
				gap: 6px;
			}
			
			.pagination a, .pagination span {
				padding: 8px 12px;
				min-width: 40px;
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
                    <div class="form-group">
                        <label for="username">Username:</label>
                        <input type="text" id="username" name="username" required>
                    </div>
                    <div class="form-group">
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
                    <h1>🐐 Random Goat Admin</h1>
                    <div class="stats">
                        <?php if ($search): ?>
                            Search: "<?php echo htmlspecialchars($search); ?>" - 
                            <?php echo $totalGoats; ?> result<?php echo $totalGoats !== 1 ? 's' : ''; ?> found |
                        <?php else: ?>
                            Total Goats: <?php echo count($allGoatIds); ?> |
                        <?php endif; ?>
                        Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
                    </div>
                </div>
                <a href="?logout=1" class="logout-btn">Logout</a>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="controls">
                <div class="controls-grid">
                    <div class="control-section">
                        <h3>Add Goat</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-content">
                                <div class="form-group">
                                    <label for="giphy_url">Giphy URL:</label>
                                    <input type="url" id="giphy_url" name="giphy_url" 
                                           placeholder="https://giphy.com/gifs/tongue-goat-cMso9wDwqSy3e" required>
                                </div>
                            </div>
                            <div class="form-buttons">
                                <button type="submit" class="btn success">Add Goat</button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="control-section">
                        <h3>Find Goat</h3>
                        <form method="GET">
                            <div class="form-content">
                                <div class="form-group">
                                    <label for="search">Search by Goat ID:</label>
                                    <input type="text" id="search" name="search" 
                                           placeholder="Enter part of goat ID..." 
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            <div class="form-buttons">
                                <?php if ($search): ?>
                                    <a href="?" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
                                <button type="submit" class="btn">🔍 Search</button>
                            </div>
                        </form>
                    </div>
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
                        <p>Add some goats using the form above!</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="gallery">
                    <?php foreach ($currentGoats as $goatId): ?>
                        <div class="goat-item">
                            <img src="https://media.giphy.com/media/<?php echo htmlspecialchars($goatId); ?>/giphy.gif" 
                                 alt="Goat GIF" class="goat-gif" loading="lazy">
                            <div class="goat-info">
                                <div class="goat-id">ID: <?php echo htmlspecialchars($goatId); ?></div>
                                <div class="goat-actions">
                                    <div class="goat-links">
                                        <a href="https://randomgoat.com?id=<?php echo htmlspecialchars($goatId); ?>" 
                                           target="_blank" 
                                           class="goat-link randomgoat tooltip" 
                                           data-tooltip="View on Random Goat">🐐</a>
                                    </div>
                                    <button type="button" class="btn danger" 
                                            onclick="showDeleteModal('<?php echo htmlspecialchars($goatId); ?>')">
                                        Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <?php if ($totalPages > 1): ?>
                    <div class="pagination">
                        <?php 
                        $searchParam = $search ? '&search=' . urlencode($search) : '';
                        
                        // Enhanced pagination logic to limit to maximum 8 buttons total
                        $maxButtons = 8;
                        
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
                            <a href="?page=1<?php echo $searchParam; ?>">First</a>
                        <?php endif; ?>
                        
                        <?php if ($hasPrevious): ?>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $searchParam; ?>">Prev</a>
                        <?php endif; ?>
                        
                        <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $searchParam; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($hasNext): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $searchParam; ?>">Next</a>
                        <?php endif; ?>
                        
                        <?php if ($hasLast): ?>
                            <a href="?page=<?php echo $totalPages; ?><?php echo $searchParam; ?>">Last</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Delete Goat</h2>
                    <p>Are you sure you want to delete this goat? This action cannot be undone.</p>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn btn-secondary" onclick="hideDeleteModal()">Cancel</button>
                    <button type="button" class="btn danger" onclick="confirmDelete()">Delete</button>
                </div>
            </div>
        </div>
        
        <!-- Hidden form for deletion -->
        <form id="deleteForm" method="POST" style="display: none;">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="goat_id" id="deleteGoatId">
        </form>
    <?php endif; ?>
    
    <script>
        let goatToDelete = '';
        
        function showDeleteModal(goatId) {
            goatToDelete = goatId;
            document.getElementById('deleteModal').classList.add('show');
            document.body.style.overflow = 'hidden';
        }
        
        function hideDeleteModal() {
            document.getElementById('deleteModal').classList.remove('show');
            document.body.style.overflow = '';
            goatToDelete = '';
        }
        
        function confirmDelete() {
            if (goatToDelete) {
                document.getElementById('deleteGoatId').value = goatToDelete;
                document.getElementById('deleteForm').submit();
            }
        }
        
        // Close modal when clicking outside
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                hideDeleteModal();
            }
        });
    </script>
</body>
</html>
