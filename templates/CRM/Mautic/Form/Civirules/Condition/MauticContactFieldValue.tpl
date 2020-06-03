{* HEADER *}

{foreach from=$elementNames item=elementName}
  {if $elementName ne 'field_name'}
    {assign var="addClass" value="fval"}
  {else}
    {assign var="addClass" value="fname"}
  {/if}
  <div class="crm-section {$addClass}">
    <div class="label">{$form.$elementName.label}</div>
    <div class="content">{$form.$elementName.html}</div>
    <div class="clear"></div>
  </div>
{/foreach}

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{literal}
<script>
(function($) {
  $('select#field_name').change(function() {
    $('.fval').hide();
    $('#' + $(this).val()).parents('.fval').show();
  }).trigger('change');
}(cj));
</script>
{/literal}