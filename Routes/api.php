<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

Route::group(['prefix' => 'v1'], function () {
    Route::group(['prefix' => 'oauth'], function () {

        Route::match(['get', 'post'], '/info', 'OauthController@getInfoByHash');

        // Google
        // MAKE
        Route::match(['get', 'post'], '/google/make', 'OGAuthController@makeForApi');
        // CALLBACK
        Route::match(['get', 'post'], '/google/callback/', 'OGAuthController@index');

        // Facebook
        // MAKE
        Route::match(['get', 'post'], '/facebook/make', 'OFAuthController@makeForApi');
        // CALLBACK
        Route::match(['get', 'post'], '/facebook/callback/', 'OFAuthController@index');

        // VK
        // MAKE
        Route::match(['get', 'post'], '/vk/make', 'OVAuthController@makeForApi');
        // CALLBACK
        Route::match(['get', 'post'], '/vk/callback/', 'OVAuthController@index');



    });
});