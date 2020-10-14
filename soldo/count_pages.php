<?php

require_once 'auth.php';

$soldo = auth();

$resp = $soldo->search_cards([
    "type" => "wallet",
    "publicId" => $_GET['wallet_id']
]);

echo strval($resp['pages']);