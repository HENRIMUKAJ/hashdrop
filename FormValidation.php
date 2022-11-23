<?php
require 'config.php';
require 'CommonlyUsedFunc.php';
if(session_status()!=2) {
    session_start();
}
Authenticate();

function MainValid(){

    if(isset($_POST["insertInfo"])) {
       $fileDesc=InputClean($_POST["fileDescription"]);
       $delTime=InputClean($_POST["timetildel"]);
       $receiverEmail=InputClean($_POST["receiverEmail"]);
       require_once 'uploadfileinfo.phtml';
    }else{
        return false;
    }

    if(!CheckDropDown($delTime)){
        ErrorLog("Deletion time given by user is invalid");
        echo "<h2 class='dangerText'>Invalid input try again!</h2>";
        return false;
    }

    if(!CheckDescription($fileDesc)){
        ErrorLog("File description given by user is invalid");
        echo "<h2 class='dangerText'>Invalid input try again!</h2>";
        return false;
    }

    $emailValidationResult=validEmailForm($receiverEmail);
    if($emailValidationResult !== true) {
        foreach ($emailValidationResult as $email) {
            ErrorLog("User entered invalid email: $email");

        }
        echo "<h2 class='dangerText'>Invalid input try again!</h2>";
        return false;
    }

    $_SESSION["fileDesc"]=$fileDesc;
    $_SESSION["delTime"]=$delTime;
    $_SESSION["receiverEmail"]=$receiverEmail;
    header("Location:fileuploadform.php");
}


if(isset($_GET["ValidateEmail"])){
    $emails=validEmailForm($_GET["ValidateEmail"]);
    if($emails !== true) {
        echo "Invalid Email(s): ";
        foreach ($emails as $invalidEmail) {
            echo $invalidEmail." ";
        }
    }
}
function validEmailForm($emails){
    $emails=explode(",",$emails);
    $inValidEmail=Array();
    for($counter=0;$counter<count($emails);$counter++){
       if (!filter_var($emails[$counter], FILTER_VALIDATE_EMAIL)) {
            $inValidEmail[$counter]=$emails[$counter];
        }
    }
    if(empty($inValidEmail)){
        http_response_code(200);
        return true;
    }
    http_response_code(400);
    return $inValidEmail;
}

function InputClean($inputData) {
    $inputData = trim($inputData);
    $inputData = stripslashes($inputData);
    $inputData = htmlspecialchars($inputData);
    return $inputData;
}

if(isset($_GET["CheckFileName"])){
    $_SESSION["CheckFileName"]=checkFileName($_GET["CheckFileName"]);
}
function checkFileName($fileName){
    $lenFileName=strlen($fileName);
    if($fileName == null && ($lenFileName <= 0 || $lenFileName >= 100 )){
        echo "File name can't be empty or longer than 100 characters!";
        http_response_code(400);
        return false;
    }
    else{
        FileNameClean($fileName);
        http_response_code(200);
        return true;
    }
}

if(isset($_GET["CheckDescription"])){
    CheckDescription($_GET["CheckDescription"]);
}
function CheckDescription($desc){
    if($desc == null || strlen($desc) >= 255){
        echo "File description can't be empty or larger that 255 characters!";
        http_response_code(400);
        return false;
    }
    else{
        http_response_code(200);
        return true;
    }
}

if(isset($_GET["CheckDropDown"])){
    CheckDropDown(intval($_GET["CheckDropDown"]));
}
function CheckDropDown($timeNum){
    if($timeNum ==null || !is_numeric($timeNum)){
        echo "Only numbers can be entered!";
        http_response_code(400);
        return false;
    }
    elseif ($timeNum == 0){
        echo "0 is not a valid time entry!";
        http_response_code(400);
        return false;
    }
    elseif ($timeNum <1  || $timeNum >=366)
    {
        echo "Number of days entered can't exceed 365 or be less than 0!";
        http_response_code(400);
        return false;
    }
    else{
        http_response_code(200);
        return true;
    }
}

if(isset($_GET["Edit"])){
    fillEditForm($_GET["Edit"]);
}

