<?php
$dir = dirname(__FILE__);
chdir("../www");

require "Lib/load_database.php";

require ("Modules/system/system_model.php");
$system = new System($mysqli);

$data = $system->list_admin();
foreach ($data as $row) {
    $systemid = $row->id;

    if (trim($row->hp_model) == "Mitsubishi Ecodan" && $row->refrigerant == "R32") {
        $capacity = $row->hp_output;

        print $systemid." ".$row->hp_model." ".$row->hp_output." ".$row->flow_temp." ".$capacity." \n";

        // update the hp_max_output field 
        if ($capacity > 0) {
            $mysqli->query("UPDATE system_meta SET hp_max_output = $capacity WHERE id = $systemid");
        }


    }
    
}
