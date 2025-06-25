<?php

session_start();

// Configuration
$dataFile = '../data/goats.txt';
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Simple auth credentials (in production, use hashed passwords)
$adminUsername = 'admin';
$adminPassword = 'mko0)OKM'; // Change this!

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
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐐</text></svg>" type="image/svg+xml">
    <meta name="description" content="Admin interface for managing random goat GIFs"/>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-primary: #1e1b4b;
            --bg-secondary: #312e81;
            --bg-tertiary: #3730a3;
            --bg-quaternary: #4c1d95;
            --text-primary: #ffffff;
            --text-secondary: #e0e7ff;
            --text-muted: #a5b4fc;
            --text-subtle: #818cf8;
            --accent-primary: #8b5cf6;
            --accent-hover: #7c3aed;
            --accent-light: #a78bfa;
            --success: #10b981;
            --success-bg: rgba(16, 185, 129, 0.15);
            --error: #f87171;
            --error-bg: rgba(248, 113, 113, 0.15);
            --warning: #fbbf24;
            --warning-bg: rgba(251, 191, 36, 0.15);
            --border: #4c1d95;
            --border-hover: #5b21b6;
            --shadow-sm: 0 1px 2px 0 rgba(30, 27, 75, 0.4);
            --shadow: 0 4px 6px -1px rgba(30, 27, 75, 0.5);
            --shadow-lg: 0 10px 15px -3px rgba(30, 27, 75, 0.6);
            --shadow-xl: 0 20px 25px -5px rgba(30, 27, 75, 0.7);
            --gradient-primary: linear-gradient(135deg, var(--accent-primary), var(--accent-light));
            --gradient-surface: linear-gradient(145deg, var(--bg-secondary), var(--bg-tertiary));
            --gradient-background: linear-gradient(135deg, #1e1b4b, #312e81, #3730a3);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: var(--gradient-background);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            font-feature-settings: 'cv11', 'ss01';
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 32px 24px;
        }
        
        .header {
            background: var(--gradient-surface);
            padding: 32px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 32px;
            border: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gradient-primary);
        }
        
        .header-content h1 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 2.5rem;
            font-weight: 700;
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .stats {
            color: var(--text-muted);
            font-size: 15px;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 16px;
            flex-wrap: wrap;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-quaternary);
            border-radius: 12px;
            border: 1px solid var(--border);
        }
        
        .logout-btn {
            background: linear-gradient(135deg, #ec4899, #be185d);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: var(--shadow);
        }
        
        .logout-btn:hover {
            background: linear-gradient(135deg, #be185d, #9d174d);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .message {
            padding: 20px 24px;
            border-radius: 16px;
            margin-bottom: 32px;
            font-weight: 500;
            border: 1px solid;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .message.success {
            background: var(--success-bg);
            color: var(--success);
            border-color: var(--success);
        }
        
        .message.error {
            background: var(--error-bg);
            color: var(--error);
            border-color: var(--error);
        }
        
        .message.warning {
            background: var(--warning-bg);
            color: var(--warning);
            border-color: var(--warning);
        }
        
        .controls {
            background: var(--gradient-surface);
            padding: 32px;
            border-radius: 20px;
            box-shadow: var(--shadow-lg);
            margin-bottom: 32px;
            border: 1px solid var(--border);
        }
        
        .controls-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            align-items: start;
        }
        
        .control-section {
            background: var(--bg-tertiary);
            padding: 28px;
            border-radius: 16px;
            border: 1px solid var(--border);
            position: relative;
            display: flex;
            flex-direction: column;
            min-height: 280px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .control-section:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            border-color: var(--border-hover);
        }
        
        .control-section h3 {
            margin-bottom: 24px;
            color: var(--text-primary);
            font-size: 1.375rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .control-section h3::before {
            content: '';
            width: 4px;
            height: 24px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }
        
        .form-content {
            flex: 1;
            display: flex;
            flex-direction: column;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-actions {
            margin-top: auto;
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding-top: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            color: var(--text-secondary);
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        input[type="url"], input[type="text"], input[type="password"] {
            width: 100%;
            padding: 16px 18px;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 15px;
            font-weight: 500;
            background: var(--bg-quaternary);
            color: var(--text-primary);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: inherit;
        }
        
        input[type="url"]:focus, input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 4px rgba(139, 92, 246, 0.2);
            background: var(--bg-secondary);
        }
        
        input[type="url"]::placeholder, input[type="text"]::placeholder, input[type="password"]::placeholder {
            color: var(--text-subtle);
            font-weight: 400;
        }
        
        .btn {
            background: var(--gradient-primary);
            color: white;
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            justify-content: center;
            text-decoration: none;
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }
        
        .btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }
        
        .btn:hover::before {
            left: 100%;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .btn.secondary {
            background: var(--bg-quaternary);
            color: var(--text-secondary);
            border: 1px solid var(--border);
        }
        
        .btn.secondary:hover {
            background: var(--border-hover);
            border-color: var(--border-hover);
        }
        
        .btn.danger {
            background: linear-gradient(135deg, var(--error), #ec4899);
        }
        
        .btn.danger:hover {
            background: linear-gradient(135deg, #ec4899, #be185d);
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(340px, 1fr));
            gap: 32px;
            margin-bottom: 40px;
        }
        
        .goat-item {
            background: var(--gradient-surface);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: var(--shadow-lg);
            border: 1px solid var(--border);
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            display: flex;
            flex-direction: column;
            height: 420px;
        }
        
        .goat-item:hover {
            transform: translateY(-8px) scale(1.02);
            box-shadow: var(--shadow-xl);
            border-color: var(--border-hover);
        }
        
        .goat-gif {
            width: 100%;
            height: 280px;
            object-fit: cover;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .goat-item:hover .goat-gif {
            scale: 1.05;
        }
        
        .goat-info {
            padding: 24px;
            flex: 1;
            display: flex;
            flex-direction: column;
            background: var(--bg-secondary);
        }
        
        .goat-id {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 16px;
            background: var(--bg-quaternary);
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid var(--border);
            font-weight: 500;
            flex: 1;
        }
        
        .goat-actions {
            display: flex;
            justify-content: flex-end;
            margin-top: auto;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 8px;
            margin: 48px 0;
        }
        
        .pagination a, .pagination span {
            padding: 12px 16px;
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            border-radius: 12px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: var(--bg-secondary);
            font-weight: 500;
            min-width: 48px;
            text-align: center;
        }
        
        .pagination a:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        
        .pagination .current {
            background: var(--gradient-primary);
            border-color: var(--accent-primary);
            box-shadow: var(--shadow);
        }
        
        .empty-state {
            text-align: center;
            padding: 120px 20px;
            color: var(--text-muted);
        }
        
        .empty-state h3 {
            margin-bottom: 16px;
            font-size: 2rem;
            font-weight: 600;
            color: var(--text-secondary);
        }
        
        .empty-state p {
            font-size: 16px;
            margin-bottom: 8px;
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
            background-color: rgba(30, 27, 75, 0.9);
            backdrop-filter: blur(8px);
        }
        
        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            animation: fadeIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        .modal-content {
            background: var(--gradient-surface);
            padding: 40px;
            border-radius: 24px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border);
            max-width: 480px;
            width: 90%;
            animation: slideIn 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
        }
        
        .modal-content::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--gradient-primary);
        }
        
        .modal-header {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .modal-header h2 {
            color: var(--text-primary);
            margin-bottom: 12px;
            font-size: 1.75rem;
            font-weight: 700;
        }
        
        .modal-header p {
            color: var(--text-muted);
            font-size: 15px;
            line-height: 1.5;
        }
        
        .modal-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 32px;
        }
        
        .login-error {
            color: var(--error);
            font-size: 14px;
            margin-top: 16px;
            text-align: center;
            font-weight: 500;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes slideIn {
            from { 
                opacity: 0;
                transform: translateY(-40px) scale(0.95);
            }
            to { 
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        @media (max-width: 1024px) {
            .container {
                padding: 24px 20px;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
                gap: 24px;
            }
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 20px 16px;
            }
            
            .header {
                padding: 24px;
                flex-direction: column;
                text-align: center;
            }
            
            .header-content h1 {
                font-size: 2rem;
            }
            
            .stats {
                justify-content: center;
            }
            
            .gallery {
                grid-template-columns: 1fr;
                gap: 24px;
            }
            
            .goat-item {
                height: auto;
            }
            
            .modal-content {
                margin: 20px;
                padding: 32px 24px;
            }
            
            .controls {
                padding: 24px;
            }
            
            .control-section {
                padding: 20px;
                min-height: auto;
            }
        }
        
        @media (max-width: 480px) {
            .header-content h1 {
                font-size: 1.75rem;
            }
            
            .stats {
                flex-direction: column;
                gap: 8px;
            }
            
            .modal-buttons {
                flex-direction: column;
            }
            
            .form-actions {
                flex-direction: column;
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
                    <h2>🐐 Admin Access</h2>
                    <p>Please enter your credentials to manage the goat gallery</p>
                </div>
                <form method="POST">
                    <div class="form-group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" placeholder="Enter username" required>
                    </div>
                    <div class="form-group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter password" required>
                    </div>
                    <button type="submit" name="login" class="btn" style="width: 100%; margin-top: 8px;">
                        🔓 Login to Dashboard
                    </button>
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
                            <div class="stat-item">
                                🔍 Search: "<?php echo htmlspecialchars($search); ?>"
                            </div>
                            <div class="stat-item">
                                📊 <?php echo $totalGoats; ?> result<?php echo $totalGoats !== 1 ? 's' : ''; ?>
                            </div>
                        <?php else: ?>
                            <div class="stat-item">
                                🎯 Total Goats: <?php echo count($allGoatIds); ?>
                            </div>
                        <?php endif; ?>
                        <div class="stat-item">
                            📄 Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
                        </div>
                    </div>
                </div>
                <a href="?logout=1" class="logout-btn">
                    🚪 Logout
                </a>
            </div>
            
            <?php if ($message): ?>
                <div class="message <?php echo $messageType; ?>">
                    <?php if ($messageType === 'success'): ?>✅<?php endif; ?>
                    <?php if ($messageType === 'error'): ?>❌<?php endif; ?>
                    <?php if ($messageType === 'warning'): ?>⚠️<?php endif; ?>
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <div class="controls">
                <div class="controls-grid">
                    <div class="control-section">
                        <h3>Add New Goat</h3>
                        <form method="POST" class="form-content">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label for="giphy_url">Giphy URL</label>
                                <input type="url" id="giphy_url" name="giphy_url" 
                                       placeholder="https://giphy.com/gifs/tongue-goat-cMso9wDwqSy3e" required>
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn">
                                    ➕ Add Goat
                                </button>
                            </div>
                        </form>
                    </div>
                    
                    <div class="control-section">
                        <h3>Search Gallery</h3>
                        <form method="GET" class="form-content">
                            <div class="form-group">
                                <label for="search">Search by Goat ID</label>
                                <input type="text" id="search" name="search" 
                                       placeholder="Enter part of goat ID..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="form-actions">
                                <button type="submit" class="btn">
                                    🔍 Search
                                </button>
                                <?php if ($search): ?>
                                    <a href="?" class="btn secondary">
                                        🗑️ Clear
                                    </a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <?php if (empty($currentGoats)): ?>
                <div class="empty-state">
                    <?php if ($search): ?>
                        <h3>🔍 No goats found</h3>
                        <p>No goats match your search for "<?php echo htmlspecialchars($search); ?>"</p>
                        <p><a href="?" style="color: var(--accent-primary); text-decoration: none; font-weight: 600;">Clear search</a> to see all goats</p>
                    <?php else: ?>
                        <h3>🐐 No goats yet</h3>
                        <p>Start building your goat gallery by adding some GIFs!</p>
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
                                    <button type="button" class="btn danger" 
                                            onclick="showDeleteModal('<?php echo htmlspecialchars($goatId); ?>')">
                                        🗑️ Delete
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
                            <a href="?page=1<?php echo $searchParam; ?>">⏮️</a>
                            <a href="?page=<?php echo $page - 1; ?><?php echo $searchParam; ?>">◀️</a>
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
                            <a href="?page=<?php echo $page + 1; ?><?php echo $searchParam; ?>">▶️</a>
                            <a href="?page=<?php echo $totalPages; ?><?php echo $searchParam; ?>">⏭️</a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div id="deleteModal" class="modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>🗑️ Delete Goat</h2>
                    <p>Are you sure you want to delete this goat? This action cannot be undone and will permanently remove it from your gallery.</p>
                </div>
                <div class="modal-buttons">
                    <button type="button" class="btn secondary" onclick="hideDeleteModal()">
                        ❌ Cancel
                    </button>
                    <button type="button" class="btn danger" onclick="confirmDelete()">
                        🗑️ Delete Goat
                    </button>
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
        
        // Add loading states to forms
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    const originalText = submitBtn.innerHTML;
                    submitBtn.innerHTML = '⏳ Processing...';
                    submitBtn.disabled = true;
                    
                    // Re-enable if form doesn't submit (validation error)
                    setTimeout(() => {
                        submitBtn.innerHTML = originalText;
                        submitBtn.disabled = false;
                    }, 3000);
                }
            });
        });
        
        // Add smooth scrolling to pagination
        document.querySelectorAll('.pagination a').forEach(link => {
            link.addEventListener('click', function(e) {
                document.querySelector('.header').scrollIntoView({ 
                    behavior: 'smooth',
                    block: 'start'
                });
            });
        });
    </script>
</body>
</html>
