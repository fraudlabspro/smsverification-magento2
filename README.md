# FraudLabs Pro SMS Verification (Extension for Magento2)
FraudLabs Pro SMS Verification is an extension that help merchants to authenticate the client’s identity by sending them a SMS for verification.

## Easy to setup

The setup is simple and only takes a few minutes. You just need to install the free FraudLabs Pro SMS Verification plugin, enter the API key and configure the settings.

## More Information

Sign up for a Free license key at https://www.fraudlabspro.com/sign-up. You will have free 10 SMS credits for you to start using the SMS verification.


# Installation

## Install Manually

1.  Download the FraudLabs Pro SMS Verification plugin from the FraudLabs Pro GitHub site at https://github.com/fraudlabspro/smsverification-magento2.
2.  Create a folder and name as Hexasoft.
3.  Unzip the file that downloaded from FraudLabs Pro GitHub site, rename it to FraudLabsProSmsVerification and transfer it into Hexasoft folder.
4.  Upload the Hexasoft folder to the subdirectory of Magento installation root directory as: magento2/app/code/
5.  Login to the Magento admin page and disable the cache under the System -> Cache Management page. 
6.  At the Linux server command line enter the following command in the Magento root directory: php bin/magento setup:upgrade

## Install via Composer

1.  At the Linux server command line enter the following command in Magento root directory: composer require hexasoft/module-fraudlabsprosmsverification
2.  Next continue by entering: composer update
3.  Then follow by: php bin/magento setup:upgrade

For more detail information please refer https://www.fraudlabspro.com/tutorials/how-to-install-fraudlabs-pro-sms-verification-plugin-on-magento2.
