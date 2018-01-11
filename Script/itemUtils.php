<?php

//Funciones que usan CURL para el manejo de la API-REST

/**
 * Funcion que loguea en la API-REST de dspace, retornando la cookie asociada
 * @param $host
 * @param $email
 * @param $pass
 * @return bool|string
 *
 */
function login ($host, $email, $pass) {
    //curl para obtener cookie
    $ch = curl_init();
    $curl_log = fopen('error-curl.txt', 'w+');
    $url = $host . "login";
    curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => "email=$email&password=$pass",
            CURLOPT_VERBOSE => true,
            CURLOPT_STDERR => $curl_log

        )
    );
    curl_exec($ch);
    rewind($curl_log);
    fclose($curl_log);
    $cookie = substr(explode(" ", exec('grep Set-Cookie: error-curl.txt '))[2], 0, -1);
    unlink ('error-curl.txt');;
    curl_close($ch);
    return $cookie;
}

/**
 * @param $host
 * @param $cookie
 */
function logout($host,$cookie) {
    $ch = curl_init();
    $url = $host . "logout";
    curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_COOKIE => $cookie,
        )
    );
    echo curl_exec($ch);
    curl_close($ch);
}

//Funciones para el manejo de items de Dspace

/**
 * Funcion que inserta un archivo XML como item dspace en la coleccion definida
 * @param $host
 * @param $cookie
 * @param $collection
 * @param $file
 */
function insertItem($host, $cookie, $collection, $file){
    //se carga el xml a insertar
    $xml = simplexml_load_file($file);
    //se busca si es que existe el item a insertar
    $identifier = $xml->metadata[0]->value->__toString();
    $search = findItem($host,$cookie,$identifier);
    //parametros para curl
    $url = $host . "collections/$collection/items";
    $fh = fopen($file, 'r');
    $size = filesize($file);
    $xml_data = fread($fh, $size);
    //si el xml tiene un tamaño mayor a 1000, se envia por partes
    if ($size > 1000) {
        //se ven todos los hijos
        $len = $xml->count();
        $itemStr = '<?xml version="1.0" encoding="UTF-8"?><item>';
        //se agregan elementos fundamentales del xml
        for ($i = 0; $i < 9; $i++) {
            $toAdd = $xml->metadata[$i]->asXML();
            $itemStr = $itemStr . $toAdd ;
            //en caso de que exista el item, se van actualizando estos metadatos
            if ($search[0] and $i != 8) {
                updateItem($host, $cookie, $search[1], $toAdd, 0);
            }
        }
        //si es que existe el item, se actualizan los metadatos faltantos
        if ($search[0] and $len >= 9){
            $multipleMetadata = $xml->metadata[9]->asXML();
            for ($i = 10; $i < $len; $i++) {
                if ($xml->metadata[$i]->key->asXML() != $xml->metadata[$i - 1]->key->asXML()){

                    updateItem($host, $cookie, $search[1], $multipleMetadata, 0);
                    $multipleMetadata = $xml->metadata[$i]->asXML();
                } else {
                    $multipleMetadata = $multipleMetadata . $xml->metadata[$i]->asXML();
                }
            }
            if (strlen($multipleMetadata) != 0){
                updateItem($host, $cookie, $search[1], $xml->metadata[$len - 1]->asXML(), 0);
            }

            print "Item actualizado \n";
            return;
        }
        //si no existe el item, se envian los metadatos fundamentales
        $itemStr = $itemStr . '</item>';
        $ch = curl_init();
        curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_COOKIE => $cookie,
                CURLOPT_POSTFIELDS => $itemStr,
                CURLOPT_FAILONERROR => true,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array(
                    "Expect: ",
                    "Accept: application/xml",
                    "Content-Type: application/xml",

                )
            )
        );
        if (!$result = curl_exec($ch)){
            print "Error al crear el item " . $identifier ."\n";
            curl_close($ch);
        } else {
            print "Item creado" . "\n";
            curl_close($ch);
            //si se enviaron los primeros datos correctamente, se comienza a actualizar el item
            //con los metadatos faltantes
            $search = findItem($host, $cookie, $identifier);
            if ($search[0] and $len >= 9) {
                $multipleMetadata = $xml->metadata[9]->asXML();
                for ($i = 10; $i < $len; $i++) {
                    if ($xml->metadata[$i]->key->asXML() != $xml->metadata[$i - 1]->key->asXML()) {

                        updateItem($host, $cookie, $search[1], $multipleMetadata, 0);
                        $multipleMetadata = $xml->metadata[$i]->asXML();
                    } else {
                        $multipleMetadata = $multipleMetadata . $xml->metadata[$i]->asXML();
                    }
                }
                if (strlen($multipleMetadata) != 0) {
                    updateItem($host, $cookie, $search[1], $xml->metadata[$len - 1]->asXML(), 0);
                }

            }
        }
    } else {
        //si el tamaño es menor a 1000, se envia en un paquete
        if ($search[0]) {
            updateItem($host, $cookie, $search[1], $file, 1);
            print "Item actualizado \n";
            return;
        }
        $ch = curl_init();
        curl_setopt_array($ch, array(
                CURLOPT_URL => $url,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_COOKIE => $cookie,
                CURLOPT_POSTFIELDS => $xml_data,
                CURLOPT_FAILONERROR => true,
                CURLOPT_RETURNTRANSFER => 1,
                CURLOPT_HTTPHEADER => array(
                    "Expect: ",
                    "Accept: application/xml",
                    "Content-Type: application/xml",

                )
            )
        );
        if (!$result = curl_exec($ch)){
            print "Error al crear el item " . $identifier ."\n";
            curl_close($ch);
        } else {
            print "Item creado" . "\n";
            curl_close($ch);
        }
    }
}

