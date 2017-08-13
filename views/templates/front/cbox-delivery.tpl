{*
* 2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @copyright 2014-2015 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
{if isset($cbox.delivery) && $cbox.delivery}
{assign var='del' value=$cbox.delivery}
<div id="sect-delivery">
	<span id="del-title">{l s='Delivery date' mod='bpostshm'}: </span>
	{if count($del.dates) > 1}
		<select name="del-dates" id="del-dates" data-shm="{$shipping_method|intval}" {if isset($url_set_delivery_date)}data-url="{$url_set_delivery_date|escape}"{/if}>
		{foreach $del.dates as $dt_code => $dt_str}
			<option value="{$dt_code|intval}" {if $dt_code == $del.def}selected{/if}>{$dt_str|escape}</option>
		{/foreach}
		</select>
	{else}
		{foreach $del.dates as $dt_code => $dt_str}
			<span>{$dt_str|escape}</span>
		{/foreach}
	{/if}
	{if $del.def_sat}
		<span id="del-info">{l s='Saturday delivery may add extra-cost for shipping' mod='bpostshm'}.</span>
	{/if}
</div>
{/if}