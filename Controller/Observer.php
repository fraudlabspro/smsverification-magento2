<?php

namespace Hexasoft\FraudLabsProSmsVerification\Controller;

use Magento\Framework\Event\ObserverInterface;

class Observer implements ObserverInterface {
    protected $_objectManager;
    protected $_storeManager;
    protected $scopeConfig;
    protected $_transportBuilder;
    protected $_orderFactory;
    protected $_logger;

    public function __construct(
        \Magento\Framework\ObjectManagerInterface $objectManager,
        \Magento\Store\Model\StoreManagerInterface $storeManager,
        \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
        \Magento\Framework\Mail\Template\TransportBuilder $transportBuilder,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Psr\Log\LoggerInterface $logger
    ) {
        $this->_objectManager = $objectManager;
        $this->_storeManager = $storeManager;
        $this->scopeConfig = $scopeConfig;
        $this->_transportBuilder = $transportBuilder;
        $this->_orderFactory = $orderFactory;
        $this->_logger = $logger;
    }

    public function execute(\Magento\Framework\Event\Observer $observer) {
        return $this->sendRequestToFraudLabsProSmsVerification($observer);
    }

    public function sendRequestToFraudLabsProSmsVerification($observer) {
        if (!$this->scopeConfig->getValue('fraudlabsprosmsverification/active_display/active', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) {
            return true;
        }

        $orderIds = $observer->getEvent()->getOrderIds();

        if (count($orderIds)) {
            $orderId = $orderIds[0];
            $order = $this->_orderFactory->create()->load($orderId);
            $customerEmail = $order->getCustomerEmail();

            if ($order->getfraudlabspro_response()) {
                if ($order->getfraudlabspro_response() === null) {
                    if($order->getfraudlabspro_response()){
                        $data = $this->_unserialize($order->getfraudlabspro_response());
                    }
                } else {
                     $data = json_decode($order->getfraudlabspro_response(), true);
                }
                if ($data['fraudlabspro_status'] == 'REVIEW') {
                    $subject = ($this->scopeConfig->getValue('fraudlabsprosmsverification/active_display/email_subject', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) ? ($this->scopeConfig->getValue('fraudlabsprosmsverification/active_display/email_subject')) : 'Action Required: SMS Verification is required to process the order.';

                    $content = ($this->scopeConfig->getValue('fraudlabsprosmsverification/active_display/email_body', \Magento\Store\Model\ScopeInterface::SCOPE_STORE)) ? ($this->scopeConfig->getValue('fraudlabsprosmsverification/active_display/email_body')) : "Dear Customer, Thanks for your business. Before we can continue processing your order, you may require you to click on the link to complete the SMS verification: {email_verification_link} Thank you.";

                    $code = $this->randomCode(20);
                    if (substr($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), -1) == '/') {
                        $siteUrl = substr($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), 0, -1);
                    } else {
                        $siteUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
                    }
                    $link = $siteUrl . '/fraudlabsprosmsverification?id=' . $orderId . '&code=' . $code;
                    if ( strpos( $content, '{email_verification_link}' ) !== false ) {
                        $content = str_replace( '{email_verification_link}', $link, $content );
                    }

                    try {
                        $store = $this->_storeManager->getStore()->getId();
                        $transport = $this->_transportBuilder->setTemplateIdentifier('contact_confirmation_template')
                            ->setTemplateOptions(['area' => 'frontend', 'store' => $store])
                            ->setTemplateVars(
                            [
                                'subject' => $subject,
                                'content' => $content,
                            ]
                        )
                        ->setFrom('general') //Store -> Configuration -> General -> Store Email Addresses
                        ->addTo($customerEmail)
                        ->getTransport();
                        $transport->sendMessage();
                        $this->_logger->info("Message sent !");

                        $data['fraudlabspro_sms_email_code'] = $code;
                        $data['fraudlabspro_sms_email_phone'] = '';
                        $data['fraudlabspro_sms_email_sms'] = '';
                        $order->setfraudlabspro_response(json_encode($data))->save();
                        return;
                    } catch (\Exception $e) {
                        $this->_logger->critical($e->getMessage());
                        return;
                    }
                }
                return;
            }
        }

        return;
    }

    /**
     * Generate random code for email verification.
     */
    private function randomCode($length=16){
        $key = '';
        $pattern = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        for($i=0; $i<$length; $i++) {
            $key .= $pattern[rand(0, strlen($pattern)-1)];
        }
        return $key;
    }

    private function _unserialize($data){
        if (class_exists(\Magento\Framework\Serialize\SerializerInterface::class)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $serializer = $objectManager->create(\Magento\Framework\Serialize\SerializerInterface::class);
            return $serializer->unserialize($data);
        } elseif (class_exists(\Magento\Framework\Unserialize\Unserialize::class)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $serializer = $objectManager->create(\Magento\Framework\Unserialize\Unserialize::class);
            return $serializer->unserialize($data);
        }
    }

}
