<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require 'config.php';
require 'CommonlyUsedFunc.php';
session_start();

if(Authenticate()){
    putFileTogether();
}


function getProfileUploadLoc($profileId){
    global $hashDropDb;
    global $profileTableName;

    $getUploadLoc=$hashDropDb->prepare("SELECT baseUpDir FROM $profileTableName WHERE profileId =:prof;");
    $getUploadLoc->bindParam(":prof",$profileId);
    if($getUploadLoc->execute()){
        foreach($getUploadLoc as $row){
            return $row["baseUpDir"];
        }
    }
    ErrorLog("getProfileUploadLoc database query failed to execute!");
    return false;
}


//if(isset($_GET["fileSize"])){
//    if(CheckAvalDiskSpace($_GET["fileSize"])){
//        http_response_code(200);
//    }
//    else
//    {
//        http_response_code(400);
//        return false;
//    }
//}
//function CheckAvalDiskSpace($file){
//    global $baseDir1;
//    $avalSpace=disk_free_space($baseDir1);
//    if($file >= 10) {
//        echo "Files cannot be uploaded at the moment not enough space.Contact the help-desk!";
//        ErrorLog("not enough space in the upload directory!");
//        return false;
//    }
//    return true;
//}

function putFileTogether()
{
    global $baseDir1;
    global $partialUploadDir;
    global $genericFileName;
    global $maxFileSizeGb;
    $getPathResult=null;
    $submitedId = null;
    $primaryProfId=checkUsrProf($_SESSION["username"]);
    if(isset($_SESSION["uploadProf"])) {
        $submitedId = $_SESSION["uploadProf"];
    }
    else{
        ErrorLog("Could not retrieve a users specified profile");
        echo "<h3 class='dangerText'>File cannot be uploaded at this time.</h3>";
        return false;
    }

    if($primaryProfId != $submitedId){
        if(checkSecondaryProf($_SESSION["username"],$submitedId) === false){
            $getPathResult= getProfileUploadLoc($submitedId);
        }
        else{
            echo "<h3 class='inline dangerText'>Profile Specified does not exist.</h3>";
            ErrorLog($_SESSION["username"]." specified a profile that does not exist");
            http_response_code(406);
        }
    }
    else{
        $getPathResult=getProfileUploadLoc($submitedId);
    }

    if($getPathResult == null){
        echo "<h3 class='dangerText'>You have not been assigned a profile. Contact IT you will not be able to upload until then.</h3>";
        http_response_code(406);
        return false;
    }

    if (isset($_FILES)) {
        if (isset($_REQUEST["name"])) {
            $fileName = $_REQUEST["name"];
            $filteredFileName = FileNameClean($fileName);
            $_SESSION['fileName'] = $filteredFileName;
            http_response_code(200);
        } else {
            echo "<h3 class='dangerText'>File upload error contact the Help-Desk!</h3>";
            ErrorLog("Original file name did not get received from the client!");
            http_response_code(406);
            return false;
        }
    }
    else
    {
        echo "<h3 class='dangerText'>File upload error contact the Help-Desk!</h3>";
        ErrorLog("File was not received from the client!");
        http_response_code(406);
        return false;
    }

    $filePath = $baseDir1 ."/".$partialUploadDir. "/" . $filteredFileName;

    if (isset($_REQUEST["chunk"])) {
        $fileChunk = intval($_REQUEST["chunk"]);
    } else {
        $fileChunk = 0;
    }

    if (isset($_REQUEST["chunks"])) {
        $fileChunks = intval($_REQUEST["chunks"]);
    } else {
        $fileChunks = 0;
    }

    if(isset($_SESSION["NotEnoughSpace"])){
        if($_SESSION["NotEnoughSpace"] === true) {
            echo "<h3 class='dangerText'>Files cannot be uploaded at the moment not enough space.Contact the help-desk!</h3>";
            http_response_code(400);
            return false;
        }
    }

    try {
        if ($fileChunk == 0) {
            $fileOut = fopen("{$filePath}.part", "wb");
        } else {
            $fileOut = fopen("{$filePath}.part", "ab");
        }
    } catch (Exception $error) {
        ErrorLog("fileOut:File trying to be opened using fread can't be opened" . "Error:" . $error);
    }

    if ($fileOut) {
        try {
            $fileIn = fopen($_FILES["file"]["tmp_name"], "rb");
        } catch (Exception $error) {
            ErrorLog("fileIn:File trying to be opened using fread can't be opened" . "Error:" . $error);
        }

        if ($fileIn) {
            while ($buffer = fread($fileIn, filesize($_FILES["file"]["tmp_name"]))) {
                fwrite($fileOut, $buffer);
            }
        } else {
            ErrorLog("Cant open input stream $fileIn");
        }
        $maxSize=$maxFileSizeGb*pow(1024,3);
        $partFileLoc="{$filePath}.part";
        if(filesize("{$filePath}.part") > $maxSize){
            echo "<h3 class='dangerText'>File is to large limit is $maxFileSizeGb gb!</h3>";
            ErrorLog("file larger than $maxFileSizeGb gb was attempted for upload");
            if(!unlink($partFileLoc)){
                ErrorLog("failed to unlink file upload that is too larget at ".$partFileLoc);
            }
            http_response_code(400);
            return false;
        }
        fclose($fileIn);
        fclose($fileOut);
        unlink($_FILES["file"]["tmp_name"]);
    } else {
        ErrorLog("Cant open output stream $fileOut");
    }


    if (!$fileChunks || $fileChunk == $fileChunks - 1) {
        rename("{$filePath}.part", $filePath);
        $sha1DirName = sha1_file($filePath);
        $_SESSION["sha1sum"] = $sha1DirName;
        $fullUploadPath = $getPathResult ."/" . $sha1DirName;
        if(!file_exists($getPathResult)){
            mkdir($getPathResult);
        }
        elseif (!file_exists($fullUploadPath)){
            mkdir($fullUploadPath);
        }

        if (!file_exists($fullUploadPath . "/" . $filteredFileName)) {
            if (rename($filePath, $fullUploadPath . "/" . $genericFileName)) {
                chdir($fullUploadPath);
                $link = $filteredFileName;
                if (symlink($genericFileName, $filteredFileName)) {
                    $_SESSION["symlink"] = $link;
                    return true;
                } else {
                    echo "<h3 class='dangerText'>File upload error contact the Help-Desk!</h3>";
                    ErrorLog("SymLink for file could not be generated");
                    http_response_code(406);
                    return false;
                }
            } else {
                echo "<h3 class='dangerText'>File upload error contact the Help-Desk!</h3>";
                ErrorLog("File could not be renamed from $filePath to ".$fullUploadPath . "/" . $genericFileName);
                http_response_code(406);
                return false;
            }
        } else {
            unlink($filePath);
            ErrorLog("unlinking duplicate file at location $filePath");
            echo "<h2 class='dangerText'>Duplicate File try again!</h2>";
            http_response_code(406);
            return false;
        }
    }
}


