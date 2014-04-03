<?php

namespace Lsw\ApiCallerBundle\Factory;

use Lsw\ApiCallerBundle\Caller\LoggingApiCaller;
use Lsw\ApiCallerBundle\Logger\ApiCallLoggerInterface;
use Lsw\ApiCallerBundle\Parser\ApiParserInterface;

/**
 * Logging API Caller Factory
 *
 * @author Dmitry Parnas <d.parnas@ocom.com>
 */
class ApiCallerFactory
{
    protected $options, $logger, $instances;

    /**
     *
     * @param array                  $options Options array
     * @param ApiCallLoggerInterface $logger  Logger
     *
     */
    public function __construct($options, ApiCallLoggerInterface $logger = null)
    {
        $this->options = $options;
        $this->logger = $logger;
    }

    /**
     * Singleton factory method to instantiate/get api caller
     *
     * @param string                $name API configuration entity name
     * @param ApiParserInterface    $parser DI parser
     *
     */
    public function api($name, ApiParserInterface $parser = null)
    {
        if(isset($this->options[$name])) {
            if(!isset($this->instances[$name])) {
                $this->instances[$name] = new LoggingApiCaller($this->options[$name], $this->logger, $parser);
            }

            return $this->instances[$name];
        }

        throw new \Exception('Wrong API: '.$name);
    }


    public function __call($name, array $arguments)
    {
        $api = $this->api('_');

        return call_user_func_array(array($api, $name), $arguments);
    }
}