# opentape-9: Set secure file permissions on password file

**Type:** bug
**Priority:** 1 (high)
**Status:** closed
**Created:** 2026-01-19

## Description

The password hash file at `settings/.opentape_password.json` may be created with world-readable permissions (0644). While bcrypt hashes are hard to crack, the file should not be readable by other users on the system.

## Fix

When writing the password file in `write_json_file()`, explicitly set permissions to 0600 (owner read/write only) for sensitive files like `.opentape_password.json`.
