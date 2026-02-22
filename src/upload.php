<?php
/* ----------  auth (IP lock) – no session hold  ---------- */
session_save_path(__DIR__ . "/tmp_sessions");
session_start();
include __DIR__ . "/auth.php"; // your existing IP-based gate
session_write_close(); // release lock instantly

/* ----------  grab headers  ---------- */
$fname = $_SERVER["HTTP_X_FILENAME"] ?? "";
$chunk = (int) ($_SERVER["HTTP_X_CHUNK"] ?? 0);
$chunks = (int) ($_SERVER["HTTP_X_CHUNKS"] ?? 1);

if (!$fname) {
    http_response_code(400);
    exit("Missing filename");
}

/* ----------  path-traversal guard  ---------- */
$fname = basename($fname);
if ($fname === "" || $fname === "." || $fname === "..") {
    http_response_code(400);
    exit("Invalid filename");
}

/* ----------  memory-only read  ---------- */
$raw = file_get_contents("php://input");
if ($raw === false) {
    http_response_code(500);
    exit("Read error");
}

/* ----------  stage chunk  ---------- */
$tmpDir = __DIR__ . "/tmp_chunks";
if (!is_dir($tmpDir)) {
    mkdir($tmpDir, 0755, true);
}
$chunkFile = $tmpDir . "/" . $fname . ".part" . $chunk;
file_put_contents($chunkFile, $raw); // small meta write

/* ----------  re-assemble only when *all* chunks arrived  ---------- */
$allHere = true;
for ($i = 0; $i < $chunks; $i++) {
    if (!file_exists($tmpDir . "/" . $fname . ".part" . $i)) {
        $allHere = false;
        break;
    }
}

if ($allHere) {
    $uploadDir = __DIR__ . "/uploads";
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    $final = $uploadDir . "/" . $fname;

    $out = fopen($final, "wb");
    for ($i = 0; $i < $chunks; $i++) {
        $part = $tmpDir . "/" . $fname . ".part" . $i;
        $in = fopen($part, "rb");
        stream_copy_to_stream($in, $out);
        fclose($in);
        unlink($part); // clean up
    }
    fclose($out);
}

/* ----------  done  ---------- */
http_response_code(200);
header("Content-Length: 0");
