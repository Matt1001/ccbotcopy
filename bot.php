<?php

header('Content-type: text/json');
require 'config.php';
require 'gm.php';
if(isset($_GET['id'])){
    $id = $_GET['id'];
    $qr = mysqli_query($con, "SELECT * FROM cc WHERE id = '{$id}'");
    if(mysqli_num_rows($qr) > 0){
        $row = mysqli_fetch_array($qr);
        $json = file_get_contents('php://input');
        if ($json != '') {
            $gm = json_decode($json, true);
            $gm_res = gm_respond($gm, $row);
            if($gm_res){
                if(strlen($gm_res) < 700){
                    post_gm($gm_res, $row['bot_id']);
                }else{
                    $split = explode("\n", $gm_res);
                    $middle = round(count($split) / 2);
                    $p1 = array_slice($split, 0, $middle);
                    $p2 = array_slice($split, $middle);
                    $gm_p1 = sprintf("(1/2) %s", implode("\n", $p1));
                    $gm_p2 = sprintf("(2/2) %s\n%s", trim($split[0]), implode("\n", $p2));
                    post_gm($gm_p1, $row['bot_id']);
                    post_gm($gm_p2, $row['bot_id']);
                }
            }
        }
    }
}else{
    header('Location: ../install');
}
