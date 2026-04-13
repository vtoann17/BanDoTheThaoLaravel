<?php
use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\VariantController;
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
Route::apiResource('products', ProductsController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:600');
Route::get('/products/{slug}/detail', [ProductsController::class, 'detail'])
    ->middleware('cache.response:600');
Route::apiResource('categories', CategoriesController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:1800');
Route::get('categories/{id}/subcategories', [SubcategoryController::class, 'getByCategory'])
    ->middleware('cache.response:1800');
Route::apiResource('subcategories', SubcategoryController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:1800');
Route::apiResource('brands', BrandsController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:1800');
Route::apiResource('attributes', AttributeController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:1800');
Route::apiResource('attribute-value', AttributeValueController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:1800');
Route::apiResource('variant', VariantController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:1800');
Route::post('/coupons/apply', [CouponsController::class, 'apply']);
Route::apiResource('coupons', CouponsController::class)
    ->only(['index', 'show'])
    ->middleware('cache.response:300');
Route::apiResource('users', UserController::class)
    ->only(['index', 'show']);
Route::get('/provinces', [ShippingController::class, 'provinces'])
    ->middleware('cache.response:86400');
Route::get('/districts/{province_id}', [ShippingController::class, 'districts'])
    ->middleware('cache.response:86400');
Route::get('/wards/{district_id}', [ShippingController::class, 'wards'])
    ->middleware('cache.response:86400');
Route::post('/shipping-fee', [ShippingController::class, 'calculateFee']);

// USER
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/getUser', [AuthController::class, 'user']);
    Route::apiResource('reviews', ReviewsController::class);
    Route::get('/favourites', [FavouritesController::class, 'index']);      // Lấy danh sách
    Route::post('/favourites', [FavouritesController::class, 'store']);    // Thêm vào yêu thích
    Route::delete('/favourites/{productId}', [FavouritesController::class, 'destroy']);
    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('cart', CartController::class);
    Route::post('orders/{id}/reorder', [CartController::class, 'reorder']);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
    Route::post('change-password', [UserController::class, 'changePassword']);
    Route::post('/orders/{orderId}/pay/vnpay', [PaymentController::class, 'createVnpay']);
    Route::post('/orders/{orderId}/pay/cod', [PaymentController::class, 'createCod']);
    Route::post('/momo/pay', [MoMoController::class, 'pay']);
    Route::patch('orders/{id}/cancel', [OrderCancellationController::class, 'cancel']);
});

// ADMIN
Route::middleware(['auth:sanctum', 'role:admin'])->group(function () {
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
});