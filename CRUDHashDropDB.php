<?php
error_reporting(-1);
ini_set('display_errors', 'On');
require 'config.php';

if(session_status()!=2) {
    session_start();
}
Authenticate();

function Main(){
    global $database_name;
    require_once 'FormValidation.php';
    require_once 'CommonlyUsedFunc.php';
    $sessionProfile=null;

    if(!(is_writeable("data/".$database_name) || is_readable("data/".$database_name))){
        GenErrorMsg();
        ErrorLog("Database is not writable or readable this will break page functionality please fix permissions!");
        return false;
    }

    if(!ConfigProfileTable()){
        GenErrorMsg();
        ErrorLog("Profile table could not be configured in database!\n");
        return false;
    }
    if(!ConfigUserTable()){
        GenErrorMsg();
        ErrorLog("User table could not be configured in database!\n");
        return false;
    }
    if(!ConfigFileTable()){
        GenErrorMsg();
        ErrorLog("File table could not be configured in database!\n");
        return false;
    }

    if(checkUserExist()==0 && isset($_SESSION["username"])){
        $userEmail=LdapUserEmail($_SESSION["username"]);
        if(isset($userEmail)) {
            if (InsertUser($userEmail)) {
                InfoLog($_SESSION["username"] . " has been added to database.");
            } else {
                GenErrorMsg();
                ErrorLog($_SESSION["username"] . " could not be added to database.");
                return false;
            }
        }
        else{
            GenErrorMsg();
            ErrorLog("Email for user ".$_SESSION["username"]." could not be retrieved from ldap");
            return false;
        }
    }

    if(isset($_GET["LogOff"])){
        session_unset();
        session_destroy();
        header("Location:logon.html");
        return true;
    }

    //Changes
    if(isset($_SESSION["username"]))
    {
        $userID = GetUserID();
        $userProfId = GetProfileId();
        $_SESSION["AllUserProfiles"]=PutProfilesInArr($_SESSION["username"]);
        if(!$_SESSION["AllUserProfiles"])
        {
            GenErrorMsg();
            ErrorLog("Session variable holding all of users: ".$_SESSION["username"]." assigned profiles could not be set!");
            return false;
        }

        if($userProfId == null ){
            ErrorLog($_SESSION["username"]." Does not have a primary profile assigned");
            echo "<h3 class='dangerText'>Looks like a profile has not been assigned please contact the help desk at 3008!</h3>";
            return false;
        }
    }

    if(isset($_SESSION["uploadProf"]))
    {
        $sessionProfile=$_SESSION["uploadProf"];
    }

    if( (isset($_POST["SubmitUploadForm"]) && $userID != NULL) ) {
        if($sessionProfile==null){
            echo "<h3 class='dangerText'>File you tried to upload could not be uploaded!</h3>";
            ErrorLog("sessionProfile variable is null file could not be inserted into the database but has made it to the file system!");
            return false;
        }
        if(InsertIntoDB($_SESSION["fileDesc"],$_SESSION["delTime"],$userID,$sessionProfile,$_SESSION["receiverEmail"])){
            InfoLog($_SESSION["username"]." has uploaded a file in profile with id ".$sessionProfile);
            if(!SendEmail($_SESSION["receiverEmail"],$_SESSION["sha1sum"],$_SESSION["fileName"],$sessionProfile,$_SESSION["fileDesc"])){
                echo "<h3 class='dangerText'>Email failed to send to ".$_SESSION["receiverEmail"]."</h3>";
                ErrorLog("Mail failed to send to ".$_SESSION["receiverEmail"]);
                http_response_code(400);
            }
            return true;
        } else {
            GenErrorMsg();
            ErrorLog("File could not be uploaded for ".$_SESSION["username"]);
            return false;
        }
    }


    if(isset($_POST['deleteInfo'])){
        $fileId=intval($_POST['deleteInfo']);
        if(DeleteFromDb($fileId,$_SESSION["username"])){
            InfoLog($_SESSION["username"]." Has deleted a file with ID ".$fileId);
            setcookie("deleteStatus","Successful delete!",time()+2);
            header("Location:index.php");
        }else{
            ErrorLog($_SESSION["username"]." attempted to delete a file with ID ".$fileId." but could not.");
            setcookie("deleteStatus","Failed to delete!",time()+2);
        }
    }

    if(isset($_POST['Edit'])) {
        $keyForUpdate = InputClean($_POST["fileId"]);
        $editedFileName=InputClean($_POST['filename1']);
        $editedDesc = InputClean($_POST['filedescr1']);
        $editedDelTime = InputClean($_POST['timetildel1']);
        $editedEmail = InputClean($_POST["receiverEmail"]);
        $editedFileName=FileNameClean($editedFileName);

        if(!checkFileName($editedFileName))
        {
            GenErrorMsg();
            ErrorLog("File name from file edit is invalid information submitted by: ".$_SESSION["username"]);
            setcookie("EditStatus","Failed to Edit!",time()+2);
            return false;
        }
        if(!CheckDropDown($editedDelTime))
        {
            GenErrorMsg();
            ErrorLog("deletion time from file edit is invalid information submitted by: ".$_SESSION["username"]);
            setcookie("EditStatus","Failed to Edit!",time()+2);
            return false;
        }
        if(!CheckDescription($editedDesc))
        {
            GenErrorMsg();
            ErrorLog("Description from file edit is invalid information submitted by: ".$_SESSION["username"]);
            setcookie("EditStatus","Failed to Edit!",time()+2);
            return false;
        }

        if(validEmailForm($editedEmail) !== true)
        {
            GenErrorMsg();
            ErrorLog("Description from file edit is invalid information submitted by: ".$_SESSION["username"]);
            setcookie("EditStatus","Failed to Edit!",time()+2);
            return false;
        }

        if(EditDbData($keyForUpdate,$editedFileName,$editedDesc,$editedDelTime,$editedEmail)){
            InfoLog($_SESSION["username"]." Has edited a file with file ID ".$keyForUpdate);
            $sha1sum=getSha1Dir($keyForUpdate);
            if($sha1sum !=false){
                if(!SendEmail($editedEmail,$sha1sum,$editedFileName,$sessionProfile,$editedDesc)){
                    echo "<h3 class='dangerText'>Email Failed to send to $editedEmail!</h3>";
                    ErrorLog($_SESSION["username"]." email could not be sent after file edit!");
                }
            }
            else
            {
                GenErrorMsg();
                ErrorLog("sha1sum for file with Id $keyForUpdate and name $editedFileName could not be retrieved!");
                return false;
            }
            setcookie("EditStatus","Successful edit!",time()+2);
            header("Location:index.php");
        }
        else{
            ErrorLog($_SESSION["username"]." could not edit file information with file ID ".$keyForUpdate);
            setcookie("EditStatus","Edit failed!",time()+2);
        }
    }

    if(isset($_COOKIE["deleteStatus"])){
        echo "<h2>".$_COOKIE["deleteStatus"]."</h2>";
    }
    if(isset($_COOKIE["EditStatus"])){
        echo "<h2>".$_COOKIE["EditStatus"]."</h2>";
    }
    if(isset($_GET["ViewProfForm"])){
        ViewProfForm();
    }

    if(isset($_POST["viewProf"])) {
        if(!isset($_POST["profile"]))
        {
            echo "<h3 class='dangerText'>Profile selected is null, select a profile!</h3>";
            return false;
        }
        $userProfName = InputClean($_POST["profile"]);
        if($_POST["profile"] === "All")
        {
            $_SESSION["allProfSel"]="All";
            unset($_SESSION["userProfId"]);
            header('Location:index.php');
        }
        elseif(checkProfileByName($userProfName) != 0){
            $userProfId = getProfIdByName($userProfName);
            if(!checkIfProfileBelongs($userProfId,$_SESSION["AllUserProfiles"])){
                GenErrorMsg();
                unset($_SESSION["userProfId"]);
                ErrorLog("Profile id that does not belong to user ".$_SESSION["username"]." was attempted to selected.");
                return false;
            }
            $_SESSION["userProfId"]=$userProfId;
            if ($userProfId == null) {
                GenErrorMsg();
                ErrorLog("User profile id could not be retrieved");
                return false;
            }
            header('Location:index.php');
        } else {
            echo "<h3 class='dangerText'>Profile selected does not exist</h3>";
            ErrorLog($_SESSION["username"] . " attempted to view a profile that does not exist. Profile $userProfName");
            return false;
        }
    }

    if(isset($_SESSION["userProfId"])){
        $profileName=getProfNameById($_SESSION["userProfId"]);
        $_SESSION["uploadProf"]=$_SESSION["userProfId"];
        echo "<h1 class='homeH1'>".$profileName."</h1>";
        echo "<a href='?viewHistory' class='histoLink'>View History</a>";
        DisplayDbData($_SESSION["userProfId"]);
    }
    elseif(isset($_SESSION["allProfSel"]))
    {
        echo "<h1 class='homeH1'>".$_SESSION["allProfSel"]."</h1>";
        echo "<a href='?viewHistory' class='histoLink'>View History</a>";
        echo "<table id='fileTable' text-align='center'>";
        echo "<thead>";
        echo "<tr>";
        echo '<th>FILE ID</th><th>FILE NAME</th><th>DESCRIPTION</th><th>DELETION TIME</th><th>UPLOAD TIME</th><th>DELETE & EDIT</th><th>UPLOAD BY</th>';
        echo "</tr>";
        echo "</thead>";
        foreach($_SESSION["AllUserProfiles"] as $profId)
        {
            DisplayDbDataForAll($profId);
        }
        echo "</table>";
        $_SESSION["uploadProf"]=$userProfId;
    }
    else
    {
        $profileName=getProfNameById($userProfId);
        $_SESSION["uploadProf"]=$userProfId;
        echo "<h1 class='homeH1'>".$profileName."</h1>";
        echo "<a href='?viewHistory' class='histoLink'>View History</a>";
        DisplayDbData($userProfId);
    }

    if(isset($_GET["viewHistory"]))
    {
        if(isset($_SESSION["userProfId"])) {
            header("Location:History.php?ViewHistory=" .$_SESSION["userProfId"]);
        }
        elseif(isset($_SESSION["allProfSel"]))
        {
            header("Location:History.php?ViewHistory=" ."All");
        }
        else{
            header("Location:History.php?ViewHistory=" .$userProfId);
        }
    }

    unset($_SESSION["fileDesc"]);
    unset($_SESSION["delTime"]);
    unset($_SESSION["receiverEmail"]);
    session_write_close();
}

