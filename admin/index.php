<?php
// Goat Gallery Admin - Single File PHP App
session_start();

// Configuration
$dataFile = '../data/goats.txt';
$perPage = 12;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

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

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
$allGoatIds = readGoatIds($dataFile);
$totalGoats = count($allGoatIds);
$totalPages = ceil($totalGoats / $perPage);
$offset = ($page - 1) * $perPage;
$currentGoats = array_slice($allGoatIds, $offset, $perPage);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Goat Gallery Admin</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f5f5f5;
            color: #333;
            line-height: 1.6;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }
        
        .header {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .header h1 {
            color: #2c3e50;
            margin-bottom: 10px;
        }
        
        .stats {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .message {
            padding: 12px;
            border-radius: 4px;
            margin-bottom: 20px;
            font-weight: 500;
        }
        
        .message.success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .message.error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .message.warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .controls {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 15px;
        }
        
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #2c3e50;
        }
        
        input[type="url"], input[type="text"] {
            width: 100%;
            padding: 10px;
            border: 2px solid #e1e8ed;
            border-radius: 4px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input[type="url"]:focus, input[type="text"]:focus {
            outline: none;
            border-color: #3498db;
        }
        
        .btn {
            background: #3498db;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            transition: background 0.3s;
        }
        
        .btn:hover {
            background: #2980b9;
        }
        
        .btn.danger {
            background: #e74c3c;
        }
        
        .btn.danger:hover {
            background: #c0392b;
        }
        
        .gallery {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .goat-item {
            background: #fff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .goat-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        
        .goat-gif {
            width: 100%;
            height: 200px;
            object-fit: cover;
        }
        
        .goat-info {
            padding: 15px;
        }
        
        .goat-id {
            font-family: monospace;
            font-size: 12px;
            color: #7f8c8d;
            margin-bottom: 10px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
            transition: all 0.3s;
        }
        
        .pagination a:hover {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .pagination .current {
            background: #3498db;
            color: white;
            border-color: #3498db;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #7f8c8d;
        }
        
        .empty-state h3 {
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .gallery {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🐐 Goat Gallery Admin</h1>
            <div class="stats">
                Total Goats: <?php echo $totalGoats; ?> | 
                Page <?php echo $page; ?> of <?php echo max(1, $totalPages); ?>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <div class="controls">
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
        
        <?php if (empty($currentGoats)): ?>
            <div class="empty-state">
                <h3>No goats found</h3>
                <p>Add some goats using the form above!</p>
            </div>
        <?php else: ?>
            <div class="gallery">
                <?php foreach ($currentGoats as $goatId): ?>
                    <div class="goat-item">
                        <img src="https://media.giphy.com/media/<?php echo htmlspecialchars($goatId); ?>/giphy.gif" 
                             alt="Goat GIF" class="goat-gif" loading="lazy">
                        <div class="goat-info">
                            <div class="goat-id">ID: <?php echo htmlspecialchars($goatId); ?></div>
                            <form method="POST" style="display: inline;" 
                                  onsubmit="return confirm('Are you sure you want to delete this goat?');">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="goat_id" value="<?php echo htmlspecialchars($goatId); ?>">
                                <button type="submit" class="btn danger">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">First</a>
                        <a href="?page=<?php echo $page - 1; ?>">Previous</a>
                    <?php endif; ?>
                    
                    <?php
                    $start = max(1, $page - 2);
                    $end = min($totalPages, $page + 2);
                    
                    for ($i = $start; $i <= $end; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="current"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">Next</a>
                        <a href="?page=<?php echo $totalPages; ?>">Last</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
