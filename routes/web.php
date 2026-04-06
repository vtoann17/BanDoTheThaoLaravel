<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentController;

Route::get('/', function () {
    return view('welcome');
});

// ✅ Thêm route debug vào đây
Route::get('/vnpay/test', function() {
    $tmnCode    = 'TSB36WHU';
    $hashSecret = 'NPAE0HPN62PYHR0WJ36R51EW4CBKZSDJ';
    $returnUrl  = 'https://cornucopiate-sherwood-bidentate.ngrok-free.dev/payment/vnpay/return';
    
    $now = \Carbon\Carbon::now('Asia/Ho_Chi_Minh');

    $params = [
        'vnp_Version'    => '2.1.0',
        'vnp_Command'    => 'pay',
        'vnp_TmnCode'    => $tmnCode,
        'vnp_Amount'     => 10000 * 100,
        'vnp_CurrCode'   => 'VND',
        'vnp_TxnRef'     => '123_' . $now->timestamp,
        'vnp_OrderInfo'  => 'Thanh toan don hang 123',
        'vnp_OrderType'  => 'other',
        'vnp_Locale'     => 'vn',
        'vnp_ReturnUrl'  => $returnUrl,
        'vnp_IpAddr'     => '113.161.1.1',
        'vnp_CreateDate' => $now->format('YmdHis'),
        'vnp_ExpireDate' => $now->copy()->addMinutes(15)->format('YmdHis'),
    ];

    ksort($params);

    $hashData = '';
    $query    = '';
    $i = 0;
    foreach ($params as $key => $value) {
        $part = $key . '=' . urlencode($value);
        $hashData .= ($i == 0 ? '' : '&') . $part;
        $query    .= $part . '&';
        $i++;
    }

    $secureHash = hash_hmac('sha512', $hashData, $hashSecret);
    $url = 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?' . $query . 'vnp_SecureHash=' . $secureHash;

    dd([
        'hashData' => $hashData,
        'hash'     => $secureHash,
        'url'      => $url,
    ]);
});

Route::get('/payment/vnpay/return', [PaymentController::class, 'return'])
    ->name('payment.vnpay.return');

Route::post('/payment/vnpay/ipn', [PaymentController::class, 'ipn'])
    ->name('payment.vnpay.ipn')
    ->withoutMiddleware(['web']);