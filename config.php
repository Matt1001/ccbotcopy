<?php
require 'vendor/autoload.php';
$main_config = array('groupme_oauth_link' => 'https://oauth.groupme.com/oauth/authorize?client_id=Mgw2upcrFYUIx8MLuRyj5rPgKxdCxi48pQR8pKG3xx5ABUtG', // Your GroupMe Application oauth Link
                      'bot_name' => 'CC Bot', // Default bot name
                      'single_bot_per_user' => false, // Set to true to only allow one bot per user
                      'mysql' => array('host' => 'localhost', // MySQL host name
                                      'username' => 'admina2dDI5f', // MySQL username
                                      'password' => 'W2QJ2SGpcL9e', // MySQL password
                                      'database' => 'ccbot') // MySQL database name
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
