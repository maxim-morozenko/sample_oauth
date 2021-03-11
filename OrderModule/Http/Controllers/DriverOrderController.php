<?php

namespace Modules\Order\Http\Controllers;

use App\Entities\User;
use App\Events\OrderBoxWasCompleted;
use App\Events\OrderBoxWasConfirmed;
use App\Events\OrderBoxWasCreated;
use App\Events\OrderBoxWasDeleted;
use App\Events\OrderBoxWasDeletedFromConfirmed;
use App\Events\OrderCarWasCompleted;
use App\Events\OrderCarWasConfirmed;
use App\Events\OrderCarWasCreated;
use App\Events\OrderCarWasDeleted;
use App\Events\OrderCarWasDeletedFromConfirmed;
use App\Http\Controllers\Controller;
use App\Notifications\CantCallNotification;
use App\Notifications\OrderCancelNotification;
use App\Notifications\OrderCompletedNotification;
use App\Notifications\OrderConfirmedNotification;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Modules\Account\Entities\UserAddress;
use Modules\Account\Entities\UserLegal;
use Modules\Account\Entities\UserPhone;
use Modules\Core\Entities\BoxType;
use Modules\Order\Entities\OrderBox;
use Modules\Order\Entities\OrderCar;


class DriverOrderController extends Controller
{
    public function index(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);
            $user = Auth::user();
            $filter = $request->filter;

            $aFilter = array_merge($filter, [
                'statuses' => [
                    OrderCar::ORDER_CAR_STATUS_WAIT_CONFIRMATION,
                    OrderCar::ORDER_CAR_STATUS_CANT_CONFIRMATION,
                ],
                'for_admin' => true,
                'driver_id' => Auth::id()

            ]);

            if ( isset($filter['statuses']) && empty($filter['statuses']) ) {
                    unset($filter['statuses']);
                    unset($aFilter['statuses']);
            }

            if ( isset($filter['statuses']) && !empty($filter['statuses']) ) {
                if ( $filter['statuses'] ) {
                    $aFilter['statuses'] = $filter['statuses'];
                }
            }


            Log::debug($aFilter);

            $lData = OrderCar::getByParams(
                $aFilter
            );



