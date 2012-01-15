<?php

namespace Inori\Banklink\iPizza;

use Inori\Banklink\Protocol;

/**
 * This class implements iPizza protocol support
 *
 * @author Roman Marintsenko <roman.marintsenko@knplabs.com>
 * @since  11.01.2012
 */
class iPizza extends Protocol
{
    private $privateKey;
    private $publicKey;

    private $sellerId;
    private $sellerName;
    private $sellerAccountNumber;

    private $successUrl;
    private $failureUrl;

    private $protocolVersion = '008';
    private $charset         = 'UTF-8';

    /**
     * initialize basic data that will be used for all issued service requests
     */
    public function __construct($sellerId, $sellerName, $sellerAccNum, $privKeyLoc, $pubKeyLoc, $successUrl, $failureUrl)
    {
        $this->successUrl          = $successUrl;
        $this->failureUrl          = $failureUrl;
        $this->sellerId            = $sellerId;
        $this->sellerName          = $sellerName;
        $this->sellerAccountNumber = $sellerAccNum;

        $this->publicKey           = file_get_contents($pubKeyLoc);
        $this->privateKey          = file_get_contents($privKeyLoc);
    }

    /**
     * @see Protocol::preparePaymentRequest()
     */
    public function preparePaymentRequest($orderId, $message, $sum, $language, $currency = 'EUR')
    {
        $data = array();

        $data[Fields::SERVICE_ID]       = Services::PAYMENT_REQUEST;
        $data[Fields::PROTOCOL_VERSION] = $this->protocolVersion;
        $data[Fields::SELLER_ID]        = $this->sellerId;
        $data[Fields::ORDER_ID]         = $orderId;
        $data[Fields::SUM]              = $sum;
        $data[Fields::CURRENCY]         = $currency;
        $data[Fields::SELLER_BANK_ACC]  = $this->sellerAccountNumber;
        $data[Fields::SELLER_NAME]      = $this->sellerName;
        $data[Fields::ORDER_REFERENCE]  = $this->generateOrderReference($orderId);
        $data[Fields::DESCRIPTION]      = $message;
        $data[Fields::CHARSET]          = $this->charset;
        $data[Fields::SUCCESS_URL]      = $this->successUrl;
        $data[Fields::FAILURE_URL]      = $this->failureUrl;
        $data[Fields::USER_LANG]        = $language;

        $data[Fields::SIGNATURE]        = $this->getSignature($data, $this->privateKey);

        return $data;
    }

    /**
     *
     * @param type $data
     * @param type $key
     * @return type
     */
    protected function getSignature($data, $key)
    {
        $checksum = $this->generateChecksum($data);

        $keyId = openssl_get_privatekey($key);
        openssl_sign($checksum, $signature, $keyId);
        openssl_free_key($keyId);

        $result = base64_encode($signature);
        
        return $result;
    }

    /**
     *
     * @param array $data
     *
     * @return string
     */
    protected function generateChecksum(array $data)
    {
        if (!isset($data[Fields::SERVICE_ID])) {
            throw new \InvalidArgumentException('Cannot generate checksum without service id');
        }

        $checksum = '';
        $fields = $this->getServiceFields($data[Fields::SERVICE_ID]);

        foreach ($fields as $field) {
            if (!isset($data[$field])) {
                throw new \InvalidArgumentException(sprintf('%s field is required for %s service', $field, $data[Fields::SERVICE_ID]));
            }

            $checksum .= sprintf('000%d%s', strlen($data[$field]), $data[$field]);
        }

        return $checksum;
    }

    /**
     * @param string $serviceId
     */
    protected function getServiceFields($serviceId)
    {
        if (Services::PAYMENT_REQUEST === $serviceId) {
            return array(
                Fields::SERVICE_ID,
                Fields::PROTOCOL_VERSION,
                Fields::SELLER_ID,
                Fields::ORDER_ID,
                Fields::SUM,
                Fields::CURRENCY,
                Fields::SELLER_BANK_ACC,
                Fields::SELLER_NAME,
                Fields::ORDER_REFERENCE,
                Fields::DESCRIPTION,
                Fields::CHARSET,
                Fields::SUCCESS_URL,
                Fields::FAILURE_URL,
                Fields::USER_LANG
            );
        } else {
            throw new \InvalidArgumentException('Unsupported service id: '.$serviceId);
        }
    }
}