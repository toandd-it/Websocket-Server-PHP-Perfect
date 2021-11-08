<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

ob_start();
session_start();

$dir_root = $_SERVER['SCRIPT_FILENAME'];
$fileNameExt = basename($dir_root);
$dir_root = explode('/'.$fileNameExt, $dir_root)[0];
$_SERVER['DOCUMENT_ROOT'] = $dir_root;
$session_id = session_id();

$host = '0.0.0.0';
$port = 8090;

include $dir_root.'/class.websocket.php';
$ws = new wsAction();
$transport = 'tlsv1.2';
$ssl = array(
    'ssl' => array(
        'local_cert' => '/etc/letsencrypt/live/zetadmin.com/cert.pem',
        'local_pk' => '/etc/letsencrypt/live/zetadmin.com/privkey.pem',
        'disable_compression' => true,
        'verify_peer' => false,
        'ssltransport' => $transport, // Transport Methods such as 'tlsv1.1', tlsv1.2'
    )
);
$ssl_context = stream_context_create($ssl);
$server = stream_socket_server($transport . '://' . $host . ':' . $port, $errno, $errstr, STREAM_SERVER_BIND|STREAM_SERVER_LISTEN, $ssl_context);
if (!$server) 
{  
    die("$errstr ($errno)"); 
}
$clients = array($server);
$write  = NULL;
$except = NULL;

$clientData = array();
$roomData = array();
$receiverData = array();

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
        $ip = stream_socket_get_name( $client, true );
        $uip = str_replace(array('.', ':'), array('', '_'), $ip);
        
        if(!isset($clientData[$uip]['uid']) || isset($clientData[$uip]['uid']) != '')
        {
            $clientData[$uip]['uid'] = '';
        }
        
        //Online
        stream_set_blocking($client, true);
        $headers = fread($client, 1500);
        $ws->handshake($client, $headers, $host, $port, 'wss');
        
        stream_set_blocking($client, false);
        $found_socket = array_search($server, $changed);
        unset($changed[$found_socket]);
        if(!empty($clientData[$uip]))
        {
            //unset($clientData[$uip]);
        }
    }
    
    foreach ($changed as $changed_socket) 
    {
        $ip = stream_socket_get_name( $changed_socket, true );
        $uips = str_replace(array('.', ':'), array('', '_'), $ip);
        $buffer = stream_get_contents($changed_socket);
        
        $server_idle=time();
        
        if ($buffer == false) 
        {
            //Offiline
            if(isset($clientData[$uips]['uid']) && !empty($clientData[$uips]['uid']))
            {
                $data_offline = array('type' => 'status', 'action' => 'offline', 'msg' => '', 'uid' => $clientData[$uips]['uid'], 'sub_id' => $uips, 'uData' => [], 'time' => time());
                $ws->send_message($clients, $data_offline, $changed_socket);
            }

            fclose($changed_socket);
            $found_socket = array_search($changed_socket, $clients);
            unset($clients[$found_socket]);
            if(!empty($clientData[$uips]))
            {
                unset($clientData[$uips]);
            }
        }
        else
        {
            $unmasked = $ws->unmask($buffer);
            if (!empty($unmasked)) 
            { 
                $msg_check = json_decode($unmasked, true);
                if(isset($clientData[$uips]))
                {
                    if(isset($msg_check['uid']) && !empty($msg_check['uid']))
                    {
                        $clientData[$uips]['uid'] = $msg_check['uid'];
                    }
                    if(isset($msg_check['rid']) && !empty($msg_check['uid']))
                    {
                        $roomData[$msg_check['rid']][] = $msg_check['uid'];
                    }
                }
                $ws->send_message($clients, $msg_check, $changed_socket);
            }
        }
    }
    
    if (time()-$server_idle>3) 
    {
        usleep(5);
    }
}
fclose($server);
?>
