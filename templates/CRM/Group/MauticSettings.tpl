<table id="mautic_settings" style="display:none;">
  <tr class="custom_field-row" id="mautic_integration_option_0">
    <td colspan=2>
      {$form.mautic_integration_option.0.html}
    </td>
  </tr>
  <tr class="custom_field-row" id="mautic_integration_option_1">
    <td colspan=2>
      {$form.mautic_integration_option.1.html}
    </td>
  </tr>
  <tr class="custom_field-row mautic_segment show-enabled" id="mautic_segment_tr">
    <td class="label">{$form.mautic_segment.label}</td>
    <td class="html-adjust">{$form.mautic_segment.html}</td>
  </tr>
</table>

{literal}
<script>
  (function($) {
    var mautic_settings = $('#mautic_settings').html();
    var mautic_segment_id = parseInt($("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").val());
    mautic_settings = mautic_settings.replace("<tbody>", "");
    mautic_settings = mautic_settings.replace("</tbody>", "");
    $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").parent().parent().after(mautic_settings);
    $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").parent().parent().hide();
    $("#mautic_segment_tr").hide();

    // action on selection of integration radio options
    var toggleRows = $('.show-enabled');
    $("input:radio[name=mautic_integration_option]").change(function() {
      if (parseInt($(this).val()) === 1) {
        $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").val(mautic_segment_id);
        toggleRows.show();
      } else {
        toggleRows.hide();
        mautic_segment_id = $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").val();
        $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").val('');
      }
    }).filter(':checked').trigger('change');

    $("#mautic_segment").change(function() {
      var segment_id = parseInt($("#mautic_segment :selected").val());
      $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").val(segment_id);
    });
  }(CRM.$));

</script>
{/literal}
