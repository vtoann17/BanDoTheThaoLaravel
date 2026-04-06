<?php

use App\Http\Controllers\AttributeController;
use App\Http\Controllers\AttributeValueController;
use App\Http\Controllers\VariantController;
use App\Http\Controllers\VariantValueController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CategoriesController;
use App\Http\Controllers\SubcategoryController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\BrandsController;
use App\Http\Controllers\ReviewsController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\CouponsController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ShippingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\PaymentController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::get('/auth/google', [AuthController::class, 'googleRedirect']);
Route::get('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);

// xem dữ liệu (public)
Route::apiResource('products', ProductsController::class)->only(['index', 'show']);
Route::get('/products/{slug}/detail', [ProductsController::class, 'detail']);
Route::apiResource('categories', CategoriesController::class)->only(['index', 'show']);
Route::apiResource('subcategories', SubcategoryController::class)->only(['index', 'show']);
Route::apiResource('brands', BrandsController::class)->only(['index', 'show']);
Route::apiResource('coupons', CouponsController::class)->only(['index', 'show']);
Route::apiResource('attributes', AttributeController::class)->only(['index', 'show']);
Route::apiResource('variant', VariantController::class)->only(['index', 'show']);
Route::apiResource('users', UserController::class)->only(['index', 'show']);
Route::apiResource('attribute-value', AttributeValueController::class)->only(['index', 'show']);
Route::get('/provinces', [ShippingController::class, 'provinces']);
Route::get('/districts/{province_id}', [ShippingController::class, 'districts']);
Route::get('/wards/{district_id}', [ShippingController::class, 'wards']);
Route::post('/shipping-fee', [ShippingController::class, 'calculateFee']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/getUser', [AuthController::class, 'user']);
    Route::apiResource('reviews', ReviewsController::class);
    Route::apiResource('addresses', AddressController::class);
    Route::apiResource('cart', CartController::class);
    Route::apiResource('orders', OrderController::class)->only(['index', 'show', 'store']);
    Route::post('/orders/{orderId}/pay/vnpay', [PaymentController::class, 'createVnpay']);
    Route::post('/orders/{orderId}/pay/cod',   [PaymentController::class, 'createCod']); // thêm
    Route::post('change-password', [UserController::class, 'changePassword']);
});

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
    Route::apiResource('orders', OrderController::class)->only(['update', 'destroy']); // sửa: dùng only thay except
});

Route::get('categories/{id}/subcategories', [SubcategoryController::class, 'getByCategory']);
