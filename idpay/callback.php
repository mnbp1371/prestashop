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
@session_start();
if (isset($_GET['do'])) {
    include(dirname(__FILE__) . '/../../config/config.inc.php');
    include(dirname(__FILE__) . '/../../header.php');
    include_once(dirname(__FILE__) . '/idpay.php');
    $idpay = new idpay;
    if ($_GET['do'] == 'payment') {
        $idpay->do_payment($cart);
    } else if (!empty($_POST['id']) && !empty($_POST['order_id']) && !empty($_POST['amount']) && !empty($_POST['status']) ) {

            $pid = $_POST['id'];
            $orderid = $_POST['order_id'];
            $amount = $cart->getOrderTotal();
            if (Configuration::get('idpay_currency') == "toman") {
                $amount *= 10;
            }
            if (!empty($pid) && !empty($orderid) && md5($amount . $orderid . Configuration::get('idpay_HASH_KEY')) == $_GET['hash'])
            {
                if($_POST['status'] == 10) {
                    $api_key = Configuration::get( 'idpay_api_key' );
                    $sandbox = Configuration::get( 'idpay_sandbox' ) == 'yes' ? 'true' : 'false';

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

                    $result      = curl_exec( $ch );
                    $result      = json_decode( $result );
                    $http_status = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
                    curl_close( $ch );

                    if ( $http_status != 200 )
                    {
                        echo $idpay->error( sprintf( 'خطا هنگام بررسی وضعیت تراکنش. وضعیت خطا: %s - کد خطا: %s - پیام خطا: %s', $http_status, $result->error_code, $result->error_message ) );
                    }
                    else
                    {
                        $verify_status   = empty( $result->status ) ? NULL : $result->status;
                        $verify_track_id = empty( $result->track_id ) ? NULL : $result->track_id;
                        $verify_order_id = empty( $result->order_id ) ? NULL : $result->order_id;
                        $verify_amount   = empty( $result->amount ) ? NULL : $result->amount;

                        if ( empty( $verify_status ) || empty( $verify_track_id ) || empty( $verify_amount ) || $verify_status < 100 || $verify_order_id !== $orderid )
                        {
                            echo $idpay->error( idpay_get_failed_message( $verify_track_id, $verify_order_id ) );
                        }
                        else
                        {
                            error_reporting( E_ALL );

                            if ( Configuration::get( 'idpay_currency' ) == "toman" )
                            {
                                $amount /= 10;
                            }

                            $idpay->validateOrder( $verify_order_id, Configuration::get( 'PS_OS_PAYMENT' ), $amount, $idpay->displayName, "سفارش تایید شده / کد رهگیری {$verify_track_id}", array(), $cookie->id_currency );
                            $_SESSION['order' . $verify_order_id] = '';
                            Tools::redirect( 'history.php' );
                        }
                    }
                } else {
                    echo $idpay->error('خطا رخ داده است.');
                }


            } else {
                echo $idpay->error('کاربر از انجام تراکنش منصرف شده است');
            }


    }
    include_once(dirname(__FILE__) . '/../../footer.php');
} else {
    header('Status: 403 Forbidden');
    header('HTTP/1.1 403 Forbidden');
    exit();
}

function idpay_get_failed_message($track_id, $order_id)
{
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], Configuration::get('idpay_failed_massage'));
}

function idpay_get_success_massage($track_id, $order_id)
{
    return str_replace(["{track_id}", "{order_id}"], [$track_id, $order_id], Configuration::get('idpay_success_massage'));
}