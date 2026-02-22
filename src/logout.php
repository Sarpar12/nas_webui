<?php
// Set session folder
session_save_path(__DIR__ . '/tmp_sessions');
session_start();

// Path to lock file
$lock_file = __DIR__ . '/tmp_sessions/lock';
$lock_removed = false;

// Remove lock file if exists
if (file_exists($lock_file)) {
    unlink($lock_file);
    $lock_removed = true;
}

// Destroy PHP session
session_destroy();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Logged Out</title>
<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    display: flex;
    justify-content: center;
    align-items: center;
    height: 100vh;
    background: linear-gradient(135deg,#1f2937,#111827);
    color: white;
    text-align: center;
}
.container {
    background: rgba(255,255,255,0.05);
    padding: 40px;
    border-radius: 14px;
    backdrop-filter: blur(10px);
    box-shadow: 0 10px 30px rgba(0,0,0,0.5);
    width: 320px;
}
h1 { color: #4ade80; margin-bottom: 20px; }
a { color: #60a5fa; text-decoration: none; }
a:hover { text-decoration: underline; }
</style>
</head>
<body>
<div class="container">
    <h1>Logged Out</h1>
    <p><?php echo $lock_removed ? "Removed session file (lock)." : "No session file found."; ?></p>
    <p><a href="login.php">Go back to Login</a></p>
</div>
</body>
</html>
