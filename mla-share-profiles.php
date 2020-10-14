<?php

function send_query($endpoint, $query, $method)
{
    $endpoint = "https://api.multiloginapp.com/v1/" . $endpoint;
    $headers = ["accept: application/json", "Content-Type: application/json"];
    $data = file_get_contents($endpoint, false, stream_context_create([
        'http' => [
            'method' => $method,
            'header' => $headers,
            'content' => $query,
        ]
    ]));
    $result = json_decode($data, true);
    return ($result) ? $result : $data;
}

//$token = 'confident_info';
//
//$response = send_query("profile/list?token=$token", '', 'GET');
//if (!isset($response['data'])) {
//    die("Error: cannot fetch list of profiles!\n");
//}
//$all_profiles = $response['data'];

$user = "confident_info@email";


$profile_names = explode("\n", file_get_contents('profiles.txt'));
$profile_names = ['6e3d2088-32a5-4a30-92cb-1cf07ccc6b0f'];

foreach ($profile_names as $profile_name) {
    $arr = [];
    foreach ($all_profiles as $profile) {
        if (strpos($profile['name'], $profile_name) !== false) {
            $arr[] = $profile['sid'];
        }
    }
    if (count($arr) === 1) {
        $url = "http://localhost:35000/api/v1/profile/share?profileId={$arr[0]}&user=$user";
        $resp = file_get_contents($url);
        var_dump($resp);
        while ($resp !== '{"status":"OK"}') {
            sleep(1);
            $resp = file_put_contents('log.txt', $resp . "\n", FILE_APPEND);
        }
    } else if (count($arr) > 1) {
        file_put_contents('log.txt', $profile_name . "\n", FILE_APPEND);
    }
}
