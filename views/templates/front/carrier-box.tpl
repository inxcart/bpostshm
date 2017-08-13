{*
* 2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @copyright 2014-2015 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
{if isset($cbox)}
	{* delivery date section *}
	{include file='./cbox-delivery.tpl'}
	<!-- <div id="sect-address"> -->
	{if $cbox.address}
		<div class="address">
		{if isset($cbox.address.title)}
			<span class="title">{$cbox.address.title|escape} </span>
		{/if}	
			<span class="body">{$cbox.address.body|escape}</span>
			<br>
		</div>
	{/if}
	{if isset($cbox.button) && $cbox.button}
		{assign dm "{l s='pick-up point' mod='bpostshm'}"}
		{assign act "{l s='Choose' mod='bpostshm'}"}
		{if $cbox.button.title > 3}
			{assign dm "{l s='parcel locker' mod='bpostshm'}"}
		{/if}
		{if $cbox.button.title & 1}
			{assign act "{l s='Change' mod='bpostshm'}"}
		{/if}
		{assign title $act|cat:' '|cat:$dm}
		<a id="btn-sp" class="{$cbox.button.class|escape}" data-url="{$cbox.button.link|escape}">{$title|escape}</a>
	{/if}
	<!-- </div> -->
{/if}
