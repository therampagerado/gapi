<?php
/**
 * 2007-2016 PrestaShop
 *
 * thirty bees is an extension to the PrestaShop e-commerce software developed by PrestaShop SA
 * Copyright (C) 2017 thirty bees
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License (AFL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/afl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@thirtybees.com so we can send you a copy immediately.
 *
 * @author    thirty bees <modules@thirtybees.com>
 * @author    PrestaShop SA <contact@prestashop.com>
 * @copyright 2017 thirty bees
 * @copyright 2007-2016 PrestaShop SA
 * @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
 * PrestaShop is an internationally registered trademark & property of PrestaShop SA
 */

use GuzzleHttp\Client;

if (!defined('_TB_VERSION_')) {
    exit;
}

/**
 * Class Gapi
 */
class Gapi extends Module
{
    const CONNECTION_TIMEOUT = 30;

    const GAPI30_REQUEST_URI_TMP = 'PS_GAPI30_REQUEST_URI_TMP';
    const GAPI30_CLIENT_ID_TMP = 'PS_GAPI30_CLIENT_ID_TMP';
    const GAPI30_CLIENT_SECRET_TMP = 'PS_GAPI30_CLIENT_SECRET_TMP';
    const GAPI30_CLIENT_ID = 'PS_GAPI30_CLIENT_ID';
    const GAPI30_CLIENT_SECRET = 'PS_GAPI30_CLIENT_SECRET';
    const GAPI_PROFILE = 'PS_GAPI_PROFILE';
    const GAPI_PROFILE_TMP = 'PS_GAPI_PROFILE_TMP';
    const GAPI_VERSION = 'PS_GAPI_VERSION';
    const GAPI30_ACCESS_TOKEN = 'PS_GAPI30_ACCESS_TOKEN';
    const GAPI30_TOKEN_EXPIRATION = 'PS_GAPI30_TOKEN_EXPIRATION';
    const GAPI30_REFRESH_TOKEN = 'PS_GAPI30_REFRESH_TOKEN';
    const GAPI30_AUTHORIZATION_CODE = 'PS_GAPI30_AUTHORIZATION_CODE';

    // @codingStandardsIgnoreStart
    /** @var string $auth_token */
    public $auth_token = '';
    // @codingStandardsIgnoreEnd

    /**
     * Gapi constructor.
     */
    public function __construct()
    {
        $this->name = 'gapi';
        $this->tab = 'administration';
        $this->version = '3.0.1';
        $this->author = 'thirty bees';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->l('Google Analytics API');
        $this->description = $this->l('Connect to Google Analytics\' API to retrieve your data and display it on your dashboard.');
    }

    /**
     * @return bool
     */
    public function isConfigured()
    {
        if (!$this->active) {
            return false;
        }

        return $this->isApiConfigured();
    }

    /**
     * @return bool
     */
    public function isApiConfigured()
    {
        return (Configuration::get('PS_GAPI30_CLIENT_ID') && Configuration::get('PS_GAPI30_CLIENT_SECRET') && Configuration::get('PS_GAPI_PROFILE'));
    }

    /**
     * @return string
     */
    public function getContent()
    {
        $html = '';

        // Check configuration
        $allowUrlFopen = ini_get('allow_url_fopen');
        $openssl = extension_loaded('openssl');
        $curl = extension_loaded('curl');
        $guzzle = new Client(
            [
                'timeout' => static::CONNECTION_TIMEOUT,
                'verify'  => _PS_TOOL_DIR_.'cacert.pem',
            ]
        );
        try {
            $devSite = (string) $guzzle->get('https://developers.google.com/')->getBody();
        } catch (Exception $e) {
            $devSite = false;
        }

        $ping = (($allowUrlFopen || $curl) && $openssl && (bool) $devSite);
        $online = (in_array(Tools::getRemoteAddr(), ['127.0.0.1', '::1']) ? false : true);

        $this->context->smarty->assign(
            [
                'allowUrlFopen' => $allowUrlFopen,
                'curl'          => $curl,
                'openssl'       => $openssl,
                'ping'          => $ping,
                'online'        => $online,
            ]
        );

        if (!$ping || !$online) {
            $html .= $this->displayError($this->display(__FILE__, 'views/templates/admin/connectionerror.tpl'));
        }

        $html .= $this->display(__FILE__, 'views/templates/admin/info.tpl');

        if (Tools::getValue(static::GAPI_VERSION)) {
            Configuration::updateValue(static::GAPI_VERSION, (int) Tools::getValue(static::GAPI_VERSION));
        }

        $html .= $this->apiGetContent();

        return $html;
    }

