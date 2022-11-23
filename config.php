<?php
//ini_set('display_errors', 1);
//ini_set('display_startup_errors', 1);
//error_reporting(E_ALL);

$baseDir1 = 'uploads/';
$genericFileName = '__file__data__.dat';
$downloadUrl = '/';
$database_name = "HashDropDatabase.sqlite";
$profileTableName="Profile";
$userTableName="User";
$fileTableName="File";
$hashDropDb = new PDO("sqlite:data/$database_name");
$partialUploadDir="__partial__";
$maxFileSizeGb=10;//This is in gb just put the number don't worry about the gb part

//Global Variables having to do with Angel login page.
$login_error_log_name = 'login error'; //title of log, might be used to as search string
$table_name = "FailedLogins";
$myPDO = new PDO("sqlite:data/$database_name");
$disableErrorPrint=1;//0 enables errors being printed to the webpage 1 disables them

//login and security
$ad_security_group_ro = "cn=,"; //users in this group are allowed to login but restricted from making changes
$ad_security_group_rw = "cn=,"; //users in this group are allowed to login and make changes to data
$backoff_threshold = 3;
$threshold = 14; //the threshold for failed login attempts before stoping to take input from that username to authenticate

require_once 'data_functions.php';
//all globals needed for Angel login page(end).
