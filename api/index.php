<?php
/*
 * ███████╗ █████╗ ███████╗ ██████╗ ███╗   ███╗ █████╗ ███████╗██╗██╗
 * ██╔════╝██╔══██╗██╔════╝██╔════╝ ████╗ ████║██╔══██╗██╔════╝██║██║
 * █████╗  ███████║███████╗██║  ███╗██╔████╔██║███████║███████╗██║██║
 * ██╔══╝  ██╔══██║╚════██║██║   ██║██║╚██╔╝██║██╔══██║╚════██║╚═╝╚═╝
 * ██║     ██║  ██║███████║╚██████╔╝██║ ╚═╝ ██║██║  ██║███████║██╗██╗
 * ╚═╝     ╚═╝  ╚═╝╚══════╝ ╚═════╬╝╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝╚═╝
 *                              ╚╝                                    
 *              CAPTCHA SERVICE FOR DEVELOPERS
 */

session_start();
header('Content-Type: text/html; charset=utf-8');

// ==================== CONFIGURATION ====================
define('DB_FILE', __DIR__ . '/captcha_db.sqlite');
define('SITE_NAME', 'SecureCaptcha');
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);

// ==================== DATABASE SETUP ====================
function initDB() {
    try {
        $db = new PDO('sqlite:' . DB_FILE);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $db->exec('PRAGMA journal_mode = WAL;');
        
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT UNIQUE NOT NULL,
            api_key TEXT UNIQUE NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            last_login DATETIME
        )");
        
        $db->exec("CREATE TABLE IF NOT EXISTS captcha_sessions (
            id TEXT PRIMARY KEY,
            api_key TEXT NOT NULL,
            code TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            solved INTEGER DEFAULT 0,
            attempts INTEGER DEFAULT 0
        )");
        
        $db->exec("CREATE INDEX IF NOT EXISTS idx_captcha_api ON captcha_sessions(api_key)");
        $db->exec("CREATE INDEX IF NOT EXISTS idx_captcha_created ON captcha_sessions(created_at)");
        
        return $db;
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }
}

 $db = initDB();

// ==================== HELPER FUNCTIONS ====================
function generateApiKey() {
    return 'sk_' . bin2hex(random_bytes(24));
}

function generateCaptchaId() {
    return 'cap_' . bin2hex(random_bytes(16));
}

function sanitize($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ==================== CAPTCHA GENERATION ====================
function generateCaptchaImage($code) {
    $width = 200;
    $height = 70;
    
    $image = imagecreatetruecolor($width, $height);
    
    // Background gradient
    for ($y = 0; $y < $height; $y++) {
        $ratio = $y / $height;
        $r = 15 + (25 - 15) * $ratio;
        $g = 23 + (40 - 23) * $ratio;
        $b = 42 + (65 - 42) * $ratio;
        imageline($image, 0, $y, $width, $y, imagecolorallocate($image, $r, $g, $b));
    }
    
    // Noise dots
    for ($i = 0; $i < 180; $i++) {
        $alpha = rand(50, 90);
        $color = imagecolorallocatealpha($image, rand(80, 220), rand(80, 220), rand(80, 220), $alpha);
        imagesetpixel($image, rand(0, $width), rand(0, $height), $color);
    }
    
    // Distortion lines
    for ($i = 0; $i < 8; $i++) {
        $color = imagecolorallocatealpha($image, rand(50, 140), rand(50, 140), rand(50, 140), 55);
        $points = [];
        for ($j = 0; $j < 4; $j++) {
            $points[] = rand(0, $width);
            $points[] = rand(0, $height);
        }
        imagepolygon($image, $points, 2, $color);
    }
    
    // Character colors - vibrant
    $colors = [
        imagecolorallocate($image, 34, 211, 238),   // Cyan
        imagecolorallocate($image, 168, 85, 247),   // Purple
        imagecolorallocate($image, 52, 211, 153),   // Emerald
        imagecolorallocate($image, 251, 146, 60),   // Orange
        imagecolorallocate($image, 244, 63, 94),    // Rose
        imagecolorallocate($image, 250, 204, 21),   // Yellow
    ];
    
    // Draw characters with random styling
    $len = strlen($code);
    $charWidth = ($width - 30) / $len;
    
    for ($i = 0; $i < $len; $i++) {
        $char = $code[$i];
        $color = $colors[$i % count($colors)];
        
        $x = 15 + $i * $charWidth + rand(-3, 3);
        $y = 18 + rand(-6, 6);
        $size = rand(4, 5);
        
        // Shadow
        imagestring($image, $size, $x + 1, $y + 1, $char, imagecolorallocatealpha($image, 0, 0, 0, 50));
        // Main character
        imagestring($image, $size, $x, $y, $char, $color);
    }
    
    // Wave distortion effect
    $temp = imagecreatetruecolor($width, $height);
    imagecopy($temp, $image, 0, 0, 0, 0, $width, $height);
    
    $amplitude = 2;
    $period = 30;
    for ($x = 0; $x < $width; $x++) {
        $offset = (int)($amplitude * sin($x / $period * 2 * M_PI));
        imagecopy($image, $temp, $x, 0, $x, $offset, 1, $height);
    }
    imagedestroy($temp);
    
    // Final noise overlay
    for ($i = 0; $i < 50; $i++) {
        $color = imagecolorallocatealpha($image, rand(150, 255), rand(150, 255), rand(150, 255), 85);
        imageline($image, rand(0, $width), rand(0, $height), rand(0, $width), rand(0, $height), $color);
    }
    
    ob_start();
    imagepng($image, null, 7);
    $data = ob_get_clean();
    imagedestroy($image);
    
    return $data;
}

// ==================== ROUTING ====================
// Handle CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    exit;
}

// Clean old captchas
 $db->exec("DELETE FROM captcha_sessions WHERE created_at < datetime('now', '-10 minutes')");

