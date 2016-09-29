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
        'text' => $text
    ));
}
function install_bot($group_id, $token, $bot_config)
{
    $user = Requests::get(sprintf('%s/users/me?token=%s', $GLOBALS['gm_api'], $token));
    $user_body = json_decode($user->body, true);
    $admin_id = $user_body['response']['id'];
    $id = dechex(mt_rand());

    $p1 = Requests::get(sprintf('%s/groups/%d?token=%s', $GLOBALS['gm_api'], $group_id, $token));
    $pbody1 = json_decode($p1->body, true);
    $group_name = $pbody1['response']['name'];

    $bot_make = array('bot' => array('name' => $bot_config['name'],
                                      'group_id' => $group_id,
                                      'avatar_url' => $bot_config['avatar'],
                                      'callback_url' => $bot_config['callback'].$id));
    $bot_create = Requests::post(sprintf('%s/bots?token=%s', $GLOBALS['gm_api'], $token), array('Content-Type' => 'application/json'), json_encode($bot_make));
    $bot_page = json_decode($bot_create->body, true);

    $admin_name = '';
    foreach ($pbody1['response']['members'] as $mem) {
        if ($mem['user_id'] == $user_body['response']['id']) {
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
                  'call_timer' => 2);
    mysqli_query($GLOBALS['con'], 'INSERT INTO `cc`'.safe_sql($GLOBALS['con'], $ins));
    post_gm("Welcome to Clash Caller bot. Type '/bot status' to see current status.\n/help admin - see admin commands\n/help - see normal commands", $bot_id);
    return 'Bot made successfully';
}
function gm_respond($gm, $row)
{
    $install_link = sprintf('http://%s%s/install', $_SERVER['HTTP_HOST'], implode('/', array_slice(explode('/', $_SERVER['PHP_SELF']), 0, -1)));
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
    $row_id = $row['id'];

    $regex_ = array(
      'call' => "/^\/call (\d+)\s*$/i",
      'call_for' => "/^\/call (\d+) for\s+(.*)$/i",
      'clear_call' => "/^\/delete call (\d+)\s*$/i",
      'clear_call_for' => "/^\/delete call on (\d+) by (.*)$/i",
      'get_calls' => "/^\/get calls\s*$/i",
      'get_all_calls' => "/^\/get all calls\s*$/i",
      'log_attack' => "/^\/attacked (\d+) for (\d+) star[s]?\s*$/i",
      'log_attack_for' => "/^\/log (\d+) star[s]? on (\d+) by (.*)$/i",
      'help' => "/^\/help\s*$/i",
      'help_admin' => "/^\/help admin\s*$/i",
      'set_cc' => "/^\/set cc (.*)/",
      'start_war' => "/^\/start war (\d+)\s+(.*)$/i",
      'cc_url' => "/^\/cc\s*$/i",
      'cc_code' => "/^\/cc code\s*$/i",
      'examples' => "/^\/examples/i",
      'status' => "/^\/bot status/i",
      'update_war_timer' => "/^\/update war timer (end|start) (\d+h\d+m)/i",
      'update_clan_name' => "/^\/set clan name (.*)/i",
      'update_clan_tag' => "/^\/set clan tag (.*)/i",
      'update_stacked_calls' => "/^\/cc stacked calls (on|off)/i",
      'update_archive' => "/^\/cc archive (on|off)/i",
      'update_call_timer' => "/^\/cc timer (\d+) hours/i",
      'admin_promote' => "/^\/cc promote (.*)/",
      'admin_demote' => "/^\/cc demote (.*)/"
      );
    $command_found = false;
    $message = '';
    $invalid_cc_error = "No caller code set. Type '/help admin' to see commands";
    foreach ($regex_ as $key => $reg) {
        if (preg_match($reg, $gm['text'], $out)) {
            $command_found = true;
            switch ($key) {
                  case 'help':
                      $commands = array('CC commands: ',
                                      '/cc - Get CC link',
                                      '/cc code - Get CC code',
                                      '/bot status - See bot status',
                                      '/call # - Call target',
                                      '/call # for [player name] - Call target for player',
                                      '/attacked # for # stars - Log attack',
                                      '/log # stars on # by [player name] - Log attack for player',
                                      '/delete call # - Delete call',
                                      '/delete call on # by [player name] - Delete call by player',
                                      '/get calls - Get active calls',
                                      '/get all calls - Get all calls');
                      $message = implode("\n", $commands);
                  break;
                  case 'help_admin':
                      $commands = array('CC admin commands: ',
                                        '/start war [war size] [enemy name] - Start new caller',
                                        '/set cc [code] - Set code',
                                        '/update war timer [end|start] [timer] - Change war timer (##h##m)',
                                        '/set clan name [clan name] - Set clan name',
                                        '/set clan tag [clan tag] - Set clan tag',
                                        '/cc archive [on|off] - CC archive toggle',
                                        '/cc stacked calls [on|off] - Stacked calls toggle',
                                        '/cc promote @[player name] - Promote player to admin',
                                        '/cc demote @[player name] - Demote player from admin');
                      $message = implode("\n", $commands);
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
                  case 'clear_call':
                      $num = intval($out[1]);
                      $name = trim($out[2]);
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = $cc->delete_call($num, $name);
                      }
                  break;
                  case 'get_calls':
                      $update = $cc->get_update();
                      $calls = $cc->format_calls($update, true);
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
                          $message = implode("\n", $calls);
                      }
                      break;
                  case 'get_all_calls':
                      $update = $cc->get_update();
                      $calls = $cc->format_calls($update, false);
                      if ($invalid_cc) {
                          $message = $invalid_cc_error;
                      } else {
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
                                  mysqli_query($GLOBALS['con'], "UPDATE cc SET cc = '{$war['war_id']}' WHERE id = '{$row_id}'");
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
                      $message = sprintf('http://www.clashcaller.com/war/%s', $row['cc']);
                  break;
                  case 'cc_code':
                      $message = $row['cc'];
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
