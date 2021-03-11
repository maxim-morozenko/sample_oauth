<?php

namespace Modules\Oauth\Entities;

use App\Entities\Traits\BaseModelTrait;
use Exception;
use Illuminate\Database\Eloquent\Model;

class OAGlobal extends Model
{
    use BaseModelTrait;

    protected $primaryKey = 'id';
    protected $table = 'oauth_global';

    protected $fillable = [
        'user_id',
        'user_internal',

        'email',
        'profile',

        'screen_name',
        'avatar',
        'expires_stamp',
        'expires_date',
        'active',
        'dev_answer',
        'hash',
        'type',
        'token',
        'token_secret',

    ];

    protected $dates = [ 'created_at', 'updated_at'];

    protected $_fPrefix = '';


    protected $maps = [


    ];


    protected $appends = [

    ];

    protected $casts = [
        'id' => 'integer',
        'user_id' => 'integer',
        'user_internal' => 'string',
        'token' => 'string',
        'hash' => 'string',
        'email' => 'string',
        'profile' => 'string',
        'token_secret' => 'string',
        'screen_name' => 'string',
        'avatar' => 'string',
        'dev_answer' => 'string',
        'expires_stamp' => 'integer',
        'expires_date' => 'datetime',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'type' => 'string'
    ];

    protected $hidden = [
        'active', 'created_at', 'updated_at',
        'token', 'token_secret', 'dev_answer',
        'profile',
    ];

    protected $_cRequired = [
        'user_internal',
        'token',
        'email',
        'token_secret',
        'screen_name',
        'avatar',
        'expires_stamp',
        'expires_date',
        'active',
        'hash',
        'type'
    ];

    protected $_cCreate = [

        'user_id' => 'integer',
        'user_internal' => 'string',
        'token' => 'string',
        'hash' => 'string',
        'email' => 'string',
        'profile' => 'string',
        'token_secret' => 'string',
        'screen_name' => 'string',
        'avatar' => 'string',
        'dev_answer' => 'string',
        'expires_stamp' => 'integer',
        'expires_date' => 'datetime',
        'active' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'type' => 'string'

    ];


    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public  function user()
    {
        return $this->hasOne('Pct\Account\Entities\User', 'id', 'user_id');
    }

    /**
     * @param $query
     * @return mixed
     */
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }



    /**
     * @param $arrData
     * @return $this|bool
     * @throws Exception
     */
    public function createItem($arrData)
    {



        $arrPrepared = $this->_mapBeforeSave($arrData);
        if( !is_array($arrPrepared) ) {
            return false;
        }

        //Check if data exist
        $objCurrent = $this->getByUserInternal($arrPrepared['user_internal'], $arrPrepared['type']);
        if( !$objCurrent ) {
            $objItem = $this;
        } else {
            $objItem = $objCurrent;

        }



        foreach ( $this->_cCreate as $keyIndex => $keyField ) {
            if( !isset($arrPrepared[$keyIndex]) ) {
                continue;
            }
            $objItem->{$keyIndex} = $arrPrepared[$keyIndex];
        }

        $flgCreate = $objItem->save();
        if( !$flgCreate ) {
            return $flgCreate;
        }


        return $this;
    }


    protected function _mapBeforeSave($arrIn) {

        $arrOut = [];

        if( !isset($arrIn['hash']) ) {
            $this->_prepareHash($arrIn);
        }
        if ( empty($arrIn['hash']) ) {
            $this->_prepareHash($arrIn);
        }

        foreach ($this->_cRequired as $keyIndex => $keyField ) {
            if( !isset($arrIn[$keyField]) ) {
                return false;
            }
        }

        foreach ( $arrIn as $keyField => $strValue ) {
            $arrOut[$keyField] = $strValue;
        }

        return $arrOut;
    }

    /**
     * @param  $arrIn
     * @return bool
     */
    protected function _prepareHash(&$arrIn) {
        if( !$arrIn ) {
            return false;
        }

        $token = $arrIn['token'];
        $user_internal = isset($arrIn['user_internal'])?$arrIn['user_internal']: null;
        $time = time();

        $arrIn['hash'] = $this->makeHash($token, $user_internal, $time);

    }

    /**
     * @param string $token
     * @param string $user_internal
     * @param int $time
     * @return string
     */
    public function makeHash($token = null, $user_internal = null, $time = null)
    {
        return md5(($token.$user_internal.$time));
    }


    /**
     * @param string $userInternal
     * @param string $strType
     * @return bool
     */
    public function getByUserInternal($userInternal, $strType)
    {
        $strUserInternal = $userInternal;
        if( !$strUserInternal || !$strType ) {
            return false;
        }

        $objSelect = $this->where('user_internal', $strUserInternal)
            ->where('type', $strType);

        $objItem = $objSelect->first();

        if( !$objItem ) {
            return false;
        }

        return $objItem;
    }


    /**
     * @param string $strHash
     * @param string $strType
     * @return bool
     */
    public function getDataByHash($strHash, $strType = null)
    {
        if( !$strHash ) {
            return false;
        }
        $objSelect = $this->where('hash', $strHash);

        if($strType) {
            $objSelect->
            where('type', $strType);
        }

        $objItem = $objSelect->first();

        if( !$objItem ) {
            return false;
        }

        return $objItem;


    }

    /**
     *
     */
    public function mapForUser()
    {
        return $this;
    }

    public function getTokenByHashAndUserId($strHash, $intAuthUserId)
    {
        if ( !$strHash || $intAuthUserId ) {
            return false;
        }

        $objSelect = $this->where('user_id', $intAuthUserId);

        $objSelect->where('hash', $strHash);

        $objItem = $objSelect->first();

        return $objItem;
    }
}
