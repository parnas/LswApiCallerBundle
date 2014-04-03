<?php

namespace Lsw\ApiCallerBundle\Parser;

/**
 * Result parser for json
 *
 * @author Dmitry Parnas <d.parnas@ocom.com>
 */
class Xmlrpc extends ApiParser implements ApiParserInterface
{
    /**
     * {@inheritdoc}
     *
     * @return object
     */
    public function parse($data)
    {
        $parsed = xmlrpc_decode($data);

        return $parsed;
    }
}