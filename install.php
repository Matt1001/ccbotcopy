<?php
require 'config.php';
require 'gm.php';
$logged_in = false;
$bot_config = array('avatar' => 'http://www.clashcaller.com/images/cc.png',
                    'name' => $main_config['bot_name'],
                    'callback' => sprintf('http://%s%s/bot/', $_SERVER['HTTP_HOST'], implode('/', array_slice(explode('/', $_SERVER['PHP_SELF']), 0, -1))));
if (isset($_GET['access_token'])) {
    $token = $_GET['access_token'];
    $_COOKIE['token'] = $token;
    setcookie('token', $token);
    $logged_in = true;
    $user = Requests::get(sprintf('%s/users/me?token=%s', $gm_api, $token));
    $body = json_decode($user->body, true);
    $uid = $body['response']['user_id'];
    mysqli_query($con, "UPDATE `cc` SET user_token = '{$token}' WHERE admin_id = '{$uid}'");
    header('Location: install');
}
$message = '';
if (isset($_POST['install_bot'])) {
    $group_id = $_POST['group_id'];
    $token = $_POST['install_bot'];
    if (trim(strlen($_POST['bot_name'])) > 0) {
        $bot_config['name'] = trim($_POST['bot_name']);
    }
    if ($main_config['single_bot_per_user']) {
        $user = Requests::get(sprintf('%s/users/me?token=%s', $gm_api, $token));
        $user_body = json_decode($user->body, true);
        $admin_id = $user_body['response']['id'];
        $qr = mysqli_query($con, "SELECT * FROM cc WHERE admin_id = '{$admin_id}'");
        if (mysqli_num_rows($qr) == 0) {
            $message = install_bot($group_id, $token, $bot_config);
        } else {
            $row = mysqli_fetch_array($qr);
            $message = (sprintf("You've already made a bot in group '%s', %s", $row['group_name'], $row['admin_name']));
        }
    } else {
        $message = install_bot($group_id, $token, $bot_config);
    }
}
if (isset($_COOKIE['token'])) {
    $token = $_COOKIE['token'];
    $group_list = array();

    $bot_page = Requests::get(sprintf('%s/bots?token=%s', $gm_api, $token));
    $bot_list = json_decode($bot_page->body, true);
    $bot_list_table = array();
    $bot_ids = array();
    foreach ($bot_list['response'] as $bot) {
        array_push($bot_ids, $bot['bot_id']);
        $parsed_callback = parse_url($bot['callback_url']);
        if ($parsed_callback['host'] == $_SERVER['HTTP_HOST']) {
            $thx_ = sprintf('<tr><td>%s</td><td>%s</td><td><a class="button" href="?delete_bot=%s">Delete</a> <a target="_blank" class="button" href="https://dev.groupme.com/bots/%s/edit">Edit</a></td></tr>', $bot['name'], $bot['group_name'], $bot['bot_id'], $bot['bot_id']);
            array_push($bot_list_table, $thx_);
            mysqli_query($con, sprintf("UPDATE cc SET group_name = '%s', group_id = '%d' WHERE bot_id = '%s'", $bot['group_name'], $bot['group_id'], $bot['bot_id']));
        }
    }
    $listed_bots = mysqli_query($con, "SELECT * FROM `cc` WHERE user_token = '{$token}'");
    while ($sq_bots = mysqli_fetch_array($listed_bots)) {
        if (!in_array($sq_bots['bot_id'], $bot_ids)) {
            mysqli_query($con, "DELETE FROM `cc` WHERE bot_id = '{$sq_bots['bot_id']}'");
        }
    }
    $gm_groups = Requests::get(sprintf('%s/groups?per_page=100&token=%s', $gm_api, $token));
    $gm_res = json_decode($gm_groups->body, true);
    foreach ($gm_res['response'] as $gmr) {
        $op_ = "<option value=\"{$gmr['id']}\">{$gmr['name']}</option>";
        array_push($group_list, $op_);
    }
    $group_list = implode("\n", $group_list);
    $logged_in = true;
}
if (isset($_GET['delete_bot'])) {
    if (!$logged_in) {
        $message = 'Not logged in';
    } else {
        $bot_id = $_GET['delete_bot'];
        Requests::post(sprintf('%s/bots/destroy?token=%s', $gm_api, $token), array(), array('bot_id' => $bot_id));
        mysqli_query($con, "DELETE FROM `cc` WHERE bot_id = '{$bot_id}'");
        header('Location: install');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
  <title>Clash Caller Bot</title>
  <!-- CSS  -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:300,300italic,700,700italic">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/milligram/1.1.0/milligram.min.css" rel="stylesheet">
</head>
<body>
  <div class="container">
    <div class="row">
    <div class="column column-80 column-offset-10">
      <a href="https://github.com/butttons/cc-bot" target="_blank" class="button button-outline float-right">Get source</a>
    <h1>Clash Caller Bot</h1>
    <blockquote>
    Add this bot to your group to easily access the caller from the group itself.
  </blockquote>
  <?php if ($message != '') {
    ?>
      <blockquote>
        <p><em><?php echo $message;
    ?></em></p>
      </blockquote>
    <?php
} ?>
    <?php if ($logged_in) {
    ?>
    <form action="install" method="post">
        <input type="hidden" name="install_bot" value="<?php echo $token;
    ?>" />
        <input type="text" placeholder="Bot name" name="bot_name" />
        <select name="group_id">
            <option>Select group</option>
            <?php echo $group_list;
    ?>
        </select>
        <input type="submit" value="Make CC Bot" />
    </form>
    <?php if (count($bot_list_table) > 0) {
    ?>
    <table>
        <thead>
            <tr>
                <th>Bot Name</th>
                <th>Group</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php echo implode("\n", $bot_list_table);
    ?>
        </tbody>
    </table>
    <?php
}
    ?>
    <?php
} else {
    ?>
      <a class="button" href="<?php echo $oauth_link;
    ?>">Click here to start</a>
    <?php
} ?>
<pre>

  Caller commands:
    <strong>/cc</strong> - Get CC link
    <strong>/cc code</strong> - Get CC code
    <strong>/bot status</strong> - See bot status
    <strong>/call #</strong> - Call target
    <strong>/call # for [player name]</strong> - Call target for player
    <strong>/attacked # for # stars</strong> - Log attack
    <strong>/log # stars on # by [player name]</strong> - Log attack for player
    <strong>/delete call #</strong> - Delete call
    <strong>/delete call on # by [player name]</strong> - Delete call by player
    <strong>/get calls</strong> - Get active calls
    <strong>/get all calls</strong> - Get all calls

  Admin commands:
    <strong>/start war [war size] [enemy name]</strong> - Start new caller
    <strong>/set cc [code]</strong> - Set code
    <strong>/update war timer [end|start] [timer]</strong> - Change war timer. Timer format: ##h##m
    <strong>/set clan name [clan name]</strong> - Set clan name
    <strong>/set clan tag [clan tag]</strong> - Set clan tag
    <strong>/cc archive [on|off]</strong> - CC archive toggle
    <strong>/cc stacked calls [on|off]</strong> - Stacked calls toggle
    <strong>/cc promote @[player name]</strong> - Promote player to admin
    <strong>/cc demote @[player name]</strong> - Demote player from admin

  Examples:
    /call 2
    /call 7 for John
    /delete call 8
    /attacked 1 for 2 stars
    /log 3 stars on 16 by hitman
    /delete call on 7 by jane
    /start war 30 Enemy Clan Name
    /update war timer end 22h18m
    /update war timer start 17h59m
    /cc stacked calls on
    /set clan name Reddit Mu
    /set clan tag 2GQ8YVV8

  <u>Of course # is a number.</u>

</pre>
      </div>
    </div>
</div>
</body>