function DisplayDbDataForAll($prof){
    global $hashDropDb;
    global $fileTableName;
    $stmt=$hashDropDb->prepare("SELECT * FROM $fileTableName WHERE profileID =:prof AND deleted=0;");
    $stmt->bindParam(":prof",$prof);
    if($stmt->execute()) {
        foreach ($stmt as $row) {
            DisplayDataRows($row['fileId'],$row['fileName'],$row['filedescr'],$row['daystildel'],$row['uploadTime'],$row['uploadUserID']);
        }
        return true;
    }else{
        ErrorLog("Database query to display files stored in database could not execute!");
        return false;
    }
}

function DisplayDataRows($fileId,$fileName,$fileDescr,$fileDayDel,$fileUploadTime,$fileUploadUserId){
    echo "<tr>";
    echo "<td>" . $fileId . "</td>";
    echo "<td>" . $fileName . "</td>";
    echo "<td>" . $fileDescr. "</td>";
    echo "<td>".CalcDeleteDate($fileDayDel,$fileUploadTime)."</td>";
    echo "<td>" . date('Y-m-d h:i A',$fileUploadTime) . "</td>";
    echo "<td>";
    echo '<form class="inline" method="POST"><button class="button danger" type="submit" name="deleteInfo" value="' . $fileId. '"/>Delete</button></form>';
    echo '<button data-modal-target="#modal" class="button edit" type="submit" name="updateInfo" value="' . $fileId . '"/>Edit</button>';
    echo "</td>";
    echo "<td>".getUserName($fileUploadUserId)."</td>";
    echo "</tr>";
}

