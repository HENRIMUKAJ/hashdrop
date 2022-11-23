<?php
    session_start();
    include 'config.php';
    if($_SESSION["fileDesc"] == null && $_SESSION["delTime"] == null && $_SESSION["receiverEmail"] == null){
        header("Location:uploadfileinfo.phtml");
    }
    Authenticate();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hash Drop</title>
    <link rel="stylesheet" href="css/site.css">
    <script src="js/plupload-3.1.5/js/plupload.full.min.js"></script>
    <script type ="text/javascript" src="js/UploadAndAjax.js"></script>
    <script type ="text/javascript" src="js/site.js"></script>
</head>
<body>
<ul>
    <li><a href="index.php">Hash Drop</a></li>
    <li><a href="uploadfileinfo.phtml">Upload Form</a></li>
    <li><a>Hi,<?= $_SESSION["firstName"]; ?></a></li>
    <li><a href="index.php?LogOff">Log Out</a></li>
</ul>

<div id="filelist"></div>
<div id="serverRep"></div>
    <input class="button edit" type="button" id="pickfiles" value="Browse"/>
    <input disabled class="hidden" type="button" id="submitForm" name="submitForm" value="Upload"/>
<footer>
    <p id="AvalSize"></p>
</footer>
</body>
</html>
