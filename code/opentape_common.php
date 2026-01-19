<?php
/**
 * Opentape Common Functions
 * Modernized for PHP 8.x
 */

// --- CONFIGURABLE ADVANCED SETTINGS
define("SETTINGS_PATH", "settings/");
define("SONGS_PATH", "songs/");
define("DEFAULT_COLOR", "EC660F");
define("VERSION", "1.0.1");
define("GITHUB_REPO", "mbinde/opentape");
define("GITHUB_API_URL", "https://api.github.com/repos/" . GITHUB_REPO . "/releases/latest");
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

// Auto-create directories and security files
ensure_directory_setup();

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

// Legacy salt used by original Opentape (pre-2024)
define('LEGACY_PASSWORD_SALT', 'MIXTAPESFORLIFE');

/**
 * Check if password has been set up (new JSON or legacy PHP format)
 */
function is_password_set(): bool {
    // Check new format first
    $password_data = read_json_file('.opentape_password');
    if (is_array($password_data) && !empty($password_data['hash'])) {
        return true;
    }

    // Check legacy format
    $legacy_data = get_legacy_password_struct();
    return is_array($legacy_data) && !empty($legacy_data['hash']);
}

/**
 * Read legacy password structure from old .opentape_password.php file
 */
function get_legacy_password_struct(): ?array {
    $php_path = SETTINGS_PATH . '.opentape_password.php';

    if (file_exists($php_path) && is_readable($php_path)) {
        // Include the file to get $password_struct_data variable
        include($php_path);
        if (isset($password_struct_data)) {
            $data = @unserialize(base64_decode($password_struct_data));
            if (is_array($data) && !empty($data['hash'])) {
                return $data;
            }
        }
    }

    // Also check even older .array format
    $array_path = SETTINGS_PATH . '.opentape_password.array';
    if (file_exists($array_path) && is_readable($array_path)) {
        $data = @unserialize(file_get_contents($array_path));
        if (is_array($data) && !empty($data['hash'])) {
            return $data;
        }
    }

    return null;
}

/**
 * Verify a password against stored hash (supports legacy MD5 with auto-upgrade)
 */
function check_password(string $password): bool {
    // Try new format first
    $password_data = read_json_file('.opentape_password');

    if (is_array($password_data) && !empty($password_data['hash'])) {
        return password_verify($password, $password_data['hash']);
    }

    // Try legacy format
    $legacy_data = get_legacy_password_struct();
    if (is_array($legacy_data) && !empty($legacy_data['hash'])) {
        // Legacy used: md5(SALT . password)
        $legacy_hash = md5(LEGACY_PASSWORD_SALT . $password);

        if (hash_equals($legacy_data['hash'], $legacy_hash)) {
            // Password correct - upgrade to bcrypt
            upgrade_legacy_password($password);
            return true;
        }
    }

    return false;
}

/**
 * Upgrade a legacy MD5 password to bcrypt
 */
