<?php
/**
 * IDPay payment gateway
 *
 * @developer JMDMahdi, meysamrazmi, vispamir, MimDeveloper.Tv(Mohammad Malek)
 * @publisher IDPay
 * @copyright (C) 2020 IDPay
 * @version  1.1
 * @license http://www.gnu.org/licenses/gpl-2.0.html GPLv2 or later
 *
 * http://idpay.ir
 */

use WHMCS\Database\Capsule;

if (isset($_REQUEST['invoiceId']) && is_numeric($_REQUEST['invoiceId'])) {

    require_once __DIR__ . '/../../init.php';
    require_once __DIR__ . '/../../includes/gatewayfunctions.php';
    require_once __DIR__ . '/../../includes/invoicefunctions.php';

    $gatewayParams = getGatewayVariables('idpay');
    if (!$gatewayParams['type']) die('Module Not Activated');

    $params = ($_SERVER['REQUEST_METHOD'] === 'POST') ? $_POST : $_GET;

    if (!empty($params['order_id']) && !empty($params['status']) && !empty($params['id']) && !empty($params['track_id'])) {

        $invoice_id = $_GET['invoiceId'];
        $order_id = $params['order_id'];
        $status = $params['status'];
        $trans_id = $params['id'];
        $track_id = $params['track_id'];

        $checkInvoice = Capsule::table('tblinvoices')->where('id', $invoice_id)->where('status', 'Unpaid')->first();
        if (!$checkInvoice) die("Invoice Not Found");
        $checkIdpayGateway = checkCbInvoiceID($order_id, $gatewayParams['name']);
        if (!$checkInvoice) die("Invoice Not For IDPAY");
        // Check Correct Order With Transaction & Double Spending
        if (isNotDoubleSpending($checkInvoice, $order_id, $trans_id) == true) {

            if ($status == 10) {
                $api_key = $gatewayParams['api_key'];
                $sandbox = $gatewayParams['sandbox'] == 'on' ? 'true' : 'false';

                $data = array(
                    'id' => $trans_id,
                    'order_id' => $order_id,
                );

                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment/verify');
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'X-API-KEY:' . $api_key,
                    'X-SANDBOX:' . $sandbox,
                ));

                $result_string = curl_exec($ch);
                $result = json_decode($result_string);
                $http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                curl_close($ch);

                if ($http_status != 200) {
                    //Special HTTP Error Message
                    $message = sprintf('خطا هنگام بررسی وضعیت تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s',
                        $http_status, $result->error_code, $result->error_message);

                    logTransaction($gatewayParams['name'],
                        [
                            "GET" => $_GET,
                            "POST" => $_POST,
                            "result" => $message
                        ], 'Failure');

                    //Save And Show Message
                    Capsule::table('tblinvoices')->where('id', $order_id)->update(['notes' => $message]);
                    header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $order_id);
                }

                $verify_status = empty($result->status) ? NULL : $result->status;
                $verify_track_id = empty($result->track_id) ? NULL : $result->track_id;
                $verify_amount = empty($result->amount) ? NULL : $result->amount;

                if (empty($verify_status) || empty($verify_track_id) || empty($verify_amount) || $verify_status < 100) {
                    $message = idpay_get_filled_message($gatewayParams['failed_massage'], $verify_track_id, $order_id);
                    $message .=   '-- وضعیت نهایی :' . 'تراکنش در آیدی پی تایید نشد';
                    logTransaction($gatewayParams['name'],
                        [
                            "GET" => $_GET,
                            "POST" => $_POST,
                            "result" => $message
                        ], 'Failure');
                    //Save And Show Message
                    Capsule::table('tblinvoices')->where('id', $order_id)->update(['notes' => $message]);

                    header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $order_id);
                } else {
                    if (!empty($gatewayParams['Currencies']) && $gatewayParams['Currencies'] == 'Toman') {
                        $amount = $verify_amount / 10;
                    }
                    addInvoicePayment($order_id, $verify_track_id, $amount, 0, $gatewayParams['paymentmethod']);

                    $message = idpay_get_filled_message($gatewayParams['success_massage'], $verify_track_id, $order_id);
                    logTransaction($gatewayParams['name'],
                        [
                            "GET" => $_GET,
                            "POST" => $_POST,
                            "result" => $message,
                            "verify_result" => print_r($result, true),
                        ], 'Success');

                    //Delete Transaction ID in Not Show For User And Replace Correct Note
                    Capsule::table('tblinvoices')->where('id', $order_id)->update(['notes' => $message]);

                    // Redirect
                    header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $order_id);
                }
            } else {
                // Failed MESSAGE
                $message = idpay_get_filled_message($gatewayParams['failed_massage'], $track_id, $order_id);
                // Append IDPAY MESSAGE
                $message .=   '-- وضعیت نهایی :' . " ($status) " . idpay_get_response_message($status);

                Capsule::table('tblinvoices')->where('id', $order_id)->update(['notes' => $message]);
                header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $order_id);
            }
        } else {
            // Failed MESSAGE
            $message = idpay_get_filled_message($gatewayParams['failed_massage'], $track_id, $order_id);
            // Append Custom Double Spend MESSAGE
            $message .=   '-- وضعیت نهایی :' . 'تراکنش مورد سوء استتفاده قرار گرفت ( دوبار خرج کردن)';

            Capsule::table('tblinvoices')->where('id', $order_id)->update(['notes' => $message]);
            header('Location: ' . $gatewayParams['systemurl'] . '/viewinvoice.php?id=' . $order_id);
        }
    } elseif (isset($_SESSION['uid'])) {

        $invoice = Capsule::table('tblinvoices')->where('id', $_REQUEST['invoiceId'])->where('status', 'Unpaid')->where('userid', $_SESSION['uid'])->first();
        if (!$invoice) die("Invoice not found");

        $user = Capsule::table('tblclients')->where('id', $_SESSION['uid'])->first();
        $api_key = $gatewayParams['api_key'];
        $sandbox = $gatewayParams['sandbox'] == 'on' ? 'true' : 'false';
        $amount = ceil($invoice->total * ($gatewayParams['Currencies'] == 'Toman' ? 10 : 1));

        /* Remove Added Slash In Version 7 Or Above */
        $systemurl = rtrim($gatewayParams['systemurl'], '/') . '/';

        $data = array(
            'order_id' => $invoice->id,
            'amount' => $amount,
            'name' => $user->firstname . ' ' . $user->lastname,
            'phone' => $user->phonenumber,
            'mail' => $user->email,
            'desc' => sprintf('پرداخت فاکتور #%s', $invoice->id),
            'callback' => $systemurl . 'modules/gateways/idpay.php?invoiceId=' . $invoice->id . '&callback=1',
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
            $output = sprintf('<p>خطا هنگام ایجاد تراکنش. وضعیت خطا: %s</p>', $http_status);
            $output .= sprintf('<p style="unicode-bidi: plaintext;">پیام خطا: %s</p>', $result->error_message);
            $output .= sprintf('<p>کد خطا: %s </p>', $result->error_code);
            echo $output;
        } else {
            // Save TRNSACTION ID TO DATABASE
            $is_Updated = Capsule::table('tblinvoices')->where('id', $invoice->id)->update(['notes' => (string)$result->id]);
            if ($is_Updated == 1) header('Location: ' . $result->link);
            if ($is_Updated == 0) die('DataBase Server Not Availible For Update Rows');
        }
    }
    return;
}

