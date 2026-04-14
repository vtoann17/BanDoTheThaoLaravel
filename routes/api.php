<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\VariantController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\VariantValueController;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\BrandsController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\FavouritesController;
use App\Http\Controllers\CouponsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\MoMoController;
use App\Http\Controllers\OrderCancellationController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\ChatbotController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\UserCouponController;


Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

Route::get('/payment/vnpay/return', [PaymentController::class, 'return']);
Route::get('/payment/vnpay/ipn', [PaymentController::class, 'ipn']);
Route::get('/momo/return', [MoMoController::class, 'return']);
Route::post('/momo/notify', [MoMoController::class, 'notify']);
Route::middleware('cache.response:600')->group(function () {
    Route::apiResource('products', ProductsController::class)->only(['index', 'show']);
    Route::get('/products/{slug}/detail', [ProductsController::class, 'detail']);
});
Route::middleware('cache.response:1800')->group(function () {
    Route::apiResource('categories', CategoriesController::class)->only(['index', 'show']);
    Route::get('categories/{id}/subcategories', [SubcategoryController::class, 'getByCategory']);
    Route::apiResource('subcategories', SubcategoryController::class)->only(['index', 'show']);
    Route::apiResource('brands', BrandsController::class)->only(['index', 'show']);
    Route::apiResource('attributes', AttributeController::class)->only(['index', 'show']);
    Route::apiResource('attribute-value', AttributeValueController::class)->only(['index', 'show']);
    Route::apiResource('variant', VariantController::class)->only(['index', 'show']);
});
Route::middleware('cache.response:300')->group(function () {
    Route::post('/coupons/apply', [CouponsController::class, 'apply']);
    Route::apiResource('coupons', CouponsController::class)->only(['index', 'show']);
});

Route::middleware('cache.response:86400')->group(function () {
    Route::get('/provinces', [ShippingController::class, 'provinces']);
    Route::get('/districts/{province_id}', [ShippingController::class, 'districts']);
    Route::get('/wards/{district_id}', [ShippingController::class, 'wards']);
});
Route::post('/shipping-fee', [ShippingController::class, 'calculateFee']);
Route::apiResource('users', UserController::class)->only(['index', 'show']);
Route::post('/chatbot', [ChatbotController::class, 'chat']);
Route::post('/contacts', [ContactController::class, 'store']);
Route::prefix('chat')->group(function () {
    Route::post('/start', [ChatController::class, 'start']);
    Route::get('/{contact}/messages', [ChatController::class, 'getMessages']);
    Route::post('/{contact}/messages', [ChatController::class, 'sendMessage']);
});


Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/getUser', [AuthController::class, 'user']);
    Route::post('/change-password', [UserController::class, 'changePassword']);
    Route::apiResource('reviews', ReviewsController::class);
    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('cart', CartController::class);
    Route::get('/favourites', [FavouritesController::class, 'index']);
    Route::post('/favourites', [FavouritesController::class, 'store']);
    Route::delete('/favourites/{productId}', [FavouritesController::class, 'destroy']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
    Route::post('orders/{id}/reorder', [CartController::class, 'reorder']);
    Route::patch('orders/{id}/cancel', [OrderCancellationController::class, 'cancel']);
    Route::post('/orders/{orderId}/pay/vnpay', [PaymentController::class, 'createVnpay']);
    Route::post('/orders/{orderId}/pay/cod', [PaymentController::class, 'createCod']);
    Route::post('/momo/pay', [MoMoController::class, 'pay']);
    Route::get('/user-coupons', [UserCouponController::class, 'index']);
Route::post('/user-coupons/claim', [UserCouponController::class, 'claim']);
});

Route::middleware(['auth:sanctum', 'role:admin'])->prefix('admin')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index']);
    Route::apiResource('users', UserController::class)->except(['index', 'show']);
    Route::apiResource('products', ProductsController::class)->except(['index', 'show']);
    Route::apiResource('categories', CategoriesController::class)->except(['index', 'show']);
    Route::apiResource('subcategories', SubcategoryController::class)->except(['index', 'show']);
    Route::apiResource('brands', BrandsController::class)->except(['index', 'show']);
    Route::apiResource('coupons', CouponsController::class)->except(['index', 'show']);
    Route::apiResource('attributes', AttributeController::class)->except(['index', 'show']);
    Route::apiResource('attribute-value', AttributeValueController::class)->except(['index', 'show']);
    Route::apiResource('variant', VariantController::class)->except(['index', 'show']);
    Route::apiResource('variant-value', VariantValueController::class);
    Route::apiResource('orders', OrderController::class)->only(['update', 'destroy']);

    Route::prefix('contacts')->group(function () {
        Route::get('/', [ContactController::class, 'index']);
        Route::get('/stats', [ContactController::class, 'stats']);
        Route::get('/{contact}', [ContactController::class, 'show']);
        Route::patch('/{contact}/status', [ContactController::class, 'updateStatus']);
        Route::patch('/{contact}/assign', [ContactController::class, 'assign']);
        Route::delete('/{contact}', [ContactController::class, 'destroy']);
        Route::post('/{contact}/reply', [ChatController::class, 'adminReply']);
    });

    Route::get('/chat/active', [ChatController::class, 'activeChats']);
});