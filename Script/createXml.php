<?php

include 'xmlUtils.php';

if ($argc < 3 ) {
    exit( "Usage: $argv[0] <dictionary xml> <dictionary values>\n");
}

//toma de diccionarios
$xmlDictJson = $argv[1]; //dicionario con datos de ejecucion
$pnDictJson = $argv[2]; //diccionario de nombres de clasificando con sus filtros
$itemsDict= file_get_contents($xmlDictJson);
$itemJson = json_decode($itemsDict, true);
$pnDict = file_get_contents($pnDictJson);
$pnJson = json_decode($pnDict, true);

//se verifica que articulos deben revisarse
$itemsToAdd = checkArticles($itemJson["htmlPath"]);

$txt = fopen("xmlToAdd.txt","a+");
$channel = $itemJson["channel"];
foreach ($channel as $c){
    foreach ($itemsToAdd as $rawItems) {
        $number = $rawItems["aid"];
        $cid = $rawItems["cid"];
        //llamada a curl para recibir informacion de xml por API-REST de Engine
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $itemJson["host"] . $c . "/id/" . $number);
        curl_setopt($ch, CURLOPT_COOKIE,$itemJson["cookie"]);
        curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
        $xml = curl_exec($ch);
        curl_close($ch);

        //se compara la ultima fecha de modificacion del xml recibido por el Engine
        //con el archivo xml creado con anterioridad (si es que existe)
        if (compareDate($xml,"xml/item-$number.xml") == 1) {
            print "Objeto item-$number ya existe \n";
            continue;
        }

        //si es que no se encuentra el articulo en el canal
        if (strpos($xml, "error")){
            continue;
        }

        //Se crean los items a agregar
        $item = createItemXml($xml, $itemJson["domain"], $pnJson, $cid);

        //Crear directorio xlm en caso de no existir
        if (!file_exists("xml")) {
            mkdir('xml',0775);
        }

        //Se escribe el archivo xml respectivo
        $toWrite = fopen("xml/item-$number.xml", "w");
        fwrite($toWrite,$item);
        fclose($toWrite);

        //Se agrega el nombre del archivo a la lista de items a agregar/actualizar

        fwrite($txt,"xml/item-$number.xml\n");

        print "Item $number creado \n";
    }
}

fclose($txt);

