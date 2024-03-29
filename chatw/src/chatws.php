<?php

$host = 'localhost'; //host
$port = '9000'; //port
$socketpath = 'chatw/src/chatws.php'; //socketpath
$magickey = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11'; //magickey
$null = NULL; //null var

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);


socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);


socket_bind($socket, 0, $port);


socket_listen($socket);


$clients = array($socket);


while (true) {
	
	$changed = $clients;

	socket_select($changed, $null, $null, 0, 10);
	

	if (in_array($socket, $changed)) {
		$socket_new = socket_accept($socket); 
		$clients[] = $socket_new; 
		
		$header = socket_read($socket_new, 1024); 
		new_client($header, $socket_new, $host, $port, $socketpath, $magickey); 
		
		socket_getpeername($socket_new, $ip); 
		$response = message_encode(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); 
		send_message($response);
		$found_socket = array_search($socket, $changed);
		unset($changed[$found_socket]);
	}
	

	foreach ($changed as $changed_socket) {	
		
		
		while(socket_recv($changed_socket, $buf, 1024, 0) >= 1)
		{
			$received_text = unmessage_encode($buf); 
			$tst_msg = json_decode($received_text); 
			$user_mode = (empty($tst_msg->mode) ? null : $tst_msg->mode); 
			$user_name = (empty($tst_msg->name) ? null : $tst_msg->name); 
			$user_message = (empty($tst_msg->message) ? null : $tst_msg->message); 
			$user_date = (empty($tst_msg->udate) ? null : $tst_msg->udate); 
			$user_color = (empty($tst_msg->color) ? null : $tst_msg->color); 
			
		
			$response_text = message_encode(json_encode(array('type'=>$user_mode, 'name'=>$user_name, 'message'=>$user_message, 'date'=>$user_date, 'color'=>$user_color)));
			send_message($response_text); 
			break 2; 
		}
		
		$buf = @socket_read($changed_socket, 1024, PHP_NORMAL_READ);
		if ($buf === false) { 
			$found_socket = array_search($changed_socket, $clients);
			socket_getpeername($changed_socket, $ip);
			unset($clients[$found_socket]);
			

			$response = message_encode(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
			send_message($response);
		}
	}
}

socket_close($socket);

function send_message($msg)
{
	global $clients;
	foreach($clients as $changed_socket)
	{
		@socket_write($changed_socket,$msg,strlen($msg));
	}
	return true;
}



function unmessage_encode($text) {
	$length = ord($text[1]) & 127;
	if($length == 126) {
		$messages = substr($text, 4, 4);
		$data = substr($text, 8);
	}
	elseif($length == 127) {
		$messages = substr($text, 10, 4);
		$data = substr($text, 14);
	}
	else {
		$messages = substr($text, 2, 4);
		$data = substr($text, 6);
	}
	$text = "";
	for ($i = 0; $i < strlen($data); ++$i) {
		$text .= $data[$i] ^ $messages[$i%4];
	}
	return $text;
}


function message_encode($text)
{
	$b1 = 0x80 | (0x1 & 0x0f);
	$length = strlen($text);
	
	if($length <= 125)
		$header = pack('CC', $b1, $length);
	elseif($length > 125 && $length < 65536)
		$header = pack('CCn', $b1, 126, $length);
	elseif($length >= 65536)
		$header = pack('CCNN', $b1, 127, $length);
	return $header.$text;
}


function new_client($receved_header,$client_conn, $host, $port, $socketpath, $magickey)
{
	$headers = array();
	$lines = preg_split("/\r\n/", $receved_header);
	foreach($lines as $line)
	{
		$line = chop($line);
		if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
		{
			$headers[$matches[1]] = $matches[2];
		}
	}

	$secKey = $headers['Sec-WebSocket-Key'];
	$secAccept = base64_encode(pack('H*', sha1($secKey . $magickey)));
	$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
	"Upgrade: websocket\r\n" .
	"Connection: Upgrade\r\n" .
	"WebSocket-Origin: $host\r\n" .
	"WebSocket-Location: ws://$host:$port/$socketpath\r\n".
	"Sec-WebSocket-Accept:$secAccept\r\n\r\n";
	socket_write($client_conn,$upgrade,strlen($upgrade));
}
