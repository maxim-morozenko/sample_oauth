<?php

namespace Modules\Order\Entities;

use App\Entities\Traits\BaseModelTrait;
use App\Entities\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Modules\Account\Entities\UserAddress;
use Modules\Account\Entities\UserLegal;
use Modules\Account\Entities\UserPhone;

class OrderCar extends Model
{
    use BaseModelTrait, SoftDeletes;


    const ORDER_CAR_STATUS_NEW = 0;

    const ORDER_CAR_STATUS_WAIT_CONFIRMATION = 1;
    const ORDER_CAR_STATUS_CANT_CONFIRMATION = 11;
    const ORDER_CAR_STATUS_CONFIRMED = 12;

    const ORDER_CAR_STATUS_IN_PROGRESS = 2;
    const ORDER_CAR_STATUS_DONE = 3;

    const ORDER_CAR_STATUS_CANCEL = -1;

    const ORDER_CAR_CATEGORY_PLASTIC = 'PLASTIC';
    const ORDER_CAR_CATEGORY_CARDBOARD = 'CARDBOARD';


    protected $table = 'orders_car';
    protected $primaryKey = 'id';


    protected $fillable = [
        'user_id',

        'code',


        // Relation V
        'legal_id',

        // Only for search
        'legal_inn',
        'legal_title',

        // Relation V
        'address_id',

        // Only for search
        'address_lat',
        'address_lng',
        'address',

        // Relation  V
        'phone_id',

        // Only for search
        'contact_name',
        'contact_email',
        'contact_phone',

        'contact_phone_confirmed',
        'contact_phone_confirmed_at',

        'status',
        'previous_status',
        'status_changed_at',

        'category',

        'declared_weight',
        'received_weight',
        'control_weight',


        'comment_user',
        'comment_manager',

        'confirmed_by_manager',
        'confirmed_by_manager_at',

        // Relation V
        'manager_id',


        'planed_delivery_at',
        'delivery_at',
        // Relation
        'delivery_id'

    ];

    protected $casts = [
        'id' => 'integer',

        'user_id' => 'integer',

        'code' => 'string',


        // Relation
        'legal_id' => 'integer',

        // Only for search
        'legal_inn' => 'string',
        'legal_title' => 'string',

        // Relation
        'address_id' => 'integer',

        // Only for search
        'address_lat' => 'string',
        'address_lng' => 'string',
        'address' => 'string',

        // Relation
        'phone_id' => 'integer',

        // Only for search
        'contact_name' => 'string',
        'contact_email' => 'string',
        'contact_phone' => 'string',

        'contact_phone_confirmed' => 'boolean',


        'status' => 'integer',
        'previous_status' => 'integer',


        'category' => 'string',

        'declared_weight' => 'float',
        'received_weight' => 'integer',
        'control_weight' => 'integer',


        'comment_user' => 'string',
        'comment_manager' => 'string',

        'confirmed_by_manager' => 'boolean',


        // Relation
        'manager_id' => 'integer',




        // Relation
        'delivery_id' => 'integer',



        'contact_phone_confirmed_at' => 'datetime:Y-m-d\TH:i:s',
        'status_changed_at' => 'datetime:Y-m-d\TH:i:s',
        'confirmed_by_manager_at' => 'datetime:Y-m-d\TH:i:s',
        'planed_delivery_at' => 'datetime:Y-m-d\TH:i:s',
        'delivery_at' => 'datetime:Y-m-d\TH:i:s',

        'created_at' => 'datetime:Y-m-d\TH:i:s',
        'updated_at' => 'datetime:Y-m-d\TH:i:s',
        'deleted_at' => 'datetime:Y-m-d\TH:i:s',

    ];

    protected $dates = [
        'created_at', 'updated_at',
        'contact_phone_confirmed_at', 'status_changed_at',
        'confirmed_by_manager_at',
        'planed_delivery_at', 'delivery_at'

    ];

    protected $hidden = [

    ];


    /**
     * Validation
     */


    /**
     * Relations
     */

