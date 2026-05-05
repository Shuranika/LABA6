<?php
$host = '0.0.0.0';
$port = 8080;
$null = NULL;

$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);
socket_bind($socket, 0, $port);
socket_listen($socket);

$clients = array($socket);

echo "Server started on port $port\n";

while (true) {
    $changed = $clients;
    if (@socket_select($changed, $null, $null, 1) < 1) {
        continue;
    }

    if (in_array($socket, $changed)) {
        $socket_new = socket_accept($socket);
        $clients[] = $socket_new;

        $header = @socket_read($socket_new, 1024);
        perform_handshake($header, $socket_new, $host, $port);

        $found_socket = array_search($socket, $changed);
        unset($changed[$found_socket]);
    }

    foreach ($changed as $changed_socket) {
        $buf = '';
        $bytes = @socket_recv($changed_socket, $buf, 1024, 0);

        if ($bytes === 0 || $bytes === false) {
            $found_socket = array_search($changed_socket, $clients);
            if ($found_socket !== false) {
                unset($clients[$found_socket]);
            }
            @socket_close($changed_socket);
            echo "Клиент отключился. Осталось клиентов: " . (count($clients) - 1) . "\n";
            continue;
        }

        $received_text = unmask($buf);
        $data = json_decode($received_text, true);

        if (isset($data['action']) && $data['action'] === 'refresh') {
            echo "Получен сигнал refresh от " . ($data['senderId'] ?? 'unknown') . "\n";

            $reply = json_encode([
                'action' => 'refresh',
                'senderId' => $data['senderId'] ?? ''
            ]);

            $response = mask($reply);
            send_message($response);
        }
    }
}

function send_message($msg) {
    global $clients, $socket;
    foreach($clients as $changed_socket) {
        if ($changed_socket !== $socket) {
            @socket_write($changed_socket, $msg, strlen($msg));
        }
    }
    return true;
}

function unmask($text) {
    if (strlen($text) < 2) return '';
    $length = ord($text[1]) & 127;
    if($length == 126) { $masks = substr($text, 4, 4); $data = substr($text, 8); }
    elseif($length == 127) { $masks = substr($text, 10, 4); $data = substr($text, 14); }
    else { $masks = @substr($text, 2, 4); $data = @substr($text, 6); }
    $text = "";
    for ($i = 0; $i < strlen($data); ++$i) { $text .= $data[$i] ^ $masks[$i%4]; }
    return $text;
}

function mask($text) {
    $b1 = 0x80 | (0x1 & 0x0f);
    $length = strlen($text);
    if($length <= 125) $header = pack('CC', $b1, $length);
    elseif($length > 125 && $length < 65536) $header = pack('CCn', $b1, 126, $length);
    elseif($length >= 65536) $header = pack('CCNN', $b1, 127, $length);
    return $header.$text;
}

function perform_handshake($receved_header, $client_conn, $host, $port) {
    $headers = array();
    $lines = explode("\r\n", $receved_header);
    foreach($lines as $line) {
        $line = trim($line);
        if (strpos($line, ': ') !== false) {
            list($key, $value) = explode(': ', $line, 2);
            $headers[$key] = $value;
        }
    }
    if (!isset($headers['Sec-WebSocket-Key'])) return false;

    $secKey = $headers['Sec-WebSocket-Key'];
    $secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
    $buffer  = "HTTP/1.1 101 Switching Protocols\r\n" .
        "Upgrade: websocket\r\n" .
        "Connection: Upgrade\r\n" .
        "Sec-WebSocket-Accept: $secAccept\r\n\r\n";
    @socket_write($client_conn, $buffer, strlen($buffer));
    echo "Новый клиент успешно подключен!\n";
    return true;
}