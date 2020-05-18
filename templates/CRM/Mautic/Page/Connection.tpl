<h3>Mautic Connection</h3>

{if $content}
  {$content}
{/if}
<div class="crm-container">
{foreach from=$sections item="section"}
<div class="crm-section">
  <div class="crm-form-block">
    <h3>{$section.title}</h3>
    {if $section.help}
    <div class="help crm-help">
     {$section.help}
    </div>
    {/if}
    <div class="crm-status">
      {$section.content}
    </div>
    <div class="crm-action">
      {$section.action}
    </div>
  </div>
</div>
<div class="clear"></div>
{/foreach}

</div>