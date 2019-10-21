<?php
namespace Hexasoft\FraudLabsProSmsVerification\Block;
class Fraudlabsprosmsverificationverify extends \Magento\Framework\View\Element\Template
{

    public function getConfig()
    {
        return $this->_scopeConfig;
    }

    public function methodBlock()
    {
        $apiKey = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/api_key')) ? $this->getConfig()->getValue('fraudlabsprosmsverification/active_display/api_key') : 'API Key cannot be empty.';
        if ($apiKey == 'Phone number cannot be empty.') return 'API Key cannot be empty.';
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
            return $data->error;
        }
        else {
            return 'FLPOK';
        }
    }

}