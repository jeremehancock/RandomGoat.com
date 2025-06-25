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
    <title>Random Goat</title>
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
            --accent-primary: #3b82f6;
            --accent-hover: #2563eb;
            --success: #10b981;
            --error: #ef4444;
            --warning: #f59e0b;
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
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            transition: background 0.3s;
        }
        
        .logout-btn:hover {
            background: #dc2626;
        }
        
        .message {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 500;
            border: 1px solid;
        }
        
        .message.success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-color: var(--success);
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--error);
            border-color: var(--error);
        }
        
        .message.warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
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
        
        .controls h3 {
            margin-bottom: 20px;
            color: var(--text-primary);
            font-size: 1.25rem;
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
            background: var(--bg-tertiary);
            color: var(--text-primary);
            transition: border-color 0.3s, box-shadow 0.3s;
        }
        
        input[type="url"]:focus, input[type="text"]:focus, input[type="password"]:focus {
            outline: none;
            border-color: var(--accent-primary);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .btn {
            background: var(--accent-primary);
            color: white;
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s, transform 0.2s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn:hover {
            background: var(--accent-hover);
            transform: translateY(-1px);
        }
        
        .btn.danger {
            background: var(--error);
        }
        
        .btn.danger:hover {
            background: #dc2626;
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 30px;
        }
        
        .goat-item {
            background: var(--bg-secondary);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 6px var(--shadow);
            border: 1px solid var(--border);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .goat-item:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 25px var(--shadow);
        }
        
        .goat-gif {
            width: 100%;
            height: 220px;
            object-fit: cover;
        }
        
        .goat-info {
            padding: 20px;
        }
        
        .goat-id {
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 12px;
            color: var(--text-muted);
            margin-bottom: 15px;
            background: var(--bg-tertiary);
            padding: 8px 12px;
            border-radius: 6px;
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
            border: 1px solid var(--border);
            text-decoration: none;
            color: var(--text-primary);
            border-radius: 8px;
            transition: all 0.3s;
            background: var(--bg-secondary);
        }
        
        .pagination a:hover {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
            transform: translateY(-1px);
        }
        
        .pagination .current {
            background: var(--accent-primary);
            border-color: var(--accent-primary);
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
            justify-content: center;
            margin-top: 24px;
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
            border: 1px solid var(--border);
        }
        
        .btn-secondary:hover {
            background: var(--border);
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
            .container {
                padding: 15px;
            }
            
            .gallery {
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 20px;
            }
            
            .header {
                flex-direction: column;
                text-align: center;
            }
            
            .controls-grid {
                grid-template-columns: 1fr;
                gap: 30px;
            }
            
            .modal-content {
                margin: 20px;
                padding: 24px;
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
                    <button type="submit" name="login" class="btn" style="width: 100%;">Login</button>
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
                    <div>
                        <h3>Add New Goat</h3>
                        <form method="POST">
                            <input type="hidden" name="action" value="add">
                            <div class="form-group">
                                <label for="giphy_url">Giphy URL:</label>
                                <input type="url" id="giphy_url" name="giphy_url" 
                                       placeholder="https://giphy.com/gifs/tongue-goat-cMso9wDwqSy3e" required>
                            </div>
                            <button type="submit" class="btn">Add Goat</button>
                        </form>
                    </div>
                    
                    <div>
                        <h3>Search Gallery</h3>
                        <form method="GET">
                            <div class="form-group">
                                <label for="search">Search by Goat ID:</label>
                                <input type="text" id="search" name="search" 
                                       placeholder="Enter part of goat ID..." 
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div style="display: flex; gap: 10px;">
                                <button type="submit" class="btn">🔍 Search</button>
                                <?php if ($search): ?>
                                    <a href="?" class="btn btn-secondary">Clear</a>
                                <?php endif; ?>
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
                                <button type="button" class="btn danger" 
                                        onclick="showDeleteModal('<?php echo htmlspecialchars($goatId); ?>')">
                                    Delete
                                </button>
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
    </script>
</body>
</html>
