<?php
class wsAction
{
    public function isJson($string) {
       json_decode($string);
       return json_last_error() === JSON_ERROR_NONE;
    }
    
    public function unmask($text) 
    {
        $length = ord($text[1]) & 127;
        if($length == 126) {    $masks = substr($text, 4, 4);    $data = substr($text, 8); }
        elseif($length == 127) {    $masks = substr($text, 10, 4); $data = substr($text, 14); }
        else { $masks = substr($text, 2, 4); $data = substr($text, 6); }
        $text = "";
        for ($i = 0; $i < strlen($data); ++$i) { $text .= $data[$i] ^ $masks[$i % 4];    }
        return $text;
    }
    
    public function mask($text) 
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

    public function handshake($client, $rcvd, $host, $port, $protocol='ws')
    {
        $headers = array();
        $lines = preg_split("/\r\n/", $rcvd);
        foreach($lines as $line)
        {
            $line = rtrim($line);
            if(preg_match('/\A(\S+): (.*)\z/', $line, $matches)){
                $headers[$matches[1]] = $matches[2];
            }
        }
        $secKey = $headers['Sec-WebSocket-Key'];
        $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
        //hand shaking header
        $upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "WebSocket-Origin: $host\r\n" .
        "WebSocket-Location: $protocol://$host:$port\r\n".
        "Sec-WebSocket-Version: 13\r\n" .
        "Sec-WebSocket-Accept:$secAccept\r\n\r\n";
        fwrite($client, $upgrade);
    }
    
    function send_message($clients, $content, $channel=NULL)
    {
        global $clientData;
        global $roomData;
        foreach($clients as $changed_socket)
        {
			if(!empty($changed_socket))
			{
				//$ip = stream_socket_get_name($changed_socket, true);
				if($channel == $changed_socket)
				{
					continue;
				}
				if(!empty($content) && !empty($content['type']))
				{
					@fwrite($changed_socket, $this->mask(json_encode($content)));
				}
			}
        }
    }
}
?>
