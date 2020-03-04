<?php

namespace Hazaar\Mail\Transport;

class Smtp extends \Hazaar\Mail\Transport {

    private $server;

    private $port;

    private $socket;

    private $read_timeout;

    public function init($config){

        if(!$config->has('server'))
            throw new \Exception('Cannot send mail.  No SMTP mail server is configured!');

        $this->server = $config->server;

        $this->port = $config->get('port', 25);

        $this->read_timeout = $config->get('timeout', 5);

    }

    private function connect(){

        if($this->socket instanceof \Hazaar\Socket)
            $this->socket->close();
        else
            $this->socket = new \Hazaar\Socket();

        return $this->socket->connect($this->server, $this->port);

    }

    private function close(){

        if(!$this->socket instanceof \Hazaar\Socket)
            return false;

        $this->socket->close();

        unset($this->socket);

        return true;

    }

    private function write($msg){

        if(!($this->socket instanceof \Hazaar\Socket && is_string($msg) && strlen($msg) > 0))
            return false;

        return $this->socket->send($msg . "\r\n", strlen($msg) + 2);

    }

    private function read($code, $len = 1024, &$message = null){

        if(!$this->socket->readSelect($this->read_timeout))
            throw new \Exception('SMTP data receive timeout');

        $this->socket->recv($buf, $len);

        if($this->getMessageCode($buf, $message) !== $code)
            return false;

        return true;

    }

    public function send($to, $subject = null, $message = null, $extra_headers = array()){
        
        $this->connect($this->server, $this->port);

        if(!$this->read(220, 1024, $result))
            throw new \Exception('Invalid response on connection: ' . $result);

        $this->write('EHLO x1');

        if(!$this->read(250, 65535, $result))
            throw new \Exception('Bad response on mail from: ' . $result);
        
        $this->write('MAIL FROM: <' . ake($extra_headers, 'From') . '>');

        if(!$this->read(250, 1024, $result))
            throw new \Exception('Bad response on mail from: ' . $result);

        foreach($to as $x){

            $this->write("RCPT TO: <$x>");

            if(!$this->read(250, 1024, $result))
                throw new \Exception('Bad response on mail to: ' . $result);

        }

        $this->write('DATA');

        if(!$this->read(354))
            throw new \Exception('Server rejected out data!');

        $extra_headers['Subject'] = $subject;

        $out = '';

        foreach($extra_headers as $key => $value)
            $out .= "$key: $value\r\n";

        $out .= "\r\n$message\r\n.";

        $this->write($out);

        if(!$this->read(250))
            throw new \Exception('Server rejected out data!');

        $this->write('QUIT');

        $this->close();

        return true;

    }

    private function getMessageCode($buf, &$message = null){

        if(!preg_match('/^(\d+)\s+(.+)$/m', $buf, $matches))
            return false;

        if(!is_numeric($matches[1]))
            return false;
            
        if(isset($matches[2]))
            $message = $matches[2];

        return intval($matches[1]);

    }


}
