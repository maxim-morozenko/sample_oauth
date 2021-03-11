<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

use Illuminate\Support\Facades\Route;

Route::prefix('oauth')->group(function() {
    Route::get('/', 'OAuthController@index');

    Route::match(['get', 'post'], '/google/make', 'OGAuthController@makeUrlAuth')->name('oauth.make.google');

    Route::match(['get', 'post'], '/facebook/make', 'OFAuthController@makeUrlAuth')->name('oauth.make.facebook');

    Route::match(['get', 'post'], '/vk/make', 'OVAuthController@makeUrlAuth')->name('oauth.make.vk');
});
