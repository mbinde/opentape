<?php
/**
 * Opentape AJAX Handler
 * Handles all admin AJAX requests with CSRF protection
 */

require_once('opentape_common.php');

// Get request data
$command = $_POST['command'] ?? '';
$args = json_decode($_POST['args'] ?? '{}', true);
$csrf_token = $_POST['csrf_token'] ?? '';

// Set JSON response header
header("Content-type: application/json; charset=UTF-8");

// create_password is the exception - user can't be logged in yet
if ($command !== 'create_password') {
    // Require authentication for all other commands
    if (!is_logged_in()) {
        echo json_encode(['status' => false, 'command' => $command, 'error' => 'Authentication required']);
        exit;
    }

    // Validate CSRF token
    if (!validate_csrf_token($csrf_token)) {
        echo json_encode(['status' => false, 'command' => $command, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

// Handle commands
switch ($command) {
    case 'create_password':
        handle_create_password($args);
        break;

    case 'change_password':
        handle_change_password($args, $csrf_token);
        break;

    case 'rename':
        handle_rename($args);
        break;

    case 'reorder':
        handle_reorder($args);
        break;

    case 'delete':
        handle_delete();
        break;

    case 'bannercaptioncolor':
        handle_banner_caption_color();
        break;

    case 'set_option':
        handle_set_option($args);
        break;

    case 'check_updates':
        handle_check_updates();
        break;

    default:
        echo json_encode(['status' => false, 'command' => $command, 'error' => 'Unknown command']);
}

/**
 * Handle initial password creation
 */
function handle_create_password(array $args): void {
    $password1 = $args['password1'] ?? '';
    $password2 = $args['password2'] ?? '';

    // Don't allow setting password if one already exists
    if (is_password_set()) {
        echo json_encode([
            'status' => false,
            'command' => 'create_password',
            'error' => 'Password already configured. Login to change it.'
        ]);
        return;
    }

    if (empty($password1) || $password1 !== $password2) {
        echo json_encode([
            'status' => false,
            'command' => 'create_password',
            'error' => 'Passwords do not match'
        ]);
        return;
    }

    if (!set_password($password1)) {
        echo json_encode(['status' => false, 'command' => 'create_password', 'error' => 'Failed to save password']);
        return;
    }

    if (!create_session()) {
        echo json_encode(['status' => false, 'command' => 'create_password', 'error' => 'Failed to create session']);
        return;
    }

    echo json_encode(['status' => true, 'command' => 'create_password']);
}

/**
 * Handle password change
 */
function handle_change_password(array $args, string $csrf_token): void {
    // Password change also needs CSRF validation
    if (!validate_csrf_token($csrf_token)) {
        echo json_encode(['status' => false, 'command' => 'change_password', 'error' => 'Invalid CSRF token']);
        return;
    }

    $password1 = $args['password1'] ?? '';
    $password2 = $args['password2'] ?? '';

    if (empty($password1) || $password1 !== $password2) {
        echo json_encode([
            'status' => false,
            'command' => 'change_password',
            'error' => 'Passwords do not match'
        ]);
        return;
    }

    if (set_password($password1)) {
        echo json_encode(['status' => true, 'command' => 'change_password']);
    } else {
        echo json_encode(['status' => false, 'command' => 'change_password', 'error' => 'Failed to save password']);
    }
}

/**
 * Handle song rename
 */
function handle_rename(array $args): void {
    $song_key = $args['song_key'] ?? '';
    $artist = htmlspecialchars($_POST['artist'] ?? '', ENT_QUOTES, 'UTF-8');
    $title = htmlspecialchars($_POST['title'] ?? '', ENT_QUOTES, 'UTF-8');

    if (!validate_song_key($song_key)) {
        echo json_encode(['status' => false, 'command' => 'rename', 'error' => 'Invalid song key']);
        return;
    }

    if (rename_song($song_key, $artist, $title)) {
        echo json_encode([
            'status' => true,
            'command' => 'rename',
            'args' => [
                'song_key' => $song_key,
                'artist' => $artist,
                'title' => $title
            ]
        ]);
    } else {
        echo json_encode(['status' => false, 'command' => 'rename', 'error' => 'Failed to rename song']);
    }
}

/**
 * Handle playlist reorder
 */
function handle_reorder(array $args): void {
    if (empty($args) || !is_array($args)) {
        echo json_encode(['status' => false, 'command' => 'reorder', 'error' => 'Invalid order data']);
        return;
    }

    if (reorder_songs($args)) {
        echo json_encode(['status' => true, 'command' => 'reorder']);
    } else {
        echo json_encode(['status' => false, 'command' => 'reorder', 'error' => 'Failed to reorder songs']);
    }
}

/**
 * Handle song deletion
 */
function handle_delete(): void {
    $song_key = $_POST['args'] ?? '';

    if (!validate_song_key($song_key)) {
        echo json_encode(['status' => false, 'command' => 'delete', 'error' => 'Invalid song key']);
        return;
    }

    if (delete_song($song_key)) {
        echo json_encode(['status' => true, 'command' => 'delete', 'args' => $song_key]);
    } else {
        echo json_encode(['status' => false, 'command' => 'delete', 'error' => 'Failed to delete song']);
    }
}

/**
 * Handle banner/caption/color update
 */
function handle_banner_caption_color(): void {
    $prefs_struct = get_opentape_prefs();

    $prefs_struct['banner'] = htmlspecialchars($_POST['banner'] ?? '', ENT_QUOTES, 'UTF-8');
    $prefs_struct['caption'] = htmlspecialchars($_POST['caption'] ?? '', ENT_QUOTES, 'UTF-8');

    // Sanitize color to only allow hex characters
    $color = preg_replace('/[^a-fA-F0-9]/', '', $_POST['color'] ?? '');
    if (strlen($color) === 6 || strlen($color) === 3) {
        $prefs_struct['color'] = $color;
    }

    if (write_opentape_prefs($prefs_struct)) {
        echo json_encode(['status' => true, 'command' => 'bannercaptioncolor']);
    } else {
        echo json_encode(['status' => false, 'command' => 'bannercaptioncolor', 'error' => 'Failed to save preferences']);
    }
}

/**
 * Handle setting options
 */
function handle_set_option(array $args): void {
    if (empty($args) || !is_array($args)) {
        echo json_encode(['status' => false, 'command' => 'set_option', 'error' => 'Invalid option data']);
        return;
    }

    $prefs_struct = get_opentape_prefs();

    foreach ($args as $key => $data) {
        // Sanitize key to alphanumeric and underscore only
        $key = preg_replace('/[^a-zA-Z0-9_]/', '', $key);

        if ($data === 'on' || $data === 'true' || $data === true || $data === 1) {
            $prefs_struct[$key] = 1;
        } else {
            $prefs_struct[$key] = 0;
        }
    }

    if (write_opentape_prefs($prefs_struct)) {
        echo json_encode(['status' => true, 'command' => 'set_option']);
    } else {
        echo json_encode(['status' => false, 'command' => 'set_option', 'error' => 'Failed to save preferences']);
    }
}

/**
 * Handle update check
 */
function handle_check_updates(): void {
    $update_info = check_for_updates();

    if ($update_info === null) {
        echo json_encode(['status' => false, 'command' => 'check_updates', 'error' => 'Could not check for updates']);
        return;
    }

    echo json_encode([
        'status' => true,
        'command' => 'check_updates',
        'update_info' => $update_info
    ]);
}
