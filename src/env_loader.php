<?php
/**
 * env_loader.php – Parse the .env file and return a username → password map.
 *
 * .env format for users:
 *   USER_1=username:password
 *   USER_2=another_user:another_password
 *
 * Lines starting with '#' are comments and are ignored.
 * Only keys matching the pattern USER_* are treated as credential entries.
 */

function load_users(string $env_path = null): array
{
    if ($env_path === null) {
        $env_path = __DIR__ . '/.env';
    }

    $users = [];

    if (!file_exists($env_path) || !is_readable($env_path)) {
        return $users;
    }

    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Only process lines that look like KEY=VALUE
        if (strpos($line, '=') === false) {
            continue;
        }

        list($key, $value) = explode('=', $line, 2);
        $key   = trim($key);
        $value = trim($value);

        // Only pick up USER_* keys
        if (preg_match('/^USER_\d+$/i', $key)) {
            // Value format: username:password  (first colon is the delimiter)
            $colon_pos = strpos($value, ':');
            if ($colon_pos !== false) {
                $username = substr($value, 0, $colon_pos);
                $password = substr($value, $colon_pos + 1);
                $users[$username] = $password;
            }
        }
    }

    return $users;
}
