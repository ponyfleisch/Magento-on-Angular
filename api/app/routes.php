<?php

/*
|--------------------------------------------------------------------------
| Application Routes
|--------------------------------------------------------------------------
|
| Here is where you can register all of the routes for an application.
| It's a breeze. Simply tell Laravel the URIs it should respond to
| and give it the Closure to execute when that URI is requested.
|
*/

Route::get('/products/filter/{pagesize}/{currentpage}', 'ProductsController@getFilterProducts');
Route::get('/filterOptions/', 'FilterController@getFilterOptionsByFilter');

Route::get('/product/{id}', 'ProductController@getProduct');

Route::get('/categories', 'CategoriesController@getCategories');

Route::get('/category/{id}/products', 'ProductsController@getCategoryProducts');
Route::get('/category/{id}/subcategories', 'CategoryController@getSubcategories');
Route::get('/category/{id}/filters', 'CategoryController@getCategoryFilters');

Route::get('filter/{id}/options', 'FilterController@getFilterOptions');

Route::get('/attributes/{name}', 'AttributesController@getOptions');

Route::get('/basket', 'BasketController@getItems');
Route::get('/basket/add/{id}/{quantity}', 'BasketController@addItem');
Route::get('/basket/remove/{id}', 'BasketController@removeItem');

Route::get('/currencies', 'CurrenciesController@getCurrencies');

Route::get('/account', 'AccountController@getAccount');
Route::post('/account/login', 'AccountController@login');
Route::post('/account/fblogin', 'AccountController@fblogin');
Route::get('/account/logout', 'AccountController@logout');
Route::post('/account/register', 'AccountController@register');

Route::get('/content/{identifier}', 'ContentController@getContent');

Route::get('/checkout/state', 'OrderController@getState');

Route::post('checkout/setShippingAddress', 'OrderController@setShippingAddress');
Route::post('checkout/setBillingAddress', 'OrderController@setBillingAddress');

Route::post('checkout/setPaymentMethod', 'OrderController@setPaymentMethod');
Route::post('checkout/setShippingMethod', 'OrderController@setShippingMethod');
Route::get('checkout/countryList', 'OrderController@getCountryList');
Route::get('checkout/regionsByCountry/{country}', 'OrderController@getRegionsByCountry');
Route::post('checkout/send', 'OrderController@sendOrder');
Route::post('checkout/setCouponCode', 'OrderController@setCouponCode');