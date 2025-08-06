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
    var mauticSettingsInitialized = false;
    debugger
    
    // Function to initialize Mautic settings when the custom field is available
    function initializeMauticSettings() {
      // Prevent multiple initializations
      if (mauticSettingsInitialized) {
        return;
      }
      
      var $customField = $("input[data-crm-custom='Mautic_Settings:Mautic_Segment']");
      
      if ($customField.length === 0) {
        // Custom field not yet loaded, check again later
        setTimeout(initializeMauticSettings, 100);
        return;
      }

      
      var mautic_settings = $('#mautic_settings').html();
      var mautic_segment_id = parseInt($customField.val());

      mautic_settings = mautic_settings.replace("<tbody>", "");
      mautic_settings = mautic_settings.replace("</tbody>", "");
      $customField.parent().parent().after(mautic_settings);
      $customField.parent().parent().hide();
      $("#mautic_segment_tr").hide();
      
      // Mark as initialized
      mauticSettingsInitialized = true;

      // action on selection of integration radio options
      var toggleRows = $('.show-enabled');
      $("input:radio[name=mautic_integration_option]").change(function() {
        if (parseInt($(this).val()) === 1) {
          $customField.val(mautic_segment_id);
          toggleRows.show();
        } else {
          toggleRows.hide();
          mautic_segment_id = $customField.val();
          $customField.val('');
        }
      }).filter(':checked').trigger('change');

      $("#mautic_segment").change(function() {
        var segment_id = parseInt($("#mautic_segment :selected").val());
        $customField.val(segment_id);
      });
    }
    
    // Start the initialization process
    CRM.$(document).ready(function() {
      initializeMauticSettings();
    });
    
    // Also listen for CiviCRM's custom AJAX events in case the field is loaded later
    CRM.$(document).on('crmLoad', function(event) {
      if ($(event.target).find("input[data-crm-custom='Mautic_Settings:Mautic_Segment']").length > 0) {
        initializeMauticSettings();
      }
    });
  }(CRM.$));

</script>
{/literal}
