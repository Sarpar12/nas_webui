<?php
session_save_path(__DIR__ . '/tmp_sessions');
session_start();
include __DIR__.'/auth.php'; // redirects to login if no lock file
?>
<h1>Welcome!</h1>
<p>You are logged in and can access the site.</p>