function ViewProfForm(){
    echo "<div class='selectdiv'>";
    echo "<form method='POST'>";
    GenerateProfilleDropForIndex();
    echo '<input type="submit" id="viewProf" name="viewProf" value="View">';
    echo "</form>";
    echo "</div>";
}

function GetProfileId(){
    global $hashDropDb;
    global $userTableName;
    $profileIdStmt=$hashDropDb->prepare("SELECT profileId FROM $userTableName WHERE username= :username;");
    $profileIdStmt->bindParam(":username",$_SESSION["username"]);

    if($profileIdStmt->execute()){
        foreach($profileIdStmt as $row){
            return $row["profileId"];
        }
    }
    else {
        ErrorLog("Database query to get profile id could not execute!");
        return false;
    }
}

function checkUserExist(){
    global $hashDropDb;
    global $userTableName;
    $checkUserStmt=$hashDropDb->prepare("SELECT username FROM $userTableName WHERE username = :username");
    $checkUserStmt->bindParam(":username",$_SESSION["username"]);
    if($checkUserStmt->execute()){
       $resultOfCheckUser=$checkUserStmt->fetchAll();
       return count($resultOfCheckUser);
    }
    else{
        ErrorLog("Database query to check that user's exist could not execute!");
        return false;
    }
}

function GetUserID(){
    global $hashDropDb;
    global $userTableName;
    $getIdStmt=$hashDropDb->prepare("SELECT userId FROM $userTableName WHERE username =:username;");
    $getIdStmt->bindParam(':username', $_SESSION["username"]);
    if($getIdStmt->execute()){
        foreach ($getIdStmt as $row){
            return $row["userId"];
        }
    }else{
        ErrorLog("Database query to get user id could not execute!");
        return NULL;
    }
}