    public function user()
    {
        return $this->hasOne(User::class, 'id', 'user_id');
    }

    public function manager()
    {
        return $this->hasOne(User::class, 'id', 'manager_id');
    }

    public function legal()
    {
        return $this->hasOne(UserLegal::class, 'id', 'legal_id');
    }

    public function addressItem()
    {
        return $this->hasOne(UserAddress::class, 'id', 'address_id');
    }

    public function addresses()
    {
        return $this->hasOne(UserAddress::class, 'id', 'address_id');
    }

    public function phone()
    {
        return $this->hasOne(UserPhone::class, 'id', 'phone_id');
    }

    public function driver()
    {
        return $this->hasOne(User::class, 'id', 'delivery_id');
    }


    public function delivery()
    {
        // TODO: For feature
        return null;
    }

    // Setters && Getters
    //
    //
    public function getDeclaredWeightAttribute()
    {

        if ( !$this->attributes['declared_weight'] ) {
            return 0;
        }

        return ($this->attributes['declared_weight'] / 1000);
    }

    public function setDeclaredWeightAttribute($value)
    {

        if ( !$value ) {
            $this->attributes['declared_weight'] = 0;
        }
        $this->attributes['declared_weight'] = $value * 1000;

    }


    public function getReceivedWeightAttribute()
    {
        if ( !$this->attributes['received_weight'] ) {
            return 0;
        }
        return ($this->attributes['received_weight'] / 1000);
    }

    public function setReceivedWeightAttribute($value)
    {
        if ( !$value ) {
            $this->attributes['received_weight'] = 0;
        }
        $this->attributes['received_weight'] = $value * 1000;
    }


    public function getControlWeightAttribute()
    {
        if ( !$this->attributes['control_weight'] ) {
            return 0;
        }
        return ($this->attributes['control_weight'] / 1000);
    }

    public function setControlWeightAttribute($value)
    {
        if ( !$value ) {
            $this->attributes['control_weight'] = 0;
        }
        $this->attributes['control_weight'] = $value * 1000;
    }

    /**
     * Custom
     */

