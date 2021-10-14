<h1>Websocket Server PHP</h1>
Websocket server using PHP. Send and Receive data in JSON format, remove error "Could not decode a text frame as UTF-8". Support SSL (wss) and No-SSL (ws)

**Run on Linux:**
  - _**OpenLiteSpeed:**_ /usr/local/lsws/lsphp73/bin/php /your-dir/wss.php &
  - _**Apache:**_ /usr/bin/php /your-dir/wss.php &

**Run on Windows:**
  - _**Xampp:**_ C:\xampp\php \your-dir\ws.php

**Using on Javascript:**

```
<script type="text/javascript">
  socketNotice = new WebSocket('wss://your-domain:8089');
  socketNotice.onopen = function(response)
  {
    console.log(response);
  }
  socketNotice.onmessage = function(response)
  { 
    console.log(response);
  }
  socketNotice.onclose = function(response)
  { 
    console.log(response);
  }
  socketNotice.send(JSON.stringify({type: "status", action: "online", rid: '', receiver: [], msg: "", uid: "_my_id", uData: []}));
</script>
