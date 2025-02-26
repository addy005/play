<?php
// Initialize favorites data
$favData = file_exists('f.json') ? json_decode(file_get_contents('f.json'), true) : [];

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!isset($favData[$_POST['video']])) {
        $favData[$_POST['video']] = [
            'favorite' => false,
            'timemarks' => []
        ];
    }
    
    switch ($_POST['action']) {
        case 'toggleFav':
            $favData[$_POST['video']]['favorite'] = !$favData[$_POST['video']]['favorite'];
            break;
        case 'addTimemark':
            $favData[$_POST['video']]['timemarks'][] = floatval($_POST['time']);
            break;
        case 'resetTimemarks':
            $favData[$_POST['video']]['timemarks'] = [];
            break;
    }
    
    file_put_contents('f.json', json_encode($favData));
    exit(json_encode(['success' => true]));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';
    $video = $_POST['video'] ?? '';
    
    if ($password === 'op' && file_exists($video)) {
        if (unlink($video)) {
            echo json_encode(['success' => true]);
            exit;
        }
    }
    
    echo json_encode(['success' => false]);
    exit;
}


// Video handling
$videos = glob("*.{mp4,webm,ogg,mkv}", GLOB_BRACE);
if (empty($videos)) {
    die("No videos found in directory");
}
// Get video stats and info
$videoStats = [];
foreach ($videos as $video) {
    $videoStats[$video] = [
        'size' => formatFileSize(filesize($video)),
        'stats' => isset($favData[$video]) ? $favData[$video] : [
            'favorite' => false,
            'timemarks' => []
        ]
    ];
}

function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}