    public static  function listStatuses()
    {
        $aStatuses = [
            // FOR PSR-77
//            [
//                'id' => self::ORDER_CAR_STATUS_NEW,
//                'title' => __('users_services.ORDERS.STATUSES.NEW')
//            ],
            [
                'id' => self::ORDER_CAR_STATUS_WAIT_CONFIRMATION,
                'title' => __('users_services.ORDERS.STATUSES.WAIT_CONFIRMATION')
            ],
            [
                'id' => self::ORDER_CAR_STATUS_CANT_CONFIRMATION,
                'title' => __('users_services.ORDERS.STATUSES.CANT_CONFIRMATION')
            ],
            // FOR PSR-77
//            [
//                'id' => self::ORDER_CAR_STATUS_CONFIRMED,
//                'title' => __('users_services.ORDERS.STATUSES.CONFIRMED')
//            ],
            [
                'id' => self::ORDER_CAR_STATUS_IN_PROGRESS,
                'title' => __('users_services.ORDERS.STATUSES.IN_PROGRESS')
            ],
            [
                'id' => self::ORDER_CAR_STATUS_DONE,
                'title' => __('users_services.ORDERS.STATUSES.DONE')
            ],
            [
                'id' => self::ORDER_CAR_STATUS_CANCEL,
                'title' => __('users_services.ORDERS.STATUSES.CANCEL')
            ],
        ];

        return $aStatuses;
    }
    /**
     * List Car Orders By params
     * @param array $aFilter
     * @param bool $all
     * @return mixed
     */
    public static function getByParams($aFilter = [], $all = false)
    {
        $oSelect = self::where('id', '<>', null);


        $user = Arr::get($aFilter, 'user_id', null);
        if ($user) {
            $oSelect->where('user_id', $user);
        }

        $status = Arr::get($aFilter, 'status', null);
        if ( !is_null($status) ) {
            $oSelect->where('status', $status);
        }

        $aStatuses = Arr::get($aFilter, 'statuses', null);
        if ( $aStatuses && !$status ) {
            $oSelect->whereIn('status', $aStatuses);
        }

        $iDriver = Arr::get($aFilter, 'driver_id', null);
        if ( $iDriver) {
            $oSelect->where('delivery_id', $iDriver);
        }

        $createdAt = Arr::get($aFilter, 'created_at', null);
        if ( $createdAt ) {
            try {
                $dCreatedAt = Carbon::parse($createdAt);
                $oSelect->whereDate('created_at', $dCreatedAt->timezone('Europe/Moscow'));

                Log::debug($dCreatedAt->timezone('Europe/Moscow'));
            } catch (\Exception $ex) {
                Log::error("[ORDER_CAR]: Filter have wrong date");
            }
        }

        $deliveredAt = Arr::get($aFilter, 'delivered_at', null);
        if ( $deliveredAt ) {
            try {
                $dDAt = Carbon::parse($deliveredAt);
                $oSelect->whereDate('delivery_at', $dDAt->timezone('Europe/Moscow'));


            } catch (\Exception $ex) {
                Log::error("[ORDER_CAR]: Filter have wrong date");
            }
        }

        $sCode = Arr::get($aFilter, 'code', null);
        if ( $sCode ) {
            $oSelect->where('code', $sCode);
        }

        $sID = Arr::get($aFilter, 'id', null);
        if ( $sID ) {
            $oSelect->where('id', $sID);
        }


        // Filter for calendar
        $oStartDate = Arr::get($aFilter, 'start_date');
        if ( $oStartDate ) {
            /**
             * @var Carbon $oStartDate
             */
            $oSelect->where('delivery_at', '>=', $oStartDate->timezone('Europe/Moscow'));
        }

        $oEndDate = Arr::get($aFilter, 'end_date');
        if ( $oEndDate ) {
            /**
             * @var Carbon $oEndDate
             */
            $oSelect->where('delivery_at', '<=', $oEndDate->timezone('Europe/Moscow'));
        }


        // Address
        $sAddress = Arr::get($aFilter, 'address', null);
        if ( $sAddress ) {
            $oSelect->where('address', 'ILIKE', '%' .  $sAddress . '%');
        }
        // Phone
        $sPhone = Arr::get($aFilter, 'phone', null);
        if ( $sPhone ) {
            $oSelect->where('contact_phone', 'ILIKE', '%' .  $sPhone . '%');
        }
        // Inn
        $sINN = Arr::get($aFilter, 'inn', null);
        if ( $sINN ) {
            $oSelect->where('legal_inn', 'ILIKE', '%' .  $sINN . '%');
        }


        $jstCount = Arr::get($aFilter, 'just_count', null);
        if ( $jstCount ) {
            return $oSelect->count();
        }

        $forAdmin = Arr::get($aFilter, 'for_admin', null);
        if (!$forAdmin) {
            $sortField = Arr::get($aFilter, 'sortField', null);
            if ( !$sortField ) {
                $oSelect->orderBy('created_at', 'DESC');
            } else {
                $sortDirection = Arr::get($aFilter, 'sortDir', 'DESC');
                $oSelect->orderBy($sortField, $sortDirection);
            }
        } else {
            $sortField = Arr::get($aFilter, 'sortField', null);
            if ( !$sortField ) {
                $oSelect->orderBy('delivery_at', 'ASC');
            } else {
                $sortDirection = Arr::get($aFilter, 'sortDir', 'DESC');
                $oSelect->orderBy($sortField, $sortDirection);
            }

        }

        $oSelect->with(['driver']);

        //Log::debug($oSelect->toSql());
        
        $count = $oSelect->count();

        $onPage = Arr::get($aFilter, 'onPage', null);
        if ( $onPage ) {
            $oSelect->limit($onPage);
        }
        $pageIndex = Arr::get($aFilter, 'pageIndex', 0);
        if ( !is_null($pageIndex) && $onPage ) {
            $oSelect->offset($pageIndex * $onPage);
        }



        if ( $all ) {
            $list = $oSelect->withTrashed()->get();

            return (object)[
                'list' => $list,
                'count' => $count
            ];
        } else {
            $list = $oSelect->get();

            return (object)[
                'list' => $list,
                'count' => $count
            ];
        }
    }

