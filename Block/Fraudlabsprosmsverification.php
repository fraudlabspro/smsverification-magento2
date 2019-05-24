<?php
namespace Hexasoft\FraudLabsProSmsVerification\Block;
class Fraudlabsprosmsverification extends \Magento\Framework\View\Element\Template
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
        if($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/active')){
            $smsInstruction = ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_instruction')) ? ($this->getConfig()->getValue('fraudlabsprosmsverification/active_display/sms_instruction')) : 'You are required to verify your phone number using SMS verification. Please make sure you enter the complete phone number (including the country code) and click on the below button to request for an OTP (One Time Password) SMS.';

            if (substr($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), -1) == '/') {
                $siteUrl = substr($this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB), 0, -1);
            } else {
                $siteUrl = $this->_storeManager->getStore()->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);
            }

            $sms_order_id = (filter_input(INPUT_GET, 'orderid')) ? (filter_input(INPUT_GET, 'orderid')) : '';
            $sms_code = (filter_input(INPUT_GET, 'code')) ? (filter_input(INPUT_GET, 'code')) : '';
            $phone = (filter_input(INPUT_GET, 'phone')) ? (filter_input(INPUT_GET, 'phone')) : '';
            $response = '';
            $errors = [];

            if ( $sms_order_id != '' ) {
                if ( !empty( $sms_code ) ) {
                    $order = $this->getOrder($sms_order_id);
                    $flpdata = unserialize($order->getfraudlabspro_response());
                    if ( ($flpdata['fraudlabspro_sms_email_code'] == $sms_code . '_VERIFIED') && ($phone != '') && ($flpdata['fraudlabspro_sms_email_sms'] == '') ) {

                        $flpdata['fraudlabspro_sms_email_phone'] = $phone;
                        $flpdata['fraudlabspro_sms_email_sms'] = 'VERIFIED';
                        $flpdata['is_phone_verified'] = $phone . ' verified';
                        $order->setfraudlabspro_response(serialize($flpdata))->save();

                        return '
                        <div>
                            Your phone has been successfully verified. Thank you.
                        </div>';
                    } elseif ( $flpdata['fraudlabspro_sms_email_code'] == $sms_code ) {
                        return '
<script src="//ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script>
  jQuery(document).ready(function(){
    jQuery("#sms_otp2").bind("keypress", function(e) {
        var code = e.keyCode || e.which;
        if (code == 13) {
            e.preventDefault();
        }
    });

    jQuery("#get_otp").click(function(e) {
        if (jQuery("#phone_number").val() == "") {
            alert("Please enter a valid phone number.");
            jQuery("#phone_number").focus();
        }else if (!confirm("Send OTP to " + jQuery("#phone_number").val() + "?")) {
            e.preventDefault();
        } else {
            doOTP();
        }
    });

    jQuery("#resend_otp").click(function() {
        if(typeof(Storage) !== "undefined") {
            if (sessionStorage.resent_count) {
                sessionStorage.resent_count = Number(sessionStorage.resent_count)+1;
            } else {
                sessionStorage.resent_count = 1;
            }

            if(sessionStorage.resent_count == 3){
                alert("Maximum number of retries to send verification SMS exceeded. Please wait for your OTP code.");
                jQuery("#get_otp").hide();
                jQuery("#resend_otp").hide();
            } else if (!confirm("Send OTP to " + jQuery("#phone_number").val() + "?")) {
                e.preventDefault();
            } else {
                doOTP();
            }
        }
    });

    jQuery("#submit_otp").click(function() {
        checkOTP();
    });

    if(sessionStorage.resent_count >= 3){
        alert("Maximum number of retries to send verification SMS exceeded. Please wait for your OTP code.");
        jQuery("#get_otp").hide();
        jQuery("#resend_otp").hide();
    }

    if (jQuery.trim(jQuery("#sms_verified").val()) == "YES" || sessionStorage.sms_vrf == "YES") {
        jQuery(".btn-checkout").prop("disabled",false);
    } else if (jQuery.trim(jQuery("#sms_verified").val()) == "") {
        jQuery(".btn-checkout").prop("disabled",true);
        /* alert("Please complete the SMS Verification.");
        jQuery("#sms_otp2").focus();
        document.getElementById("verifysms").scrollIntoView(true);*/
    }

    function doOTP() {
        var data = {
            "tel": jQuery("#phone_number").val(),
            "sms_order_id": jQuery("#sms_order_id").val(),
            "sms_code": jQuery("#sms_code").val()
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
        if (data.indexOf("OK") != 0) {
            alert(data + "\nError: Unable to send the SMS verification message to " + jQuery("#phone_number").val() + ".");
        }
        else if (data.indexOf("OK") == 0) {
            alert("A verification SMS has been sent to " + jQuery("#phone_number").val() + ".");
            jQuery("#sms_tran_id").val(data.substr(2,20));
            jQuery("#get_otp").hide();
            jQuery("#resend_otp").show();
            jQuery("#submit_otp").show();
            jQuery("#enter_sms_otp").show();
            jQuery("#sms_otp1").val(data.substr(22));
            jQuery("#phone_number").prop("disabled", true);
            jQuery("#sms_otp1").prop("disabled", true);
        }
    }

    function sms_doOTP_error() {
        alert("Error: Unable to send the SMS verification message to " + jQuery("#phone_number").val() + ".");
    }

    function checkOTP() {
        var data = {
            "otp": jQuery("#sms_otp1").val() + "-" + jQuery("#sms_otp2").val(),
            "tran_id": jQuery("#sms_tran_id").val()
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
        if (data.indexOf("OK") != 0) {
            alert("Error: " + data );
        }
        else if (data.indexOf("OK") == 0) {
            jQuery("#sms_verified").val("YES");
            jQuery(".btn-checkout").prop("disabled",false);
            if(typeof(Storage) !== "undefined") {
                sessionStorage.sms_vrf = "YES";
                sessionStorage.resent_count = 0;
            }
            jQuery("#enter_sms_otp").hide();
            jQuery("#submit_otp").hide();
            jQuery("#get_otp").hide();
            jQuery("#resend_otp").hide();
            jQuery("#sms_box").hide();
            jQuery("#sms_success_status").show();
            // redirect the page to get phone number
            var url = window.location.href + "&phone=" + jQuery("#phone_number").val();
            window.location.href = url;
        }
    }

    function sms_checkOTP_error() {
        alert("Error: Could not perform sms verification.");
    }

    if(sessionStorage.sms_vrf == "YES") {
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
  <label for="phone_number" id="enter_phone_number">
    ' . $smsInstruction . '    <br /><br />
    Phone Number with country code<br /><input type="text" class="page-width" name="phone_number" id="phone_number" value="" placeholder="Enter phone number.">
  </label>
  <br />
  <label for="sms_otp" id="enter_sms_otp" style="margin-bottom: 10px; display: none;">
     OTP<br /><input type="text" name="sms_otp1" id="sms_otp1" value="" placeholder="Enter OTP characters" style="width:180px;">-<input type="text" name="sms_otp2" id="sms_otp2" value="" placeholder="Enter OTP numbers" style="width:180px;">
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
</div>
<div id="sms_success_status" class="page-width" style="text-align: center; border: 1px solid silver; padding: 10px; background-color: #22B14C; color: white; display: none;"><span>Your phone has been successfully verified.</span></div>
<br /><br />';
                    } else {
                        $errors[] = 'Invalid verification code. Verification failed.';
                    }
                } else {
                    $errors[] = 'Verification code cannot be empty. Verification failed.';
                }

                if (!empty($errors)) {
                    foreach ($errors as $error) {
                        $response .= '
                        <div>
                            <span>' . $error . '</span>
                        </div>';
                    }
                    return $response;
                }
            } else {
                return '
                    <div>
                        <span>Input data cannot be empty. Verification failed.</span>
                    </div>';
            }

        } else {
            return '';
        }
    }

}