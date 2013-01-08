<?php
/**
   * @package twitqr
   * @version   1.1
   * @author_original    Minipunk
   * @author_this_version Pabloguti
   * @copyright (C) 2013 Minipunk
   *
   * @license        Apache License Version 2.0, see LICENSE.md
   * 3/JAN/2012
   *
 */
$file = "respondido.txt"; // Almacena el ID del útlimo tweet respondido

// compruebo que existe el archivo txt y si no existe lo crea
if(!file_exists($file)){
    $since_id = 0;
} else {
    $handle2 = fopen($file, 'r');
    
    if(filesize($file)==0){
        $since_id = 0;
    } else {
        $since_id = fread($handle2, filesize($file));
    }
    fclose($handle2);    
}
require ('src/tmhOAuth.php'); // utilizo la biblioteca de https://github.com/themattharris
require ('src/tmhUtilities.php'); // utilizo la biblioteca de https://github.com/themattharris

$tmhOAuth = new tmhOAuth(array(
  'consumer_key'    => 'KEY DE APP',
  'consumer_secret' => 'SECRET DE APP',
  'user_token'      => 'TOKEN DE USUARIO',
  'user_secret'     => 'SECRET DE USUARIO',
));

//En caso de que since_id ya exista (>0) le pido a twitter las menciones desde el último
if ($since_id != 0) {
	$params = array('since_id' => $since_id);

	$code = $tmhOAuth -> request('GET', $tmhOAuth -> url('1.1/statuses/mentions_timeline.json'), $params);
	// recupero menciones de twitter
} else {
	$code = $tmhOAuth -> request('GET', $tmhOAuth -> url('1.1/statuses/mentions_timeline.json'));
}
$mentions = json_decode($tmhOAuth -> response['response']);
// Decodifico el archivo JSON

$last_id = 0;
$last_user = '';
if (!isset($mentions -> errors)) {//Si twitter NO devuelve un error, proceso
	foreach ($mentions as $mention)//Recorro todas las menciones
	{
		$tweetqr = procesa_text($mention -> text);
		//Proceso el texto
		
		if ($tweetqr != null) {
			$filename = get_image_qr($tweetqr, $mention -> id);
			$handle = fopen($filename, "rb");
			$image = fread($handle, filesize($filename));
			fclose($handle);
			$user = $mention -> user -> screen_name;
			$status = "@$user aquí tienes tu QR ";
			$filename = $mention -> id . ".jpg";
			$code = $tmhOAuth -> request('POST', 'https://api.twitter.com/1.1/statuses/update_with_media.json', array('media[]' => "{$image};type=image/jpeg;filename={$filename}", "status" => ' ' . $status), true, // uso auth
			true // multipart
			);
			if ($mention -> id > $last_id) {//Obtengo el id mayor para almacenarlo
				$last_id = $mention -> id;
			}
			unlink($filename);
			if ($last_user == $user) {
				sleep(10);
			} else {
				$last_user = $user;
			}
		}
	}

	$handle = fopen($file, 'w+');
	// abro y leo el archivo de texto con el ID del último tweet procesado
	fwrite($handle, $last_id);
	// Escribo el nuevo id
	fclose($handle);
	// cierro el archivo de texto
} else {//En caso de error, muesto el error de Twitter
	echo "Error en Twitter: " . $mentions -> errors[0] -> message;

}
function get_image_qr($data, $id) { //Obtengo la imagen de Google
	set_time_limit(120);
	$source = "https://chart.googleapis.com/chart?chs=545x545&cht=qr&chld=H&chl=" . urlencode($data); 
	//importante codificar la url 
	$dest = $id . ".jpg"; //Selecciono el destino (id + .jpg)
	$fd = fopen($source, "r") or die("<center>Unable to access source page</center>");
	$pagina = fread($fd, sizeof($source));
	copy($source, $dest); //Copy la imagen de google chart api la imagen a mi servidor
	fclose($fd);
	return $dest; //Devuelvo el nombre de la imagen
}

function procesa_text($text) {
	$array_text = explode(' ', $text);
	if ($array_text[0] == '@creaqr') {
		$cambia = array('@creaqr'); //Elimino el nombre de la mención
		$replace = array('');
		$text = str_replace($cambia, $replace, $text);
		$text = trim($text); //Elimino espacios al principio y fin
	} else {
		$text = null;
	}
	return $text;
}
?>
