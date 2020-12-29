{* HEADER *}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="top"}
</div>

<div class="crm-container">
{foreach from=$sections item=section key=sectionName}
   <div class="crm-form-block wrapper-{$sectionName}">
    <h3>{$sectionTitles.$sectionName.title}</h3>
    {if $sectionTitles.$sectionName.help}
    <div class="help">{$sectionTitles.$sectionName.help} </div>
    {/if}
  {foreach from=$section item=element key=k}
    <div class="crm-section wrapper-{$element}">
      <div class="label">
         {$form.$element.label}
         {if $settings_fields.$element.is_required}
         <span class="crm-marker">*</span>
         {/if}
      </div>
      <div class="content">
          {$form.$element.html}
      <div class="description">{$settings_fields.$element.description}</div>
      </div>
      <div class="clear"></div>
    </div>
  {/foreach}
  </fieldset>
   </div>
{/foreach}
</div>
<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
<script>
{literal}
(function($) {
  $('[name=mautic_connection_authentication_method]').change(function() {
    var methods = ['basic', 'oauth1', 'oauth2'];
    for (var i=0; i < methods.length; i++) {
      var method = methods[i];
      var methodSelected = method == $(this).val();
      $('.wrapper-mautic_' +  method ).toggle(methodSelected)
      .find('input,select').each(function() {
         $(this).attr('required', methodSelected)
      });
    }
  }).trigger('change');
  $('[name=mautic_sync_tag_method]').change(function() {
    var show = $(this).val() == 'sync_tag_children';
    console.log(show);
    $('.wrapper-mautic_sync_tag_parent').toggle(show);
  }).trigger('change');

}(CRM.$));
{/literal}
</script>
