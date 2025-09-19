<?php
require 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

// Fetch categories for dropdowns
$stmt = $pdo->query("SELECT * FROM categories ORDER BY name");
$categories = $stmt->fetchAll();

// Initialize messages
$error = '';
$success = '';

// Handle new category submission (admin only)
if ($role === 'admin' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_category'])) {
    $cat_name = trim($_POST['category_name']);
    if ($cat_name) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO categories (name) VALUES (?)");
        $stmt->execute([$cat_name]);
        $success = "Category added.";
    } else {
        $error = "Category name cannot be empty.";
    }
}

// Handle new article submission (writer)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['new_article'])) {
    $title = trim($_POST['title']);
    $content = trim($_POST['content']);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $image_path = null;

    if (!empty($_FILES['image']['name'])) {
        $target_dir = "uploads/";
        if (!is_dir($target_dir)) mkdir($target_dir, 0777, true);
        $image_path = $target_dir . basename($_FILES["image"]["name"]);
        move_uploaded_file($_FILES["image"]["tmp_name"], $image_path);
    }

    if ($title && $content && $category_id) {
        $stmt = $pdo->prepare("INSERT INTO articles (user_id, title, content, image, category_id, status) VALUES (?, ?, ?, ?, ?, 'published')");
        $stmt->execute([$user_id, $title, $content, $image_path, $category_id]);
        header('Location: index.php');
        exit;
    } else {
        $error = "Title, content, and category are required.";
    }
}

// Handle article category update (writer)
if ($role === 'writer' && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $article_id = (int)$_POST['article_id'];
    $new_category_id = (int)$_POST['category_id'];

    // Verify ownership
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ? AND user_id = ?");
    $stmt->execute([$article_id, $user_id]);
    $article = $stmt->fetch();

    if ($article && $new_category_id) {
        $stmt = $pdo->prepare("UPDATE articles SET category_id = ? WHERE id = ?");
        $stmt->execute([$new_category_id, $article_id]);
        $success = "Category updated.";
        header('Location: index.php');
        exit;
    } else {
        $error = "Invalid article or category.";
    }
}

// Handle edit request
if (isset($_GET['request_edit'])) {
    $article_id = (int)$_GET['request_edit'];

    // Check if user is writer and owns the article
    $stmt = $pdo->prepare("SELECT * FROM articles WHERE id = ?");
    $stmt->execute([$article_id]);
    $article = $stmt->fetch();

    if ($article && $role === 'writer' && $article['user_id'] == $user_id) {
        // Insert edit request
        $stmt = $pdo->prepare("INSERT INTO edit_requests (article_id, writer_id) VALUES (?, ?)");
        $stmt->execute([$article_id, $user_id]);

        // Update article status
        $stmt = $pdo->prepare("UPDATE articles SET status = 'edit_requested' WHERE id = ?");
        $stmt->execute([$article_id]);

        // Notify admin(s)
        $stmt = $pdo->prepare("SELECT id FROM users WHERE role = 'admin'");
        $admins = $stmt->fetchAll();
        foreach ($admins as $admin) {
            $msg = "Edit request for article '{$article['title']}' by {$_SESSION['username']}.";
            $stmt2 = $pdo->prepare("INSERT INTO notifications (user_id, message) VALUES (?, ?)");
            $stmt2->execute([$admin['id'], $msg]);
        }

        $success = "Edit request sent.";
    }
}

// Fetch articles with category name
if ($role === 'admin') {
    $stmt = $pdo->query("SELECT articles.*, users.username, categories.name AS category_name 
                         FROM articles 
                         JOIN users ON articles.user_id = users.id
                         LEFT JOIN categories ON articles.category_id = categories.id");
} else {
    $stmt = $pdo->prepare("SELECT articles.*, users.username, categories.name AS category_name 
                           FROM articles 
                           JOIN users ON articles.user_id = users.id
                           LEFT JOIN categories ON articles.category_id = categories.id
                           WHERE articles.user_id = ?");
    $stmt->execute([$user_id]);
}
$articles = $stmt->fetchAll();

// Fetch notifications
$stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? AND is_read = 0 ORDER BY created_at DESC");
$stmt->execute([$user_id]);
$notifications = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Home</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
<h2>Welcome, <?=htmlspecialchars($_SESSION['username'])?> (<?=htmlspecialchars($role)?>)</h2>
<a href="logout.php">Logout</a> | 
<a href="shared_articles.php">Shared Articles</a>

<h3>Notifications</h3>
<?php if ($notifications): ?>
    <ul>
    <?php foreach ($notifications as $note): ?>
        <li><?=htmlspecialchars($note['message'])?></li>
    <?php endforeach; ?>
    </ul>
<?php else: ?>
    <p>No new notifications.</p>
<?php endif; ?>

<?php if ($role === 'admin'): ?>
<h3>Add New Category</h3>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if (!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>
<form method="post">
    <input type="hidden" name="new_category" value="1">
    Category Name: <input type="text" name="category_name" required>
    <button type="submit">Add Category</button>
</form>
<?php endif; ?>

<?php if ($role === 'writer'): ?>
<h3>Create New Article</h3>
<?php if (!empty($error)) echo "<p style='color:red;'>$error</p>"; ?>
<?php if (!empty($success)) echo "<p style='color:green;'>$success</p>"; ?>
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="new_article" value="1">
    Title: <input type="text" name="title" required><br>
    Content:<br>
    <textarea name="content" rows="5" cols="40" required></textarea><br>
    Category:
    <select name="category_id" required>
        <option value="">Select category</option>
        <?php foreach ($categories as $cat): ?>
            <option value="<?=htmlspecialchars($cat['id'])?>"><?=htmlspecialchars($cat['name'])?></option>
        <?php endforeach; ?>
    </select><br>
    Image: <input type="file" name="image" accept="image/*"><br>
    <button type="submit">Create Article</button>
</form>
<?php endif; ?>

<h3>Your Articles</h3>
<table border="1" cellpadding="5" cellspacing="0">
    <tr><th>Title</th><th>Content</th><th>Image</th><th>Category</th><th>Status</th><th>Actions</th></tr>
    <?php foreach ($articles as $art): ?>
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
            <?php if ($role === 'writer'): ?>
                <form method="post" style="margin:0;">
                    <input type="hidden" name="update_category" value="1">
                    <input type="hidden" name="article_id" value="<?=htmlspecialchars($art['id'])?>">
                    <select name="category_id" onchange="this.form.submit()">
                        <option value="">Select category</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?=htmlspecialchars($cat['id'])?>" <?=($cat['id'] == $art['category_id']) ? 'selected' : ''?>>
                                <?=htmlspecialchars($cat['name'])?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            <?php else: ?>
                <?=htmlspecialchars($art['category_name'] ?? 'Uncategorized')?>
            <?php endif; ?>
        </td>
        <td><?=htmlspecialchars($art['status'])?></td>
        <td>
            <?php if ($role === 'writer' && $art['status'] === 'published'): ?>
                <a href="?request_edit=<?=$art['id']?>">Request Edit</a>
            <?php endif; ?>
            <?php if ($role === 'admin'): ?>
                <a href="delete_article.php?id=<?=$art['id']?>" onclick="return confirm('Delete this article?')">Delete</a>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
</table>
</body>
</html>