// Get request path - works on all servers
 $requestUri = $_SERVER['REQUEST_URI'];
 $path = parse_url($requestUri, PHP_URL_PATH);
 $method = $_SERVER['REQUEST_METHOD'];

// Remove script name if present (for subdirectory installations)
 $scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName !== '/' && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}
if ($path === '') $path = '/';

// ==================== API ENDPOINTS ====================

// Get Captcha - flexible pattern matching
if (preg_match('#^/?api/getcaptcha/([a-zA-Z0-9_]+)$#', $path, $m) && $method === 'GET') {
    $apiKey = $m[1];
    
    $stmt = $db->prepare("SELECT id FROM users WHERE api_key = ?");
    $stmt->execute([$apiKey]);
    if (!$stmt->fetch()) {
        jsonResponse(['error' => 'Invalid API key', 'success' => false], 401);
    }
    
    $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $captchaId = generateCaptchaId();
    
    $stmt = $db->prepare("INSERT INTO captcha_sessions (id, api_key, code) VALUES (?, ?, ?)");
    $stmt->execute([$captchaId, $apiKey, $code]);
    
    $imgData = generateCaptchaImage($code);
    $base64 = base64_encode($imgData);
    
    jsonResponse([
        'success' => true,
        'captcha_id' => $captchaId,
        'image_base64' => $base64,
        'image_url' => 'data:image/png;base64,' . $base64,
        'expires_in' => 600
    ]);
}

// Verify Captcha
if (preg_match('#^/?api/verify$#', $path) && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['captcha_id']) || !isset($input['code'])) {
        jsonResponse(['error' => 'Missing parameters', 'success' => false], 400);
    }
    
    $captchaId = $input['captcha_id'];
    $code = strtoupper(trim($input['code']));
    
    $stmt = $db->prepare("SELECT cs.*, u.api_key FROM captcha_sessions cs 
                          JOIN users u ON cs.api_key = u.api_key 
                          WHERE cs.id = ?");
    $stmt->execute([$captchaId]);
    $captcha = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$captcha) {
        jsonResponse(['error' => 'Invalid or expired captcha', 'success' => false], 404);
    }
    
    if ($captcha['attempts'] >= 5) {
        $db->prepare("DELETE FROM captcha_sessions WHERE id = ?")->execute([$captchaId]);
        jsonResponse(['error' => 'Too many attempts', 'success' => false], 429);
    }
    
    $db->prepare("UPDATE captcha_sessions SET attempts = attempts + 1 WHERE id = ?")->execute([$captchaId]);
    
    if ($captcha['code'] === $code) {
        $db->prepare("UPDATE captcha_sessions SET solved = 1 WHERE id = ?")->execute([$captchaId]);
        jsonResponse([
            'success' => true,
            'message' => 'Captcha verified successfully',
            'verified' => true
        ]);
    } else {
        jsonResponse([
            'success' => false,
            'error' => 'Incorrect code',
            'verified' => false
        ]);
    }
}

// Demo verify
if (preg_match('#^/?api/demo/verify$#', $path) && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($_SESSION['demo_captcha']) || !isset($input['code'])) {
        jsonResponse(['success' => false, 'error' => 'Session expired']);
    }
    
    if (strtoupper(trim($input['code'])) === $_SESSION['demo_captcha']) {
        unset($_SESSION['demo_captcha']);
        jsonResponse(['success' => true, 'verified' => true]);
    } else {
        jsonResponse(['success' => false, 'verified' => false]);
    }
}

// Demo captcha
if (preg_match('#^/?api/demo/captcha$#', $path) && $method === 'GET') {
    $code = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    $_SESSION['demo_captcha'] = $code;
    
    $imgData = generateCaptchaImage($code);
    
    jsonResponse([
        'success' => true,
        'image_base64' => base64_encode($imgData)
    ]);
}

// Auth
if (preg_match('#^/?api/auth$#', $path) && $method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['email']) || !filter_var($input['email'], FILTER_VALIDATE_EMAIL)) {
        jsonResponse(['error' => 'Valid email required'], 400);
    }
    
    $email = strtolower(trim($input['email']));
    
    $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = ?")->execute([$user['id']]);
    } else {
        $apiKey = generateApiKey();
        $stmt = $db->prepare("INSERT INTO users (email, api_key) VALUES (?, ?)");
        $stmt->execute([$email, $apiKey]);
        
        $stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    
    jsonResponse([
        'success' => true,
        'user' => [
            'email' => $user['email'],
            'api_key' => $user['api_key'],
            'created_at' => $user['created_at']
        ]
    ]);
}

// Logout
if (preg_match('#^/?api/logout$#', $path)) {
    session_destroy();
    jsonResponse(['success' => true]);
}

// Get current user
if (preg_match('#^/?api/me$#', $path)) {
    if (!isset($_SESSION['user_id'])) {
        jsonResponse(['error' => 'Not authenticated'], 401);
    }
    
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        session_destroy();
        jsonResponse(['error' => 'User not found'], 404);
    }
    
    $stmt = $db->prepare("SELECT COUNT(*) as total FROM captcha_sessions WHERE api_key = ?");
    $stmt->execute([$user['api_key']]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    jsonResponse([
        'success' => true,
        'user' => [
            'email' => $user['email'],
            'api_key' => $user['api_key'],
            'created_at' => $user['created_at'],
            'total_requests' => $stats['total'] ?? 0
        ]
    ]);
}

// ==================== PAGE ROUTING ====================
 $isLoggedIn = isset($_SESSION['user_id']);
 $currentPage = 'home';

if (preg_match('#/docs#', $path)) {
    $currentPage = 'docs';
} elseif (preg_match('#/dashboard#', $path)) {
    $currentPage = 'dashboard';
}

// Get user data if logged in
 $currentUser = null;
