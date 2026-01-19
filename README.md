# Opentape

A simple, self-hosted web application for creating and sharing mixtapes.

## Requirements

- PHP 8.0 or higher
- Web server (Apache, nginx, etc.)
- Writable `songs/` and `settings/` directories

## Installation

1. Upload all files and folders to a directory on your web server
2. Ensure `songs/` and `settings/` directories are writable by the web server
3. Visit your installation URL (e.g., `https://yoursite.com/opentape/`)
4. Create an admin password when prompted
5. Start uploading MP3s!

## Features

- Upload and organize MP3 files into playlists
- Automatic ID3 tag reading for artist/title info
- Drag-and-drop reordering
- Customizable banner, caption, and color
- Crossfade between tracks
- RSS feed for your mixtape
- No database required - all data stored in JSON files

## Directory Structure

```
opentape/
├── index.php          # Entry point
├── code/              # PHP application files
│   ├── getid3/        # ID3 tag parsing library
│   └── *.php          # Application logic
├── res/               # CSS and JavaScript
├── settings/          # Configuration storage (auto-created)
└── songs/             # Uploaded MP3 files
```

## Security Notes

- Uses PHP's native `password_hash()` for secure password storage
- CSRF protection on all admin actions
- Session management with secure cookie settings
- All user input is properly sanitized

## Changelog

### 1.0.0

Complete modernization for PHP 8.x and modern browsers:

- Replaced MooTools with vanilla JavaScript
- Replaced SoundManager 2 with native HTML5 Audio
- Updated to latest getID3 library
- Switched from serialized PHP to JSON file storage
- Added CSRF protection
- Upgraded password hashing from MD5 to bcrypt
- Native PHP session management
- HTML5 doctype and modern markup
- Removed dead opentape.fm integration

### 0.13

- Removed Flash support
- Updates to PHP ID3 libs
- Minor fixes for PHP 7/8

## License

Opentape is licensed under the GNU Affero GPL v3. See the LICENSE file for details.

### Third-party code

- getID3 - GNU GPL: https://github.com/JamesHeinrich/getID3
