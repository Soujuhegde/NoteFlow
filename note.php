<?php
// Connect to DB
$mysqli = new mysqli("localhost", "NoteFlow_User", "Oracle@123", "NoteFlow");
if ($mysqli->connect_error) {
    die("DB connection failed: " . $mysqli->connect_error);
}

$id = $_GET['id'] ?? '';
if (!preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
    die("Invalid ID format.");
}

// Fetch content if exists
$stmt = $mysqli->prepare("SELECT content FROM notes WHERE id = ?");
if (!$stmt) {
    die("Query failed: " . $mysqli->error);
}
$stmt->bind_param("s", $id);
$stmt->execute();
$stmt->bind_result($content);
$stmt->fetch();
$stmt->close();
$mysqli->close();

$content = $content ?? ''; // blank if no content found
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <title>NoteFlow - <?php echo htmlspecialchars($id); ?></title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #282c34; color: white; }
        #editor { width: 100vw; height: 100vh; padding: 1rem; font-size: 1.2rem; background: #1e2127; border: none; color: white; resize: none; outline: none; }
        #status { position: fixed; bottom: 10px; right: 10px; font-size: 0.9rem; color: #61dafb; }
    </style>
</head>
<body>
    <textarea id="editor" placeholder="Start typing..."><?php echo htmlspecialchars($content); ?></textarea>
    <div id="status">Saved</div>

    <script>
        const editor = document.getElementById('editor');
        const status = document.getElementById('status');
        let lastContent = editor.value;

        function saveContent() {
            const content = editor.value;
            status.textContent = 'Saving...';

            fetch('save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id: '<?php echo addslashes($id); ?>', content: content })
            })
            .then(response => response.json())
            .then(data => {
                status.textContent = data.success ? 'Saved' : 'Error saving';
            })
            .catch(() => {
                status.textContent = 'Error saving';
            });
        }

        // Auto-save every second if content changed
        setInterval(() => {
            if (editor.value !== lastContent) {
                lastContent = editor.value;
                saveContent();
            }
        }, 1000);
    </script>
</body>
</html>
