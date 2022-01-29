<?php

include 'config.php';
$file = "servers.json";

function combine($data) {
    $combinated = array();
    foreach ($data as $server) {
        foreach ($server['data'] as $window => $block) {
            foreach ($block as $cat => $value) {
                foreach ($value as $type => $vValue) {
                    if (!(isset($combinated[$window][$cat][$type]))) { $combinated[$window][$cat][$type] = 0; }
                    $combinated[$window][$cat][$type] += $vValue;
                }
            }
        }
    }
    $combinated['nodes'] = getNodes($data);
    return json_encode($combinated,JSON_PRETTY_PRINT);
}

function getLabels($stats) {
    $response = array();
    foreach ($stats as $window => $block) {
        if ($window == "current" or $window == "nodes") { continue; }
        $date = date('d.m', $window);
        $response[] = "'{$date}'";
    }
    return $response;
}

function getData($stats,$cat,$var) {
    $response = array();
    foreach ($stats as $window => $block) {
        if ($window == "current") { continue; }
        foreach ($block as $category => $data) {
            if ($category != $cat) { continue; }
            $response[] = size($data,$var)['value'];
        }
    }
    return $response;
}

function prepare($payload) {
    $response = array();
    foreach ($payload['data'] as $key => $value) {
        $response['current'][$key] = $value;
    }
    return $response;
}

function size($stats,$field) {
    if (isset($stats[$field])) {
        if ($stats[$field] >= 1e+12) {
            return array('value' => round($stats[$field] / 1e+12,1),'type' => 'TB');
        } else {
            return array('value' => round($stats[$field] / 1e+9,1),'type' => 'GB');
        }
    } else {
        return array('value' => 0,'type' => 'GB');
    }
}

function getNodes($data) {
    $alive = 0;
    foreach ($data as $node) {
        if (isset($node['data'][strtotime('today midnight')])) { $alive += 1; }
    }
    return array('total' => count($data),'alive' => $alive);
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
            if (!array_key_exists($payload['name'], $data)) { $data[$payload['name']]['data'] = array();}
            $fresh = prepare($payload);
            $data[$payload['name']]['data']['current'] = $fresh['current'];
            $data[$payload['name']]['data'][strtotime('today midnight')] = $fresh['current'];
            file_put_contents("stats.json",combine($data));
            //Truncate
            ftruncate($handle, 0);
            //Reset pointer
            rewind($handle);
            //Encode data
            $json = json_encode($data,JSON_PRETTY_PRINT);
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
        <script src="js/chart.js"></script>
        <link rel="stylesheet" href="css/style.css?v=6">
        <script>
            labels = [<?php echo implode(",",getLabels($stats)); ?>]
            storage = [<?php echo implode(",",getData($stats,'storj','storage')); ?>]
            traffic = [<?php echo implode(",",getData($stats,'storj','bandwidth')); ?>]
        </script>
        </head>
        <body>
            <div class="content">
                <div class="container">
                    <div class="item">
                        <a href="index.php" class="white"><h1 class="center">bwstats</h1></a>
                    </div>
                    <div class="item">
                        <div class="container">
                            <div class="item w30">
                                <?php
                                echo "<h1>storj <smol>{$stats['nodes']['alive']}/{$stats['nodes']['total']} nodes</smol></h1>"
                                ?>
                            </div>
                            <div class="item w30">
                                <div class="container">
                                    <div class="item"><h2>Traffic</h2></div>
                                    <div class="item">
                                        <?php 
                                        $bandwidth = size($stats['current']['storj'],'bandwidth');
                                        echo "{$bandwidth['value']}{$bandwidth['type']}";
                                        ?>
                                    </div>
                                </div>
                            </div>
                            <div class="item w30">
                                <div class="container">
                                    <div class="item"><h2>Storage</h2></div>
                                    <div class="item">
                                        <?php 
                                        $storage = size($stats['current']['storj'],'storage');
                                        $available = size($stats['current']['storj'],'storageAvailable');
                                        echo "{$storage['value']}{$storage['type']} of {$available['value']}{$available['type']}";
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container">
                            <div class="item w100"><canvas id="traffic"></canvas></div>
                        </div>
                        <div class="container">
                            <div class="item w100"><canvas id="storage"></canvas></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="footer center">
                <p><a href="https://github.com/Ne00n/bwstats" target="_blank">bwstats</a> powered by cutting edge json database</p>
            </div>
        </body>
    <footer>
        <script src="js/stats.js?v=3"></script>
    </footer>
    </html>
    <?php
}


