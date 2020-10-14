<?php

function decrypt($encrypted)
{
    return trim(`java -cp bcprov-ext-jdk15on-165.jar CryptographyExample.java '$encrypted'`);
}

require_once 'auth.php';

$soldo = auth();

//echo json_encode([['qqq'=>'www', 'aaa' => 'sss'], ['zzz'=>'xxx', 'ccc'=>'vvvv']]);
//exit(0);

$page_number = intval($_GET['page_number']) - 1;

$cards = $soldo->search_cards([
    "type" => "wallet",
    "publicId" => $_GET['wallet_id'],
    "p" => strval($page_number)
])['results'];

$res = [];

$i = 0;
foreach ($cards as $card) {
    $card = $soldo->get_card($card['id']);
    $data = '';
    if (isset($_GET['cvv']))
        $data = decrypt($card["sensitive_data"]["encrypted_cvv"]);
    else if (isset($_GET['card_number']))
        $data = decrypt($card["sensitive_data"]["encrypted_full_pan"]);
    $res[] = ['name' => $card['name'], 'data' => $data];
    ++$i;
    if ($i === 2) break;
}


echo json_encode($res);
