<?php
if(session_status()!=2) {
    session_start();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hash Drop</title>
    <link rel="stylesheet" href="css/site.css">
    <script defer src="js/site.js"></script>
</head>
<body>
<ul>
    <li><a href="index.php">Hash Drop</a></li>
    <li><a href="uploadfileinfo.phtml">Upload Form</a></li>
    <li><a>Hi,<?= $_SESSION["firstName"]; ?></a></li>
    <li><a href="index.php?LogOff">Log Out</a></li>
</ul>

<div>
    <?php require 'DisplayHistory.php'; ?>
</div>
<footer>
    <p id="AvalSize"></p>
</footer>
</body>
</html>