<?php
// Path to lock file
$lock_file = __DIR__ . '/tmp_sessions/lock';

// Maximum age in seconds (e.g., 30 minutes)
$max_age = 30 * 60;

// Check if lock exists
if (!file_exists($lock_file)) {
    header("Location: login.php");
    exit();
}

// Read lock file contents
list($lock_ip, $lock_ua_hash) = explode('|', file_get_contents($lock_file));

// Get current visitor
$current_ip = $_SERVER['REMOTE_ADDR'];
$current_ua_hash = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');

// Verify IP + User Agent
if ($current_ip !== $lock_ip || $current_ua_hash !== $lock_ua_hash) {
    unlink($lock_file); // Optional: remove invalid lock
    header("Location: login.php");
    exit();
}

// Check for expiration
if (time() - filemtime($lock_file) > $max_age) {
    unlink($lock_file); // auto-delete expired lock
    header("Location: login.php");
    exit();
}

// Update timestamp to extend session (sliding expiration)
touch($lock_file);
