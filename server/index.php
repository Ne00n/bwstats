<?php

include 'config.php';
$file = "servers.json";

function combine($data) {
    $combinated = array();
    foreach ($data as $server) {
        foreach ($server['data'] as $key => $value) {
            if (!(isset($combinated[$key]))) { $combinated[$key] = 0; }
            $combinated[$key] += $value;
        }
    }
    return json_encode($combinated);
}

if ($_SERVER['REQUEST_METHOD'] == "POST") {
    $payload = file_get_contents('php://input');
    if (strlen($payload) > 10000) {  http_response_code(413); die(); }
    $payload = json_decode($payload,true);
    if (json_last_error() !== 0) {  http_response_code(420); die(); }
    if (!isset($payload['name']) or !isset($payload['data']) or !isset($payload['token'])) {  http_response_code(400); die(); }
    if ($payload['token'] != _token) {  http_response_code(401); die(); }
    $i = 0;
    while ($i <= 15) {
        $handle = fopen($file,"c+");
        //Requesting lock on file
        if (flock($handle,LOCK_EX)) {
            clearstatcache();
            //Read file
            $raw = fread($handle,filesize($file));
            $data = json_decode($raw,true);
            //Load it
            if ($payload['name'] != $data) { $data[$payload['name']]['data'] = "";}
            $data[$payload['name']]['data'] = $payload['data'];
            file_put_contents("stats.json",combine($data));
            //Truncate
            ftruncate($handle, 0);
            //Reset pointer
            rewind($handle);
            //Encode data
            $json = json_encode($data);
            //Write
            fwrite($handle,$json);
            fflush($handle);
            //Unlock
            flock($handle,LOCK_UN);
            break;
        } else {
            //Wait until we try to get a lock again
            sleep(rand(1, 4));
            if ($i == 14) { http_response_code(423); die(); }
        }
    }
} else {
    $stats = file_get_contents("stats.json");
    var_dump($stats);
}