function InsertUser($email){
    global $hashDropDb;
    global $userTableName;
    $insUserStmt=$hashDropDb->prepare("INSERT INTO $userTableName (username,email) VALUES (:username,:email);");
    $insUserStmt->bindParam(':username', $_SESSION["username"]);
    $insUserStmt->bindParam(':email', $email);
    if($insUserStmt->execute()){
        return true;
    }else{
        ErrorLog("Database query to insert user could not execute!");
        return false;
    }
}

//Function executes a query to enter data relating to file uploaded to the filesystem and takes some information as parameters  in order to insert.
function InsertIntoDB($fileDescr,$deltime,$userID,$userProfId,$receiverEmail){
    global $hashDropDb;
    global $fileTableName;
    $timeInSec=time();
    $stmt=$hashDropDb->prepare("INSERT INTO $fileTableName (fileName,uploadTime,filedescr,daystildel,sha1Hash,uploadUserID,profileID,receiverEmail) VALUES (:fileName,:uploadTime,:filedescr,:daystildel,:sha1Hash,:userID,:profileId,:email)");
    $stmt->bindParam(':fileName',$_SESSION["fileName"]);
    $stmt->bindParam(':uploadTime',$timeInSec);
    $stmt->bindParam(':filedescr',$fileDescr);
    $stmt->bindParam(':daystildel',$deltime);
    $stmt->bindParam(':sha1Hash',$_SESSION["sha1sum"]);
    $stmt->bindParam(':userID',$userID);
    $stmt->bindParam(':profileId',$userProfId);
    $stmt->bindParam(':email',$receiverEmail);

    if($stmt->execute()){
        return true;
    }
    ErrorLog("Database query to insert file could not execute!");
    return false;
}

