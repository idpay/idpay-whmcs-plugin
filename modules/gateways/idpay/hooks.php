<?php
use WHMCS\Database\Capsule;

add_hook('ShoppingCartCheckoutCompletePage', 1, function($vars) {
    $transaction = Capsule::table('tblaccounts')->where('invoiceid', $vars['invoiceid'])->first();

    if($vars['paymentmethod'] == 'idpay' && $vars['ispaid'] == true && isset($transaction->transid)){
        $gatewayParams = getGatewayVariables('idpay');

        $output = '<div class="col-sm-8 col-sm-offset-2 alert alert-success order-confirmation idpay">';
        $output .= str_replace(["{order_id}", "{track_id}"], [$vars['orderid'], $transaction->transid], $gatewayParams['success_massage']);
        $output .='</div>';
        $output .='<style>.order-confirmation:not(.idpay) {display: none;}</style>';
        return $output;
    }
});
