<div class="crm-block crm-form-block crm-mautic-sync-form-block">
  {if $smarty.get.state eq 'done'}
    <div class="help">
      {if $dry_run}
        <p>
        {ts}<strong>Dry Run: no changes made.</strong>{/ts}
      {/if}
        </p>
      {ts}Push completed:{/ts}<br/>
      {foreach from=$stats item=group}
      <h2>{$group.name}</h2>
      <table class="form-layout-compressed">
      <tr><td>{ts}Contacts on CiviCRM{/ts}:</td><td>{$group.stats.c_count}</td></tr>
      <tr><td>{ts}Contacts on Mautic (originally){/ts}:</td><td>{$group.stats.mautic_count}</td></tr>
      <tr><td>{ts}Contacts that were already in sync{/ts}:</td><td>{$group.stats.in_sync}</td></tr>
      <tr><td>{ts}Contacts updated at Mautic{/ts}:</td><td>{$group.stats.updates}</td></tr>
      <tr><td>{ts}New Contacts added to Segment {/ts}:</td><td>{$group.stats.additions}</td></tr>
      <tr><td>{ts}Contacts Removed from Segment{/ts}:</td><td>{$group.stats.unsubscribes}</td></tr>
      </table>
      {/foreach}
    </div>
    {if $error_messages}
    <h2>Error messages</h2>
    <p>These errors have come from the last sync operation.</p>
    <table>
    <thead><tr><th>Group Id</th><th>Name and Email</th><th>Error</th></tr>
    </thead>
    <tbody>
    {foreach from=$error_messages item=msg}
      <tr><td>{$msg.group}</td>
      <td>{$msg.name} {$msg.email}</td>
      <td>{$msg.message}</td>
    </tr>
    {/foreach}
    </tbody>
    </table>
    {/if}
    {if $mauticDeletedInCivi}
      <h2>Contacts in Mautic with CiviCRM contact IDs that do not exist in CiviCRM</h2>
      <p>This can happen when you have deleted a contact in CiviCRM.</p>
      <table>
        <thead><tr><th>Mautic Contact ID</th><th>CiviCRM Contact ID</th></tr></thead>
        <tbody>
        {foreach from=$mauticDeletedInCivi item=msg}
          <tr>
            <td>{$msg.mautic_cid}</td>
            <td>{$msg.civicrm_cid}</td>
          </tr>
        {/foreach}
        </tbody>
      </table>
    {/if}
  {else}
    <h3>{ts}Push contacts from CiviCRM to Mautic{/ts}</h3>
    <p>{ts}Running this will assume that the information in CiviCRM about who belongs in the Mautic segment is correct.{/ts}</p>
    <p>{ts}Points to know:{/ts}</p>
    <ul>
      <li>{ts}If a contact is not in the group at CiviCRM, they will be removed from the Mautic segment (assuming they are currently subscribed at Mautic).{/ts}</li>
      <li>{ts}If a contact is in the group, they will be included in the Mautic segment.{/ts}</li>
      <li>{ts}You can use Dry Run to view the numbers of contacts in each group without making any changes.{/ts}</li>
      <li>{ts}If somone's name is different, the Mautic name is replaced by the CiviCRM name (unless there is a name at Mautic but no name at CiviCRM).{/ts}</li>
      <li>{ts}This is a "push" <em>to</em> Mautic operation..{/ts}</li>
    </ul>
    {$summary}
    {$form.mautic_dry_run.html} {$form.mautic_dry_run.label}
    <div class="crm-submit-buttons">
      {include file="CRM/common/formButtons.tpl"}
    </div>
  {/if}
</div>
