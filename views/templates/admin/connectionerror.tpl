{*
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
*}
<ul>
    {if !($curl || $allowUrlFopen)}<li>{l s='You are not allowed to open external URLs' mod='gapi'}</li>{/if}
    {if !$curl}<li>{l s='cURL is not enabled' mod='gapi'}</li>{/if}
    {if !$openssl}<li>{l s='OpenSSL is not enabled' mod='gapi'}</li>{/if}
    {if ($allowUrlFopen || $curl) && $openssl && !$ping}<li>{l s='Google is unreachable (check your firewall)' mod='gapi'}</li>{/if}
    {if !$online}<li>{l s='You are currently testing your shop on a local server. In order to enjoy the full features, you need to put your shop on an online server.' mod='gapi'}</li>{/if}
</ul>
