{*
* 2014-2015 Stigmi
*
* @author Serge <serge@stigmi.eu>
* @author thirty bees <contact@thirtybees.com>
* @copyright 2014-2015 Stigmi
* @copyright 2017 Thirty Development, LLC
* @license http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
*}
<script type="text/javascript">
  var fancy_pop = false;

  function reloadPage() {
    window.location.replace("{$reload_href|escape:'javascript':'UTF-8'}");
    // All smarty escape attempts FAIL!!
    // window.location.replace("{$reload_href|escape:'url':'UTF-8'}");
  }

  function showErrors(errors) {
    var err_msgs = '<ul class="eonbox-err">';
    $.each(errors, function (title, type_errors) {
      err_msgs += '<h4>' + title + '</h4>';
      $.each(type_errors, function (i, err) {
        err_msgs += '<li>' + err + '</li>';
      });
    });
    // eonBox.displayError(err_msgs);
    eonBox.displayError(err_msgs, function () {
      reloadPage();
    });
  }

  (function ($) {
    $(function () {
      /* Tabs */
      var $str_open = "{$str_tabs['open']|escape:'javascript':'UTF-8'}",
        $str_treated = "{$str_tabs['treated']|escape:'javascript':'UTF-8'}",
        $table = $('table.order_bpost'),
        $thead = $table.find('thead'),
        tr_list = [];

      $table.find('td.treated_col').each(function (i, td) {
        var $td = $(td);
        if ($td.text().trim() == 1){tr_list.push($td.closest('tr'));
}
      });


      /* Tabs */
      // // remove treated column;
      var $first_row = $table.find('tbody tr:eq(0)'),
        $first_row_haystack = $first_row.children('td'),
        $first_row_needle = $first_row.children('td.treated_col'),
        position = $first_row_haystack.index($first_row_needle);

      if (0 == $('td.list-empty').length) {
        $('tr, colgroup', 'table.order_bpost').each(function () {
          $(this).children(':eq(' + position + ')').not('.list-empty').remove();
        });
      }

      // sep list
      if (tr_list.length) {
        var $table_treated = $table.clone(),
          $parent;

        $table_treated
          .find('thead').remove().end()
          .find('tbody').empty();

        $.each(tr_list, function (i, tr) {
          $table_treated.append(tr);
        });


        $parent = $('.table-responsive');
        $parent.before(
          $('<ul id="idTabs" class="tab nav nav-tabs" />')
            .append(
              '<li class="tab-row active"><a href="#tab1">' + $str_open + '</a></li>',
              '<li class="tab-row"><a href="#tab2">' + $str_treated + '</a></li>'
            ));
        $parent.prepend(
          $('<div id="tab1" />').append($table),
          $('<div id="tab2" style="display: none;" />').append($table_treated));
        /*
        $('#idTabs a').on('click', function(e) {
          var $link = $(this),
            $li = $link.parent();

          $li.addClass('active').siblings().removeClass('active');
          $parent.children().hide();
          $($link.attr('href')).show();
          // This was the 1.6 Treated header problem
          $thead.prependTo('.order_bpost:visible');
        }); */


        $('#idTabs').idTabs()
          .find('a').on('click', function () {

          var $link = $(this),
            $li = $link.parent();

          $li.addClass('active').siblings().removeClass('active');

          $thead.prependTo('.order_bpost:visible');
        });

        if ('undefined' !== typeof location.hash && '#tab2' === location.hash){$('#idTabs').find('li:eq(1) a').trigger('click');
}

      }

      /* Actions */
      $('select.actions')
        .on('change', function (e) {
          if (this.value) {
            if ('undefined' !== typeof $(this).children(':selected').data('target')) {
              window.open(this.value);
              reloadPage();
              return;
            }

            $.get(this.value, {}, function (response) {
              var has_errors = 'undefined' !== typeof response.errors,
                has_links = 'undefined' !== typeof response.links;
              if (has_errors){showErrors(response.errors);
}

              if (has_links) {
                if (fancy_pop){eonBox.reset();
}
                $.each(response.links, function (i, link) {
                  if (fancy_pop){eonBox.addLink(link);
}else {window.open(link);
}

                });
                if (fancy_pop){eonBox.open('', function () {
                    reloadPage();
                  });
}else if (!has_errors) {
                  reloadPage();
                  // return;
                }
                return;
              }

              if (!has_errors && !has_links && response) {
                reloadPage();
                return;
              }

            }, 'JSON');
          }
        })
        .children(':disabled').on('click', function () {
        var $option = $(this);
        if ($option.data('disabled')){eonBox.display($option.data('disabled'));
}
      });

      $('img.print').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var $img = $(this);

        if ('undefined' !== typeof $img.data('labels'))$.get($img.data('labels'), {}, function (response) {
            var has_errors = 'undefined' !== typeof response.errors,
              has_links = 'undefined' !== typeof response.links;
            if (has_errors){showErrors(response.errors);
}

            if (has_links) {
              if (fancy_pop){eonBox.reset();
}
              $.each(response.links, function (i, link) {
                if (fancy_pop){eonBox.addLink(link);
}else {window.open(link);
}

              });
              if (fancy_pop){eonBox.open('', function () {
                  reloadPage();
                });
}else if (!has_errors) {
                reloadPage();
                // return;
              }
              return;
            }
          });
      });

      {if isset($remove_info_link)}
      /* info link */
      $('#aob-info-remove').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();

        var remove_link = "{$remove_info_link|escape:'javascript':'UTF-8'}";
        // window.location.replace(remove_link);
        $.get(remove_link, {}, function (response) {
          var has_errors = 'undefined' !== typeof response.errors;
          if (!has_errors)
            $('#aob-info').slideUp(200);
          // reloadPage();
        }, 'JSON');
      });
      {/if}

      /* Bulk actions */
      var chkboxes = $('input[name="order_bpostBox[]"]');

      // New Print bulk
      chkboxes.prop('checked', false);
      {if !empty($errors) && is_array($errors)}
      var errors = {$errors|json_encode};
      showErrors(errors);
      {/if}
      {if !empty($labels) && is_array($labels)}
      var txt_labels = '',
        labels = {$labels|json_encode};

      // chkboxes.prop('checked', false);
      eonBox.reset();
      $.each(labels, function (i, link) {
        if (fancy_pop)
          eonBox.addLink(link);
        else
          window.open(link);

      });
      eonBox.open('', function () {
        reloadPage();
      });

      // if (!fancy_pop)
      // 	reloadPage();

      return;
      /*
      eonBox.reset();
      $.each(labels, function(i, label) {
        txt_labels += '<li>' + label + '</li>';
      });
      eonBox.display(txt_labels);
      */
      {/if}
      /* Bulk actions end */
    });
  })(jQuery);
</script>
