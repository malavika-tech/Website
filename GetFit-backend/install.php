<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>GetFit — Install</title>
<style>
  body { font-family: sans-serif; max-width: 600px; margin: 60px auto; padding: 0 20px; }
  .ok  { color: #16a34a; } .err { color: #dc2626; }
  pre  { background: #f3f4f6; padding: 12px; border-radius: 6px; font-size: 13px; }
  a    { color: #2563eb; }
</style>
</head>
<body>
<h2>GetFit — Installer</h2>
<?php
$host = 'localhost'; $user = 'root'; $pass = ''; $dbname = 'getfit';

try {
    // Connect without selecting a DB first so we can create it
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);

    $sql = file_get_contents(__DIR__ . '/sql/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
        if ($stmt) $pdo->exec($stmt);
    }
    echo '<p class="ok">&#10003; Database and tables created.</p>';

    // Check if admin already exists
    $pdo->exec("USE $dbname");
    $exists = $pdo->query("SELECT id FROM users WHERE username='admin'")->fetch();
    if ($exists) {
        echo '<p class="ok">&#10003; Admin account already exists — skipping.</p>';
    } else {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $st = $pdo->prepare("INSERT INTO users (role, username, password_hash, email) VALUES ('admin','admin',?,'admin@getfit.com')");
        $st->execute([$hash]);
        echo '<p class="ok">&#10003; Default admin created: <strong>admin / admin123</strong></p>';
    }

    echo '<hr><p>Setup complete! You can now:</p><ul>';
    echo '<li>Delete or rename <code>install.php</code> (optional but recommended).</li>';
    echo '<li>Visit <a href="/GetFit/index.html">/GetFit/index.html</a> and log in as <strong>admin / admin123</strong>.</li>';
    echo '</ul>';

} catch (PDOException $e) {
    echo '<p class="err">&#10007; Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<pre>Make sure XAMPP MySQL is running and root has no password (default XAMPP setup).</pre>';
}
?>
</body>
</html>