$current_video = isset($_GET['video']) ? $_GET['video'] : $videos[array_rand($videos)];
$backgroundVideo = file_exists('back.jpg') ? 'back.jpg' : $current_video;
$video_title = pathinfo($current_video, PATHINFO_FILENAME);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Video Player</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #00a8ff;
            --dark-bg: #1a1a1a;
            --max-width: 95%;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--dark-bg);
            color: #fff;
            font-family: 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
            flex-direction: column;
            overflow-x: hidden;
        }

        .video-wrapper {
            position: relative;
            width: var(--max-width);
            max-width: 1920px;
            margin: 0 auto;
            margin-bottom: 20px;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            background: #000;
            aspect-ratio: auto;
        }

        .video-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            filter: blur(20px) brightness(0.2);
            object-fit: cover;
        }

        #videoPlayer {
            width: 100%;
            display: block;
            cursor: pointer;
        }

        .video-controls {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            background: linear-gradient(transparent, rgba(0,0,0,0.9));
            padding: 20px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .video-zoom-container {
            overflow-x: auto;
            white-space: nowrap;
            -webkit-overflow-scrolling: touch;
            display: none;
        }
        
        .video-zoomed {
            height: 100vh;
            width: auto;
        }

        .video-wrapper:hover .video-controls {
            opacity: 1;
        }

        .progress-container {
            width: 100%;
            height: 4px;
            background: rgba(255,255,255,0.2);
            cursor: pointer;
            border-radius: 2px;
            margin-bottom: 10px;
            position: relative;
            transition: height 0.2s ease;
        }

        .progress-container:hover {
            height: 8px;
        }

        .progress-bar {
            height: 100%;
            background: var(--primary-color);
            border-radius: 2px;
            width: 0;
            position: relative;
            transition: width 0.1s linear;
        }

        .progress-hover {
            position: absolute;
            height: 100%;
            background: rgba(255,255,255,0.2);
            pointer-events: none;
        }

        .time-tooltip {
            position: absolute;
            background: rgba(0,0,0,0.8);
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            bottom: 100%;
            transform: translateX(-50%);
            display: none;
        }

        .controls-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .control-group {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .control-button {
            background: transparent;
            border: none;
            color: #fff;
            cursor: pointer;
            padding: 5px;
            font-size: 16px;
            transition: all 0.2s ease;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }

        .control-button:hover {
            color: var(--primary-color);
            background: rgba(255,255,255,0.1);
            transform: scale(1.1);
        }

        .volume-container {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .volume-slider {
            width: 0;
            height: 4px;
            -webkit-appearance: none;
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
            transition: width 0.2s ease;
            overflow: hidden;
        }

        .volume-container:hover .volume-slider {
            width: 80px;
        }

        .volume-slider::-webkit-slider-thumb {
            -webkit-appearance: none;
            width: 12px;
            height: 12px;
            background: var(--primary-color);
            border-radius: 50%;
            cursor: pointer;
            box-shadow: -80px 0 0 80px var(--primary-color);
        }

        .time-display {
            font-size: 14px;
            color: #fff;
            margin: 0 10px;
            min-width: 100px;
            text-align: center;
        }

        .speed-menu {
            position: absolute;
            bottom: 100%;
            right: 0;
            background: rgba(0,0,0,0.9);
            border-radius: 8px;
            padding: 5px;
            display: none;
            flex-direction: column;
            gap: 2px;
            margin-bottom: 5px;
        }

        .loading-spinner {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.3);
            border-radius: 50%;
            border-top-color: var(--primary-color);
            animation: spin 1s ease-in-out infinite;
            display: none;
        }
        
        @keyframes spin {
            to { transform: translate(-50%, -50%) rotate(360deg); }
        }

        .speed-menu button {
            display: block;
            width: 100%;
            padding: 5px 15px;
            text-align: left;
            background: transparent;
            border: none;
            color: #fff;
            cursor: pointer;
            white-space: nowrap;
            transition: background 0.2s ease;
        }

        .speed-menu button:hover {
            background: rgba(255,255,255,0.1);
        }

        .speed-menu button.active {
            color: var(--primary-color);
        }

        .video-title {
            position: absolute;
            top: 5px;
            left: 20px;
            color: #fff;
            font-size: 18px;
            font-weight: bold;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.5);
            opacity: 0;
            transition: opacity 0.3s;
            z-index: 2;
        }

        .video-wrapper:hover .video-title {
            opacity: 1;
        }

        .double-tap-overlay {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 25%;
            height: 100%;
            display: none;
            justify-content: center;
            align-items: center;
            background: rgba(255,255,255,0.1);
            z-index: 3;
        }

        .double-tap-overlay i {
            font-size: 48px;
            color: var(--primary-color);
        }

        .double-tap-left { left: 0; }
        .double-tap-right { right: 0; }

        .tap-indicator {
            position: absolute;
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            justify-content: center;
            align-items: center;
            animation: tapRipple 0.5s ease-out;
            pointer-events: none;
        }

        @keyframes tapRipple {
            0% {
                transform: scale(0);
                opacity: 1;
            }
            100% {
                transform: scale(2);
                opacity: 0;
            }
        }

        ///fieldset
        .video-list-container {
            width: var(--max-width);
            max-width: 1920px;
            margin: 20px auto 0;
            padding: 15px;
            background: rgba(26, 26, 26, 0.8);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
        }
        
        .video-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            margin: 5px 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .video-item:hover {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(5px);
        }
        
        .video-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            margin-right: 10px;
        }
        
        .video-name {
            font-size: 14px;
            color: #fff;
            margin-right: 15px;
        }
        
        .video-size {
            font-size: 12px;
            color: #888;
            white-space: nowrap;
        }
        
        .favorite-icon {
            color: var(--primary-color);
            font-size: 14px;
        }
        
        @media (max-width: 768px) {
            .video-list-container {
                padding: 10px;
            }
            
            .video-item {
                padding: 10px;
            }
            
            .video-name {
                font-size: 13px;
            }
            
            .video-size {
                font-size: 11px;
            }
        }

        .compact-video-list {
            width: 95%;
            height: 50vh;
            border: 2px solid rgba(255, 255, 255, 0.8);
            border-radius: 12px;
            margin: 20px auto;
            padding: 15px;
            background: rgba(26, 26, 26, 0.8);
            overflow-y: auto;
            position: relative;
        }
        
        .search-sort-container {
            width: 90%;
            margin: 0 auto 10px;
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .search-input {
            flex: 1;
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
        }
        
        .sort-select {
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            background: rgba(255, 255, 255, 0.1);
            color: white;
            font-size: 14px;
            cursor: pointer;
        }
        
        .search-input:focus,
        .sort-select:focus {
            outline: none;
            border-color: var(--primary-color);
        }
        
        .wavy-border {
            background-size: 10px 10px;
        }
        .delete-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: rgba(0, 0, 0, 0.9);
            padding: 20px;
            border-radius: 10px;
            z-index: 1000;
            display: none;
        }
        
        .delete-modal input {
            width: 100%;
            padding: 8px;
            margin: 10px 0;
            background: #333;
            border: 1px solid #555;
            color: white;
            border-radius: 4px;
        }
        
        .delete-modal button {
            padding: 8px 15px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .delete-modal button.confirm {
            background: #dc3545;
            color: white;
        }
        
        .delete-modal button.cancel {
            background: #6c757d;
            color: white;
        }
        
        .modal-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            z-index: 999;
        }

        /* Fix fullscreen video positioning */
        .video-wrapper:-webkit-full-screen,
        .video-wrapper:fullscreen {
        display: flex;
        flex-direction: column;
        justify-content: center;
        width: 100% !important;
        height: 100% !important;
        }

        .video-wrapper:-webkit-full-screen #videoPlayer,
        .video-wrapper:fullscreen #videoPlayer {
        width: 100%;
        height: auto;
        max-height: 100vh;
        margin: auto;
        }

        .video-wrapper:-webkit-full-screen .video-controls,
        .video-wrapper:fullscreen .video-controls {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        z-index: 2147483647;
        }

    </style>
