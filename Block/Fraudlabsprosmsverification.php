<?php
namespace Hexasoft\FraudLabsProSmsVerification\Block;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\Escaper;

class Fraudlabsprosmsverification extends \Magento\Framework\View\Element\Template
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
        if ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/active')) {
            $smsInstruction = $this->escaper->escapeHtml(($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_instruction')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_instruction')) : 'You are required to verify your phone number using SMS verification. Please make sure you enter the complete phone number (including the country code) and click on the below button to request for an OTP (One Time Password) SMS.');
            $smsDefaultCc = $this->escaper->escapeHtml(($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_default_cc')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_default_cc')) : 'US');

            $siteUrl = $this->escaper->escapeUrl($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB));
            if (substr($siteUrl, -1) == '/') {
                $siteUrl = substr($siteUrl, 0, -1);
            }

            $sms_order_id = $this->escaper->escapeHtml((filter_input(INPUT_GET, 'id')) ? (filter_input(INPUT_GET, 'id')) : '');
            $sms_code = $this->escaper->escapeHtml((filter_input(INPUT_GET, 'code')) ? (filter_input(INPUT_GET, 'code')) : '');
            $phone = $this->escaper->escapeHtml((filter_input(INPUT_GET, 'phone')) ? (filter_input(INPUT_GET, 'phone')) : '');
            $response = '';
            $errors = [];

            if ( $sms_order_id != '' ) {
                if ( !empty( $sms_code ) ) {
                    try {
                        $order = $this->getOrder($sms_order_id);
                    } catch(\Exception $e) {
                        return '<div><span>' . $this->escaper->escapeHtml('Order not found. Verification failed.') . '</span></div>';
                    }
                    $loggedInCustomerId = $this->customerSession->getCustomerId();
                    if ($order->getCustomerId() && $order->getCustomerId() != $loggedInCustomerId) {
                        return '<div><span>' . $this->escaper->escapeHtml('Access Denied. Authorization failed.') . '</span></div>';
                    }
                    $flpdata = [];
                    if ($order->getfraudlabspro_response() !== null) {
                        $flpdata = json_decode($order->getfraudlabspro_response(), true);
                    }
                    if ( !empty( $flpdata['fraudlabspro_sms_email_code'] ) ) {
                        $msgVrfComplete = $this->escaper->escapeHtml(($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_vrf_complete')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_vrf_complete')) : 'Thank you. You have successfully completed the SMS verification.');
                        if ( ($flpdata['fraudlabspro_sms_email_code'] == $sms_code . '_VERIFIED') && ($phone != '') && ($flpdata['fraudlabspro_sms_email_sms'] == '') ) {

                            $flpdata['fraudlabspro_sms_email_phone'] = $phone;
                            $flpdata['fraudlabspro_sms_email_sms'] = 'VERIFIED';
                            $flpdata['is_phone_verified'] = $phone . ' verified';
                            $order->setfraudlabspro_response(json_encode($flpdata))->save();

                            return '<div>' . $msgVrfComplete . '</div>';
                        } elseif ( $flpdata['fraudlabspro_sms_email_sms'] == 'VERIFIED' ) {
                            return '<div>' . $msgVrfComplete . '</div>';
                        } elseif ( $flpdata['fraudlabspro_sms_email_code'] == $sms_code ) {
                            $msgOtpSuccess = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_otp_success')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_otp_success')) : 'A SMS containing the OTP (One Time Passcode) has been sent to {phone}. Please enter the 6 digits OTP value to complete the verification.';
                            if (strpos($msgOtpSuccess, '{phone}') === false) {
                                $msgOtpSuccess = 'A SMS containing the OTP (One Time Passcode) has been sent to {phone}. Please enter the 6 digits OTP value to complete the verification.';
                            }
                            $msgOtpSuccess = explode("{phone}", $msgOtpSuccess);
                            $msgOtpFail = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_otp_fail')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_otp_fail')) : 'Error: Unable to send the SMS verification message to {phone}.';
                            if (strpos($msgOtpFail, '{phone}') === false) {
                                $msgOtpFail = 'Error: Unable to send the SMS verification message to {phone}.';
                            }
                            $msgOtpFail = explode("{phone}", $msgOtpFail);
                            $msgInvalidPhone = $this->escaper->escapeJs(($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_invalid_phone')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_invalid_phone')) : 'Please enter a valid phone number.');
                            $msgInvalidOtp = $this->escaper->escapeJs(($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_invalid_otp')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/msg_invalid_otp')) : 'Error: Invalid OTP. Please enter the correct OTP.');
                            return '
    <script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="//cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.5/js/intlTelInput.min.js"></script>
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.5/css/intlTelInput.min.css">
    <script language="Javascript">
      var phoneNum, defaultCc;
      jQuery( document ).ready(function() {
        if ( jQuery("#sms_phone_cc").length ) {
            defaultCc = jQuery("#sms_phone_cc").val();
        } else {
            defaultCc = "US";
        }
        phoneNum = window.intlTelInput(document.querySelector("#phone_number"), {
            utilsScript: "https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.5/js/utils.min.js",
            separateDialCode: true,
            initialCountry: defaultCc
        });
      });

      jQuery(document).ready(function(){
        jQuery("#sms_otp2").bind("keypress", function(e) {
            var code = e.keyCode || e.which;
            if (code == 13) {
                e.preventDefault();
            }
        });

        jQuery("#get_otp").click(function(e) {
            if (jQuery("#phone_number").val() == "") {
                jQuery("#sms_err").html("' . $msgInvalidPhone . '");
                jQuery("#sms_err").show();
                jQuery("#phone_number").focus();
            } else if (!confirm("Send OTP to " + phoneNum.getNumber() + "?")) {
                e.preventDefault();
            } else {
                doOTP();
            }
        });

        jQuery("#resend_otp").click(function() {
            if (typeof(Storage) !== "undefined") {
                if (sessionStorage.resent_count) {
                    sessionStorage.resent_count = Number(sessionStorage.resent_count)+1;
                } else {
                    sessionStorage.resent_count = 1;
                }

                if (sessionStorage.resent_count == 3) {
                    jQuery("#sms_err").html("Maximum number of retries to send verification SMS exceeded. Please wait for your OTP code.");
                    jQuery("#sms_err").show();
                    jQuery("#get_otp").hide();
                    jQuery("#resend_otp").hide();
                } else if (!confirm("Send OTP to " + phoneNum.getNumber() + "?")) {
                    e.preventDefault();
                } else {
                    doOTP();
                }
            }
        });

        jQuery("#submit_otp").click(function() {
            checkOTP();
        });

        if (sessionStorage.resent_count >= 3) {
            jQuery("#sms_err").html("Maximum number of retries to send verification SMS exceeded. Please wait for your OTP code.");
            jQuery("#sms_err").show();
            jQuery("#get_otp").hide();
            jQuery("#resend_otp").hide();
        }

        if (jQuery.trim(jQuery("#sms_verified").val()) == "YES" || sessionStorage.sms_vrf == "YES") {
            jQuery(".btn-checkout").prop("disabled",false);
        } else if (jQuery.trim(jQuery("#sms_verified").val()) == "") {
            jQuery(".btn-checkout").prop("disabled",true);
            jQuery("#sms_otp2").focus();
            document.getElementById("verifysms").scrollIntoView(true);
        }

        function doOTP() {
            var data = {
                "tel": phoneNum.getNumber(),
                "tel_cc": phoneNum.getSelectedCountryData().iso2.toUpperCase(),
                "sms_order_id": jQuery("#sms_order_id").val()
            };
            jQuery.ajax({
                type: "POST",
                url: "' . $siteUrl . '/fraudlabsprosmsverificationsend",
                data: data,
                success: sms_doOTP_success,
                error: sms_doOTP_error,
                dataType: "text"
            });
        }

        function sms_doOTP_success(data) {
            if (data.includes("FLPOK")) {
                var num = data.search("FLPOK");
                alert("' . $msgOtpSuccess[0] . '" + phoneNum.getNumber() + "' . $msgOtpSuccess[1] . '");
                jQuery("#sms_tran_id").val(data.substr(num+5, 20));
                jQuery("#get_otp").hide();
                jQuery("#sms_err").hide();
                jQuery("#resend_otp").show();
                jQuery("#submit_otp").show();
                jQuery("#enter_sms_otp").show();
                jQuery("#sms_otp1").val(data.substr(num+25, 6));
                jQuery("#phone_number").prop("disabled", true);
                jQuery("#sms_otp1").prop("disabled", true);
            } else {
                jQuery("#sms_err").html("' . $msgOtpFail[0] . '" + phoneNum.getNumber() + "' . $msgOtpFail[1] . '");
                jQuery("#sms_err").show();
            }
        }

        function sms_doOTP_error() {
            jQuery("#sms_err").html("' . $msgOtpFail[0] . '" + phoneNum.getNumber() + "' . $msgOtpFail[1] . '");
            jQuery("#sms_err").show();
        }

        function checkOTP() {
            var data = {
                "otp": jQuery("#sms_otp1").val() + "-" + jQuery("#sms_otp2").val(),
                "tran_id": jQuery("#sms_tran_id").val(),
                "sms_order_id": jQuery("#sms_order_id").val(),
                "sms_code": jQuery("#sms_code").val()
            };
            jQuery.ajax({
                type: "POST",
                url: "' . $siteUrl . '/fraudlabsprosmsverificationverify",
                data: data,
                success: sms_checkOTP_success,
                error: sms_checkOTP_error,
                dataType: "text"
            });
        }

        function sms_checkOTP_success(data) {
            if (data.includes("FLPOK")) {
                jQuery("#sms_verified").val("YES");
                jQuery(".btn-checkout").prop("disabled",false);
                if (typeof(Storage) !== "undefined") {
                    sessionStorage.sms_vrf = "YES";
                    sessionStorage.resent_count = 0;
                }
                jQuery("#enter_sms_otp").hide();
                jQuery("#submit_otp").hide();
                jQuery("#get_otp").hide();
                jQuery("#resend_otp").hide();
                jQuery("#sms_err").hide();
                jQuery("#sms_box").hide();
                jQuery("#sms_success_status").show();
                // redirect the page to get phone number
                var url = window.location.href + "&phone=" + phoneNum.getNumber();
                window.location.href = url;
            } else if (data.includes("ERROR 601")) {
                jQuery("#sms_err").html("' . $msgInvalidOtp . '");
                jQuery("#sms_err").show();
            } else {
                jQuery("#sms_err").html("Error: Error while performing verification.");
                jQuery("#sms_err").show();
            }
        }

        function sms_checkOTP_error() {
            jQuery("#sms_err").html("Error: Could not perform sms verification.");
            jQuery("#sms_err").show();
        }

        if (sessionStorage.sms_vrf == "YES") {
            jQuery("#sms_verified").val("YES");
            jQuery(".btn-checkout").prop("disabled",false);
            jQuery("#enter_sms_otp").hide();
            jQuery("#submit_otp").hide();
            jQuery("#get_otp").hide();
            jQuery("#resend_otp").hide();
            jQuery("#sms_box").hide();
            jQuery("#sms_success_status").show();
        }
      });
    </script>

    <br />
    <div id="sms_box" class="page-width" style="font-size: 14px; border: 1px solid silver; padding: 5px;">
      <h1 id="verifysms">SMS Verification<abbr class="required" title="required">*</abbr></h1>
      <div id="sms_err" style="background-color:#f8d7da;color:#7d5880;padding:10px;margin-bottom:20px;font-size:1em;display:none;"></div>
      <label for="phone_number" id="enter_phone_number">
        ' . $smsInstruction . '    <br /><br />
        Phone Number with country code<br /><input type="text" class="page-width" name="phone_number" id="phone_number" value="" placeholder="Enter phone number.">
      </label>
      <br />
      <label for="sms_otp" id="enter_sms_otp" style="margin-bottom: 10px; display: none;">
         OTP<br /><input type="text" name="sms_otp1" id="sms_otp1" value="" placeholder="Enter OTP characters" style="width:180px;">-<input type="text" name="sms_otp2" id="sms_otp2" value="" placeholder="Enter OTP numbers" style="width:180px;"><br />
      </label>
      <br />
      <input type="button" class="action primary" name="submit_otp" id="submit_otp" value="Submit OTP" style="margin-right: 5px; padding: 5px 10px; display: none;">
      <input type="button" class="action primary" name="get_otp" id="get_otp" value="Get OTP" style="margin-right: 5px; padding: 5px 10px;">
      <input type="button" class="action primary" name="resend_otp" id="resend_otp" value="Resend OTP" style="margin-right: 5px; padding: 5px 10px; display: none;">
      <input type="hidden" name="sms_verified" id="sms_verified" value="">
      <input type="hidden" name="sms_tran_id" id="sms_tran_id" value="">
      <input type="hidden" name="sms_ip_addr" id="sms_ip_addr" value="">
      <input type="hidden" name="sms_order_id" id="sms_order_id" value="' . $sms_order_id . '"></div>
      <input type="hidden" name="sms_code" id="sms_code" value="' . $sms_code . '">
      <input type="hidden" name="sms_phone_cc" id="sms_phone_cc" value="' . $smsDefaultCc . '">
    </div>
    <div id="sms_success_status" class="page-width" style="text-align: center; border: 1px solid silver; padding: 10px; background-color: #22B14C; color: white; display: none;"><span>' . $msgVrfComplete . '</span></div>
    <br /><br />';
                        } else {
                            $errors[] = 'Invalid verification code. Verification failed.';
                        }
                    } else {
                        $errors[] = 'Verification code not found. Verification failed.';
                    }
                } else {
                    $errors[] = 'Verification code cannot be empty. Verification failed.';
                }

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $response .= '
                        <div>
                            <span>' . $this->escaper->escapeHtml($error) . '</span>
                        </div>';
                    }
                    return $response;
                }
            } else {
                return '<div><span>' . $this->escaper->escapeHtml('Input data cannot be empty. Verification failed.') . '</span></div>';
            }

        } else {
            return '';
        }
    }

}