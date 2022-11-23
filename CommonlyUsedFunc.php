<?php
include 'config.php';

function ErrorLog($message){
    openlog("Hash Drop: ",LOG_PID,LOG_SYSLOG);
    syslog(LOG_ERR,"ERROR: ".$message);
}

function InfoLog($message){
    openlog("Hash Drop: ",LOG_PID,LOG_SYSLOG);
    syslog(LOG_INFO,"INFO: ".$message);
}

function GetAllUserProfs($username){
    global $hashDropDb;
    global $userTableName;

    $selectStmt=$hashDropDb->prepare("select profileID,secondaryProfiles from $userTableName where username=:username;");
    $selectStmt->bindParam(":username",$username);
    if($selectStmt->execute()){
        return $selectStmt->fetchAll();
    }
    else{
        ErrorLog("Database query to get all user profiles could not be executed");
        return false;
    }
}

function PutProfilesInArr($username){

    $userProfiles=GetAllUserProfs($username);
    $counter=0;
    $profilesArray=null;
    if($userProfiles){
        foreach ($userProfiles as $allprofiles)
        {
            $profilesArray[$counter]=$allprofiles["profileId"];
            $counter++;
            if($allprofiles["secondaryProfiles"] != null)
            {
                $secProfs=explode(",",$allprofiles["secondaryProfiles"]);
                for($itr=0;$itr<count($secProfs);$itr++)
                {
                    $profilesArray[$counter]=$secProfs[$itr];
                    $counter++;
                }
            }
        }
        return $profilesArray;
    }
    else
    {
        ErrorLog("Could not put ".$username." profiles in array for assigned profiles session variable.");
        return false;
    }
}

function LdapUserEmail($user){
    $ldapConn= ldap_connect("");
    $ldapBind = ldap_bind($ldapConn,"","");
    $attrForLdap=array("mail");
    $ldapSearchResult=ldap_search($ldapConn,"OU=User_Accounts,DC=jaxport,dc=com","samAccountName={$user}", $attrForLdap);
    $info = ldap_get_entries($ldapConn, $ldapSearchResult);
    if($info["count"] == 0){
        ErrorLog("Ldap search was done but nothing was returned!");
        return false;
    }
    elseif(isset($info[0]["mail"][0])){
        InfoLog("Email for $user has been found!");
        return $info[0]["mail"][0];
    }
    else{
        ErrorLog("Ldap search for $user email retrieval has failed!");
        return false;
    }
}

function checkFileDel($fileId)
{
    global $hashDropDb;
    global $fileTableName;

    $checkFile=$hashDropDb->prepare("select deleted from $fileTableName where fileId=:fileId;");
    $checkFile->bindParam(":fileId",$fileId);
    if($checkFile->execute())
    {
        $resultStmt=$checkFile->fetchAll();
        if(!is_null($resultStmt[0]["deleted"]))
        {
            return $resultStmt[0]["deleted"];
        }
        else
        {
            ErrorLog("the result of the query came back null meaning file id was never set!");
            return false;
        }
    }
    ErrorLog("Database query for checkFilDel could not execute!");
    return false;
}

function getUserName($usrId){
    global $hashDropDb;
    global $userTableName;

    $userSelect=$hashDropDb->prepare("select username from $userTableName where userId =:usrId");
    $userSelect->bindParam(":usrId",$usrId);
    if($userSelect->execute()){
        $result=$userSelect->fetchAll();
        return $result[0]["username"];
    }
    ErrorLog("Username retrieval using user id could not occur");
    return false;
}

function CheckFilePrem($userAssignedIds,$fileId)
{
    global $hashDropDb;
    global $fileTableName;

    $fileProfId = $hashDropDb->prepare("select profileID from $fileTableName where fileId=:fileId");
    $fileProfId->bindParam(":fileId",$fileId);
    if($fileProfId->execute())
    {
        foreach ($fileProfId as $profId)
        {
            foreach ($userAssignedIds as $userId)
            {
                if($userId == $profId["profileID"])
                {
                    return true;
                }
            }
        }
        return false;
    }
    else
    {
        ErrorLog("Query checking for file permission could not execute.");
        return false;
    }

}

//Function calculates the deletion time using 2 parameters to compute the time in seconds. It also returns the deletion time in seconds once computed.
function CalcDeleteDate($timeTilDelete,$fileUploadDate){
    $day=86400;
    $totalSecTilDel=($timeTilDelete*$day)+$fileUploadDate;
    if(isset($totalSecTilDel)) {
        return date('Y-m-d h:i A', $totalSecTilDel);
    }
    ErrorLog("Could not calculate the deletion date for files!");
    return false;
}

function checkIfProfileBelongs($profId,$profilesArr)
{
    for($counter=0;$counter<count($profilesArr);$counter++)
    {
        if($profId==$profilesArr[$counter])
        {
            return true;
        }
    }
    ErrorLog("A profile id that does not belong to user ".$_SESSION["username"]." was found.");
    return false;
}

