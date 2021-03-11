<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 29/09/16
 * Time: 20:50
 */

namespace Modules\Oauth\Http\Controllers;


use App\Entities\User;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Modules\Oauth\Entities\OAGlobal;


/**
 * Class OGAuthController
 * @package Mena\Modules\Oauth\Google\Controllers
 *
 */
class OGAuthController extends OauthController
{



    /**
     * Class constructor.
     */
    public function __construct(){

    }


    /**
     * @api {get} /oauth/google/callback [Google] Callback.
     * @apiVersion 0.1.0
     * @apiDescription   Callback for google
     * @apiName Google callback url
     * @apiGroup Oauth
     *
     *
     *
     *
     * @apiError {Object[]} errors Errors data
     * @apiSuccess {Number} timestamp  Timestamp Available in dev.
     *
     * @apiSuccessExample {json} Success-Response:
     * {
     *
     * }
     *
     * @apiUse NotFoundError
     *

     */
    public function index() {
        $objGUser = false;
        try {
            $objGUser = Socialite::driver('google')->user();
        } catch (InvalidStateException $isEx) {
            Log::error($isEx);
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        } catch (ClientException $isEx) {
            Log::error($isEx);
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        } catch (\Exception $ex) {
            Log::error($ex);
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        if( !$objGUser ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        $intAuthUserId = null;
        $user = Auth::user();
        //Log::info("User object");
        //Log::info($user);
        if( !empty($user) ) {
            $intAuthUserId = (int)$user->id;
        }

        $arrGData = [
            'user_internal' => $objGUser->getId(),
            'token' => $objGUser->token,
            'email' => ( $objGUser->getEmail() ) ? $objGUser->getEmail() : 'none',
            'profile' => null,
            'token_secret' => 'none', //$objGUser->tokenSecret,
            'screen_name' => $objGUser->getName(),
            'avatar' => $objGUser->getAvatar(),
            'expires_stamp' => 1,
            'expires_date' => $objGUser->expiresIn,
            'active' => true,
            'hash' => md5($objGUser->getId() . time()),
            'type' => parent::$T_GOOGLE
        ];

        $tblOAAll = new OAGlobal();


        //Log::info("Current user id - " . $intAuthUserId);

        if( !$intAuthUserId ) {
            //Registration authorization for not login user
            $objExistGAuth = $tblOAAll->getByUserInternal($objGUser->getId(), parent::$T_GOOGLE);
            if( $objExistGAuth ) {

                if( !$objExistGAuth->user_id ) {
                    $tblUser = new User();

                    if ( $objExistGAuth->email ) {
                        $objUser = $tblUser->getByEmail($objExistGAuth->email);
                        if ( $objUser ) {
                            $objExistGAuth->user_id = $objUser->id;
                            $objExistGAuth->save();
                        }
                    }

                }

                if( !$objExistGAuth->user_id ) {

                    //Registration
                    return response()->redirectTo(config('auth.oauth.redirects.registration'). $objExistGAuth->hash);

                } else {
                    //Authorization
                    return $this->authorizationByHash($objExistGAuth->hash, parent::$T_GOOGLE);
                }

            }
        } else {
            //Append account to already auth user
            $arrGData['user_id'] = $intAuthUserId;

        }

        //Check if already exist

        $objGoogle = $tblOAAll->createItem($arrGData);

        if( $intAuthUserId  && ($objGoogle instanceof OAGlobal) ) {
            //Redirect to profile page
            return response()->redirectTo(config('auth.oauth.redirects.profile'));
        }


        if( !($objGoogle instanceof OAGlobal) ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        if( !$objGoogle->user_id ) {
            $tblUser = new User();

            if ( $objGoogle->email ) {
                $objUser = $tblUser->getByEmail($objGoogle->email);
                if ( $objUser ) {
                    $objGoogle->user_id = $objUser->id;
                    $objGoogle->save();
                }
            }

        }

        if( $objGoogle->user_id ) {
            //Authorization
            return $this->authorizationByHash($objGoogle->hash, parent::$T_GOOGLE);
        }

        return response()->redirectTo(config('auth.oauth.redirects.registration'). $objGoogle->hash);
    }

    /**
     * @api {get} /oauth/google/make [Google] Make auth url.
     * @apiVersion 0.1.0
     * @apiDescription   Get region by ID
     * @apiName Google make auth url
     * @apiGroup Oauth
     *
     *
     *
     *
     * @apiError {Object[]} errors Errors data
     * @apiSuccess {Number} timestamp  Timestamp Available in dev.
     *
     * @apiSuccessExample {json} Success-Response:
     * {
     *      "redirect_uri": "http://url.route",
     * }
     *
     * @apiUse NotFoundError
     *

     */
    public function makeUrlAuth() {
        return Socialite::driver('google')->redirect();

    }


    public function makeForApi()
    {
        $urlRedirect = route('oauth.make.google');
        return $this->make($urlRedirect);
    }
}