</head>
<body>
    <video class="video-background" src="<?php echo htmlspecialchars($backgroundVideo); ?>" autoplay muted loop></video>
    
    <div class="video-wrapper">
    <div class="loading-spinner"></div>
        <div class="video-title"><?php echo htmlspecialchars($video_title); ?>
    <div class="video-info">
    <span class="file-size"><?php echo $videoStats[$current_video]['size']; ?></span>
    <i class="fas fa-heart" style="color: <?php echo $videoStats[$current_video]['stats']['favorite'] ? 'red' : 'white'; ?>"></i>
</div>
    </div>
        <div class="double-tap-overlay double-tap-left">
            <i class="fas fa-backward"></i>
        </div>
        <div class="double-tap-overlay double-tap-right">
            <i class="fas fa-forward"></i>
        </div>
        <video id="videoPlayer" preload="metadata">
            <source src="<?php echo htmlspecialchars($current_video); ?>" type="video/mp4">
        </video>
        <div class="loading-spinner"></div>
        
        <div class="video-controls">
            <div class="progress-container" id="progressContainer">
                <div class="progress-hover"></div>
                <div class="progress-bar" id="progressBar"></div>
                <div class="time-tooltip"></div>
            </div>
            
            <div class="controls-row">
                <div class="control-group">
                    <button class="control-button" onclick="previousVideo()" title="Previous Video">
                        <i class="fas fa-step-backward"></i>
                    </button>
                    <button class="control-button" onclick="skipBackward()" title="Backward 10s">
                        <i class="fas fa-backward"></i>
                    </button>
                    <button class="control-button" id="playPauseBtn" title="Play/Pause (Space)">
                        <i class="fas fa-play"></i>
                    </button>
                    <button class="control-button" onclick="skipForward()" title="Forward 10s">
                        <i class="fas fa-forward"></i>
                    </button>
                    <button class="control-button" onclick="nextVideo()" title="Next Video">
                        <i class="fas fa-step-forward"></i>
                    </button>
                    
                    <div class="volume-container">
                        <button class="control-button" id="muteBtn">
                            <i class="fas fa-volume-up"></i>
                        </button>
                        <input type="range" class="volume-slider" id="volumeSlider" min="0" max="1" step="0.1" value="1">
                    </div>
                    
                    <span class="time-display" id="timeDisplay">0:00 / 0:00</span>
                </div>

                <button class="control-button" id="zoomBtn" title="Zoom Video">
                    <i class="fas fa-search-plus"></i>
                </button>
                <button class="control-button" id="favBtn" title="Favorite Video">
                    <i class="fas fa-heart"></i>
                </button>
                <button class="control-button" id="timemarkBtn" title="Add Timemark">
                    <i class="fas fa-bookmark"></i>
                </button>
                <button class="control-button" id="deleteBtn" title="Delete Video">
                    <i class="fas fa-trash"></i>
                </button>

                <div class="control-group">
                    <div style="position: relative;">
                        <button class="control-button" id="speedBtn" title="Playback Speed">
                            <i class="fas fa-tachometer-alt"></i>
                        </button>
                        <div class="speed-menu" id="speedMenu">
                            <button data-speed="0.25">0.25x</button>
                            <button data-speed="0.5">0.5x</button>
                            <button data-speed="0.75">0.75x</button>
                            <button data-speed="1" class="active">Normal</button>
                            <button data-speed="1.25">1.25x</button>
                            <button data-speed="1.5">1.5x</button>
                            <button data-speed="2">2x</button>
                        </div>
                    </div>
                    <button class="control-button" onclick="randomVideo()" title="Random Video">
                        <i class="fas fa-random"></i>
                    </button>
                    <button class="control-button" onclick="togglePiP()" title="Picture in Picture (P)">
                        <i class="fas fa-external-link-alt"></i>
                    </button>
                    <button class="control-button" onclick="shareVideo()" title="Share">
                        <i class="fas fa-share-alt"></i>
                    </button>
                    <button class="control-button" onclick="downloadVideo()" title="Download">
                        <i class="fas fa-download"></i>
                    </button>
                    <button class="control-button" onclick="toggleFullscreen()" title="Fullscreen (F)">
                        <i class="fas fa-expand"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>
    <div class="search-sort-container">
    <input type="text" class="search-input" id="videoSearch" placeholder="Search videos...">
    <select class="sort-select" id="videoSort">
        <option value="name">Sort by Name</option>
        <option value="size">Sort by Size</option>
        <option value="time">Sort by Time</option>
        <option value="favorite">Favorites First</option>
    </select>
