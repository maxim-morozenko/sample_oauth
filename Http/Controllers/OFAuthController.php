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
 * Class OFAuthController
 * @package Pct\Oauth\Http\Controllers
 */

class OFAuthController extends OauthController
{



    /**
     * Class constructor.
     */
    public function __construct(){


    }


    /**
     * @api {get} /oauth/facebook/callback [FB] Callback.
     * @apiVersion 0.1.0
     * @apiDescription   Callback for google
     * @apiName Facebook callback url
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
        $objFUser = false;
        try {
            $objFUser = Socialite::driver('facebook')->user();
        } catch (InvalidStateException $isEx) {
            Log::error($isEx);
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        } catch (ClientException $isEx) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        } catch (\Exception $ex) {
            Log::error($ex);
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        if( !$objFUser ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        $intAuthUserId = null;
        $user = Auth::user();
        //Log::info("User object");
        //Log::info($user);
        if( !empty($user) ) {
            $intAuthUserId = (int)$user->id;
        }


        $arrFData = [
            'user_internal' => $objFUser->getId(),
            'token' => $objFUser->token,
            'email' => ( $objFUser->getEmail() ) ? $objFUser->getEmail() : 'none',
            'profile' => null,
            'token_secret' => 'null',//$objFUser->tokenSecret,
            'screen_name' => $objFUser->getName(),
            'avatar' => $objFUser->getAvatar(),
            'expires_stamp' => 1,
            'expires_date' => $objFUser->expiresIn,
            'active' => true,
            'hash' => md5($objFUser->getId() . time()),
            'type' => parent::$T_FACEBOOK
        ];

        $tblOAAll = new OAGlobal();

        //Log::info("Current user id - " . $intAuthUserId);

        if( !$intAuthUserId ) {
            //Registration authorization for not login user
            $objExistFAuth = $tblOAAll->getByUserInternal($objFUser->getId(), parent::$T_FACEBOOK);
            if( $objExistFAuth ) {

                if( !$objExistFAuth->user_id ) {
                    $tblUser = new User();

                    if ( $objExistFAuth->email ) {
                        $objUser = $tblUser->getByEmail($objExistFAuth->email);
                        if ( $objUser ) {
                            $objExistFAuth->user_id = $objUser->id;
                            $objExistFAuth->save();
                        }
                    }

                }

                if( !$objExistFAuth->user_id ) {
                    return response()->redirectTo(config('auth.oauth.redirects.registration'). $objExistFAuth->hash);

                } else {
                    //Authorization
                    return $this->authorizationByHash($objExistFAuth->hash, parent::$T_FACEBOOK);
                }

            }
        } else {
            //Append account to already auth user
            $arrFData['user_id'] = $intAuthUserId;

        }

        $objFacebook = $tblOAAll->createItem($arrFData);

        if( $intAuthUserId  && ($objFacebook instanceof OAGlobal) ) {
            //Redirect to profile page
            return response()->redirectTo(config('auth.oauth.redirects.profile'));
        }

        if( !($objFacebook instanceof OAGlobal) ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }


        if( !$objFacebook->user_id ) {
            $tblUser = new User();

            if ( $objFacebook->email ) {
                $objUser = $tblUser->getByEmail($objFacebook->email);
                if ( $objUser ) {
                    $objFacebook->user_id = $objUser->id;
                    $objFacebook->save();
                }
            }

        }

        if( $objFacebook->user_id ) {
            //Authorization
            return $this->authorizationByHash($objFacebook->hash, parent::$T_FACEBOOK);
        }



        return response()->redirectTo(config('auth.oauth.redirects.registration'). $objFacebook->hash);
    }

    /**
     * @api {get} /oauth/facebook/make [Facebook] Make auth url.
     * @apiVersion 0.1.0
     * @apiDescription   Get region by ID
     * @apiName Facebook make auth url
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
        return Socialite::driver('facebook')->redirect();

    }

    public function makeForApi()
    {
        $urlRedirect = route('oauth.make.facebook');
        return $this->make($urlRedirect);
    }




}