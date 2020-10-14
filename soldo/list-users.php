<?php

require_once 'auth.php';

$soldo = auth();

$all_employees = $soldo->search_users([], true);

$options = [];
foreach ($all_employees as $employee) {
    $owner = $employee['name']
        . (isset($employee['middlename']) ? ' ' . $employee['middlename'] . ' ' : ' ')
        . $employee['surname'];
    $options[] = "<option value='{$employee['id']}'>$owner</option>";
}

for ($i = 0; $i < (count($options) - 1); ++$i) {
    for ($j = $i + 1; $j < count($options); ++$j)
    if (strpos($options[$i], 'Farm') === false && strpos($options[$j], 'Farm') !== false) {
        $temp = $options[$i];
        $options[$i] = $options[$j];
        $options[$j] = $temp;
    }
}

foreach ($options as $option) {
    echo $option;
}