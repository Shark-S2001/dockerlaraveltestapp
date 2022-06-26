<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OrdersController;
use App\Http\Controllers\ShopOrdersController;
use App\Http\Controllers\ProductsController;
use App\Http\Controllers\CustomerController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::get('/get_all_products',[ProductsController::class, 'select_all_products']);
Route::post('/update_price',[ProductsController::class, 'update_products_price']);
Route::post('/update_stocks',[ProductsController::class, 'update_products_stocks']);
Route::post('/insert_new_products',[ProductsController::class, 'create_new_product']);
Route::post('/insert_new_uom',[ProductsController::class, 'create_uom_measure']);
Route::post('/upload_uom_updates',[ProductsController::class, 'update_uom_online']);
Route::post('/insert_new_retail_package',[ProductsController::class, 'create_retail_package']);
Route::post('/upload_package_updates',[ProductsController::class, 'update_retail_package_online']);
Route::post('/update_product_packages',[ProductsController::class, 'update_products_packages']);
//Orders controller
Route::post('/add_item_to_cart',[OrdersController::class, 'add_new_product']);
Route::post('/get_cart_products',[OrdersController::class, 'select_cart_products']);
Route::post('/add_new_order',[OrdersController::class, 'create_new_order']);
Route::post('/add_item_to_returns',[OrdersController::class, 'restore_product']);
Route::post('/add_return_full_order',[OrdersController::class, 'restore_full_order']);
Route::post('/get_sales_summary',[OrdersController::class, 'select_dashboard_summary']);
Route::post('/get_recent_orders',[OrdersController::class, 'select_recent_orders']); 
Route::post('/get_order_items',[OrdersController::class, 'select_orders_items']);
Route::post('/delete_product_from_cart',[OrdersController::class, 'delete_product']);
Route::post('/delete_order_completely',[OrdersController::class, 'delete_order']);
Route::post('/update_product_in_cart',[OrdersController::class, 'update_item_in_cart']);
Route::post('/send_order_message',[OrdersController::class, 'sendReminderMessage']);
//Shop orders controller
Route::post('/add_new_shop_order',[ShopOrdersController::class, 'create_new_shop_order']);
Route::post('/get_recent_shop_orders',[ShopOrdersController::class, 'select_recent_shop_orders']);
Route::POST('/select_weekly_sales',[ShopOrdersController::class, 'select_weekly_data']);
Route::POST('/select_monthly_sales',[ShopOrdersController::class, 'select_monthly_data']);
Route::post('/get_shop_order_items',[ShopOrdersController::class, 'select_shop_order_items']);
Route::post('/delete_shop_order_completely',[ShopOrdersController::class, 'delete_shop_order']);
Route::get('/select_all_shop_customers',[ShopOrdersController::class, 'select_shop_customers']);
Route::post('/update_shop_order',[ShopOrdersController::class, 'edit_shop_order']);
//Customers controller
Route::post('/create_new_customer',[CustomerController::class, 'add_new_customer']);
Route::post('/update_customers_data',[CustomerController::class, 'update_customers_info']);
Route::post('/get_all_customers',[CustomerController::class, 'select_all_customers']);
Route::get('/get_all_routes',[CustomerController::class, 'select_all_routes']);
//Clear Cache in laravel app
Route::get('/clear-cache', function() {
    Artisan::call('cache:clear');
    //reset cache when data is changed
    Cache::forget('products');
    Cache::forget('customers');
    Cache::forget('routes');
    Cache::forget("shopcustomers");
    return "Cache is cleared";
});