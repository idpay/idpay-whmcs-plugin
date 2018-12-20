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

require_once __DIR__ . '/../../../init.php';
require_once __DIR__ . '/../../../includes/gatewayfunctions.php';
require_once __DIR__ . '/../../../includes/invoicefunctions.php';

if (!defined("WHMCS")) die();

$gatewayParams = getGatewayVariables('idpay');

if (!$gatewayParams['type']) die('Module Not Activated');

function idpay_get_failed_message($failed_massage, $track_id, $order_id)
{
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $failed_massage);
}

function idpay_get_success_message($success_massage, $track_id, $order_id)
{
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $success_massage);
}

function idpay_end()
{
    global $orderid, $CONFIG, $paymentSuccess;
    if (isset($orderid) && $orderid) {
        callback3DSecureRedirect($orderid, $paymentSuccess);
        exit();
    } else {
        header('Location: ' . $CONFIG['SystemURL'] . '/clientarea.php?action=invoices');
        exit();
    }
}

$paymentSuccess = false;
$orderid = $_POST['order_id'];
$amount = $_POST['amount'];
$orderid = checkCbInvoiceID($orderid, $gatewayParams['name']);

$pid = $_POST['id'];
$porder_id = $_POST['order_id'];

if (!empty($pid) && !empty($porder_id) && $porder_id == $orderid) {
    $api_key = $gatewayParams['api_key'];
    $sandbox = $gatewayParams['sandbox'] == 'on' ? 'true' : 'false';

    $data = array(
        'id' => $pid,
        'order_id' => $orderid,
    );

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://api.idpay.ir/v1/payment/inquiry');
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

    if ($http_status != 200) {
        logTransaction($gatewayParams['name'],
            [
                "GET" => $_GET,
                "POST" => $_POST,
                "result" => sprintf('خطا هنگام بررسی وضعیت تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message)
            ], 'Failure');
        idpay_end();
    }

    $inquiry_status = empty($result->status) ? NULL : $result->status;
    $inquiry_track_id = empty($result->track_id) ? NULL : $result->track_id;
    $inquiry_amount = empty($result->amount) ? NULL : $result->amount;

    checkCbTransID($inquiry_track_id);

    if (empty($inquiry_status) || empty($inquiry_track_id) || empty($inquiry_amount) || $inquiry_amount != $amount || $inquiry_status != 100) {
        logTransaction($gatewayParams['name'],
            [
                "GET" => $_GET,
                "POST" => $_POST,
                "result" => idpay_get_failed_message($gatewayParams['failed_massage'], $inquiry_track_id, $orderid)
            ], 'Failure');
    } else {
        $paymentSuccess = true;
        addInvoicePayment($orderid, $inquiry_track_id, $amount, 0, $gatewaymodule);
        logTransaction($gatewayParams['name'],
            [
                "GET" => $_GET,
                "POST" => $_POST,
                "result" => idpay_get_success_message($gatewayParams['success_massage'], $inquiry_track_id, $orderid)
            ], 'Success');
    }
} else {
    logTransaction($gatewayParams['name'],
        [
            "GET" => $_GET,
            "POST" => $_POST,
            "result" => 'کاربر از انجام تراکنش منصرف شده است'
        ], 'Failure');
}

idpay_end();
