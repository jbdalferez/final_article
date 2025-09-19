<?php
require 'db.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'writer') {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch accepted edit requests for this writer
$stmt = $pdo->prepare("
    SELECT a.*, u.username, er.status AS edit_status, er.id AS edit_request_id
    FROM articles a
    JOIN edit_requests er ON a.id = er.article_id
    JOIN users u ON a.user_id = u.id
    WHERE er.writer_id = ? AND er.status = 'accepted' AND a.status = 'shared'
");
$stmt->execute([$user_id]);
$shared_articles = $stmt->fetchAll();

// Handle accept/reject notifications for edit requests
if (isset($_GET['edit_request_id'], $_GET['action'])) {
    $edit_request_id = (int)$_GET['edit_request_id'];
    $action = $_GET['action'];

    if (in_array($action, ['accept', 'reject'])) {
        $new_status = $action === 'accept' ? 'accepted' : 'rejected';

        // Update edit request status
        $stmt = $pdo->prepare("UPDATE edit_requests SET status = ? WHERE id = ? AND writer_id = ?");
        $stmt->execute([$new_status, $edit_request_id, $user_id]);

        // Notify admin
        $stmt = $pdo->prepare("SELECT article_id FROM edit_requests WHERE id = ?");
        $stmt->execute([$edit_request_id]);
        $article_id = $stmt->fetchColumn();

        $stmt = $pdo->prepare("SELECT user_id, title FROM articles WHERE id = ?");
        $stmt->execute([$article_id]);
        $article = $stmt->fetch();

        $msg = "Writer {$_SESSION['username']} has {$new_status} the edit request for article '{$article['title']}'.";

        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
        $stmt->execute([$article['user_id'], $msg]);

        header('Location: shared_articles.php');
        exit;
    }
}
?>

<!DOCTYPE html>
<html>
<head><title>Shared Articles</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h2>Shared Articles for <?=htmlspecialchars($_SESSION['username'])?></h2>
<a href="index.php">Home</a> | <a href="logout.php">Logout</a>

<?php if ($shared_articles): ?>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Title</th><th>Content</th><th>Image</th><th>Actions</th></tr>
    <?php foreach ($shared_articles as $art): ?>
    <tr>
        <td><?=htmlspecialchars($art['title'])?></td>
        <td><?=nl2br(htmlspecialchars($art['content']))?></td>
        <td>
            <?php if ($art['image']): ?>
                <img src="<?=htmlspecialchars($art['image'])?>" width="100" alt="Article Image">
            <?php else: ?>
                No image
            <?php endif; ?>
        </td>
        <td>
            <!-- Writer can accept or reject the shared article edit request -->
            <a href="?edit_request_id=<?=$art['edit_request_id']?>&action=accept">Accept</a> | 
            <a href="?edit_request_id=<?=$art['edit_request_id']?>&action=reject">Reject</a>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
<?php else: ?>
<p>No shared articles.</p>
<?php endif; ?>
</body>
</html>