if ($isLoggedIn) {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$_SESSION['user_id']]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title><?= SITE_NAME ?> - خدمة كابتشا مجانية للمطورين</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;900&family=Fira+Code:wght@400;500&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --bg: #0a0f1a;
            --bg-secondary: #111827;
            --fg: #f8fafc;
            --muted: #94a3b8;
            --accent: #06b6d4;
            --accent-secondary: #8b5cf6;
            --card: #1e293b;
            --border: #334155;
            --success: #10b981;
            --error: #ef4444;
        }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        html { scroll-behavior: smooth; overflow-x: hidden; }
        body {
            font-family: 'Cairo', sans-serif;
            background: var(--bg);
            color: var(--fg);
            min-height: 100vh;
            overflow-x: hidden;
            width: 100%;
        }
        .bg-pattern {
            position: fixed;
            inset: 0;
            z-index: -1;
            background: 
                radial-gradient(ellipse 80% 50% at 50% -20%, rgba(6, 182, 212, 0.12), transparent),
                radial-gradient(ellipse 60% 40% at 80% 100%, rgba(139, 92, 246, 0.08), transparent),
                linear-gradient(180deg, var(--bg) 0%, #0f172a 100%);
        }
        .nav {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            padding: 0.75rem 1rem;
            backdrop-filter: blur(20px);
            background: rgba(10, 15, 26, 0.85);
            border-bottom: 1px solid rgba(6, 182, 212, 0.1);
        }
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 900;
            font-size: 1.25rem;
            background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        .logo-icon {
            width: 36px;
            height: 36px;
            background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            -webkit-text-fill-color: initial;
        }
        .nav-links {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: var(--bg-secondary);
            padding: 0.75rem;
            flex-direction: column;
            border-bottom: 1px solid var(--border);
            gap: 0.25rem;
        }
        .nav-links.show { display: flex; }
        @media (min-width: 768px) {
            .mobile-menu-btn { display: none; }
            .nav-links {
                display: flex;
                position: static;
                background: transparent;
                padding: 0;
                flex-direction: row;
                border-bottom: none;
            }
        }
        .nav-link {
            padding: 0.5rem 0.75rem;
            border-radius: 8px;
            color: var(--muted);
            text-decoration: none;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        .nav-link:hover, .nav-link.active { color: var(--fg); background: rgba(6, 182, 212, 0.1); }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.6rem 1.2rem;
            border-radius: 10px;
            font-weight: 700;
            font-size: 0.9rem;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            font-family: inherit;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--accent), #0891b2);
            color: var(--bg);
            box-shadow: 0 4px 15px rgba(6, 182, 212, 0.3);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(6, 182, 212, 0.4); }
        .btn-secondary { background: var(--card); color: var(--fg); border: 1px solid var(--border); }
        .btn-secondary:hover { border-color: var(--accent); background: rgba(6, 182, 212, 0.1); }
        .btn-ghost { background: transparent; color: var(--muted); padding: 0.5rem 0.75rem; }
        .btn-ghost:hover { color: var(--fg); background: rgba(255, 255, 255, 0.05); }
        .mobile-menu-btn {
            display: flex;
            padding: 0.5rem;
            background: transparent;
            border: none;
            color: var(--fg);
            cursor: pointer;
        }
        main { padding-top: 70px; min-height: 100vh; width: 100%; }
        .hero { padding: 2rem 1rem; text-align: center; max-width: 100%; }
        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4rem 1rem;
            background: rgba(6, 182, 212, 0.1);
            border: 1px solid rgba(6, 182, 212, 0.3);
            border-radius: 100px;
            font-size: 0.8rem;
            color: var(--accent);
            margin-bottom: 1.5rem;
        }
        .hero h1 {
            font-size: clamp(1.75rem, 6vw, 3.5rem);
            font-weight: 900;
            line-height: 1.2;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, var(--fg) 0%, var(--muted) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .hero h1 span {
            background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
            -webkit-background-clip: text;
        }
        .hero p {
            font-size: clamp(0.95rem, 2.5vw, 1.1rem);
            color: var(--muted);
            max-width: 550px;
            margin: 0 auto 1.5rem;
            line-height: 1.7;
        }
        .hero-actions { display: flex; flex-wrap: wrap; justify-content: center; gap: 0.75rem; margin-bottom: 2rem; }
        .demo-container {
            max-width: 360px;
            margin: 0 auto;
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.25);
        }
        .demo-title { font-size: 1rem; font-weight: 700; margin-bottom: 1rem; color: var(--muted); }
        .captcha-display {
            background: var(--bg);
            border-radius: 10px;
            padding: 0.75rem;
            margin-bottom: 0.75rem;
            min-height: 90px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid var(--border);
            transition: all 0.3s;
        }
        .captcha-display.success { border-color: var(--success); background: rgba(16, 185, 129, 0.1); }
        .captcha-display.error { border-color: var(--error); animation: shake 0.5s ease-in-out; }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20%, 60% { transform: translateX(-8px); }
            40%, 80% { transform: translateX(8px); }
        }
        .captcha-display img { max-width: 100%; height: auto; border-radius: 6px; }
        .captcha-input-group { display: flex; gap: 0.5rem; margin-bottom: 0.75rem; }
        .captcha-input {
            flex: 1;
            padding: 0.75rem;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: var(--bg);
            color: var(--fg);
            font-size: 1.1rem;
            font-weight: 700;
            text-align: center;
            letter-spacing: 0.3rem;
            text-transform: uppercase;
            font-family: 'Fira Code', monospace;
            transition: all 0.3s;
        }
        .captcha-input:focus { outline: none; border-color: var(--accent); box-shadow: 0 0 0 3px rgba(6, 182, 212, 0.15); }
        .captcha-input.success { border-color: var(--success); background: rgba(16, 185, 129, 0.1); }
        .captcha-input.error { border-color: var(--error); }
        .refresh-btn {
            padding: 0.75rem;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: var(--bg);
            color: var(--muted);
            cursor: pointer;
            transition: all 0.3s;
        }
        .refresh-btn:hover { border-color: var(--accent); color: var(--accent); }
        .verify-btn {
            width: 100%;
            padding: 0.75rem;
            border-radius: 10px;
            border: none;
            background: linear-gradient(135deg, var(--accent), #0891b2);
            color: var(--bg);
            font-weight: 700;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .verify-btn:hover { transform: translateY(-2px); box-shadow: 0 6px 20px rgba(6, 182, 212, 0.35); }
        .success-message {
            display: none;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.75rem;
            background: rgba(16, 185, 129, 0.1);
            border: 1px solid var(--success);
            border-radius: 10px;
            color: var(--success);
            font-weight: 600;
            font-size: 0.9rem;
            margin-top: 0.75rem;
        }
        .success-message.show { display: flex; }
        .stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1rem;
            max-width: 600px;
            margin: 2rem auto 0;
            padding: 0 1rem;
        }
        @media (min-width: 640px) { .stats { grid-template-columns: repeat(4, 1fr); } }
        .stat-card {
            background: var(--card);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--border);
            text-align: center;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: 900;
            background: linear-gradient(135deg, var(--accent), var(--accent-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .stat-label { color: var(--muted); font-size: 0.75rem; margin-top: 0.25rem; }
        .features { padding: 3rem 1rem; max-width: 1100px; margin: 0 auto; }
        .section-title { text-align: center; margin-bottom: 2rem; }
        .section-title h2 { font-size: clamp(1.5rem, 4vw, 2rem); font-weight: 900; margin-bottom: 0.5rem; }
        .section-title p { color: var(--muted); font-size: 0.95rem; }
        .features-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1rem; }
        .feature-card {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            transition: all 0.3s;
        }
        .feature-card:hover { transform: translateY(-3px); border-color: var(--accent); box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2); }
        .feature-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            background: linear-gradient(135deg, rgba(6, 182, 212, 0.15), rgba(139, 92, 246, 0.15));
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            color: var(--accent);
        }
        .feature-card h3 { font-size: 1.1rem; font-weight: 700; margin-bottom: 0.5rem; }
        .feature-card p { color: var(--muted); font-size: 0.9rem; line-height: 1.6; }
        .code-block {
            background: var(--bg);
            border-radius: 10px;
            padding: 1rem;
            overflow-x: auto;
            border: 1px solid var(--border);
            font-family: 'Fira Code', monospace;
            font-size: 0.75rem;
            line-height: 1.5;
            position: relative;
            max-width: 100%;
        }
        .code-block .copy-btn {
            position: absolute;
            top: 0.5rem;
            left: 0.5rem;
            padding: 0.3rem;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 6px;
            color: var(--muted);
            cursor: pointer;
        }
        .code-block .copy-btn:hover { color: var(--accent); border-color: var(--accent); }
        .code-block pre { margin: 0; white-space: pre-wrap; word-break: break-word; }
        .code-keyword { color: #c792ea; }
        .code-string { color: #c3e88d; }
        .code-comment { color: #546e7a; }
        .docs-layout {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            max-width: 100%;
            margin: 0 auto;
            padding: 1rem;
        }
        @media (min-width: 900px) {
            .docs-layout { grid-template-columns: 220px 1fr; max-width: 1200px; padding: 1.5rem; }
        }
        .docs-sidebar {
            background: var(--card);
            border-radius: 12px;
            padding: 1rem;
            border: 1px solid var(--border);
        }
        .docs-sidebar-title {
            font-weight: 700;
            margin-bottom: 0.75rem;
            color: var(--muted);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .docs-nav { display: flex; flex-direction: row; flex-wrap: wrap; gap: 0.25rem; }
        @media (min-width: 900px) { .docs-nav { flex-direction: column; } }
        .docs-nav a {
            padding: 0.5rem 0.75rem;
            border-radius: 6px;
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.85rem;
            transition: all 0.2s;
        }
        .docs-nav a:hover, .docs-nav a.active { color: var(--fg); background: rgba(6, 182, 212, 0.1); }
        .docs-content {
            background: var(--card);
            border-radius: 16px;
            padding: 1.5rem;
            border: 1px solid var(--border);
            max-width: 100%;
            overflow-x: hidden;
        }
        .docs-content h2 {
            font-size: 1.5rem;
            font-weight: 900;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid var(--border);
        }
        .docs-content h3 { font-size: 1.1rem; font-weight: 700; margin: 1.5rem 0 0.75rem; color: var(--accent); }
        .docs-content p { color: var(--muted); font-size: 0.9rem; line-height: 1.7; margin-bottom: 0.75rem; }
        .docs-content ul { margin: 0.75rem 0; padding-right: 1.25rem; }
        .docs-content li { color: var(--muted); font-size: 0.9rem; margin-bottom: 0.35rem; line-height: 1.6; }
        .dashboard { max-width: 900px; margin: 0 auto; padding: 1rem; }
        .dashboard-header { margin-bottom: 1.5rem; }
        .dashboard-header h1 { font-size: 1.5rem; font-weight: 900; margin-bottom: 0.25rem; }
        .dashboard-header p { color: var(--muted); font-size: 0.9rem; }
        .api-key-card {
            background: var(--card);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid var(--border);
            margin-bottom: 1.5rem;
        }
        .api-key-label {
            font-size: 0.75rem;
            color: var(--muted);
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }
        .api-key-value {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            background: var(--bg);
            border-radius: 10px;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border);
            font-family: 'Fira Code', monospace;
            font-size: 0.75rem;
            word-break: break-all;
        }
        @media (min-width: 480px) { .api-key-value { flex-direction: row; align-items: center; font-size: 0.8rem; } }
        .api-key-value span { color: var(--accent); flex: 1; direction: ltr; text-align: right; }
        .endpoint-grid { display: grid; grid-template-columns: 1fr; gap: 1rem; }
        @media (min-width: 640px) { .endpoint-grid { grid-template-columns: repeat(2, 1fr); } }
        .endpoint-card {
            background: var(--card);
            border-radius: 12px;
            padding: 1.25rem;
            border: 1px solid var(--border);
        }
        .endpoint-method {
            display: inline-block;
            padding: 0.2rem 0.5rem;
            border-radius: 4px;
            font-size: 0.65rem;
            font-weight: 700;
            margin-left: 0.5rem;
        }
        .endpoint-method.get { background: rgba(16, 185, 129, 0.2); color: #10b981; }
        .endpoint-method.post { background: rgba(59, 130, 246, 0.2); color: #3b82f6; }
        .endpoint-url {
            font-family: 'Fira Code', monospace;
            font-size: 0.7rem;
            margin: 0.75rem 0;
            color: var(--muted);
            background: var(--bg);
            padding: 0.5rem;
            border-radius: 6px;
            word-break: break-all;
            direction: ltr;
            text-align: left;
        }
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(10px);
            z-index: 200;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .modal-overlay.show { display: flex; }
        .modal {
            background: var(--card);
            border-radius: 20px;
            padding: 2rem;
            max-width: 400px;
            width: 100%;
            border: 1px solid var(--border);
            position: relative;
        }
        .modal-close {
            position: absolute;
            top: 0.75rem;
            left: 0.75rem;
            background: transparent;
            border: none;
            color: var(--muted);
            cursor: pointer;
            padding: 0.5rem;
        }
        .modal-close:hover { color: var(--fg); }
        .modal h2 { font-size: 1.5rem; font-weight: 900; margin-bottom: 0.5rem; }
        .modal p { color: var(--muted); font-size: 0.9rem; margin-bottom: 1.5rem; }
        .form-group { margin-bottom: 1.25rem; }
        .form-label { display: block; font-weight: 600; font-size: 0.85rem; margin-bottom: 0.4rem; color: var(--muted); }
        .form-input {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 10px;
            border: 2px solid var(--border);
            background: var(--bg);
            color: var(--fg);
            font-size: 1rem;
            font-family: inherit;
            transition: all 0.3s;
        }
        .form-input:focus { outline: none; border-color: var(--accent); }
        .fade-in { animation: fadeIn 0.5s ease-out forwards; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(15px); } to { opacity: 1; transform: translateY(0); } }
        .loading-spinner {
            width: 35px;
            height: 35px;
            border: 3px solid var(--border);
            border-top-color: var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .toast {
            position: fixed;
            bottom: 1.5rem;
            left: 50%;
            transform: translateX(-50%) translateY(80px);
            background: var(--card);
            border: 1px solid var(--border);
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            box-shadow: 0 8px 30px rgba(0, 0, 0, 0.3);
            z-index: 300;
            opacity: 0;
            transition: all 0.3s;
            font-size: 0.9rem;
            max-width: 90vw;
        }
        .toast.show { transform: translateX(-50%) translateY(0); opacity: 1; }
        .toast.success { border-color: var(--success); background: rgba(16, 185, 129, 0.15); }
        .toast.error { border-color: var(--error); background: rgba(239, 68, 68, 0.15); }
        footer { padding: 2rem 1rem; border-top: 1px solid var(--border); margin-top: 3rem; }
        .footer-content {
            max-width: 900px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1rem;
            text-align: center;
        }
        @media (min-width: 640px) { .footer-content { flex-direction: row; justify-content: space-between; } }
        .footer-links { display: flex; gap: 1.5rem; }
        .footer-links a { color: var(--muted); text-decoration: none; font-size: 0.9rem; transition: color 0.2s; }
        .footer-links a:hover { color: var(--accent); }
        .footer-copy { color: var(--muted); font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="bg-pattern"></div>
    
    <nav class="nav">
        <div class="nav-container">
            <a href="/" class="logo">
                <div class="logo-icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        <path d="M9 12l2 2 4-4"/>
                    </svg>
                </div>
                <?= SITE_NAME ?>
            </a>
            
            <button class="mobile-menu-btn" onclick="toggleMobileMenu()">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M3 12h18M3 6h18M3 18h18"/>
                </svg>
            </button>
            
            <div class="nav-links" id="navLinks">
                <a href="/" class="nav-link <?= $currentPage === 'home' ? 'active' : '' ?>">الرئيسية</a>
                <a href="/docs" class="nav-link <?= $currentPage === 'docs' ? 'active' : '' ?>">التوثيق</a>
                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard" class="nav-link <?= $currentPage === 'dashboard' ? 'active' : '' ?>">لوحة التحكم</a>
                    <button class="btn btn-ghost" onclick="logout()">خروج</button>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="showAuthModal()">ابدأ مجانا</button>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    
    <main>
        <?php if ($currentPage === 'home'): ?>
        <section class="hero">
            <div class="hero-badge">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                </svg>
                مجاني تماماً
            </div>
            
            <h1>حماية مواقعك من <span>الغش</span><br>بكابتشا ذكية وسريعة</h1>
            
            <p>خدمة كابتشا مجانية للمطورين، سهلة التكامل، سريعة الاستجابة، مع حماية متقدمة من البوتات</p>
            
            <div class="hero-actions">
                <?php if ($isLoggedIn): ?>
                    <a href="/dashboard" class="btn btn-primary">لوحة التحكم</a>
                <?php else: ?>
                    <button class="btn btn-primary" onclick="showAuthModal()">ابدأ مجاناً</button>
                <?php endif; ?>
                <a href="/docs" class="btn btn-secondary">التوثيق</a>
            </div>
            
            <div class="demo-container fade-in">
                <div class="demo-title">جرّب الكابتشا الآن</div>
                
                <div class="captcha-display" id="captchaDisplay">
                    <div class="loading-spinner" id="captchaLoader"></div>
                    <img id="captchaImage" style="display:none;" alt="Captcha">
                </div>
                
                <div class="captcha-input-group">
                    <input type="text" class="captcha-input" id="captchaInput" placeholder="أدخل الكود" maxlength="6" autocomplete="off">
                    <button class="refresh-btn" onclick="loadDemoCaptcha()" title="تحديث">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M23 4v6h-6"/><path d="M1 20v-6h6"/>
                            <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"/>
                        </svg>
                    </button>
                </div>
                
                <button class="verify-btn" onclick="verifyDemoCaptcha()">تحقق</button>
                
                <div class="success-message" id="successMessage">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/>
                        <polyline points="22 4 12 14.01 9 11.01"/>
                    </svg>
                    تم التحقق بنجاح!
                </div>
            </div>
            
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-value" id="statUsers">0</div>
                    <div class="stat-label">مطور نشط</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="statRequests">0</div>
                    <div class="stat-label">طلب تحقق</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">&lt;50ms</div>
                    <div class="stat-label">زمن الاستجابة</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">100%</div>
                    <div class="stat-label">مجاني</div>
                </div>
            </div>
        </section>
        
        <section class="features">
            <div class="section-title">
                <h2>لماذا <?= SITE_NAME ?>؟</h2>
                <p>مميزات تجعلنا الخيار الأفضل للمطورين</p>
            </div>
            
            <div class="features-grid">
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                        </svg>
                    </div>
                    <h3>سرعة فائقة</h3>
                    <p>استجابة فورية أقل من 50ms مع خوادم سريعة ومُحسّنة</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                        </svg>
                    </div>
                    <h3>حماية متقدمة</h3>
                    <p>صور مشوّشة وملونة بأحرف عشوائية صعبة على البوتات</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="16 18 22 12 16 6"/>
                            <polyline points="8 6 2 12 8 18"/>
                        </svg>
                    </div>
                    <h3>تكامل سهل</h3>
                    <p>API بسيط مع JSON وصور Base64 يعمل مع أي لغة برمجة</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    </div>
                    <h3>مجاني تماماً</h3>
                    <p>لا رسوم خفية، لا حدود يومية، خدمة كاملة مجانية</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                        </svg>
                    </div>
                    <h3>منع الغش</h3>
                    <p>حد أقصى للمحاولات الفاشلة مع انتهاء صلاحية الكود</p>
                </div>
                
                <div class="feature-card">
                    <div class="feature-icon">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                            <line x1="8" y1="21" x2="16" y2="21"/>
                            <line x1="12" y1="17" x2="12" y2="21"/>
                        </svg>
                    </div>
                    <h3>متجاوب</h3>
                    <p>يعمل على جميع الأجهزة والشاشات بتصميم أنيق</p>
                </div>
            </div>
        </section>
        
        <?php elseif ($currentPage === 'docs'): ?>
        <div class="docs-layout">
            <aside class="docs-sidebar">
                <div class="docs-sidebar-title">المحتويات</div>
                <nav class="docs-nav">
                    <a href="#intro" class="active">المقدمة</a>
                    <a href="#quickstart">البدء السريع</a>
                    <a href="#endpoints">نقاط API</a>
                    <a href="#examples">أمثلة</a>
                </nav>
            </aside>
            
            <div class="docs-content">
                <h2 id="intro">توثيق API</h2>
                
                <p>مرحباً بك في توثيق <?= SITE_NAME ?>. هذا الدليل سيساعدك على تكامل خدمة الكابتشا مع موقعك بسهولة.</p>
                
                <h3 id="quickstart">البدء السريع</h3>
                <ul>
                    <li>سجّل دخول بالإيميل للحصول على API Key</li>
                    <li>استدعِ endpoint الحصول على كابتشا</li>
                    <li>اعرض الصورة للمستخدم وتحقق من الإجابة</li>
                </ul>
                
                <h3 id="endpoints">نقاط API</h3>
                
                <h4 style="color: var(--fg); margin: 1rem 0 0.5rem;">1. الحصول على كابتشا</h4>
                <div class="code-block">
                    <button class="copy-btn" onclick="copyCode(this)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                    <pre><span class="code-keyword">GET</span> /api/getcaptcha/<span class="code-variable">{YOUR_API_KEY}</span></pre>
                </div>
                
                <p style="margin-top: 0.75rem;"><strong>الاستجابة:</strong></p>
                <div class="code-block">
                    <pre>{
  <span class="code-string">"success"</span>: <span class="code-keyword">true</span>,
  <span class="code-string">"captcha_id"</span>: <span class="code-string">"cap_xxx..."</span>,
  <span class="code-string">"image_base64"</span>: <span class="code-string">"iVBORw0KGgo..."</span>,
  <span class="code-string">"image_url"</span>: <span class="code-string">"data:image/png;base64,..."</span>
}</pre>
                </div>
                
                <h4 style="color: var(--fg); margin: 1rem 0 0.5rem;">2. التحقق من الكابتشا</h4>
                <div class="code-block">
                    <pre><span class="code-keyword">POST</span> /api/verify
{
  <span class="code-string">"captcha_id"</span>: <span class="code-string">"cap_xxx..."</span>,
  <span class="code-string">"code"</span>: <span class="code-string">"ABC123"</span>
}</pre>
                </div>
                
                <h3 id="examples">أمثلة الكود</h3>
                
                <h4 style="color: var(--fg); margin: 1rem 0 0.5rem;">JavaScript</h4>
                <div class="code-block">
                    <pre><span class="code-comment">// الحصول على كابتشا</span>
<span class="code-keyword">const</span> res = <span class="code-keyword">await</span> <span class="code-function">fetch</span>(<span class="code-string">'/api/getcaptcha/YOUR_API_KEY'</span>);
<span class="code-keyword">const</span> data = <span class="code-keyword">await</span> res.<span class="code-function">json</span>();
<span class="code-variable">document</span>.<span class="code-function">getElementById</span>(<span class="code-string">'captchaImg'</span>).<span class="code-variable">src</span> = data.<span class="code-variable">image_url</span>;

<span class="code-comment">// التحقق</span>
<span class="code-keyword">const</span> verifyRes = <span class="code-keyword">await</span> <span class="code-function">fetch</span>(<span class="code-string">'/api/verify'</span>, {
  <span class="code-variable">method</span>: <span class="code-string">'POST'</span>,
  <span class="code-variable">headers</span>: {<span class="code-string">'Content-Type'</span>: <span class="code-string">'application/json'</span>},
  <span class="code-variable">body</span>: <span class="code-variable">JSON</span>.<span class="code-function">stringify</span>({
    <span class="code-string">captcha_id</span>: data.<span class="code-variable">captcha_id</span>,
    <span class="code-string">code</span>: userInput
  })
});</pre>
                </div>
            </div>
        </div>
        
        <?php elseif ($currentPage === 'dashboard'): ?>
        <?php if (!$isLoggedIn): ?>
        <script>window.location.href = '/';</script>
        <?php else: ?>
        <div class="dashboard">
            <div class="dashboard-header">
                <h1>لوحة التحكم</h1>
                <p>مرحباً <?= sanitize($currentUser['email']) ?></p>
            </div>
            
            <div class="api-key-card">
                <div class="api-key-label">مفتاح API الخاص بك</div>
                <div class="api-key-value">
                    <span id="apiKeyValue"><?= sanitize($currentUser['api_key']) ?></span>
                    <button class="btn btn-secondary" onclick="copyApiKey()" style="font-size:0.8rem;padding:0.5rem 1rem">نسخ</button>
                </div>
            </div>
            
            <h3 style="margin-bottom: 0.75rem; font-size: 1rem;">استخدام API</h3>
            
            <div class="endpoint-grid">
                <div class="endpoint-card">
                    <div>
                        <span class="endpoint-method get">GET</span>
                        <span style="font-weight: 600; font-size: 0.9rem;">الحصول على كابتشا</span>
                    </div>
                    <div class="endpoint-url">/api/getcaptcha/<?= sanitize($currentUser['api_key']) ?></div>
                    <button class="btn btn-secondary" style="width:100%; font-size:0.8rem" onclick="testEndpoint()">جرّب الآن</button>
                </div>
                
                <div class="endpoint-card">
                    <div>
                        <span class="endpoint-method post">POST</span>
                        <span style="font-weight: 600; font-size: 0.9rem;">التحقق من الكود</span>
                    </div>
                    <div class="endpoint-url">/api/verify</div>
                    <p style="color: var(--muted); font-size: 0.8rem;">أرسل captcha_id و code</p>
                </div>
            </div>
            
            <div style="margin-top: 1.5rem;">
                <h3 style="margin-bottom: 0.75rem; font-size: 1rem;">كود جاهز للنسخ</h3>
                <div class="code-block">
                    <button class="copy-btn" onclick="copyCode(this)">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="9" y="9" width="13" height="13" rx="2" ry="2"/>
                            <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>
                        </svg>
                    </button>
                    <pre>&lt;<span class="code-keyword">div</span> <span class="code-variable">id</span>=<span class="code-string">"sc-box"</span>&gt;&lt;/<span class="code-keyword">div</span>&gt;
&lt;<span class="code-keyword">script</span>&gt;
(<span class="code-keyword">async</span>()=>{
 <span class="code-keyword">const</span> r=<span class="code-keyword">await</span> <span class="code-function">fetch</span>(<span class="code-string">'<?= SITE_URL ?>/api/getcaptcha/<?= sanitize($currentUser['api_key']) ?>'</span>);
 <span class="code-keyword">const</span> d=<span class="code-keyword">await</span> r.<span class="code-function">json</span>();
 <span class="code-variable">document</span>.<span class="code-function">getElementById</span>(<span class="code-string">'sc-box'</span>).<span class="code-variable">innerHTML</span>=
  <span class="code-string">`&lt;img src="${d.image_url}" style="cursor:pointer;border-radius:8px" onclick="load()"&gt;
   &lt;input id="sc-in" placeholder="أدخل الكود"&gt;&lt;input type="hidden" id="sc-id" value="${d.captcha_id}"&gt;`</span>;
})();
<span class="code-keyword">async function</span> <span class="code-function">check</span>(){
 <span class="code-keyword">const</span> r=<span class="code-keyword">await</span> <span class="code-function">fetch</span>(<span class="code-string">'<?= SITE_URL ?>/api/verify'</span>,{
  <span class="code-variable">method</span>:<span class="code-string">'POST'</span>,<span class="code-variable">headers</span>:{<span class="code-string">'Content-Type'</span>:<span class="code-string">'application/json'</span>},
  <span class="code-variable">body</span>:<span class="code-variable">JSON</span>.<span class="code-function">stringify</span>({<span class="code-string">captcha_id</span>:<span class="code-variable">document</span>.<span class="code-function">getElementById</span>(<span class="code-string">'sc-id'</span>).<span class="code-variable">value</span>,<span class="code-string">code</span>:<span class="code-variable">document</span>.<span class="code-function">getElementById</span>(<span class="code-string">'sc-in'</span>).<span class="code-variable">value</span>})
 });
 <span class="code-keyword">return await</span> r.<span class="code-function">json</span>();
}
&lt;/<span class="code-keyword">script</span>&gt;</pre>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </main>
    
    <div class="modal-overlay" id="authModal">
        <div class="modal">
            <button class="modal-close" onclick="hideAuthModal()">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
            <h2>ابدأ مجاناً</h2>
            <p>أدخل بريدك الإلكتروني للدخول أو إنشاء حساب</p>
            
            <form id="authForm" onsubmit="handleAuth(event)">
                <div class="form-group">
                    <label class="form-label">البريد الإلكتروني</label>
                    <input type="email" class="form-input" id="authEmail" placeholder="example@email.com" required>
                </div>
                <button type="submit" class="btn btn-primary" style="width: 100%">متابعة</button>
            </form>
        </div>
    </div>
    
    <div class="toast" id="toast"></div>
    
    <footer>
        <div class="footer-content">
            <div class="footer-links">
                <a href="/">الرئيسية</a>
                <a href="/docs">التوثيق</a>
            </div>
            <div class="footer-copy">© <?= date('Y') ?> <?= SITE_NAME ?></div>
        </div>
    </footer>
    
    <script>
        function toggleMobileMenu() { document.getElementById('navLinks').classList.toggle('show'); }
        document.addEventListener('click', function(e) {
            if (!document.querySelector('.nav').contains(e.target)) {
                document.getElementById('navLinks').classList.remove('show');
            }
        });
        
        function showAuthModal() { document.getElementById('authModal').classList.add('show'); }
        function hideAuthModal() { document.getElementById('authModal').classList.remove('show'); }
        
        async function handleAuth(e) {
            e.preventDefault();
            try {
                const res = await fetch('/api/auth', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ email: document.getElementById('authEmail').value })
                });
                const data = await res.json();
                if (data.success) {
                    showToast('تم تسجيل الدخول بنجاح!', 'success');
                    setTimeout(() => window.location.href = '/dashboard', 1000);
                } else {
                    showToast(data.error || 'حدث خطأ', 'error');
                }
            } catch (err) { showToast('حدث خطأ في الاتصال', 'error'); }
        }
        
        async function logout() { await fetch('/api/logout'); window.location.href = '/'; }
        
        async function loadDemoCaptcha() {
            const display = document.getElementById('captchaDisplay');
            const image = document.getElementById('captchaImage');
            const loader = document.getElementById('captchaLoader');
            const input = document.getElementById('captchaInput');
            const success = document.getElementById('successMessage');
            
            display.classList.remove('success', 'error');
            input.classList.remove('success', 'error');
            input.value = '';
            success.classList.remove('show');
            loader.style.display = 'block';
            image.style.display = 'none';
            
            try {
                const res = await fetch('/api/demo/captcha');
                const data = await res.json();
                if (data.success) {
                    image.src = 'data:image/png;base64,' + data.image_base64;
                    loader.style.display = 'none';
                    image.style.display = 'block';
                }
            } catch (err) { showToast('حدث خطأ', 'error'); }
        }
        
        async function verifyDemoCaptcha() {
            const input = document.getElementById('captchaInput');
            const display = document.getElementById('captchaDisplay');
            const success = document.getElementById('successMessage');
            const code = input.value.trim().toUpperCase();
            
            if (!code) { showToast('الرجاء إدخال الكود', 'error'); return; }
            
            try {
                const res = await fetch('/api/demo/verify', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ code })
                });
                const data = await res.json();
                if (data.success) {
                    display.classList.add('success');
                    input.classList.add('success');
                    success.classList.add('show');
                    showToast('تم التحقق بنجاح!', 'success');
                } else {
                    display.classList.add('error');
                    input.classList.add('error');
                    showToast('الكود غير صحيح', 'error');
                    setTimeout(() => {
                        display.classList.remove('error');
                        input.classList.remove('error');
                        loadDemoCaptcha();
                    }, 800);
                }
            } catch (err) { showToast('حدث خطأ', 'error'); }
        }
        
        function showToast(message, type = '') {
            const toast = document.getElementById('toast');
            toast.textContent = message;
            toast.className = 'toast ' + type;
            setTimeout(() => toast.classList.add('show'), 10);
            setTimeout(() => toast.classList.remove('show'), 3000);
        }
        
        function copyCode(btn) {
            navigator.clipboard.writeText(btn.parentElement.querySelector('pre').textContent);
            showToast('تم النسخ!', 'success');
        }
        
        function copyApiKey() {
            navigator.clipboard.writeText(document.getElementById('apiKeyValue').textContent);
            showToast('تم نسخ مفتاح API!', 'success');
        }
        
        async function testEndpoint() {
            const key = document.getElementById('apiKeyValue').textContent;
            try {
                const res = await fetch(`/api/getcaptcha/${key}`);
                const data = await res.json();
                if (data.success) {
                    showToast('تم إنشاء كابتشا! انظر Console', 'success');
                    console.log('Captcha:', data);
                } else {
                    showToast(data.error || 'حدث خطأ', 'error');
                }
            } catch (err) { showToast('حدث خطأ', 'error'); }
        }
        
        function animateValue(id, start, end, duration) {
            const el = document.getElementById(id);
            if (!el) return;
            const range = end - start;
            const startTime = performance.now();
            function update(currentTime) {
                const progress = Math.min((currentTime - startTime) / duration, 1);
                el.textContent = Math.floor(start + range * progress).toLocaleString();
                if (progress < 1) requestAnimationFrame(update);
            }
            requestAnimationFrame(update);
        }
        
        if (document.getElementById('captchaDisplay')) {
            loadDemoCaptcha();
            animateValue('statUsers', 0, 2847, 2000);
            animateValue('statRequests', 0, 154320, 2000);
        }
        
        document.getElementById('captchaInput')?.addEventListener('keypress', e => { if (e.key === 'Enter') verifyDemoCaptcha(); });
        document.getElementById('authModal')?.addEventListener('click', e => { if (e.target.id === 'authModal') hideAuthModal(); });
    </script>
</body>
</html>
