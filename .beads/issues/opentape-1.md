# opentape-1: Fix XSS vulnerabilities in RSS feed

**Type:** bug
**Priority:** 0 (critical)
**Status:** closed
**Created:** 2026-01-19

## Description

User-controlled banner and artist/title data is output directly into XML without escaping in `code/rss.php`.

**Affected lines:**
- Line 22: `echo $prefs_struct['banner'];`
- Line 48: `echo $row['opentape_artist'] . ' - ';`
- Line 51: `echo $row['opentape_title'];`

An attacker could inject malicious XML or JavaScript that would be interpreted by RSS readers.

## Fix

Use `htmlspecialchars()` with `ENT_XML1` flag for all user-controlled output in the RSS feed.
