<?php
include 'class.websocket.php';
include 'config.php';
$ws = new wsAction();

$server = stream_socket_server('tcp://' . $host . ':' . $port, $errno, $errstr);
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

        if (!$client){ 
            continue; 
        }
        
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
        $ws->handshake($client, $headers, $host, $port);
        
        stream_set_blocking($client, false);
        $found_socket = array_search($server, $changed);
        unset($changed[$found_socket]);
        //unset($clientData[$uip]);
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
            if(isset($clientData[$uips]['uid']) && $clientData[$uips]['uid'] != '')
            {
                $data_offline = array('type' => 'status', 'action' => 'offline', 'msg' => '', 'uid' => $clientData[$uips]['uid'], 'sub_id' => $uips, 'uData' => '', 'time' => time());
            }
            else
            {
                $data_offline = array('type' => 'status', 'action' => 'offline', 'msg' => '', 'uid' => '', 'sub_id' => $uips, 'uData' => '', 'time' => time());
            }
            $ws->send_message($clients, $data_offline, $changed_socket);

            fclose($changed_socket);
            $found_socket = array_search($changed_socket, $clients);
            unset($clients[$found_socket]);
            unset($clientData[$uips]);
        }
        else
        {
            $unmasked = $ws->unmask($buffer);
            if ($unmasked != "") 
            { 
                $msg_check = json_decode($unmasked, true);
                if(isset($clientData[$uips]))
                {
                    if(isset($msg_check['uid']) && $msg_check['uid'] != '')
                    {
                        $clientData[$uips]['uid'] = $msg_check['uid'];
                    }
                    if(isset($msg_check['rid']) && $msg_check['rid'] != '')
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
        usleep(1000000);
    }
}
fclose($server);
?>