function isNotDoubleSpending($reference, $order_id, $transaction_id)
{
    $relatedTransaction = $reference->notes;
    if ($reference->id == $order_id && !empty($relatedTransaction)) {
        return $transaction_id == $relatedTransaction;
    }
    return false;
}

function idpay_MetaData()
{
    return array(
        'DisplayName' => 'ماژول پرداخت آیدی پی',
        'APIVersion' => '1.1',
    );
}

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
            "Value" => "پرداخت شماره {order_id} ناموفق بوده است. شماره پیگیری : {track_id}",
            "Description" => "متن پیامی که می خواهید بعد از پرداخت ناموفق به کاربر نمایش دهید را وارد کنید. همچنین می توانید از شورت کدهای {order_id} برای نمایش شماره سفارش و {track_id} برای نمایش کد رهگیری آیدی پی استفاده نمایید."
        ]
    ];
}

function idpay_link($params)
{
    $htmlOutput = '<form method="GET" action="modules/gateways/idpay.php">';
    $htmlOutput .= '<input type="hidden" name="invoiceId" value="' . $params['invoiceid'] . '">';
    $htmlOutput .= '<input type="submit" value="' . $params['langpaynow'] . '" />';
    $htmlOutput .= '</form>';
    return $htmlOutput;
}

function idpay_get_filled_message($massage, $track_id, $order_id)
{
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $massage);
}

function idpay_get_response_message($massage_id)
{
    switch ($massage_id) {
        case 1:
            return 'پرداخت انجام نشده است';
            break;
        case 2:
            return 'پرداخت ناموفق بوده است';
            break;
        case 3:
            return 'خطا رخ داده است';
            break;
        case 4:
            return 'بلوکه شده';
            break;
        case 5:
            return 'برگشت به پرداخت کننده';
            break;
        case 6:
            return 'برگشت خورده سیستمی';
            break;
        case 7:
            return 'انصراف از پرداخت';
            break;
        case 8:
            return 'به درگاه پرداخت منتقل شد';
            break;
        case 100:
            return 'پرداخت تایید شده است';
            break;
        case 101:
            return 'پرداخت قبلا تایید شده است';
            break;
        case 200:
            return 'به دریافت کننده واریز شد';
            break;
        default:
            return '';
    }
}

/* Un USED */
function post_to_zibal($url, $data = false)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "https://gateway.zibal.ir/v1/" . $url);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type:application/json; charset=utf-8'));
    curl_setopt($ch, CURLOPT_POST, 1);
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
    $result = curl_exec($ch);
    curl_close($ch);
    return !empty($result) ? json_decode($result) : false;
}

