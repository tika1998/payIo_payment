<?php


namespace payiopayment\offlinepayments\Model;


class PayIoPayments extends \Magento\Payment\Model\Method\AbstractMethod
{

    /**
     * Payment method code
     *
     * @var string
     */
    protected $_code = 'payio';

    /**
     * Availability option
     *
     * @var bool
     */
    protected $_isOffline = true;


}
