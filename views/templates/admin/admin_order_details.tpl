{*
* 2014-2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @author thirty bees <contact@thirtybees.com>
* @copyright 2014-2015 Stigmi
* @copyright 2017 Thirty Development, LLC
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

{if isset($sp)}
  <div class="order-details">
    <h4>{$sp.slug|escape:'htmlall':'UTF-8'}</h4>
    <p>
      {$sp.lname|escape:'htmlall':'UTF-8'}:&nbsp;{$sp.id|escape:'htmlall':'UTF-8'}<br>
      {$sp.office|escape:'htmlall':'UTF-8'}<br>
      {$sp.street|escape:'htmlall':'UTF-8'} {$sp.nr|escape:'htmlall':'UTF-8'}<br>
      {$sp.zip|escape:'htmlall':'UTF-8'} {$sp.city|escape:'htmlall':'UTF-8'}
    </p>
  </div>
{/if}
<br>

