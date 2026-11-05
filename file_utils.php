<?php
// Utility helpers for file operations used across the project.
// Keep simple and dependency-free so it can be included from other scripts.

/**
 * Try to unlink a file that may be stored as relative or absolute path.
 * Returns an array: [bool $deleted, ?string $deletedPath]
 */
function try_unlink_upload($path)
{
    if (empty($path))
        return [false, null];

    // Normalize to forward slashes for candidates
    $p = str_replace('\\', '/', $path);
    $candidates = [];

    // Candidate: project-relative path
    $candidates[] = __DIR__ . '/' . ltrim($p, '/');

    // Candidate: with DIRECTORY_SEPARATOR
    $candidates[] = __DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, ltrim($p, '/'));

    // Candidate: raw path (in case stored as absolute)
    $candidates[] = $path;

    foreach ($candidates as $c) {
        if (!$c)
            continue;
        if (file_exists($c) && is_file($c)) {
            // Suppress warnings; check result
            if (@unlink($c)) {
                return [true, $c];
            }
        }
    }

    return [false, null];
}


/**
 * Normalize a text string for storage/display.
 * - Trims whitespace
 * - Attempts to detect common encodings and convert to UTF-8
 * - Removes invalid byte sequences
 *
 * @param string|null $s
 * @return string|null
 */
function normalize_text($s)
{
    if ($s === null)
        return null;
    // Ensure it's a string
    $s = (string) $s;
    $s = trim($s);
    if ($s === '')
        return '';

    // If already valid UTF-8, keep it
    if (mb_check_encoding($s, 'UTF-8')) {
        // Remove any invalid control characters except common ones (tab, newline)
        $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);
        return $s;
    }

    // Try to detect encoding from common sets
    $enc = mb_detect_encoding($s, ['UTF-8', 'ISO-8859-1', 'WINDOWS-1252', 'ASCII'], true);
    if ($enc) {
        $s = mb_convert_encoding($s, 'UTF-8', $enc);
    } else {
        // Fallback to ISO-8859-1 -> UTF-8
        $s = mb_convert_encoding($s, 'UTF-8', 'ISO-8859-1');
    }

    // Drop any remaining invalid bytes
    $s = iconv('UTF-8', 'UTF-8//IGNORE', $s);
    // Remove control chars again
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $s);

    return $s;
}


/**
 * Format an author/name string to proper/title case.
 * - Trims and normalizes encoding via normalize_text() first.
 * - Converts input to Title Case regardless of original capitalization.
 * - Collapses repeated spaces.
 * - Ensures initials ("P.") remain uppercase and letters after apostrophes or hyphens are uppercase
 *   (so "O'donell" -> "O'Donell", "mary-jane" -> "Mary-Jane").
 *
 * @param string|null $s
 * @return string|null
 */
function format_author($s)
{
    if ($s === null)
        return null;
    $s = normalize_text($s);
    if ($s === '')
        return '';

    // Collapse multiple whitespace into single spaces
    $s = preg_replace('/\s+/u', ' ', $s);

    // If there's no alphabetic character, return as-is
    if (!preg_match('/\p{L}/u', $s)) {
        return $s;
    }

    // Always convert to Title Case: lowercase first, then MB_CASE_TITLE
    $lower = mb_strtolower($s, 'UTF-8');
    $title = mb_convert_case($lower, MB_CASE_TITLE, 'UTF-8');

    // Ensure single-letter initials remain uppercase followed by a dot (e.g., "P.")
    $title = preg_replace_callback('/\b([\p{L}])\.(?=\s|$)/u', function ($m) {
        return mb_strtoupper($m[1], 'UTF-8') . '.';
    }, $title);

    // Uppercase letter after apostrophes and hyphens: treat them as separators
    $title = preg_replace_callback("/([\'\-])([\p{L}])/u", function ($m) {
        return $m[1] . mb_strtoupper($m[2], 'UTF-8');
    }, $title);

    return $title;
}

?>