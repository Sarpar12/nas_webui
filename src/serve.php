<?php
/* ----------  auth (IP lock) – no session hold  ---------- */
session_save_path(__DIR__ . "/tmp_sessions");
session_start();
include __DIR__ . "/auth.php";
session_write_close();

/* ----------  validate request  ---------- */
$requested = $_GET["file"] ?? "";

if ($requested === "") {
    http_response_code(400);
    exit("Missing file parameter");
}

/* Strip any path components — only bare filenames allowed */
$requested = basename($requested);

if ($requested === "" || $requested === "." || $requested === "..") {
    http_response_code(400);
    exit("Invalid filename");
}

/* ----------  resolve and confine to uploads/  ---------- */
$uploadsRaw = __DIR__ . "/uploads";

if (!is_dir($uploadsRaw)) {
    error_log("[serve.php] uploads directory does not exist: " . $uploadsRaw);
    http_response_code(500);
    exit("Upload directory not found");
}

$uploadDir = realpath($uploadsRaw);

if ($uploadDir === false) {
    error_log("[serve.php] realpath() failed for uploads dir: " . $uploadsRaw);
    http_response_code(500);
    exit("Upload directory could not be resolved");
}

$targetRaw = $uploadDir . DIRECTORY_SEPARATOR . $requested;

if (!file_exists($targetRaw)) {
    http_response_code(404);
    exit("File not found");
}

$filePath = realpath($targetRaw);

/*
 * Two-part guard:
 *   1. realpath() returns false if the file doesn't exist or the path is bogus.
 *   2. strpos() confirms the resolved path starts with the uploads directory,
 *      so even if someone sneaks in a symlink or encoded traversal it won't escape.
 */
if (
    $filePath === false ||
    strpos($filePath, $uploadDir . DIRECTORY_SEPARATOR) !== 0
) {
    error_log(
        '[serve.php] path traversal blocked: requested="' .
            $requested .
            '" resolved="' .
            ($filePath ?: "false") .
            '" uploadDir="' .
            $uploadDir .
            '"',
    );
    http_response_code(403);
    exit("Access denied");
}

if (!is_file($filePath)) {
    http_response_code(404);
    exit("File not found");
}

/* ----------  detect MIME type  ---------- */
$mime = "application/octet-stream"; // safe default

if (function_exists("finfo_open")) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo !== false) {
        $detected = finfo_file($finfo, $filePath);
        if ($detected !== false && $detected !== "") {
            $mime = $detected;
        }
        finfo_close($finfo);
    } else {
        error_log(
            "[serve.php] finfo_open() returned false — fileinfo extension may be misconfigured",
        );
    }
} else {
    error_log(
        "[serve.php] finfo_open() not available — fileinfo extension is not loaded, falling back to application/octet-stream",
    );
}

/* ----------  serve the file  ---------- */
$size = filesize($filePath);

if ($size === false) {
    error_log("[serve.php] filesize() failed for: " . $filePath);
    http_response_code(500);
    exit("Could not read file size");
}

header("Content-Type: " . $mime);
header("Content-Length: " . $size);
header(
    'Content-Disposition: inline; filename="' . rawurlencode($requested) . '"',
);
header("X-Content-Type-Options: nosniff");
header("Cache-Control: private, max-age=3600");

readfile($filePath);
exit();
