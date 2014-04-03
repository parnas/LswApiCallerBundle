<?php
namespace Lsw\ApiCallerBundle\Call;

use Lsw\ApiCallerBundle\Helper\Curl;

/**
 * cURL based API Call
 *
 * @author Maurits van der Schee <m.vanderschee@leaseweb.com>
 */
abstract class CurlCall implements ApiCallInterface
{
    protected $url;
    protected $name;
    protected $requestObject;
    protected $requestHeaders;
    protected $responseRaw;
    protected $responseData;
    protected $responseObject;
    protected $responseHeaders;
    protected $status;
    protected $engine;
    protected $curlOptions;
    protected $method;

    /**
     * Class constructor
     *
     * @param string $url               API url
     * @param string $method            API method
     * @param object $requestObject     Request data
     * @param object $engine            Request engine
     */
    public function __construct($url, $method = '', $requestObject = array(), Curl $engine = null)
    {
        $this->url = $url;
        $this->method = $method;

        $this->requestObject = $requestObject;

        $this->engine = $engine ?: new Curl();
    }

    /**
     * {@inheritdoc}
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return get_class($this);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestData()
    {
        return http_build_query($this->requestObject);
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestObject()
    {
        return $this->requestObject;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestHeaders()
    {
        return $this->requestHeaders;
    }

    /**
     * {@inheritdoc}
     */
    public function getRequestObjectRepresentation()
    {
        $dumper = new \Symfony\Component\Yaml\Dumper();

        return $dumper->dump($this->requestObject, 100);
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseData()
    {
        return $this->responseData;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseObject()
    {
        return $this->responseObject;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseHeaders()
    {
        return $this->responseHeaders;
    }

    /**
     * {@inheritdoc}
     */
    public function getResponseObjectRepresentation()
    {
        $dumper = new \Symfony\Component\Yaml\Dumper();

        return $dumper->dump($this->responseObject, 100);
    }

    /**
     * {@inheritdoc}
     */
    public function getStatusCode()
    {
        return $this->status;
    }

    /**
     * Get the HTTP status message by HTTP status code
     *
     * @return mixed HTTP status message (string) or the status code (integer) if the message can't be found
     */
    public function getStatus()
    {
        $code = $this->getStatusCode();
        $codes = array(
                0   => 'Connection failed',
                100 => 'Continue',
                101 => 'Switching Protocols',
                200 => 'OK',
                201 => 'Created',
                202 => 'Accepted',
                203 => 'Non-Authoritative Information',
                204 => 'No Content',
                205 => 'Reset Content',
                206 => 'Partial Content',
                300 => 'Multiple Choices',
                301 => 'Moved Permanently',
                302 => 'Found',
                303 => 'See Other',
                304 => 'Not Modified',
                305 => 'Use Proxy',
                307 => 'Temporary Redirect',
                400 => 'Bad Request',
                401 => 'Unauthorized',
                403 => 'Forbidden',
                404 => 'Not Found',
                405 => 'Method Not Allowed',
                406 => 'Not Acceptable',
                407 => 'Proxy Authentication Required',
                408 => 'Request Timeout',
                409 => 'Conflict',
                410 => 'Gone',
                411 => 'Length Required',
                412 => 'Precondition Failed',
                413 => 'Request Entity Too Large',
                414 => 'Request URI Too Long',
                415 => 'Unsupported Media Type',
                416 => 'Requested Range Not Satisfiable',
                417 => 'Expectation Failed',
                500 => 'Internal Server Error',
                501 => 'Not Implemented',
                502 => 'Bad Gateway',
                503 => 'Service Unavailable',
                504 => 'Gateway Timeout',
                505 => 'HTTP Version Not Supported',
        );

        if (isset($codes[$code])) {
            return "$code $codes[$code]";
        }

        return $code;
    }

    /**
     * {@inheritdoc}
     */
    public function execute(array $options = array(), $parser = null)
    {
        $this->setCurlOptions($options);
        $this->makeRequest();
        $this->assignResponseValues();
        $this->status = $this->engine->getinfo(CURLINFO_HTTP_CODE);
        $this->requestHeaders = $this->engine->getinfo(CURLINFO_HEADER_OUT);
        $this->responseObject = ($parser) ? $parser($this->responseData) : $this->responseData;
        $result = $this->getResponseObject();

        return $result;
    }

    /**
     * Set curl options
     *
     * @param array $options
     */
    protected function setCurlOptions($options = array())
    {
        $params = array();
        $params['returntransfer'] = true;
        $params['header'] = true;
        $params['curlinfo_header_out'] = true;

        $this->curlOptions = $this->parseCurlOptions(array_merge($params, $options));
        $this->engine->setoptArray($this->curlOptions);
    }

    /**
     * Private method to parse cURL options from the bundle config.
     * If some option is not defined an exception will be thrown.
     *
     * @param array $config ApiCallerBundle configuration
     *
     * @throws \Exception Specified cURL option can't be found
     *
     * @return array
     */
    protected function parseCurlOptions($config)
    {
        $options = array();
        $prefix = 'CURLOPT_';
        foreach ($config as $key => $value) {
            $constantName = $prefix . strtoupper($key);
            // Weird check is because of CURLINFO_HEADER_OUT. Note the "CURLINFO_".
            // That also means that user can specify options with CURLOPT_ prefix directly.
            if (!defined($constantName) && ($constantName = strtoupper($key)) && !defined($constantName)) {
                $messageTemplate  = "Invalid option '%s' in apicaller.config.engine parameter. ";
                $messageTemplate .= "Use options (from the cURL section in the PHP manual) without prefix '%s'";
                $message = sprintf($messageTemplate, $key, $prefix);
                throw new \Exception($message);
            }
            $options[constant($constantName)] = $value;
        }

        return $options;
    }

    /**
     * {@inheritdoc}
     */
    protected function makeRequest()
    {
        $this->responseRaw = $this->engine->exec();
    }

    /**
     * Parses raw curl response into header and body (data)
     */
    protected function assignResponseValues()
    {
        $headers = $body = '';

        if($this->responseRaw) {
            $parts = explode("\r\n\r\nHTTP/", $this->responseRaw);
            $parts = (count($parts) > 1 ? 'HTTP/' : '').array_pop($parts);
            list($headers, $body) = explode("\r\n\r\n", $parts, 2);
        }

        $this->responseHeaders = $headers;
        $this->responseData = $body;
    }
}
