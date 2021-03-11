<?php
/**
 * Created by PhpStorm.
 * User: dev
 * Date: 29/09/16
 * Time: 20:50
 */

namespace Modules\Oauth\Http\Controllers;


use App\Entities\User;
use Carbon\Carbon;
use GuzzleHttp\Exception\ClientException;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use League\Flysystem\Exception;
use Modules\Oauth\Entities\OAGlobal;



/**
 * Class OVAuthController
 * @package Mena\Modules\Oauth\Facebook\Controllers
 *
 */
class OVAuthController extends OauthController
{



    /**
     * Class constructor.
     */
    public function __construct(){


    }


    /**
     * @api {get} /oauth/vkontakte/callback [VK] Callback.
     * @apiVersion 0.1.0
     * @apiDescription   Callback for google
     * @apiName Vkontakte callback url
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

        $objVUser = null;
        try {
            $objVUser = Socialite::driver('vkontakte')->user();


        }catch (InvalidStateException $isEx) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        } catch (ClientException $isEx) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        } catch (Exception $e) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        if( !$objVUser ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }

        $sEmail = ( $objVUser->getEmail() ) ? $objVUser->getEmail() : 'none';
        if ($sEmail == 'none') {
            if ( isset($objVUser->accessTokenResponseBody) && isset($objVUser->accessTokenResponseBody['email']) ) {
                $sEmail = $objVUser->accessTokenResponseBody['email'] ? $objVUser->accessTokenResponseBody['email'] : 'none';
            }
        }

        $intAuthUserId = null;
        $user = Auth::user();
        //Log::info("User object");
        //Log::info($user);
        if( !empty($user) ) {
            $intAuthUserId = (int)$user->id;
        }

        $arrVData = [
            'user_internal' => $objVUser->getId(),
            'token' => $objVUser->token,
            'email' => $sEmail,
            'profile' => null,
            'token_secret' => 'none', //$objVUser->tokenSecret,
            'screen_name' => $objVUser->getName(),
            'avatar' => $objVUser->getAvatar(),
            'expires_stamp' => 1,
            'expires_date' => Carbon::now(),
            'active' => true,
            'hash' => md5($objVUser->getId() . time()),
            'type' => parent::$T_VK
        ];





        $tblOAAll = new OAGlobal();

        //Log::info("Current user id - " . $intAuthUserId);

        if( !$intAuthUserId ) {
            //Registration authorization for not login user
            $objExistVAuth = $tblOAAll->getByUserInternal($objVUser->getId(), parent::$T_VK);
            if( $objExistVAuth ) {

                if( !$objExistVAuth->user_id ) {
                    $tblUser = new User();

                    if ( $objExistVAuth->email ) {
                        $objUser = $tblUser->getByEmail($objExistVAuth->email);
                        if ( $objUser ) {
                            $objExistVAuth->user_id = $objUser->id;
                            $objExistVAuth->save();
                        }
                    }

                }

                if( !$objExistVAuth->user_id ) {
                    //Registration
                    return response()->redirectTo(config('auth.oauth.redirects.registration'). $objExistVAuth->hash);

                } else {
                    //Authorization
                    return $this->authorizationByHash($objExistVAuth->hash, parent::$T_VK);
                }

            }
        } else {
            //Append account to already auth user
            $arrVData['user_id'] = $intAuthUserId;

        }

        $objVk = $tblOAAll->createItem($arrVData);


        if( $intAuthUserId  && ($objVk instanceof OAGlobal) ) {
            //Redirect to profile page
            return response()->redirectTo(config('auth.oauth.redirects.profile'));
        }

        if( !($objVk instanceof OAGlobal) ) {
            return response()->redirectTo(config('auth.oauth.redirects.error'));
        }


        if( !$objVk->user_id ) {
            $tblUser = new User();

            if ( $objVk->email ) {
                $objUser = $tblUser->getByEmail($objVk->email);
                if ( $objUser ) {
                    $objVk->user_id = $objUser->id;
                    $objVk->save();
                }
            }

        }

        if( $objVk->user_id ) {
            //Authorization
            return $this->authorizationByHash($objVk->hash, parent::$T_VK);
        }




        return response()->redirectTo(config('auth.oauth.redirects.registration'). $objVk->hash);
    }

    /**
     * @api {get} /oauth/vkontakte/make [VK] Make auth url.
     * @apiVersion 0.1.0
     * @apiDescription   Get region by ID
     * @apiName Vkontakte make auth url
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
        return Socialite::driver('vkontakte')->redirect();

    }

    public function makeForApi()
    {
        $urlRedirect = route('oauth.make.vk');
        return $this->make($urlRedirect);
    }

}