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

Route::group(['prefix' => 'v1'], function (){
    Route::group(['prefix' => 'order', 'middleware' => 'auth:api'], function () {
        Route::group(['middleware' => 'throttle:100,0.5'], function () {
            Route::any('/statuses', 'OrderController@orderStatuses')->name('api.order.statuses');

            Route::any('/car/create', 'OrderController@createCar')->name('api.order.car.create');
            Route::any('/car/list', 'OrderController@index')->name('api.order.car.list');
            Route::any('/car/last', 'OrderController@getLastCarRequest')->name('api.order.car.last');


            Route::any('/box/create', 'OrderController@createBox')->name('api.order.box.create');
            Route::any('/box/list', 'OrderController@indexBox')->name('api.order.box.list');
            Route::any('/box/last', 'OrderController@getLastBoxRequest')->name('api.order.box.last');

        });

        Route::group(['middleware' => 'throttle:5000,0.5'], function () {
            Route::group(['middleware' => ['role:admin|manager'],'prefix' => 'admin'], function () {
                Route::any('/statuses', 'AdminOrderController@orderStatuses')->name('api.order.admin.statuses');
                Route::any('/cant-call', 'AdminOrderController@cantCall')->name('api.order.admin.cant-call');
                Route::any('/confirm', 'AdminOrderController@confirmedOrder')->name('api.order.admin.confirm');
                Route::any('/update-driver', 'AdminOrderController@setDriver')->name('api.order.admin.update-driver');
                Route::any('/cancel', 'AdminOrderController@cancelOrder')->name('api.order.admin.cancel');
                Route::any('/complete', 'AdminOrderController@completeOrder')->name('api.order.admin.complete');

                Route::any('/car/update', 'AdminOrderController@updateCar')->name('api.order.admin.car.update');
                Route::any('/car/list', 'AdminOrderController@index')->name('api.order.admin.car.list');
                Route::any('/car/item', 'AdminOrderController@getLastCarRequest')->name('api.order.admin.car.last');


                Route::any('/box/update', 'AdminOrderController@updateBox')->name('api.order.admin.box.update');
                Route::any('/box/list', 'AdminOrderController@indexBox')->name('api.order.admin.box.list');
                Route::any('/box/item', 'AdminOrderController@getLastBoxRequest')->name('api.order.admin.box.last');


                Route::any('/map/list', 'AdminOrderController@indexMap')->name('api.order.admin.map.list');
                Route::any('/calendar/list', 'AdminOrderController@listForCalendar')->name('api.order.admin.calendar.list');
            });

            Route::group(['middleware' => ['role:driver'],'prefix' => 'driver'], function () {
                Route::any('/statuses', 'DriverOrderController@orderStatuses')->name('api.order.driver.statuses');
                Route::any('/cant-call', 'DriverOrderController@cantCall')->name('api.order.driver.cant-call');
                Route::any('/confirm', 'DriverOrderController@confirmedOrder')->name('api.order.driver.confirm');
                Route::any('/cancel', 'DriverOrderController@cancelOrder')->name('api.order.driver.cancel');
                Route::any('/complete', 'DriverOrderController@completeOrder')->name('api.order.driver.complete');

                Route::any('/car/update', 'DriverOrderController@updateCar')->name('api.order.driver.car.update');
                Route::any('/car/list', 'DriverOrderController@index')->name('api.order.driver.car.list');
                Route::any('/car/item', 'DriverOrderController@getLastCarRequest')->name('api.order.driver.car.last');


                Route::any('/box/update', 'DriverOrderController@updateBox')->name('api.order.driver.box.update');
                Route::any('/box/list', 'DriverOrderController@indexBox')->name('api.order.driver.box.list');
                Route::any('/box/item', 'DriverOrderController@getLastBoxRequest')->name('api.order.driver.box.last');


                Route::any('/map/list', 'DriverOrderController@indexMap')->name('api.order.driver.map.list');
                Route::any('/calendar/list', 'DriverOrderController@listForCalendar')->name('api.order.driver.calendar.list');
            });

        });


    });
});