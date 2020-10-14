<?php


define("PUBLISHER_AUTO_LOGIN", true);

function getRandResolution()
{
    $resolution_arr = ["1920x1080", "1920x1200", "2048x1152", "2304x1440",];
    return $resolution_arr[rand(0, 3)];
}

function getRandCores()
{
    $cores_arr = [4, 6, 8];
    return $cores_arr[rand(0, 2)];
}

$_POST['profile_name'] = 'Testpp11';
$_POST['proxy_ip'] = 'confident_info';
$_POST['port'] = '6128';
$_POST['username'] = 'confident_info';
$_POST['password'] = 'confident_info';

if (!empty($_POST['profile_name']) && !empty($_POST['proxy_ip']) && !empty($_POST['port']) && !empty($_POST['username'] && !empty($_POST['password']))) {

    $language = !empty($_POST['language']) ? $_POST['language'] : "uk,ru;q=0.9,uk-UA;q=0.8,en;q=0.7";
    $cores = !empty($_POST['cores']) ? $_POST['cores'] : getRandCores();
    $resolution = !empty($_POST['resolution']) ? $_POST['resolution'] : getRandResolution();

    $profile_name = $_POST['profile_name'];
    $proxy_ip = $_POST['proxy_ip'];
    $port = $_POST['port'];
    $username = $_POST['username'];
    $password = $_POST['password'];


    $mla_version = "4.5.3";
//    $token = "confident_info"; // 
//    $token = 'confident_info'; // 
    $token = "confident_info"; // NEW ACC MLA 5
    $body_content = '{ "name": "' . $profile_name . '", "notes": "Test profile notes", "browser": "mimic", "os": "win", "googleServices": true, "enableLock": false, "navigator": { "userAgent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:62.0) Gecko/20100101 Firefox/62.0", "resolution": "' . $resolution . '", "language": "' . $language . '", "platform": "Win64", "doNotTrack": 0, "hardwareConcurrency": ' . $cores . ' }, "storage": { "local": true, "extensions": true, "bookmarks": false, "history": false, "passwords": true },"network": { "proxy": { "type": "HTTP", "host": "' . $proxy_ip . '", "port": "' . $port . '", "username": "' . $username . '", "password": "' . $password . '" }, "dns": [] }, "plugins": { "enableVulnerable": true, "enableFlash": true }, "timezone": { "mode": "FAKE", "fillBasedOnExternalIp": true, "zoneId": null }, "geolocation": { "mode": "PROMPT", "fillBasedOnExternalIp": true }, "audioContext": { "mode": "NOISE" }, "canvas": { "mode": "NOISE" }, "fonts": { "mode": "FAKE", "maskGlyphs": true,"families": [ "MS Serif", "Segoe UI" ] }, "mediaDevices": { "mode": "FAKE", "videoInputs": 1, "audioInputs": 2, "audioOutputs": 3 }, "webRTC": { "mode": "FAKE", "fillBasedOnExternalIp": true, "localIps": [ "192.168.0.162","192.168.0.169" ] }, "webGL": { "mode": "NOISE" }, "webGLMetadata": { "mode": "MASK", "vendor": "Google Inc.", "renderer": "ANGLE AMD Mobility Radeon HD 5000" }, "extensions": { "enable": true, "names": [ "ext1.crx", "ext2.crx" ] } }';

    $url = "https://api.multiloginapp.com/v2/profile?token=$token&mlaVersion=$mla_version";
    $opts = array('http' =>
        array(
            'method' => 'POST',
            'header' => ["accept: application/json", "Content-Type: application/json"],
            'timeout' => 5,
            'content' => $body_content
        )
    );
    $context = stream_context_create($opts);
    $result = file_get_contents($url, false, $context);
//echo $result;

    $arr = json_decode($result, true);

    if (!isset($arr["uuid"])) {
        echo "Error!";
        return;
    }

    echo "Profile id: " . $arr["uuid"] . "\n" . "Profile_name: " . $profile_name . "\n";

}

