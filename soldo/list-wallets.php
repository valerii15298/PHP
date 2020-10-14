<?php

require_once 'auth.php';

$soldo = auth();

$employee_wallets = $soldo->search_wallets([
    "type" => "employee",
    "publicId" => $_GET['owner_id']
], true);

foreach ($employee_wallets as $wallet) {
    echo "<option value='{$wallet['id']}'>{$wallet['name']}</option>";
}
