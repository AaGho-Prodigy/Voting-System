<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * PHP Version 5.5+
 * @package PHPMailer
 * @link https://github.com/PHPMailer/PHPMailer
 * @author Marcus Bointon (Synchro/coolbru) <phpmailer@synchromedia.co.uk>
 * @author Jim Jagielski (jimjag) <jimjag@gmail.com>
 * @author Andy Prevost (codeworxtech) <codeworxtech@users.sourceforge.net>
 * @author Brent R. Matzelle (original founder)
 * @copyright 2012 - 2020 Marcus Bointon
 * @copyright 2010 - 2012 Jim Jagielski
 * @copyright 2004 - 2009 Andy Prevost
 * @license http://www.gnu.org/copyleft/lesser.html GNU Lesser General Public License
 */

namespace PHPMailer\PHPMailer;

/**
 * PHPMailer class.
 */
class PHPMailer
{
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS = 'ssl';

    public $Host = 'localhost';
    public $Port = 25;
    public $SMTPAuth = false;
    public $Username = '';
    public $Password = '';
    public $SMTPSecure = '';
    public $From = '';
    public $FromName = '';
    public $Subject = '';
    public $Body = '';
    public $AltBody = '';
    public $CharSet = 'UTF-8';
    public $Mailer = 'mail';
    public $ErrorInfo = '';
    public $ContentType = 'text/html';
    private $to = [];

    public function __construct($exceptions = null)
    {
    }

    public function isSMTP()
    {
        $this->Mailer = 'smtp';
        return $this;
    }

    public function setFrom($address, $name = '')
    {
        $this->From = $address;
        $this->FromName = $name;
        return true;
    }

    public function addAddress($address, $name = '')
    {
        $this->to[] = ['address' => $address, 'name' => $name];
        return true;
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
        return $this;
    }

    public function send()
    {
        if ($this->Mailer == 'smtp') {
            return $this->smtpSend();
        } else {
            return $this->mailSend();
        }
    }

    private function smtpSend()
    {
        $host = $this->Host;
        $port = $this->Port;

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ]
        ]);

        $fp = stream_socket_client("tcp://$host:$port", $errno, $errstr, 30, STREAM_CLIENT_CONNECT, $context);
        if (!$fp) {
            $this->ErrorInfo = "Connection failed: $errstr ($errno)";
            return false;
        }

        fgets($fp, 1024);

        fputs($fp, "EHLO localhost\r\n");
        while ($line = fgets($fp, 1024)) {
            if (substr($line, 3, 1) == ' ') break;
        }

        if ($this->SMTPSecure == 'tls') {
            fputs($fp, "STARTTLS\r\n");
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != '220') {
                $this->ErrorInfo = 'TLS failed';
                fclose($fp);
                return false;
            }
            stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
            fputs($fp, "EHLO localhost\r\n");
            while ($line = fgets($fp, 1024)) {
                if (substr($line, 3, 1) == ' ') break;
            }
        }

        if ($this->SMTPAuth) {
            fputs($fp, "AUTH LOGIN\r\n");
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != '334') {
                $this->ErrorInfo = 'AUTH not supported';
                fclose($fp);
                return false;
            }
            fputs($fp, base64_encode($this->Username) . "\r\n");
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != '334') {
                $this->ErrorInfo = 'Username rejected';
                fclose($fp);
                return false;
            }
            fputs($fp, base64_encode($this->Password) . "\r\n");
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != '235') {
                $this->ErrorInfo = 'Authentication failed';
                fclose($fp);
                return false;
            }
        }

        fputs($fp, "MAIL FROM: <{$this->From}>\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '250') {
            $this->ErrorInfo = 'MAIL FROM failed';
            fclose($fp);
            return false;
        }

        foreach ($this->to as $to) {
            fputs($fp, "RCPT TO: <{$to['address']}>\r\n");
            $response = fgets($fp, 1024);
            if (substr($response, 0, 3) != '250') {
                $this->ErrorInfo = 'RCPT TO failed';
                fclose($fp);
                return false;
            }
        }

        fputs($fp, "DATA\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '354') {
            $this->ErrorInfo = 'DATA command failed';
            fclose($fp);
            return false;
        }

        $headers = "From: {$this->FromName} <{$this->From}>\r\n";
        $headers .= "To: {$this->to[0]['name']} <{$this->to[0]['address']}>\r\n";
        $headers .= "Subject: {$this->Subject}\r\n";
        $headers .= "Content-Type: {$this->ContentType}; charset={$this->CharSet}\r\n";
        $headers .= "\r\n";

        fputs($fp, $headers);
        fputs($fp, $this->Body);
        fputs($fp, "\r\n.\r\n");
        $response = fgets($fp, 1024);
        if (substr($response, 0, 3) != '250') {
            $this->ErrorInfo = 'Message sending failed';
            fclose($fp);
            return false;
        }

        fputs($fp, "QUIT\r\n");
        fclose($fp);

        return true;
    }

    private function mailSend()
    {
        $headers = "From: {$this->FromName} <{$this->From}>\r\n";
        $headers .= "Content-Type: {$this->ContentType}; charset={$this->CharSet}\r\n";

        return mail($this->to[0]['address'], $this->Subject, $this->Body, $headers);
    }
}