<?php
require_once('Core/Response/YogResponse.php');
require_once('Core/Serializer/SerializerJSON.php');
require_once('Core/Serializer/SerializerXML.php');
class Response implements YogResponse
{
    private $status_code;
    private $body;
    private $headers;
    private $Serializer;
    private $status_reason;
    private $protocol_version;
    private $cookies;
    private $send_body = true;
    public function __construct($body = '', $status_code = 200)
    {
        $this->body        = $body;
        $this->status_code = $status_code;
    }
    public function setCookie(YogCookie $Cookie)
    {
        $name = $Cookie->getName();
        if (empty($name)) return false;
        $name                 = $this->getNewOrExistingKeyInArray($name, $this->cookies);
        $this->cookies[$name] = $Cookie;
    }
    public function getCookie($name)
    {
        $name = $this->getNewOrExistingKeyInArray($name, $this->cookies);
        return isset($this->cookies[$name]) ? $this->cookies[$name] : NULL;
    }
    public function getCookies()
    {
        return $this->cookies;
    }
    public function getStatusCode()
    {
        return $this->status_code;
    }
    public function setStatusCode($status_code)
    {
        $this->status_code = (int)$status_code;
    }
    public function setHeader($header, $value)
    {
        if (empty($header)) return false;
        $header                 = $this->getNewOrExistingKeyInArray($header, $this->headers);
        $this->headers[$header] = $value;
        if (strtolower($header) === 'location'
            && ($this->status_code < 300 || $this->status_code > 399)
        ) {
            $this->setStatusCode(301);
        }
    }
    public function getHeader($header)
    {
        $header = $this->getNewOrExistingKeyInArray($header, $this->headers);
        return isset($this->headers[$header]) ? $this->headers[$header] : NULL;
    }
    public function getBody()
    {
        return $this->body;
    }
    public function setBody($body)
    {
        $this->body = $body;
    }
    public function getHeaders()
    {
        return $this->headers;
    }
    public function setHeaders(array $headers)
    {
        $this->headers = $headers;
    }
    public function Send()
    {
//        header_remove();
        $body    = $this->getBodyToSend();
        $headers = $this->getHeadersToSend($body);
        foreach ($headers as $header) {
            header($header);
        }
        if (!empty($this->cookies)) {
            foreach ($this->cookies as $Cookie) {
                setcookie($Cookie->getName(), $Cookie->getValue(),
                    $Cookie->getExpire(), $Cookie->getPath(),
                    $Cookie->getDomain(), $Cookie->getSecure(),
                    $Cookie->getHttpOnly());
            }
        }
        if ($this->send_body) {
            print $body;
        }
    }
    public function __toString()
    {
        $body    = $this->getBodyToSend();
        $headers = $this->getHeadersToSend($body);
        return implode("\r\n", $headers)."\r\n\r\n$body";
    }
    private function getHeadersToSend(&$body)
    {
        $headers = array();
        if (isset($body)) {
            $this->setHeader('Content-Length', strlen($body));
        }
        $headers[] = 'HTTP/'.$this->getProtocolVersion().' '.$this->getStatusCode().' '.$this->getStatusReason();
        if (($h = $this->getHeaders())) {
            foreach ($h as $header_key => $header_value) {
                $headers[] = $header_key.': '.$header_value;
            }
        }
        if (($cookies = $this->getCookies())) {
            foreach ($cookies as $Cookie) {
                $headers[] = 'Set-Cookie: '.$Cookie->getHeader();
            }
        }
        return $headers;
    }
    private function getBodyToSend()
    {
        if (!empty($this->Serializer) && $this->body !== '') {
            $body = $this->Serializer->serialize($this->body);
            if ($this->Serializer->getContentType()) {
                $this->setHeader('Content-Type', $this->Serializer->getContentType());
            }
            return $body;
        }
        else {
            $body = $this->body;
            if (!is_scalar($body)) {
                $Serializer = new SerializerJSON();
                $body       = $Serializer->serialize($body);
                $this->setHeader('Content-Type', $Serializer->getContentType());
                $this->setHeader('X-Warning', 'Result of request should be serialized to send through. Specify in "ACCEPT" header type of acceptable method of serialization.');
                return $body;
            }
            return $body;
        }
    }
    public function setSerializer($Serializer)
    {
        $this->Serializer = $Serializer;
    }
    public function isStatusError()
    {
        return ($this->status_code > 399);
    }
    public function getSerializer()
    {
        return $this->Serializer;
    }
    private function getNewOrExistingKeyInArray($key, $array)
    {
        if (empty($array)) return $key;
        $keys    = array_keys($array);
        $low_key = strtolower($key);
        foreach ($keys as $existing_key) {
            if ($low_key === strtolower($existing_key)) {
                return $existing_key;
            }
        }
        return $key;
    }
    public function getStatusReason()
    {
        if (empty($this->status_reason)) {
            if (empty($this->status_code)) {
                $this->status_code = 200;
                return 'OK (default)';
            }
            $reasons = array(
                '100' => 'Continue',
                '101' => 'Switching Protocols',
                '102' => 'Processing',
                '200' => 'OK',
                '201' => 'Created',
                '202' => 'Accepted',
                '203' => 'Non-Authoritative Information',
                '204' => 'No Content',
                '205' => 'Reset Content',
                '206' => 'Partial Content',
                '207' => 'Multi-Status',
                '208' => 'Already Reported',
                '226' => 'IM Used',
                '300' => 'Multiple Choices',
                '301' => 'Moved Permanently',
                '302' => 'Found',
                '303' => 'See Other',
                '304' => 'Not Modified',
                '305' => 'Use Proxy',
                '306' => 'Switch Proxy',
                '307' => 'Temporary Redirect',
                '308' => 'Permanent Redirect',
                '400' => 'Bad Request',
                '401' => 'Unauthorized',
                '402' => 'Payment Required',
                '403' => 'Forbidden',
                '404' => 'Not Found',
                '405' => 'Method Not Allowed',
                '406' => 'Not Acceptable',
                '407' => 'Proxy Authentication Required',
                '408' => 'Request Timeout',
                '409' => 'Conflict',
                '410' => 'Gone',
                '411' => 'Length Required',
                '412' => 'Precondition Failed',
                '413' => 'Request Entity Too Large',
                '414' => 'Request-URI Too Long',
                '415' => 'Unsupported Media Type',
                '416' => 'Requested Range Not Satisfiable',
                '417' => 'Expectation Failed',
                '418' => 'I\'m a teapot',
                '420' => 'Enhance Your Calm',
                '422' => 'Unprocessable Entity',
                '423' => 'Locked',
                '424' => 'Failed Dependency',
                '425' => 'Unordered Collection',
                '426' => 'Upgrade Required',
                '428' => 'Precondition Required',
                '429' => 'Too Many Requests',
                '431' => 'Request Header Fields Too Large',
                '444' => 'No Response',
                '449' => 'Retry With',
                '450' => 'Blocked by Windows Parental Controls',
                '499' => 'Client Closed Request',
                '500' => 'Internal Server Error',
                '501' => 'Not Implemented',
                '502' => 'Bad Gateway',
                '503' => 'Service Unavailable',
                '504' => 'Gateway Timeout',
                '505' => 'HTTP Version Not Supported',
                '506' => 'Variant Also Negotiates',
                '507' => 'Insufficient Storage',
                '508' => 'Loop Detected',
                '509' => 'Bandwidth Limit Exceeded',
                '510' => 'Not Extended',
                '511' => 'Network Authentication Required',
                '598' => 'Network read timeout error',
                '599' => 'Network connect timeout error');
            if (isset($reasons[$this->status_code])) {
                $this->status_reason = $reasons[$this->status_code];
            }
        }
        return $this->status_reason;
    }
    public function setStatusReason($status_reason)
    {
        $this->status_reason = $status_reason;
    }
    public function removeHeader($header)
    {
        if (empty($header)) return false;
        $header = $this->getNewOrExistingKeyInArray($header, $this->headers);
        unset($this->headers[$header]);
        return true;
    }
    public function getProtocolVersion()
    {
        if (empty($this->protocol_version)) {
            if (isset($_SERVER['SERVER_PROTOCOL'])) {
                list(, $this->protocol_version) = explode('/', $_SERVER['SERVER_PROTOCOL']);
            }
            else {
                $this->protocol_version = '1.0';
            }
        }
        return $this->protocol_version;
    }
    public function setProtocolVersion($protocol_version)
    {
        $this->protocol_version = $protocol_version;
    }
    public function getSendBody()
    {
        return $this->send_body;
    }
    public function setSendBody($send_body)
    {
        $this->send_body = $send_body;
    }
}