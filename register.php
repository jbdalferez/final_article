<?php
require 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $role = $_POST['role'] === 'admin' ? 'admin' : 'writer'; // only allow these two roles

    if ($username && $password) {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error = "Username already taken.";
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
            $stmt->execute([$username, $hash, $role]);
            header('Location: login.php');
            exit;
        }
    } else {
        $error = "Please fill all fields.";
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Register</title>
<link rel="stylesheet" href="style.css"></head>
<body>
<h2>Register</h2>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<form method="post">
    Username: <input type="text" name="username" required><br>
    Password: <input type="password" name="password" required><br>
    Role:
    <select name="role">
        <option value="writer">Writer</option>
        <option value="admin">Admin</option>
    </select><br>
    <button type="submit">Register</button>
</form>
<a href="login.php">Login</a>
</body>
</html>
