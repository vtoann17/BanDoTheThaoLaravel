<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <style>
        body {
            font-family: Arial, sans-serif;
            color: #333;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 24px;
        }

        .header {
            background: #1a73e8;
            color: white;
            padding: 20px;
            border-radius: 8px 8px 0 0;
        }

        .body {
            border: 1px solid #e5e7eb;
            padding: 24px;
        }

        .total {
            font-size: 18px;
            font-weight: bold;
            color: #1a73e8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16px;
        }

        th {
            background: #f3f4f6;
            padding: 10px;
            text-align: left;
        }

        td {
            padding: 10px;
            border-bottom: 1px solid #f3f4f6;
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="header">
            <h2>
                @if($order->payment_method === 'momo' || $order->payment_method === 'vnpay')
                    🎉 Thanh toán thành công!
                @else
                    🎉 Đặt hàng thành công!
                @endif
            </h2>
        </div>
        <div class="body">
            <p>Xin chào <strong>{{ $order->user->name }}</strong>,</p>
            <p>Đơn hàng <strong>#{{ $order->id }}</strong> của bạn đã được xác nhận.</p>
            <p>Phương thức thanh toán: <strong>
                @if($order->payment_method === 'momo')
                    Ví MoMo (Đã thanh toán)
                @elseif($order->payment_method === 'vnpay')
                    VNPay (Đã thanh toán)
                @else
                    COD (Thanh toán khi nhận hàng)
                @endif
            </strong></p>

            <table>
                <thead>
                    <tr>
                        <th>Sản phẩm</th>
                        <th>Số lượng</th>
                        <th>Đơn giá</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($order->items as $item)
                        <tr>
                            <td>{{ $item->variant->product->name ?? 'N/A' }} ({{ $item->variant->sku }})</td>
                            <td>{{ $item->quantity }}</td>
                            <td>{{ number_format($item->price, 0, ',', '.') }}đ</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <p style="margin-top: 16px;">
                Tiền hàng:
                <span class="total">{{ number_format($order->total_amount, 0, ',', '.') }}đ</span>
            </p>
            <p>
                Phí vận chuyển:
                <span class="total">{{ number_format($order->shipping_fee ?? 0, 0, ',', '.') }}đ</span>
            </p>
            <p style="border-top: 1px solid #e5e7eb; padding-top: 12px; margin-top: 8px;">
                Tổng thanh toán:
                <span class="total">{{ number_format(($order->total_amount ?? 0) + ($order->shipping_fee ?? 0), 0, ',', '.') }}đ</span>
            </p>
        </div>
    </div>
</body>

</html>