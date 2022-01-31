<?php

include 'config.php';
$file = "servers.json";

function combine($data) {
    $combinated = array();
    foreach ($data as $server) {
        foreach ($server['data'] as $window => $block) {
            foreach ($block as $cat => $value) {
                $combinated[$cat.'nodes'] = getNodes($data,$cat);
                foreach ($value as $type => $vValue) {
                    if (!(isset($combinated[$window][$cat][$type]))) { $combinated[$window][$cat][$type] = 0; }
                    $combinated[$window][$cat][$type] += $vValue;
                }
            }
        }
    }
    return json_encode($combinated,JSON_PRETTY_PRINT);
}

function getLabels($stats,$cat = "storj") {
    $response = array();
    foreach ($stats as $window => $block) {
        if (!isset($block[$cat])) { continue; }
        if ($window == "current" or $window == "nodes") { continue; }
        $date = date('d.m', $window);
        $response[] = "'{$date}'";
    }
    return $response;
}

function getData($stats,$cat,$var,$targetWindow=0) {
    $response = array();
    foreach ($stats as $window => $block) {
        if ($window == "current") { continue; }
        if ($window < $targetWindow) { continue; }
        foreach ($block as $category => $data) {
            if ($category != $cat) { continue; }
            $response[] = size($data,$var)['value'];
        }
    }
    return $response;
}

function getLastDays($stats,$cat,$var,$days=4) {
    $scprime = getData($stats,$cat,$var,strtotime('today midnight') - (86400 * $days));
    return size(array("total" => $scprime[count($scprime) -1] - $scprime[0]),"total");
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
        } elseif ($stats[$field] >= 1e+9) {
            return array('value' => round($stats[$field] / 1e+9,1),'type' => 'GB');
        } elseif ($stats[$field] >= 1000) {
                return array('value' => round($stats[$field] / 1000,1),'type' => 'TB');
        } else {
            return array('value' => round($stats[$field],1),'type' => 'GB');
        }
    } else {
        return array('value' => 0,'type' => 'GB');
    }
}

function getNodes($data,$cat) {
    $alive = 0;
    foreach ($data as $node) {
        if (isset($node['data'][strtotime('today midnight')][$cat])) { $alive += 1; }
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
        <link rel="stylesheet" href="css/style.css?v=8">
        <script>
            labels = [<?php echo implode(",",getLabels($stats)); ?>]
            scprimeLabels = [<?php echo implode(",",getLabels($stats,"scprime")); ?>]
            storage = [<?php echo implode(",",getData($stats,'storj','storage')); ?>]
            traffic = [<?php echo implode(",",getData($stats,'storj','bandwidth')); ?>]
            scprimeStorage = [<?php echo implode(",",getData($stats,'scprime','storage')); ?>]
        </script>
        </head>
        <body>
            <div class="content">
                <div class="container base">
                    <div class="item">
                        <a href="index.php" class="white"><h1 class="center">bwstats</h1></a>
                    </div>
                    <div class="item">
                        <div class="container">
                            <div class="item w30">
                                <?php
                                echo "<h1>storj <smol>{$stats['storjnodes']['alive']}/{$stats['storjnodes']['total']} nodes</smol></h1>"
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
                                    <?php
                                    $twoDays = getLastDays($stats,'storj','storage',2);
                                    echo '<div class="item"><h2>Storage <med>'.$twoDays["value"].$twoDays["type"].' 48H</med></h2></div>';
                                    ?>
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
                            <div class="item"><canvas id="traffic"></canvas></div>
                        </div>
                        <div class="container">
                            <div class="item"><canvas id="storage"></canvas></div>
                        </div>
                    </div>
                    <div class="item">
                        <div class="container">
                            <div class="item w30">
                                <?php
                                echo "<h1>scprime <smol>{$stats['scprimenodes']['alive']}/{$stats['scprimenodes']['total']} nodes</smol></h1>"
                                ?>
                            </div>
                            <div class="item w30">
                                <div class="container">
                                    <?php
                                    $twoDays = getLastDays($stats,'scprime','storage',2);
                                    echo '<div class="item"><h2>Storage <med>'.$twoDays["value"].$twoDays["type"].' 48H</med></h2></div>';
                                    ?>
                                    <div class="item">
                                        <?php 
                                        $storage = size($stats['current']['scprime'],'storage');
                                        $available = size($stats['current']['scprime'],'storageAvailable');
                                        echo "{$storage['value']}{$storage['type']} of {$available['value']}{$available['type']}";
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="container">
                            <div class="item"><canvas id="scprime"></canvas></div>
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


