<?php

include 'config.php';
$file = "servers.json";

function combine($data) {
    $combinated = array();
    foreach ($data as $server) {
        foreach ($server['data'] as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $vKey => $vValue) {
                    if (!(isset($combinated[$key]['current'][$vKey]))) { $combinated[$key]['current'][$vKey] = 0; }
                    if (!(isset($combinated[$key][strtotime('today midnight')][$vKey]))) { $combinated[$key][strtotime('today midnight')][$vKey] = 0; }
                    $combinated[$key]['current'][$vKey] += $vValue;
                    $combinated[$key][strtotime('today midnight')][$vKey] += $vValue;
                }
            } else {
                if (!(isset($combinated[$key]))) { $combinated[$key] = 0; }
                $combinated[$key] += $value;
            }
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
    if (strlen($payload['token'] > 60)) { http_response_code(420); die(); }
    if (!in_array($payload['token'],_tokens)) {  http_response_code(401); die(); }
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
    $raw = file_get_contents("stats.json");
    $stats = json_decode($raw,true);
    ?>
    <!DOCTYPE html>
    <html lang="en">
        <head>
        <meta charset="utf-8">
        <title>bwstats</title>
        <meta name="description" content="">
        <meta name="author" content="">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <link rel="stylesheet" href="css/style.css">
        </head>
        <body>
            <div class="content">
                <div class="container">
                    <div class="item">
                        <h1 class="center">bwstats</h1>
                    </div>
                    <div class="item">
                        <div class="container">
                            <div class="item w30">
                                <h1>storj</h1>
                            </div>
                            <div class="item w30">
                                <div class="container">
                                    <div class="item"><h2>Traffic</h2></div>
                                    <div class="item"><?php echo (isset($stats['storj']['bandwidth']) ? round($stats['storj']['bandwidth'] / 1e+9,1) : 'n/a'); ?>GB</div>
                                </div>
                            </div>
                            <div class="item w30">
                                <div class="container">
                                    <div class="item"><h2>Storage</h2></div>
                                    <div class="item"><?php echo (isset($stats['storj']['storage']) ? round($stats['storj']['storage'] / 1e+9,1) : 'n/a'); ?>GB</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer center">
                <p><a href="https://github.com/Ne00n/bwstats" target="_blank">bwstats</a> powered by cutting edge json database</p>
            </div>
        </body>
    </html>
    <?php
}


