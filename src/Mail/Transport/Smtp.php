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

    private function read($code, $len = 1024, &$message = null, &$response = null){

        if(!$this->socket->readSelect($this->read_timeout))
            throw new \Exception('SMTP data receive timeout');

        $this->socket->recv($response, $len);

        if($this->getMessageCode($response, $message) !== $code)
            return false;

        return true;

    }

    public function send($to, $subject = null, $message = null, $extra_headers = array()){
        
        $from = ake($extra_headers, 'From');

        if(preg_match('/\<(\w+@.+)\>/', $from, $matches))
            $from = $matches[1];

        $this->connect($this->server, $this->port);

        if(!$this->read(220, 1024, $result))
            throw new \Exception('Invalid response on connection: ' . $result);

        $this->write('EHLO ' . gethostname());

        if(!$this->read(250, 65535, $result, $response))
            throw new \Exception('Bad response on mail from: ' . $result);
        
        $auth_methods = array_reduce(explode("\r\n", $response), function($carry, $item){
            if(substr($item, 0, 8) === '250-AUTH') return array_merge($carry, explode(' ', substr($item, 9)));
            return $carry;
        }, array());

        if($this->options->has('username')){

            if(in_array('CRAM-MD5', $auth_methods)){

                $this->write('AUTH CRAM-MD5');

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server does not want to CRAM-MD5 authenticate!');

                $this->write(base64_encode($this->options['username'] . ' ' . hash_hmac('MD5', base64_decode($result), $this->options['password'])));

            }elseif(in_array('LOGIN', $auth_methods)){

                $this->write('AUTH LOGIN');

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server does not want to LOGIN authenticate!');

                if(($prompt = base64_decode($result)) !== 'Username:')
                    throw new \Exception('Server is broken.  Sent weird username prompt \'' . $prompt . '\'');

                $this->write(base64_encode($this->options['username']));

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server did not request password: ' . $result);

                $this->write(base64_encode($this->options['password']));
                
            }elseif(in_array('PLAIN', $auth_methods)){

                $this->write('AUTH PLAIN');

                if(!$this->read(334, 128, $result))
                    throw new \Exception('Server does not want to PLAIN authenticate!');

                $this->write(base64_encode("\0" . $this->options['username'] . "\0" . $this->options['password']));

            }

            if(!$this->read(235, 1024, $result, $response))
                throw new \Exception('SMTP Auth failed: ' . $result);

        }

        $this->write("MAIL FROM: $from");

        if(!$this->read(250, 1024, $result))
            throw new \Exception('Bad response on mail from: ' . $result);

        foreach($to as $x){

            if(preg_match('/^(.*)<(.*)>$/', $x, $matches)){

                if(!(array_key_exists('To', $extra_headers) && is_array($extra_headers['To'])))
                    $extra_headers['To'] = (array_key_exists('To', $extra_headers) ? $extra_headers['To'] : array());

                $extra_headers['To'][] = $x;

                $x = $matches[2];

            }

            $this->write("RCPT TO: <$x>");

            if(!$this->read(250, 1024, $result))
                throw new \Exception('Bad response on mail to: ' . $result);

        }

        $this->write('DATA');

        if(!$this->read(354))
            throw new \Exception('Server rejected our data!');

        $extra_headers['Subject'] = $subject;

        $out = '';

        foreach($extra_headers as $key => $value)
            $out .= "$key: " . (is_array($value) ? implode(', ', $value) : $value) . "\r\n";

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
