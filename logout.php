<?php
require_once __DIR__ . '/includes/auth.php';

// Hancurkan session dan cookie di sisi server (OWASP Best Practice)
invalidate_session();

$target = isset($_GET['timeout']) ? 'login.php?timeout=1' : 'index.php';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="0;url=<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>">
    <title>Logout</title>
</head>
<body>
    <script>
        try {
            localStorage.clear();
            sessionStorage.clear();
        } catch(e) {}
        window.location.replace("<?= htmlspecialchars($target, ENT_QUOTES, 'UTF-8') ?>");
    </script>
</body>
</html>