function fillEditForm($fileId){
    global $hashDropDb;
    global $fileTableName;

    $stmt=$hashDropDb->prepare("SELECT * FROM $fileTableName  WHERE fileId= :fileId;");
    $stmt->bindParam(':fileId',$fileId);
    if($stmt->execute()){
        foreach($stmt as $row){
            $oldFileName=$row['fileName'];
            $oldFileDesc=$row['filedescr'];
            $oldFileTimeDel=$row['daystildel'];
            $fileId=$row['fileId'];
            $oldFileEmail=$row["receiverEmail"];
        }
        if($fileId == null || $oldFileTimeDel == null){
            echo "<span class='dangerText'>Contact the help-desk!</span>";
            ErrorLog("var fileid = ".$fileId." var oldfiletimedel =".$oldFileTimeDel. "one of the following is null and the edit form cannot autofill");
            http_response_code(400);
            return false;
        }
        if($oldFileName == null || $oldFileDesc == null || $oldFileEmail == null){
            echo "<span class='dangerText'>Contact the help-desk!</span>";
            ErrorLog("var oldfilename=".$oldFileName."var oldfiledesc=".$oldFileDesc."var oldfileemail=".$oldFileEmail."is null edit form data cannot be autofilled");
            http_response_code(400);
            return false;
        }
        generateEditForm($fileId,$oldFileName,$oldFileDesc,$oldFileTimeDel,$oldFileEmail);
        return $stmt->fetchAll();
    }
    else
    {
        ErrorLog("Database query to fill the edit form could not execute!");
        return false;
    }
}

if(isset($_GET["fileSize"])){
    if(CheckAvalDiskSpace($_GET["fileSize"])){
        $_SESSION["NotEnoughSpace"] = false;
        http_response_code(200);
    }
    else
    {
        $_SESSION["NotEnoughSpace"] = true;
        http_response_code(400);
        return false;
    }
}
function CheckAvalDiskSpace($file){
    global $baseDir1;
    $avalSpace=disk_free_space($baseDir1);
    if($file >= $avalSpace) {
        echo "<h3 class='dangerText'>Files cannot be uploaded at the moment not enough space.Contact the help-desk!</h3>";
        ErrorLog("not enough space in the upload directory!");
        return false;
    }
    return true;
}

if(isset($_GET["GetSpace"]))
{
    $resultOfFree=FreeSpace();
    if(is_numeric($resultOfFree))
    {
       echo '<span class="textColor dangerText">Space available: '.$resultOfFree.'GB</span>';
    }
    else {
        echo '<span class="dangerText">Space available: ' . $resultOfFree . '</span>';
    }
}
function FreeSpace()
{
    global $baseDir1;
    $space = disk_free_space($baseDir1);
    if(is_numeric($space))
    {
        if ($space >= 1 ) {
            return intval($space / pow("1024", 3));
        }
        else{
            return "Less than 1GB of space.";
        }
    }
    return "No space available!";
}

//Function generates the form that the user will see when trying to edit a database row and takes 3 parameters to autofill the fields also.
function generateEditForm($fileId,$fileName,$fileDesc,$fileTimeDel,$receiverEmail){
    echo "<form class='click' method='POST'>";
    echo "<div><label for='filename1'>File Name</label>".'<input type="text" class="inputField nameinput" id="filename1" name="filename1" value="'.$fileName.'">'."<br><span class='dangerText filenameerror'></span></div>";
    echo "<div><label for='receiverEmail'>Recepient Email(s)</label>".'<input type="text" class="inputField emailinput" id="receiverEmail" name="receiverEmail" value="'.$receiverEmail.'">'."<br><span class='dangerText emailserror'></span></div>";
    echo '<div id="replace"><label for="timetildel1">Day(s) Until Expiration</label><select name="timetildel1" class="inputField dropinput" id="timetildel1">';
    echo '<option selected="selected" value="'.$fileTimeDel.'">'.$fileTimeDel." Day(S)".'</option>';
    echo '<option value="1">1 Day</option>';
    echo '<option value="7">1 Week</option>';
    echo '<option value="30">1 Month</option>';
    echo '<option value="365">1 Year</option>';
    echo '<option value="Custom Input">Custom Input</option>';
    echo '</select><img  hidden id="svgImage" class="imageAlign" src="image/list.svg" alt="go back to drop down"><br><span id="repSpan" class="dangerText dropdownerror"></span></div>';
    echo "<div><label for='filedescr1'>File Description<br><em>**This description will be sent to the email recipient**</em></label>".'<textarea type="text" id="filedescr1" class="inputField descinput" name="filedescr1">'.$fileDesc.'</textarea>'."<br><span class='dangerText filedescerror'></span></div>";
    echo "<div>".'<input  hidden type="text" id="fileId" name="fileId" value="'.$fileId.'">'."</div>";
    echo '<input id="Edit" value="Submit" name="Edit" type="submit">';
    echo "</form>";
}

MainValid();

?>