<?php
require 'config.php';
if(session_status()!=2) {
    session_start();
}
Authenticate();

function main(){
    require_once 'FormValidation.php';

    if(isset($_GET["ViewHistory"]))
    {
        $profileId =InputClean($_GET["ViewHistory"]);

        if($profileId =="All")
        {
            if(isset($_SESSION["AllUserProfiles"]))
            {
                foreach ($_SESSION["AllUserProfiles"] as $profId)
                {
                    echo "<h1>History Of : ".getProfNameById($profId)."</h1>";
                    GenHistoTable($profId);
                }
            }
            return true;
        }

        if(!checkIfProfileBelongs($profileId,$_SESSION["AllUserProfiles"]))
        {
            GenErrorMsg();
            return false;
        }

        $profileId = intval($profileId);
        $profileName=getProfNameById($profileId);

        if(checkProfileByName($profileName) == 0){
            Echo "Profile not found";
            return false;
        }

        echo "<h1>History Of : ".$profileName."</h1>";
        GenHistoTable($profileId);
    }
    else
    {
        ErrorLog("View History get request was not set!");
       return false;
    }
}

function GenHistoTable($prof){
    $histoDisp=DisplayHistory($prof);

    if($histoDisp === false)
    {
       echo "<h3 class='dangerText'>History table could not be generated!</h3>";
       ErrorLog("History table could not be generated");
       return false;
    }
    if($histoDisp == null){
        echo "<h3 class='dangerText marginLeft'><em>Table has no history.</em></h3>";
        return false;
    }

    echo "<table id='fileTable'text-align='center'>";
    echo "<thead>";
    echo "<tr>";
    echo '<th>FILE ID</th><th>FILE NAME</th><th>SHA1SUM</th><th>DELETION TIME</th><th>UPLOAD TIME</th><th>UPLOAD BY</th><th>DELETED</th><th>DELETED BY</th>';
    echo "</tr>";
    echo "</thead>";
    foreach ($histoDisp as $row){
        echo "<tr>";
        echo "<td>".$row["fileId"]."</td>";
        echo "<td>".$row["fileName"]."</td>";
        echo "<td>".$row["sha1Hash"]."</td>";
        echo "<td>".CalcDeleteDate($row["daystildel"],$row["uploadTime"])."</td>";
        echo "<td>" .date('Y-m-d h:i A',$row["uploadTime"]). "</td>";
        echo "<td>".getUserName($row["uploadUserID"])."</td>";
        echo "<td>".humReadStat($row["deleted"])."</td>";
        echo "<td>".niceDelByDisp($row["delBy"])."</td>";
        echo "</tr>";
    }
    echo  "</table>";
}

function niceDelByDisp($delBy)
{
    if(is_null($delBy))
    {
        return "N/A";
    }
    else
    {
        return $delBy;
    }
}

function humReadStat($delStat)
{
    if($delStat == 1)
    {
        return "yes";
    }
    if($delStat ==0)
    {
        return "no";
    }
}

function DisplayHistory($prof){
    global $hashDropDb;
    global $fileTableName;

    $histStmt=$hashDropDb->prepare("SELECT * FROM $fileTableName where profileID=:prof");
    $histStmt->bindParam(":prof",$prof);
    if($histStmt->execute())
    {
        return $histStmt->fetchAll();
    }
    else
    {
        ErrorLog("Database query to display file history stored in database could not execute!");
        return false;
    }
}

main();