<?php
class MindArc_FatZebra_Helper_Response extends Mage_Core_Helper_Abstract
{
    /**
     *
     * Response from FatZebra is returning chunked header but body is not chunked
     * Zend throws exception when we have this mismatch in response
     * This function can optionally extract the body by ignoring chunk header
     *
     * @see Zend_Http_Response::getBody()
     * @see Zend_Http_Response::decodeChunkedBody()
     *
     * @param Zend_Http_Response $oResponse
     *
     * @return string
     * @throws Zend_Http_Exception
     */
    public function getChunkBody(Zend_Http_Response $oResponse)
    {
        try {
            return $oResponse->getBody();
        } catch (Zend_Http_Exception $e) {
            if (!$this->canRemoveHeader()) {
                throw $e;
            }
        }


        $vResponse = $oResponse->asString();
        $headers = Zend_Http_Response::extractHeaders($vResponse);
        if (isset($headers['transfer-encoding']) &&
            ($headers['transfer-encoding'] == 'chunked')
        ) {
            unset($headers['transfer-encoding']);
            /**
             * @see Zend_Http_Response::fromString()
             */
            $code = Zend_Http_Response::extractCode($vResponse);
            $body = Zend_Http_Response::extractBody($vResponse);
            $version = Zend_Http_Response::extractVersion($vResponse);
            $message = Zend_Http_Response::extractMessage($vResponse);
            $oResponse = new Zend_Http_Response($code, $headers, $body, $version, $message);
            return $oResponse->getBody();
        }
        throw $e;

    }
   protected function canRemoveHeader()
   {
       return Mage::getStoreConfigFlag('payment/fatzebra/ignore_chunk_response');
   }
}