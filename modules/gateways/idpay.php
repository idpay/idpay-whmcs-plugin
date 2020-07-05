<?php
/**
 * IDPay payment gateway
 *
 * @developer JMDMahdi
 * @publisher IDPay
 * @copyright (C) 2018 IDPay
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://idpay.ir
 */

if (!defined("WHMCS")) die("This file cannot be accessed directly");

function idpay_config()
{
    return [
        "FriendlyName" => [
            "Type" => "System",
            "Value" => "IDPay"
        ],
        "Currencies" => [
            "FriendlyName" => "واحد پولی",
            "Type" => "dropdown",
            "Options" => "Rial,Toman"
        ],
        "api_key" => [
            "FriendlyName" => "API KEY",
            "Type" => "text"
        ],
        "sandbox" => [
            "FriendlyName" => "آزمایشگاه",
            "Type" => "yesno"
        ],
        "success_massage" => [
            "FriendlyName" => "پیام پرداخت موفق",
            "Type" => "textarea",
            "Value" => "پرداخت شما با موفقیت انجام شد. کد رهگیری: {track_id}",
            "Description" => "متن پیامی که می خواهید بعد از پرداخت موفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری آیدی پی استفاده نمایید."
        ],
        "failed_massage" => [
            "FriendlyName" => "پیام پرداخت ناموفق",
            "Type" => "textarea",
            "Value" => "پرداخت شما ناموفق بوده است. لطفا مجددا تلاش نمایید یا در صورت بروز اشکال با مدیر سایت تماس بگیرید.",
            "Description" => "متن پیامی که می خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری آیدی پی استفاده نمایید."
        ]
    ];
}

function idpay_link($params)
{
    $systemurl = $params['systemurl'];
    $api_key = $params['api_key'];
    $sandbox = $params['sandbox'] == 'on' ? 'true' : 'false';
    $amount = intval($params['amount']);
    $moduleName = $params['paymentmethod'];
    if (!empty($params['Currencies']) && $params['Currencies'] == "Toman") {
        $amount *= 10;
    }

    // Customer information
    $client = $params['clientdetails'];
    $name = $client['firstname'] . ' ' .  $client['lastname'];
    $mail = $client['email'];
    $phone = $client['phonenumber'];

    $desc = $params["description"];

    // Remove any trailing slashes and then add a new one.
    // WHMCS version 7 contains a trailing slash but version 6
    // does not contain any one. We remove and then add a new trailing slash for
    // the compatibility of the two versions.
    $systemurl = rtrim($systemurl, '/') . '/';

    $callback = $systemurl . 'modules/gateways/callback/' . $moduleName . '.php';

    if (empty($amount)) {
        return 'واحد پول انتخاب شده پشتیبانی نمی شود.';
    }

    $data = array(
        'order_id' => $params['invoiceid'],
        'amount' => $amount,
        'name' => $name,
        'phone' => $phone,
        'mail' => $mail,
        'desc' => $desc,
        'callback' => $callback,
    );

    $ch = curl_init('https://api.idpay.ir/v1.1/payment');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json',
        'X-API-KEY:' . $api_key,
        'X-SANDBOX:' . $sandbox,
    ));

    $result = curl_exec($ch);
    $result = json_decode($result);
    $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_status != 201 || empty($result) || empty($result->id) || empty($result->link)) {
        return sprintf('<span style="unicode-bidi: plaintext;">خطا هنگام ایجاد تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s</span>', $http_status, $result->error_code, $result->error_message);
    } else {
        $logo_link = $systemurl . 'modules/gateways/idpay/logo.svg';
        $output = '<form method="get" action="' . $result->link . '">
            <button type="submit" name="pay" value="پرداخت" style="direction: rtl;"><img src="' . $logo_link . '" width="70px">پرداخت امن با آیدی پی</button>
            <p style="margin-top: 10px;">پرداخت امن به وسیله کلیه کارتهای عضو شتاب با درگاه پرداخت آیدی پی</p>
        </form>';
        return $output;
    }
}