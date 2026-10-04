<?php
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    header('Location: ../public/login.php', true, 307);
} else {
    header('Location: ../public/login.php');
}
exit;