function upgrade_legacy_password(string $password): bool {
    // Save with new secure hash
    $result = set_password($password);

    if ($result) {
        // Optionally remove legacy files (keep them for now as backup)
        error_log("Opentape: Upgraded legacy MD5 password to bcrypt");
    }

    return $result;
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
// JSON FILE STORAGE
// ============================================================================

/**
 * Read data from a JSON file in settings directory
 */
function read_json_file(string $filename_base): array {
    $json_path = SETTINGS_PATH . $filename_base . '.json';

    if (file_exists($json_path) && is_readable($json_path)) {
        $content = file_get_contents($json_path);
        $data = json_decode($content, true);
        return is_array($data) ? $data : [];
    }

    return [];
}

/**
 * Read legacy PHP-serialized data from old .opentape_*.php or .array files
 * Returns the data array, or null if not found
 */
function read_legacy_php_file(string $filename_base, string $var_name): ?array {
    // Try .php format first (base64-encoded serialized data in a PHP variable)
    $php_path = SETTINGS_PATH . $filename_base . '.php';
    if (file_exists($php_path) && is_readable($php_path)) {
        include($php_path);
        if (isset($$var_name)) {
            $data = @unserialize(base64_decode($$var_name));
            if (is_array($data)) {
                return $data;
            }
        }
    }

    // Try older .array format (plain serialized data)
    $array_path = SETTINGS_PATH . $filename_base . '.array';
    if (file_exists($array_path) && is_readable($array_path)) {
        $data = @unserialize(file_get_contents($array_path));
        if (is_array($data)) {
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

    // Set restrictive permissions for sensitive files (password, etc.)
    if ($bytes !== false && str_starts_with($filename_base, '.opentape_password')) {
        chmod($json_path, 0600);
    }

    return $bytes !== false;
}

// ============================================================================
// SONGLIST MANAGEMENT
// ============================================================================

/**
 * Validate a song key
 * Returns true if the key is valid base64, decodes to a reasonable filename,
 * and exists in the current songlist
 */
function validate_song_key(string $song_key): bool {
    // Must not be empty
    if (empty($song_key)) {
        return false;
    }

    // Must be valid base64
    if (!preg_match('/^[A-Za-z0-9+\/=]+$/', $song_key)) {
        return false;
    }

    // Decode and check for path traversal
    $decoded = base64_decode($song_key, true);
    if ($decoded === false) {
        return false;
    }

    $filename = rawurldecode($decoded);

    // Check for path traversal attempts
    if (strpos($filename, '..') !== false || strpos($filename, '/') !== false || strpos($filename, '\\') !== false) {
        return false;
    }

    // Must exist in current songlist
    $songlist = get_songlist_struct();
    return isset($songlist[$song_key]);
}

/**
 * Get the songlist structure (supports legacy PHP format with auto-upgrade)
 */
function get_songlist_struct(): array {
    // Try new JSON format first
    $data = read_json_file('.opentape_songlist');
    if (!empty($data)) {
        return $data;
    }

    // Try legacy PHP format
    $legacy_data = read_legacy_php_file('.opentape_songlist', 'songlist_struct_data');
    if (is_array($legacy_data) && !empty($legacy_data)) {
        // Auto-upgrade to JSON format
        error_log("Opentape: Upgrading legacy songlist to JSON format");
        write_json_file('.opentape_songlist', $legacy_data);
        return $legacy_data;
    }

    return [];
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

    $result = write_json_file('.opentape_songlist', $songlist_struct);

    // Also write human-readable text version
    if ($result) {
        write_songlist_txt($songlist_struct);
    }

    return $result;
}

/**
 * Write human-readable text version of songlist
 */
function write_songlist_txt(array $songlist_struct): void {
    $prefs = get_opentape_prefs();

    $lines = [];
    $lines[] = $prefs['banner'] ?? 'Untitled Mixtape';
    $lines[] = $prefs['caption'] ?? '';
    $lines[] = '';

    foreach ($songlist_struct as $row) {
        $artist = $row['opentape_artist'] ?? $row['artist'] ?? '';
        $title = $row['opentape_title'] ?? $row['title'] ?? '';

        if ($artist && $title) {
            $lines[] = $artist . ' - ' . $title;
        } elseif ($title) {
            $lines[] = $title;
        } elseif ($artist) {
            $lines[] = $artist;
        }
    }

    $txt_path = SETTINGS_PATH . 'songlist.txt';
    @file_put_contents($txt_path, implode("\n", $lines) . "\n");
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
    $prefs = get_opentape_prefs();
    $use_filename = !empty($prefs['use_filename']);

    while (false !== ($file = readdir($dir_handle))) {
        if ($file === "." || $file === ".." || !preg_match('/\.mp3$/i', $file)) {
            continue;
        }

        $key = base64_encode(rawurlencode($file));

        if (!isset($songlist_struct[$key])) {
            $id3_info = $getID3->analyze(SONGS_PATH . $file);

            $song_item = [];

            if ($use_filename) {
                // Use filename as title, ignore ID3 tags
                $song_item['artist'] = '';
                $song_item['title'] = preg_replace('/\.mp3$/i', '', $file);
            } else {
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
                    $song_item['artist'] = '';
                    $song_item['title'] = preg_replace('/\.mp3$/i', '', $file);
                } elseif (empty($song_item['artist'])) {
                    $song_item['artist'] = 'Unknown artist';
                } elseif (empty($song_item['title'])) {
                    $song_item['title'] = 'Unknown track';
                }
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
        $songlist_struct = array_merge($songlist_struct, $songlist_new_items);
        write_songlist_struct($songlist_struct);
    }

    return $songlist_struct;
}

/**
 * Rescan all songs, preserving manual edits (opentape_artist/opentape_title)
 */
function rescan_songs(): array {
    require_once('getid3/getid3.php');

    $dir_handle = opendir(SONGS_PATH);
    if (!$dir_handle) {
        return [];
    }

    // Get existing songlist to preserve manual edits
    $old_songlist = get_songlist_struct();

    $getID3 = new getID3();
    $songlist_new = [];
    $prefs = get_opentape_prefs();
    $use_filename = !empty($prefs['use_filename']);

    while (false !== ($file = readdir($dir_handle))) {
        if ($file === "." || $file === ".." || !preg_match('/\.mp3$/i', $file)) {
            continue;
        }

        $key = base64_encode(rawurlencode($file));
        $id3_info = $getID3->analyze(SONGS_PATH . $file);

        $song_item = [];

        if ($use_filename) {
            $song_item['artist'] = '';
            $song_item['title'] = preg_replace('/\.mp3$/i', '', $file);
        } else {
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

            if (empty($song_item['artist']) && empty($song_item['title'])) {
                $song_item['artist'] = '';
                $song_item['title'] = preg_replace('/\.mp3$/i', '', $file);
            } elseif (empty($song_item['artist'])) {
                $song_item['artist'] = 'Unknown artist';
            } elseif (empty($song_item['title'])) {
                $song_item['title'] = 'Unknown track';
            }
        }

        $song_item['filename'] = $id3_info['filename'] ?? $file;
        $song_item['playtime_seconds'] = $id3_info['playtime_seconds'] ?? 0;
        $song_item['playtime_string'] = $id3_info['playtime_string'] ?? '0:00';
        $song_item['mtime'] = filemtime(SONGS_PATH . $file);
        $song_item['size'] = filesize(SONGS_PATH . $file);

        // Preserve manual edits from old songlist
        if (isset($old_songlist[$key])) {
            if (!empty($old_songlist[$key]['opentape_artist'])) {
                $song_item['opentape_artist'] = $old_songlist[$key]['opentape_artist'];
            }
            if (!empty($old_songlist[$key]['opentape_title'])) {
                $song_item['opentape_title'] = $old_songlist[$key]['opentape_title'];
            }
        }

        $songlist_new[$key] = $song_item;
    }

    closedir($dir_handle);

    // Preserve order from old songlist where possible
    $songlist_ordered = [];
    foreach ($old_songlist as $key => $value) {
        if (isset($songlist_new[$key])) {
            $songlist_ordered[$key] = $songlist_new[$key];
            unset($songlist_new[$key]);
        }
    }
    // Add any new songs at the end
    $songlist_ordered = array_merge($songlist_ordered, $songlist_new);

    write_songlist_struct($songlist_ordered);
    return $songlist_ordered;
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
 * Get user preferences (supports legacy PHP format with auto-upgrade)
 */
function get_opentape_prefs(): array {
    // Try new JSON format first
    $data = read_json_file('.opentape_prefs');
    if (!empty($data)) {
        return $data;
    }

    // Try legacy PHP format
    $legacy_data = read_legacy_php_file('.opentape_prefs', 'prefs_struct_data');
    if (is_array($legacy_data) && !empty($legacy_data)) {
        // Auto-upgrade to JSON format
        error_log("Opentape: Upgrading legacy prefs to JSON format");
        write_json_file('.opentape_prefs', $legacy_data);
        return $legacy_data;
    }

    return [];
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
    header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline'; media-src 'self'; img-src 'self' https://api.github.com; script-src 'self' 'unsafe-inline'; frame-ancestors 'self'; connect-src 'self' https://api.github.com");
}

// ============================================================================
// USERDATA SETUP
// ============================================================================

/**
 * Ensure userdata directories and security files exist
 * Returns array of errors, empty if all OK
 */
function ensure_directory_setup(): array {
    $errors = [];

    // Create settings directory
    if (!is_dir(SETTINGS_PATH)) {
        if (!@mkdir(SETTINGS_PATH, 0755, true)) {
            $errors[] = 'Could not create ' . SETTINGS_PATH . ' directory';
        }
    }

    // Create and verify settings/.htaccess (blocks direct access)
    $htaccess_file = SETTINGS_PATH . '.htaccess';
    $htaccess_content = "# Deny direct access to settings files\n<IfModule mod_authz_core.c>\nRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\nOrder deny,allow\nDeny from all\n</IfModule>\n";
    if (is_dir(SETTINGS_PATH) && !file_exists($htaccess_file)) {
        @file_put_contents($htaccess_file, $htaccess_content);
    }

    // Create songs directory
    if (!is_dir(SONGS_PATH)) {
        if (!@mkdir(SONGS_PATH, 0755, true)) {
            $errors[] = 'Could not create ' . SONGS_PATH . ' directory';
        }
    }

    return $errors;
}

/**
 * Get detailed setup status for diagnostics
 */
function get_setup_status(): array {
    $status = [
        'settings_dir' => is_dir(SETTINGS_PATH),
        'settings_writable' => is_dir(SETTINGS_PATH) && is_writable(SETTINGS_PATH),
        'settings_htaccess' => file_exists(SETTINGS_PATH . '.htaccess'),
        'songs_dir' => is_dir(SONGS_PATH),
        'songs_writable' => is_dir(SONGS_PATH) && is_writable(SONGS_PATH),
    ];

    $status['all_ok'] = $status['settings_writable']
        && $status['songs_writable']
        && $status['settings_htaccess'];

    return $status;
}

// ============================================================================
// UPDATE CHECKER
// ============================================================================

/**
 * Check for updates from GitHub releases
 * Returns array with update info or null on error
 */
function check_for_updates(): ?array {
    $ctx = stream_context_create([
        'http' => [
            'method' => 'GET',
            'header' => "User-Agent: Opentape/" . VERSION . "\r\n",
            'timeout' => 10
        ]
    ]);

    $response = @file_get_contents(GITHUB_API_URL, false, $ctx);
    if ($response === false) {
        return null;
    }

    $release = json_decode($response, true);
    if (!is_array($release) || empty($release['tag_name'])) {
        return null;
    }

    // Parse version (remove 'v' prefix if present)
    $latest_version = ltrim($release['tag_name'], 'v');
    $current_version = VERSION;

    return [
        'current_version' => $current_version,
        'latest_version' => $latest_version,
        'update_available' => version_compare($latest_version, $current_version, '>'),
        'release_name' => $release['name'] ?? $release['tag_name'],
        'release_notes' => $release['body'] ?? '',
        'release_url' => $release['html_url'] ?? ('https://github.com/' . GITHUB_REPO . '/releases'),
        'download_url' => $release['zipball_url'] ?? '',
        'published_at' => $release['published_at'] ?? ''
    ];
}