function getProfileMessage($sessionProfile){
    global $hashDropDb;
    global $profileTableName;

    $selectStmt=$hashDropDb->prepare("select message from $profileTableName where profileId=:prof;");
    $selectStmt->bindParam(":prof",$sessionProfile);
    if($selectStmt->execute()){
        $message=$selectStmt->fetchAll();
        return $message[0]["message"];
    }
    else
    {
        ErrorLog("Database query trying to get default message from profile could not execute!");
        return false;
    }
}

function getFileExp($filename){
    global $hashDropDb;
    global $fileTableName;

    $selectStmt=$hashDropDb->prepare("select daystildel,uploadTime from $fileTableName where filename=:fileName;");
    $selectStmt->bindParam(":fileName",$filename);
    if($selectStmt->execute()){
        return $selectStmt->fetchAll();
    }
    else
    {
        ErrorLog("Database query trying to get days until deletion could not execute!");
        return false;
    }
}

function emailMessage($downLink,$sessionProfile,$fileName,$senderName,$expTime,$fileDesc){
    if(!isset($expTime)){
        return false;
    }
    $profileMsg=getProfileMessage($sessionProfile);
    if($profileMsg === false){
        return false;
    }
    $message="<html><head><title>Download Link</title></head>
    <body>
    <p> ".$profileMsg.'</p>
    <p>Download Here: <a href="'.genDownloadPath().$downLink.'">'.genDownloadPath().$downLink."</a></p>
    <p>**Your link will expire at ".$expTime.". Please download file before then!**</p>
    <p>File Description:<br>$fileDesc</p>
    <p>Thank You,<br>".$senderName."</p>
    </body>
    </html>";
    return $message;
}

function genDownloadPath(){
    global $downloadUrl;

    if($_SERVER["SERVER_PORT"] == 443 && $_SERVER["HTTPS"] == "on"){
        $protocol = "https";
    }else{
        $protocol = "http";
    }

    return $protocol."://".$_SERVER["SERVER_NAME"].$downloadUrl;
}

function SendEmail($to,$sha1sum,$fileName,$sessionProfile,$fileDesc){
    $userEmail=GetUserEmail($_SESSION["username"]);
    $fileTimes = getFileExp($fileName);
    $fileExpTime=CalcDeleteDate($fileTimes[0]["daystildel"],$fileTimes[0]["uploadTime"]);

    if(!isset($userEmail)){
        ErrorLog("Email was not retrieved for user ". $_SESSION["username"]);
        return false;
    }
    $headers[] = 'MIME-Version: 1.0';
    $headers[]= 'Disposition-Notification-To: '.$userEmail;
    $headers[] = 'Content-type: text/html; charset=iso-8859-1';
    $headers[] = 'From: '.$userEmail;
    $headers[]= 'Bcc: '.$to;
    $baseDir=getBaseFilePathByName($fileName);
    if($baseDir == null ){
        ErrorLog("Base file path could not be retrieved when trying to generate email message!");
        return false;
    }
    $downLink=$baseDir."/".$sha1sum."/".$fileName;
    $message=emailMessage($downLink,$sessionProfile,$fileName,$_SESSION["displayName"],$fileExpTime,$fileDesc);
    if($message === false){
        ErrorLog("Email message came back false!");
        return false;
    }
    if(mail($userEmail,"Download Link",$message,implode("\r\n", $headers))){
        return true;
    }else{
        ErrorLog("PHP's mail function has failed.\n");
        return false;
    }
}

