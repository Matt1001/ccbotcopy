<?php
require 'config.php';
class ClashCaller
{
    public $cc;
    public $cc_api = 'http://clashcaller.com/api.php';
    public $config;
    public function __construct()
    {
    }
    public function set_cc($code)
    {
        $this->cc = $code;
    }
    public function set_config($config)
    {
        $this->config = $config;
    }
    public function api_call($data)
    {
        $page = Requests::post($this->cc_api, array(), $data);
        if (preg_match("/>Invalid War ID\./", $page -> body, $nowar)) {
            return array('error' => 'Invalid War ID');
        } else {
            return json_decode($page -> body, true);
        }
    }
    public function start_war($enemy, $size)
    {
        $data = array('REQUEST' => 'CREATE_WAR',
                      'cname' => $this->config['clan_name'],
                      'ename' => $enemy,
                      'size' => $size,
                      'timer' => $this->config['call_timer'],
                      'searchable' => $this->config['archive'],
                      'clanid' => $this->config['clan_tag']);
        $page = Requests::post($this->cc_api, array(), $data);
        if(preg_match("/war\/(.*)/", $page -> body, $war_id)){
            return array('war_id' => $war_id[1]);
        }else{
            return array('error' => 'unknown error');
        }
    }
    public function get_update()
    {
        $data = array('REQUEST' => 'GET_FULL_UPDATE', 'warcode' => $this->cc);
        return $this->api_call($data);
    }
    public function update_timer($st, $timer){
        $start_ = ($st == 'start') ? 's' : 'e';
        $mins_ = $this -> timer_to_minute($timer);
        $data = array('REQUEST' => 'UPDATE_WAR_TIME',
                      'warcode' => $this->cc,
                      'start' => $start_,
                      'minutes' => $mins_);
        $this -> api_call($data);
        return "War time updated";

    }
    public function update_stars($num, $stars, $name, $greeting = '', $is_admin = false){
        if($stars < 0 || $stars > 3){
            return 'Invalid stars';
        }
        $all = $this -> get_update();
        $calls = $this -> serialize_calls($all['calls'], $all['general']);
        $y_ = $num - 1;
        $found_call = false;
        if(!isset($calls[$y_])){
            if($is_admin){
                $this -> call_target($num, $name);
                return $this -> update_stars($num, $stars, $name, $greeting, $is_admin);
            }else{
                return sprintf("You have no calls on #%d, %s", $num, $name);
            }
        }else{
            foreach($calls[$y_] as $k => $c){
                if($c['name'] == $name){
                    $x_ = $c['x'];
                    $found_call = true;
                    break;
                }
            }
        }
        if($found_call){
            $cgn_message = '';
            if($greeting != ''){
                if($stars == 3){
                    $cgn_message = preg_replace("/@name/", $name, $greeting);
                    $cgn_message = "\n" . $this->spin_text($cgn_message);
                }
            }
            $data = array('REQUEST' => 'UPDATE_STARS',
                      'warcode' => $this->cc,
                      'posx' => $x_,
                      'posy' => $y_,
                      'value' => $stars + 2);
            $this -> api_call($data);
            return sprintf("Logged %d stars on #%d by %s%s", $stars, $num, $name, $cgn_message);
        }else{
            if($is_admin){
                $this -> call_target($num, $name);
                return $this -> update_stars($num, $stars, $name, $greeting, $is_admin);
            }else{
                return sprintf("You have no calls on #%d, %s", $num, $name);
            }
        }
    }
    public function delete_call($num, $name){
        $all = $this -> get_update();
        $calls = $this -> serialize_calls($all['calls'], $all['general']);
        $y_ = $num - 1;
        $found_call = false;
        if(!isset($calls[$y_])){
            return sprintf("You have no calls on #%d, %s", $num, $name);
        }else{
            foreach($calls[$y_] as $k => $c){
                if($c['name'] == $name){
                    $x_ = $c['x'];
                    $found_call = true;
                    break;
                }
            }
        }
        if($found_call){
            $data = array('REQUEST' => 'DELETE_CALL',
                      'warcode' => $this->cc,
                      'posx' => $x_,
                      'posy' => $y_);
            $this -> api_call($data);
            return sprintf("Deleted call on #%d by %s", $num, $name);
        }else{
            return sprintf("You have no calls on #%d, %s", $num, $name);
        }
    }
    public function call_target($num, $name){
        $data = array('REQUEST' => 'APPEND_CALL',
                      'warcode' => $this->cc,
                      'posy' => $num - 1,
                      'value' => $name);
        $all = $this -> get_update();
        if($num > intval($all['general']['size'])){
            return "Target out of bounds";
        }
        if(!$this -> config['stacked_calls']){
            $calls = $this -> serialize_calls($all['calls'], $all['general']);
            $y_ = $num - 1;
            $found_call = false;
            if(isset($calls[$y_])){
                foreach($calls[$y_] as $c){
                    if($c['active']) {
                        $c_ = $c;
                        $found_call = true;
                        break;
                    }
                }
            }
            if(!$found_call){
                $cd = $this -> api_call($data);
                return sprintf("Called #%d for %s", $num, $name);
            }else{
                return sprintf("#%d already called by %s (%s)", $num, $c_['name'], $c_['timer']);
            }
        }else{
            $this -> api_call($data);
            return sprintf("Called #%d for %s", $num, $name);
        }
    }
    public function get_stats($clan_tag, $pl_name){
        $data = array('REQUEST' => 'SEARCH_FOR_PLAYER',
                      'clan' => $clan_tag,
                      'name' => $pl_name);
        $resp = $this->api_call($data);
        $out = sprintf("Player %s not found", $pl_name);
        if(count($resp['attacks']) > 0){
            $stats = array('stars' => array(0, 0, 0, 0), 'total_stars' => 0, 'total_attacks' => count($resp['attacks']));
            $wars_in = array();
            foreach($resp['attacks'] as $at){
                array_push($wars_in, $at['ID']);
                $stars = $at['STAR'] - 2;
                $stats['stars'][$stars]++;
                $stats['total_stars'] += $stars;
            }
            $wars_part_in = count(array_unique($wars_in));
            $out = sprintf("Stats for %s:\nWars participated: %d\nTotal stars: %d\nTotal attacks: %d\nAverage stars: %s",
                            $pl_name, $wars_part_in, $stats['total_stars'], $stats['total_attacks'], round($stats['total_stars'] / $stats['total_attacks'], 2));
            $out = sprintf("%s\n\n3 stars: %d (%s)\n2 stars: %d (%s)\n1 stars: %d (%s)\n0 stars: %d (%s)", $out,
                            $stats['stars'][3], round(($stats['stars'][3] / $stats['total_attacks'] * 100), 2) . "%",
                            $stats['stars'][2], round(($stats['stars'][2] / $stats['total_attacks'] * 100), 2) . "%",
                            $stats['stars'][1], round(($stats['stars'][1] / $stats['total_attacks'] * 100), 2) . "%",
                            $stats['stars'][0], round(($stats['stars'][0] / $stats['total_attacks'] * 100), 2) . "%");
        }
        return $out;
    }
    public function get_war_status($data){
        $now = strtotime($data['general']['checktime']);
        $war_start = strtotime($data['general']['starttime']);
        $war_end = $war_start + $this -> timer_to_second('24h00m');
        $war_started = false;
        $war_size = intval($data['general']['size']);
        $diff = $war_start - $now;
        $out = array();
        $calls = $this -> serialize_calls($data['calls'], $data['general'], false);
        $targets = isset($data['targets']) ? $this -> serialize_targets($data['targets']) : false;
        $out = array();
        if($war_start < $now){
            $war_started = true;
            $diff = $war_end - $now;
        }
        if($now > $war_end){
            array_push($out, sprintf("War has finished vs %s", $data['general']['enemyname']));
        }else {
            $war_time = $this->second_to_timer($diff);
            array_push($out, sprintf("War %s in %s vs %s", ($war_started ? 'ends' : 'starts'), $war_time, $data['general']['enemyname']));
        }
        for($i = 0; $i < $war_size; $i++){
            $target_name_ = '';
            $num_ = $i;
            $status_ = 'not attacked';

            if(isset($targets[$i])) {
                if(strlen($targets[$i]['name']) > 0){
                    $target_name_ = sprintf(" (%s)", $targets[$i]['name']);
                }
            }
            if(isset($calls[$i])){
                $best_attack_ = false;
                $total_attacks_ = 0;
                $full_attacks_ = 0;
                foreach($calls[$i] as $c){
                    if($c['attacked']){
                        $total_attacks_++;
                        if($c['stars'] == 3){
                            $full_attacks_++;
                        }
                        if(!isset($best_attack_)){
                            $best_attack_ = $c;
                        }
                        if($c['stars'] > $best_attack_['stars']){
                            $best_attack_ = $c;
                        }
                    }
                }
                if($best_attack_){
                    $status_ = sprintf("(%d/%d) %s (%s)", $full_attacks_, $total_attacks_, $best_attack_['name'], $best_attack_['timer']);
                }
            }
            array_push($out, sprintf("#%d%s: %s", $i + 1, $target_name_, $status_));
        }
        return $out;
    }
    public function format_calls($data, $only_active = false)
    {
        $general_ = $data['general'];
        $now = strtotime($data['general']['checktime']);
        $war_start = strtotime($data['general']['starttime']);
        $war_end = $war_start + $this -> timer_to_second('24h00m');
        $war_started = false;
        $war_size = intval($data['general']['size']);
        $diff = $war_start - $now;
        $out = array();
        $calls = $this -> serialize_calls($data['calls'], $data['general'], false);
        $targets = isset($data['targets']) ? $this -> serialize_targets($data['targets']) : false;
        $out = array();
        if($war_start < $now){
            $war_started = true;
            $diff = $war_end - $now;
        }
        if($now > $war_end){
            array_push($out, sprintf("War has finished vs %s", $data['general']['enemyname']));
        }else {
            $war_time = $this->second_to_timer($diff);
            array_push($out, sprintf("War %s in %s vs %s", ($war_started ? 'ends' : 'starts'), $war_time, $data['general']['enemyname']));
        }

        $calls = $this -> serialize_calls($data['calls'], $data['general'], $only_active);
        foreach($calls as $num => $call){
            $call__ = array();
            $target_name_ = '';
            if($targets){
                if(isset($targets[$num])) {
                    if(strlen($targets[$num]['name']) > 0){
                        $target_name_ = sprintf(" (%s)", $targets[$num]['name']);
                    }
                }
            }
            if($this -> config['stacked_calls']){
                foreach($call as $c){
                    array_push($call__, sprintf("%s (%s)", $c['name'], $c['timer']));
                }
            }else{
                $best_attack_ = false;
                $full_attacks_ = 0;
                foreach($call as $c){
                    if($c['attacked']){
                        if(!isset($best_attack_)){
                            $best_attack_ = $c;
                        }
                        if($c['stars'] > $best_attack_['stars']){
                            $best_attack_ = $c;
                        }
                    }
                }
                $end_call = ($best_attack_) ? $best_attack_ : end($call);
                array_push($call__, sprintf("%s (%s)", $end_call['name'], $end_call['timer']));
            }
            array_push($out, sprintf("#%d%s: %s", $num + 1, $target_name_, implode(", ", $call__)));
        }
        if(count($out) == 1){
            array_push($out, sprintf("No%scalls", ($only_active ? ' active ' : ' ')));
        }
        return $out;
    }
    public function set_breakdown($data, $bd, $ths){
        $bds_ = explode("/", $bd);
        $ths_ = explode("/", $ths);
        if(count($bds_) != count($ths_)){
            return "Please correct breakdown:townhall";
        }
        $war_size = intval($data['general']['size']);
        $actual_size = 0;
        if(array_sum($bds_) != $war_size){
            return "Breakdown is not equal to war size";
        }
        $breakdown = array();
        foreach($bds_ as $k => $b){
            $cn_ = intval($b);
            $th_ = sprintf("TH%s", $ths_[$k]);
            for($i = 0; $i < $cn_; $i++){
                array_push($breakdown, $th_);
            }
        }
        foreach($breakdown as $pos => $name){
            $data = array('REQUEST' => 'UPDATE_TARGET_NAME',
                          'warcode' => $this -> cc,
                          'posy' => $pos,
                          'value' => $name);
            $this -> api_call($data);
        }
        return "Townhall breakdown set on CC";
    }
    public function update_note($target, $note){
        $data = array('REQUEST' => 'UPDATE_TARGET_NOTE',
                      'warcode' => $this -> cc,
                      'posy' => $target - 1,
                      'value' => $note);
        $this->api_call($data);
        return sprintf("Note on %d updated", $target);
    }
    public function get_note($target){
        $num = $target - 1;
        $data = $this -> get_update();
        if($num <= intval($data['general']['size'])){
            $targets = isset($data['targets']) ? $this -> serialize_targets($data['targets']) : false;
            if(!isset($targets[$num])){
                return "No note on #" . $target . " found";
            }else{
                $tx_ = $targets[$num];
                $name__ = ($tx_['name'] == '') ? '' : " ({$tx_['name']})";
                if($tx_['note'] != ''){
                    return sprintf("#%d%s: %s", $target, $name__, $tx_['note']);
                }else{
                    return "No note on #" . $target . " found";
                }
            }
        }else{
            return "Target out of bounds";
        }
    }
    public function format_call($data, $general, $status = false){
        $check_time = strtotime($general['checktime']);
        $war_start = strtotime($general['starttime']);
        $call_start = strtotime($data['calltime']);
        $call_timer = intval($general['timerlength']);
        $timer_show = true;
        if($call_timer > 0){
            $call_end = strtotime($data['calltime']) + ($call_timer * 60 * 60);
        }else if($call_timer < 0){
            if($call_start < $war_start) $call_start = $war_start;
            $diff_ = $war_start + $this -> timer_to_second('24h00m') - $check_time;
            $diff_ /= abs($call_timer);
            $call_end = $call_start + $diff_;
        }else{
            $timer_show = false;
            $active_call = true;
            $timer_text = 'called';
        }
        $active_call = true;
        $attacked = false;
        $stars = 0;
        if($timer_show){
            if($call_start < $war_start){
                $call_end = $war_start + (intval($general['timerlength']) * 60 * 60);
            }
            if($call_end < $check_time){
                $timer_text = 'expired';
                $active_call = false;
            }else{
                $diff = $call_end - $check_time;
                $timer_text = $this -> second_to_timer($diff);
            }
            if($war_start > $check_time){
                $timer_text = 'called';
            }
            if($general['timerlength'] == '0'){
                $timer_text = 'called';
            }
        }
        if($data['stars'] > 1){
            $active_call = false;
            $attacked = true;
            $stars = $data['stars'] - 2;
            switch($data['stars']){
                case 2:
                    $timer_text = '0 stars';
                break;
                case 3:
                    $timer_text = '*';
                break;
                case 4:
                    $timer_text = '**';
                break;
                case 5:
                    $timer_text = '***';
                break;
            }
        }
        return array('active' => $active_call, 'attacked' => $attacked, 'stars' => $stars, 'name' => $data['playername'], 'timer' => $timer_text, 'x' => $data['posx']);
    }
    public function serialize_targets($targets){
        $out = array();
        foreach($targets as $t){
            $pos_ = intval($t['position']);
            if(!isset($out[$pos_])){
                $out[$pos_] = array('name' => $t['name'], 'note' => $t['note']);
            }
        }
        return $out;
    }
    public function serialize_calls($data, $general_, $only_active = false){
        $calls = array();
        foreach($data as $call){
            $y_ = $call['posy'];
            $x_ = $call['posx'];
            $status = $this -> format_call($call, $general_);
            $push_call = false;
            if($only_active){
                if($status['active']) {
                    $push_call = true;
                }
            }else{
                $push_call = true;
            }
            if($push_call){
                if(!isset($calls[$y_])){
                    $calls[$y_] = array();
                }
                if(!isset($calls[$y_][$x_])){
                    $calls[$y_][$x_] = $status;
                }
            }
        }
        ksort($calls);
        return $calls;
    }
    public function is_admin($uid, $admins){
        $is_admin = false;
        if(count($admins) > 0){
            foreach($admins as $a){
                if($a['id'] == $uid){
                    $is_admin = true;
                }
            }
        }
        return $is_admin;
    }
    public function second_to_timer($num)
    {
        $num = abs($num);

        $hours = floor($num / 3600);
        $minutes = floor(($num / 60) % 60);
        $seconds = $num % 60;
        return "$hours".'h'.$minutes.'m';
    }
    public function timer_to_minute($string){
        if(preg_match("/([\d]{1,2})h([\d]{1,2})m/", $string, $timer)){
            return ($timer[1] * 60) + ($timer[2]);
        }else{
            return 0;
        }
    }
    public function timer_to_second($string){
        if(preg_match("/([\d]{1,2})h([\d]{1,2})m/", $string, $timer)){
            return ($timer[1] * 60 * 60) + ($timer[2] * 60);
        }else{
            return 0;
        }
    }
    public function spin_text($text)
    {
        return preg_replace_callback(
            '/\{(((?>[^\{\}]+)|(?R))*)\}/x',
            array($this, 'replace'),
            $text
        );
    }
    public function replace($text)
    {
        $text = $this->spin_text($text[1]);
        $parts = explode('|', $text);
        return $parts[array_rand($parts)];
    }
}
