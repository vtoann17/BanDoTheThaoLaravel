<?php

namespace App\Services;

use Carbon\Carbon;

class VNPayService
{
    protected string $tmnCode;
    protected string $hashSecret;
    protected string $url;
    protected string $returnUrl;

    public function __construct()
    {
        $this->tmnCode = config('services.vnpay.tmn_code');
        $this->hashSecret = config('services.vnpay.hash_secret');
        $this->url = config('services.vnpay.url');
        $this->returnUrl = config('services.vnpay.return_url');
    }

    public function createPaymentUrl(int $orderId, int $amount, string $ipAddr): string
    {
        $now = Carbon::now('Asia/Ho_Chi_Minh');


        $inputData = [
            'vnp_Version' => '2.1.0',
            'vnp_TmnCode' => $this->tmnCode,
            'vnp_Amount' => $amount * 100,
            'vnp_Command' => 'pay',
            'vnp_CreateDate' => $now->format('YmdHis'),
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $this->getClientIp(),
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => 'Thanh toan don hang ' . $orderId,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $this->returnUrl,
            'vnp_TxnRef' => $orderId . '_' . $now->timestamp,
            'vnp_ExpireDate' => $now->copy()->addMinutes(15)->format('YmdHis'),
        ];

        ksort($inputData);

        $query = '';
        $hashdata = '';

        foreach ($inputData as $key => $value) {
            $query .= urlencode($key) . '=' . urlencode($value) . '&';
            $hashdata .= urlencode($key) . '=' . urlencode($value) . '&';
        }

        // bỏ dấu & cuối
        $query = rtrim($query, '&');
        $hashdata = rtrim($hashdata, '&');

        $secureHash = hash_hmac('sha512', $hashdata, $this->hashSecret);
        \Log::info('VNPAY DEBUG', [
            'inputData' => $inputData,
            'hashdata' => $hashdata,
            'secureHash' => $secureHash,
            'url' => $this->url . '?' . $query . '&vnp_SecureHash=' . $secureHash,
        ]);

        return $this->url . '?' . $query . '&vnp_SecureHash=' . $secureHash;
    }

    public function verifyReturn(array $params): bool
    {
        $secureHash = $params['vnp_SecureHash'] ?? '';

        $inputData = [];

        foreach ($params as $key => $value) {
            if (
                str_starts_with($key, 'vnp_') &&
                $key !== 'vnp_SecureHash' &&
                $key !== 'vnp_SecureHashType'
            ) {
                $inputData[$key] = $value;
            }
        }

        ksort($inputData);

        $hashdata = '';

        foreach ($inputData as $key => $value) {
            $hashdata .= urlencode($key) . '=' . urlencode($value) . '&';
        }

        $hashdata = rtrim($hashdata, '&');

        $calcHash = hash_hmac('sha512', $hashdata, $this->hashSecret);

        return hash_equals($calcHash, $secureHash);
    }
    private function getClientIp(): string
    {
        $ip = request()->ip();
        if ($ip === '127.0.0.1' || $ip === '::1') {
            return '113.161.1.1';
        }

        return $ip;
    }
}