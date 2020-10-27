<?php
/**
 * IDPay payment gateway
 *
 * @developer JMDMahdi, meysamrazmi, vispamir
 * @publisher IDPay
 * @copyright (C) 2020 IDPay
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

/**
 * @param $failed_massage
 * @param $track_id
 * @param $order_id
 * @return mixed
 */
function idpay_get_filled_message($massage, $track_id, $order_id)
{
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], $massage);
}

/**
 * @param $success_massage
 * @param $track_id
 * @param $order_id
 * @return mixed
 */
function idpay_get_response_message($massage_id)
{
    switch($massage_id){
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

/**
 *  End IDPay process
 */
function idpay_end()
{
    global $orderid, $CONFIG, $paymentSuccess, $track_id;
    if (isset($orderid) && $orderid) {
        if($paymentSuccess)
            callback3DSecureRedirect($orderid, $paymentSuccess);
        else
            header('Location: ' . $CONFIG['SystemURL'] . '/viewinvoice.php?id='. $orderid .'&paymentfailed=true&track_id='. $track_id);
        exit();
    } else {
        header('Location: ' . $CONFIG['SystemURL'] . '/clientarea.php?action=invoices');
        exit();
    }
}

$paymentSuccess = false;
$orderid = 0;

if(!empty($_POST['order_id']) || !empty($_GET['order_id'])){

    // Check request method
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $orderid = $_POST['order_id'];
        $status = $_POST['status'];
        $pid = $_POST['id'];
        $porder_id = $_POST['order_id'];
    }
    elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $orderid = $_GET['order_id'];
        $status = $_GET['status'];
        $pid = $_GET['id'];
        $porder_id = $_GET['order_id'];
    }

    $orderid = checkCbInvoiceID($orderid, $gatewayParams['name']);
    $status_code = $_POST['status'];


    if (!empty($pid) && !empty($porder_id) && $porder_id == $orderid)
    {
        if ($status == 10) {
            $api_key = $gatewayParams['api_key'];
            $sandbox = $gatewayParams['sandbox'] == 'on' ? 'true' : 'false';

            $data = array(
                'id'       => $pid,
                'order_id' => $orderid,
            );

            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, 'https://api.idpay.ir/v1.1/payment/verify' );
            curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode( $data ) );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, TRUE );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                'Content-Type: application/json',
                'X-API-KEY:' . $api_key,
                'X-SANDBOX:' . $sandbox,
            ) );

            $result_string      = curl_exec( $ch );
            $result      = json_decode( $result_string );
            $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
            curl_close( $ch );

            if ( $http_status != 200 )
            {
                logTransaction( $gatewayParams['name'],
                    [
                        "GET"    => $_GET,
                        "POST"   => $_POST,
                        "result" => sprintf( 'خطا هنگام بررسی وضعیت تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message )
                    ], 'Failure' );
                idpay_end();
            }

            $verify_status   = empty( $result->status ) ? NULL : $result->status;
            $verify_track_id = empty( $result->track_id ) ? NULL : $result->track_id;
            $verify_amount   = empty( $result->amount ) ? NULL : $result->amount;

            checkCbTransID( $verify_track_id );

            if ( empty( $verify_status ) || empty( $verify_track_id ) || empty( $verify_amount ) || $verify_status < 100 )
            {
                logTransaction( $gatewayParams['name'],
                    [
                        "GET"    => $_GET,
                        "POST"   => $_POST,
                        "result" => idpay_get_filled_message( $gatewayParams['failed_massage'], $verify_track_id, $orderid )
                    ], 'Failure' );
            }
            else
            {
                $paymentSuccess = TRUE;
                if ( ! empty( $gatewayParams['Currencies'] ) && $gatewayParams['Currencies'] == 'Toman' )
                {
                    $amount = $verify_amount / 10;
                }
                addInvoicePayment( $orderid, $verify_track_id, $amount, 0, $gatewayParams['paymentmethod'] );
                logTransaction( $gatewayParams['name'],
                    [
                        "GET"    => $_GET,
                        "POST"   => $_POST,
                        "result" => idpay_get_filled_message( $gatewayParams['success_massage'], $verify_track_id, $orderid ),
                        "verify_result" => print_r($result, true),
                    ], 'Success' );
            }
        }
        else
        {
            logTransaction($gatewayParams['name'],
                [
                    "GET" => $_GET,
                    "POST" => $_POST,
                    "result" => sprintf('خطا هنگام بررسی وضعیت تراکنش. کد خطا: %s - پیام خطا: %s', $status_code, idpay_get_response_message($status_code) ),
                    "message" => idpay_get_filled_message( $gatewayParams['failed_massage'], $track_id, $porder_id )
                ], 'Failure');
        }
    }
    else
    {
        logTransaction($gatewayParams['name'],
            [
                "GET" => $_GET,
                "POST" => $_POST,
                "result" => 'کاربر از انجام تراکنش منصرف شده است'
            ], 'Failure');
    }

}

idpay_end();
