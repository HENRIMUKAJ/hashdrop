<?php
session_start();
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
    <li><a href="?ViewProfForm">Select Profile</a></li>
    <li><a>Hi,<?= $_SESSION["firstName"]; ?></a></li>
    <li><a href="?LogOff">Log Out</a></li>
</ul>

<div class='modal' id='modal'>
    <div class='modal-body'>
    <div class='modal-header'>
        <div class='title'>Update Information</div>
        <button name="closeModal" data-close-button class='close-button'>&#x2716</button>
    </div>
    <div class="Edit-Form"></div>
    </div>
</div>
<div id='overlay'></div>

<div id="serverRep">
<?php require 'CRUDHashDropDB.php'; ?>
</div>
<footer>
    <p id="AvalSize"></p>
</footer>
</body>
</html>
