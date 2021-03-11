<?php

namespace Modules\Order\Http\Controllers;

use App\Events\OrderBoxWasCreated;
use App\Events\OrderCarWasCreated;
use App\Http\Controllers\Controller;
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


class OrderController extends Controller
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
                'user_id' => $user->id
            ]);
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
                'user_id' => $user->id
            ]);
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
    public function createCar(Request $request)
    {
        try {
            $this->validate($request, [
                // For address
                'address' => 'required',
                'lat' => 'required',
                'lng' => 'required',
                // For contact
                '_contact_name' => 'required',
                'email' => 'required|email',
                '_phone' => [
                    'required',
                    'regex:/[0-9]{9,15}/',
                    'numeric'
                ],
                // For legal if exists
                'inn' => [
                    'sometimes',
                    'numeric',
                    'regex:/^(\d{10}|\d{12})$/'
                ],
                'ogrn' => 'sometimes|numeric',
                'company' => 'sometimes|required',

                // For order
                'category' => [
                    'required',
                    Rule::in([
                        OrderCar::ORDER_CAR_CATEGORY_PLASTIC, OrderCar::ORDER_CAR_CATEGORY_CARDBOARD
                    ]),

                ],
                'weight' => 'required|numeric',

            ]);

            $user = Auth::user();
            Log::debug($request->all());
            $aOrder = [
                'user_id' => $user->id,
            ];
            // Fill address if not exist
            $oAddress = UserAddress::getByCoordinates([$request->lat, $request->lng], $user->id);
            if ( !$oAddress ) {
                $oAddress = new UserAddress();
                // TODO: need to check in area
                $aAdress = [

                    'address' => $request->address,
                    'lat' => $request->lat,
                    'lng' => $request->lng,
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
            $oContact = UserPhone::getByPhone($request->_phone, $user->id);
            if ( !$oContact ) {
                $oContact = new UserPhone();
                $aContact = [
                    'user_id' => $user->id,

                    'phone' => $request->_phone,
                    'email' => $request->email,
                    'full_name' => $request->_contact_name,

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

            // For PSR-81
            if ( $user->name == 'user' ) {
                $user->name = $request->_contact_name;
                $user->save();
            }

            $aOrder['phone_id'] = $oContact->id;
            $aOrder['contact_name'] = $request->_contact_name;
            $aOrder['contact_email'] = $oContact->email;
            $aOrder['contact_phone'] = $oContact->phone;
            $aOrder['contact_phone_confirmed'] = $oContact->phone_confirmed;
            $aOrder['contact_phone_confirmed_at'] = $oContact->phone_confirmed_at;
            // Fill legal if exists
            if ( isset($request->inn) && $request->inn ) {
                $oLegal = UserLegal::getByInn($request->inn, $user->id);
                if ( !$oLegal ) {
                    $oLegal = new UserLegal();
                    // TODO: add validate
                    $aLegal = [
                        'user_id' => $user->id,

                        'inn' => $request->inn,
                        'full_name' => $request->company,
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
            }

            $aOrder['status'] = OrderCar::ORDER_CAR_STATUS_WAIT_CONFIRMATION;
            $aOrder['category'] = $request->category;

            $aOrder['comment_user'] = $request->comment ? $request->comment : $request->notice ;
            if ( !$aOrder['comment_user'] ) {
                $aOrder['comment_user'] = ' ';
                $aOrder['comment_manager'] = ' ';
            }
            $aOrder['comment_manager'] = ' ';

            $aCode = randomPassword(8, 1,'upper_case,numbers');
            $aOrder['code'] = $aCode[0];
            $aOrder['declared_weight'] = $request->weight;

            $oOrder = new OrderCar();


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

            event(new OrderCarWasCreated($oOrder));

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
    public function createBox(Request $request)
    {
        try {
            $this->validate($request, [
                // For address
                'address' => 'required',
                'lat' => 'required',
                'lng' => 'required',
                // For contact
                '_contact_name' => 'required',
                'email' => 'required|email',
                '_phone' => [
                    'required',
                    'regex:/[0-9]{9,15}/',
                    'numeric'
                ],
                // For legal if exists
                'inn' => [
                    'sometimes',
                    'numeric',
                    'regex:/^(\d{10}|\d{12})$/'
                ],
                'ogrn' => 'sometimes|numeric',
                'company' => 'sometimes|required',

                // For order
                'order_type' => [
                    'required',
                    Rule::in([
                        OrderBox::ORDER_BOX_TYPE_RENT, OrderBox::ORDER_BOX_TYPE_SALE
                    ]),

                ],
                'box_count' => 'required|numeric',
                'box_type' => 'required|numeric|exists:box_types,id',



            ]);

            $user = Auth::user();
            $aOrder = [
                'user_id' => $user->id,
            ];
            // Fill address if not exist
            $oAddress = UserAddress::getByCoordinates([$request->lat, $request->lng], $user->id);
            if ( !$oAddress ) {
                $oAddress = new UserAddress();
                // TODO: need to check in area
                $aAdress = [

                    'address' => $request->address,
                    'lat' => $request->lat,
                    'lng' => $request->lng,
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
            $oContact = UserPhone::getByPhone($request->_phone, $user->id);
            if ( !$oContact ) {
                $oContact = new UserPhone();
                $aContact = [
                    'user_id' => $user->id,

                    'phone' => $request->_phone,
                    'email' => $request->email,
                    'full_name' => $request->_contact_name,

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

            // For PSR-81
            if ( $user->name == 'user' ) {
                $user->name = $request->_contact_name;
                $user->save();
            }
            $aOrder['phone_id'] = $oContact->id;
            $aOrder['contact_name'] = $request->_contact_name;
            $aOrder['contact_email'] = $oContact->email;
            $aOrder['contact_phone'] = $oContact->phone;
            $aOrder['contact_phone_confirmed'] = $oContact->phone_confirmed;
            $aOrder['contact_phone_confirmed_at'] = $oContact->phone_confirmed_at;
            // Fill legal if exists
            if ( isset($request->inn) && $request->inn ) {
                $oLegal = UserLegal::getByInn($request->inn, $user->id);
                if ( !$oLegal ) {
                    $oLegal = new UserLegal();
                    // TODO: add validate
                    $aLegal = [
                        'user_id' => $user->id,

                        'inn' => $request->inn,
                        'full_name' => $request->company,
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
            }

            $aOrder['status'] = OrderCar::ORDER_CAR_STATUS_WAIT_CONFIRMATION;

            $aOrder['order_type'] = $request->order_type;
            $aOrder['box_count'] = $request->box_count;
            $aOrder['box_type_id'] = $request->box_type;
            // Get Box type data
            $oBoxType = BoxType::getById($request->box_type);
            if ( $oBoxType ) {
                $aOrder['box_price'] = $oBoxType->price;
                $aOrder['box_type'] = $oBoxType->title;
            }
            $aOrder['box_time_type'] = $request->box_time_type;
            $aOrder['box_time_count'] = $request->box_time_count;




            $aOrder['comment_user'] = $request->comment ? $request->comment : $request->notice ;
            if ( !$aOrder['comment_user'] ) {
                $aOrder['comment_user'] = ' ';
                $aOrder['comment_manager'] = ' ';
            }
            $aOrder['comment_manager'] = ' ';

            $aCode = randomPassword(8, 1,'upper_case,numbers');
            $aOrder['code'] = $aCode[0];


            $oOrder = new OrderBox();


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

            $tOrderCar = OrderCar::lastOrder([
                'user_id' => Auth::id()
            ]);

            if ( $tOrderCar ) {
                $orderCar = $tOrderCar;
                $orderCar->load([
                    'addresses',
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


    public function getLastBoxRequest(Request $request)
    {
        try {
            $this->validate($request, [
                ''
            ]);
            $orderCar = null;

            $tOrderCar = OrderBox::lastOrder([
                'user_id' => Auth::id()
            ]);

            if ( $tOrderCar ) {
                $orderCar = $tOrderCar;
                $orderCar->load([
                    'addresses',
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
