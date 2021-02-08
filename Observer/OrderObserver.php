<?php 
namespace payiopayment\offlinepayments\Observer;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientFactory;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ResponseFactory;
use Magento\Framework\Webapi\Rest\Request;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\Stdlib\Cookie\CookieMetadataFactory;
use Magento\Framework\Stdlib\CookieManagerInterface;

class OrderObserver implements \Magento\Framework\Event\ObserverInterface
{
    const API_KEY = 'payment/payio/api_key';
    const REDIRECT_URL = 'payment/payio/redirect_url';

    protected $_responseFactory;

    public function __construct(
        \Psr\Log\LoggerInterface $logger,
        \Magento\Framework\App\ResponseFactory $responseFactory,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Encryption\EncryptorInterface $encryptor,
        CookieManagerInterface $cookieManager,
        CookieMetadataFactory $cookieMetadataFactory
     ) 
     {
        $this->client   = new GuzzleClient();
        $this->_logger = $logger;
        $this->_responseFactory = $responseFactory;
        $this->scopeConfig = $scopeConfig;
        $this->_encryptor = $encryptor;
        $this->cookieManager = $cookieManager;
        $this->cookieMetadataFactory = $cookieMetadataFactory;
     }

    public function execute(\Magento\Framework\Event\Observer $observer)
    {
       
       try {
            
            if (empty($observer->getOrder())) {
                return $this;
            }

            $order = $observer->getOrder();

            if (empty($order->getPayment())) {
                return $this;
            }

            $payment = $order->getPayment();

            if ($payment->getMethodInstance()->getCode() != 'payio') {
                return $this;
            }
            

            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $cookieManager = $objectManager->create('\Magento\Framework\Stdlib\CookieManagerInterface');
            $cookieMetadataFactory = $objectManager->create('\Magento\Framework\Stdlib\Cookie\CookieMetadataFactory');

            
            $apiKeyValue = $this->scopeConfig->getValue(self::API_KEY,\Magento\Store\Model\ScopeInterface::SCOPE_STORE);
            $redirectUrl = $this->scopeConfig->getValue(self::REDIRECT_URL,\Magento\Store\Model\ScopeInterface::SCOPE_STORE);

            $apiKey = $this->_encryptor->decrypt($apiKeyValue);
            $orderData = $order->getData(); 

            
            $clientEmail = $orderData['customer_email'];
            $clientFirstName = $orderData['customer_firstname'];
            $clientLastname = $orderData['customer_lastname'];
            $amount = $orderData['total_due'];
            $uniqueId = $this->unique_id();
    
            $url = 'https://secure.payio.co.uk/api/v1/payment-request/create';  
            $data = [
                "amount" => $amount,
                "reference" => "GBPdasdasd",
                "client_name" => $clientFirstName.' '.$clientLastname,
                "client_email" => $clientEmail,   
                "redirect_url" => $redirectUrl,
                "unique_id" =>  $uniqueId
            ];
            $options = [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Channel-API-Key' => $apiKey                
                ],
                'json' => $data
            ];

            $res = $this->client->request("POST",$url,$options);
            $paymentData = json_decode($res->getBody()->getContents());
            $payment->setPayioUniqueId($uniqueId);

            $metadata   = $cookieMetadataFactory
                        ->createPublicCookieMetadata()
                        ->setDuration(1800)
                        ->setPath('/');
            $cookieManager->setPublicCookie('url', $paymentData->url, $metadata);

            return $this;   
        } catch (\Exception $e) {
            $metadata   = $cookieMetadataFactory
                        ->createPublicCookieMetadata()
                        ->setDuration(1800)
                        ->setPath('/');
            $cookieManager->setPublicCookie('url', null, $metadata); 

            throw new \Magento\Framework\Validator\Exception(__('Payment capturing error.'));
        }
      
    }

    /**
     * generate unique id
     *
     * @return   string
     *
    */
    private function unique_id() 
    {
        return substr(md5(uniqid(mt_rand(), true)), 0, 8);
    }

}