    public static function getByParamsForAnalytics($aFilter = [], $all = false)
    {
        $oSelect = self::where('id', '<>', null);


        $user = Arr::get($aFilter, 'user_id', null);
        if ($user) {
            $oSelect->where('user_id', $user);
        }

        $status = Arr::get($aFilter, 'status', null);
        if ( !is_null($status) ) {
            $oSelect->where('status', $status);
        }

        $aStatuses = Arr::get($aFilter, 'statuses', null);
        if ( $aStatuses && !$status ) {
            $oSelect->whereIn('status', $aStatuses);
        }

        $iDriver = Arr::get($aFilter, 'driver_id', null);
        if ( $iDriver) {
            $oSelect->where('delivery_id', $iDriver);
        }

        $createdAt = Arr::get($aFilter, 'created_at', null);
        if ( $createdAt ) {
            try {
                $dCreatedAt = Carbon::parse($createdAt);
                $oSelect->whereDate('created_at', $dCreatedAt->timezone('Europe/Moscow'));

                Log::debug($dCreatedAt->timezone('Europe/Moscow'));
            } catch (\Exception $ex) {
                Log::error("[ORDER_CAR]: Filter have wrong date");
            }
        }

        $deliveredAt = Arr::get($aFilter, 'delivered_at', null);
        if ( $deliveredAt ) {
            try {
                $dDAt = Carbon::parse($deliveredAt);
                $oSelect->whereDate('delivery_at', $dDAt->timezone('Europe/Moscow'));


            } catch (\Exception $ex) {
                Log::error("[ORDER_CAR]: Filter have wrong date");
            }
        }

        $sCode = Arr::get($aFilter, 'code', null);
        if ( $sCode ) {
            $oSelect->where('code', $sCode);
        }

        $sID = Arr::get($aFilter, 'id', null);
        if ( $sID ) {
            $oSelect->where('id', $sID);
        }


        // Filter for calendar
        $oStartDate = Arr::get($aFilter, 'start_date');
        if ( $oStartDate ) {
            /**
             * @var Carbon $oStartDate
             */
            $oSelect->where('delivery_at', '>=', $oStartDate->timezone('Europe/Moscow'));
        }

        $oEndDate = Arr::get($aFilter, 'end_date');
        if ( $oEndDate ) {
            /**
             * @var Carbon $oEndDate
             */
            $oSelect->where('delivery_at', '<=', $oEndDate->timezone('Europe/Moscow'));
        }


        // Address
        $sAddress = Arr::get($aFilter, 'address', null);
        if ( $sAddress ) {
            $oSelect->where('address', 'ILIKE', '%' .  $sAddress . '%');
        }
        // Phone
        $sPhone = Arr::get($aFilter, 'phone', null);
        if ( $sPhone ) {
            $oSelect->where('contact_phone', 'ILIKE', '%' .  $sPhone . '%');
        }
        // Inn
        $sINN = Arr::get($aFilter, 'inn', null);
        if ( $sINN ) {
            $oSelect->where('legal_inn', 'ILIKE', '%' .  $sINN . '%');
        }

        // Sum Weight
        $bSumWeight = Arr::get($aFilter, 'sum_weight', null);
        if ( $bSumWeight ) {
            $sCategory = Arr::get($aFilter, 'category', null);
            $aPeriod = Arr::get($aFilter, 'period', null);
            $oSelect->select(DB::raw('
                (SELECT SUM(received_weight) FROM orders_car
                WHERE orders_car.status IN (' . OrderCar::ORDER_CAR_STATUS_DONE . ')
                AND orders_car.category = \'' .  $sCategory . '\'
                AND orders_car.created_at between ' . $aPeriod[0] . ' AND ' .  $aPeriod[1] . ') as count_received_weight_plastic
            '),
                DB::raw('
                (SELECT SUM(declared_weight) FROM orders_car
                WHERE orders_car.status IN (' . OrderCar::ORDER_CAR_STATUS_DONE . ')
                AND orders_car.category = \'' .  $sCategory . '\'
                AND orders_car.created_at between ' . $aPeriod[0] . ' AND ' .  $aPeriod[1] . ') as count_declared_weight_plastic
            '));


            $jstCount = Arr::get($aFilter, 'just_count', null);

            if ( $jstCount ) {
                dd($oSelect->toSql());
                $counter = $oSelect->get();
                return $counter;
            }
        }

        // For period
        $aPeriod = Arr::get($aFilter, 'period', null);
        if ( $aPeriod && !empty($aPeriod) ) {
            $oSelect->whereBetween('created_at', $aPeriod);
        }



        // For category
        $sCategory = Arr::get($aFilter, 'category', null);
        if ( $sCategory ) {
            $oSelect->where('category', $sCategory);
        }



        $jstCount = Arr::get($aFilter, 'just_count', null);
        if ( $jstCount ) {
            return $oSelect->count();
        }

        $forAdmin = Arr::get($aFilter, 'for_admin', null);
        if (!$forAdmin) {
            $sortField = Arr::get($aFilter, 'sortField', null);
            if ( !$sortField ) {
                $oSelect->orderBy('created_at', 'DESC');
            } else {
                $sortDirection = Arr::get($aFilter, 'sortDir', 'DESC');
                $oSelect->orderBy($sortField, $sortDirection);
            }
        } else {
            $sortField = Arr::get($aFilter, 'sortField', null);
            if ( !$sortField ) {
                $oSelect->orderBy('delivery_at', 'ASC');
            } else {
                $sortDirection = Arr::get($aFilter, 'sortDir', 'DESC');
                $oSelect->orderBy($sortField, $sortDirection);
            }

        }

        $oSelect->with(['driver']);

        //Log::debug($oSelect->toSql());

        $count = $oSelect->count();

        $onPage = Arr::get($aFilter, 'onPage', null);
        if ( $onPage ) {
            $oSelect->limit($onPage);
        }
        $pageIndex = Arr::get($aFilter, 'pageIndex', 0);
        if ( !is_null($pageIndex) && $onPage ) {
            $oSelect->offset($pageIndex * $onPage);
        }



        if ( $all ) {
            $list = $oSelect->withTrashed()->get();

            return (object)[
                'list' => $list,
                'count' => $count
            ];
        } else {
            $list = $oSelect->get();

            return (object)[
                'list' => $list,
                'count' => $count
            ];
        }
    }


    public static function getSumsWeight($aFilter)
    {
        $sCategory = Arr::get($aFilter, 'category', null);
        $aPeriod = Arr::get($aFilter, 'period', null);
        $sFieldForSum = Arr::get($aFilter, 'field_for_sum', null);
        $sumData = DB::select('
                SELECT SUM(orders_car.'. $sFieldForSum .') as sum_'. $sFieldForSum .' FROM orders_car
                WHERE orders_car.status IN (' . OrderCar::ORDER_CAR_STATUS_DONE . ')
                AND orders_car.category = \'' .  $sCategory . '\'
                AND orders_car.created_at between \'' . $aPeriod[0] . '\' AND \'' .  $aPeriod[1] . '\'
            ');



        return $sumData;
    }


    public static function lastOrder($aFilter)
    {
        $items = self::getByParams($aFilter);
        $item = null;
        if ( $items  &&  $items->list && ($items->list instanceof Collection) ) {
            $items = $items->list;
            /**
             * @var Collection $items
             */
            $item = $items->first();
        }

        return $item;
    }

    public static function lastOrderForAdmin($aFilter)
    {
        $items = self::getByParams($aFilter);
        $item = null;
        if ( $items  &&  $items->list && ($items->list instanceof Collection) ) {
            $items = $items->list;
            /**
             * @var Collection $items
             */
            $item = $items->first();
        }

        return $item;
    }
}
