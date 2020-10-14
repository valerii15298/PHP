<?php

require_once 'Soldo.php';

function auth(): SoldoAPI
{
    $client_id = 'confident info';
    $client_secret = 'confident info';
    $token = "confident info";


    $public_rsa_string = "confident info";

    $private_rsa_string = "confident info";

    $soldo_creds = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'token' => $token,
        'private_rsa' => $private_rsa_string,
        'public_rsa' => $public_rsa_string
    ];

    return new SoldoAPI($soldo_creds, true);
}
