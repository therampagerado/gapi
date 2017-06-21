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
        $this->version = '2.0.0';
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
        if ((int) Configuration::get('PS_GAPI_VERSION') === 30) {
            return $this->api_3_0_isConfigured();
        } elseif ((int) Configuration::get('PS_GAPI_VERSION') === 13) {
            return $this->api_1_3_isConfigured();
        }

        return false;
    }

    /**
     * @return bool
     */
    public function api_3_0_isConfigured()
    {
        return (Configuration::get('PS_GAPI30_CLIENT_ID') && Configuration::get('PS_GAPI30_CLIENT_SECRET') && Configuration::get('PS_GAPI_PROFILE'));
    }

    /**
     * @return bool
     */
    public function api_1_3_isConfigured()
    {
        return (Configuration::get('PS_GAPI13_EMAIL') && Configuration::get('PS_GAPI13_PASSWORD') && Configuration::get('PS_GAPI_PROFILE'));
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
        $guzzle = new Client([
            'timeout' => static::CONNECTION_TIMEOUT,
            'verify'  => _PS_TOOL_DIR_.'cacert.pem',
        ]);
        try {
            $devSite = (string) $guzzle->get('https://developers.google.com/')->getBody();
        } catch (Exception $e) {
            $devSite = false;
        }

        $ping = (($allowUrlFopen || $curl) && $openssl && (bool) $devSite);
        $online = (in_array(Tools::getRemoteAddr(), ['127.0.0.1', '::1']) ? false : true);

        $this->context->smarty->assign([
            'allowUrlFopen' => $allowUrlFopen,
            'curl'          => $curl,
            'openssl'       => $openssl,
            'ping'          => $ping,
            'online'        => $online,
        ]);

        if (!$ping || !$online) {
            $html .= $this->displayError($this->display(__FILE__, 'views/templates/admin/connectionerror.tpl'));
        }

        $html .= $this->display(__FILE__, 'views/templates/admin/info.tpl');

        if (Tools::getValue('PS_GAPI_VERSION')) {
            Configuration::updateValue('PS_GAPI_VERSION', (int) Tools::getValue('PS_GAPI_VERSION'));
        }

        $helper = new HelperOptions($this);
        $helper->id = $this->id;
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;

        $fieldsOptions = [
            'general' => [
                'title'  => $this->l('Which Google Analytics API version do you want to use?'),
                'fields' => $fields = [
                    'PS_GAPI_VERSION' => [
                        'type'       => 'radio',
                        'choices'    => [
                            13 => $this->l('v1.3: easy to configure but deprecated and less secure'),
                            30 => $this->l('v3.0 with OAuth 2.0: most powerful and up-to-date version'),
                        ],
                        'visibility' => Shop::CONTEXT_SHOP,
                    ],
                ],
                'submit' => ['title' => $this->l('Save and configure')],
            ],
        ];

        $helper->tpl_vars = ['currentIndex' => $helper->currentIndex];

        $html .= $helper->generateOptions($fieldsOptions);

        if (Configuration::get('PS_GAPI_VERSION') == 30) {
            $html .= $this->api_3_0_getContent();
        } elseif (Configuration::get('PS_GAPI_VERSION') == 13) {
            $html .= $this->api_1_3_getContent();
        }

        return $html;
    }

    /**
     * @return string
     */
    public function api_3_0_getContent()
    {
        $html = '';
        if (Tools::getValue('PS_GAPI30_CLIENT_ID')) {
            Configuration::updateValue('PS_GAPI30_REQUEST_URI_TMP', dirname($_SERVER['REQUEST_URI']).'/'.AdminController::$currentIndex.'&configure='.$this->name.'&token='.Tools::getAdminTokenLite('AdminModules'));
            Configuration::updateValue('PS_GAPI30_CLIENT_ID_TMP', trim(Tools::getValue('PS_GAPI30_CLIENT_ID')));
            Configuration::updateValue('PS_GAPI30_CLIENT_SECRET_TMP', trim(Tools::getValue('PS_GAPI30_CLIENT_SECRET')));
            Configuration::updateValue('PS_GAPI_PROFILE_TMP', trim(Tools::getValue('PS_GAPI_PROFILE')));
            // This will redirect the user to Google API authentication page
            $this->api_3_0_authenticate();
        } elseif (Tools::getValue('oauth2callback') == 'error') {
            $html .= $this->displayError('Google API: Access denied');
        } elseif (Tools::getValue('oauth2callback') == 'undefined') {
            $html .= $this->displayError('Something wrong happened with Google API authorization');
        } elseif (Tools::getValue('oauth2callback') == 'success') {
            if ($this->api_3_0_refreshtoken()) {
                $html .= $this->displayConfirmation('Google API Authorization granted');
            } else {
                $html .= $this->displayError('Google API Authorization granted but access token cannot be retrieved');
            }
        }

        $displaySlider = true;
        if ($this->api_3_0_isConfigured()) {
            $resultTest = $this->api_3_0_requestReportData('', 'ga:visits,ga:uniquePageviews', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), null, null, 1, 1);
            if (!$resultTest) {
                $html .= $this->displayError('Cannot retrieve test results');
            } else {
                $displaySlider = false;
                $html .= $this->displayConfirmation(sprintf($this->l('Yesterday, your store received the visit of %d people for a total of %d unique page views.'), $resultTest[0]['metrics']['visits'], $resultTest[0]['metrics']['uniquePageviews']));
            }
        }

        if ($displaySlider) {
            $shop = new Shop(Shop::getContextShopID());
            $authorizedOrigin = $shop->domain;
            $authorizedRedirect = $shop->domain.$shop->getBaseURI().'modules/'.$this->name.'/oauth2callback.php';
            $slides = [
                'Google API - 01 - Start.png'              => $this->l('Go to https://code.google.com/apis/console and click the "Create Project" button'),
                'Google API - 02 - Services.png'           => $this->l('In the "APIS & AUTH > APIs" tab, switch on the Analytics API'),
                'Google API - 03 - Terms.png'              => $this->l('You may be asked to agree to the Terms of Service of Google APIs and Analytics API'),
                'Google API - 04 - Services OK.png'        => $this->l('You should now have something like that'),
                'Google API - 05 - API Access.png'         => $this->l('In the "APIS & AUTH > Credentials" tab, click the first, red, "Create new Client ID" button'),
                'Google API - 06 - Create Client ID.png'   =>
                    sprintf($this->l('Keep "Web application" selected and fill in the "Authorized Javascript Origins" area with "%s" and "%s" then the "Authorized Redirect URI" area with "%s" and "%s".'), 'http://'.$authorizedOrigin, 'https://'.$authorizedOrigin, 'http://'.$authorizedRedirect, 'https://'.$authorizedRedirect).'
					<br />'.$this->l('Then validate by clicking the "Create client ID" button'),
                'Google API - 07 - API Access created.png' => $this->l('You should now have the following screen. Copy/Paste the "Client ID" and "Client secret" into the form below'),
                'Google API - 08 - Profile ID.png'         => $this->l('Now you need the ID of the Analytics Profile you want to connect. In order to find your Profile ID, connect to the Analytics dashboard, then look at the URL in the address bar. Your Profile ID is the number following a "p", as shown underlined in red on the screenshot'),
            ];
            $firstSlide = key($slides);

            $html .= '
			<a id="screenshots_button" href="#screenshots"><button class="btn btn-default"><i class="icon-question-sign"></i> How to configure Google Analytics API</button></a>
			<div style="display:none">
				<div id="screenshots" class="carousel slide">
					<ol class="carousel-indicators">';
            $i = 0;
            foreach ($slides as $slide => $caption) {
                $html .= '<li data-target="#screenshots" data-slide-to="'.($i++).'" '.($slide == $firstSlide ? 'class="active"' : '').'></li>';
            }
            $html .= '
					</ol>
					<div class="carousel-inner">';
            foreach ($slides as $slide => $caption) {
                $html .= '
						<div class="item '.($slide == $firstSlide ? 'active' : '').'">
							<img src="'.$this->_path.'screenshots/3.0/'.$slide.'" style="margin:auto">
							<div style="text-align:center;font-size:1.4em;margin-top:10px;font-weight:700">
								'.$caption.'
							</div>
							<div class="clear">&nbsp;</div>
						</div>';
            }
            $html .= '
					</div>
					<a class="left carousel-control" href="#screenshots" data-slide="prev">
						<span class="icon-prev"></span>
					</a>
					<a class="right carousel-control" href="#screenshots" data-slide="next">
						<span class="icon-next"></span>
					</a>
				</div>
			</div>
			<div class="clear">&nbsp;</div>
			<script type="text/javascript">
				$(document).ready(function(){
					$("a#screenshots_button").fancybox();
					$("#screenshots").carousel({interval:false});
					$("ol.carousel-indicators").remove();
				});
			</script>';
        }

        $helper = new HelperOptions($this);
        $helper->id = $this->id;
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;

        $fieldsOptions = [
            'general' => [
                'title'  => $this->l('Google Analytics API v3.0'),
                'fields' => $fields = [
                    'PS_GAPI30_CLIENT_ID'     => [
                        'title' => $this->l('Client ID'),
                        'type'  => 'text',
                    ],
                    'PS_GAPI30_CLIENT_SECRET' => [
                        'title' => $this->l('Client Secret'),
                        'type'  => 'text',
                    ],
                    'PS_GAPI_PROFILE'         => [
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
    public function api_3_0_authenticate()
    {
        $shop = new Shop(Shop::getContextShopID());
        // https://developers.google.com/accounts/docs/OAuth2WebServer
        $params = [
            'response_type'   => 'code',
            'client_id'       => Configuration::get('PS_GAPI30_CLIENT_ID_TMP'),
            'scope'           => 'https://www.googleapis.com/auth/analytics.readonly',
            'redirect_uri'    => $shop->getBaseURL(true).'modules/'.$this->name.'/oauth2callback.php',
            'state'           => $this->context->employee->id.'-'.Tools::encrypt($this->context->employee->id.Configuration::get('PS_GAPI30_CLIENT_ID_TMP')),
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
    public function api_3_0_refreshtoken()
    {
        $params = [
            'client_id'     => Configuration::get('PS_GAPI30_CLIENT_ID'),
            'client_secret' => Configuration::get('PS_GAPI30_CLIENT_SECRET'),
        ];

        // https://developers.google.com/accounts/docs/OAuth2WebServer#offline
        if (Configuration::get('PS_GAPI30_REFRESH_TOKEN')) {
            $params['grant_type'] = 'refresh_token';
            $params['refresh_token'] = Configuration::get('PS_GAPI30_REFRESH_TOKEN');
        } else {
            $shop = new Shop(Shop::getContextShopID());
            $params['grant_type'] = 'authorization_code';
            $params['code'] = Configuration::get('PS_GAPI30_AUTHORIZATION_CODE');
            $params['redirect_uri'] = $shop->getBaseURL(true).'modules/'.$this->name.'/oauth2callback.php';
        }

        $guzzle = new Client([
            'timeout' => static::CONNECTION_TIMEOUT,
            'verify'  => _PS_TOOL_DIR_.'cacert.pem',
        ]);

        try {
            $responseJson = (string) $guzzle->post('https://accounts.google.com/o/oauth2/token', [
                'form_params' => $params,
            ]);
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

        Configuration::updateValue('PS_GAPI30_ACCESS_TOKEN', $response['access_token']);
        Configuration::updateValue('PS_GAPI30_TOKEN_EXPIRATION', time() + (int) $response['expires_in']);
        if (isset($response['refresh_token'])) {
            Configuration::updateValue('PS_GAPI30_REFRESH_TOKEN', $response['refresh_token']);
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
    protected function api_3_0_requestReportData($dimensions, $metrics, $dateFrom, $dateTo, $sort, $filters, $start, $limit)
    {
        if (Configuration::get('PS_GAPI30_TOKEN_EXPIRATION') < time() + 30 && !$this->api_3_0_refreshtoken()) {
            return false;
        }
        $bearer = Configuration::get('PS_GAPI30_ACCESS_TOKEN');

        $params = [
            'ids'          => 'ga:'.Configuration::get('PS_GAPI_PROFILE'),
            'dimensions'   => $dimensions,
            'metrics'      => $metrics,
            'sort'         => $sort ? $sort : $metrics,
            'start-date'   => $dateFrom,
            'end-date'     => $dateTo,
            'start-index'  => $start,
            'max-results'  => $limit,
            'access_token' => $bearer,
        ];
        if ($filters !== null) {
            $params['filters'] = $filters;
        }
        $content = str_replace('&amp;', '&', urldecode(http_build_query($params)));

        $api = ($dateFrom && $dateTo) ? 'ga' : 'realtime';

        $guzzle = new Client([
            'timeout' => static::CONNECTION_TIMEOUT,
            'verify'  => _PS_TOOL_DIR_.'cacert.pem',
        ]);

        try {
            $responseJson = (string) $guzzle->post('https://www.googleapis.com/analytics/v3/data/'.$api.'?'.$content, [
                'form_params' => $params,
            ]);
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
     * @return string
     */
    public function api_1_3_getContent()
    {
        $html = '';
        if (Tools::isSubmit('PS_GAPI13_EMAIL')) {
            if ($this->api_1_3_authenticate(Tools::getValue('PS_GAPI13_EMAIL'), Tools::getValue('PS_GAPI13_PASSWORD'))) {
                Configuration::updateValue('PS_GAPI13_EMAIL', Tools::getValue('PS_GAPI13_EMAIL'));
                Configuration::updateValue('PS_GAPI13_PASSWORD', Tools::getValue('PS_GAPI13_PASSWORD'));
                Configuration::updateValue('PS_GAPI_PROFILE', Tools::getValue('PS_GAPI_PROFILE'));
            } else {
                $html .= $this->displayError($this->l('Authentication failed'));
            }
        }

        if ($this->api_1_3_isConfigured()) {
            $resultTest = $this->api_1_3_requestReportData('', 'ga:visits,ga:uniquePageviews', date('Y-m-d', strtotime('-1 day')), date('Y-m-d', strtotime('-1 day')), null, null, 1, 1);
            if (!$resultTest) {
                $html .= $this->displayError('Cannot retrieve test results');
            } else {
                $html .= $this->displayConfirmation(sprintf($this->l('Yesterday, your store received the visit of %d people for a total of %d unique page views.'), $resultTest[0]['metrics']['visits'], $resultTest[0]['metrics']['uniquePageviews']));
            }
        }

        $helper = new HelperOptions($this);
        $helper->id = $this->id;
        $helper->currentIndex = AdminController::$currentIndex.'&configure='.$this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->module = $this;

        $fieldsOptions = [
            'general' => [
                'title'  => $this->l('Google Analytics API v1.3'),
                'fields' => $fields = [
                    'PS_GAPI13_EMAIL'    => [
                        'title' => $this->l('Email'),
                        'type'  => 'text',
                    ],
                    'PS_GAPI13_PASSWORD' => [
                        'title' => $this->l('Password'),
                        'type'  => 'password',
                    ],
                    'PS_GAPI_PROFILE'    => [
                        'title' => $this->l('Profile'),
                        'type'  => 'text',
                        'desc'  => $this->l('You can find your profile ID in the address bar of your browser while accessing Analytics report.').'<br />'.
                            $this->l('For the OLD VERSION of Google Analytics, the profile ID is in the URL\'s "id" parameter (see "&id=xxxxxxxx"):').'<br />'.
                            'https://www.google.com/analytics/reporting/?reset=1&id=XXXXXXXX&pdr=20110702-20110801'.'<br />'.
                            $this->l('For the NEW VERSION of Google Analytics, the profile ID is the number at the end of the URL, starting with p:').'<br />'.
                            'https://www.google.com/analytics/web/#home/a11345062w43527078pXXXXXXXX/',
                    ],
                ],
                'submit' => ['title' => $this->l('Save and Authenticate')],
            ],
        ];

        $helper->tpl_vars = ['currentIndex' => $helper->currentIndex];

        return $html.$helper->generateOptions($fieldsOptions);
    }

    /**
     * @param string $email
     * @param string $password
     *
     * @return bool
     */
    protected function api_1_3_authenticate($email, $password)
    {
        // @codingStandardsIgnoreEnd
        $streamContext = stream_context_create(
            [
                'http' => [
                    'method'  => 'POST',
                    'content' => 'accountType=GOOGLE&Email='.urlencode($email).'&Passwd='.urlencode($password).'&source=GAPI-1.3&service=analytics',
                    'header'  => 'Content-type: application/x-www-form-urlencoded'."\r\n",
                    'timeout' => 5,
                ],
            ]
        );

        $guzzle = new Client([
            'timeout' => static::CONNECTION_TIMEOUT,
            'verify'  => _PS_TOOL_DIR_.'cacert.pem',
        ]);

        try {
            $response = (string) $guzzle->post('https://www.google.com/accounts/ClientLogin', [
                'body' => 'accountType=GOOGLE&Email='.urlencode($email).'&Passwd='.urlencode($password).'&source=GAPI-1.3&service=analytics',
            ]);
        } catch (Exception $e) {
            $response = false;
        }

        if (!$response) {
            return false;
        }

        parse_str(str_replace(["\n", "\r\n"], '&', $response), $responseArray);
        if (!is_array($responseArray) || !isset($responseArray['Auth']) || empty($responseArray['Auth'])) {
            return false;
        }

        $this->auth_token = $responseArray['Auth'];

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
    protected function api_1_3_requestReportData($dimensions, $metrics, $dateFrom, $dateTo, $sort, $filters, $start, $limit)
    {
        if (!$this->api_1_3_authenticate(Configuration::get('PS_GAPI13_EMAIL'), Configuration::get('PS_GAPI13_PASSWORD'))) {
            return false;
        }

        $params = [
            'ids'         => 'ga:'.Configuration::get('PS_GAPI_PROFILE'),
            'dimensions'  => $dimensions,
            'metrics'     => $metrics,
            'sort'        => $sort ? $sort : $metrics,
            'start-date'  => $dateFrom,
            'end-date'    => $dateTo,
            'start-index' => $start,
            'max-results' => $limit,
        ];
        if ($filters !== null) {
            $params['filters'] = $filters;
        }
        $content = str_replace('&amp;', '&', urldecode(http_build_query($params)));

        $guzzle = new Client([
            'timeout' => static::CONNECTION_TIMEOUT,
            'verify'  => _PS_TOOL_DIR_.'cacert.pem',
        ]);

        try {
            $response = (string) $guzzle->get('https://www.google.com/analytics/feeds/data?'.$content, [
                'headers' => [
                    'Authorization' => 'GoogleLogin auth='.$this->auth_token,
                ],
            ]);
        } catch (Exception $e) {
            $response = false;
        }

        if (!$response) {
            return false;
        }

        $xml = simplexml_load_string($response);

        $result = [];
        foreach ($xml->entry as $entry) {
            $metrics = [];
            foreach ($entry->children('http://schemas.google.com/analytics/2009')->metric as $metric) {
                $key = str_replace('ga:', '', $metric->attributes()->name);
                $metricValue = strval($metric->attributes()->value);
                if (preg_match('/^(\d+\.\d+)|(\d+E\d+)|(\d+.\d+E\d+)$/', $metricValue)) {
                    $metrics[$key] = (float) $metricValue;
                } else {
                    $metrics[$key] = (int) $metricValue;
                }
            }

            $dimensions = [];
            foreach ($entry->children('http://schemas.google.com/analytics/2009')->dimension as $dimension) {
                $dimensions[str_replace('ga:', '', $dimension->attributes()->name)] = strval($dimension->attributes()->value);
            }

            $result[] = ['metrics' => $metrics, 'dimensions' => $dimensions];
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
        if (Configuration::get('PS_GAPI_VERSION') == 30) {
            return $this->api_3_0_requestReportData($dimensions, $metrics, $dateFrom, $dateTo, $sort, $filters, $start, $limit);
        } elseif (Configuration::get('PS_GAPI_VERSION') == 13) {
            return $this->api_1_3_requestReportData($dimensions, $metrics, $dateFrom, $dateTo, $sort, $filters, $start, $limit);
        }

        return false;
    }

    /**
     * OAuth 2 callback
     */
    public function api_3_0_oauth2callback()
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
