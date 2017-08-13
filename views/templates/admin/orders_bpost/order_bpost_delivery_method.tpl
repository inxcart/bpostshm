{*
* 2014-2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @authort thirty bees <contact@thirtybees.com>
* @copyright 2014-2015 Stigmi
* @copyright 2017 Thirty Development, LLC
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
<div class="order-bpost-delivery-method">
  {if isset($dm)}
    <span>{$dm|escape:'htmlall':'UTF-8'}</span>
    {if isset($options)}
      <ul class="dm-list">
      {foreach $options as $option}
        <li>+ {$option|escape:'htmlall':'UTF-8'}</li>
      {/foreach}
    {/if}
    </ul>
  {/if}
</div>
