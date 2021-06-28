<?php
namespace Hexasoft\FraudLabsProSmsVerification\Block;
class Fraudlabsprosmsverificationverify extends \Magento\Framework\View\Element\Template
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
        if ($apiKey == 'Phone number cannot be empty.') return 'API Key cannot be empty.';
        $sms_order_id = (filter_input(INPUT_POST, 'sms_order_id')) ? (filter_input(INPUT_POST, 'sms_order_id')) : '';
        $sms_code = (filter_input(INPUT_POST, 'sms_code')) ? (filter_input(INPUT_POST, 'sms_code')) : '';
        $params['format'] = 'json';
        $params['otp'] = (filter_input(INPUT_POST, 'otp')) ? (filter_input(INPUT_POST, 'otp')) : 'OTP cannot be empty.';
        if ($params['otp'] == 'OTP cannot be empty.') return 'OTP cannot be empty.';
        $params['tran_id'] = (filter_input(INPUT_POST, 'tran_id')) ? (filter_input(INPUT_POST, 'tran_id')) : 'Tran ID cannot be empty.';
        if ($params['tran_id'] == 'Tran ID cannot be empty.') return 'Tran ID cannot be empty.';
        $url = 'https://api.fraudlabspro.com/v1/verification/result';

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
            if ($data->error == 'Invalid OTP.') {
                return 'ERROR 601-' . $data->error;
            } else {
                $this->write_debug_log('Error occurred during FraudLabs Pro SMS OTP Verify. ERROR: ' . $data->error);
                return 'ERROR 600-' . $data->error;
            }
        } else {
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
            return 'FLPOK';
        }
    }

}