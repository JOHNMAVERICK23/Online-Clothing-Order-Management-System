<?php
/**
 * WebSocket Server para sa Cafeteria Ordering System
 * Run with: php socket-server.php
 * 
 * Ginagamit: php-socket (built-in sa PHP)
 * Walang external dependencies!
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Server configuration
$host = '0.0.0.0';  // Listen on all interfaces (accessible from any IP)
$port = 4005;
$maxClients = 100;
$clients = array();

echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     WebSocket Server - Cafeteria Ordering System              ║\n";
echo "║     ================================================            ║\n";
echo "║     Status: Starting...                                       ║\n";
echo "║     Host: $host                                      ║\n";
echo "║     Port: $port                                              ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Create server socket
$socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
if (!$socket) {
    die("Failed to create socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Set socket options
socket_set_option($socket, SOL_SOCKET, SO_REUSEADDR, 1);

// Bind socket
if (!socket_bind($socket, $host, $port)) {
    die("Failed to bind socket: " . socket_strerror(socket_last_error()) . "\n");
}

// Listen for connections
if (!socket_listen($socket, 5)) {
    die("Failed to listen on socket: " . socket_strerror(socket_last_error()) . "\n");
}

echo "[" . date('H:i:s') . "] Server started successfully!\n";
echo "[" . date('H:i:s') . "] Listening on port $port\n";
echo "[" . date('H:i:s') . "] Waiting for connections...\n\n";

// Main server loop
while (true) {
    // Get list of sockets. Filter out any closed/invalid client sockets
    $read = array($socket);
    foreach ($clients as $key => $c) {
        // socket_getpeername returns false for invalid/closed sockets
        if (@socket_getpeername($c, $tmp_ip, $tmp_port)) {
            $read[] = $c;
        } else {
            // remove invalid client socket from list
            unset($clients[$key]);
        }
    }
    // Reindex clients array to avoid holes
    $clients = array_values($clients);
    $write = NULL;
    $except = NULL;
    
    // Socket select
    $num = @socket_select($read, $write, $except, 5);
    
    if ($num === false) {
        echo "Socket select error\n";
        break;
    }
    
    // Check if new connection
    if (in_array($socket, $read)) {
        $newClient = socket_accept($socket);
        if ($newClient) {
            // Get client info
            socket_getpeername($newClient, $ip, $port_info);
            
            // Add to clients array
            $clients[] = $newClient;
            $clientId = count($clients) - 1;
            
            // Perform WebSocket handshake
            if (performWebSocketHandshake($newClient)) {
                echo "[" . date('H:i:s') . "] ✓ Client connected from $ip:$port_info (ID: $clientId)\n";
                echo "[" . date('H:i:s') . "] Total clients: " . count($clients) . "\n";
            } else {
                socket_close($newClient);
                unset($clients[$clientId]);
                echo "[" . date('H:i:s') . "] ✗ Handshake failed from $ip\n";
            }
        }
        
        // Remove the main socket from read array
        $key = array_search($socket, $read);
        unset($read[$key]);
    }
    
    // Check for data from clients
    foreach ($read as $client) {
        $data = @socket_read($client, 1024);
        
        if ($data === false) {
            // Connection closed
            $key = array_search($client, $clients);
            unset($clients[$key]);
            socket_close($client);
            echo "[" . date('H:i:s') . "] Client disconnected. Total: " . count($clients) . "\n";
        } else if (!empty($data)) {
            // Try to decode as WebSocket frame first
            $message = decodeWebSocketData($data);
            
            // If not a WebSocket frame, try JSON directly (for API connections)
            if (!$message) {
                $decoded = json_decode($data, true);
                if (is_array($decoded) && isset($decoded['type'])) {
                    $message = $data; // Keep as JSON string
                }
            }
            
            if ($message) {
                echo "[" . date('H:i:s') . "] Message: $message\n";
                
                // Broadcast to all connected clients
                broadcastMessage($clients, $message);
            }
        }
    }
}

socket_close($socket);

/**
 * Perform WebSocket handshake
 */
function performWebSocketHandshake(&$socket) {
    $data = socket_read($socket, 1024);
    
    if (!preg_match('/Sec-WebSocket-Key: (.*)\r\n/', $data, $matches)) {
        return false;
    }
    
    $key = trim($matches[1]);
    $hash = sha1($key . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11');
    $accept = base64_encode(pack('H*', $hash));
    
    $response = "HTTP/1.1 101 Switching Protocols\r\n";
    $response .= "Upgrade: websocket\r\n";
    $response .= "Connection: Upgrade\r\n";
    $response .= "Sec-WebSocket-Accept: $accept\r\n";
    $response .= "\r\n";
    
    socket_write($socket, $response, strlen($response));
    
    return true;
}

/**
 * Decode WebSocket frame
 */
function decodeWebSocketData($data) {
    if (strlen($data) < 2) return null;
    
    $byte1 = ord($data[0]);
    $byte2 = ord($data[1]);
    
    $masked = ($byte2 & 128) >> 7;
    $payloadLength = $byte2 & 127;
    
    $offset = 2;
    
    if ($payloadLength == 126) {
        if (strlen($data) < 4) return null;
        $payloadLength = unpack('n', substr($data, $offset, 2))[1];
        $offset += 2;
    } else if ($payloadLength == 127) {
        if (strlen($data) < 10) return null;
        $temp = unpack('N*', substr($data, $offset, 8));
        $payloadLength = $temp[2];
        $offset += 8;
    }
    
    if ($masked) {
        if (strlen($data) < $offset + 4) return null;
        $mask = substr($data, $offset, 4);
        $offset += 4;
        
        $payload = substr($data, $offset, $payloadLength);
        $decoded = '';
        
        for ($i = 0; $i < strlen($payload); $i++) {
            $decoded .= chr(ord($payload[$i]) ^ ord($mask[$i % 4]));
        }
        
        return $decoded;
    }
    
    return substr($data, $offset, $payloadLength);
}

/**
 * Encode WebSocket frame
 */
function encodeWebSocketData($data) {
    $frame = chr(0x81);
    $dataLength = strlen($data);
    
    if ($dataLength <= 125) {
        $frame .= chr($dataLength);
    } else if ($dataLength <= 65535) {
        $frame .= chr(126);
        $frame .= pack('n', $dataLength);
    } else {
        $frame .= chr(127);
        $frame .= pack('N', $dataLength >> 32);
        $frame .= pack('N', $dataLength & 0xFFFFFFFF);
    }
    
    $frame .= $data;
    return $frame;
}

/**
 * Broadcast message to all clients
 */
function broadcastMessage(&$clients, $message) {
    $frame = encodeWebSocketData($message);
    $count = 0;
    
    foreach ($clients as $client) {
        if (@socket_write($client, $frame, strlen($frame))) {
            $count++;
        }
    }
    
    echo "[" . date('H:i:s') . "] Broadcasted to $count clients\n";
}

?>