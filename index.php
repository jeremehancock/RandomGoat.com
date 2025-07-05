<?php
// Parse clean URLs like domain.com/gifid (with optional ?season=christmas)
$request_uri = $_SERVER['REQUEST_URI'];
$path = parse_url($request_uri, PHP_URL_PATH);

// Remove leading slash and split path into components
$path_parts = array_filter(explode('/', trim($path, '/')));

// Extract gif ID from URL path
$gif_id = '';

if (!empty($path_parts)) {
    // First part is the gif ID (if it's not a known route like 'embed.php')
    $first_part = $path_parts[0];

    // Skip if it's a known file or route
    if (!in_array($first_part, ['embed.php', 'data', 'goats', 'images', 'favicon.ico'])) {
        $gif_id = htmlspecialchars($first_part);
    }
}

// Get season from query parameters (e.g., ?season=christmas)
$season = '';
if (isset($_GET['season']) && !empty($_GET['season'])) {
    $season = htmlspecialchars($_GET['season']);
}

// Set content type
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Random Goat</title>
    <link rel="icon"
        href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🐐</text></svg>"
        type="image/svg+xml">
    <meta name="description" content="Looking for random goat gifs? Look no further!" />
    <meta name="keywords"
        content="pointless, useless, web, websites, sites, goat, goats, gif, gifs, random, weird, odd, bizarre" />
    <meta property="og:title" content="Random Goat" />
    <meta property="og:type" content="website" />
    <meta property="og:description" content="Looking for random goat gifs? Look no further!" />
    <meta property="og:url" content="https://randomgoat.com" />
    <meta property="og:image" content="https://randomgoat.com/images/goat-og-img.png" />
    <meta property="og:site_name" content="randomgoat.com" />
    <style>
        :root {
            --vh: 1vh;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        html {
            width: 100%;
            height: 100%;
            overflow: hidden;
            -webkit-text-size-adjust: 100%;
        }

        body {
            font-family: Arial, sans-serif;
            background: #000;
            cursor: pointer;
            user-select: none;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            position: relative;
            overflow: hidden;
            margin: 0;
            padding: 0;
        }

        .container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .splash-screen {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            transition: opacity 1s ease;
            z-index: 1000;
        }

        .splash-screen.hide {
            opacity: 0;
            pointer-events: none;
        }

        .splash-screen h1 {
            font-size: 3rem;
            margin-bottom: 1rem;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
            animation: pulse 2s ease-in-out infinite;
        }

        .splash-screen p {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }

        @keyframes pulse {

            0%,
            100% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.05);
            }
        }

        @keyframes spin {
            0% {
                transform: rotate(0deg);
            }

            100% {
                transform: rotate(360deg);
            }
        }

        .gif-container {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            background: #000;
            opacity: 1;
        }

        .gif-layer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .gif-layer img {
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            object-fit: cover;
            object-position: center;
            opacity: 0;
            transition: all 0.6s cubic-bezier(0.4, 0, 0.2, 1);
            transform: scale(1.02);
            filter: brightness(0.9);
        }

        .gif-layer.active img {
            opacity: 1;
            transform: scale(1);
            filter: brightness(1);
        }

        .gif-layer.fade-out img {
            opacity: 0;
            transform: scale(0.98);
            filter: brightness(0.8);
        }

        .loading-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            background: linear-gradient(45deg, #000 25%, transparent 25%, transparent 75%, #000 75%, #000),
                linear-gradient(45deg, #000 25%, transparent 25%, transparent 75%, #000 75%, #000);
            background-size: 20px 20px;
            background-position: 0 0, 10px 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: 10;
            animation: slide 2s linear infinite;
        }

        .loading-overlay.show {
            opacity: 0.1;
        }

        @keyframes slide {
            0% {
                background-position: 0 0, 10px 10px;
            }

            100% {
                background-position: 20px 20px, 30px 30px;
            }
        }

        .progress-bar {
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 8px;
            background: linear-gradient(90deg, #667eea, #764ba2, #f093fb);
            transition: width 0.1s linear;
            z-index: 20;
            box-shadow:
                0 0 20px rgba(102, 126, 234, 0.8),
                0 -2px 10px rgba(102, 126, 234, 0.4),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            animation: progressGlow 2s ease-in-out infinite alternate;
        }

        @keyframes progressGlow {
            0% {
                box-shadow:
                    0 0 20px rgba(102, 126, 234, 0.8),
                    0 -2px 10px rgba(102, 126, 234, 0.4),
                    inset 0 1px 0 rgba(255, 255, 255, 0.3);
            }

            100% {
                box-shadow:
                    0 0 30px rgba(102, 126, 234, 1),
                    0 -4px 15px rgba(102, 126, 234, 0.6),
                    inset 0 1px 0 rgba(255, 255, 255, 0.5);
            }
        }

        .logo {
            position: absolute;
            bottom: 10px;
            right: 10px;
            display: flex;
            align-items: center;
            gap: 12px;
            background: linear-gradient(135deg, rgba(0, 0, 0, 1), rgba(0, 0, 0, 1));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 35px;
            padding: 12px 24px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 40;
            text-decoration: none;
            color: white;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-weight: 600;
            font-size: 1.3rem;
            letter-spacing: -0.5px;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
        }

        .logo:hover {
            transform: translateY(-2px) scale(1.05);
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6));
            box-shadow:
                0 12px 40px rgba(0, 0, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .logo.disabled {
            cursor: default;
            pointer-events: none;
        }

        .logo.disabled:hover {
            transform: none;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.5));
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            border-color: rgba(255, 255, 255, 0.3);
        }

        .logo .goat-icon {
            font-size: 1.6rem;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
            animation: bobble 3s ease-in-out infinite;
        }

        @keyframes bobble {

            0%,
            100% {
                transform: translateY(0px) rotate(0deg);
            }

            25% {
                transform: translateY(-1px) rotate(1deg);
            }

            50% {
                transform: translateY(-2px) rotate(0deg);
            }

            75% {
                transform: translateY(-1px) rotate(-1deg);
            }
        }

        .logo .site-text {
            background: linear-gradient(45deg, #ff6b9d, #c471ed, #12c2e9);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 4s ease-in-out infinite;
            font-weight: 700;
        }

        @keyframes gradientShift {

            0%,
            100% {
                background-position: 0% 50%;
            }

            50% {
                background-position: 100% 50%;
            }
        }

        .embed-button,
        .share-button {
            position: absolute;
            top: 10px;
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.5));
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 25px;
            padding: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            z-index: 40;
            color: white;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            font-weight: 600;
            font-size: 1.2rem;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.8);
            box-shadow:
                0 8px 32px rgba(0, 0, 0, 0.5),
                inset 0 1px 0 rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
        }

        .embed-button {
            right: 10px;
        }

        .share-button {
            left: 10px;
        }

        .embed-button:hover,
        .share-button:hover {
            transform: translateY(-2px) scale(1.05);
            background: linear-gradient(135deg, rgba(0, 0, 0, 0.8), rgba(0, 0, 0, 0.6));
            box-shadow:
                0 12px 40px rgba(0, 0, 0, 0.6),
                inset 0 1px 0 rgba(255, 255, 255, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
        }

        .embed-icon {
            width: 26px;
            height: 26px;
            fill: currentColor;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
        }

        .modal-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            height: calc(var(--vh, 1vh) * 100);
            background: rgba(0, 0, 0, 0.8);
            backdrop-filter: blur(5px);
            display: flex;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: all 0.3s ease;
        }

        .modal-overlay.show {
            opacity: 1;
            visibility: visible;
        }

        .modal {
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            border-radius: 20px;
            padding: 25px;
            max-width: 550px;
            width: 90%;
            max-height: 90%;
            max-height: calc(var(--vh, 1vh) * 90);
            overflow-y: auto;
            box-shadow:
                0 20px 60px rgba(0, 0, 0, 0.8),
                inset 0 1px 0 rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            transform: scale(0.9) translateY(20px);
            transition: all 0.3s ease;
            color: white;
        }

        .modal-overlay.show .modal {
            transform: scale(1) translateY(0);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .modal-title {
            font-size: 1.4rem;
            font-weight: 700;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            letter-spacing: -0.5px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .modal-title .goat-icon {
            font-size: 1.3rem;
            filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.3));
        }

        .modal-title .title-text {
            background: linear-gradient(45deg, #ff6b9d, #c471ed, #12c2e9);
            background-size: 200% 200%;
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: gradientShift 4s ease-in-out infinite;
        }

        .close-button {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            padding: 5px;
            border-radius: 50%;
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .close-button:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: scale(1.1);
        }

        .embed-preview {
            margin-bottom: 20px;
        }

        .embed-preview h3 {
            margin-bottom: 12px;
            color: #fff;
            font-size: 1rem;
        }

        .preview-container {
            border: 2px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 12px;
            background: rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .preview-iframe {
            width: 100%;
            border-radius: 8px;
            border: none;
        }

        .code-section h3 {
            margin-bottom: 12px;
            color: #fff;
            font-size: 1rem;
        }

        .code-container {
            position: relative;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            height: 120px;
        }

        .code-container-small {
            position: relative;
            background: rgba(0, 0, 0, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 12px;
            height: 62px;
        }


        .code-block {
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace;
            font-size: 0.85rem;
            color: #00ff88;
            line-height: 1.6;
            margin: 0;
            word-break: break-all;
            white-space: pre-wrap;
        }

        .copy-button {
            position: absolute;
            bottom: 15px;
            right: 15px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border: none;
            color: white;
            padding: 8px 15px;
            border-radius: 8px;
            cursor: pointer;
            font-size: 0.8rem;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }

        .copy-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }

        .copy-button.copied {
            background: linear-gradient(135deg, #00ff88, #00cc6a);
            box-shadow: 0 4px 15px rgba(0, 255, 136, 0.3);
        }

        .embed-instructions {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
            padding: 12px;
            margin-top: 15px;
        }

        .embed-instructions h4 {
            margin-bottom: 8px;
            color: #ff6b9d;
            font-size: 0.95rem;
        }

        .embed-instructions p {
            margin-bottom: 6px;
            font-size: 0.85rem;
            line-height: 1.3;
            opacity: 0.9;
        }

        .seasonal-mascot {
            position: absolute;
            bottom: 0;
            left: 0;
            height: auto;
            max-height: none;
            width: auto;
            z-index: 25;
            opacity: 0;
            transform: translateX(-20px) scale(0.9);
            transition: all 0.6s ease;
            filter: drop-shadow(2px 2px 8px rgba(0, 0, 0, 0.3));
        }

        .seasonal-mascot.show {
            opacity: 1;
            transform: translateX(0) scale(1);
        }

        .error-message {
            color: #ff6b6b;
            font-size: 1.1rem;
            text-align: center;
            background: rgba(0, 0, 0, 0.9);
            padding: 20px;
            border-radius: 10px;
            margin: 20px;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            border: 1px solid #ff6b6b;
            backdrop-filter: blur(10px);
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }

            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        /* Mobile-specific fixes */
        @media (max-width: 768px) {
            .splash-screen h1 {
                font-size: 2rem;
            }

            .logo {
                bottom: 10px;
                right: 10px;
                padding: 10px 18px;
                font-size: 1rem;
                gap: 10px;
            }

            .logo .goat-icon {
                font-size: 1.4rem;
            }

            .embed-button,
            .share-button {
                top: 10px;
                padding: 8px 15px;
                font-size: 0.8rem;
                gap: 6px;
            }

            .embed-button {
                right: 10px;
            }

            .share-button {
                left: 10px;
            }

            .modal {
                width: 95%;
                padding: 15px;
                margin: 10px;
            }

            .modal-title {
                font-size: 1.2rem;
                gap: 8px;
            }

            .modal-title .goat-icon {
                font-size: 1.1rem;
            }

            .preview-iframe {
                height: 200px;
                max-width: 300px;
            }

            .code-block {
                font-size: 0.75rem;
            }

            .copy-button {
                padding: 6px 12px;
                font-size: 0.75rem;
            }

            .embed-instructions p {
                font-size: 0.8rem;
            }
        }

        /* Safe area padding for devices with notches */
        @supports (padding: max(0px)) {
            .logo {
                bottom: max(10px, env(safe-area-inset-bottom, 10px));
                right: max(10px, env(safe-area-inset-right, 10px));
            }

            .embed-button {
                top: max(10px, env(safe-area-inset-top, 10px));
                right: max(10px, env(safe-area-inset-right, 10px));
            }

            .share-button {
                top: max(10px, env(safe-area-inset-top, 10px));
                left: max(10px, env(safe-area-inset-left, 10px));
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="splash-screen" id="splashScreen">
            <h1>🐐 Loading Goats...</h1>
        </div>

        <div class="modal-overlay" id="shareModalOverlay">
            <div class="modal">
                <div class="modal-header">
                    <h2 class="modal-title">
                        <span class="goat-icon">🔗</span>
                        <span class="title-text">Share This Goat</span>
                    </h2>
                    <button class="close-button" id="closeShareModal">×</button>
                </div>

                <div class="embed-preview">
                    <h3>Short URL:</h3>
                    <div class="code-container-small">
                        <button class="copy-button" id="copyShareButton">Copy</button>
                        <pre class="code-block" id="shareUrl">rdgt.co/loading...</pre>
                    </div>
                </div>

                <div class="embed-instructions">
                    <h4>Share this goat:</h4>
                    <p>1. Copy the short URL above</p>
                    <p>2. Share it with your friends</p>
                    <p>3. They'll see the same goat gif! 🐐</p>
                </div>
            </div>
        </div>

        <button class="share-button" id="shareButton">
            <span>🔗</span>
        </button>

        <button class="embed-button" id="embedButton">
            <svg class="embed-icon" xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision"
                text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd"
                clip-rule="evenodd" viewBox="0 0 512 331.617">
                <path fill-rule="nonzero"
                    d="M271.099 21.308C274.787 6.304 289.956-2.873 304.96.815c15.005 3.688 24.181 18.857 20.493 33.862l-68.491 275.632c-3.689 15.005-18.857 24.181-33.862 20.493-15.005-3.688-24.181-18.857-20.493-33.862l68.492-275.632zm-118.45 224.344c11.616 10.167 12.795 27.834 2.628 39.45-10.168 11.615-27.835 12.794-39.45 2.627L9.544 194.604C-2.071 184.437-3.25 166.77 6.918 155.155c.873-.997 1.8-1.912 2.767-2.75l106.142-93.001c11.615-10.168 29.282-8.989 39.45 2.626 10.167 11.616 8.988 29.283-2.628 39.45l-82.27 72.086 82.27 72.086zm243.524 42.077c-11.615 10.167-29.282 8.988-39.45-2.627-10.167-11.616-8.988-29.283 2.628-39.45l82.27-72.086-82.27-72.086c-11.616-10.167-12.795-27.834-2.628-39.45 10.168-11.615 27.835-12.794 39.45-2.626l106.142 93.001a28.366 28.366 0 012.767 2.75c10.168 11.615 8.989 29.282-2.626 39.449l-106.283 93.125z" />
            </svg>
        </button>

        <div class="gif-container" id="gifContainer">
            <div class="gif-layer" id="layer1">
                <img alt="Goat gif">
            </div>
            <div class="gif-layer" id="layer2">
                <img alt="Goat gif">
            </div>
            <div class="loading-overlay" id="loadingOverlay"></div>
            <div class="progress-bar" id="progressBar"></div>
        </div>

        <a href="https://randomgoat.com" target="_blank" class="logo" id="logo">
            <span class="goat-icon">🐐</span>
            <span class="site-text">randomgoat.com</span>
        </a>

        <img class="seasonal-mascot" id="seasonalMascot" alt="Seasonal goat mascot">
    </div>

    <div class="modal-overlay" id="modalOverlay">
        <div class="modal">
            <div class="modal-header">
                <h2 class="modal-title">
                    <span class="goat-icon"><svg xmlns="http://www.w3.org/2000/svg" shape-rendering="geometricPrecision"
                            text-rendering="geometricPrecision" image-rendering="optimizeQuality" fill-rule="evenodd"
                            clip-rule="evenodd" viewBox="0 0 512 331.617" fill="white" style="margin-top: 10px;">
                            <path fill-rule="nonzero"
                                d="M271.099 21.308C274.787 6.304 289.956-2.873 304.96.815c15.005 3.688 24.181 18.857 20.493 33.862l-68.491 275.632c-3.689 15.005-18.857 24.181-33.862 20.493-15.005-3.688-24.181-18.857-20.493-33.862l68.492-275.632zm-118.45 224.344c11.616 10.167 12.795 27.834 2.628 39.45-10.168 11.615-27.835 12.794-39.45 2.627L9.544 194.604C-2.071 184.437-3.25 166.77 6.918 155.155c.873-.997 1.8-1.912 2.767-2.75l106.142-93.001c11.615-10.168 29.282-8.989 39.45 2.626 10.167 11.616 8.988 29.283-2.628 39.45l-82.27 72.086 82.27 72.086zm243.524 42.077c-11.615 10.167-29.282 8.988-39.45-2.627-10.167-11.616-8.988-29.283 2.628-39.45l82.27-72.086-82.27-72.086c-11.616-10.167-12.795-27.834-2.628-39.45 10.168-11.615 27.835-12.794 39.45-2.626l106.142 93.001a28.366 28.366 0 012.767 2.75c10.168 11.615 8.989 29.282-2.626 39.449l-106.283 93.125z" />
                        </svg></span>
                    <span class="title-text">Embed Random Goat</span>
                </h2>
                <button class="close-button" id="closeModal">×</button>
            </div>

            <div class="embed-preview">
                <h3>Preview:</h3>
                <div class="preview-container">
                    <iframe src="//randomgoat.com/embed.php" width="480" height="270" frameBorder="0"
                        class="preview-iframe" allowFullScreen>
                    </iframe>
                </div>
            </div>

            <div class="code-section">
                <h3>Embed Code:</h3>
                <div class="code-container">
                    <button class="copy-button" id="copyButton">Copy</button>
                    <pre class="code-block"
                        id="embedCode">&lt;iframe src='//randomgoat.com/embed.php' width='480' height='360' frameBorder='0' id='random-goat-embed' allowFullScreen&gt;&lt;/iframe&gt;</pre>
                </div>
            </div>

            <div class="embed-instructions">
                <h4>How to embed:</h4>
                <p>1. Copy the code above</p>
                <p>2. Paste it into your website's HTML</p>
                <p>3. Adjust width and height as needed</p>
                <p>4. Enjoy random goats on your site! 🐐</p>
            </div>
        </div>
    </div>

    <script>
        // PHP-parsed URL parameters - set by server
        window.phpUrlParams = {
            gifId: <?= json_encode($gif_id) ?>,
            season: <?= json_encode($season) ?>
        };

        // Set CSS custom property for viewport height on mobile
        function setVH() {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }

        // Set initial viewport height
        setVH();

        // Update viewport height on resize and orientation change
        window.addEventListener('resize', setVH);
        window.addEventListener('orientationchange', () => {
            setTimeout(setVH, 100);
        });

        class OptimizedGoatGifApp {
            constructor() {
                this.gifIds = [];
                this.goatsData = []; // Store full goat objects for future use
                this.currentGifIndex = -1;
                this.timer = null;
                this.countdown = 20;
                this.isLoading = false;
                this.currentLayer = 1;
                this.fallbackGifId = 'XdACBmg3lx6RG'; // Fallback gif ID

                // Enhanced preloading system
                this.preloadCache = new Map();
                this.priorityQueue = [];
                this.backgroundQueue = [];
                this.activePreloads = new Set();
                this.usedGifIds = new Set();
                this.loadingQueue = [];
                this.specificGifId = null;
                this.isShowingSpecificGif = false;
                this.isEmbedded = false;
                this.firstGifReady = false;
                this.forcedSeason = null;
                this.currentGifId = null;
                this.currentGoatData = null; // Store current goat data

                // Modal pause state tracking
                this.isModalPaused = false;
                this.pausedCountdown = null;

                // Transition state tracking
                this.transitionTimeout = null;

                // Adaptive preloading settings
                this.preloadConfig = {
                    immediate: 3,       // Keep 3 ready immediately for instant transitions
                    background: 2,      // Keep 2 more in background
                    batchSize: 1,       // Load 1 at a time to avoid blocking
                    idleTimeout: 100,   // Use idle time for preloading
                    retryDelay: 2000,   // Retry failed preloads after 2s
                    maxRetries: 2       // Max retry attempts per gif
                };

                // Network and performance monitoring
                this.performance = {
                    loadTimes: [],
                    failureRate: 0,
                    lastInteraction: Date.now(),
                    networkType: 'unknown',
                    isSlowNetwork: false
                };

                // Initialize network detection after performance object exists
                this.detectNetworkType();

                // Background scheduling
                this.backgroundScheduler = {
                    idleCallbackSupported: typeof requestIdleCallback !== 'undefined',
                    scheduledWork: [],
                    isProcessing: false
                };

                this.elements = {
                    splashScreen: document.getElementById('splashScreen'),
                    gifContainer: document.getElementById('gifContainer'),
                    layer1: document.getElementById('layer1'),
                    layer2: document.getElementById('layer2'),
                    loadingOverlay: document.getElementById('loadingOverlay'),
                    progressBar: document.getElementById('progressBar'),
                    logo: document.getElementById('logo'),
                    seasonalMascot: document.getElementById('seasonalMascot'),
                    embedButton: document.getElementById('embedButton'),
                    modalOverlay: document.getElementById('modalOverlay'),
                    closeModal: document.getElementById('closeModal'),
                    copyButton: document.getElementById('copyButton'),
                    embedCode: document.getElementById('embedCode'),
                    shareButton: document.getElementById('shareButton'),
                    shareModalOverlay: document.getElementById('shareModalOverlay'),
                    closeShareModal: document.getElementById('closeShareModal'),
                    copyShareButton: document.getElementById('copyShareButton'),
                    shareUrl: document.getElementById('shareUrl')
                };

                this.init();
            }

            detectNetworkType() {
                if ('connection' in navigator) {
                    const connection = navigator.connection;
                    this.performance.isSlowNetwork = connection.effectiveType === 'slow-2g' ||
                        connection.effectiveType === '2g' ||
                        connection.saveData;
                    this.performance.networkType = connection.effectiveType || 'unknown';
                } else {
                    this.performance.networkType = 'unknown';
                    this.performance.isSlowNetwork = false;
                }
            }

            // Intelligent background scheduler using idle time
            scheduleBackgroundWork(task, priority = 'normal') {
                const workItem = { task, priority, timestamp: Date.now() };

                if (this.backgroundScheduler.idleCallbackSupported) {
                    requestIdleCallback((deadline) => {
                        if (deadline.timeRemaining() > 0 && !this.backgroundScheduler.isProcessing) {
                            this.backgroundScheduler.isProcessing = true;
                            // Ensure task always returns a Promise
                            Promise.resolve(task()).finally(() => {
                                this.backgroundScheduler.isProcessing = false;
                            });
                        } else {
                            // Fallback to setTimeout if no idle time
                            setTimeout(task, priority === 'low' ? 500 : 100);
                        }
                    });
                } else {
                    // Fallback for browsers without requestIdleCallback
                    setTimeout(task, priority === 'low' ? 500 : 100);
                }
            }

            parseUrlParams() {
                // Use PHP-parsed parameters from server
                this.specificGifId = window.phpUrlParams.gifId || null;
                const seasonParam = window.phpUrlParams.season || null;

                if (this.specificGifId) {
                    // Set the search ID - we'll resolve it to a full ID after loading the data
                    this.isShowingSpecificGif = true;
                }

                if (seasonParam) {
                    const normalizedSeason = seasonParam.toLowerCase();
                    if (['christmas', 'xmas', 'holiday'].includes(normalizedSeason)) {
                        this.forcedSeason = 'christmas';
                    } else if (['halloween', 'spooky', 'scary'].includes(normalizedSeason)) {
                        this.forcedSeason = 'halloween';
                    } else if (['normal', 'default', 'regular'].includes(normalizedSeason)) {
                        this.forcedSeason = 'normal';
                    }
                }
            }

            // New method to find goat by either full ID or short ID
            findGoatByIdOrShortId(searchId) {
                if (!searchId || !this.goatsData || this.goatsData.length === 0) {
                    return null;
                }

                // First try to find by full ID
                let goat = this.goatsData.find(goat => goat.id === searchId);

                // If not found, try to find by short_id
                if (!goat) {
                    goat = this.goatsData.find(goat => goat.short_id === searchId);
                }

                return goat;
            }

            async init() {
                this.parseUrlParams();
                this.checkIfEmbedded();
                this.setupEventListeners();

                await this.loadGifIds();
                await this.prepareFirstGif();

                setTimeout(() => {
                    this.startSmoothTransition();
                }, 1500);
            }

            setupEventListeners() {
                document.addEventListener('click', (e) => this.handleInteraction(e));
                document.addEventListener('touchstart', (e) => this.handleInteraction(e));
                document.addEventListener('keydown', (e) => {
                    if (e.code === 'Space' || e.code === 'Enter') {
                        e.preventDefault();
                        this.handleInteraction(e);
                    }
                });

                this.elements.embedButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.openModal();
                });

                this.elements.shareButton.addEventListener('click', (e) => {
                    e.stopPropagation();
                    this.openShareModal();
                });

                this.elements.closeModal.addEventListener('click', () => {
                    this.closeModal();
                });

                this.elements.closeShareModal.addEventListener('click', () => {
                    this.closeShareModal();
                });

                this.elements.modalOverlay.addEventListener('click', (e) => {
                    if (e.target === this.elements.modalOverlay) {
                        this.closeModal();
                    }
                });

                this.elements.shareModalOverlay.addEventListener('click', (e) => {
                    if (e.target === this.elements.shareModalOverlay) {
                        this.closeShareModal();
                    }
                });

                this.elements.copyButton.addEventListener('click', () => {
                    this.copyEmbedCode();
                });

                this.elements.copyShareButton.addEventListener('click', () => {
                    this.copyShareUrl();
                });

                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape') {
                        this.closeModal();
                        this.closeShareModal();
                    }
                });

                this.elements.logo.addEventListener('click', (e) => {
                    if (!this.isEmbedded) {
                        e.preventDefault();
                        e.stopPropagation();
                    }
                });

                // Monitor visibility changes to pause/resume preloading
                document.addEventListener('visibilitychange', () => {
                    if (document.hidden) {
                        this.pauseBackgroundWork();
                    } else {
                        this.resumeBackgroundWork();
                    }
                });
            }

            pauseBackgroundWork() {
                // Reduce preloading when page is hidden
                this.preloadConfig.background = 1;
            }

            resumeBackgroundWork() {
                // Resume normal preloading when page is visible
                this.preloadConfig.background = this.performance.isSlowNetwork ? 2 : 3;
                this.scheduleIntelligentPreload();
            }

            async prepareFirstGif() {
                try {
                    let gifId;
                    let goatData = null;

                    if (this.specificGifId) {
                        // Try to find the goat by either full ID or short ID
                        goatData = this.findGoatByIdOrShortId(this.specificGifId);

                        if (goatData) {
                            gifId = goatData.id; // Always use the full ID for loading
                        } else {
                            console.warn(`No goat found for ID/short_id: ${this.specificGifId}, falling back to random`);
                            gifId = this.getRandomGifId();
                            goatData = this.getGoatData(gifId);
                        }
                    } else {
                        gifId = this.getRandomGifId();
                        goatData = this.getGoatData(gifId);
                    }

                    if (!gifId) throw new Error('No gif ID available');

                    this.currentGifId = gifId;
                    this.currentGoatData = goatData;

                    const startTime = Date.now();
                    await this.loadGifToLayerWithFallback(gifId, this.elements.layer1);

                    // Track performance
                    const loadTime = Date.now() - startTime;
                    this.performance.loadTimes.push(loadTime);

                    this.firstGifReady = true;
                } catch (error) {
                    console.error('Error preparing first gif:', error);
                    this.performance.failureRate += 0.1;
                    this.firstGifReady = false;
                }
            }

            async startSmoothTransition() {
                this.setupSeasonalMascot();

                if (this.firstGifReady) {
                    this.elements.layer1.classList.add('active');
                    this.startTimer();
                }

                this.hideSplashScreen();

                // Start intelligent preloading after UI is ready
                setTimeout(() => {
                    if (!this.firstGifReady) {
                        this.nextGif();
                    }
                    this.initializeIntelligentPreloading();
                }, 1000);
            }

            checkIfEmbedded() {
                if (window.self !== window.top) {
                    this.isEmbedded = true;
                    this.elements.embedButton.style.display = 'none';
                    this.elements.shareButton.style.display = 'none';
                    this.elements.seasonalMascot.style.display = 'none';
                    // Reduce preloading in embedded mode
                    this.preloadConfig.background = 2;
                } else {
                    this.isEmbedded = false;
                    this.elements.logo.classList.add('disabled');
                }
            }

            async loadGifIds() {
                try {
                    const response = await fetch('data/goats.json');
                    if (!response.ok) throw new Error('File not found');

                    const data = await response.json();

                    // Validate JSON structure
                    if (!Array.isArray(data)) {
                        throw new Error('Invalid JSON format - expected array');
                    }

                    // Store full goat data and extract IDs
                    this.goatsData = data.filter(goat =>
                        goat &&
                        typeof goat === 'object' &&
                        typeof goat.id === 'string' &&
                        goat.id.trim().length > 0
                    );

                    this.gifIds = this.goatsData.map(goat => goat.id.trim());

                    if (this.gifIds.length === 0) throw new Error('No valid goat IDs found in JSON');

                    console.log(`Loaded ${this.gifIds.length} goat gifs from JSON!`);

                } catch (error) {
                    console.log('JSON loading failed, using fallback gif IDs...', error.message);

                    // Fallback data in JSON format
                    this.goatsData = [
                        { "id": "i2KjyMsyb2L3G", "tags": [], "short_id": "i2K" },
                        { "id": "6qbvtoTgXyoy4", "tags": [], "short_id": "6qb" },
                        { "id": "3bjpYEM2wLeqQ", "tags": [], "short_id": "3bj" }
                    ];
                    this.gifIds = this.goatsData.map(goat => goat.id);
                }

                // Adjust preloading based on available GIFs
                if (this.gifIds.length < 10) {
                    this.preloadConfig.background = Math.min(2, Math.floor(this.gifIds.length / 2));
                }
            }

            // Helper method to get goat data by ID (for future use with tags)
            getGoatData(gifId) {
                return this.goatsData.find(goat => goat.id === gifId);
            }

            // Helper method to filter goats by tags (for future implementation)
            getGoatsByTags(tags) {
                if (!tags || tags.length === 0) return this.goatsData;

                return this.goatsData.filter(goat =>
                    goat.tags && goat.tags.some(tag => tags.includes(tag))
                );
            }

            hideSplashScreen() {
                this.elements.splashScreen.classList.add('hide');
            }

            setupSeasonalMascot() {
                let mascotImage;

                if (this.forcedSeason) {
                    switch (this.forcedSeason) {
                        case 'christmas':
                            mascotImage = 'images/santa-goat.png';
                            break;
                        case 'halloween':
                            mascotImage = 'images/halloween-goat.png';
                            break;
                        case 'normal':
                        default:
                            mascotImage = 'images/goat.png';
                            break;
                    }
                } else {
                    const currentMonth = new Date().getMonth() + 1;

                    if (currentMonth === 12) {
                        mascotImage = 'images/santa-goat.png';
                    } else if (currentMonth === 10) {
                        mascotImage = 'images/halloween-goat.png';
                    } else {
                        mascotImage = 'images/goat.png';
                    }
                }

                this.elements.seasonalMascot.src = mascotImage;

                setTimeout(() => {
                    this.elements.seasonalMascot.classList.add('show');
                }, 1500);
            }

            getActiveLayer() {
                return this.currentLayer === 1 ? this.elements.layer1 : this.elements.layer2;
            }

            getInactiveLayer() {
                return this.currentLayer === 1 ? this.elements.layer2 : this.elements.layer1;
            }

            getRandomGifId() {
                if (this.gifIds.length === 0) return null;

                let availableIds = this.gifIds;

                if (this.usedGifIds.size < this.gifIds.length - 1) {
                    availableIds = this.gifIds.filter(id => !this.usedGifIds.has(id));
                } else {
                    this.usedGifIds.clear();
                }

                const randomIndex = Math.floor(Math.random() * availableIds.length);
                const selectedId = availableIds[randomIndex];

                this.usedGifIds.add(selectedId);
                return selectedId;
            }

            // Enhanced preloading with priority system and fallback
            async preloadGif(gifId, priority = 'background') {
                if (this.preloadCache.has(gifId)) {
                    return this.preloadCache.get(gifId);
                }

                if (this.activePreloads.has(gifId)) {
                    return null; // Already being loaded
                }

                this.activePreloads.add(gifId);

                const loadPromise = new Promise((resolve, reject) => {
                    const img = new Image();
                    const gifUrl = `./goats/${gifId}.gif`;
                    const startTime = Date.now();

                    // Set priority-based loading
                    if (priority === 'immediate') {
                        img.loading = 'eager';
                    }

                    img.onload = () => {
                        const loadTime = Date.now() - startTime;
                        this.performance.loadTimes.push(loadTime);

                        this.preloadCache.set(gifId, {
                            url: gifUrl,
                            loadTime,
                            timestamp: Date.now()
                        });
                        this.activePreloads.delete(gifId);
                        resolve(gifUrl);
                    };

                    img.onerror = () => {
                        this.performance.failureRate += 0.05;
                        this.activePreloads.delete(gifId);

                        // Try fallback gif if original fails
                        if (gifId !== this.fallbackGifId) {
                            console.log(`Failed to preload ${gifId}, trying fallback...`);
                            const fallbackImg = new Image();
                            const fallbackUrl = `./goats/${this.fallbackGifId}.gif`;

                            fallbackImg.onload = () => {
                                this.preloadCache.set(this.fallbackGifId, {
                                    url: fallbackUrl,
                                    loadTime: Date.now() - startTime,
                                    timestamp: Date.now()
                                });
                                resolve(fallbackUrl);
                            };

                            fallbackImg.onerror = () => {
                                reject(new Error(`Failed to preload both ${gifId} and fallback ${this.fallbackGifId}`));
                            };

                            fallbackImg.src = fallbackUrl;
                        } else {
                            reject(new Error(`Failed to preload gif: ${gifId}`));
                        }
                    };

                    img.src = gifUrl;
                });

                return loadPromise;
            }

            // Intelligent preloading that adapts to performance
            initializeIntelligentPreloading() {
                // Start with immediate priority items
                this.scheduleIntelligentPreload();

                // Setup periodic maintenance
                setInterval(() => {
                    this.maintainPreloadCache();
                }, 5000); // Check every 5 seconds instead of 2
            }

            scheduleIntelligentPreload() {
                this.scheduleBackgroundWork(async () => {
                    await this.performIntelligentPreload();
                }, 'normal');
            }

            async performIntelligentPreload() {
                try {
                    const currentCacheSize = this.preloadCache.size;
                    const targetImmediate = this.preloadConfig.immediate;
                    const targetBackground = this.preloadConfig.background;

                    // Adjust targets based on performance
                    const adjustedImmediate = this.performance.isSlowNetwork ?
                        Math.max(1, Math.floor(targetImmediate / 2)) : targetImmediate;
                    const adjustedBackground = this.performance.isSlowNetwork ?
                        Math.max(1, Math.floor(targetBackground / 2)) : targetBackground;

                    if (currentCacheSize >= adjustedImmediate + adjustedBackground) {
                        return; // Cache is full enough
                    }

                    // Load immediate priority first
                    const immediateNeeded = Math.max(0, adjustedImmediate - currentCacheSize);
                    if (immediateNeeded > 0) {
                        const immediatePromises = [];
                        for (let i = 0; i < Math.min(immediateNeeded, this.preloadConfig.batchSize); i++) {
                            const gifId = this.getRandomGifId();
                            if (gifId && !this.preloadCache.has(gifId) && !this.activePreloads.has(gifId)) {
                                immediatePromises.push(
                                    this.preloadGif(gifId, 'immediate').catch(error => {
                                        console.log(`Immediate preload failed for ${gifId}:`, error.message);
                                    })
                                );
                            }
                        }
                        await Promise.allSettled(immediatePromises);
                    }

                    // Schedule background loading for later
                    const backgroundNeeded = Math.max(0, adjustedBackground - (currentCacheSize - immediateNeeded));
                    if (backgroundNeeded > 0) {
                        this.scheduleBackgroundWork(async () => {
                            const gifId = this.getRandomGifId();
                            if (gifId && !this.preloadCache.has(gifId) && !this.activePreloads.has(gifId)) {
                                try {
                                    await this.preloadGif(gifId, 'background');
                                } catch (error) {
                                    console.log(`Background preload failed for ${gifId}:`, error.message);
                                }
                            }
                        }, 'low');
                    }

                } catch (error) {
                    console.error('Error in intelligent preload:', error);
                }
            }

            maintainPreloadCache() {
                // Clean up old cache entries (older than 10 minutes)
                const maxAge = 10 * 60 * 1000;
                const now = Date.now();

                for (const [gifId, data] of this.preloadCache.entries()) {
                    if (now - data.timestamp > maxAge) {
                        this.preloadCache.delete(gifId);
                    }
                }

                // Trigger preload if cache is getting low
                if (this.preloadCache.size < this.preloadConfig.immediate) {
                    this.scheduleIntelligentPreload();
                }
            }

            getNextCachedGif() {
                const cachedEntries = Array.from(this.preloadCache.entries());
                if (cachedEntries.length === 0) return null;

                // Prefer recently cached items for better performance
                cachedEntries.sort((a, b) => b[1].timestamp - a[1].timestamp);

                const [selectedId, data] = cachedEntries[0];
                this.preloadCache.delete(selectedId);

                return {
                    id: selectedId,
                    url: data.url
                };
            }

            showLoadingState() {
                // Show loading indicator faster for better responsiveness
                this.loadingTimeout = setTimeout(() => {
                    this.elements.loadingOverlay.classList.add('show');
                }, 200); // Reduced from 500ms to 200ms
            }

            hideLoadingState() {
                if (this.loadingTimeout) {
                    clearTimeout(this.loadingTimeout);
                    this.loadingTimeout = null;
                }
                this.elements.loadingOverlay.classList.remove('show');
            }

            async loadGifToLayer(gifId, layer) {
                const img = layer.querySelector('img');
                const gifUrl = `./goats/${gifId}.gif`;

                return new Promise((resolve, reject) => {
                    const tempImg = new Image();

                    tempImg.onload = () => {
                        img.src = gifUrl;
                        resolve();
                    };

                    tempImg.onerror = () => {
                        reject(new Error(`Failed to load gif: ${gifId}`));
                    };

                    // Check cache first
                    const cachedData = this.preloadCache.get(gifId);
                    if (cachedData) {
                        tempImg.src = cachedData.url;
                    } else {
                        tempImg.src = gifUrl;
                    }
                });
            }

            // New method with fallback functionality
            async loadGifToLayerWithFallback(gifId, layer) {
                try {
                    await this.loadGifToLayer(gifId, layer);
                } catch (error) {
                    console.log(`Failed to load gif ${gifId}, trying fallback ${this.fallbackGifId}...`);

                    if (gifId !== this.fallbackGifId) {
                        try {
                            await this.loadGifToLayer(this.fallbackGifId, layer);
                            console.log(`Successfully loaded fallback gif ${this.fallbackGifId}`);
                        } catch (fallbackError) {
                            console.error(`Both original and fallback gifs failed to load:`, error, fallbackError);
                            throw new Error(`Failed to load both original gif (${gifId}) and fallback gif (${this.fallbackGifId})`);
                        }
                    } else {
                        throw error; // Already trying fallback, so re-throw
                    }
                }
            }

            startTimer() {
                // Ensure any existing timer is stopped first
                this.stopTimer();

                this.countdown = 20;
                this.updateProgressBar();

                this.timer = setInterval(() => {
                    this.countdown--;
                    this.updateProgressBar();

                    if (this.countdown <= 0) {
                        // Clear timer and trigger next gif
                        this.stopTimer();
                        // Use the same nextGif method for consistency
                        this.nextGif();
                    }
                }, 1000);
            }

            updateProgressBar() {
                const progress = ((20 - this.countdown) / 20) * 100;
                this.elements.progressBar.style.width = `${progress}%`;
            }

            stopTimer() {
                if (this.timer) {
                    clearInterval(this.timer);
                    this.timer = null;
                }
                this.elements.progressBar.style.width = '0%';
            }

            // Enhanced modal pause functionality
            pauseTimerForModal() {
                // Clear any existing timers and timeouts
                this.stopTimer();

                // Clear any pending transition timeouts
                if (this.transitionTimeout) {
                    clearTimeout(this.transitionTimeout);
                    this.transitionTimeout = null;
                }

                // Clear any pending safety timeouts or other scheduled work
                if (this.loadingTimeout) {
                    clearTimeout(this.loadingTimeout);
                    this.loadingTimeout = null;
                }

                // Stop any ongoing loading operations
                this.isLoading = false;
                this.hideLoadingState();

                // Set modal paused state
                this.isModalPaused = true;
                this.pausedCountdown = this.countdown || 20;

                // Reset progress bar to 0% to show fresh start
                this.elements.progressBar.style.width = '0%';
            }

            resumeTimerFromModal() {
                if (this.isModalPaused) {
                    // Clear modal paused state first
                    this.isModalPaused = false;
                    this.pausedCountdown = null;

                    // Ensure we're not in a loading state
                    this.isLoading = false;
                    this.hideLoadingState();

                    // Clean up any potential leftover animation states
                    this.cleanupLayerStates();

                    // Small delay to ensure modal close event doesn't interfere
                    setTimeout(() => {
                        // Always resume with a full 20-second timer for the same goat
                        this.countdown = 20;
                        this.startTimer();
                    }, 50); // Small delay to prevent any interference
                }
            }

            // New method to clean up any leftover animation states
            cleanupLayerStates() {
                // Remove any transition classes that might be stuck
                this.elements.layer1.classList.remove('fade-out');
                this.elements.layer2.classList.remove('fade-out');

                // Ensure proper active state based on currentLayer
                if (this.currentLayer === 1) {
                    this.elements.layer1.classList.add('active');
                    this.elements.layer2.classList.remove('active');
                } else {
                    this.elements.layer2.classList.add('active');
                    this.elements.layer1.classList.remove('active');
                }

            }

            async nextGif() {
                // Comprehensive checks to prevent unwanted gif changes
                if (this.isLoading || this.isModalPaused) {
                    return;
                }

                if (this.isShowingSpecificGif) {
                    this.isShowingSpecificGif = false;
                    this.specificGifId = null;
                }

                this.isLoading = true;
                this.performance.lastInteraction = Date.now();

                try {
                    let gifId = null;
                    let gifUrl = null;
                    let isFromCache = false;
                    let goatData = null;

                    // Try to get from cache first
                    const cachedGif = this.getNextCachedGif();

                    if (cachedGif) {
                        gifId = cachedGif.id;
                        gifUrl = cachedGif.url;
                        isFromCache = true;
                        this.currentGifId = gifId;
                        goatData = this.getGoatData(gifId);
                        this.currentGoatData = goatData;
                    } else {
                        // Fallback to loading fresh
                        gifId = this.getRandomGifId();
                        if (!gifId) throw new Error('No gif ID available');
                        this.currentGifId = gifId;
                        goatData = this.getGoatData(gifId);
                        this.currentGoatData = goatData;
                        isFromCache = false;
                    }

                    const activeLayer = this.getActiveLayer();
                    const inactiveLayer = this.getInactiveLayer();

                    // Double-check we're not paused before proceeding with transition
                    if (this.isModalPaused) {
                        this.isLoading = false;
                        return;
                    }

                    // For cached gifs, transition immediately
                    if (isFromCache) {
                        // Stop timer and start transition right away
                        this.stopTimer();

                        // Load cached gif (should be instant)
                        await this.loadGifToLayerWithFallback(gifId, inactiveLayer);

                        // Final check before transition
                        if (this.isModalPaused) {
                            this.isLoading = false;
                            return;
                        }

                        // Immediate smooth transition
                        activeLayer.classList.add('fade-out');
                        inactiveLayer.classList.add('active');

                        setTimeout(() => {
                            if (!this.isModalPaused) {
                                activeLayer.classList.remove('active', 'fade-out');
                                this.currentLayer = this.currentLayer === 1 ? 2 : 1;
                                this.startTimer(); // Start timer after transition
                            }
                        }, 600);

                    } else {
                        // For non-cached gifs, show loading but keep progress running briefly
                        this.showLoadingState();

                        // Give a short moment for user to see the loading state
                        await new Promise(resolve => setTimeout(resolve, 100));

                        // Check again before proceeding
                        if (this.isModalPaused) {
                            this.hideLoadingState();
                            this.isLoading = false;
                            return;
                        }

                        // Now stop timer and load
                        this.stopTimer();
                        await this.loadGifToLayerWithFallback(gifId, inactiveLayer);
                        this.hideLoadingState();

                        // Final check before transition
                        if (this.isModalPaused) {
                            this.isLoading = false;
                            return;
                        }

                        // Smooth transition
                        activeLayer.classList.add('fade-out');
                        inactiveLayer.classList.add('active');

                        setTimeout(() => {
                            if (!this.isModalPaused) {
                                activeLayer.classList.remove('active', 'fade-out');
                                this.currentLayer = this.currentLayer === 1 ? 2 : 1;
                                this.startTimer(); // Start timer after transition
                            }
                        }, 600);
                    }

                    // Trigger preload maintenance after transition
                    this.scheduleBackgroundWork(() => {
                        this.scheduleIntelligentPreload();
                    }, 'low');

                } catch (error) {
                    console.error('Error loading gif:', error);
                    this.hideLoadingState();
                    this.showError('Failed to load gif. Trying another...');

                    // Only restart timer if not paused
                    if (!this.isModalPaused) {
                        this.startTimer();

                        // Schedule retry without keeping isLoading stuck
                        setTimeout(() => {
                            if (!this.isModalPaused) {
                                this.nextGif();
                            }
                        }, 1500);
                    }
                } finally {
                    // Always reset loading state
                    this.isLoading = false;
                }
            }

            handleInteraction(event) {
                // Prevent interaction if modal is open - most important check
                if (this.isModalPaused) {
                    return;
                }

                // Prevent stuck loading states
                if (this.isLoading) {
                    return;
                }

                // Check if this interaction is on a modal-related element
                const target = event.target;
                if (target && (
                    target.closest('.modal-overlay') ||
                    target.closest('.share-button') ||
                    target.closest('.embed-button')
                )) {
                    return;
                }

                this.createRipple(event);
                this.performance.lastInteraction = Date.now();

                // Add a safeguard timeout to reset loading state if something goes wrong
                const safetyTimeout = setTimeout(() => {
                    if (this.isLoading && !this.isModalPaused) {
                        console.warn('Loading state stuck - forcing reset');
                        this.isLoading = false;
                        this.hideLoadingState();
                        this.startTimer(); // Ensure timer is running
                    }
                }, 10000); // 10 second safeguard

                this.nextGif();

                // Aggressively preload after user interaction (they'll likely click again)
                setTimeout(() => {
                    if (!this.isModalPaused) {
                        this.scheduleBackgroundWork(() => {
                            this.performIntelligentPreload();
                        }, 'normal');
                    }
                }, 100);

                // Clear the safety timeout after a reasonable time
                setTimeout(() => {
                    clearTimeout(safetyTimeout);
                }, 11000);
            }

            createRipple(event) {
                const container = this.elements.gifContainer;
                const ripple = document.createElement('div');

                const rect = container.getBoundingClientRect();
                const x = (event.clientX || event.touches?.[0]?.clientX || rect.width / 2) - rect.left;
                const y = (event.clientY || event.touches?.[0]?.clientY || rect.height / 2) - rect.top;

                ripple.style.cssText = `
                    position: absolute;
                    border-radius: 50%;
                    background: rgba(255, 255, 255, 0.3);
                    transform: scale(0);
                    animation: ripple 0.6s ease-out;
                    pointer-events: none;
                    z-index: 100;
                    left: ${x}px;
                    top: ${y}px;
                    width: 0;
                    height: 0;
                `;

                container.appendChild(ripple);

                const style = document.createElement('style');
                style.textContent = `
                    @keyframes ripple {
                        to {
                            transform: scale(4);
                            opacity: 0;
                            width: 100px;
                            height: 100px;
                            margin-left: -50px;
                            margin-top: -50px;
                        }
                    }
                `;
                document.head.appendChild(style);

                setTimeout(() => {
                    container.removeChild(ripple);
                    document.head.removeChild(style);
                }, 600);
            }

            showError(message) {
                const errorDiv = document.createElement('div');
                errorDiv.className = 'error-message';
                errorDiv.textContent = message;

                document.body.appendChild(errorDiv);

                setTimeout(() => {
                    if (errorDiv.parentNode) {
                        errorDiv.style.animation = 'slideIn 0.3s ease reverse';
                        setTimeout(() => {
                            if (errorDiv.parentNode) {
                                errorDiv.parentNode.removeChild(errorDiv);
                            }
                        }, 300);
                    }
                }, 2000);
            }

            openModal() {
                this.elements.modalOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';
            }

            closeModal() {
                this.elements.modalOverlay.classList.remove('show');
                document.body.style.overflow = '';
                this.elements.copyButton.textContent = 'Copy';
                this.elements.copyButton.classList.remove('copied');
            }

            openShareModal() {
                // Pause the timer and reset progress to start
                this.pauseTimerForModal();

                // Clear any pending gif loading to prevent interruption
                if (this.isLoading) {
                    this.isLoading = false;
                    this.hideLoadingState();
                }

                // Update the share URL with current gif short_id or fallback to full ID
                let shareId = this.currentGifId;
                if (this.currentGoatData && this.currentGoatData.short_id) {
                    shareId = this.currentGoatData.short_id;
                }

                const shareUrl = shareId ? `rdgt.co/${shareId}` : 'rdgt.co/loading...';
                this.elements.shareUrl.textContent = shareUrl;

                // Show the modal
                this.elements.shareModalOverlay.classList.add('show');
                document.body.style.overflow = 'hidden';

            }

            closeShareModal() {
                // Hide the modal first
                this.elements.shareModalOverlay.classList.remove('show');
                document.body.style.overflow = '';
                this.elements.copyShareButton.textContent = 'Copy';
                this.elements.copyShareButton.classList.remove('copied');

                // Resume the timer with full duration for current goat
                // Use a longer delay to ensure modal close events don't interfere
                setTimeout(() => {
                    this.resumeTimerFromModal();
                }, 100); // Delay to prevent interference
            }

            async copyEmbedCode() {
                const code = this.elements.embedCode.textContent;

                try {
                    await navigator.clipboard.writeText(code);
                    this.elements.copyButton.textContent = '✓ Copied!';
                    this.elements.copyButton.classList.add('copied');

                    setTimeout(() => {
                        this.elements.copyButton.textContent = 'Copy';
                        this.elements.copyButton.classList.remove('copied');
                    }, 2000);
                } catch (err) {
                    const textArea = document.createElement('textarea');
                    textArea.value = code;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();

                    try {
                        document.execCommand('copy');
                        this.elements.copyButton.textContent = '✓ Copied!';
                        this.elements.copyButton.classList.add('copied');

                        setTimeout(() => {
                            this.elements.copyButton.textContent = 'Copy';
                            this.elements.copyButton.classList.remove('copied');
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                        this.elements.copyButton.textContent = 'Failed';
                        setTimeout(() => {
                            this.elements.copyButton.textContent = 'Copy';
                        }, 2000);
                    }

                    document.body.removeChild(textArea);
                }
            }

            async copyShareUrl() {
                const url = this.elements.shareUrl.textContent;

                try {
                    await navigator.clipboard.writeText(url);
                    this.elements.copyShareButton.textContent = '✓ Copied!';
                    this.elements.copyShareButton.classList.add('copied');

                    setTimeout(() => {
                        this.elements.copyShareButton.textContent = 'Copy';
                        this.elements.copyShareButton.classList.remove('copied');
                    }, 2000);
                } catch (err) {
                    const textArea = document.createElement('textarea');
                    textArea.value = url;
                    textArea.style.position = 'fixed';
                    textArea.style.left = '-999999px';
                    textArea.style.top = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus();
                    textArea.select();

                    try {
                        document.execCommand('copy');
                        this.elements.copyShareButton.textContent = '✓ Copied!';
                        this.elements.copyShareButton.classList.add('copied');

                        setTimeout(() => {
                            this.elements.copyShareButton.textContent = 'Copy';
                            this.elements.copyShareButton.classList.remove('copied');
                        }, 2000);
                    } catch (err) {
                        console.error('Failed to copy text: ', err);
                        this.elements.copyShareButton.textContent = 'Failed';
                        setTimeout(() => {
                            this.elements.copyShareButton.textContent = 'Copy';
                        }, 2000);
                    }

                    document.body.removeChild(textArea);
                }
            }
        }

        window.addEventListener('DOMContentLoaded', () => {
            new OptimizedGoatGifApp();
        });
    </script>

    <script type="text/javascript">
        var sc_project = 13146731;
        var sc_invisible = 1;
        var sc_security = "e7168d71"; 
    </script>
    <script type="text/javascript" src="https://www.statcounter.com/counter/counter.js" async></script>
    <noscript>
        <div class="statcounter"><a title="Web Analytics Made Easy - Statcounter" href="https://statcounter.com/"
                target="_blank"><img class="statcounter" src="https://c.statcounter.com/13146731/0/e7168d71/1/"
                    alt="Web Analytics Made Easy - Statcounter" referrerPolicy="no-referrer-when-downgrade"></a></div>
    </noscript>
</body>

</html>