function GetUserEmail($user){
    global $hashDropDb;
    global $userTableName;
    $emailStmt = $hashDropDb->prepare("select email from $userTableName where username=:usr;");
    $emailStmt->bindParam(":usr",$user);
    if($emailStmt->execute()){
        $result=$emailStmt->fetchAll();
        if(isset($result[0]["email"])) {
            return $result[0]["email"];
        }
        ErrorLog("Email for $user could not be retrieved from database");
        return false;
    }
    ErrorLog("Query to retrieve email from database has failed");
    return false;
}

function getBaseFilePathByName($fileName){
    global $hashDropDb;
    global $fileTableName;
    global $profileTableName;

    $selectStmt = $hashDropDb->prepare("select baseUpDir from $profileTableName where profileId = (select profileID from $fileTableName where fileName= :fileName);");
    $selectStmt->bindParam(":fileName",$fileName);
    if($selectStmt->execute()){
        foreach ($selectStmt as $row) {
            return $row["baseUpDir"];
        }
    }
    else{
        ErrorLog("Database query to get base file path could not execute!");
        return false;
    }
}

//Function executes the query necessary to edit the data in the db and also does the edits on the filesystem.multiple parameters are taken by this function in
//order the get the edit data and file id to actually perform this actions.
function EditDbData($fileId,$fileName,$fileDesc,$fileDel,$email){
    global $hashDropDb;
    global $fileTableName;
    if(checkFileDel($fileId) == 1)
    {
        ErrorLog($_SESSION["username"]." attempted to edit a file that has been deleted!");
        return false;
    }
    if(!CheckFilePrem($_SESSION["AllUserProfiles"], $fileId))
    {
        ErrorLog($_SESSION["username"]." attempted to edit a file that is not available to them thru their profile(s)!");
        return false;
    }
    $newFileName = $fileName;
    $newFileDesc = $fileDesc;
    $newFileTimeDel = $fileDel;

        $newFileTimeDel=intval($newFileTimeDel);
        $stmt1 = $hashDropDb->prepare("UPDATE $fileTableName SET filedescr= :filedescr1,daystildel= :timetildel1,fileName= :filename1,receiverEmail=:email WHERE fileId= :fileId;");
        $stmt1->bindParam(':fileId',$fileId);
        $stmt1->bindParam(':filename1', $newFileName);
        $stmt1->bindParam(':filedescr1', $newFileDesc);
        $stmt1->bindParam(':timetildel1', $newFileTimeDel);
        $stmt1->bindParam(':email',$email);
        $filePathToEdit=getFileDirectory($fileId);
        $sha1Path="/".getSha1Dir($fileId);
        $profilePath=getProfileBaseDir($fileId);
        if(($filePathToEdit == null || $sha1Path == null) || $profilePath == null){
            ErrorLog("One of the following has failed get file directory or get sha1 directory or get profiles base directory.");
            return false;
        }

        $filePathToEdit=$profilePath.$filePathToEdit;
        $sha1Path=$profilePath.$sha1Path;
        rename($filePathToEdit,"$sha1Path/$newFileName");
        if ($stmt1->execute()) {
            InfoLog($_SESSION["username"]." has edited a file successfully.");
            return true;
        } else {
            ErrorLog("Database query to edit file data could not execute!");
            return false;
        }

}

//Function generates the display table for database data and also query's the db for this data.
function DisplayDbData($prof){
    global $hashDropDb;
    global $fileTableName;
    $stmt=$hashDropDb->prepare("SELECT * FROM $fileTableName WHERE profileID =:prof AND deleted=0;");
    $stmt->bindParam(":prof",$prof);
    if($stmt->execute()) {
        echo "<table id='fileTable'text-align='center'>";
        echo "<thead>";
        echo "<tr>";
        echo '<th>FILE ID</th><th>FILE NAME</th><th>DESCRIPTION</th><th>DELETION TIME</th><th>UPLOAD TIME</th><th>DELETE & EDIT</th><th>UPLOAD BY</th>';
        echo "</tr>";
        echo "</thead>";
        foreach ($stmt as $row) {
            DisplayDataRows($row['fileId'],$row['fileName'],$row['filedescr'],$row['daystildel'],$row['uploadTime'],$row['uploadUserID']);
        }
        echo "</table>";
        return true;
    }else{
        ErrorLog("Database query to display files stored in database could not execute!");
        return false;
    }
}

