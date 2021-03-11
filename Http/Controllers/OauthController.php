<?php

namespace Modules\Oauth\Http\Controllers;

use App\Entities\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

use Modules\Oauth\Entities\OAGlobal;

class OauthController extends Controller
{
    public static $T_FACEBOOK = 'facebook';
    public static $T_GOOGLE   = 'google';
    public static $T_VK       = 'vk';
    public static $T_ODN      = 'odn';
    public static $T_INST     = 'instagram';



    public function authorizationByHash($strHash, $strType)
    {
        if( !$strHash || !$strType ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        $tblOauth = new OAGlobal();

        $objAuth = $tblOauth->getDataByHash($strHash, $strType);

        if (!$objAuth ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        if( !$objAuth->user_id ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }
        $tblUser = new User();
        $objUser = $tblUser->getById($objAuth->user_id);


        if ( !$objUser ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }
        if ( !$objUser->active ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        $sToken = $this->guard()->login($objUser);



        $sRedirectTo = _redirectAfterLogin();

        return response()->redirectTo($sRedirectTo)
            ->withHeaders(['x-jwt-token' => $sToken])
            ->cookie('_mb_a_t', $sToken, 20160, '/', config('api.domain'), true, false)
            ->cookie('_mb_a_tt', 'bearer', 20160, '/', config('api.domain'), true, false);
    }

    protected function guard()
    {
        return Auth::guard();
    }

    public function make($strUri)
    {
        _setParams([
            'redirect_to' => $strUri
        ]);

        return _view();
    }

    public function getInfoByHash(Request $request)
    {
        $strHash = $request->get('hash', null);
        $objValidator = Validator::make($request->all(), [
            'hash' => 'required|exists:oauth_global'
        ]);

        if ( $objValidator->fails() ) {
            _setParams([
                'errors' => $objValidator->errors()
            ]);
            return _view('oauth::index');
        }

        $tblGOAuth = new OAGlobal();
        $objGOAuth = $tblGOAuth->getDataByHash($strHash);
        if( !$objGOAuth ) {
            _setParams([
                'errors' => [
                    'NT_FND' => [
                        'Not found'
                    ]
                ]
            ]);
            return _view('oauth::index');
        }

        _setParams([
            'oauth' => $objGOAuth
        ]);
        return _view('oauth::index');
    }



    /**
     * @api {post} /oauth/remove/ [Global] remove token.
     * @apiVersion 0.1.0
     * @apiDescription   Required AUTH. remove token by hash
     * @apiName Remove token by hash
     * @apiGroup Oauth
     *
     *
     * @apiParam {String} hash
     *
     *
     * @apiError {Object[]} errors Errors data
     * @apiSuccess {Number} timestamp  Timestamp Available in dev.
     *
     *
     * @apiUse NotFoundError
     *

     */
    public function removeAuth() {
        $intAuthUserId = null;
        $user          = Auth::user();

        if( $user ) {
            $intAuthUserId = (int)$user->id;
        }

        if( !$intAuthUserId ) {
            return response()->json('not_auth', 403);
        }

        $strHash = request()->input('hash', null);

        if( !$strHash ) {
            _setParams([
                'errors' => [
                    'NT_AUTH' => [
                        'Not auth'
                    ]
                ]
            ]);
            return _view('oauth::index');
        }

        $tblOAuth = new OAGlobal();

        $objToken = $tblOAuth->getTokenByHashAndUserId($strHash, $intAuthUserId);

        if( !$objToken ) {
            _setParams([
                'errors' => [
                    'NT_AUTH' => [
                        'Not auth'
                    ]
                ]
            ]);
            return _view('oauth::index');
        }

        if( $objToken instanceof OAGlobal ) {
            $objToken->delete();
        }

        _setParams([]);
        return _view('oauth::index');

    }


    /**
     * @api {post} /oauth/remove/ [Global] remove token.
     * @apiVersion 0.1.0
     * @apiDescription   Required AUTH. remove token by hash
     * @apiName Remove token by hash
     * @apiGroup Oauth
     *
     *
     * @apiParam {String} hash
     *
     *
     * @apiError {Object[]} errors Errors data
     * @apiSuccess {Number} timestamp  Timestamp Available in dev.
     *
     *
     * @apiUse NotFoundError
     *

     */
    public function removeAuthByType() {
        $intAuthUserId = null;
        $user          = Auth::user();


        if( $user ) {
            $intAuthUserId = (int)$user->id;
        }

        if( !$intAuthUserId ) {
            return response()->json('not_auth', 403);
        }

        $type = request()->input('type', null);

        if( !$type ) {
            _setParams([
                'errors' => [
                    'NT_AUTH' => [
                        'Not auth'
                    ]
                ]
            ]);

            return _view('oauth::index');
        }


       $OAuthRecords      =  OAGlobal::whereUserId($intAuthUserId)->whereType($type)->get();
       $OAuthRecordsCount =  $OAuthRecords->count();

        $OAuthRecords->each(function($OAuthRecord){
            $OAuthRecord->delete();
        });



        _setParams([
            'status' => 'OK',
            'count'  => $OAuthRecordsCount,
        ]);
        return _view('oauth::index');

    }
}
