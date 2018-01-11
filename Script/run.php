<?php
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        //Comienza la sincronizacion.
        case "start-oai-sync":
            //Bloquear para evitar ejecucion en paralelo
            $file = fopen("lock","w+");
            if (flock($file,LOCK_EX)){

                //en caso de reset, se elimina tanto el log como los xml
                if($_GET['reset'] == "on"){
                    exec("rm -R xml");
                    exec("rm -R xmlToAdd.txt");
                    exec("rm -R log.txt");
                }
                //Se ejecuta la creacion de archivos xml a agregar o actualizar.
                //$tiempo_inicio = microtime(true);
                exec("php createXml.php xmlDict.json pnid.json", $res) or die("Error al crear archivos xml\n");
                //print_r($res);
                //$tiempo_fin = microtime(true);
                //echo "Tiempo de creacion archivos xml: " . ($tiempo_fin - $tiempo_inicio). "\n";

                //Fecha y hora en caso de creacion correcta
                $createDate = date('m/d/Y h:i:s a', time());
                echo "Creacion y actualizacion de xml finalizada.\n";

                //Se ejecuta la insercion de archivos xml a items dspace.
                //$tiempo_inicio = microtime(true);
                exec("php addItems.php itemDict.json", $res) or die("Error al ingresar Items\n");
                //print_r($res);
                //$tiempo_fin = microtime(true);
                //echo "Tiempo de insercion items: " . ($tiempo_fin - $tiempo_inicio) . "\n";

                //Fecha y hora en caso de insercion correcta
                $insertDate = date('m/d/Y h:i:s a', time());
                echo "Insercion de items finalizada.\n";

                //Si resulta correcto, se guardan los registros en el log.
                if($_GET['debug'] == "on"){
                    $log = fopen("log.txt", "a+");
                    fwrite($log, "Creacion y actualizacion de xml: " . $createDate . "\n");
                    fwrite($log, "Insercion de items: " . $insertDate . "\n");
                    fclose($log);
                }


                //Se llama al import de oai
                //$tiempo_inicio = microtime(true);
                $ch = curl_init();
                curl_setopt_array($ch, array(
                        CURLOPT_URL => "https://kila-testing.newtenberg.com/mod/oai/manager.php",
                        CURLOPT_POST => true,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_VERBOSE => true,
                    )
                );
                curl_exec($ch);
                echo "Actualizacion Oai completada \n";
                //print_r(curl_exec($ch));
                curl_close($ch);
                //$tiempo_fin = microtime(true);
                //echo "Tiempo de importe OAI: " . ($tiempo_fin - $tiempo_inicio) . "\n";
                flock($file,LOCK_UN);
            } else {
                echo "Error: ya esta en ejecucion\n.";
            }
            fclose($file);
            break;

        case "status":

            //Se busca la ejecucion del proceso createXml.
            exec("ps -u webtest aux | grep createXml | grep php", $process);

            //Si se encuentra el proceso, se imprime en pantalla.
            if (count($process) > 1) {
                echo "Creando archivos xml...\n";
            } else {
                //En caso de no encontrarse se verifica si se estan agregando items
                exec("ps -u webtest aux | grep addItems | grep php", $process);

                //Si se encuentra el proceso addItems, se imprime en pantalla.
                if (count($process) > 2) {
                    echo "Enviando items a Dspace...\n";
                } else {
                    //En caso de que no se esten ejecutando ninguno de los dos procesos
                    //Se imprime en pantalla los dos ultimos registros del log.
                    $file = file("log.txt");
                    for ($i = max(0, count($file) - 2); $i < count($file); $i++) {
                        print($file[$i]);
                    }
                }
            }
            break;
        //debug: fuerza la actualizacion todos los items ademas de resetear el log
        case "reset":
            exec("rm -R xml");
            exec("rm -R xmlToAdd.txt");
            exec("rm -R log.txt");
            break;
    }
} else {
    //debug: permite la creacion e insercion de los items desde la terminal.
    /*echo exec("php createXml.php xmlDict.json pnid.json", $create) or die("Error al crear archivos xml\n");
    echo exec("php addItems.php itemDict.json pnid.json ") or die("Error al ingresar Items\n");
    $ch = curl_init();
    curl_setopt_array($ch, array(
            CURLOPT_URL => "https://kila-testing.newtenberg.com/mod/oai/manager.php",
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_VERBOSE => true,
        )
    );
    echo curl_exec($ch);
    curl_close($ch);*/
}



