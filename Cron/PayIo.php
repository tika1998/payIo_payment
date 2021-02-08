<?php
/**
 * Copyright © lala All rights reserved.
 * See COPYING.txt for license details.
 */

namespace payiopayment\offlinepayments\Cron;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;

class PayIo
{

    protected $logger;
    const API_KEY = 'payment/payio/api_key';

    /**
     * Constructor
     *
     * @param \Psr\Log\LoggerInterface $logger
     */
    public function __construct(\Psr\Log\LoggerInterface $logger, \Magento\Newsletter\Model\SubscriberFactory $subscriberFactory, \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig, \Magento\Framework\Encryption\EncryptorInterface $encryptor, \Magento\Framework\App\ResourceConnection $resource)
    {
        $this->logger = $logger;
        $this->subscriberFactory = $subscriberFactory;
        $this->client = new GuzzleClient();
        $this->scopeConfig = $scopeConfig;
        $this->_encryptor = $encryptor;
        $this->resource = $resource;
    }

    /**
     * Execute the cron
     *
     * @return void
     */
    public function execute()
    {
        $apiKeyValue = $this->scopeConfig->getValue(self::API_KEY,\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
        $apiKey = $this->_encryptor->decrypt($apiKeyValue);

        $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
        $resource = $objectManager->get('Magento\Framework\App\ResourceConnection');
        $connection = $resource->getConnection();
        $connection1 = $this->resource;
        $salesOrderPayment = $connection1->getTableName('sales_order_payment');
        $salesOrder = $connection1->getTableName('sales_order');
        $condition1 = new \Zend_Db_Expr("order.status = 'processing'");
        $condition2 = new \Zend_Db_Expr("order.status = 'pending'");
        $select = $this->connection->select()
          ->from(
              ['payment' => $salesOrderPayment]
          )
          ->where('payment.payio_unique_id != ?', 'null')
          ->join(
            ['order' => $salesOrder],
            'order.entity_id=payment.parent_id',
          )
          ->where ("{$condition1} OR {$condition2}");
        $data = $connection->fetchAll($select);

        foreach ($data as $result) {
           try {
                $url = 'https://secure.payio.co.uk/api/v1/payment-request/status/' . $result['payio_unique_id'];  
                $options = [
                    'headers' => [
                        'Content-Type' => 'application/json',
                        'Accept' => 'application/json',
                        'Channel-API-Key' => $apiKey                
                    ]
                ];

                $res = $this->client->request("GET",$url,$options);
                $paymentData = json_decode($res->getBody()->getContents());
                $this->logger->debug('1111',get_object_vars($paymentData));  

                $status = $paymentData->data->status;
                $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
                $order = $objectManager->create('\Magento\Sales\Model\Order')->load($result['parent_id']);
                
                if($status == 'paid') {
                    $order->setState('success')->setStatus('success');
                    $order->save();
                }
           } catch (\Exception $e) {
              $this->logger->debug($e->getMessage());  

              continue; 
            // throw new \Magento\Framework\Validator\Exception(__($e, '15151515'));

           }
        }
    }
}
