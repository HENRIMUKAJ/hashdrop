<?php
require_once 'config.php';
require_once 'CommonlyUsedFunc.php';
error_reporting(-1);
ini_set('display_errors', 'On');


function main(){
    global $database_name;

    if(!(is_writeable("data/".$database_name) || is_readable("data/".$database_name))){
        echo "Error database is not writable or readable";
        ErrorLog("Database is not writable or readable this will break page functionality please fix permissions!");
        return false;
    }

    $baseDirProf=getProfilesDir();
    $resultOfDeleteFileExp=null;
    $resultOfRemoveDirectoryNotInDb=null;
    $resultOfDeletePartialUploadFiles=deletePartiallyUploadedFiles();
    if(!is_null($baseDirProf)){
        foreach ($baseDirProf as $baseDir){
            $resultOfDeleteFileExp=deleteFilesExp($baseDir["baseUpDir"],$baseDir["profileId"]);
            $resultOfRemoveDirectoryNotInDb=removeDiretoryNotInDB($baseDir["baseUpDir"]);
            if($resultOfDeleteFileExp !=0){
                echo "Errors have occurred during deletion of files that have expired!\n";
                ErrorLog("Errors Have occured during deletion of file that have expired");
            }
            if($resultOfRemoveDirectoryNotInDb !=0)
            {
                echo "Errors have occurred during deletion of directories and files not in the database!\n";
                ErrorLog("Errors have occurred during deletion of directories and files not in the database!");
            }
        }
    }
    elseif ($baseDirProf=== false){
        echo "Errors Profiles base directories could not be retrieved";
        ErrorLog("Profile directories could not be retrieved from the database!");
        exit(1);
    }
    if($resultOfDeletePartialUploadFiles === 0 && $resultOfRemoveDirectoryNotInDb === 0 && $resultOfDeleteFileExp === 0){
        echo "Successful deletion";
        exit(0);
    }
    else{
        echo "Errors have occurred during cron job in functions deleteFilesExpiration: ".$resultOfDeleteFileExp." RemoveDirectoryNotInDb: ".$resultOfRemoveDirectoryNotInDb." DeletePartiallyUploadFiles: ".$resultOfDeletePartialUploadFiles;
        ErrorLog("Errors have occurred during cron job in functions deleteFilesExpiration: ".$resultOfDeleteFileExp." RemoveDirectoryNotInDb: ".$resultOfRemoveDirectoryNotInDb." DeletePartiallyUploadFiles: ".$resultOfDeletePartialUploadFiles);
        exit(1);
    }
}

function getProfilesDir(){
     global $hashDropDb;
     global $profileTableName;

     $getStmt=$hashDropDb->prepare("select baseUpDir,profileId from $profileTableName");
     if($getStmt->execute()){
         return $getStmt->fetchAll();
     }
     ErrorLog("Query to retrieve profile directories could not execute!");
     return false;
}

function deletePartiallyUploadedFiles(){
    global $baseDir1;
    global $partialUploadDir;
    $scanDirRes=scandir($baseDir1."/".$partialUploadDir);
    $error=0;
    if(!file_exists($baseDir1."/".$partialUploadDir)){
        ErrorLog($baseDir1."/".$partialUploadDir."This directory does not exist. Deletion of partially uploaded files can't occur");
        $error+=1;
    }
    foreach ($scanDirRes as $file){
        if(is_file($baseDir1."/".$partialUploadDir."/".$file)){
            $directoryStats=stat($baseDir1."/".$partialUploadDir."/".$file);
            $allowTimeOfInact=$directoryStats[9]+(2*86400);
            if(file_exists($baseDir1."/".$partialUploadDir."/".$file) && $allowTimeOfInact < time()){
                if(!unlink($baseDir1."/".$partialUploadDir."/".$file)){
                    ErrorLog("Unlink has failed at ".$baseDir1."/".$partialUploadDir."/".$file);
                    $error+=1;
                }
                InfoLog("Unlink was successful at ".$baseDir1."/".$partialUploadDir."/".$file);
            }
        }
    }
    return $error;
}

