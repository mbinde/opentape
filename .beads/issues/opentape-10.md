# opentape-10: Fix race conditions in file operations

**Type:** bug
**Priority:** 2 (medium)
**Status:** open
**Created:** 2026-01-19

## Description

File existence checks in `code/opentape_common.php` (lines 272-278, 398-419) have potential TOCTOU (time-of-check-time-of-use) race conditions. A file could be modified between the existence check and the actual operation.

## Fix

Use atomic file operations where possible, or use file locking to prevent race conditions.
