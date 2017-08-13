{*
* 2014-2016 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @copyright 2014-2016 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

<div id="aob-info" class="{if $version < 1.6}toolbarBox toolbarHead infoPanel{else}panel kpi-container{/if}">
	<p>{l s='PARA-1' mod='bpostshm'}</p>
	<p>{l s='PARA-2' mod='bpostshm'}</p>
	<ul>{strip}
		<li>{l s='LISTITEM-1' mod='bpostshm'}</li>
		<li>{l s='LISTITEM-2' mod='bpostshm'}</li>
		<li>{l s='LISTITEM-3' mod='bpostshm'}</li>
	{/strip}</ul>
	<p class="admin-lo">
		<a href="#" id="aob-info-remove" title="{l s='Click here' mod='bpostshm'}">
		{l s='Click here' mod='bpostshm'}</a>
		{l s='if you no longer wish to see this message' mod='bpostshm'}.
	</p>
</div>