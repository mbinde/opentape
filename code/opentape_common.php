<?php
/**
 * Opentape Common Functions
 * Modernized for PHP 8.x
 */

// --- CONFIGURABLE ADVANCED SETTINGS
define("SETTINGS_PATH", "settings/");
define("SONGS_PATH", "songs/");
define("DEFAULT_COLOR", "EC660F");
define("VERSION", "1.0.0");
// --- END OF CONFIGURABLE ADVANCED SETTINGS ---- //

// Calculate relative path from current URL
global $REL_PATH;
$REL_PATH = preg_replace('|settings/[^/]*?$|', '', $_SERVER['REQUEST_URI']);
$REL_PATH = preg_replace('|songs/[^/]*?$|', '', $REL_PATH);
$REL_PATH = preg_replace('|code/[^/]*?$|', '', $REL_PATH);
$REL_PATH = preg_replace('|res/[^/]*?$|', '', $REL_PATH);
$REL_PATH = preg_replace('|/[^/]+?$|', '/', $REL_PATH);
$REL_PATH = preg_replace('|/+|', '/', $REL_PATH);

// Helper for getID3 on Windows
define("GETID3_HELPERAPPSDIR", "/");

// Change dir to the main install dir for consistency
$cwd = getcwd();
if (preg_match('/code\/?$/', $cwd) || preg_match('|' . SETTINGS_PATH . '?$|', $cwd) || preg_match('/res\/?$/', $cwd)) {
    chdir('..');
}

// ============================================================================
// SESSION MANAGEMENT (using PHP native sessions)
// ============================================================================

/**
 * Initialize session with secure settings
 */
function init_session(): void {
    if (session_status() === PHP_SESSION_NONE) {
        // Set secure session cookie parameters
        session_set_cookie_params([
            'lifetime' => 86400 * 30, // 30 days
            'path' => '/',
            'domain' => '',
            'secure' => is_https(),
            'httponly' => true,
            'samesite' => 'Strict'
        ]);
        session_start();
    }
}

/**
 * Check if user is logged in
 */
function is_logged_in(): bool {
    init_session();
    return !empty($_SESSION['opentape_authenticated']) && $_SESSION['opentape_authenticated'] === true;
}

/**
 * Create a new authenticated session
 */
function create_session(): bool {
    init_session();
    // Regenerate session ID to prevent fixation attacks
    session_regenerate_id(true);
    $_SESSION['opentape_authenticated'] = true;
    $_SESSION['opentape_login_time'] = time();
    return true;
}

/**
 * Destroy the current session (logout)
 */
function remove_session(): bool {
    init_session();
    $_SESSION = [];

    // Delete session cookie
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    return true;
}

// ============================================================================
// CSRF PROTECTION
// ============================================================================

/**
 * Generate or retrieve CSRF token
 */
