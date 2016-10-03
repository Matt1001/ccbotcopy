<?php
require 'config.php';
require 'cc.php';
function safe_sql($con, $arr)
{
    $keys = array();
    $vals = array();
    foreach ($arr as $key => $item) {
        array_push($keys, $key);
        array_push($vals, "'".mysqli_real_escape_string($con, $item)."'");
    }
    $ql = '('.implode(', ', $keys).') VALUES ('.implode(', ', $vals).')';

    return $ql;
}
function post_gm($text, $bot_id)
{
    Requests::post('https://api.groupme.com/v3/bots/post', array(), array(
        'bot_id' => $bot_id,
        'text' => $text,
    ));
}
function install_bot($group_id, $token, $bot_config){
    $user = Requests::get(sprintf("%s/users/me?token=%s", $GLOBALS['gm_api'], $token));
    $user_body = json_decode($user->body, true);
    $admin_id = $user_body['response']['id'];
    $id = dechex(mt_rand());

    $p1 = Requests::get(sprintf("%s/groups/%d?token=%s", $GLOBALS['gm_api'], $group_id, $token));
    $pbody1 = json_decode($p1 -> body, true);
    $group_name = $pbody1['response']['name'];

    $bot_make = array('bot' => array('name' => $bot_config['name'],
                                      'group_id' => $group_id,
                                      'avatar_url' => $bot_config['avatar'],
                                      'callback_url' => $bot_config['callback'] . $id));
    $bot_create = Requests::post(sprintf("%s/bots?token=%s", $GLOBALS['gm_api'], $token), array('Content-Type' => 'application/json'), json_encode($bot_make));
    $bot_page = json_decode($bot_create->body, true);

    $admin_name = '';
    foreach($pbody1['response']['members'] as $mem){
        if($mem['user_id'] == $user_body['response']['id']){
            $admin_id = $mem['user_id'];
            $admin_name = $mem['nickname'];
        }
    }
    $admin_list = array(array('id' => $admin_id, 'name' => $admin_name));
    $bot_id = $bot_page['response']['bot']['bot_id'];
    $ins = array('id' => $id,
                  'group_id' => $group_id,
                  'group_name' => $group_name,
                  'user_token' => $token,
                  'bot_id' => $bot_id,
                  'admin_id' => $admin_id,
                  'admin_name' => $admin_name,
                  'admins' => json_encode($admin_list),
                  'clan_name' => '',
                  'clan_tag' => '',
                  'archive' => 0,
                  'stacked_calls' => 0,
                  'call_timer' => 2,
                  'proposed' => '[]');
    mysqli_query($GLOBALS['con'], "INSERT INTO `cc`" . safe_sql($GLOBALS['con'], $ins));
    post_gm("Welcome to Clash Caller bot. Type '/bot status' to see current status.\n/help admin - see admin commands\n/help full - see all commands\n/help - see normal commands", $bot_id);
    return "Bot made successfully";
}
function gm_respond($gm, $row)
{
    $install_link = 'http://cc-butttons.rhcloud.com/install';
    $config = array(
          'clan_name' => $row['clan_name'],
          'clan_tag' => $row['clan_tag'],
          'call_timer' => $row['call_timer'],
          'archive' => $row['archive'],
          'stacked_calls' => $row['stacked_calls']
      );
    $row_cc = $row['cc'];
    $cc = new ClashCaller();
    $cc->set_config($config);
    $cc->set_cc($row['cc']);

    $invalid_cc = ($row_cc == '') ? true : false;

    $name_ = $gm['name'];
    $uid_ = $gm['user_id'];
    $admins = json_decode($row['admins'], true);
    $assigned = json_decode($row['proposed'], true);
    $row_id = $row['id'];

    $command_found = false;
    $message = '';

    $admin_commands = array('>Admin commands: ',
                      '/start war [war size] [enemy name] - Start new caller',
                      '/set cc [code] - Set code',
                      '/update war timer [end|start] [timer] - Change war timer (##h##m)', '',
                      '/set breakdown [#/#/#/#] [#/#/#/#] - Set townhall breakdown on CC', '',
                      '/set clan name [clan name] - Set clan name',
                      '/set clan tag [clan tag] - Set clan tag', '',
                      '/cc timer # hours - Set call timer on CC',
                      '/cc archive [on|off] - CC archive toggle',
                      '/cc stacked calls [on|off] - Stacked calls toggle', '',
                      '/cc promote @[player name] - Promote player to admin',
                      '/cc demote @[player name] - Demote player from admin', '',
                      '/assign # to @[player name] - Assign base to tagged player',
                      '/clear assigned base # - Delete base assigned for players',
                      '/clear all assigned bases - Delete all assigned bases for the war'
                    );

    $full_commands = array('>All commands: ',
                      '/cc - Get CC link',
                      '/cc code - Get CC code', '',
                      '/call # - Call target',
                      '/call # for [player name] - Call target for player', '',
                      '/attacked # for # stars - Log attack',
                      '/log # stars on # by [player name] - Log attack for player', '',
                      '/delete call # - Delete call',
                      '/delete call on # by [player name] - Delete call by player', '',
                      '/get calls - Get active calls',
                      '/get all calls - Get all calls',
                      '/get war status - Get status on war', '',
                      '/get note # - Get note on target',
                      '/update note # [note] - Update note on target', '',
                      '/my stats - View your stats',
                      '/stats for [player name] - View stats for player', '',
                      '/get my base - Get base assigned to you',
                      '/decline my base - Decline base assigned to you',
                      '/get assigned bases - Get all assigned bases');

    $normal_commands = array('>Caller commands: ',
                            '/cc - Get CC link',
                            '/call # - Call target',
                            '/attacked # for # stars - Log attack',
                            '/delete call # - Delete call',
                            '/get calls - Get active calls',
                            '/get war status - Get status on war',
                            '/get note # - Get note on target',
                            '/get my base - Get base assigned to you',
                            '/decline my base - Decline base assigned to you', '',
                            '/bot status - View bot status',
                            '/help full - Show all commands',
                            '/help admin - Show admin commands',
                            '@leadership - Tag leadership');

    $regex_ = array(
      'call' => "/^\/call (\d+)\s*$/i",
      'call_for' => "/^\/call (\d+) for\s+(.*)$/i",
      'clear_call' => "/^\/delete call (\d+)\s*$/i",
      'clear_call_for' => "/^\/delete call on (\d+) by (.*)$/i",
      'get_calls' => "/^\/get calls\s*$/i",
      'get_all_calls' => "/^\/get all calls\s*$/i",
      'get_war_status' => "/^\/get war status\s*$/i",
      'log_attack' => "/^\/attacked (\d+) for (\d+) star[s]?\s*$/i",
      'log_attack_for' => "/^\/log (\d+) star[s]? on (\d+) by (.*)$/i",
      'help' => "/^\/help\s*$/i",
      'help_admin' => "/^\/help admin\s*$/i",
      'help_full' => "/^\/help full\s*$/i",
      'set_cc' => "/^\/set cc (.*)/",
      'start_war' => "/^\/start war (\d+)\s+(.*)$/i",
      'cc_url' => "/^\/cc\s*$/i",
      'cc_code' => "/^\/cc code\s*$/i",
      'examples' => "/^\/examples/i",
      'status' => "/^\/bot status/i",
      'my_stats' => "/^\/my stats/i",
      'stats_for' => "/^\/stats for (.*)/i",
      'update_war_timer' => "/^\/update war timer (end|start) (\d+h\d+m)/i",
      'update_clan_name' => "/^\/set clan name (.*)/i",
      'update_clan_tag' => "/^\/set clan tag (.*)/i",
      'update_stacked_calls' => "/^\/cc stacked calls (on|off)/i",
      'update_archive' => "/^\/cc archive (on|off)/i",
      'update_call_timer' => "/^\/cc timer (\d+) hours/i",
      'admin_promote' => "/^\/cc promote (.*)/i",
      'admin_demote' => "/^\/cc demote (.*)/i",
      'cc_bd' => "/^\/set breakdown ([\d\/]+) ([\d\/\.]+)/i",
      'update_note' => "/^\/update note (\d+) (.*)/i",
      'get_note' => "/^\/get note (\d+)/i",
      'assign_base' => "/^\/assign (\d+) to @(.*)/i",
      'get_my_base' => "/^\/get my base/",
      'decline_my_base' => "/^\/decline my base/",
      'get_assigned_bases' => "/^\/get assigned bases/",
      'clear_assigned_base' => "/^\/clear assigned base (\d+)/",
      'clear_all_assigned_bases' => "/^\/clear all assigned bases/",
      'tag_admins' => "/@leadership/i"
      );
    $invalid_cc_error = "No caller code set. Type '/help admin' to see commands";
    foreach ($regex_ as $key => $reg) {
        if (preg_match($reg, $gm['text'], $out)) {
            $command_found = true;
            switch ($key) {
                  case 'help':
                      $message = implode("\n", $normal_commands);
                  break;
                  case 'help_admin':
                      $message = implode("\n", $admin_commands);
                  break;
                  case 'help_full':
                      $message = implode("\n", $full_commands);
                  break;
                  case 'tag_admins':
                      $out = array('text' => 'Hey ', 'bot_id' => $row['bot_id'], 'attachments' => array(
                              array('loci' => array(),
                                    'user_ids' => array(),
                                    'type' => 'mentions')
                              ));
                      $pos_ = strlen($out['text']);
                      $anames = array();
                      foreach($admins as $a){
                          $len_ = strlen($a['name']);
                          array_push($out['attachments'][0]['user_ids'], $a['id']);
                          array_push($out['attachments'][0]['loci'], array($pos_, $len_));
                          $pos_ += $len_   + 2;
                          array_push($anames, $a['name']);
                      }
                      $out['text'] = sprintf("Hey %s! %s needs help", implode(', ', $anames), $name_);
                      $page = Requests::post('https://api.groupme.com/v3/bots/post', array('Content-Type' => 'application/json'), json_encode($out));
                  break;
                  case 'clear_all_assigned_bases':
                      if($cc->is_admin($uid_, $admins)){
                          mysqli_query($GLOBALS['con'], "UPDATE cc SET proposed = '[]' WHERE id = '{$row_id}'");
                          $message = "Cleared all assigned bases";
                      }else{
                          $message = "Only admins can clear assigned bases";
                      }
                  break;
                  case 'clear_assigned_base':
                      $num = intval($out[1]) - 1;
                      if($cc->is_admin($uid_, $admins)){
                          if(!isset($assigned[$num])){
                              $message = sprintf("No base assigned to %s base yet", $out[1]);
                          }else{
                              $user_name_ = $assigned[$num]['name'];
                              unset($assigned[$num]);
                              $assigned_json = json_encode($assigned);
                              mysqli_query($GLOBALS['con'], "UPDATE cc SET proposed = '{$assigned_json}' WHERE id = '{$row_id}'");
                              $message = sprintf("Removed #%d base, assigned to %s", $out[1], $user_name_);
                          }
                      }else{
                          $message = "Only admins can clear an assigned base";
                      }
                  break;
                  case 'assign_base':
                      $num = intval($out[1]) - 1;
                      if($cc->is_admin($uid_, $admins)){
                          if (count($gm['attachments']) > 0) {
                              if ($gm['attachments'][0]['type'] == 'mentions') {
                                  $user_id = $gm['attachments'][0]['user_ids'][0];
                                  list($ls, $ln) = $gm['attachments'][0]['loci'][0];
                                  $user_name_ = trim($out[2]);
                                  if(isset($assigned[$num])){
                                      $message = sprintf("Base #%d already assigned to %s", $out[1], $assigned[$num]['name']);
                                  }else{
                                      $assigned[$num] = array('user_id' => $user_id, 'name' => $user_name_);
                                      $assigned_json = json_encode($assigned);
                                      mysqli_query($GLOBALS['con'], "UPDATE cc SET proposed = '{$assigned_json}' WHERE id = '{$row_id}'");
                                      $message = sprintf("Base #%d assigned to %s", $out[1], $user_name_);
                                  }
                              }
                          }else{
                             $message = "Please tag a player to assign the base";
                          }
                      }else{
                          $message = "Only admins can assign a base";
                      }
                  break;
                  case 'get_my_base':
                      $found_user = false;
                      foreach($assigned as $num => $val){
                          if($val['user_id'] == $uid_){
                              $found_user = true;
                              $base_num = $num + 1;
                              $base_name = $val['name'];
                              break;
                          }
                      }
                      if($found_user){
                          $message = sprintf("#%d base assigned to you, %s", $base_num, $base_name);
                      }else{
                          $message = "No assigned base found for you, " . $name_;
                      }
                  break;
                  case 'decline_my_base':
                      $found_user = false;
                      foreach($assigned as $num => $val){
                          if($val['user_id'] == $uid_){
                              $found_user = true;
                              $base_num = $num;
                              $base_name = $val['name'];
                              break;
                          }
                      }
                      if($found_user){
                          unset($assigned[$base_num]);
                          $assigned_json = json_encode($assigned);
                          mysqli_query($GLOBALS['con'], "UPDATE cc SET proposed = '{$assigned_json}' WHERE id = '{$row_id}'");
                          $message = sprintf("Removed #%d base, assigned to %s", $base_num + 1, $base_name);
                      }else{
                          $message = "No assigned base found for you, " . $name_;
                      }
                  break;
                  case 'get_assigned_bases':
                      if(count($assigned) == 0){
                          $message = "No assigned bases yet";
                      }else{
                          $out = array("Currently assigned bases: ");
                          ksort($assigned);
                          foreach($assigned as $num => $val){
                              array_push($out, sprintf("#%d: %s", $num + 1, $val['name']));
                          }
                          return implode("\n", $out);
                      }
                  break;

                  case 'set_cc':
                      $code = trim($out[1]);
                      if ($cc->is_admin($uid_, $admins)) {
                          mysqli_query($GLOBALS['con'], "UPDATE cc SET cc = '{$code}' WHERE id = '{$row_id}'");
                          $message = 'CC code updated to: '.$code;
                      } else {
                          $message = 'Only admins can change cc code, '.$name_;
                      }
                  break;
                  case 'update_note':
                      $num = intval($out[1]);
                      $note = trim($out[2]);
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->update_note($num, $note);
                      }
                  break;
                  case 'get_note':
                      $num = intval($out[1]);
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->get_note($num);
                      }
                  break;
                  case 'call':
                      $num = intval($out[1]);
                      $name = $name_;
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->call_target($num, $name);
                      }
                  break;
                  case 'call_for':
                      $num = intval($out[1]);
                      $name = trim($out[2]);
                      if(count($gm['attachments']) > 0){
                          if($gm['attachments'][0]['type'] == 'mentions'){
                              $name = substr($name, 1);
                          }
                      }
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->call_target($num, $name);
                      }
                  break;
                  case 'clear_call':
                      $num = intval($out[1]);
                      $name = $name_;
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->delete_call($num, $name);
                      }
                  break;
                  case 'clear_call_for':
                      $num = intval($out[1]);
                      $name = trim($out[2]);
                      if(count($gm['attachments']) > 0){
                          if($gm['attachments'][0]['type'] == 'mentions'){
                              $name = substr($name, 1);
                          }
                      }
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->delete_call($num, $name);
                      }
                  break;
                  case 'get_calls':
                      $update = $cc->get_update();
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $calls = $cc->format_calls($update, true);
                          $message = implode("\n", $calls);
                      }
                      break;
                  case 'get_all_calls':
                      $update = $cc->get_update();
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $calls = $cc->format_calls($update, false);
                          $message = implode("\n", $calls);
                      }
                  break;
                  case 'get_war_status':
                      $update = $cc->get_update();
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $calls = $cc->get_war_status($update);
                          $message = implode("\n", $calls);
                      }
                  break;
                  case 'log_attack':
                      $num = intval($out[1]);
                      $stars = intval($out[2]);
                      $name = $name_;
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->update_stars($num, $stars, $name);
                      }
                  break;
                  case 'log_attack_for':
                      $num = intval($out[2]);
                      $stars = intval($out[1]);
                      $name = trim($out[3]);
                      if(count($gm['attachments']) > 0){
                          if($gm['attachments'][0]['type'] == 'mentions'){
                              $name = substr($name, 1);
                          }
                      }
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->update_stars($num, $stars, $name);
                      }
                  break;
                  case 'start_war':
                      $size = intval($out[1]);
                      $enemy = $out[2];
                      if ($cc->is_admin($uid_, $admins)) {
                          if ($row['clan_name'] == '') {
                              $message = "Please set your clan name first. Type '/help admin' to see commands.";
                          } else {
                              $war = $cc->start_war($enemy, $size);
                              if (isset($war['war_id'])) {
                                  mysqli_query($GLOBALS['con'], "UPDATE cc SET cc = '{$war['war_id']}', proposed = '[]' WHERE id = '{$row_id}'");
                                  $message = "War started against {$enemy}. CC code: {$war['war_id']}";
                              } else {
                                  $message = 'Unknown error';
                              }
                          }
                      } else {
                          $message = 'Only admins can start new caller, '.$name_;
                      }
                  break;
                  case 'cc_url':
                      if($invalid_cc){
                          $message = "CC code not set. Type /help admin to see commands.";
                      }else{
                          $message = sprintf('http://www.clashcaller.com/war/%s', $row['cc']);
                      }
                  break;
                  case 'cc_code':
                      if($invalid_cc){
                          $message = "CC code not set. Type /help admin to see commands.";
                      }else{
                          $message = $row['cc'];
                      }
                  break;
                  case 'update_war_timer':
                      $st = $out[1];
                      $timer = $out[2];
                      if ($cc->is_admin($uid_, $admins)) {
                          if ($invalid_cc) {
                              $message = $invalid_cc_error;
                          } else {
                              $message = $cc->update_timer($st, $timer);
                          }
                      } else {
                          $message = 'Only admins can update war timer, '.$name_;
                      }
                  break;
                  case 'status':
                      $clan_name_ = ($row['clan_name'] == '') ? 'not set' : $row['clan_name'];
                      $clan_tag_ = ($row['clan_tag'] == '') ? 'not set' : '#'.strtoupper($row['clan_tag']);
                      $archive_ = ($row['archive']) ? 'yes' : 'no';
                      $stacked_ = ($row['stacked_calls']) ? 'yes' : 'no';
                      $cc_code_ = ($row['cc'] == '') ? 'not set' : $row['cc'];
                      $admin_list = array();
                      foreach ($admins as $a) {
                          array_push($admin_list, $a['name']);
                      }
                      $message = sprintf("CC Bot - \nInstall at: %s\nCaller code: %s\nCreator: %s\nAdmins: %s\nClan name: %s\nClan tag: %s\nArchive: %s\nAllow stacked calls: %s\nTimer length: %d hours",
                                          $install_link, $cc_code_, $row['admin_name'], implode(', ', $admin_list), $clan_name_, $clan_tag_, $archive_, $stacked_, $row['call_timer']);
                  break;
                  case 'update_clan_name':
                      $clan_name = trim($out[1]);
                      if ($cc->is_admin($uid_, $admins)) {
                          mysqli_query($GLOBALS['con'], "UPDATE cc SET clan_name = '{$clan_name}' WHERE id = '{$row_id}'");
                          $message = 'Clan name set to: '.$clan_name;
                      } else {
                          $message = 'Only admins can change clan name, '.$name_;
                      }
                  break;
                  case 'update_clan_tag':
                      $clan_tag = trim($out[1]);
                      if ($cc->is_admin($uid_, $admins)) {
                          mysqli_query($GLOBALS['con'], "UPDATE cc SET clan_tag = '{$clan_tag}' WHERE id = '{$row_id}'");
                          $message = 'Clan tag set to: #'.strtoupper($clan_tag);
                      } else {
                          $message = 'Only admins can change clan tag, '.$name_;
                      }
                  break;
                  case 'update_stacked_calls':
                      $stacked = ($out[1] == 'on') ? 1 : 0;
                      if ($cc->is_admin($uid_, $admins)) {
                          mysqli_query($GLOBALS['con'], "UPDATE cc SET stacked_calls = {$stacked} WHERE id = '{$row_id}'");
                          $message = 'Stacked calls are now '.$out[1];
                      } else {
                          $message = 'Only admins can change CC settings, '.$name_;
                      }
                  break;
                  case 'update_archive':
                      $archive = ($out[1] == 'on') ? 1 : 0;
                      $go_on = true;
                      if ($archive) {
                          if ($row['clan_tag'] == '') {
                              $go_on = false;
                              $message = "Please set clan tag first to make CC searchable. Type '/help admin' to view commands";
                          }
                      }
                      if ($go_on) {
                          if ($cc->is_admin($uid_, $admins)) {
                              mysqli_query($GLOBALS['con'], "UPDATE cc SET archive = {$archive} WHERE id = '{$row_id}'");
                              $message = 'Clash Caller archive is now '.$out[1];
                          } else {
                              $message = 'Only admins can change CC settings, '.$name_;
                          }
                      }
                  break;
                  case 'my_stats':
                      $pl_name = $name_;
                      $go_on = true;
                      if ($row['clan_tag'] == '') {
                          $go_on = false;
                          $message = "Please set clan tag first view player stats. Type '/help admin' to view commands";
                      }else{
                          $message = $cc->get_stats($row['clan_tag'], $pl_name);
                      }
                  break;

                  case 'stats_for':
                      $pl_name = trim($out[1]);
                      $go_on = true;
                      if ($row['clan_tag'] == '') {
                          $go_on = false;
                          $message = "Please set clan tag first view player stats. Type '/help admin' to view commands";
                      }else{
                          $message = $cc->get_stats($row['clan_tag'], $pl_name);
                      }
                  break;
                  case 'update_call_timer':
                      $timer = intval($out[1]);
                      if ($cc->is_admin($uid_, $admins)) {
                          if ($timer > 0 && $timer < 24) {
                              mysqli_query($GLOBALS['con'], "UPDATE cc SET call_timer = {$timer} WHERE id = '{$row_id}'");
                              $message = 'Clash Caller timer set to '.$out[1].' hours';
                          } else {
                              $message = 'Set timer between 0 to 24 hours only';
                          }
                      } else {
                          $message = 'Only admins can change CC settings, '.$name_;
                      }
                  break;

                  case 'cc_bd':
                      $bd = trim($out[1]);
                      $ths = trim($out[2]);
                      if ($cc->is_admin($uid_, $admins)) {
                          $update = $cc->get_update();
                          post_gm("Updating townhall breakdown on CC", $row['bot_id']);
                          $message = $cc->set_breakdown($update, $bd, $ths);
                      } else {
                          $message = 'Only admins can update townhall breakdown, '.$name_;
                      }
                  break;
                  case 'admin_promote':

                  if ($cc->is_admin($uid_, $admins)) {
                      if (count($gm['attachments']) > 0) {
                          if ($gm['attachments'][0]['type'] == 'mentions') {
                              $names = array();
                              $ids = array();
                              $out = true;
                              foreach ($gm['attachments'][0]['user_ids'] as $k => $uid) {
                                  list($ls, $ln) = $gm['attachments'][0]['loci'][$k];
                                  $aname_ = substr($gm['text'], ($ls + 1), ($ln - 1));
                                  array_push($names, $aname_);
                                  if (!$cc->is_admin($uid, $admins)) {
                                      $st_ = array('id' => $uid, 'name' => trim($aname_));
                                      array_push($admins, $st_);
                                  } else {
                                      $message = $aname_.' is already admin';
                                      $out = false;
                                  }
                              }
                              if ($out) {
                                  $admin_json = json_encode($admins);
                                  mysqli_query($GLOBALS['con'], "UPDATE cc SET admins = '{$admin_json}' WHERE id = '{$row_id}'");
                                  $message = sprintf('Promoted %s to admin', implode(', ', $names));
                              }
                          }
                      } else {
                          $admin_name = trim($out[1]);
                          $members = Requests::get(sprintf('%s/groups/%d?token=%s', $GLOBALS['gm_api'], $row['group_id'], $row['user_token']));
                          $body = json_decode($members->body, true);
                          $found_user = false;
                          $admin_id = '';
                          foreach ($body['response']['members'] as $mem) {
                              if (strtolower($mem['nickname']) == strtolower($admin_name)) {
                                  $found_user = true;
                                  $admin_id = $mem['user_id'];
                                  $admin_name = $mem['nickname'];
                              }
                          }

                          if ($found_user) {
                              if (!$cc->is_admin($admin_id, $admins)) {
                                  $st_ = array('id' => $admin_id, 'name' => trim($admin_name));
                                  array_push($admins, $st_);
                                  $admin_json = json_encode($admins);
                                  mysqli_query($GLOBALS['con'], "UPDATE cc SET admins = '{$admin_json}' WHERE id = '{$row_id}'");
                                  $message = sprintf('Promoted %s to admin', $admin_name);
                              } else {
                                  $message = $admin_name.' is already admin';
                              }
                          } else {
                              $message = sprintf('User %s not found in group', $admin_name);
                          }
                      }
                  } else {
                      $message = 'Only admins can promote to admin';
                  }
                  break;
                  case 'admin_demote':
                  if ($cc->is_admin($uid_, $admins)) {
                      if (count($gm['attachments']) > 0) {
                          if ($gm['attachments'][0]['type'] == 'mentions') {
                              $names = array();
                              $ids = array();
                              $out = true;
                              foreach ($gm['attachments'][0]['user_ids'] as $k => $uid) {
                                  if (strval($uid) == strval($row['admin_id'])) {
                                      $message = 'Cannot demote bot creator';
                                      $out = false;
                                  } else {
                                      list($ls, $ln) = $gm['attachments'][0]['loci'][$k];
                                      $aname_ = substr($gm['text'], ($ls + 1), ($ln - 1));
                                      if ($cc->is_admin($uid, $admins)) {
                                          foreach ($admins as $ak => $ad) {
                                              if ($ad['id'] == $uid) {
                                                  array_push($names, trim($aname_));
                                                  unset($admins[$ak]);
                                              }
                                          }
                                      } else {
                                          $message = trim($aname_).' is not an admin';
                                          $out = false;
                                      }
                                  }
                              }
                              if ($out) {
                                  $admin_json = json_encode($admins);
                                  mysqli_query($GLOBALS['con'], "UPDATE cc SET admins = '{$admin_json}' WHERE id = '{$row_id}'");
                                  $message = sprintf('Demoted %s from admin', implode(', ', $names));
                              }
                          }
                      } else {
                          $found_admin = false;
                          $admin_name = trim($out[1]);
                          foreach ($admins as $ak => $ad) {
                              if ($ad['name'] == $admin_name) {
                                  unset($admins[$ak]);
                                  $found_admin = true;
                              }
                          }
                          if ($found_admin) {
                              $admin_json = json_encode($admins);
                              mysqli_query($GLOBALS['con'], "UPDATE cc SET admins = '{$admin_json}' WHERE id = '{$row_id}'");
                              $message = sprintf('Demoted %s from admin', $admin_name);
                          } else {
                              $message = $admin_name.' is not an admin';
                          }
                      }
                  } else {
                      $message = 'Only admins can demote from admins';
                  }
                  break;
              }
        }
    }
    if (!$command_found) {
        if ($gm['text'][0] == '/') {
            return sprintf('Invalid command: %s. Type /help to see commands', $gm['text']);
        } else {
            return false;
        }
    } else {
        return $message;
    }
}
