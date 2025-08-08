<?php
namespace Hexasoft\FraudLabsProSmsVerification\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Escaper;

class Fraudlabsprosmsverificationverify extends \Magento\Framework\View\Element\Template
{

    protected $orderRepository ;
    protected $customerSession;
    protected $escaper;

    public function __construct(
        \Magento\Framework\View\Element\Template\Context $context,
        \Magento\Sales\Api\OrderRepositoryInterface $orderRepository,
        CustomerSession $customerSession,
        Escaper $escaper,
        array $data = []
    ) {
        $this->orderRepository = $orderRepository;
        $this->customerSession = $customerSession;
        $this->escaper = $escaper;
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
        if ($apiKey == 'Phone number cannot be empty.') {
            return $this->escaper->escapeHtml('API Key cannot be empty.');
        }
        $sms_order_id = (filter_input(INPUT_POST, 'sms_order_id')) ? (filter_input(INPUT_POST, 'sms_order_id')) : '';
        $sms_code = (filter_input(INPUT_POST, 'sms_code')) ? (filter_input(INPUT_POST, 'sms_code')) : '';
        $params['format'] = 'json';
        $params['otp'] = (filter_input(INPUT_POST, 'otp')) ? (filter_input(INPUT_POST, 'otp')) : 'OTP cannot be empty.';
        if ($params['otp'] == 'OTP cannot be empty.') {
            return $this->escaper->escapeHtml('OTP cannot be empty.');
        }
        $params['tran_id'] = (filter_input(INPUT_POST, 'tran_id')) ? (filter_input(INPUT_POST, 'tran_id')) : 'Tran ID cannot be empty.';
        if ($params['tran_id'] == 'Tran ID cannot be empty.') {
            return $this->escaper->escapeHtml('Tran ID cannot be empty.');
        }
        $url = 'https://api.fraudlabspro.com/v2/verification/result';

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

        if (isset($data->error->error_message)) {
            if ($data->error->error_message == 'INVALID OTP') {
                return $this->escaper->escapeHtml('ERROR 601-' . $data->error->error_message);
            } else {
                $this->write_debug_log('Error occurred during FraudLabs Pro SMS OTP Verify. ERROR: ' . $data->error->error_messag);
                return $this->escaper->escapeHtml('ERROR 600-' . $data->error->error_message);
            }
        } else {
            if ( $sms_order_id != "" ) {
                if ( $sms_code != "" ) {
                    $order = $this->getOrder($sms_order_id);
                    $loggedInCustomerId = $this->customerSession->getCustomerId();
                    if ($order->getCustomerId() && $order->getCustomerId() != $loggedInCustomerId) {
                        return $this->escaper->escapeHtml('ERROR 700-Authorization failed.');
                    }
                    if ($order->getfraudlabspro_response()) {
                        if ($order->getfraudlabspro_response() !== null) {
                            $flpdata = json_decode($order->getfraudlabspro_response(), true);
                            if ( $flpdata['fraudlabspro_sms_email_code'] == $sms_code ) {
                                $flpdata['fraudlabspro_sms_email_code'] = $sms_code . '_VERIFIED';
                                $order->setfraudlabspro_response(json_encode($flpdata))->save();
                            }
                        }
                    }
                }
            }
            return $this->escaper->escapeHtml('FLPOK');
        }
    }

}