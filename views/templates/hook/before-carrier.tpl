{*
* 2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @copyright 2015 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
<script type="text/javascript">
// <![CDATA[ 
	var id_carrier			= {$id_carrier|intval|default:0},
		carriers_shm 		= {$carriers_shm|json_encode},
		l_messages	 		= {$l_messages|json_encode},
		opc 				= {if $opc}true{else}false{/if},
		version 			= {$version|floatval};

(function($) {
	$(function() {

{if isset($no_address)}
		var msg = '{$no_address|escape}',
			sel_container = version < 1.5 ? '#carrierTable > tbody' : 'div.delivery_options',
			sel_radio = version < 1.5 ? '#carrierTable > tbody > tr' : 'div.delivery_option',
			elms = $(sel_radio).has($(':input').filter(function() {
			return this.value.replace(',', '') in carriers_shm
		}));
		elms.remove();
		//elms.css('border', '1px solid red');
		var elm_msg = $(document.createElement('div'));
		if (version < 1.5) {
			var tr = $(document.createElement('tr')),
				td = $(document.createElement('td'));
			td.attr('colspan', '4');
			tr.append(td.append(elm_msg));
			$(sel_container).prepend(tr);
		}
		else
			$(sel_container).append(elm_msg);
		
		elm_msg
			.addClass('no-address')
			.addClass('ps' + version.toString().replace('.', ''))
			.text(msg);

{else}
	{if isset($debug_mode)}
		var debug_sect = $('#dbg-bc');
		if (0 === debug_sect.length) {
			var sel_container = version < 1.5 ? '#opc_delivery_methods' : '#carrier_area',
				debug_sect = $(document.createElement('div')),
				debug_src = "{$debug_mode|escape:'javascript'}";
			debug_sect
				.attr('id', 'dbg-bc')
				.html(debug_src);
			$(sel_container).before(debug_sect);
		}
	{/if}
		if ('undefined' === typeof(CarriersHandler)) {
			if ('undefined' !== typeof($.eonCacheScript)) {
				$.eonCacheScript('{$url_carriers_js|escape:"javascript"}', {
					success: function(content) {
						CarriersHandler.init(
							version,
							opc,
							id_carrier,
							carriers_shm,
							l_messages,
							'{$url_carrierbox|escape:"javascript"}'
							);
					},
					error: function(jqXHR, textStatus, error) {
						debugger;
						console.log(textStatus);
					}
				});	
			}
		}
		else
			if (id_carrier in carriers_shm)
				CarriersHandler.refreshCarrierInfo(id_carrier);	
{/if}	

	});
})(jQuery);
// ]]>
</script>