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
                if(strlen($gm_res) < 1000){
                    post_gm($gm_res, $row['bot_id']);
                }else{
                    $split = explode("\n", $gm_res);
                    $first_line = $split[0];
                    $split_lines = count($split);

                    $lines = array_slice($split, 1);
                    $parts = array();
                    $str_ctr = strlen($first_line) + 10;
                    $check_ctr = (1000 - $str_ctr);
                    $part_ctr = 0;
                    foreach($lines as $num => $line){
                        $str_ctr += strlen($line);
                        if($str_ctr > $check_ctr){
                            $str_ctr = strlen($line) + strlen($first_line) + 10;
                            $part_ctr++;
                        }
                        if(!isset($parts[$part_ctr])) {
                            $parts[$part_ctr] = array();
                        }
                        array_push($parts[$part_ctr], $line);
                    }
                    $total_parts = count($parts);
                    foreach($parts as $num => $pt){
                        $out_str = sprintf("(%d/%d) %s:\n%s", $num + 1, $total_parts, $first_line, implode("\n", $pt));
                        post_gm($out_str, $row['bot_id']);
                    }
                }
            }
        }
    }
}else{
    header('Location: ../install');
}
