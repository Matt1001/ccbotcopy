<?php
require 'config.php';
require 'gm.php';
$logged_in = false;
$bot_config = array('avatar' => 'http://www.clashcaller.com/images/cc.png',
                    'name' => $main_config['bot_name'],
                    'callback' => sprintf('http://%s%s/bot/', $_SERVER['HTTP_HOST'], implode('/', array_slice(explode('/', $_SERVER['PHP_SELF']), 0, -1))), );
if (isset($_GET['access_token'])) {
    $token = $_GET['access_token'];
    $_COOKIE['token'] = $token;
    setcookie('token', $token);
    $logged_in = true;
    $user = Requests::get(sprintf('%s/users/me?token=%s', $gm_api, $token));
    $body = json_decode($user->body, true);
    $uid = $body['response']['user_id'];
    mysqli_query($con, "UPDATE `cc` SET user_token = '{$token}' WHERE admin_id = '{$uid}'");
    header("Location: install");
}
$message = '';
if (isset($_POST['install_bot'])) {
    $group_id = $_POST['group_id'];
    $token = $_POST['install_bot'];
    if(trim(strlen($_POST['bot_name'])) > 0){
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
    $bot_page = Requests::get(sprintf('%s/bots?token=%s', $gm_api, $token));
    $bot_list = json_decode($bot_page->body, true);
    $bot_list_table = array();
    $bot_ids = array();
    foreach($bot_list['response'] as $bot){
        array_push($bot_ids, $bot['bot_id']);
        $parsed_callback = parse_url($bot['callback_url']);
        if($parsed_callback['host'] == $_SERVER['HTTP_HOST']){
            $thx_ = sprintf("<tr><td>%s</td><td>%s</td><td><a class=\"button\" href=\"?delete_bot=%s\">Delete</a> <a target=\"_blank\" class=\"button\" href=\"https://dev.groupme.com/bots/%s/edit\">Edit</a></td></tr>", $bot['name'], $bot['group_name'], $bot['bot_id'], $bot['bot_id']);
            array_push($bot_list_table, $thx_);
            mysqli_query($con, sprintf("UPDATE cc SET group_name = '%s', group_id = '%d' WHERE bot_id = '%s'", $bot['group_name'], $bot['group_id'], $bot['bot_id']));
        }
    }
    $listed_bots = mysqli_query($con, "SELECT * FROM `cc` WHERE user_token = '{$token}'");
    while($sq_bots = mysqli_fetch_array($listed_bots)){
        if(!in_array($sq_bots['bot_id'], $bot_ids)){
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
if(isset($_GET['delete_bot'])){
    if(!$logged_in){
        $message = 'Not logged in';
    }else{
        $bot_id = $_GET['delete_bot'];
        Requests::post(sprintf('%s/bots/destroy?token=%s', $gm_api, $token), array(), array('bot_id' => $bot_id));
        mysqli_query($con, "DELETE FROM `cc` WHERE bot_id = '{$bot_id}'");
        header("Location: install");
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
  <style>
  code {font-size: 100%}
  pre {font-size: 13px}
  </style>
  <div class="container">
    <div class="row">
    <div class="column column-80 column-offset-10">
      <a href="https://github.com/butttons/cc-bot" target="_blank" class="button button-outline float-right">Get source</a>
      <a href="https://groupme.com/join_group/25440271/j0WbsM" target="_blank" class="button button-outline float-right">Join GroupMe Demo room</a>
    <h1>Clash Caller Bot</h1>
    <blockquote>
    Add this bot to your group to easily access the caller from the group itself.
  </blockquote>
  <?php if($message != ""){ ?>
      <blockquote>
        <p><em><?php echo $message; ?></em></p>
      </blockquote>
    <?php } ?>
    <?php if($logged_in){ ?>
    <form action="install" method="post">
        <input type="hidden" name="install_bot" value="<?php echo $token; ?>" />
        <input type="text" placeholder="Bot name" name="bot_name" />
        <select name="group_id">
            <option>Select group</option>
            <?php echo $group_list; ?>
        </select>
        <input type="submit" value="Make CC Bot" />
    </form>
    <?php if(count($bot_list_table) > 0) { ?>
    <table>
        <thead>
            <tr>
                <th>Bot Name</th>
                <th>Group</th>
                <th>Delete</th>
            </tr>
        </thead>
        <tbody>
            <?php echo implode("\n", $bot_list_table); ?>
        </tbody>
    </table>
    <?php } ?>
    <?php }else{ ?>
      <a class="button" href="<?php echo $oauth_link; ?>">Click here to start</a>
    <?php } ?>
    <h2>FAQ: </h2>
    <ul>
      <li>
        <strong>How do I get this bot for my group?</strong>
        <blockquote>
          Start by clicking on the 'Click here to start' button. Once you've authenticated with groupme, put the bot name and select the group where the bot will live, and click 'Make CC Bot'
        </blockquote>
      </li>
      <li>
        <strong>How do get started on setting it up?</strong>
        <blockquote>
          Once you've installed the bot, you should set your clan name first by typing <code>/set clan name [your clan name]</code>. No brackets.
          Now you can set the caller code manually <code>/set cc [code]</code>, or start a new war by <code>/start war [size] [enemy name]</code>. Where <code>size</code> is the size of war.
        </blockquote>
      </li>
      <li>
        <strong>Is it connected to clash caller?</strong>
        <blockquote>
          Yes, all changes made by the bot are reflected on clash caller.
        </blockquote>
      </li>
      <li>
        <strong>I can't see assigned bases on the clash caller website. Why is that?</strong>
        <blockquote>
          That feature isn't implemented in clash caller yet, it's a bot only feature. Admins can assign bases to player to attack, and the player can see which base it is, and can either decline it or call it.
        </blockquote>
      </li>
      <li>
        <strong>Why do no stats show up for my player name?</strong>
        <blockquote>
          Stats are fetched from archived wars in clash caller website. Your clan tag must be correct and the player name in order to fetch correct stats.
        </blockquote>
      </li>
      <li>
        <strong>What does stacked calls mean?</strong>
        <blockquote>
          If stacked calls are turned on, no more calls can be made on a base until it's previous call is expired or logged stars on. While using <code>/get all calls</code>, only the calls with the highest stars is shown, otherwise all calls are shown of a target.
        </blockquote>
      </li>


    </ul>

<pre>

   Commands:
    /bot status - See bot status
    /help - Show caller commands
    /help full - Show all commands
    /help admin - Show admin commands

    /cc - Get CC link
    /cc code - Get CC code

    /call # - Call target
    /call # for [player name] - Call target for player

    /attacked # for # stars - Log attack
    /log # stars on # by [player name] - Log attack for player

    /delete call # - Delete call
    /delete call on # by [player name] - Delete call by player

    /get calls - Get active calls
    /get all calls - Get all calls
    /get war status - Get status on war

    /get note # - Get note on target
    /update note # [note] - Update note on target

    /my stats - View your stats
    /stats for [player name] - View stats for player

    /get my base - Get base assigned to you
    /decline my base - Decline base assigned to you


  Admin commands:
    /start war [war size] [enemy name] - Start new caller
    /set cc [code] - Set code
    /update war timer [end|start] [timer] - Change war timer (##h##m)

    /set breakdown [#/#/#/#] [#/#/#/#] - Set townhall breakdown on CC

    /set clan name [clan name] - Set clan name
    /set clan tag [clan tag] - Set clan tag

    /cc timer # hours - Set call timer on CC
    /cc archive [on|off] - CC archive toggle
    /cc stacked calls [on|off] - Stacked calls toggle
    /cc promote @[player name] - Promote player to admin
    /cc demote @[player name] - Demote player from admin

    /assign # to @[player name] - Assign base to tagged player
    /clear assigned base # - Delete base assigned for players
    /clear all assigned bases - Delete all assigned bases for the war

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


    Format for 30 person war:
    /set breakdown 2/6/22 11/10/9
    For 2 TH11, 6 TH10, 22 TH9 in the enemy roster

  <u>Of course # is a number.</u>

</pre>
      </div>
    </div>
</div>
</body>
