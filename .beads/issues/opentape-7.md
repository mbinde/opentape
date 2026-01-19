# opentape-7: Remove unsafe unserialize() calls

**Type:** bug
**Priority:** 1 (high)
**Status:** closed
**Created:** 2026-01-19

## Description

The migration code in `code/opentape_common.php` (lines 207, 228) uses `@unserialize()` on file contents. While intended for migrating old format data, `unserialize()` on untrusted data can lead to object injection vulnerabilities.

## Fix

Since old opentape versions don't work anyway, remove the migration code entirely. Only support JSON format.
