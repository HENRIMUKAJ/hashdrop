<?php
require_once 'config.php';

function Authenticate()
{

    if (isset($_SESSION['username']))
    {
        return true;
    }
    else
    {
        session_unset();
        session_destroy();
        header("Location: logon.php");
        return false;
    }
}



function PrintSysLog($message)
{
    global $suppress_log;

    //only print if var to suppress log is not activated
    if ($suppress_log != 1)
    {
        //prints message to syslog
        syslog(LOG_INFO, $message);
    }
}



function QuerySingleRowByUsername($myPDO, $username, $columnName)
{
    global $table_name;
    $result = $myPDO->query("SELECT $columnName FROM $table_name 
    WHERE Username = '$username';
    ");

    if ($result)
    {
        $result = $result->fetch();
        //PrintLogonTestArray($result);
        if($result == null){ return null; }
    }
    else
    {
        //print error to syslog
        global $login_error_log_name;
        openlog($login_error_log_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
        PrintSysLog("HashDrop Website, STDERR, database error: query value for column '$columnName' for user '$username'");
        $err = $myPDO->errorInfo(); $error = "";
        foreach($err as $e) { $error = $error . $e . ", "; }
        PrintSysLog("HashDrop Website, STDERR, database error: $error");
        closelog();

        PrintLogonTestArray($myPDO->errorInfo());
        return false;
    }

    $return = "";

    foreach($result as $row)
    {
        $return = $row;
        break;
    }

    return $return;
}

function UpdateRow($myPDO, $username, $failedTries)
{
    global $table_name;
    //find out the following to values to insert/update table
    $ipaddress = getenv("REMOTE_ADDR"); //ip of remote client
    $hostname = gethostbyaddr($ipaddress); //hostname of remote client
    $date = date("m-d-Y h:i:s A"); //current date
    $time = time();
    PrintLogonTestMsg("$username | $hostname | $ipaddress | $date | $time | $failedTries");

    //update info for input username
    $result = $myPDO->exec(
        "UPDATE $table_name 
        SET 'Hostname' = '$hostname',
            'IpAddress' = '$ipaddress',
            'Date' = '$date',
            'Timestamp' = '$time',
            'FailedLogins' = '$failedTries'
        WHERE Username = '$username';"
    );

    if ($result === false || $result === null || $result == 0)
    {
        //print error to syslog
        global $login_error_log_name;
        openlog($login_error_log_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
        PrintSysLog("HashDrop Website, STDERR, database error: update row for user '$username'");
        $err = $myPDO->errorInfo();        $error = "";
        foreach($err as $e) { $error = $error . $e . ", "; }
        PrintSysLog("HashDrop Website, STDERR, database error: $error");
        closelog();

        PrintLogonTestMsg("Error updating row!");
        PrintLogonTestArray($myPDO->errorInfo());
        return false;
    }
    else
    {
        //PrintLogonTestMsg("updaterow: updated: $result");
        PrintLogonTestMsg("Row succesfully updated!");
        return true;
    }
}

function InsertRow($myPDO, $username, $failedTries)
{
    global $table_name;
    global $login_error_log_name;
    //find out the following to values to insert/update table
    $ipaddress = getenv("REMOTE_ADDR"); //ip of remote client
    $hostname = gethostbyaddr($ipaddress); //hostname of remote client
    $date = date("m-d-Y h:i:s A"); //current date
    $time = time();
    PrintLogonTestMsg("$username | $hostname | $ipaddress | $date | $time | $failedTries");

    //insert the data about failed login into table
    $result = $myPDO->exec(
        "INSERT INTO $table_name
         ('Username', 'Hostname', 'IpAddress', 'Date', 'Timestamp', 'FailedLogins')
         VALUES ('$username','$hostname','$ipaddress','$date','$time','$failedTries');"
    );

    if ($result === false)
    {
        //print error to syslog
        openlog($login_error_log_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
        PrintSysLog("HashDrop Website, STDERR, database error: insert row for user '$username'");
        $err = $myPDO->errorInfo(); $error = "";
        foreach($err as $e) { $error = $error . $e . ", "; }
        PrintSysLog("HashDrop Website, STDERR, database error: $error");
        closelog();

        PrintLogonTestMsg("Error inserting row!");
        PrintLogonTestArray($myPDO->errorInfo());
        return false;
    }
    else
    {
        PrintLogonTestMsg("Row succesfully inserted!");
        return true;
    }
}

function UpdateOrInsertRow($myPDO, $username, $failedTries)
{
    //find out if username is in table
    $columnName = "Count(*)";
    $recordInTable = QuerySingleRowByUsername($myPDO, $username, $columnName);
    $ret = false;

    if($recordInTable === false)
    {
        PrintLogonTestMsg("Error quering table!");
        //QUIT
        return false;
    }

    PrintLogonTestMsg("row count in table : $recordInTable");
    //update row if username in table else insert new row
    if($recordInTable == null || $recordInTable == 0)
    {
        //insert new row
        $ret = InsertRow($myPDO, $username, $failedTries);
    }
    else
    {
        //update existing row
        $ret = UpdateRow($myPDO, $username, $failedTries);
    }

    return $ret;
}

function CheckIfTableExists($myPDO)
{
    global $table_name;
    global $login_error_log_name;
    $result = $myPDO->exec(
        "CREATE TABLE if not exists $table_name
            (Id INTEGER PRIMARY KEY AUTOINCREMENT NOT NULL,
            Username TEXT NOT NULL,
            Hostname TEXT NULL,
            IpAddress TEXT NULL, 
            Date TEXT NOT NULL,
            Timestamp TEXT NOT NULL,
            FailedLogins INTEGER NOT NULL);"
    );

    //return "result:" + $result;
    if ($result === false)
    {
        //print error to syslog
        openlog($login_error_log_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
        PrintSysLog("HashDrop Website, STDERR, database error: check if table exists");
        $err = $myPDO->errorInfo(); $error = "";
        foreach($err as $e) { $error = $error . $e . ", "; }
        PrintSysLog("HashDrop Website, STDERR, database error: $error");
        closelog();
        PrintLogonTestMsg("Error checking if table exists!");
        PrintLogonTestArray($myPDO->errorInfo());
        return false;
    }
    else
    {
        //PrintLogonTestMsg("Table succesfully checked!");
        return true;
    }
}

function PrintLogonTestMsg($message)
{
    global $disableErrorPrint;
    if ($disableErrorPrint != 0) {return null;}
    echo $message . "<br>";
}

function PrintLogonTestArray($message)
{
    global $disableErrorPrint;
    if ($disableErrorPrint != 0) {return null;}
    print_r($message);
    echo "<br>";
}

function CheckGroupPermission($username, $password, $inputGroup)
{
    //get all groups the user belongs to
    $usr_groups = get_groups($username, $password);

    //variable to indicate group was found
    $found = 0;

    //iterate to all groups
    foreach($usr_groups as $group)
    {
        // echo $group . "<br>";
        //compare if allowed group is one of the groups the user is a part of
        if(strpos(strtolower($group), $inputGroup) !== false)
        {
            //indicate allowed group was found
            return true;
            // echo "found <br>";
        }
    }
    // if group was not found return false;
    return false;
}

?>