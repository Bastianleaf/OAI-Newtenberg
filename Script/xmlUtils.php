<?php
set_error_handler('exceptions_error_handler');

//Funciones para curl y manejo de excepciones

/**
 * Funcion que realiza una llamada cul por get y retorna los resultados en xml
 * @param $host
 * @return mixed
 */
function curlXml($host){
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $host);
    curl_setopt($ch,CURLOPT_RETURNTRANSFER,TRUE);
    $xml = curl_exec($ch);
    curl_close($ch);
    return $xml;

}

/**
 * Funcion para manegar excepciones
 * @param $severity
 * @param $message
 * @param $filename
 * @param $lineno
 * @throws ErrorException
 */
function exceptions_error_handler($severity, $message, $filename, $lineno) {
    if (error_reporting() == 0) {
        return;
    }
    if (error_reporting() & $severity) {
        throw new ErrorException($message, 0, $severity, $filename, $lineno);
    }
}


// Funciones auxiliares para la creacion de xml

/**
 *
 * @param $path
 * @return array
 * retorna todos los articulos del directorio
 */
function checkArticles($path) {
    $aid = array();
    foreach ($path as $channel){
        $cid = explode("/",$channel[0])[1];
        $files = glob($channel[0]);
        foreach ($files as &$file) {
            $id = explode(".",explode("-",$file)[2])[0];
            $key = array_search($id, array_column($aid, 'aid'));
            if ($key != FALSE){
                array_push($aid[$key]["cid"],$cid);
            } else {
                array_push($aid,array("aid" => $id, "cid" => [$cid]));
            }
        }
    }
    return $aid;
}

/**
 * @param $xml
 * @param $item
 * @return int
 * compara un archivo actulizado xml del Engine con el creado anteriormente
 * retorna 1 si son iguales y 0 si el archivo no existe o no son de la misma fecha
 */
function compareDate($xml,$item) {
    if (!file_exists($item)) {
        return 0;
    }
    $xmlToCreate = simplexml_load_string("$xml") or die("Error: Cannot create object");
    $xmlCreated = simplexml_load_file("$item") or die("Error: Cannot create object");
    $date1 = $xmlToCreate->modificationdate->__toString();
    $date2 = $xmlCreated->metadata[4]->value->__toString();
    if ($date1 == $date2) {
        return 1;
    } else {
        return 0;
    }
}


//Filtros para xml

/**
 * Funcion que sirve para manejar los distintos filtros aplicables
 * @param $name
 * @param $xml
 * @param $pn
 * @param $item
 * @return mixed
 */
function handleFilter($name,$xml, $pn, $item){
    switch ($name){
        case "jerarquia_inversa_comas":
            return jerarquia_a_lista_inversa_separada_por_comas($xml, $pn, $item);
        case "texto_previo":
            return textoPrevioSubject($xml, $pn, $item);
    }
    return -1;
}

/**
 * Funcion que toma todos los pvid y les asigna su respectivo texto previo
 * @param $xml
 * @param $pn
 * @param $item
 * @return mixed
 */
function textoPrevioSubject($xml, $pn, $item){
    $i = 1;
    $meta = 0;
    foreach ($xml->propertyvalue as $valor){
        $meta = $item->addChild("metadata");
        $meta->addChild("key", $pn["qname"]);
        $meta->addChild("value", $pn["filters"][$i] . ":" .$valor );
        $i++;
    }
    return $meta;

}

/**
 * Funcion que toma todos los pv de un pn jerarquizado, generando los metadatos como
 * un unico elemento separado por comas
 * @param $xml
 * @param $pn
 * @param $item
 * @return mixed
 */
function jerarquia_a_lista_inversa_separada_por_comas($xml, $pn, $item){
    $lista = array();
    foreach ($xml->propertyvalue as $valor){
        array_unshift($lista, (string)$valor);
    }
    $meta = $item->addChild("metadata");
    $meta->addChild("key", $pn["qname"]);
    $meta->addChild("value", implode(", ", $lista));
    return $meta;
}


//Funciones para creacion de xml

/**
 * @param $file
 * @param $domain
 * @param $pnDict
 * @return mixed
 * crea un archivo XML en base al XML creado por el Engine
 */
function createItemXml($file, $domain, $pnDict, $cid) {
    $xml = simplexml_load_string("$file") or die("Error: Cannot create object");
    $item = new SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><item></item>');

    //aid
    $ntgMetadata = $item->addChild("metadata");
    $ntgMetadata->addChild("key","ntg.identifier");
    $ntgMetadata->addChild("value","$domain/article/" . $xml->aid);

    //title
    $titleMetadata = $item->addChild("metadata");
    $titleMetadata->addChild("key","dc.title");
    $titleMetadata->addChild("value",$xml->name);

    //description
    $descriptionMetadata = $item->addChild("metadata");
    $descriptionMetadata->addChild("key","dc.description");
    $descriptionMetadata->addChild("value",$xml->description);

    //date
    $dateMetadata = $item->addChild("metadata");
    $dateMetadata->addChild("key","dc.date.created");
    $dateMetadata->addChild("value",$xml->date);

    //date modified
    $modifiedMetadata = $item->addChild("metadata");
    $modifiedMetadata->addChild("key","dcterms.modified");
    $modifiedMetadata->addChild("value",$xml->modificationdate);

    //date created dc.date.created
    $createdMetadata = $item->addChild("metadata");
    $createdMetadata->addChild("key","dc.date.created");
    $createdMetadata->addChild("value",$xml->creationdate);

    //publication date dc.date.issued}
    $publicationMetadata = $item->addChild("metadata");
    $publicationMetadata->addChild("key","dc.date.created");
    $publicationMetadata->addChild("value",$xml->publicationdate);

    //author
    foreach ($xml->user->attributes() as $a => $b){
        if ($a === "href"){
            $autor = simplexml_load_string(curlXml($b));
            $autorMetadata = $item->addChild("metadata");
            $autorMetadata->addChild("key","dc.contributor.author");
            $autorMetadata->addChild("value",$autor->nickname);
        }
    }

    //url identifier
    foreach ($cid as $c){
        $identifierMetadata = $item->addChild("metadata");
        $identifierMetadata->addChild("key", "dc.identifier.uri");
        $identifierMetadata->addChild("value","https://" . $domain . "/" . $c . "/w3-article-" . $xml->aid .".html");
    }


    //abstract
    $abstractMetadata = $item->addChild("metadata");
    $abstractMetadata->addChild("key","dcterms.abstract");
    $abstractMetadata->addChild("value", $xml->content->body->children()[0]->abstract);



    //clasificandos
    foreach ($xml->propertyname as $clasificando){
        foreach($clasificando->attributes() as $a => $b) {
            if ($a ==="pnid") {
                try {
                    $pn = $pnDict[strval($b)];
                    //en caso de haber filtros, se aplican
                    if ($pn["filters"] != []){
                        foreach ($pn["filters"] as $filter){
                            handleFilter($filter, $clasificando,$pn, $item);
                        }
                    } else {
                        foreach ($clasificando->propertyvalue as $valor){
                            $meta = $item->addChild("metadata");
                            $meta->addChild("key", $pn["qname"]);
                            $meta->addChild("value", $valor);
                        }
                    }

                } catch (Exception $e) {
                    continue;
                }
            }
        }
    }
    return $item->asXML();
}

//print_r(checkArticles([["../605/w3-article*", 501], ["../601/w3-article*", 503]]));