</div>
<div class="compact-video-list wavy-border">
    <div id="videoListContent">
        <?php foreach ($videos as $video): ?>
            <div class="video-item" data-name="<?php echo htmlspecialchars(pathinfo($video, PATHINFO_FILENAME)); ?>" 
                 data-size="<?php echo $videoStats[$video]['size']; ?>"
                 data-time="<?php echo filemtime($video); ?>"
                 onclick="updateVideo(<?php echo array_search($video, $videos); ?>)">
                <div class="video-info">
                    <span class="video-name"><?php echo htmlspecialchars(pathinfo($video, PATHINFO_FILENAME)); ?></span>
                    <span class="video-size"><?php echo $videoStats[$video]['size']; ?></span>
                </div>
                <?php if ($videoStats[$video]['stats']['favorite']): ?>
                    <i class="fas fa-heart favorite-icon"></i>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<div class="modal-overlay"></div>
<div class="delete-modal">
    <h3>Enter Password to Delete</h3>
    <input type="password" id="deletePassword" placeholder="Enter password">
    <div>
        <button class="confirm" onclick="confirmDelete()">Delete</button>
        <button class="cancel" onclick="closeDeleteModal()">Cancel</button>
    </div>
</div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const player = document.getElementById('videoPlayer');
            const wrapper = document.querySelector('.video-wrapper');
            const videos = <?php echo json_encode($videos); ?>;
            let currentIndex = videos.indexOf('<?php echo $current_video; ?>');
            let lastTap = 0;
            let tapTimeout;
            
            // Initialize player
            player.controls = false;
            
            // Single click and double tap handling
            player.addEventListener('click', function(e) {
                const currentTime = new Date().getTime();
                const tapLength = currentTime - lastTap;
                const rect = player.getBoundingClientRect();
                const x = e.clientX - rect.left;
                const width = rect.width;
                
                if (tapLength < 500 && tapLength > 0) {
                    // Double tap
                    clearTimeout(tapTimeout);
                    if (x < width * 0.3) {
                        showTapIndicator(e.clientX, e.clientY, 'left');
                        skipBackward();
                    } else if (x > width * 0.7) {
                        showTapIndicator(e.clientX, e.clientY, 'right');
                        skipForward();
                    }
                } else {
                    // Single tap
                    tapTimeout = setTimeout(() => {
                        togglePlay();
                    }, 200);
                }
                lastTap = currentTime;
            });

            function showTapIndicator(x, y) {
                const indicator = document.createElement('div');
                indicator.className = 'tap-indicator';
                indicator.style.left = `${x - 30}px`;
                indicator.style.top = `${y - 30}px`;
                document.body.appendChild(indicator);
                
                setTimeout(() => {
                    indicator.remove();
                }, 500);
            }

            // Speed menu handling
            const speedBtn = document.getElementById('speedBtn');
            const speedMenu = document.getElementById('speedMenu');
            
            speedBtn.addEventListener('mouseenter', () => {
                speedMenu.style.display = 'flex';
            });
            
            document.querySelector('.control-group').addEventListener('mouseleave', () => {
                speedMenu.style.display = 'none';
            });
            
            speedMenu.addEventListener('click', (e) => {
                if (e.target.hasAttribute('data-speed')) {
                    const speed = parseFloat(e.target.getAttribute('data-speed'));
                    player.playbackRate = speed;
                    speedMenu.querySelectorAll('button').forEach(btn => {
                        btn.classList.remove('active');
                    });
                    e.target.classList.add('active');
                }
            });

            // Progress bar handling
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressHover = document.querySelector('.progress-hover');
            const timeTooltip = document.querySelector('.time-tooltip');
            
            progressContainer.addEventListener('mousemove', (e) => {
                const rect = progressContainer.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                progressHover.style.width = `${pos * 100}%`;
                timeTooltip.style.display = 'block';
                timeTooltip.style.left = `${e.clientX - rect.left}px`;
                timeTooltip.textContent = formatTime(pos * player.duration);
            });

            progressContainer.addEventListener('mouseleave', () => {
                progressHover.style.width = '0';
                timeTooltip.style.display = 'none';
            });

            progressContainer.addEventListener('click', (e) => {
                const rect = progressContainer.getBoundingClientRect();
                const pos = (e.clientX - rect.left) / rect.width;
                player.currentTime = pos * player.duration;
            });

            // Update progress
            player.addEventListener('timeupdate', () => {
                const progress = (player.currentTime / player.duration) * 100;
                progressBar.style.width = `${progress}%`;
                updateTimeDisplay();
            });


            // Loading spinner
            function showLoader() {
                document.querySelector('.loading-spinner').style.display = 'block';
            }

            function hideLoader() {
                document.querySelector('.loading-spinner').style.display = 'none';
            }

            // Favorite handling
            function toggleVideoFavorite() {
                fetch('a.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=toggleFav&video=${encodeURIComponent(videos[currentIndex])}`
                });
            }

            // Timemark handling
            function addTimemark() {
                fetch('a.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=addTimemark&video=${encodeURIComponent(currentVideo)}&time=${player.currentTime}`
                });
            }

            // Mobile zoom handling
            let isZoomed = false;
            function toggleZoom() {
                isZoomed = !isZoomed;
                const container = document.querySelector('.video-zoom-container');
                container.style.display = isZoomed ? 'block' : 'none';
                player.classList.toggle('video-zoomed');
            }

            // Event listeners
            player.addEventListener('waiting', showLoader);
            player.addEventListener('playing', hideLoader);
            player.addEventListener('canplay', hideLoader);

            document.getElementById('zoomBtn').addEventListener('click', toggleZoom);
            document.getElementById('favBtn').addEventListener('click', toggleVideoFavorite);
            document.getElementById('timemarkBtn').addEventListener('click', addTimemark);

            function toggleVideoFavorite() {
                const favBtn = document.getElementById('favBtn');
                const currentScript = window.location.pathname.split('/').pop();
                
                fetch(currentScript, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded'
                    },
                    body: `action=toggleFav&video=${encodeURIComponent(videos[currentIndex])}`
                })
                .then(response => response.json())
                .then(data => {
                    if(data.success) {
                        favBtn.querySelector('i').style.color = 
                            favBtn.querySelector('i').style.color === 'red' ? 'white' : 'red';
                            
                        // Update video list item favorite status
                        const videoItem = document.querySelector(
                            `.video-item[data-name="${videos[currentIndex].split('.')[0]}"]`
                        );
                        if (videoItem) {
                            const favIcon = videoItem.querySelector('.favorite-icon');
                            if (favBtn.querySelector('i').style.color === 'red') {
                                if (!favIcon) {
                                    const newFavIcon = document.createElement('i');
                                    newFavIcon.className = 'fas fa-heart favorite-icon';
                                    videoItem.appendChild(newFavIcon);
                                }
                            } else if (favIcon) {
                                favIcon.remove();
                            }
                        }
                    }
                });
            }
           

            // Playback functions
            window.togglePlay = function() {
                if (player.paused) {
                    player.play();
                    document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-pause"></i>';
                } else {
                    player.pause();
                    document.getElementById('playPauseBtn').innerHTML = '<i class="fas fa-play"></i>';
                }
            }

            window.skipBackward = function() {
                player.currentTime = Math.max(player.currentTime - 10, 0);
            }

            window.skipForward = function() {
                player.currentTime = Math.min(player.currentTime + 10, player.duration);
            }

            // Volume control
            const volumeSlider = document.getElementById('volumeSlider');
            const muteBtn = document.getElementById('muteBtn');
            
            volumeSlider.addEventListener('input', () => {
                player.volume = volumeSlider.value;
                player.muted = false;
                updateVolumeIcon();
            });

            muteBtn.addEventListener('click', () => {
                player.muted = !player.muted;
                updateVolumeIcon();
            });

            function updateVolumeIcon() {
                const icon = muteBtn.querySelector('i');
                if (player.muted || player.volume === 0) {
                    icon.className = 'fas fa-volume-mute';
                } else if (player.volume < 0.5) {
                    icon.className = 'fas fa-volume-down';
                } else {
                    icon.className = 'fas fa-volume-up';
                }
            }

            // Navigation functions
            window.updateVideo = function(newIndex) {
                currentIndex = newIndex;
                const newVideo = videos[currentIndex];
                player.src = newVideo;
                document.querySelector('.video-title').textContent = newVideo.split('/').pop().split('.')[0];
                player.play();
            }

            window.previousVideo = function() {
                updateVideo((currentIndex - 1 + videos.length) % videos.length);
            }

            window.nextVideo = function() {
                updateVideo((currentIndex + 1) % videos.length);
            }

            window.randomVideo = function() {
                let newIndex;
                do {
                    newIndex = Math.floor(Math.random() * videos.length);
                } while (newIndex === currentIndex && videos.length > 1);
                updateVideo(newIndex);
            }
            // Get modal elements
            const deleteModal = document.querySelector('.delete-modal');
            const modalOverlay = document.querySelector('.modal-overlay');
            const deleteBtn = document.getElementById('deleteBtn');
            const cancelBtn = document.querySelector('.delete-modal .cancel');

            // Show modal when clicking delete button
            deleteBtn.addEventListener('click', () => {
            deleteModal.style.display = 'block';
            modalOverlay.style.display = 'block';
            document.getElementById('deletePassword').focus();
            });

            // Close modal when clicking cancel or overlay
            cancelBtn.addEventListener('click', closeDeleteModal);
            modalOverlay.addEventListener('click', closeDeleteModal);

            // Close modal function
            function closeDeleteModal() {
            deleteModal.style.display = 'none';
            modalOverlay.style.display = 'none';
            document.getElementById('deletePassword').value = '';
            }

            // Handle delete confirmation
            function confirmDelete() {
            const password = document.getElementById('deletePassword').value;
            const currentVideo = videos[currentIndex];
            
            fetch('', {
                method: 'POST',
                headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: `password=${encodeURIComponent(password)}&video=${encodeURIComponent(currentVideo)}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                videos.splice(currentIndex, 1);
                closeDeleteModal();
                if (videos.length === 0) {
                    location.reload();
                } else {
                    nextVideo();
                }
                } else {
                showError('Incorrect password');
                }
            })
            .catch(() => {
                showError('Error deleting video');
            });
            }

            // Show error message
            function showError(message) {
            const errorDiv = document.createElement('div');
            errorDiv.className = 'error-message';
            errorDiv.textContent = message;
            errorDiv.style.color = '#dc3545';
            errorDiv.style.fontSize = '14px';
            errorDiv.style.marginTop = '10px';
            errorDiv.style.textAlign = 'center';
            
            const existingError = deleteModal.querySelector('.error-message');
            if (existingError) {
                existingError.remove();
            }
            
            deleteModal.querySelector('.buttons').insertAdjacentElement('beforebegin', errorDiv);
            
            setTimeout(() => {
                errorDiv.remove();
            }, 3000);
            }



            // Additional functions
            window.togglePiP = async function() {
                try {
                    if (document.pictureInPictureElement) {
                        await document.exitPictureInPicture();
                    } else {
                        await player.requestPictureInPicture();
                    }
                } catch (error) {
                    console.error('PiP failed:', error);
                }
            }

            window.toggleFullscreen = function() {
                if (!document.fullscreenElement) {
                    if (wrapper.requestFullscreen) {
                        wrapper.requestFullscreen();
                    } else if (wrapper.webkitRequestFullscreen) {
                        wrapper.webkitRequestFullscreen();
                    } else if (wrapper.msRequestFullscreen) {
                        wrapper.msRequestFullscreen();
                    }
                } else {
                    if (document.exitFullscreen) {
                        document.exitFullscreen();
                    } else if (document.webkitExitFullscreen) {
                        document.webkitExitFullscreen();
                    } else if (document.msExitFullscreen) {
                        document.msExitFullscreen();
                    }
                }
            }
            
            window.shareVideo = function() {
                const url = new URL(window.location.href);
                url.searchParams.set('video', videos[currentIndex]);
                navigator.clipboard.writeText(url.toString())
                    .then(() => alert('Video URL copied to clipboard!'))
                    .catch(console.error);
            }

            window.downloadVideo = function() {
                const link = document.createElement('a');
                link.href = player.src;
                link.download = videos[currentIndex].split('/').pop();
                link.click();
            }
            // Add this to your JavaScript
            const playPauseBtn = document.getElementById('playPauseBtn');
            const videoPlayer = document.getElementById('videoPlayer');

            playPauseBtn.addEventListener('click', function() {
                if (videoPlayer.paused) {
                    videoPlayer.play();
                    playPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
                } else {
                    videoPlayer.pause();
                    playPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            });
            
            // Search and Sort functionality
            const videoSearch = document.getElementById('videoSearch');
            const videoSort = document.getElementById('videoSort');
            const videoListContent = document.getElementById('videoListContent');
            
            function convertSizeToBytes(sizeStr) {
                const units = {
                    'B': 1,
                    'KB': 1024,
                    'MB': 1024 * 1024,
                    'GB': 1024 * 1024 * 1024
                };
                const matches = sizeStr.match(/^([\d.]+)\s*([A-Z]+)$/);
                if (!matches) return 0;
                const size = parseFloat(matches[1]);
                const unit = matches[2];
                return size * units[unit];
            }
            
            function filterAndSortVideos() {
                const searchTerm = videoSearch.value.toLowerCase();
                const sortBy = videoSort.value;
                const videoItems = Array.from(videoListContent.getElementsByClassName('video-item'));
                
                videoItems.sort((a, b) => {
                    if (sortBy === 'favorite') {
                        const aHasFav = a.querySelector('.favorite-icon') !== null;
                        const bHasFav = b.querySelector('.favorite-icon') !== null;
                        if (aHasFav === bHasFav) {
                            return a.dataset.name.localeCompare(b.dataset.name);
                        }
                        return bHasFav ? 1 : -1;
                    } else if (sortBy === 'size') {
                        const aSize = convertSizeToBytes(a.dataset[sortBy]);
                        const bSize = convertSizeToBytes(b.dataset[sortBy]);
                        return bSize - aSize;
                    }
                    return a.dataset[sortBy].localeCompare(b.dataset[sortBy]);
                });
            
                videoItems.forEach(item => {
                    const videoName = item.dataset.name.toLowerCase();
                    item.style.display = videoName.includes(searchTerm) ? '' : 'none';
                });
            
                videoListContent.innerHTML = '';
                videoItems.forEach(item => videoListContent.appendChild(item));
            }
            
            videoSearch.addEventListener('input', filterAndSortVideos);
            videoSort.addEventListener('change', filterAndSortVideos);

            // Add this to handle double-click fullscreen
            videoPlayer.addEventListener('dblclick', function(e) {
                const rect = videoPlayer.getBoundingClientRect();
                const clickX = e.clientX - rect.left;
                const width = rect.width;
                
                // Only trigger fullscreen on middle section (30% to 70% of width)
                if (clickX > width * 0.3 && clickX < width * 0.7) {
                    if (!document.fullscreenElement) {
                        if (videoPlayer.requestFullscreen) {
                            videoPlayer.requestFullscreen();
                        } else if (videoPlayer.webkitRequestFullscreen) {
                            videoPlayer.webkitRequestFullscreen();
                        } else if (videoPlayer.msRequestFullscreen) {
                            videoPlayer.msRequestFullscreen();
                        }
                    } else {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                        } else if (document.webkitExitFullscreen) {
                            document.webkitExitFullscreen();
                        } else if (document.msExitFullscreen) {
                            document.msExitFullscreen();
                        }
                    }
                }
            });

            // Keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                if (e.code === 'Space') {
                    e.preventDefault();
                    togglePlay();
                } else if (e.code === 'ArrowLeft') {
                    skipBackward();
                } else if (e.code === 'ArrowRight') {
                    skipForward();
                } else if (e.code === 'KeyM') {
                    player.muted = !player.muted;
                    updateVolumeIcon();
                } else if (e.code === 'KeyF') {
                    toggleFullscreen();
                } else if (e.code === 'KeyP') {
                    togglePiP();
                }
            });

            // Helper functions
            function formatTime(seconds) {
                if (isNaN(seconds)) return "0:00";
                const minutes = Math.floor(seconds / 60);
                seconds = Math.floor(seconds % 60);
                return `${minutes}:${seconds.toString().padStart(2, '0')}`;
            }

            function updateTimeDisplay() {
                const currentTime = formatTime(player.currentTime);
                const duration = formatTime(player.duration);
                document.getElementById('timeDisplay').textContent = `${currentTime} / ${duration}`;
            }

            // Auto play next
            player.addEventListener('ended', nextVideo);
        });
    </script>
    
</body>
</html>