//This function deletes files from the db and the filesystem and takes the file id as a parameter in order to complete the deletion query.
function DeleteFromDb($fileId,$usr){
    global $hashDropDb;
    global $genericFileName;
    global $fileTableName;
    if(checkFileDel($fileId) == 1)
    {
        ErrorLog($_SESSION["username"]." attempted to delete a file that has already been deleted aka archived!");
        return false;
    }
    if(!CheckFilePrem($_SESSION["AllUserProfiles"], $fileId))
    {
        ErrorLog($_SESSION["username"]." attempted to delete a file that is not available to them thru their profile(s)!");
        return false;
    }
    $deletedVal = 1;
    $stmt=$hashDropDb->prepare("UPDATE $fileTableName SET deleted=:deleted,delBy=:user WHERE fileId= :fileId;");
    $stmt->bindParam(':fileId',$fileId);
    $stmt->bindParam(':deleted',$deletedVal);
    $stmt->bindParam(':user',$usr);
    $profileDir=getProfileBaseDir($fileId);
    $dirForDel=getFileDirectory($fileId);
    $sha1DirForDel="/".getSha1Dir($fileId);

    if(($dirForDel == null || $sha1DirForDel == null) || $profileDir == null){
        ErrorLog("Could not get the required paths for deletion!");
        return false;
    }
    $dirForDel=$profileDir.$dirForDel;
    $sha1DirForDel=$profileDir.$sha1DirForDel;
    if($stmt->execute()){
        if(unlink($dirForDel)){
            InfoLog("Unlink at ".$dirForDel." has occurred");
            $symLinkCount=0;
            $filesPresent=scandir($sha1DirForDel);
            for($counter=0;$counter<count($filesPresent);$counter++) {
                if (is_link($sha1DirForDel . "/" . $filesPresent[$counter])) {
                    $symLinkCount += 1;
                }
            }
            if($symLinkCount==0){
                if(unlink($sha1DirForDel."/".$genericFileName) && rmdir($sha1DirForDel)){
                    InfoLog("Unlink at ".$sha1DirForDel."/".$genericFileName." has occurred and rmdir at ".$sha1DirForDel." has occurred.For user ".$_SESSION["username"]);
                    return true;
                }
                else {
                    ErrorLog("Unlink at ".$sha1DirForDel."/".$genericFileName." has failed or rmdir at ".$sha1DirForDel." has failed.For user ".$_SESSION["username"]);
                    return false;
                }
            }
        }
        else{
            ErrorLog("Unlink at ".$dirForDel." has failed.");
            return false;
        }
        return true;
    }
    else {
        ErrorLog("Database query to delete a file from the database could not execute!");
        return false;
    }
}



//Function is used to retrieve the file directory using the file Id parameter that gets the sha1Hash and filename stored in the db. By combining the baseDir and sha1hash and filename from the db
// the file directory is  retrieved.
function getFileDirectory($fileId){
    global $hashDropDb;
    global $fileTableName;
    $stmt=$hashDropDb->prepare("select sha1Hash,fileName from $fileTableName where fileId= :fileId;");
    $stmt->bindParam(':fileId',$fileId);
    if($stmt->execute()){
        foreach($stmt as $row){
            $fileDirectory= "/".$row['sha1Hash']."/".$row['fileName'];
            return $fileDirectory;
        }
    }
    else{
        ErrorLog("Database query to get file directory could not be executed!");
        return false;
    }
}

