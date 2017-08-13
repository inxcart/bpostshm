{*
* 2014 Stigmi
*
* @author Stigmi.eu <www.stigmi.eu>
* @copyright 2014 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

<div id="service-point" class="at-shop{if $version == 1.5} v1-5{/if}">
	{if $is_valid}
		<div id="catch-phrase">{l s='Select where you want to pick up your parcel.' mod='bpostshm'}</div>
	{else}
		<div id="error-phrase">{l s='Invalid postcode. Please provide a correct address.' mod='bpostshm'}</div>
	{/if}
	<form action="{$url_get_nearest_service_points|escape}" id="search-form" method="GET" target="_self" autocomplete="off">
		<!-- <label for="postcode">{l s='Postcode' mod='bpostshm'}</label>
		<input type="text" name="postcode" id="postcode" value="{$postcode|escape}" size="10" />
		<label for="city">{l s='City' mod='bpostshm'}</label>
		<input type="text" name="city" id="city" value="{if isset($city)}{$city|escape}{/if}" size="25" />
		<input type="submit" name="searchSubmit" class="button" value="{l s='Search' mod='bpostshm'}" />
		<img class="loader" src="{$module_dir|escape}views/img/ajax-loader.gif" alt="{l s='Loading...' mod='bpostshm'}" /> -->
		<div class="form-group">
			<label for="postcode">{l s='Postcode' mod='bpostshm'}</label>
			<input type="text" name="postcode" id="postcode" value="{$postcode|escape}" size="10" />
		</div>
		<div class="form-group">
			<label for="city">{l s='City' mod='bpostshm'}</label>
			<input type="text" name="city" id="city" value="{if isset($city)}{$city|escape}{/if}" size="25" />
		</div>
		<p class="submit">
			<input type="submit" name="searchSubmit" class="button" value="{l s='Search' mod='bpostshm'}" />
			<img class="loader" src="{$module_dir|escape}views/img/ajax-loader.gif" alt="{l s='Loading...' mod='bpostshm'}" />
		</p>
	</form>
	<div class="clearfix">
		<ul id="poi" class="col-xs-4 alpha">{strip}
			<li class="hidden">
				<a title="" class="clearfix">
					<img src="{$module_dir|escape}views/img/bpost-poi.png" alt="bpost" />
					<span class="details"></span>
				</a>
			</li>
		{/strip}</ul>
		<div class="col-xs-8 omega">
			<div id="map-canvas"></div>
		</div>
	</div>

	<script type="text/javascript">
		google.maps.event.addDomListener(window, 'load', function() {
			ServicePointer.icon = '{$module_dir|escape:'javascript'}views/img/bpost-poi.png';
			ServicePointer.lang = {
				'Next step': 		"{l s='Next step' mod='bpostshm' js=1}",
				'Closed':			"{l s='Closed' mod='bpostshm' js=1}",
				'No results found':	"{l s='No results found' mod='bpostshm' js=1}",
				'Monday': 			"{l s='Monday' mod='bpostshm' js=1}",
				'Tuesday': 			"{l s='Tuesday' mod='bpostshm' js=1}",
				'Wednesday': 		"{l s='Wednesday' mod='bpostshm' js=1}",
				'Thursday': 		"{l s='Thursday' mod='bpostshm' js=1}",
				'Friday': 			"{l s='Friday' mod='bpostshm' js=1}",
				'Saturday': 		"{l s='Saturday' mod='bpostshm' js=1}",
				'Sunday': 			"{l s='Sunday' mod='bpostshm' js=1}"
			};
			ServicePointer.services = {
				get_nearest_service_points:	'{$url_get_nearest_service_points|escape:'javascript'}',
				get_service_point_hours:	'{$url_get_service_point_hours|escape:'javascript'}',
				set_service_point:			'{$url_set_service_point|escape:'javascript'}'
			};
			ServicePointer.init(
				{$version|floatval},
				{$servicePoints|json_encode}, 
				{$shipping_method|intval} 
			);

			$(window).resize(function() {
				google.maps.event.trigger(ServicePointer.map, 'resize');
			});
			google.maps.event.trigger(ServicePointer.map, 'resize');
		});

		$(function() {
			$(document)
				.ajaxStart(function() {
					ServicePointer.is_busy = true;
					$('.loader').css('display', 'inline-block');
				})
				.ajaxComplete(function() {
					$('.loader').hide();
					ServicePointer.is_busy = false;
				});
		});
	</script>
</div>