<script>
{if $mautic_groups}
var mautic_groups = {$mautic_groups};
{else}
var mautic_groups = {};
{/if}

{literal}
function mauticGroupsPageAlter() {
  if (!mautic_groups) {
    return;
  }
  // Add header only once
  if (cj('table.crm-group-selector thead th.crm-mautic').length < 1) {
    cj('table.crm-group-selector thead th.crm-group-visibility').after(
       '<th class="crm-mautic">Mautic Sync</th>');
  }

  var rows = cj('table.crm-group-selector tbody tr');
  rows.each(function() {
    var row = cj(this);
    var group_id = row.data('id');
    if (!group_id) return;
    var group_id_index = 'id' + group_id;

    var mautic_td = row.find('td.crm-mautic');
    if (mautic_td.length < 1) {
      mautic_td = cj('<td class="crm-mautic" />');
      row.find('td.crm-group-visibility').after(mautic_td);
    }

    if (mautic_groups[group_id_index]) {
      mautic_td.text(mautic_groups[group_id_index]);
    } else {
      mautic_td.text('');
    }
  });
}
{/literal}

{if $action eq 16}
{* action 16 is VIEW, i.e. the Manage Groups page.*}
{literal}
  cj('table.crm-group-selector').on( 'draw.dt', function () {
    mauticGroupsPageAlter();
  });

  cj(document).on('crmFormSuccess', function() {
    cj.getJSON(CRM.url('civicrm/group', {reset: 1, snippet: 4, mautic_groups_refresh: 1}), function(data) {
       mautic_groups = data;
       mauticGroupsPageAlter();
    }).fail(function(jqXHR, textStatus, errorThrown) {console.warn("getJSON request failed! Status:", textStatus, "Error:", errorThrown)});
  });
{/literal}
{/if}
</script>