    /**
     * @return string
     */
    public function apiGetContent()
    {
        $html = '';
        if (Tools::getValue(static::GAPI30_CLIENT_ID)) {
            Configuration::updateValue(static::GAPI30_REQUEST_URI_TMP, dirname($_SERVER['REQUEST_URI']).'/'.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
            Configuration::updateValue(static::GAPI30_CLIENT_ID_TMP, trim(Tools::getValue(static::GAPI30_CLIENT_ID)));
            Configuration::updateValue(static::GAPI30_CLIENT_SECRET_TMP, trim(Tools::getValue(static::GAPI30_CLIENT_SECRET)));
            Configuration::updateValue(static::GAPI_PROFILE_TMP, trim(Tools::getValue(static::GAPI_PROFILE)));
            // This will redirect the user to Google API authentication page
            $this->apiAuthenticate();
        } elseif (Tools::getValue('oauth2callback') == 'error') {
            $html .= $this->displayError('Google API: Access denied');
        } elseif (Tools::getValue('oauth2callback') == 'undefined') {
            $html .= $this->displayError('Something wrong happened with Google API authorization');
        } elseif (Tools::getValue('oauth2callback') == 'success') {
            if ($this->apiRefreshToken()) {
                $html .= $this->displayConfirmation('Google API Authorization granted');
            } else {
                $html .= $this->displayError('Google API Authorization granted but access token cannot be retrieved');
            }
        }

        if ($this->isApiConfigured()) {
            $resultTest = $this->apiRequestReportData('', 'ga:visits,ga:uniquePageviews', '30daysAgo', 'yesterday', null, null, 1, 1);
            if (!$resultTest) {
                $html .= $this->displayError('Cannot retrieve test results or there is no data in your account, yet');
            } else {
                $html .= $this->displayConfirmation(sprintf($this->l('Yesterday, %d people visited your store for a total of %d unique page views.'), $resultTest[0]['metrics']['visits'], $resultTest[0]['metrics']['uniquePageviews']));
            }
        }

        $shop = new Shop(Shop::getContextShopID());
        $authorizedOrigin = $shop->domain;
        $authorizedRedirect = $shop->domain.$shop->getBaseURI().'modules/'.$this->name.'/oauth2callback.php';
        $slides = [
            'Google API - 01 - Start.png'              => $this->l('Go to').' <a href="https://console.developers.google.com/cloud-resource-manager" target="_blank">https://console.developers.google.com/cloud-resource-manager</a> '.$this->l('and click the "Create Project" button'),
            'Google API - 02 - Services.png'           => $this->l('On the "API Manager > Library" tab, switch on the Analytics API'),
            'Google API - 04 - Services OK.png'        => $this->l('You should now have something like this'),
            'Google API - 05 - API Access.png'         => $this->l('On the "API Manager > Credentials" tab, click the "Create credentials" button. A dropdown will appear. Select "OAuth client ID"'),
            'Google API - 06 - Create Client ID.png'   =>
                sprintf($this->l('Keep "Web application" selected and fill in the "Authorized Javascript Origins" area with "%s" and "%s" then the "Authorized Redirect URI" area with "%s" and "%s".'), 'http://'.$authorizedOrigin, 'https://'.$authorizedOrigin, 'http://'.$authorizedRedirect, 'https://'.$authorizedRedirect).'
                <br />'.$this->l('Then validate by clicking the "Create" button'),
            'Google API - 07 - API Access created.png' => $this->l('You should now see the following screen. Copy/Paste the "Client ID" and "Client secret" into the form below'),
            'Google API - 08 - Profile ID.png'         => $this->l('Now you need the ID of the Analytics Profile you want to connect. In order to find your Profile ID, connect to the Analytics dashboard, then look at the URL in the address bar. Your Profile ID is the number following a "p", as shown underlined in red on the screenshot'),
        ];

        $this->context->smarty->assign(
            [
                'slides'     => $slides,
                'modulePath' => $this->_path,
            ]
        );

        $html .= $this->display(__FILE__, 'views/templates/admin/slider.tpl');

        $helper = new HelperOptions($this);
        $helper->id = $this->id;
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;

        $fieldsOptions = [
            'general' => [
                'title'  => $this->l('Google Analytics API v3.0'),
                'fields' => $fields = [
                    static::GAPI30_CLIENT_ID     => [
                        'title' => $this->l('Client ID'),
                        'type'  => 'text',
                    ],
                    static::GAPI30_CLIENT_SECRET => [
                        'title' => $this->l('Client Secret'),
                        'type'  => 'text',
                    ],
                    static::GAPI_PROFILE         => [
                        'title' => $this->l('Profile'),
                        'type'  => 'text',
                    ],
                ],
                'submit' => ['title' => $this->l('Save and Authenticate')],
            ],
        ];

        $helper->tpl_vars = ['currentIndex' => $helper->currentIndex];

        return $html.$helper->generateOptions($fieldsOptions);
    }

    /**
     * API Authenticate
     */
    public function apiAuthenticate()
    {
        $shop = new Shop(Shop::getContextShopID());
        // https://developers.google.com/accounts/docs/OAuth2WebServer
        $params = [
            'response_type'   => 'code',
            'client_id'       => Configuration::get(static::GAPI30_CLIENT_ID_TMP),
            'scope'           => 'https://www.googleapis.com/auth/analytics.readonly',
            'redirect_uri'    => $shop->getBaseURL(true).'modules/'.$this->name.'/oauth2callback.php',
            'state'           => $this->context->employee->id.'-'.Tools::encrypt($this->context->employee->id.Configuration::get(static::GAPI30_CLIENT_ID_TMP)),
            'approval_prompt' => 'force',
            'access_type'     => 'offline',
        ];
        Tools::redirectLink('https://accounts.google.com/o/oauth2/auth?'.http_build_query($params));
    }

    /**
     * Refresh API token
     *
     * @return bool
     */
    public function apiRefreshToken()
    {
        $params = [
            'client_id'     => Configuration::get(static::GAPI30_CLIENT_ID),
            'client_secret' => Configuration::get(static::GAPI30_CLIENT_SECRET),
        ];

        // https://developers.google.com/accounts/docs/OAuth2WebServer#offline
        if (Configuration::get(static::GAPI30_REFRESH_TOKEN)) {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = Configuration::get(static::GAPI30_REFRESH_TOKEN);
        } else {
            $shop = new Shop(Shop::getContextShopID());
            $params['grant_type'] = 'authorization_code';
            $params['code'] = Configuration::get(static::GAPI30_AUTHORIZATION_CODE);
            $params['redirect_uri'] = $shop->getBaseURL(true).'modules/'.$this->name.'/oauth2callback.php';
        }

        $guzzle = new Client(
            [
                'timeout' => static::CONNECTION_TIMEOUT,
                'verify'  => _PS_TOOL_DIR_.'cacert.pem',
            ]
        );

        try {
            $responseJson = (string) $guzzle->post(
                'https://accounts.google.com/o/oauth2/token', [
                'form_params' => $params,
            ]
            )->getBody();
        } catch (Exception $e) {
            $responseJson = false;
        }

        if (!$responseJson) {
            return false;
        }

        $response = json_decode($responseJson, true);
        if (isset($response['error'])) {
            return false;
        }

        Configuration::updateValue(static::GAPI30_ACCESS_TOKEN, $response['access_token']);
        Configuration::updateValue(static::GAPI30_TOKEN_EXPIRATION, time() + (int) $response['expires_in']);
        if (isset($response['refresh_token'])) {
            Configuration::updateValue(static::GAPI30_REFRESH_TOKEN, $response['refresh_token']);
        }

        return true;
    }

    /**
     * @param mixed $dimensions
     * @param mixed $metrics
     * @param mixed $dateFrom
     * @param mixed $dateTo
     * @param mixed $sort
     * @param mixed $filters
     * @param mixed $start
     * @param mixed $limit
     *
     * @return array|bool
     */
    protected function apiRequestReportData($dimensions, $metrics, $dateFrom, $dateTo, $sort, $filters, $start, $limit)
    {
        if (Configuration::get(static::GAPI30_TOKEN_EXPIRATION) < time() + 30 && !$this->apiRefreshToken()) {
            return false;
        }
        $bearer = Configuration::get(static::GAPI30_ACCESS_TOKEN);

        if (substr($metrics, 0, 3) === 'rt:') {
            $params = [
                'access_token' => $bearer,
                'ids'          => 'ga:'.Configuration::get(static::GAPI_PROFILE),
                'metrics'      => $metrics,
            ];
        } else {
            $params = [
                'access_token' => $bearer,
                'ids'          => 'ga:'.Configuration::get(static::GAPI_PROFILE),
                'dimensions'   => $dimensions,
                'metrics'      => $metrics,
                'sort'         => $sort ? $sort : $metrics,
                'start-date'   => $dateFrom,
                'end-date'     => $dateTo,
                'start-index'  => $start,
                'max-results'  => $limit,
            ];
        }

        if (substr($metrics, 0, 3) !== 'rt:' && $filters !== null) {
            $params['filters'] = $filters;
        }
        $content = str_replace('&amp;', '&', urldecode(http_build_query($params)));

        $api = ($dateFrom && $dateTo) ? 'ga' : 'realtime';

        $guzzle = new Client(
            [
                'timeout' => static::CONNECTION_TIMEOUT,
                'verify'  => _PS_TOOL_DIR_.'cacert.pem',
            ]
        );

        try {
            $responseJson = (string) $guzzle->get("https://www.googleapis.com/analytics/v3/data/$api?$content")->getBody();
        } catch (Exception $e) {
            $responseJson = false;
        }

        if (!$responseJson) {
            return false;
        }

        // https://developers.google.com/analytics/devguides/reporting/core/v3/reference
        $response = json_decode($responseJson, true);

        $result = [];
        if (isset($response['rows']) && is_array($response['rows'])) {
            foreach ($response['rows'] as $row) {
                $metrics = [];
                $dimensions = [];
                foreach ($row as $key => $value) {
                    if ($response['columnHeaders'][$key]['columnType'] == 'DIMENSION') {
                        $dimensions[str_replace('ga:', '', $response['columnHeaders'][$key]['name'])] = $value;
                    } elseif ($response['columnHeaders'][$key]['columnType'] == 'METRIC') {
                        $metrics[str_replace('ga:', '', $response['columnHeaders'][$key]['name'])] = $value;
                    }
                }
                $result[] = ['metrics' => $metrics, 'dimensions' => $dimensions];
            }
        }

        return $result;
    }

    /**
     * @param mixed       $dimensions
     * @param mixed       $metrics
     * @param null|string $dateFrom
     * @param null|string $dateTo
     * @param null        $sort
     * @param null        $filters
     * @param int         $start
     * @param int         $limit
     *
     * @return array|bool
     */
    public function requestReportData($dimensions, $metrics, $dateFrom = null, $dateTo = null, $sort = null, $filters = null, $start = 1, $limit = 30)
    {
        return $this->apiRequestReportData($dimensions, $metrics, $dateFrom, $dateTo, $sort, $filters, $start, $limit);
    }

    /**
     * OAuth 2 callback
     */
    public function apiOauthCallback()
    {
        if (!Tools::getValue('state')) {
            die ('token missing');
        }
        $state = explode('-', Tools::getValue('state'));
        if (count($state) != 2) {
            die ('token malformed');
        }
        if ($state[1] != Tools::encrypt($state[0].Configuration::get('PS_GAPI30_CLIENT_ID_TMP'))) {
            die ('token not valid');
        }

        $oauth2callback = 'undefined';
        $url = Configuration::get('PS_GAPI30_REQUEST_URI_TMP');
        if (Tools::getValue('error')) {
            $oauth2callback = 'error';
        } elseif (Tools::getValue('code')) {
            Configuration::updateValue('PS_GAPI30_CLIENT_ID', Configuration::get('PS_GAPI30_CLIENT_ID_TMP'));
            Configuration::updateValue('PS_GAPI30_CLIENT_SECRET', Configuration::get('PS_GAPI30_CLIENT_SECRET_TMP'));
            Configuration::updateValue('PS_GAPI_PROFILE', Configuration::get('PS_GAPI_PROFILE_TMP'));
            Configuration::updateValue('PS_GAPI30_AUTHORIZATION_CODE', Tools::getValue('code'));
            $oauth2callback = 'success';
        }

        Configuration::deleteFromContext('PS_GAPI30_CLIENT_ID_TMP');
        Configuration::deleteFromContext('PS_GAPI30_CLIENT_SECRET_TMP');
        Configuration::deleteFromContext('PS_GAPI_PROFILE_TMP');
        Configuration::deleteFromContext('PS_GAPI30_REQUEST_URI_TMP');
        Configuration::deleteFromContext('PS_GAPI30_REFRESH_TOKEN');

        Tools::redirectAdmin($url.'&oauth2callback='.$oauth2callback);
    }
}
