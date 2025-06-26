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
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no, viewport-fit=cover">
    <title>Random Goat Admin</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐐</text></svg>" type="image/svg+xml">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Goat Admin">
    <meta name="mobile-web-app-capable" content="yes">
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
            -webkit-tap-highlight-color: transparent;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            min-height: 100dvh;
            overscroll-behavior: none;
            touch-action: manipulation;
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
            position: sticky;
            top: 10px;
            z-index: 100;
            backdrop-filter: blur(10px);
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
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid;
            animation: slideInDown 0.3s ease-out;
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
            padding: 16px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 16px;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s, transform 0.2s;
        }
        
        input[type="url"]:focus, input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(91, 33, 182, 0.15);
            transform: translateY(-2px);
        }
        
        .btn {
            background: var(--accent-primary);
            color: #ffffff;
            padding: 16px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 700;
            transition: all 0.2s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            text-decoration: none;
            box-shadow: 0 4px 8px rgba(91, 33, 182, 0.4);
            min-width: 120px;
            min-height: 48px;
            width: auto;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            position: relative;
            overflow: hidden;
            flex-shrink: 0;
            touch-action: manipulation;
        }
        
        .btn:active {
            transform: translateY(1px);
            box-shadow: 0 2px 4px rgba(91, 33, 182, 0.3);
        }
        
        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(91, 33, 182, 0.5);
        }
        
        .btn.success {
            background: var(--success);
            box-shadow: 0 4px 8px rgba(5, 150, 105, 0.4);
            color: #ffffff;
        }
        
        .btn.success:hover {
            background: var(--success-hover);
            box-shadow: 0 6px 12px rgba(5, 150, 105, 0.5);
        }
        
        .btn.danger {
            background: var(--error);
            box-shadow: 0 4px 8px rgba(220, 38, 38, 0.4);
            color: #ffffff;
        }
        
        .btn.danger:hover {
            background: var(--error-hover);
            box-shadow: 0 6px 12px rgba(220, 38, 38, 0.5);
        }
        
        .btn.warning {
            background: var(--warning);
            color: #ffffff;
            box-shadow: 0 4px 8px rgba(217, 119, 6, 0.4);
        }
        
        .btn.warning:hover {
            background: var(--warning-hover);
            box-shadow: 0 6px 12px rgba(217, 119, 6, 0.5);
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
            box-shadow: 0 4px 8px rgba(55, 65, 81, 0.3);
            font-weight: 600;
            min-width: 120px;
            min-height: 48px;
            flex-shrink: 0;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            text-decoration: none;
            transition: all 0.2s ease;
            cursor: pointer;
            box-sizing: border-box;
            touch-action: manipulation;
        }
        
        .btn-secondary:hover {
            background: var(--border);
            border-color: var(--text-secondary);
            box-shadow: 0 6px 12px rgba(55, 65, 81, 0.4);
            color: var(--text-primary);
            transform: translateY(-2px);
        }
        
        .btn-secondary:active {
            transform: translateY(1px);
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
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 6px var(--shadow);
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
            position: relative;
            animation: fadeInUp 0.5s ease-out;
        }
        
        .goat-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 12px 30px var(--shadow);
        }
        
        .goat-gif {
            width: 100%;
            height: 220px;
            object-fit: cover;
            border-radius: 16px 16px 0 0;
            transition: transform 0.3s ease;
        }
        
        .goat-item:hover .goat-gif {
            transform: scale(1.05);
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
            padding: 12px 16px;
            border-radius: 8px;
            flex: 1;
            border: 1px solid var(--border);
        }
        
        .goat-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: auto;
            gap: 12px;
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
            width: 44px;
            height: 44px;
            border-radius: 12px;
            text-decoration: none;
            transition: all 0.2s ease;
            font-size: 18px;
            font-weight: 700;
            border: 1px solid var(--border);
            touch-action: manipulation;
        }
        
        .goat-link.randomgoat {
            background: var(--accent-primary);
            color: #ffffff;
            box-shadow: 0 3px 6px rgba(91, 33, 182, 0.3);
        }
        
        .goat-link.randomgoat:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 5px 10px rgba(91, 33, 182, 0.4);
        }
        
        .goat-link.randomgoat:active {
            transform: translateY(0);
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
            border-radius: 8px;
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
            gap: 8px;
            margin: 40px 0;
            flex-wrap: wrap;
        }
        
        .pagination a, .pagination span {
            padding: 12px 16px;
            border: 2px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            border-radius: 12px;
            transition: all 0.3s;
            background: var(--bg-secondary);
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(55, 65, 81, 0.2);
            min-width: 48px;
            min-height: 48px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            touch-action: manipulation;
        }
        
        .pagination a:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(91, 33, 182, 0.4);
            color: #ffffff;
        }
        
        .pagination a:active {
            transform: translateY(0);
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
            animation: fadeIn 0.5s ease-out;
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
            backdrop-filter: blur(8px);
            padding: 20px;
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
            border-radius: 24px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            border: 1px solid var(--border);
            max-width: 400px;
            width: 100%;
            animation: slideInUp 0.3s ease-out;
            max-height: 90vh;
            overflow-y: auto;
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
            animation: shake 0.5s ease-in-out;
        }
        
        /* Animations */
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideInDown {
            from { 
                opacity: 0;
                transform: translateY(-20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes slideInUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes fadeInUp {
            from { 
                opacity: 0;
                transform: translateY(20px);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
        
        /* Mobile Optimizations */
        @media (max-width: 768px) {
            body {
                font-size: 16px;
                overflow-x: hidden;
            }
            
            .container {
                padding: 10px;
                padding-bottom: 80px; /* Extra bottom padding for mobile */
            }
            
            .header {
                position: sticky;
                top: 0;
                margin: 0 0 16px 0;
                border-radius: 0 0 16px 16px;
                padding: 16px;
                backdrop-filter: blur(20px);
                background: rgba(26, 26, 26, 0.95);
            }
            
            .header-content h1 {
                font-size: 1.5rem;
                margin-bottom: 8px;
            }
            
            .stats {
                font-size: 13px;
            }
            
            .logout-btn {
                padding: 10px 16px;
                font-size: 13px;
                min-width: 80px;
                min-height: 40px;
            }
            
            .controls {
                padding: 16px;
                border-radius: 16px;
                margin-bottom: 16px;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
                gap: 16px;
            }
            
            .control-section {
                min-height: auto;
                padding: 16px;
                border-radius: 12px;
            }
            
            .control-section h3 {
                font-size: 1.1rem;
                margin-bottom: 16px;
            }
            
            .form-buttons {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
                padding-top: 16px;
                min-height: auto;
            }
            
            .btn, .btn-secondary {
                width: 100%;
                padding: 16px;
                font-size: 16px;
                min-height: 48px;
                border-radius: 12px;
            }
            
            input[type="url"], input[type="text"], input[type="password"] {
                padding: 16px;
                font-size: 16px;
                border-radius: 12px;
            }
            
            .gallery {
                grid-template-columns: 1fr;
                gap: 16px;
                margin-bottom: 24px;
            }
            
            .goat-item {
                max-width: 100%;
                border-radius: 16px;
                margin: 0;
                animation-delay: calc(var(--index, 0) * 0.1s);
            }
            
            .goat-gif {
                height: 240px;
            }
            
            .goat-info {
                padding: 16px;
            }
            
            .goat-actions {
                gap: 8px;
                flex-wrap: wrap;
            }
            
            .goat-link {
                width: 40px;
                height: 40px;
                font-size: 16px;
            }
            
            .btn.danger {
                padding: 12px 16px;
                font-size: 14px;
                min-height: 40px;
                min-width: 80px;
            }
            
            .pagination {
                gap: 6px;
                margin: 24px 0;
                padding: 0 10px;
            }
            
            .pagination a, .pagination span {
                padding: 10px 12px;
                min-width: 40px;
                min-height: 40px;
                font-size: 14px;
                border-radius: 10px;
            }
            
            .modal {
                padding: 16px;
                align-items: flex-end;
            }
            
            .modal-content {
                width: 100%;
                max-width: 100%;
                border-radius: 20px 20px 0 0;
                max-height: 85vh;
                margin: 0;
                animation: slideInUpMobile 0.3s ease-out;
            }
            
            .modal-buttons {
                flex-direction: column-reverse;
                align-items: stretch;
                gap: 12px;
            }
            
            .modal-buttons .btn,
            .modal-buttons .btn-secondary {
                width: 100%;
            }
            
            .message {
                margin: 0 -10px 16px -10px;
                border-radius: 0 0 12px 12px;
                animation: slideInDown 0.3s ease-out;
            }
            
            .empty-state {
                padding: 60px 20px;
            }
            
            .empty-state h3 {
                font-size: 1.3rem;
            }
            
            /* Hide tooltips on mobile */
            .tooltip::before,
            .tooltip::after {
                display: none;
            }
            
            /* Better touch targets */
            .goat-item {
                cursor: pointer;
                -webkit-tap-highlight-color: rgba(91, 33, 182, 0.1);
            }
            
            /* Smooth scrolling for mobile */
            html {
                scroll-behavior: smooth;
                -webkit-overflow-scrolling: touch;
            }
            
            /* Pull-to-refresh styling */
            body {
                overscroll-behavior-y: contain;
            }
        }
        
        @keyframes slideInUpMobile {
            from { 
                opacity: 0;
                transform: translateY(100%);
            }
            to { 
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Extra small mobile devices */
        @media (max-width: 480px) {
            .container {
                padding: 8px;
            }
            
            .header {
                padding: 12px;
                flex-direction: column;
                gap: 12px;
                text-align: center;
            }
            
            .header-content h1 {
                font-size: 1.3rem;
            }
            
            .controls {
                padding: 12px;
            }
            
            .control-section {
                padding: 12px;
            }
            
            .goat-gif {
                height: 200px;
            }
            
            .pagination a, .pagination span {
                padding: 8px 10px;
                min-width: 36px;
                min-height: 36px;
                font-size: 13px;
            }
            
            /* Stack pagination on very small screens */
            .pagination {
                justify-content: center;
                max-width: 100%;
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
                padding: 0 20px;
            }
        }
        
        /* High DPI displays */
        @media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
            .goat-gif {
                image-rendering: -webkit-optimize-contrast;
                image-rendering: crisp-edges;
            }
        }
        
        /* Dark mode support for Safari */
        @media (prefers-color-scheme: dark) {
            .header {
                background: rgba(26, 26, 26, 0.95);
            }
        }
        
        /* Reduced motion support */
        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }
        }
        
        /* Focus styles for keyboard navigation */
        .btn:focus-visible,
        .btn-secondary:focus-visible,
        .goat-link:focus-visible {
            outline: 3px solid var(--accent-primary);
            outline-offset: 2px;
        }
        
        input:focus-visible {
            outline: 3px solid var(--accent-primary);
            outline-offset: 2px;
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
                    <?php foreach ($currentGoats as $index => $goatId): ?>
                        <div class="goat-item" style="--index: <?php echo $index; ?>;">
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
                        ?>
                        <?php if ($page > 1): ?>
                            <a href="?page=1<?php echo $searchParam; ?>">First</a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $searchParam; ?>">Previous</a>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page - 2);
                        $end = min($totalPages, $page + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <?php if ($i == $page): ?>
                                <span class="current"><?php echo $i; ?></span>
                            <?php else: ?>
                                <a href="?page=<?php echo $i; ?><?php echo $searchParam; ?>"><?php echo $i; ?></a>
                            <?php endif; ?>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                            <a href="?page=<?php echo $page + 1; ?><?php echo $searchParam; ?>">Next</a>
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
        
        // Add staggered animation to gallery items on mobile
        if (window.innerWidth <= 768) {
            const galleryItems = document.querySelectorAll('.goat-item');
            galleryItems.forEach((item, index) => {
                item.style.setProperty('--index', index);
                item.style.animationDelay = `${index * 0.1}s`;
            });
        }
        
        // Add pull-to-refresh feel (visual feedback only)
        let startY = 0;
        let currentY = 0;
        let pullDelta = 0;
        
        document.addEventListener('touchstart', function(e) {
            startY = e.touches[0].clientY;
        }, { passive: true });
        
        document.addEventListener('touchmove', function(e) {
            currentY = e.touches[0].clientY;
            pullDelta = currentY - startY;
            
            if (pullDelta > 0 && window.pageYOffset === 0) {
                e.preventDefault();
                // Visual feedback for pull-to-refresh
                const header = document.querySelector('.header');
                if (header && pullDelta < 100) {
                    header.style.transform = `translateY(${Math.min(pullDelta * 0.5, 20)}px)`;
                    header.style.opacity = 1 - (pullDelta * 0.01);
                }
            }
        }, { passive: false });
        
        document.addEventListener('touchend', function(e) {
            // Reset header position
            const header = document.querySelector('.header');
            if (header) {
                header.style.transform = '';
                header.style.opacity = '';
            }
            
            // If pulled far enough, reload page
            if (pullDelta > 80 && window.pageYOffset === 0) {
                location.reload();
            }
            
            startY = 0;
            currentY = 0;
            pullDelta = 0;
        }, { passive: true });
        
        // Add haptic feedback on button press (if supported)
        document.querySelectorAll('.btn, .btn-secondary').forEach(button => {
            button.addEventListener('touchstart', function() {
                if ('vibrate' in navigator) {
                    navigator.vibrate(10);
                }
            }, { passive: true });
        });
        
        // Smooth scroll to top when clicking header
        document.querySelector('.header')?.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
        
        // Add loading states for better UX
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function() {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.style.opacity = '0.7';
                    submitBtn.innerHTML = submitBtn.innerHTML.replace(/^/, '⏳ ');
                }
            });
        });
    </script>
</body>
</html>
