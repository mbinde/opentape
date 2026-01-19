# opentape-4: Remove legacy MD5 password support

**Type:** bug
**Priority:** 0 (critical)
**Status:** closed
**Created:** 2026-01-19

## Description

The code in `code/opentape_common.php` (lines 150-158) maintains legacy support for MD5 hashed passwords with a hardcoded salt. MD5 is cryptographically broken.

Since old versions of opentape no longer work, there's no migration path needed - this code can be removed entirely.

## Fix

Remove the MD5 password check block in `check_password()` function. Only support bcrypt hashes from `password_hash()`.
