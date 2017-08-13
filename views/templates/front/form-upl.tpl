{*
* 2014 Stigmi
*
* @author Stigmi.eu <www.stigmi.eu>
* @copyright 2014 Stigmi
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}

{if !empty($step)}
<div id="service-point" class="at-247{if $version >= 1.5 && $version < 1.6} v1-5{/if}">	
{if 1 == $step|intval}
	<div class="clearfix">
		<h1 class="col-xs-12"{if $version < 1.5} style="line-height: 4.6em;"{/if}>
			<span class="step">1</span>
			{l s='Parcel locker selection process' mod='bpostshm'}
			<img class="loader" src="{$module_dir|escape}views/img/ajax-loader.gif" alt="{l s='Loading...' mod='bpostshm'}" />
		</h1>
		<p class="col-xs-12" style="padding: 15px 0 15px 15px;">
			<a href="{l s='parcel locker info link' mod='bpostshm'}" target="_blank">{l s='Click here' mod='bpostshm'}</a>
			{l s='for more information on the parcel locker delivery method' mod='bpostshm'}.
		</p>
	</div>
	{if isset($upl_info)}
	<div id="unregister" class="clearfix">		
		<form class="col-xs-6 center" action="{$url_post_upl_unregister|escape}" id="upl-unregister" method="POST" autocomplete="off" novalidate="novalidate">
			<div class="row clearfix">
				<label for="email">{l s='E-mail' mod='bpostshm'}</label>
				<input type="text" name="eml" id="eml" value="" required="required" />
				<sup>*</sup>
			</div>
			<div class="row clearfix">
				<label for="mobile-number">
					{l s='Mobile phone' mod='bpostshm'}
					<img class="info" src="{$module_dir|escape}views/img/icons/information.png" data-tip="mobile">
				</label>
				<input type="text" name="mob" id="mob" value="" />
			</div>
			<div class="row clearfix">
				<label for="rmz">
					{l s='Reduced mobility zone' mod='bpostshm'}
					<img class="info" src="{$module_dir|escape}views/img/icons/information.png" data-tip="mobi-zone">
				</label>
				<input type="checkbox" name="rmz" id="rmz" value="1" {if $upl_info['rmz']}checked="checked"{/if} />
			</div>
			<div class="row last">
				<br /><br />
				<input type="submit" class="button" value="{l s='Proceed' mod='bpostshm'}" />
			</div>
			<div class="row clearfix legend">
				<label>
					* {l s='required' mod='bpostshm'}
				</label>
			</div>
		</form>
	</div>
	{/if}
	<script type="text/javascript">
		$(function() {

			var tip_mobile = {
				style: { classes:'qtip-bootstrap' },
				content: { text: '' }
			},
			tip_rmz = $.extend(true, {}, tip_mobile);
			tip_mobile.content.text = "{l s='Mobile number info' mod='bpostshm' js=1}";
			tip_rmz.content.text = "{l s='Reduced mobility zone info' mod='bpostshm' js=1}";
			$('[data-tip="mobile"]').qtip(tip_mobile);
			$('[data-tip="mobi-zone"]').qtip(tip_rmz);
			
			//
			var upl = {$upl_info|json_encode};
			$('input#eml').val(upl.eml);
			$('input#mob').val(upl.mob);

			$('.loader').hide();
			$(document)
				// .tooltip()
				.ajaxStart(function() {
					$('.loader').css('display', 'inline-block');
				})
				.ajaxComplete(function() {
					$('.loader').hide();
				});

			$('#upl-unregister').live('submit', function(e) {
				e.preventDefault();
				e.stopPropagation();

				var $form 	= $(this),
					$errors = [];

				$('span.field-error').remove();
				$.each($form.find('[type="text"]'), function(i, field) {
					var $field = $(field);

					$field.removeClass('error');
					if ($field.is('input#eml') && !reMail.test(field.value))
						$errors.push($field);
					else if ($field.is('input#mob')) {
						val = field.value.replace(/[\s\(\)]/g, '');
						if (val.length) { 
							if (reMobileBE.test(val))
								field.value =  '0' + val.substring(val.length-9);
							else
								$errors.push($field);
						}
					}
				});
				if ($errors.length)
				{
					var field_error_msg = "{l s='Incorrect format' mod='bpostshm' js=1}";
					$.each($errors, function(i, $field) {
						$field.addClass('error')
							.after($('<span class="field-error">'+field_error_msg+'</span>'))
							.fadeOut('slow', function(){
								$(this).fadeIn('slow');
							});
					});
					return;
				}

				upl.eml = $('input#eml').val();
				upl.mob = $('input#mob').val();
				upl.rmz = $('input#rmz').prop('checked'); 

				$.post( $form.attr('action'), { post_upl_info: JSON.stringify(upl) } )
					.done(function(response) {
						if (null != response['Error'])
							// srgDebug.traceJson(response);
							eonBox.displayError(response.Error);
							//srgDebug.trace("{l s='Registration failed.' mod='bpostshm'}");
						else
				  			$(location).attr('href', "{$url_get_point_list|escape:'javascript'}");

					}, "json")
					.fail(function( jqXHR, textStatus, error ) {
				    	var err = textStatus + '<br>' + error + '<br>';
				    	err += jqXHR.responseText;
				    	//srgDebug.trace(err);
					});

			});

			$('#register-247').live('submit', function(e) {
				e.preventDefault();
				e.stopPropagation();

				
				var dob = $('input[name="date_of_birth"]');
				if ('' != dob.val() && !reDate.test(dob.val())) {
					dob.fadeOut('slow').fadeIn('slow');
					dob.val('');
				}
				
				var elm_mobile = $('input#mobile-number'),
					mob_num = elm_mobile.val().replace(/[\s\(\)]/g, '');
				if ('' != mob_num && reMobileBE.test(mob_num)) 
					elm_mobile.val(mob_num.substring(mob_num.length-9));
				else {
					elm_mobile.fadeOut('slow').fadeIn('slow');
					elm_mobile.val('');
				}
				
				var $form 	= $(this),
					$errors = [];

				$.each($form.find('[required]'), function(i, field) {
					var $field = $(field);

					$field.removeClass('error');
					if ($field.is('select, [type="text"]') && '' == field.value)
						$errors.push($field);
					else if ($field.is('#mobile-number')) {
						val = field.value.replace(/[\s\(\)]/g, '');
						if (reMobileBE.test(val))
							field.value = val.substring(val.length-9);
						else
							$errors.push($field);
					}
					else if ($field.is('[type="checkbox"]') && !$field.is(':checked'))
						$errors.push($field{if $version > 1.5}.parent(){/if});

				});

				if ($errors.length)
				{
					$.each($errors, function(i, $field) {
						$field.addClass('error')
							.fadeOut('slow', function(){
								$(this).fadeIn('slow');
							});
					});
					return;
				}

				$.post( $form.attr('action'), $form.serialize() )
					.done(function(response) {
						if (null != response['Error'])
							// srgDebug.traceJson(response);
							eonBox.displayError(response.Error);
							//srgDebug.trace("{l s='Registration failed.' mod='bpostshm'}");
						else
				  			$(location).attr('href', "{$url_get_point_list|escape:'javascript'}");

					}, "json")
					.fail(function( jqXHR, textStatus, error ) {
				    	var err = textStatus + '<br>' + error + '<br>';
				    	err += jqXHR.responseText;
				    	//srgDebug.trace(err);
					});

			});
		});
	</script>
{/if}
</div>
{/if}