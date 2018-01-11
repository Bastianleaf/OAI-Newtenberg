<?php

include 'itemUtils.php';

if ($argc < 2 ) {
    exit( "Usage: $argv[0] <dictionary>\n" );
}

// Se guardan las variables del diccionario
$string = file_get_contents($argv[1]);
$dictionary = json_decode($string, true);
$host = $dictionary["host"];
$email = $dictionary["email"];
$pass =  $dictionary["pass"];
$collection = $dictionary["collection"];
$path = $dictionary["xmlPath"];

//En caso de que no existan ni los archivos xml o el txt con la lista
//no se agrega nada
if (!file_exists($path) or !file_exists("xml") or filesize($path) == 0) {
    print("No hay nuevos eidox a agregar o actualizar\n");
    return 0;
}

//Se carga la lista de xml a agregar o actualizar
$files = file($path, FILE_IGNORE_NEW_LINES) or die("No existen items nuevos a agregar o actualizar") ;

//Se logea a la API-REST
$cookie = login($host,$email, $pass);

//Por cada item a agregar se hace la respectiva insercion.
foreach ($files as $file){
    insertItem($host,$cookie,$collection,$file);
}

//Se borra el archivo con lista de xml
unlink($dictionary["xmlPath"]);
//Se deslogea
logout($dictionary["host"], $cookie);

