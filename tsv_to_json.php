<?php

$data_ob = file_get_contents("hpmon_all.tsv");

$data = explode("\n",$data_ob);
$keys = explode("\t",array_shift($data));

// convert tsv table data to json key value rows
$users = array();
foreach ($data as $row) {
    if ($row=="") continue;
    $values = explode("\t",$row);
    $user = array();
    for ($i=0; $i<count($keys); $i++) {
        $user[trim($keys[$i])] = trim($values[$i]);
    }
    $users[] = $user;
}

// write to json file
file_put_contents("data.json",json_encode($users,JSON_PRETTY_PRINT));


// print json_encode($users,JSON_PRETTY_PRINT);
