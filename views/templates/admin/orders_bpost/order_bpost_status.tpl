{*
* 2014-2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @author thirty bees <contact@thirtybees.com>
* @copyright 2014-2015 Stigmi
* @copyright 2017 Thirty Development, LLC
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
<div class="order-bpost-status">
	{if isset($status)}
		<span class="status-main{if !empty($cls_late)} {$cls_late|escape:'htmlall':'UTF-8'}{/if}">{$status|escape:'htmlall':'UTF-8'}</span>
		{if !empty($print_count)}<span class="status-count">{$print_count|escape:'htmlall':'UTF-8'}</span>{/if}
	{/if}
</div>
