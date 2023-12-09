<?php

use Illuminate\Http\Request;

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

Route::post('login','Api\AuthController@login');
Route::post('register','Api\AuthController@register');
Route::post('logout','Api\AuthController@logout');
Route::post('add-inventory-item', 'InventoryAPIController@storeInventory');
Route::post('approve', 'InventoryAPIController@approveTempMaterial');
Route::post('activate', 'InventoryAPIController@reactivateAccount');
Route::post('deactivate', 'InventoryAPIController@deactivateAccount');
Route::post('check-status', 'InventoryAPIController@checkAccountStatus');
Route::post('sendVerif','Api\AuthController@sendVerificationCode');
Route::middleware(['web'])->group(function () {
Route::post('Verify', 'Api\AuthController@verifyVerificationCode');
});

Route::get('get-materials', 'InventoryAPIController@getMaterials');
// Material-related routes
Route::get('get-types', 'InventoryAPIController@getTypes');
Route::get('get-sizes', 'InventoryAPIController@getSizes');
Route::get('get-prices', 'InventoryAPIController@getPrices');
Route::get('get-seller-id', 'InventoryAPIController@getSellerIdByUserId');
Route::get('getMaterialDetails', 'InventoryAPIController@getMaterialDetails');


Route::get('adminTempMaterials', 'InventoryAPIController@getAllTempMaterials');
Route::get('inactive', 'InventoryAPIController@getInactiveAccounts');

Route::get('seller_inventory', 'InventoryAPIController@getSellerInventory');
Route::get('getIdDetail', 'InventoryAPIController@getDetailId');
Route::get('getMaterialsId', 'InventoryAPIController@getMaterialsId');
Route::get('searchMaterials', 'InventoryAPIController@searchMaterials');
Route::get('sellerInfo', 'InventoryAPIController@getSellerInfo');
Route::get('nameInfo', 'InventoryAPIController@getUserInfoByName');
Route::post('updateInventory', 'InventoryAPIController@updateInventoryItem');
Route::post('addtocart', 'InventoryAPIController@addToCart');
Route::get('getbuyerId', 'InventoryAPIController@getBuyerId');
Route::get('addtocart', 'InventoryAPIController@addToCart');
Route::get('cartdetails', 'InventoryAPIController@getCartDetails');
Route::get('cartitems', 'InventoryAPIController@getShoppingCartItems');
Route::get('geoAddress', 'InventoryAPIController@geocodeAddress');
Route::post('userUpdate', 'InventoryAPIController@updateUserProfile');
route::get('userCapture','InventoryAPIController@getUserProfile');
route::get('geoCode','InventoryAPIController@geocodeAddressAPI');
route::post('generateReport','InventoryAPIController@generateReport');
route::post('orderPlace','InventoryAPIController@orderPlacement');
Route::get('seller_inventory_temporary', 'InventoryAPIController@getTemporaryInventory');
Route::post('updateTempMaterial', 'InventoryAPIController@updateTemporaryMaterial');
route::get('getOrders','InventoryAPIController@getSellerOrders');
Route::post('approveOrder', 'InventoryAPIController@updateOrderStatus');


route::get('getMyOrders','InventoryAPIController@getOrders');

route::post('updateBulk','InventoryAPIController@updateOrderDetails');

route::post('adjustOrderPrice','InventoryAPIController@adjustOrderPrice');

Route::get('webPage', 'InventoryAPIController@getWebsiteContent');

route::post('addTemp','InventoryAPIController@addTemporaryMaterial');

route::post('updateInventoryAvailability','InventoryAPIController@updateInventoryAvailability');
route::post('updateTempAvailability','InventoryAPIController@updateTemporaryInventoryItem');


Route::delete('deletecartItem', 'InventoryAPIController@deleteCartItem');
Route::delete('deleteTemp', 'InventoryAPIController@deleteTemporaryMaterial');
Route::delete('deleteInventory', 'InventoryAPIController@deleteInventoryItem');

Route::get('reverseGeocode','InventoryAPIController@reverseGeocode');