function GenErrorMsg(){
    echo "<h3 class='dangerText'>Error, Please contact the Help-Desk at 3008!</h3>";
}

function getProfNameById($profId){
    global $hashDropDb;
    global $profileTableName;

    $selectStmt = $hashDropDb->prepare("Select profName from $profileTableName where profileId=:profId;");
    $selectStmt->bindParam(":profId",$profId);
    if($selectStmt->execute()){
        $result=$selectStmt->fetchAll();
        if(empty($result)){
            return "";
        }
        return $result[0]["profName"];
    }
    ErrorLog("Database query to get profile name by id could not occur could not be executed");
    return false;
}
function GenerateProfilleDropForIndex(){
    echo "<label>Profile";
    echo '<select class="inputField" name="profile" id="profile">';
    echo "<option>All</option>";
    GenerateProfilleDrop();
    echo "</select>";
    echo "</label>";
}


function GenerateProfilleDrop(){
    $userInfo=GetAllUserProfs($_SESSION["username"]);
    if(!empty($userInfo)){
        echo '<option selected>'. getProfNameById($userInfo[0]["profileId"]) . '</option>';
        $secondaryProfs=explode(",",$userInfo[0]["secondaryProfiles"]);
        if(!empty($secondaryProfs)){
            foreach($secondaryProfs as $profileId){
                if($profileId != ""){
                    echo '<option>'.getProfNameById($profileId).'</option>';
                }
            }
        }
    }
    else
    {
    echo "Contact IT to assign a profile";
    ErrorLog($_SESSION['username']. "Does not have a profile Id!");
    return false;
    }
}

function ConvertIdToString($secondaryId){
    $recombNameString=array();
    $secondaryId=explode(",",$secondaryId);
    for($count = 0;$count<count($secondaryId);$count++){
        $recombNameString[$count]=getProfNameById($secondaryId[$count]);
    }
    return implode(",",$recombNameString);
}

function FileNameClean($fileName){
    $pattern='/[^\w\.-]/';
    $replaChar="_";
    return preg_replace($pattern,$replaChar,$fileName);
}

function getProfIdByName($profName){
    global $hashDropDb;
    global $profileTableName;

    $selectStmt = $hashDropDb->prepare("Select profileId from $profileTableName where profName = :profName;");
    $selectStmt ->bindParam(":profName",$profName);
    if($selectStmt->execute()){
        foreach ($selectStmt as $row){
            return $row["profileId"];
        }
    }
    ErrorLog("Database query to get profileId by profile name could not be executed");
    return false;
}

function checkProfileByName($profileName){
    global $hashDropDb;
    global $profileTableName;

    $checkStmt=$hashDropDb->prepare("SELECT profName FROM $profileTableName WHERE profName =:profileName;");
    $checkStmt->bindParam(":profileName",$profileName);
    if($checkStmt->execute()){
        $resultOfStmt=$checkStmt->fetchAll();
        return count($resultOfStmt);
    }
    ErrorLog("Database query to check whether a profile exist could not be executed");
    return false;
}

function checkSecondaryProf($usr,$profId){
    $result=GetSecProf($usr);
    $resultOfExp=explode(",",$result);
    foreach($resultOfExp as $profId1){
        if($profId == $profId1){
            return false;
        }
    }
    return true;
}

function getDefaultTime($profile){
    global $hashDropDb;
    global $profileTableName;

    $getExp=$hashDropDb->prepare("select defaultExpTime from $profileTableName where profileId=:profId");
    $getExp->bindParam(":profId",$profile);
    if($getExp->execute()){
        $expTime=$getExp->fetchAll();
        return $expTime[0]["defaultExpTime"];
    }
    ErrorLog("Database query to get default expiration times could not be executed");
    return false;
}

function checkUsrProf($usr){
    global $hashDropDb;
    global $userTableName;

    $Prof= $hashDropDb->prepare("SELECT profileId FROM $userTableName WHERE username =:usr;");
    $Prof->bindParam(":usr",$usr);
    if($Prof->execute()){
        $result=$Prof->fetchAll();
        return $result[0]["profileId"];
    }
    ErrorLog("Database query to check user profiles could not be executed");
    return false;
}

function GetSecProf($usr){
    global $hashDropDb;
    global $userTableName;

    $getSecProf= $hashDropDb->prepare("SELECT secondaryProfiles FROM $userTableName WHERE username =:usr;");
    $getSecProf->bindParam(":usr",$usr);
    if($getSecProf->execute()){
        $result=$getSecProf->fetchAll();
        return $result[0]["secondaryProfiles"];
    }
    ErrorLog("Database query to get secondary user profiles could not be executed");
    return false;
}

?>