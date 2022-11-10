=== IDPay Payment Gateway for WHMCS ===
Contributors: jmdmahdi, vispa, meysamrazmi, MimDeveloper.Tv
Tags: WHMCS , whmcs, payment, idpay, gateway, آیدی پی
Stable tag: 1.1.0
Tested up to: 8.5.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

[IDPay](https://idpay.ir) payment method for [Settings > Payments].

== Description ==

[IDPay](https://idpay.ir) is one of the Financial Technology providers in Iran.

IDPay provides some payment services and this plugin enables the IDPay's payment gateway for WHMCS.

== Installation ==

After creating a web service on https://idpay.ir and getting an API Key, follow this instruction:

1. Copy idpay.php to Modules/Gateways Folder.
2. Go To WHMCS Admin Panel > Payment Gateways.
3. Enable IDPay payment gateway.
4. Go to Manage.
5. Enter the API Key.

If you need to use this plugin in Test mode, check the "Sandbox".

Also there is a complete documentation [here](https://blog.idpay.ir/helps/) which helps you to install the plugin step by step.

Thank you so much for using IDPay Payment Gateway.

== Changelog ==

== 1.1.0, June 18, 2022 ==
* First Official Release
* Tested Up With Whmcs 8.5.1
* Check Double Spending Correct
* Check Does Not Xss Attack Correct
* Fix Show Transaction ( Index - Show )
* Fix String Decoder Errors
* Some Improvement
