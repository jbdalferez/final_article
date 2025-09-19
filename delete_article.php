<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: login.php');
    exit;
}

if (!isset($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$article_id = (int)$_GET['id'];

// Get article info
$stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
$stmt->execute([$article_id]);
$article = $stmt->fetch();

if (!$article) {
    header('Location: index.php');
    exit;
}

// Delete article
$stmt = $pdo->prepare("DELETE FROM articles WHERE id = ?");
$stmt->execute([$article_id]);

// Notify author
$msg = "Your article '{$article['title']}' has been deleted by admin.";
$stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
$stmt->execute([$article['user_id'], $msg]);

header('Location: index.php');
exit;
?>
