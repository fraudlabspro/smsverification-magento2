<?php
namespace Hexasoft\FraudLabsProSmsVerification\Block;
class Fraudlabsprosmsverificationsend extends \Magento\Framework\View\Element\Template
{

    protected $orderRepository ;
    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        parent::__construct($context, $data);
    }

    public function getConfig()
    {
        return $this->_scopeConfig;
    }

    public function getOrder($id)
    {
        return $this->orderRepository->get($id);
    }

    public function methodBlock()
    {
        $apiKey = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/api_key')) ? $this->getConfig()->getValue('fraudlabsprosmsverification/active_display/api_key') : 'API Key cannot be empty.';
        $otpTimeout = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/otp_timeout')) ? $this->getConfig()->getValue('fraudlabsprosmsverification/active_display/otp_timeout') : 3600;
        if ($apiKey == 'Phone number cannot be empty.') return 'API Key cannot be empty.';
        $tel = (filter_input(INPUT_POST, 'tel')) ? (filter_input(INPUT_POST, 'tel')) : 'Phone number cannot be empty.';
        if ($tel == 'Phone number cannot be empty.') return 'Phone number cannot be empty.';
        $sms_order_id = (filter_input(INPUT_POST, 'sms_order_id')) ? (filter_input(INPUT_POST, 'sms_order_id')) : '';
        $sms_code = (filter_input(INPUT_POST, 'sms_code')) ? (filter_input(INPUT_POST, 'sms_code')) : '';
        $params['format'] = 'json';
        $params['source'] = 'magento';
        $params['tel'] = trim($tel);
        if (strpos($params['tel'], '+') !== 0)
            $params['tel'] = '+' . $params['tel'];
        $params['mesg'] = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_template')) ? $this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_template') : 'Hi, your OTP for Magento is {otp}.';
        $params['mesg'] = str_replace(['{', '}'], ['<', '>'], $params['mesg']);
        $params['otp_timeout'] = $otpTimeout;
        $url = 'https://api.fraudlabspro.com/v1/verification/send';

        $query = '';

        foreach($params as $key=>$value) {
            $query .= '&' . $key . '=' . rawurlencode($value);
        }

        $url = $url . '?key=' . $apiKey . $query;

        $result = file_get_contents($url);

        // network error, wait 2 seconds for next retry
        if (!$result) {
            for ($i = 0; $i < 3; ++$i) {
                sleep(2);
                $result = file_get_contents($url);
            }
        }

        // still having network issue after 3 retries, give up
        if (!$result)
            return;

        // Get the HTTP response
        $data = json_decode($result);

        if (trim($data->error) != '') {
            return $data->error;
        }
        else {
            if ( $sms_order_id != "" ) {
                if ( $sms_code != "" ) {
                    $order = $this->getOrder($sms_order_id);
                    if ($order->getfraudlabspro_response()) {
                        if(is_null(json_decode($order->getfraudlabspro_response(), true))){
                            if($order->getfraudlabspro_response()){
                                $flpdata = $this->_unserialize($order->getfraudlabspro_response());
                            }
                        } else {
                             $flpdata = json_decode($order->getfraudlabspro_response(), true);
                        }
                        if ( $flpdata['fraudlabspro_sms_email_code'] == $sms_code ) {
                            $flpdata['fraudlabspro_sms_email_code'] = $sms_code . '_VERIFIED';
                            $order->setfraudlabspro_response(json_encode($flpdata))->save();
                        }
                    }
                }
            }
            return 'FLPOK' . $data->tran_id . $data->otp_char;
        }

    }

    private function _unserialize($data){
        if (class_exists(\Magento\Framework\Serialize\SerializerInterface::class)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $serializer = $objectManager->create(\Magento\Framework\Serialize\SerializerInterface::class);
            return $serializer->unserialize($data);
        } else if (class_exists(\Magento\Framework\Unserialize\Unserialize::class)) {
            $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
            $serializer = $objectManager->create(\Magento\Framework\Unserialize\Unserialize::class);
            return $serializer->unserialize($data);
        }
    }

}