function get_csrf_token(): string {
    init_session();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validate_csrf_token(?string $token): bool {
    init_session();
    if (empty($token) || empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Output hidden CSRF input field for forms
 */
function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(get_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

// ============================================================================
// PASSWORD MANAGEMENT (using password_hash/password_verify)
// ============================================================================

/**
 * Check if password has been set up
 */
function is_password_set(): bool {
    $password_data = read_json_file('.opentape_password');
    return is_array($password_data) && !empty($password_data['hash']);
}

/**
 * Verify a password against stored hash
 * Also handles migration from old MD5 hashes
 */
function check_password(string $password): bool {
    $password_data = read_json_file('.opentape_password');

    if (!is_array($password_data) || empty($password_data['hash'])) {
        return false;
    }

    // Check if this is an old MD5 hash (32 chars) or new password_hash (60+ chars)
    if (strlen($password_data['hash']) === 32) {
        // Old MD5 format: md5("MIXTAPESFORLIFE" . $password)
        if (md5("MIXTAPESFORLIFE" . $password) === $password_data['hash']) {
            // Migrate to new hash format on successful login
            set_password($password);
            return true;
        }
        return false;
    }

    // New password_hash format
    return password_verify($password, $password_data['hash']);
}

/**
 * Set a new password
 */
function set_password(string $password): bool {
    $password_data = [
        'hash' => password_hash($password, PASSWORD_DEFAULT),
        'updated' => time()
    ];
    return write_json_file('.opentape_password', $password_data);
}

// ============================================================================
// JSON FILE STORAGE (replacing serialized PHP)
// ============================================================================

/**
 * Read data from a JSON file in settings directory
 * Handles migration from old serialized PHP format
 */
function read_json_file(string $filename_base): array {
    $json_path = SETTINGS_PATH . $filename_base . '.json';
    $php_path = SETTINGS_PATH . $filename_base . '.php';
    $array_path = SETTINGS_PATH . $filename_base . '.array';

    // Try JSON file first (new format)
    if (file_exists($json_path) && is_readable($json_path)) {
        $content = file_get_contents($json_path);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    // Try old PHP format and migrate
    if (file_exists($php_path) && is_readable($php_path)) {
        $data = migrate_php_storage($php_path, $filename_base);
        if ($data !== null) {
            return $data;
        }
    }

    // Try old .array format and migrate
    if (file_exists($array_path) && is_readable($array_path)) {
        $content = file_get_contents($array_path);
        $data = @unserialize($content);
        if (is_array($data)) {
            // Migrate to JSON
            write_json_file($filename_base, $data);
            return $data;
        }
    }

    return [];
}

/**
 * Migrate old PHP serialized storage to JSON
 */
function migrate_php_storage(string $php_path, string $filename_base): ?array {
    $content = file_get_contents($php_path);

    // Extract the base64-encoded serialized data
    // Format: <?php $varname_data = "base64string"; ? >
    if (preg_match('/\$\w+_data\s*=\s*"([^"]+)"/', $content, $matches)) {
        $decoded = base64_decode($matches[1]);
        $data = @unserialize($decoded);

        if (is_array($data)) {
            // Write to new JSON format
            write_json_file($filename_base, $data);
            return $data;
        }
    }

    return null;
}

/**
 * Write data to a JSON file in settings directory
 */
function write_json_file(string $filename_base, array $data): bool {
    $json_path = SETTINGS_PATH . $filename_base . '.json';
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

    if ($json === false) {
        error_log("Failed to encode JSON for $filename_base");
        return false;
    }

    $bytes = file_put_contents($json_path, $json, LOCK_EX);
    return $bytes !== false;
}

// ============================================================================
// SONGLIST MANAGEMENT
// ============================================================================

/**
 * Get the songlist structure
 */
function get_songlist_struct(): array {
    return read_json_file('.opentape_songlist');
}

/**
 * Write the songlist structure
 * Validates that all files still exist before saving
 */
function write_songlist_struct(array $songlist_struct): bool {
    // Remove entries for files that no longer exist
    foreach ($songlist_struct as $pos => $row) {
        if (!is_file(SONGS_PATH . ($row['filename'] ?? ''))) {
            error_log(($row['filename'] ?? 'unknown') . " is not accessible, removing from songlist");
            unset($songlist_struct[$pos]);
        }
    }

    return write_json_file('.opentape_songlist', $songlist_struct);
}

/**
 * Scan songs directory and update songlist with new files
 */
function scan_songs(): array {
    require_once('getid3/getid3.php');

    $dir_handle = opendir(SONGS_PATH);
    if (!$dir_handle) {
        return [];
    }

    $getID3 = new getID3();
    $songlist_struct = get_songlist_struct();
    $songlist_new_items = [];

    while (false !== ($file = readdir($dir_handle))) {
        if ($file === "." || $file === ".." || !preg_match('/\.mp3$/i', $file)) {
            continue;
        }

        $key = base64_encode(rawurlencode($file));

        if (!isset($songlist_struct[$key])) {
            $id3_info = $getID3->analyze(SONGS_PATH . $file);

            $song_item = [];

            // Check ID3v2 tags first, then ID3v1
            $song_item['artist'] = clean_titles(
                $id3_info['id3v2']['comments']['artist'][0] ??
                $id3_info['id3v1']['artist'] ??
                ''
            );

            $song_item['title'] = clean_titles(
                $id3_info['id3v2']['comments']['title'][0] ??
                $id3_info['id3v1']['title'] ??
                ''
            );

            // If missing tags, use filename
            if (empty($song_item['artist']) && empty($song_item['title'])) {
                $song_item['artist'] = "";
                $song_item['title'] = preg_replace('/\.mp3$/i', '', $file);
            } elseif (empty($song_item['artist'])) {
                $song_item['artist'] = "Unknown artist";
            } elseif (empty($song_item['title'])) {
                $song_item['title'] = "Unknown track";
            }

            $song_item['filename'] = $id3_info['filename'] ?? $file;
            $song_item['playtime_seconds'] = $id3_info['playtime_seconds'] ?? 0;
            $song_item['playtime_string'] = $id3_info['playtime_string'] ?? '0:00';
            $song_item['mtime'] = filemtime(SONGS_PATH . $file);
            $song_item['size'] = filesize(SONGS_PATH . $file);

            $songlist_new_items[$key] = $song_item;
        }
    }

    closedir($dir_handle);

    if (!empty($songlist_new_items)) {
        $songlist_struct = array_merge($songlist_new_items, $songlist_struct);
        write_songlist_struct($songlist_struct);
    }

    return $songlist_struct;
}

/**
 * Rename a song's display artist/title
 */
function rename_song(string $song_key, string $artist, string $title): bool {
    if (empty($song_key)) {
        error_log("rename_song called with empty song_key");
        return false;
    }

    $songlist_struct = get_songlist_struct();

    if (!isset($songlist_struct[$song_key])) {
        return false;
    }

    $songlist_struct[$song_key]['opentape_artist'] = $artist;
    $songlist_struct[$song_key]['opentape_title'] = $title;

    return write_songlist_struct($songlist_struct);
}

/**
 * Reorder songs in the playlist
 */
function reorder_songs(array $args): bool {
    if (empty($args)) {
        error_log("reorder_songs called with empty args");
        return false;
    }

    $songlist_struct = get_songlist_struct();
    $songlist_struct_new = [];

    foreach ($args as $row) {
        if (isset($songlist_struct[$row])) {
            $songlist_struct_new[$row] = $songlist_struct[$row];
        }
    }

    return write_songlist_struct($songlist_struct_new);
}

/**
 * Delete a song from disk and songlist
 */
function delete_song(string $song_key): bool {
    if (empty($song_key)) {
        error_log("delete_song called with empty song_key");
        return false;
    }

    $songlist_struct = get_songlist_struct();

    if (!isset($songlist_struct[$song_key])) {
        return false;
    }

    $filepath = SONGS_PATH . $songlist_struct[$song_key]['filename'];

    if (file_exists($filepath) && !unlink($filepath)) {
        return false;
    }

    unset($songlist_struct[$song_key]);

    return write_songlist_struct($songlist_struct);
}

// ============================================================================
// PREFERENCES MANAGEMENT
// ============================================================================

/**
 * Get user preferences
 */
function get_opentape_prefs(): array {
    return read_json_file('.opentape_prefs');
}

/**
 * Write user preferences
 */
function write_opentape_prefs(array $prefs_struct): bool {
    return write_json_file('.opentape_prefs', $prefs_struct);
}

// ============================================================================
// UTILITY FUNCTIONS
// ============================================================================

/**
 * Check if current request is HTTPS
 */
function is_https(): bool {
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        return true;
    }
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
        return true;
    }
    if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443) {
        return true;
    }
    return false;
}

/**
 * Get base URL for the application
 */
function get_base_url(): string {
    global $REL_PATH;
    $protocol = is_https() ? 'https' : 'http';
    return $protocol . '://' . $_SERVER['HTTP_HOST'] . $REL_PATH;
}

/**
 * Convert php.ini size notation to bytes
 */
function let_to_num(string $v): int {
    $v = trim($v);
    $last = strtoupper(substr($v, -1));
    $value = (int)$v;

    switch ($last) {
        case 'P': $value *= 1024;
        case 'T': $value *= 1024;
        case 'G': $value *= 1024;
        case 'M': $value *= 1024;
        case 'K': $value *= 1024;
    }

    return $value;
}

/**
 * Get maximum upload size in MB
 */
function get_max_upload_mb(): float {
    $max_upload = min(
        let_to_num(ini_get('post_max_size')),
        let_to_num(ini_get('upload_max_filesize'))
    );
    return round($max_upload / (1024 * 1024), 2);
}

/**
 * Get maximum upload size in bytes
 */
function get_max_upload_bytes(): int {
    return min(
        let_to_num(ini_get('post_max_size')),
        let_to_num(ini_get('upload_max_filesize'))
    );
}

/**
 * Get total runtime of all songs in seconds
 */
function get_total_runtime_seconds(): int {
    $songlist = get_songlist_struct();
    $total = 0;

    foreach ($songlist as $row) {
        $total += (int)($row['playtime_seconds'] ?? 0);
    }

    return $total;
}

/**
 * Get formatted runtime string
 */
function get_total_runtime_string(): string {
    $seconds = get_total_runtime_seconds();
    $mins = (int)round($seconds / 60);
    $secs = $seconds % 60;

    $str = $mins === 1 ? "$mins min" : "$mins mins";
    $str .= $secs === 1 ? " $secs sec" : " $secs secs";

    return $str;
}

/**
 * Clean null characters from ID3 tag strings
 */
function clean_titles(string $string): string {
    return str_replace("\0", '', $string);
}

/**
 * Escape string for use in HTML input value attributes
 */
function escape_for_inputs(string $string): string {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get current version
 */
function get_version(): string {
    return VERSION;
}

/**
 * Output version banner HTML
 */
function get_version_banner(): void {
    echo 'This is Opentape ' . htmlspecialchars(VERSION, ENT_QUOTES, 'UTF-8') . '.';
}

/**
 * Send security headers
 */
function send_security_headers(): void {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

