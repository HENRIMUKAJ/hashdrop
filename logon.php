<?php

//file with global definitions
require 'config.php';

//handle page requests
ProcessPageRequest();

//global variable
$user_message_displayed = 0;

function AuthenticateUser($username, $password)
{
    $response = LDAPauth($username, $password);
    //$response = NULL;
    if ($response != NULL)
    {
        //initialise session variables
        $_SESSION["username"] = $response[0];
        $_SESSION["displayName"] = $response[1];
        $_SESSION["firstName"] = $response[2];
        // echo $_SESSION["username"] . $_SESSION["displayName"]. $_SESSION["firstName"];

        $ipaddress = getenv("REMOTE_ADDR");
        $hostname = gethostbyaddr($ipaddress);
        $u = $_SESSION['username'];

        //output log to know who logged in from where
        $update_log = openlog('user_info_login_log', LOG_PID, LOG_LOCAL0);
        PrintSysLog("employee info admin console, $u logged in from $hostname ($ipaddress)");
        closelog();

        return true;
    }
    else
    {
        return false;
    }
}

function DisplayLoginPage($message="")
{
    require_once 'logon.html';
}

function LDAPauth($username, $password)
{
    //open syslog
    global $login_error_log_name;
    openlog($login_error_log_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);

    //define constant variables
    define('DOMAIN_FQDN', 'jaxport.com');
    define('LDAP_SERVER', 'vmdc2.jaxport.com');
    define('PROTOCOL', 'ldap');

    $user = strip_tags($username) .'@'. DOMAIN_FQDN; //name of user under domain
    $pass = stripslashes($password); //password
    $conn = ldap_connect(PROTOCOL . "://". LDAP_SERVER ."/");   //connect to AD server\
    $user_ip = $_SERVER["REMOTE_ADDR"];

    if (!$conn)
    {
        $err = 'Could not connect to LDAP server<br>';
        echo $err;
        sleep(1);
        return null;
    }
    // else //procced if connected
    // {
    //set options
    ldap_set_option($conn, LDAP_OPT_PROTOCOL_VERSION, 3);
    ldap_set_option($conn, LDAP_OPT_REFERRALS, 0);

    //check amount of login attempts before authenticating
    //query the number of rows the alleged user appears on the table
    global $myPDO;

    //create database if it does not exits
    global $database_name;
    $database = new SQLite3("data/$database_name", SQLITE3_OPEN_READWRITE | SQLITE3_OPEN_CREATE);

    //check if table exits and if not create it
    $ret = CheckIfTableExists($myPDO);

    if ($ret === false)
    {
        PrintLogonTestMsg("Database error! Unable to check or create table;");
        sleep(1);
        return null;
    }

    $columName = "FailedLogins";
    $failedTries = QuerySingleRowByUsername($myPDO, $username, $columName);
    PrintLogonTestMsg("Previous fails:$failedTries");

    if ($failedTries === null)
    {
        //No data was not found for that username therefore username has 0 failed tries
        $failedTries = 0;
    }
    elseif ($failedTries === false)
    {
        PrintLogonTestMsg("Database error! Unable to get number of failed attempts.");

        // global $user_message_displayed;
        // DisplayLoginPage($message="<div id='login_message_container'>
        // <p id='login_message'>Database error! Please contact IT.</p></div>");
        // $user_message_displayed = 1;
        sleep(1);
        return null;
    }

    global $backoff_threshold, $threshold;
    //if threshold was exceed then stop trying to authenticate account
    if ($failedTries >= $threshold)
    {
        global $user_message_displayed;
        DisplayLoginPage($message="<div id='login_message_container'>
        <p id='login_message'>This account has exceed the limit of login attempts.
        Please contact IT.</p></div>");
        $user_message_displayed = 1;

        sleep(1);
        return null;
    }

    //if failed to login enough times then stop authentication for that account temporarly
    //until punishment time is over
    if ($failedTries >= $backoff_threshold)
    {
        $base = 4;
        $exponent = 1; //starts at one
        $punishment_duration = 0;

        //get timestamp of last login attempt
        $columnName = "Timestamp";
        $timestamp = QuerySingleRowByUsername($myPDO, $username, $columnName);
        $time = time();

        //something went wrong
        if ($timestamp === false || $timestamp === null)
        {
            PrintLogonTestMsg("Database error! Unable to get timestamp.");
            // global $user_message_displayed;
            // DisplayLoginPage($message="<div id='login_message_container'>
            // <p id='login_message'>Database error! Please contact IT.</p></div>");
            // $user_message_displayed = 1;
            sleep(1);
            return null;
        }

        for ($i = $backoff_threshold; $i <= $threshold; $i++)
        {
            if ($failedTries == $i)
            {
                $punishment_duration = pow($base, $exponent);
                PrintLogonTestMsg("base: $base exponent: $exponent " .
                    "duration: $punishment_duration seconds" . "<br>");
            }
            $exponent++;
        }

        $lastdate = date("m-d-Y h:i:s A", $timestamp);
        //calculate timestamp of when punishment will be over
        $timePunOver = $timestamp + $punishment_duration;
        //calculate seconds to wait
        $timeLeft = $timePunOver - $time;
        //calculate date punishment will be over
        $datePunOver = date("m-d-Y h:i:s A", $timePunOver);

        if ($time < $timePunOver)
        {
            PrintLogonTestMsg("lastlogindate: $lastdate");
            PrintLogonTestMsg("date over: $datePunOver");
            PrintLogonTestMsg("lastlogintime: $timestamp");
            PrintLogonTestMsg("time over: $timePunOver");
            PrintLogonTestMsg("time given: $punishment_duration");
            PrintLogonTestMsg("time left: $timeLeft seconds <br>");

            $unit = "seconds";

            if ($timeLeft > 86400)
            {
                $timeLeft = $timeLeft / 60 / 60 / 24;
                $unit = "days";
            }
            elseif ($timeLeft > 3600)
            {
                $timeLeft = $timeLeft / 60 / 60;
                $unit = "hours";
            }
            elseif ($timeLeft > 60)
            {
                $timeLeft = $timeLeft / 60;
                $unit = "minutes";
            }

            $timeLeft = number_format($timeLeft,2);

            global $user_message_displayed;
            DisplayLoginPage($message="<div id='login_message_container'>
            <p id='login_message'>This account was temporarly locked. 
            Please try again after $timeLeft $unit or contact IT.</p></div>");
            $user_message_displayed = 1;

            sleep(1);
            return null;
        }
        else
        {
            PrintLogonTestMsg("The blocking period of $punishment_duration seconds for this account
            was expired $datePunOver in. Login restriction has been lifted. <br>");
        }
    }

    ////////////////////////////////////////////////////////////////////////////////////////
    //authenticate user to AD
    ///////////////////////////////////////////////////////////////////////////////////////
    $bind = @ldap_bind($conn, $user, $pass);

    //get errors
    ldap_get_option($conn, LDAP_OPT_DIAGNOSTIC_MESSAGE, $extended_error);
    if (!empty($extended_error))
    {
        //print failed login message to syslog
        openlog($login_error_log_name, LOG_PID | LOG_PERROR, LOG_LOCAL0);
        $ipaddress = getenv("REMOTE_ADDR"); //ip of remote client
        $hostname = gethostbyaddr($ipaddress); //hostname of remote client
        PrintSysLog("employee info admin console, $username failed to login from $hostname ($ipaddress)");
        closelog();

        PrintLogonTestMsg("Login failed!");
        //echo "$extended_error";
        $errno = explode(',', $extended_error); $errno = $errno[2];
        $errno = explode(' ', $errno); $errno = $errno[2]; $errno = intval($errno);
        //echo "$errno";

        //increment number of failed attempts
        $failedTries = $failedTries + 1;

        $ret = UpdateOrInsertRow($myPDO, $username, $failedTries);

        if ($ret === false)
        {
            PrintLogonTestMsg("Database error! Unable to update or insert row.");

            // global $user_message_displayed;
            // DisplayLoginPage($message="<div id='login_message_container'>
            // <p id='login_message'>Database error! Please contact IT.</p></div>");
            // $user_message_displayed = 1;

            sleep(1);
            return null;
        }

        //delay user control for a second
        sleep(1);
    }
    elseif ($bind) //proceed if authenticated
    {
        PrintLogonTestMsg("Login successfull! It binded.");

        //reset counter for failed tries because user has succefully authenticated
        $failedTries = 0;

        $ret = UpdateOrInsertRow($myPDO, $username, $failedTries);

        if ($ret === false)
        {
            PrintLogonTestMsg("Database error! Unable to update or insert row.");

            global $user_message_displayed;
            // DisplayLoginPage($message="<div id='login_message_container'>
            // <p id='login_message'>Database error! Please contact IT.</p></div>");
            // $user_message_displayed = 1;

            sleep(1);
            return null;
        }

        //determine the LDAP Path from Active Directory details
        $base_dn = "OU=User_Accounts,DC=". join(',DC=', explode('.', DOMAIN_FQDN));
        //search for the user in AD
        $result = ldap_search($conn, $base_dn, "(cn=*)");
        //initialize array
        $response = array();

        if (!$result) //return null if user was not found under specified path
        {
            $err = 'Result: '. ldap_error($conn);

            sleep(1);
            return null;
        }
        else //procced if user was found
        {
            //get all groups the user belongs to
            $usr_groups = get_groups($username, $password);

            //pass in global var
            global $ad_security_group_rw, $ad_security_group_ro;

            //check if user belongs to any of the allowed groups
            $group_rw = CheckGroupPermission($username, $password, $ad_security_group_rw);
            $group_ro = CheckGroupPermission($username, $password, $ad_security_group_ro);

            // return null if user doesn't belong to any of the allowed groups
            if (!($group_rw) && !($group_ro)) {sleep(1); return null;}

            //start the session
            session_start();

            //set the variable indication read/write access depending on the group user belongs to
            if ($group_rw)
            {
                $_SESSION["rw_permission"] = 1;
            }
            else
            {
                $_SESSION["rw_permission"] = 0;
            }

            //get AD user info
            $ADinfo = ldap_get_entries($conn, $result);
            //iterate through array to extract info
            for ($i=0; $i < $ADinfo["count"]; $i++)
            {
                // echo $i . $info[$i]["userprincipalname"][0] . "<br>";
                $userPriName = $ADinfo[$i]["userprincipalname"][0];

                //find matching username in search result
                if (strtolower($user) === strtolower($userPriName))
                {
                    //assign property values to output array
                    $response[0] = $username;
                    $response[1] = $ADinfo[$i]["displayname"][0];
                    $response[2] = $ADinfo[$i]["givenname"][0];
                    break;
                }
            }
            //return array with property values
            return $response;
        }
    }
    //}
    ldap_close($conn); //close the connection to AD

    sleep(1);
    return null;
}

function get_groups($username, $password)
{
    $base_dn = "OU=User_Accounts,DC=". join(',DC=', explode('.', DOMAIN_FQDN));
    $user = $username .'@'. DOMAIN_FQDN;
    //echo $user;

    // Connect to AD
    $ldap = ldap_connect(PROTOCOL . "://". LDAP_SERVER ."/") or die("Could not connect to LDAP");
    ldap_bind($ldap,$user,$password) or die("Could not bind to LDAP");

    // Search AD
    $results = ldap_search($ldap, $base_dn, "(samaccountname=$username)", array("memberof","primarygroupid"));
    $entries = ldap_get_entries($ldap, $results);

    // No information found, bad user
    if($entries['count'] == 0) return null;

    // Get groups and primary group token
    $output = $entries[0]['memberof'];
    $token = $entries[0]['primarygroupid'][0];

    // Remove extraneous first entry i.e. the count of the groups the user belongs to
    array_shift($output);

    // We need to look up the primary group, get list of all groups
    $results2 = ldap_search($ldap, $base_dn, "(objectcategory=group)", array("distinguishedname", "primarygrouptoken"));
    $entries2 = ldap_get_entries($ldap, $results2);

    // Remove extraneous first entry
    array_shift($entries2);

    // Loop through and find group with a matching primary group token
    foreach($entries2 as $e)
    {
        if($e['primarygrouptoken'][0] == $token) {
            //echo $e['distinguishedname'][0];
            // Primary group found, add it to output array
            $output[] = $e['distinguishedname'][0];
            break;
        }
    }
    return $output;
}

function ProcessPageRequest()
{
    // echo "session code: " . session_status() . "<br>";

    if(session_status() == PHP_SESSION_ACTIVE)
    {
        session_unset();
        session_destroy();
    }

    if ($_POST == null)
    {
        // echo "rw:" . $_SESSION["myvar"];
        // echo "here";
        // die();
        // echo "rw:" . $_SESSION["myvar"];
        DisplayLoginPage();
    }

    if (isset($_POST['action']))
    {
        if ($_POST['action'] == 'login')
        {
            //store username input in lowercase
            $username= strtolower($_POST["username_input"]);
            //store password input
            $password= $_POST["password_input"];

            if (authenticateUser($username, $password))
            {
                //initialize variables for execute function
                $env = array('EIAC_USER' => $_SESSION['username']);
                //go to home page after successful authentication
                header("Location: index.php");
            }
            else
            {
                global $user_message_displayed;
                if ($user_message_displayed == 0)
                {
                    DisplayLoginPage($message="<div id='login_message_container'>
                        <p id='login_message'>The credentials you entered are incorrect. 
                        Please double-check and try again.</p></div>");
                    $user_message_displayed = 1;
                }
            }
        }
    }
}



?>