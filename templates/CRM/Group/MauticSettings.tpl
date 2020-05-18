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
<tr class="custom_field-row mautic_fixup show-enabled" id="mautic_fixup_tr">
    <td colspan=2>{$form.mc_fixup.html}{$form.mc_fixup.label}<br />
      <span class="description">{ts}If this is ticked when you press Save,
      CiviCRM will edit the webhook settings of this Mautic segment to make
      sure they're configured correctly. The only time you would want to
      <em>untick</em> this box is if you are doing some development on a local
      server because that would result in supplying an invalid webhook URL to a
      possibly production list at mautic. So basically leave this ticked,
      unless you know what you're doing.{/ts}</span>
    </td>
</tr>
</table>

{literal}
<script>
cj( document ).ready(function() {
    var mautic_settings = cj('#mautic_settings').html();
    mautic_settings = mautic_settings.replace("<tbody>", "");
    mautic_settings = mautic_settings.replace("</tbody>", "");
    cj("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").parent().parent().after(mautic_settings);
    cj("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").parent().parent().hide();
    cj("#mautic_segment_tr").hide();

    // action on selection of integration radio options
    var toggleRows = cj('.show-enabled');
    cj("input:radio[name=mautic_integration_option]").change(function() {
      var intopt = cj(this).val();
      if (intopt == 1) {
        toggleRows.show();
      } else {
        toggleRows.hide();
      }
    }).filter(':checked').trigger('change');
   
    cj("#mautic_segment").change(function() {
      var segment_id = cj("#mautic_segment :selected").val();
      if (segment_id  == 0) {
          segment_id = '';
      }
      cj("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").val(segment_id);
      console.log( cj("input[data-crm-custom='Mautic_Settings:Mautic_Segment']"));
    }); 
    {/literal}
    {if $action eq 2}
    {if !isset($mautic_segment_id)}
      {assign var="mautic_segment_id" value="0"}
    {/if}
    {literal}
        var mautic_segment_id = {/literal}{$mautic_segment_id}{literal};
        var list_id = cj("#mautic_segment :selected").val();
    {/literal}
    {/if}
    {literal}

});


</script>
{/literal}
