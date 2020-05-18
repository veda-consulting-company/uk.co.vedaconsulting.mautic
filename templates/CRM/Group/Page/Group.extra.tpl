<script>

{literal}
function mauticGroupsPageAlter() {

  // Add header only once
  if (cj('table.crm-group-selector thead th.crm-mautic').length < 1) {
    cj('table.crm-group-selector thead th.crm-group-visibility').after(
       '<th class="crm-mautic">Mautic Sync</th>');
  }
  
  var rows = cj('table.crm-group-selector tbody tr');
  rows.each(function() {
    var row = cj(this);
    var group_id_index = 'id' + row.data('id');
    var mautic_td = cj('<td class="crm-mautic" />');
    row.find('td.crm-group-visibility').after(mautic_td);
  });
}
{/literal}
{if $action eq 16}
{* action 16 is VIEW, i.e. the Manage Groups page.*}
{literal}
  cj('table.crm-group-selector').on( 'draw.dt', function () {
    mauticGroupsPageAlter();
  });
{/literal}
{/if}
</script>

{if $action eq 2}
    {* action 16 is EDIT a group *}
    {include file="CRM/Group/MauticSettings.tpl"}
{/if}
