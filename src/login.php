<?php
session_save_path(__DIR__ . "/tmp_sessions");
if (!is_dir(__DIR__ . "/tmp_sessions")) {
    mkdir(__DIR__ . "/tmp_sessions", 0755, true);
}
session_start();

require __DIR__ . "/env_loader.php";

$users = load_users();
$error = "";
$lock_file = __DIR__ . "/tmp_sessions/lock";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $input_user = $_POST["username"] ?? "";
    $input_pass = $_POST["password"] ?? "";

    if (isset($users[$input_user]) && $users[$input_user] === $input_pass) {
        $_SESSION["logged_in"] = true;
        $_SESSION["username"] = $input_user;
        $client_ip = $_SERVER["REMOTE_ADDR"];
        $user_agent_hash = hash("sha256", $_SERVER["HTTP_USER_AGENT"] ?? "");
        file_put_contents($lock_file, $client_ip . "|" . $user_agent_hash);
        header("Location: upload_page.php");
        exit();
    } else {
        $error = "Invalid username or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Login</title>
<style>
body {
    margin: 0; font-family: Arial, sans-serif; display:flex; justify-content:center; align-items:center; height:100vh;
    background: linear-gradient(135deg, #1f2937, #111827); color:white;
}
.container { background: rgba(255,255,255,0.05); padding: 40px; border-radius: 14px; backdrop-filter: blur(10px); box-shadow:0 10px 30px rgba(0,0,0,0.5); width:320px; text-align:center;}
h2 { margin-bottom: 20px; }
input { width:90%; padding:8px; margin:8px 0; border-radius:6px; border:none; }
button { width:95%; padding:10px; border-radius:6px; border:none; background-color:#2563eb; color:white; cursor:pointer; margin-top:10px; }
button:hover { background-color:#1e40af; }
.error { color:#f87171; margin-top:10px; }
</style>
</head>
<body>
<div class="container">
<h2>Login</h2>
<form method="POST">
<input type="text" name="username" placeholder="Username" required><br>
<input type="password" name="password" placeholder="Password" required><br>
<button type="submit">Login</button>
</form>
<?php if ($error) {
    echo "<div class='error'>$error</div>";
} ?>
</div>
</body>
</html>
