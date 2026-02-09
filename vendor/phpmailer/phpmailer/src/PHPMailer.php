<?php
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    public $isSMTP = false;
    public $Host;
    public $SMTPAuth;
    public $Username;
    public $Password;
    public $SMTPSecure;
    public $Port;
    public $From;
    public $FromName;
    public $Subject;
    public $Body;
    public $AltBody;
    public $isHTML = false;

    private $addresses = [];

    public function isSMTP()
    {
        $this->isSMTP = true;
    }

    public function isHTML($bool)
    {
        $this->isHTML = (bool) $bool;
    }

    public function setFrom($from, $name = '')
    {
        $this->From = $from;
        $this->FromName = $name;
    }

    public function addAddress($address)
    {
        $this->addresses[] = $address;
    }

    public function send()
    {
        $to = implode(',', $this->addresses);
        $headers = "From: " . ($this->FromName ? $this->FromName . " <" . $this->From . ">" : $this->From) . "\r\n";
        if ($this->isHTML) {
            $headers .= "MIME-Version: 1.0\r\nContent-type: text/html; charset=UTF-8\r\n";
        }
        $subject = $this->Subject ?: '';
        $message = $this->Body ?: $this->AltBody ?: '';
        return @mail($to, $subject, $message, $headers);
    }
}
