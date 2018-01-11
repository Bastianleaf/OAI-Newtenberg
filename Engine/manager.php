<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $file = fopen("lock","w+");
    if (flock($file,LOCK_EX)){
        exec("/opt/dspace/bin/dspace oai import -c", $res);
        flock($file,LOCK_UN);
    } else {
        echo "Error: ya esta en ejecucion\n.";
    }
} else {
    exit;
}