//Function is used to retrieve the sha1 directory using the file Id parameter that gets the sha1 stored in the db. By combining the baseDir and sha1hash from the db
// the sha1 directory is  retrieved.
function getSha1Dir($fileId){
    global $hashDropDb;
    global $fileTableName;
    $stmt=$hashDropDb->prepare("select sha1Hash from $fileTableName where fileId= :fileId;");
    $stmt->bindParam(':fileId',$fileId);
    if($stmt->execute()){
        foreach($stmt as $row){
            $fileDirectory1=$row['sha1Hash'];
            return $fileDirectory1;
        }
    }
    else{
        ErrorLog("Database query to get sha 1 directory could not be executed!");
        return false;
    }
}

function getProfileBaseDir($fileId){
    global $hashDropDb;
    global $fileTableName;
    global $profileTableName;

    $selectStmt= $hashDropDb->prepare("select baseUpDir from $profileTableName where profileId = (select profileID from $fileTableName where fileId= :fileId);");
    $selectStmt->bindParam(':fileId',$fileId);
    if($selectStmt->execute()){
        $result=$selectStmt->fetchAll();
        return $result[0]["baseUpDir"];
    }
    else{
        ErrorLog("Database query to get profile base directory could not be executed!");
        return false;
    }
}

function ConfigProfileTable(){
    global $hashDropDb;
    global $profileTableName;
    $tableExist=$hashDropDb->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='$profileTableName';");

    if($tableExist->execute()){
        $resultOfTbExist=$tableExist->fetchAll();
        if(count($resultOfTbExist)==0){
            try {
               $hashDropDb->exec("CREATE TABLE $profileTableName(profileId INTEGER PRIMARY KEY,baseUpDir TEXT,message TEXT ,defaultExpTime INTEGER,profName TEXT);");
            }catch(PDOException $e){
                ErrorLog("Table  $e $profileTableName could not be created");
                return false;
            }
        }
        return true;
    }
    else{
        ErrorLog("Database query to to check if table $profileTableName exist could not be executed");
        return false;
    }

}

function ConfigUserTable(){
    global $hashDropDb;
    global $userTableName;
    global $profileTableName;
    $tableExist=$hashDropDb->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='$userTableName';");
    if($tableExist->execute()){
        $resultOfTbExist=$tableExist->fetchAll();
        if(count($resultOfTbExist)==0){
            try {
                $hashDropDb->exec("CREATE TABLE $userTableName(userId INTEGER PRIMARY KEY,profileId INTEGER,secondaryProfiles TEXT,username TEXT NOT NULL,email TEXT,FOREIGN KEY (profileId) REFERENCES $profileTableName (profileId));");
            }catch(PDOException $e){
                ErrorLog($e."(Table $userTableName could not be created)");
                return false;
            }
        }
        return true;
    }
    else{
        ErrorLog("Database query to to check if table $userTableName exist could not be executed");
        return false;
    }
}

function ConfigFileTable(){
    global $hashDropDb;
    global $fileTableName;
    $tableExist=$hashDropDb->prepare("SELECT name FROM sqlite_master WHERE type='table' AND name='$fileTableName';");
    if($tableExist->execute()){
        $resultOfTbExist=$tableExist->fetchAll();
        if(count($resultOfTbExist)==0){
            try {
                $hashDropDb->exec("CREATE TABLE $fileTableName(fileId INTEGER PRIMARY KEY,fileName TEXT,uploadTime INTEGER ,filedescr TEXT,daystildel INTEGER ,sha1Hash TEXT,uploadUserID INTEGER,profileID INTEGER,receiverEmail TEXT,deleted INTEGER DEFAULT 0,delBy TEXT, FOREIGN KEY (uploadUserId) REFERENCES User(userId), FOREIGN KEY(profileID) REFERENCES Profile(profileId));");
            }catch(PDOException $e){
                ErrorLog($e."(Table $fileTableName could not be created)");
                return false;
            }
        }
        return true;
    }
    else{
        ErrorLog("Database query to to check if table $fileTableName exist could not be executed");
        return false;
    }
}

Main();

?>
