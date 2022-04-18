<?php
error_reporting(E_ALL);
ini_set('display_errors', 'Off');

ob_start();

$dir_root = $_SERVER['SCRIPT_FILENAME'];
$fileNameExt = basename($dir_root);
$dir_root = explode('/'.$fileNameExt, $dir_root)[0];
$_SERVER['DOCUMENT_ROOT'] = $dir_root;
$session_id = session_id();

$host = 'shareoffice.vn';
$port = 8090;

include $dir_root.'/class.websocket.php';
$ws = new wsAction();
$transport = 'tlsv1.2';
$context = stream_context_create();

stream_context_set_option($context, 'ssl', 'local_cert', '/usr/local/lsws/conf/vhosts/shareoffice.vn/ssl/shareoffice_vn_cert.pem');
stream_context_set_option($context, 'ssl', 'local_pk', '/usr/local/lsws/conf/vhosts/shareoffice.vn/ssl/Private.key');
stream_context_set_option($context, 'ssl', 'allow_self_signed', true);
stream_context_set_option($context, 'ssl', 'verify_peer', false);

$server = stream_socket_server($transport.'://'.$host.':'.$port, $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $context);
if (!$server) 
{  
    die("$errstr ($errno)"); 
}
$clients = array($server);
$write  = NULL;
$except = NULL;

$clientData = [];
$roomData = [];
$receiverData = [];

$server_idle = time();
while (true) 
{
    $changed = $clients;
    stream_select($changed, $write, $except, 10);
    if (in_array($server, $changed)) 
    {
        $client = stream_socket_accept($server);
        if (!$client){ continue; }
        
        $clients[] = $client;
        
        stream_set_blocking($client, true);
        $headers = fread($client, 1500);
        $ws->handshake($client, $headers, $host, $port, 'wss');
        
        stream_set_blocking($client, false);
        $found_socket = array_search($server, $changed);
        unset($changed[$found_socket]);
    }
    
    foreach ($changed as $changed_socket) 
    {
		if(!empty($changed_socket))
		{
			$ip = stream_socket_get_name( $changed_socket, true );
		}

        $buffer = stream_get_contents($changed_socket);
        
        if ($buffer == false) 
        {
            //Offiline
            if(!empty($clientData[$ip]['uid']))
            {
                $data_offline = ['type' => 'status', 'action' => 'offline', 'msg' => '', 'uid' => $clientData[$ip]['uid'], 'rid' => '', 'sub_id' => $ip, 'uData' => [], 'time' => time()];
                if(!empty($clientData[$ip]['rid']))
                {
                    $data_offline['rid'] = $clientData[$ip]['rid'];
                    if(!empty($roomData[$clientData[$ip]['rid']]))
                    {
                        // send msg offline to all client on a room ID
                        $ws->send_message($roomData[$clientData[$ip]['rid']], $data_offline, $changed_socket);
                    }
                }
                else
                {
                    // send msg offline to all client on server
                    $ws->send_message($clients, $data_offline, $changed_socket);
                }
            }

            fclose($changed_socket);
            $found_socket = array_search($changed_socket, $clients);
            unset($clients[$found_socket]);
            if(!empty($clientData[$ip]))
            {
                unset($clientData[$ip]);
            }
        }
        else
        {
            $unmasked = $ws->unmask($buffer);
            if (!empty($unmasked)) 
            { 
                $msg_check = json_decode($unmasked, true);
				
				if(!empty($msg_check['uid']) && !empty($ip))
                {
                    $clientData[$ip]['uid'] = $msg_check['uid'];
                }

                if(!empty($msg_check['rid']) && !empty($ip))
                {
                    $clientData[$ip]['rid'] = $msg_check['rid'];
                }

                if(!empty($msg_check['rid']) && !empty($client))
                {
                    $roomData[$msg_check['rid']][] = $client;
                }
                
				if(!empty($msg_check['rid']) && !empty($roomData[$msg_check['rid']]))
				{
                    // send msg to all client on a room ID
					$ws->send_message($roomData[$msg_check['rid']], $msg_check, $changed_socket);
				}
				else
				{
                    // send msg to all client on server
					$ws->send_message($clients, $msg_check, $changed_socket);
				}
            }
        }
    }
}
fclose($server);
?>