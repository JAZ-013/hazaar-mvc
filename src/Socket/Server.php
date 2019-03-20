<?php

/**
 * @package     Socket
 */
namespace Hazaar\Socket;

/**
 * The socket server class
 *
 * Creates a socket server that listens on an address and port for incoming connections.
 *
 * @since 2.0.0
 *       
 */
abstract class Server {

    protected $maxBufferSize;

    protected $master;

    protected $sockets = array();

    protected $running = false;

    function __construct($addr, $port, $bufferLength = 2048) {

        $this->maxBufferSize = $bufferLength;
        
        $this->master = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        
        if ($this->master == false)
            throw new Exception\CreateFailed($this->master);
        
        if (! socket_set_option($this->master, SOL_SOCKET, SO_REUSEADDR, 1))
            throw new Exception\OptionFailed($this->master);
        
        if (! socket_bind($this->master, $addr, $port))
            throw new Exception\BindFailed($this->master);
        
        if (! socket_listen($this->master, 20))
            throw new Exception\ListenFailed($this->master);
        
        $this->sockets[] = $this->master;
    
    }

    /**
     * Incomming connection request handler
     *
     * Event called when a connection request is received. Should return true or false indicating if the connection should be accepted.
     */
    abstract protected function connecting($remote_addr, $remote_port, $local_addr, $local_port);

    /**
     * Incomming connection handlers
     *
     * Event called when a connection is established and data can begin to be sent/received.
     */
    abstract protected function connected($client);

    /**
     * Incoming data handler
     *
     * Called immediately when data is recieved.
     */
    abstract protected function process($message);

    /**
     * Close connection handler
     *
     * Called after the connection is closed.
     */
    abstract protected function closed($client);

    /**
     * Server main loop
     */
    public function run($timeout = null) {

        $this->running = true;
        
        while ($this->running === true) {
            
            if (empty($this->sockets)) {
                
                $this->sockets[] = $this->master;
            }
            
            $read = $this->sockets;
            
            $write = $except = null;
            
            @socket_select($read, $write, $except, null);
            
            foreach ($read as $socket) {
                
                if ($socket == $this->master) {
                    
                    $client = socket_accept($socket);
                    
                    if ($client < 0) {
                        $this->stderr("Failed: socket_accept()");
                        continue;
                    } else {
                        
                        $remote_addr = null;
                        
                        $remote_port = null;
                        
                        socket_getpeername($client, $remote_addr, $remote_port);
                        
                        $local_addr = null;
                        
                        $local_port = null;
                        
                        socket_getsockname($client, $local_addr, $local_port);
                        
                        if ($this->connecting($remote_addr, $remote_port, $local_addr, $local_port) !== false) {
                            
                            echo "Accepted\n";
                            
                            $this->sockets[] = $client;
                            
                            $this->connected($client);
                        }
                    }
                } else {
                    
                    $buf = '';
                    
                    $numBytes = socket_recv($socket, $buf, $this->maxBufferSize, 0);
                    
                    if ($numBytes > 0) {
                        
                        $this->process($buf);
                    } else {
                        
                        socket_close($socket);
                        
                        $this->closed($client);
                    }
                }
            }
        }
    
    }

}