function deleteFilesExp($baseDir,$profileId)
{
    global $hashDropDb;
    global $genericFileName;
    global $fileTableName;
    $numError=0;
    $stmt = $hashDropDb->prepare("select * from $fileTableName where profileID=:profId and deleted=0");
    $stmt->bindParam(":profId",$profileId);
    $stmt1 = $hashDropDb->prepare("UPDATE $fileTableName SET deleted=1,delBy='System' WHERE fileId= :fileId;");
    $stmt2= $hashDropDb->prepare("select sha1Hash,count(sha1Hash),fileName,fileId,uploadTime,daystildel From $fileTableName WHERE sha1Hash=:sha1Hash AND profileID=:profId GROUP BY sha1Hash HAVING COUNT(sha1Hash) > 0;");
    $stmt2->bindParam(":profId",$profileId);
    $secInDay = 86400;
    if($stmt->execute()) {
            foreach ($stmt as $row) {
                $stmt2->bindParam(":sha1Hash",$row["sha1Hash"]);
                if($stmt2->execute()) {
                    $numberOfSha1 =$stmt2->fetch();
                }
                else{
                    ErrorLog("Query to get the sha1sums inside the database could not be executed");
                    $numError+=1;
                }
                $convertedTime = $row["daystildel"] * $secInDay;
                $delTime = $row["uploadTime"] + $convertedTime;
                if ($numberOfSha1["count(sha1Hash)"] == 1 && $delTime < time()) {
                    $stmt1->bindParam(":fileId", $row["fileId"]);
                    if ($stmt1->execute()) {
                        if(!unlink($baseDir . "/" . $row["sha1Hash"] . "/" . $row["fileName"])) {
                            ErrorLog("Unlink has failed at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $row["fileName"]);
                            $numError+=1;
                        }
                        InfoLog("Unlink has succeeded at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $row["fileName"]);
                        if(!unlink($baseDir . "/" . $row["sha1Hash"] . "/" . $genericFileName)){
                            ErrorLog("Unlink has failed at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $genericFileName);
                            $numError+=1;
                        }
                        InfoLog("Unlink has succeeded at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $genericFileName);
                        if(!rmdir($baseDir . "/" . $row["sha1Hash"])){
                            ErrorLog("rmdir has failed at ".$baseDir . "/" . $row["sha1Hash"]);
                            $numError+=1;
                        }
                        InfoLog("rmdir has succeeded at".$baseDir. "/" . $row["sha1Hash"]);
                    }
                    else{
                        ErrorLog("Query to delete files from database has failed to execute");
                        $numError+=1;
                    }
                } elseif ($numberOfSha1["count(sha1Hash)"] >= 2 && $delTime < time()) {
                    $stmt1->bindParam(":fileId", $row["fileId"]);
                    if ($stmt1->execute()) {
                        if(!unlink($baseDir . "/" . $row["sha1Hash"] . "/" . $row["fileName"]))
                        {
                            ErrorLog("Unlink has failed at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $row["fileName"]);
                            $numError+=1;
                        }
                        InfoLog("Unlink has succeeded at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $row["fileName"]);
                        if(CheckDirContents($baseDir . "/" . $row["sha1Hash"]))
                        {
                            if(!unlink($baseDir."/".$row["sha1Hash"]."/".$genericFileName))
                            {
                                ErrorLog("Unlink has failed at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $genericFileName);
                                $numError+=1;
                            }
                            InfoLog("Unlink has succeeded at ".$baseDir . "/" . $row["sha1Hash"] . "/" . $genericFileName);
                            if(!rmdir($baseDir."/".$row["sha1Hash"]))
                            {
                                ErrorLog("rmdir has failed at ".$baseDir . "/" . $row["sha1Hash"]);
                                $numError+=1;
                            }
                            InfoLog("rmdir has succeeded at ".$baseDir . "/" . $row["sha1Hash"]);
                        }
                    }else{
                        ErrorLog("Delete query has failed to execute");
                        $numError+=1;
                    }
                }
            }
    }
    else{
        ErrorLog("Query to retrieve all files from the database has failed");
        $numError+=1;
    }
    return $numError;
}

//check if the only file left in the directory is the generic file name set in the config this function is used in the case where multiple sim links expire at the same time
function CheckDirContents($directory)
{
    $filesFound=0;
    $directory=scandir($directory);
    for($counter=0;$counter<count($directory);$counter++)
    {
        if(!($directory[$counter] == "." || $directory[$counter] == ".."))
        {
            $filesFound++;
        }
    }

    if($filesFound == 1)
    {
        return true;
    }
    else
    {
        return false;
    }
}

function removeDiretoryNotInDB($baseDir)
{
    global $hashDropDb;
    $numError=0;
    $stmt=$hashDropDb->prepare("select sha1Hash from FILE where sha1Hash = :sha1Hash");
    $dirPresent=scandir($baseDir);
    for($counter=0;$counter<count($dirPresent);$counter++){
        if(!($dirPresent[$counter] == "." || $dirPresent[$counter] == "..")){
            $stmt->bindParam(":sha1Hash",$dirPresent[$counter]);
            if($stmt->execute()){
                $isDirPresentInDb=$stmt->fetch();
                $directoryStats=stat($baseDir."/".$dirPresent[$counter]);
                $allowTimeOfInact=$directoryStats[9]+(2*86400);
                if(!$isDirPresentInDb && $allowTimeOfInact < time()){
                    $filesPresentInDir=scandir($baseDir."/".$dirPresent[$counter]);
                    if(count($filesPresentInDir)>2){
                        foreach ($filesPresentInDir as $file) {
                            if(!($file == "." || $file == "..")) {
                                if (!unlink($baseDir . "/" . $dirPresent[$counter] . "/" . $file)) {
                                    ErrorLog("Unlink has failed at " . $baseDir . "/" . $dirPresent[$counter] . "/" . $file);
                                    $numError += 1;
                                }
                                InfoLog("Unlink has succeeded at " . $baseDir . "/" . $dirPresent[$counter] . "/" . $file);
                            }
                        }
                    }
                    if(!rmdir("$baseDir"."/".$dirPresent[$counter])){
                        ErrorLog("rmdir has failed at ".$baseDir."/".$dirPresent[$counter]);
                        $numError+=1;
                    }
                    InfoLog("rmdir has failed at ".$baseDir."/".$dirPresent[$counter]);
                }
            }
            else{
                ErrorLog("query to get all the sha1sums from the database has failed");
                $numError+=1;
            }
        }
    }
    return $numError;
}

main();