            _setParams([
                'car_orders' => $lData->list,
                'count' => $lData->count
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }

    public function indexBox(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);
            $user = Auth::user();
            $filter = $request->filter;

            $aFilter = array_merge($filter, [
                'statuses' => [
                    OrderCar::ORDER_CAR_STATUS_WAIT_CONFIRMATION,
                    OrderCar::ORDER_CAR_STATUS_CANT_CONFIRMATION,

                ],
                'for_admin' => true,
                'driver_id' => Auth::id()
            ]);

            if ( isset($filter['statuses']) && empty($filter['statuses']) ) {
                unset($filter['statuses']);
                unset($aFilter['statuses']);
            }

            if ( isset($filter['statuses']) ) {
                if ( $filter['statuses'] ) {
                    $aFilter['statuses'] = $filter['statuses'];
                }
            }

            $lData = OrderBox::getByParams(
                $aFilter
            );



            _setParams([
                'box_orders' => $lData->list,
                'count' => $lData->count
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }

    public function indexMap(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);
            $user = Auth::user();
            $filter = $request->filter;

            $aFilter = array_merge($filter, [
                'statuses' => [
                    OrderCar::ORDER_CAR_STATUS_WAIT_CONFIRMATION,
                    OrderCar::ORDER_CAR_STATUS_CANT_CONFIRMATION,

                ],
                'for_admin' => true
            ]);

            if ( isset($filter['statuses']) ) {
                if ( $filter['statuses'] ) {
                    $aFilter['statuses'] = $filter['statuses'];
                }
            }

            $lDataBox = OrderBox::getByParams(
                $aFilter
            );

            $lDataCar = OrderCar::getByParams(
                $aFilter
            );



            _setParams([
                'box_orders' => $lDataBox->list,
                'box_count' => $lDataBox->count,
                'car_orders' => $lDataCar->list,
                'car_count' => $lDataCar->count
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }


    public function orderStatuses(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);


            _setParams([
                'order_statuses' => OrderCar::listStatuses()
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }

    /**
     * Create Car order
     * Plan
     * 1. Get address data and store ( create UserAddress - need for relation )
     * 2. Get contact data and store ( create UserPhone - need for relation )
     * 3. Get legal data if exists and store ( create UserLegal - need for relation )
     *
     * 4. Get order data ( category, weight, comment )
     *
     * 5. Create order with NEED_CONFIRM status
     *
     * Done
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function updateCar(Request $request)
    {
        try {
            $this->validate($request, [
                'id' => 'required',
                // For address
                'address' => 'required',
                'address_lat' => 'required',
                'address_lng' => 'required',
                // For contact
                'contact_name' => 'required',
                'contact_email' => 'required|email',
                'contact_phone' => [
                    'required',
                    'regex:/[0-9]{9,15}/',
                    'numeric'
                ],
                // For legal if exists
                'legal_inn' => [
                    'sometimes',

                ],
                'ogrn' => 'sometimes',
                'legal_title' => 'sometimes',

                // For order
                'category' => [
                    'required',
                    Rule::in([
                        OrderCar::ORDER_CAR_CATEGORY_PLASTIC, OrderCar::ORDER_CAR_CATEGORY_CARDBOARD
                    ]),

                ],
                'declared_weight' => 'required|numeric',

            ]);

            $user = User::sGetById($request->user_id);
            $oOrder = OrderCar::sGetById($request->id);

            Log::debug($request->all());
            $aOrder = [
                'user_id' => $user->id,
            ];
            // Fill address if not exist
            $oAddress = UserAddress::getByCoordinates([$request->address_lat, $request->address_lng], $user->id);
            if ( !$oAddress ) {
                $oAddress = new UserAddress();
                // TODO: need to check in area
                $aAdress = [

                    'address' => $request->address,
                    'lat' => $request->address_lat,
                    'lng' => $request->address_lng,
                    'user_id' => $user->id,
                ];
                $oAddress->fill($aAdress);
                if ( !$oAddress->save() ) {
                    _setParams([
                        'errors' => [
                            'ERROR_SAVE_ADDRESS' => [
                                __('errors.address_cant_be_saved')
                            ]
                        ]
                    ]);
                    return _view();
                }

            }
            $aOrder['address_id'] = $oAddress->id;
            $aOrder['address_lat'] = $oAddress->lat;
            $aOrder['address_lng'] = $oAddress->lng;
            $aOrder['address'] = $oAddress->address;
            // Fill contact if not exists
            $oContact = UserPhone::getByPhone($request->contact_phone, $user->id);
            if ( !$oContact ) {
                $oContact = new UserPhone();
                $aContact = [
                    'user_id' => $user->id,

                    'phone' => $request->contact_phone,
                    'email' => $request->contact_email,
                    'full_name' => $request->contact_name,

                    'phone_confirmed' => true,
                    'phone_confirmed_at' => Carbon::now(),
                    'phone_confirmation_code' =>'NA',


                ];
                $oContact->fill($aContact);


                if ( !$oContact->save() ) {
                    _setParams([
                        'errors' => [
                            'ERROR_SAVE_CONTACT' => [
                                __('errors.contact_cant_be_saved')
                            ]
                        ]
                    ]);
                    return _view();
                }



            }


            $aOrder['phone_id'] = $oContact->id;
            $aOrder['contact_name'] = $request->contact_name;
            $aOrder['contact_email'] = $oContact->email;
            $aOrder['contact_phone'] = $oContact->phone;
            $aOrder['contact_phone_confirmed'] = $oContact->phone_confirmed;
            $aOrder['contact_phone_confirmed_at'] = $oContact->phone_confirmed_at;
            // Fill legal if exists
            if ( isset($request->legal_inn) && $request->legal_inn ) {
                $oLegal = UserLegal::getByInn($request->legal_inn, $user->id);
                if ( !$oLegal ) {
                    $oLegal = new UserLegal();
                    // TODO: add validate
                    $aLegal = [
                        'user_id' => $user->id,

                        'inn' => $request->legal_inn,
                        'full_name' => $request->legal_title,
                        'ogrn' => $request->ogrn,

                        'confirmed' => false

                    ];
                    $oLegal->fill($aLegal);
                    if ( !$oLegal->save() ) {
                        _setParams([
                            'errors' => [
                                'ERROR_SAVE_CONTACT' => [
                                    __('errors.legal_cant_be_saved')
                                ]
                            ]
                        ]);
                        return _view();
                    }

                }
                $aOrder['legal_id'] = $oLegal->id;
                $aOrder['legal_inn'] = $oLegal->inn;
                $aOrder['legal_title'] = $oLegal->full_name;
            } else {
                $aOrder['legal_id'] = null;
                $aOrder['legal_inn'] = null;
                $aOrder['legal_title'] = null;
            }

            $aOrder['category'] = $request->category;

            $aOrder['comment_user'] = $request->comment_user ? $request->comment_user : $request->comment_user ;
            if ( !$aOrder['comment_user'] ) {
                $aOrder['comment_user'] = ' ';
                $aOrder['comment_manager'] = ' ';
            }
            $aOrder['comment_manager'] = ' ';


            $aOrder['declared_weight'] = $request->declared_weight;


            $oOrder->fill($aOrder);


            // dd($oOrder->declared_weight);

            if ( !$oOrder->save() ) {
                _setParams([
                    'errors' => [
                        'ERROR_SAVE_CONTACT' => [
                            __('errors.order_cant_be_saved')
                        ]
                    ]
                ]);
                return _view();
            }

            // broadcast(new OrderCarWasCreated($oOrder));

            _setParams([
                'message' => 'Success',
                'order_created' => true,
                'order' => $oOrder,
            ]);





        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            _setParams([
                'errors' => [
                    'SYSTEM_ERROR' => [
                        __('errors.service_not_response')
                    ]
                ]
            ]);
        }

        return _view();
    }

    /**
     * Create Box order
     * Plan
     * 1. Get address data and store ( create UserAddress - need for relation )
     * 2. Get contact data and store ( create UserPhone - need for relation )
     * 3. Get legal data if exists and store ( create UserLegal - need for relation )
     *
     * 4. Get order data ( category, weight, comment )
     *
     * 5. Create order with NEED_CONFIRM status
     *
     * Done
     *
     * @param Request $request
     * @return \Illuminate\Contracts\View\Factory|\Illuminate\View\View
     */
    public function updateBox(Request $request)
    {
        try {
            $this->validate($request, [
                // For address
                'address' => 'required',
                'address_lat' => 'required',
                'address_lng' => 'required',
                // For contact
                'contact_name' => 'required',
                'contact_email' => 'required|email',
                'contact_phone' => [
                    'required',
                    'regex:/[0-9]{9,15}/',
                    'numeric'
                ],
                // For legal if exists
                'legal_inn' => [
                    'sometimes',

                ],
                'ogrn' => 'sometimes',
                'legal_title' => 'sometimes',

                // For order
                'order_type' => [
                    'required',
                    Rule::in([
                        OrderBox::ORDER_BOX_TYPE_RENT, OrderBox::ORDER_BOX_TYPE_SALE
                    ]),

                ],
                'box_count' => 'required|numeric',
                'box_type_id' => 'required|numeric|exists:box_types,id',



            ]);

            $user = User::sGetById($request->user_id);
            $oOrder = OrderBox::sGetById($request->id);
            $aOrder = [
                'user_id' => $user->id,
            ];
            // Fill address if not exist
            $oAddress = UserAddress::getByCoordinates([$request->address_lat, $request->address_lng], $user->id);
            if ( !$oAddress ) {
                $oAddress = new UserAddress();
                // TODO: need to check in area
                $aAdress = [

                    'address' => $request->address,
                    'lat' => $request->address_lat,
                    'lng' => $request->address_lng,
                    'user_id' => $user->id,
                ];
                $oAddress->fill($aAdress);
                if ( !$oAddress->save() ) {
                    _setParams([
                        'errors' => [
                            'ERROR_SAVE_ADDRESS' => [
                                __('errors.address_cant_be_saved')
                            ]
                        ]
                    ]);
                    return _view();
                }

            }
            $aOrder['address_id'] = $oAddress->id;
            $aOrder['address_lat'] = $oAddress->lat;
            $aOrder['address_lng'] = $oAddress->lng;
            $aOrder['address'] = $oAddress->address;
            // Fill contact if not exists
            $oContact = UserPhone::getByPhone($request->contact_phone, $user->id);
            if ( !$oContact ) {
                $oContact = new UserPhone();
                $aContact = [
                    'user_id' => $user->id,

                    'phone' => $request->contact_phone,
                    'email' => $request->contact_email,
                    'full_name' => $request->contact_name,

                    'phone_confirmed' => true,
                    'phone_confirmed_at' => Carbon::now(),
                    'phone_confirmation_code' =>'NA',


                ];
                $oContact->fill($aContact);


                if ( !$oContact->save() ) {
                    _setParams([
                        'errors' => [
                            'ERROR_SAVE_CONTACT' => [
                                __('errors.contact_cant_be_saved')
                            ]
                        ]
                    ]);
                    return _view();
                }



            }


            $aOrder['phone_id'] = $oContact->id;
            $aOrder['contact_name'] = $request->contact_name;
            $aOrder['contact_email'] = $oContact->email;
            $aOrder['contact_phone'] = $oContact->phone;
            $aOrder['contact_phone_confirmed'] = $oContact->phone_confirmed;
            $aOrder['contact_phone_confirmed_at'] = $oContact->phone_confirmed_at;
            // Fill legal if exists
            if ( isset($request->legal_inn) && $request->legal_inn ) {
                $oLegal = UserLegal::getByInn($request->legal_inn, $user->id);
                if ( !$oLegal ) {
                    $oLegal = new UserLegal();
                    // TODO: add validate
                    $aLegal = [
                        'user_id' => $user->id,

                        'inn' => $request->legal_inn,
                        'full_name' => $request->legal_title,
                        'ogrn' => $request->ogrn,

                        'confirmed' => false

                    ];
                    $oLegal->fill($aLegal);
                    if ( !$oLegal->save() ) {
                        _setParams([
                            'errors' => [
                                'ERROR_SAVE_CONTACT' => [
                                    __('errors.legal_cant_be_saved')
                                ]
                            ]
                        ]);
                        return _view();
                    }

                }
                $aOrder['legal_id'] = $oLegal->id;
                $aOrder['legal_inn'] = $oLegal->inn;
                $aOrder['legal_title'] = $oLegal->full_name;
            } else {
                $aOrder['legal_id'] = null;
                $aOrder['legal_inn'] = null;
                $aOrder['legal_title'] = null;
            }


            $aOrder['order_type'] = $request->order_type;
            $aOrder['box_count'] = $request->box_count;
            $aOrder['box_type_id'] = $request->box_type_id;
            // Get Box type data
            $oBoxType = BoxType::getById($request->box_type_id);
            if ( $oBoxType ) {
                $aOrder['box_price'] = $oBoxType->price;
                $aOrder['box_type'] = $oBoxType->title;
            }
            $aOrder['box_time_type'] = $request->box_time_type;
            $aOrder['box_time_count'] = $request->box_time_count;




            $aOrder['comment_user'] = $request->comment_user ? $request->comment_user : $request->comment_user ;
            if ( !$aOrder['comment_user'] ) {
                $aOrder['comment_user'] = ' ';
                $aOrder['comment_manager'] = ' ';
            }
            $aOrder['comment_manager'] = ' ';






            $oOrder->fill($aOrder);


            // dd($oOrder->declared_weight);

            if ( !$oOrder->save() ) {
                _setParams([
                    'errors' => [
                        'ERROR_SAVE_CONTACT' => [
                            __('errors.order_cant_be_saved')
                        ]
                    ]
                ]);
                return _view();
            }

            event(new OrderBoxWasCreated($oOrder));

            _setParams([
                'message' => 'Success',
                'order_created' => true,
                'order' => $oOrder,
            ]);





        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        } catch (\Exception $e) {
            Log::error($e);
            _setParams([
                'errors' => [
                    'SYSTEM_ERROR' => [
                        __('errors.service_not_response')
                    ]
                ]
            ]);
        }

        return _view();
    }

    public function getLastCarRequest(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);
            $orderCar = null;

            $tOrderCar = OrderCar::lastOrderForAdmin([
                'id' => $request->order_id
            ]);

            if ( $tOrderCar ) {
                $orderCar = $tOrderCar;
                $orderCar->load([
                    'addressItem',
                    'phone',
                    'legal'
                ]);
            }

            _setParams([
               'order_car' => $orderCar
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }

    public function cantCall(Request $request)
    {
        try {
            $this->validate($request, [
                'order_id' => 'required',
                'order_type' => 'required'
            ]);

            $order = null;
            if ( $request->order_type == 'car' ) {
                $order = OrderCar::sGetById($request->order_id);
            } else {
                $order = OrderBox::sGetById($request->order_id);

            }

            if ( $order ) {
                /**
                 * @var OrderCar|OrderBox $order
                 */

                $order->loadMissing(['user']);

                if ( $order->user ) {
                    /**
                     * @var User $cOrderUser
                     */
                    $cOrderUser = $order->user;
                    $cOrderUser->notify((new CantCallNotification($cOrderUser, $order)));
                }
            }

            $order->status = OrderCar::ORDER_CAR_STATUS_CANT_CONFIRMATION;
            $order->save();

            // TODO: add notification for user

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }


    public function confirmedOrder(Request $request)
    {
        try {
            $this->validate($request, [
                'order_id' => 'required',
                'order_type' => 'required',


                'comment_manager' => 'sometimes',
            ]);

            $order = null;
            if ( $request->order_type == 'car' ) {
                $order = OrderCar::sGetById($request->order_id);
            } else {
                $order = OrderBox::sGetById($request->order_id);

            }

            $iPrevStatus = $order->status;
            $dDeliveryAt = $order->delivery_at;
            $order->status = OrderCar::ORDER_CAR_STATUS_IN_PROGRESS;
            if ( $request->comment_manager ) {
                $order->comment_manager = $request->comment_manager;
            }

            $cDate = Carbon::parse($request->date_delivery)->timezone('Europe/Moscow');
            $order->delivery_at = Carbon::parse( $cDate->format('d-m-Y'). ' '
                . 12 . ':' . 0 .':00');
            $order->save();

            if ( $order ) {
                /**
                 * @var OrderCar|OrderBox $order
                 */

                $order->loadMissing(['user']);

                if ( $order->user ) {
                    /**
                     * @var User $cOrderUser
                     */

                    $cOrderUser = $order->user;
                    $cOrderUser->notify((new OrderConfirmedNotification($cOrderUser, $order)));

                    if ( $iPrevStatus !== OrderCar::ORDER_CAR_STATUS_IN_PROGRESS ) {
                        if ( $order->delivery_at->format('d-m-Y') === Carbon::now()->format('d-m-Y') ) {
                            if ($order instanceof OrderBox) {
                                event(new OrderBoxWasConfirmed($order));
                            } elseif ($order instanceof OrderCar) {
                                event(new OrderCarWasConfirmed($order));
                            }
                        }
                    } elseif ( $iPrevStatus === OrderCar::ORDER_CAR_STATUS_IN_PROGRESS ) {
                        if ( $order->delivery_at->format('d-m-Y') === Carbon::now()->format('d-m-Y') ) {
                            if ($order instanceof OrderBox) {
                                event(new OrderBoxWasConfirmed($order));
                            } elseif ($order instanceof OrderCar) {
                                event(new OrderCarWasConfirmed($order));
                            }
                        } else {
                            if ($dDeliveryAt->format('d-m-Y') === Carbon::now()->format('d-m-Y') ) {
                                if ($order instanceof OrderBox) {
                                    event(new OrderBoxWasDeletedFromConfirmed($order));
                                } elseif ($order instanceof OrderCar) {
                                    event(new OrderCarWasDeletedFromConfirmed($order));
                                }
                            }
                        }
                    }

                }
            }



            // TODO: add notification for user

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }


    public function cancelOrder(Request $request)
    {
        try {
            $this->validate($request, [
                'order_id' => 'required',
                'order_type' => 'required',

                'comment_manager' => 'required',
            ]);

            $order = null;
            if ( $request->order_type == 'car' ) {
                $order = OrderCar::sGetById($request->order_id);
            } else {
                $order = OrderBox::sGetById($request->order_id);

            }

            $iPrevStatus = $order->status;
            $order->status = OrderCar::ORDER_CAR_STATUS_CANCEL;
            if ( $request->comment_manager ) {
                $order->comment_manager = $request->comment_manager;
            }

            $order->save();


            if ( $order ) {
                /**
                 * @var OrderCar|OrderBox $order
                 */

                $order->loadMissing(['user']);

                if ( $order->user ) {
                    /**
                     * @var User $cOrderUser
                     */
                    $cOrderUser = $order->user;
                    $cOrderUser->notify((new OrderCancelNotification($cOrderUser, $order)));


                    if ( $order instanceof OrderBox ) {
                        if ( $iPrevStatus === OrderCar::ORDER_CAR_STATUS_IN_PROGRESS ) {
                            event(new OrderBoxWasDeletedFromConfirmed($order));
                        } else {
                            event(new OrderBoxWasDeleted($order));
                        }
                    } elseif ( $order instanceof OrderCar ) {
                        if ( $iPrevStatus === OrderCar::ORDER_CAR_STATUS_IN_PROGRESS ) {
                            event(new OrderCarWasDeletedFromConfirmed($order));
                        } else {
                            event(new OrderCarWasDeleted($order));
                        }
                    }
                }
            }



            // TODO: add notification for user

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }

    public function completeOrder(Request $request)
    {
        try {
            $this->validate($request, [
                'order_id' => 'required',
                'order_type' => 'required',



                'comment_manager' => 'sometimes',
            ]);

            $order = null;
            if ( $request->order_type == 'car' ) {
                $order = OrderCar::sGetById($request->order_id);
            } else {
                $order = OrderBox::sGetById($request->order_id);

            }


            $order->status = OrderCar::ORDER_CAR_STATUS_DONE;
            if ( $request->comment_manager ) {
                $order->comment_manager = $request->comment_manager;
            }

            if ($request->received_weight) {
                $order->received_weight = $request->received_weight;
            }
            if ($request->box_received ) {
                $order->box_received = true;
            }

            $order->save();

            if ( $order ) {
                /**
                 * @var OrderCar|OrderBox $order
                 */

                $order->loadMissing(['user']);

                if ( $order->user ) {
                    /**
                     * @var User $cOrderUser
                     */
                    $cOrderUser = $order->user;
                    $cOrderUser->notify((new OrderCompletedNotification($cOrderUser, $order)));

                    if ( $order instanceof OrderBox ) {
                        event(new OrderBoxWasCompleted($order));
                    } elseif ( $order instanceof OrderCar ) {
                        event(new OrderCarWasCompleted($order));
                    }

                }
            }



            // TODO: add notification for user

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }


    public function getLastBoxRequest(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);
            $orderCar = null;

            $tOrderCar = OrderBox::lastOrderForAdmin([
                'id' => $request->order_id
            ]);

            if ( $tOrderCar ) {
                $orderCar = $tOrderCar;
                $orderCar->load([
                    'addressItem',
                    'phone',
                    'legal'
                ]);
            }

            _setParams([
                'order_box' => $orderCar
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }


    public function listForCalendar(Request $request)
    {
        // Get filter data
        try {
            $this->validate($request, [
                ''
            ]);
            $lEvents = null;
            $filter = [];
            $filter['start_date'] = Carbon::parse($request->filter['startDate']);
            $filter['end_date'] = Carbon::parse($request->filter['endDate']);

            /**
             *
             *
             *
            start    : subDays(startOfDay(new Date()), 1),
            end      : addDays(new Date(), 1),
            title    : 'A 3 day event',
            allDay   : true,
            color    : {
            primary  : '#F44336',
            secondary: '#FFCDD2'
            },
            resizable: {
            beforeStart: true,
            afterEnd   : true
            },
            draggable: true,
            meta     : {
            location: 'Los Angeles',
            notes   : 'Eos eu verear adipiscing, ex ornatus denique iracundia sed, quodsi oportere appellantur an pri.'
            }
             *
             */

            $lCar = OrderCar::getByParams($filter);
            if ( $lCar->count ) {
                foreach ($lCar->list as $keyIndex => $oItem) {
                    $oItem->type = 'CAR';
                    $oItem->start = $oItem->delivery_at->timezone('Europe/Moscow');
                    $oItem->title = __('calendar.car_title', [
                        'id' => $oItem->id,
                        'weight' => $oItem->declared_weight,
                        'category' => __('calendar.car_category.' . $oItem->category),
                        'address' => $oItem->address
                    ]);
                    $oItem->color = [
                        'primary' => '#ffa726',

                    ];
                    $oItem->resizable = [
                        'beforeStart' => false,
                        'afterEnd' => false,
                    ];
                    $oItem->draggable = false;
                    $oItem->allDay = false;
                    $oItem->meta = [
                        'location' => $oItem->address,
                        'notes' => $oItem->comment_user,
                        'type' => 'CAR',
                    ];
                    $oItem->type = 'CAR';
                    $lEvents[] = $oItem;
                }
            }
            $lBox = OrderBox::getByParams($filter);
            if ( $lBox->count ) {
                foreach ($lBox->list as $keyIndex => $oItem) {
                    $oItem->type = 'BOX';
                    $oItem->start = $oItem->delivery_at->timezone('Europe/Moscow');
                    $oItem->title = __('calendar.box_title', [
                        'id' => $oItem->id,
                        'box_type' => $oItem->box_type,
                        'box_count' => $oItem->box_count,
                        'address' => $oItem->address
                    ]);
                    $oItem->color = [
                        'primary' => '#009688',


                    ];
                    $oItem->resizable = [
                        'beforeStart' => false,
                        'afterEnd' => false,
                    ];
                    $oItem->draggable = false;
                    $oItem->allDay = false;
                    $oItem->meta = [
                        'location' => $oItem->address,
                        'notes' => $oItem->comment_user,
                        'type' => 'BOX',
                    ];
                    $oItem->type = 'BOX';
                    $lEvents[] = $oItem;
                }
            }

            _setParams([
                'events' => $lEvents
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }


        return _view();
    }

    // For template

    public function template(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);

        } catch (ValidationException $vEx) {
            _setParams([
                'errors' => $vEx->errors()
            ]);
        }

        return _view();
    }
}
