<?php
require 'vendor/autoload.php';
$main_config = array('groupme_oauth_link' => '', // Your GroupMe Application oauth Link
                      'bot_name' => 'CC Bot', // Default bot name
                      'single_bot_per_user' => true, // Set to true to only allow one bot per user
                      'mysql' => array('host' => 'localhost', // MySQL host name
                                      'username' => '', // MySQL username
                                      'password' => '', // MySQL password
                                      'database' => '') // MySQL database name
                      );

/* DON'T CHANGE ANYTHING BELOW */ 
$GLOBALS['gm_api'] = "https://api.groupme.com/v3";
$GLOBALS['oauth_link'] = $main_config['groupme_oauth_link'];
$con = mysqli_connect($main_config['mysql']['host'], $main_config['mysql']['username'], $main_config['mysql']['password'], $main_config['mysql']['database']);
if($con){
    $GLOBALS['con'] = $con;
}else{
    die("Cannot connect to database");
}

?>
