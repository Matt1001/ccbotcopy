<?php

header('Content-type: text/json');
require 'config.php';
require 'gm.php';
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $qr = mysqli_query($con, "SELECT * FROM cc WHERE id = '{$id}'");
    $gm = $sample_gm;
    if (mysqli_num_rows($qr) > 0) {
        $row = mysqli_fetch_array($qr);
        $json = file_get_contents('php://input');
        if ($json != '') {
            $gm = json_decode($json, true);
            $gm_res = gm_respond($gm, $row);
            if ($gm_res) {
                post_gm($gm_res, $row['bot_id']);
            }
        }
    }
} else {
    header('Location: install');
}