/**
 * Funcion que elimina un item
 * @param $host
 * @param $cookie
 * @param $itemId
 * @return int
 */
function deleteItem($host, $cookie, $itemId) {
    $url = $host . "items/" . $itemId;
    $ch = curl_init();
    curl_setopt_array($ch, array(
        CURLOPT_URL => $url,
        CURLOPT_CUSTOMREQUEST => 'DELETE',
        CURLOPT_COOKIE => $cookie,
        CURLOPT_FAILONERROR => true
        )
    );
    if( !$result = curl_exec($ch)) {
        print "No existe el item \n";
        curl_close($ch);
        return 0;
    } else {
        print "Item $itemId eliminado \n";
        curl_close($ch);
        return 1;
    }


}

/**
 * Funcion que actualiza un item
 * @param $host
 * @param $cookie
 * @param $itemId
 * @param $file
 * @param $isFile
 */
function updateItem($host, $cookie, $itemId, $file, $isFile) {
    if ($isFile == 1){
        $xml = simplexml_load_file($file);
    } else {
        $itemStr = '<?xml version="1.0" encoding="UTF-8"?><item>';
        $itemStr = $itemStr . $file . "</item>";
        $xml = simplexml_load_string($itemStr);
    }
    $xmlStr = <<<XML
    <metadataEntries>
    </metadataEntries>
XML;
    $metadataEntries = new SimpleXMLElement($xmlStr);
    foreach ($xml->children() as $metadata ){
        $meta = $metadataEntries->addChild("metadataentry");
        $meta->addChild("key",$metadata->key->__toString());
        $meta->addChild("value",$metadata->value->__toString());
    }
    $url = $host . "items/" . $itemId . "/metadata";
    $ch = curl_init();
    curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => 'PUT',
            CURLOPT_COOKIE => $cookie,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS => $metadataEntries->asXML(),
            CURLOPT_HTTPHEADER => array(
                "Expect: ",
                "Accept: application/xml",
                "Content-Type: application/xml",
            )
        )
    );
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Funcion que encuentra si existe un item
 * @param $host
 * @param $cookie
 * @param $identifier
 * @return array
 */
function findItem($host,$cookie,$identifier) {
  $url = $host . "items/find-by-metadata-field";
  $xmlStr = <<<XML
<metadataentry>
<key>ntg.identifier</key>
<value>$identifier</value>
</metadataentry>
XML;
    $item = new SimpleXMLElement($xmlStr);

    $ch = curl_init();
    curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_COOKIE => $cookie,
            CURLOPT_POSTFIELDS => $item->asXML(),
            CURLOPT_HTTPHEADER => array(
                "Accept: application/xml",
                "Content-Type: application/xml",
            )

        )
    );
    $exec = curl_exec($ch);
    $response = new SimpleXMLElement($exec);
    print ($response->asXML());
    if (!(bool) $response->item[0]){
        curl_close($ch);
        return array(0 , "");
    } else {
        curl_close($ch);
        return array(1,$response->item->UUID->__toString());
    }
}

/**
 * Funcion que elimna los metadatos de un item
 * @param $host
 * @param $cookie
 * @param $identifier
 */
function clearMetadata($host,$cookie,$identifier){
    $url = $host . 'items/' . $identifier . '/metadata';
    $ch = curl_init();
    curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'DELETE',
            CURLOPT_VERBOSE => true,
            CURLOPT_COOKIE => $cookie,
        )
    );
    $exec = curl_exec($ch);
    print $exec;
    return;
}
