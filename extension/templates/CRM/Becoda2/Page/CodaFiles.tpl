<style>
tr.collapsible {ldelim}
  background: url("{$base_url}/sites/all/modules/civicrm/i/TreeMinus.gif") 5px 5px no-repeat;
{rdelim}
tr.collapsible-closed {ldelim}
  background: url("{$base_url}/sites/all/modules/civicrm/i/TreePlus.gif")  3px 7px no-repeat;
{rdelim}
tr.row {ldelim}
  background-color: #eee;
{rdelim}
.log pre {ldelim}
  line-height: 1.2em;
{rdelim}
.log pre span.bb {ldelim} font-weight: bold; {rdelim}
.log pre span.red {ldelim} color: red; {rdelim}
</style>

<button style="float: right;" onclick="document.location='./dashboard';">Back to CODA dashboard</button>
    
{if $pagemode == 'single'}
<p>
  Read {$path} ...
</p>
<div class="log">
  <pre>{foreach from=$log item=logitem}<span class="{$logitem.cls}">{$logitem.msg}</span>
{/foreach}</pre>
  </div>
  <p>
    <button onclick="document.location='?mode=list&a={$account}';">Back to list of CODA files</button>
  </p>
  
{/if}

  {if $pagemode == 'group'}
<p>
  Read {$path} ...
</p>
<div class="log">
  <pre>{foreach from=$log item=logitem}<span class="{$logitem.cls}">{$logitem.msg}</span>
{/foreach}</pre>
  </div>
  <p>
    <button onclick="document.location='?mode=list&a={$account}';">Back to list of CODA files</button>
  </p>
  
{/if}
  
{if $pagemode == 'list'}
<p>
  These are the files to read :
</p>

<div class="crm-container">
  <table>
    {assign var=one value=''}
    {foreach from=$files key=account item=fs}
      {if ($one != $account)}
        <tr style="height: 8px; background-color: #cef;" class="collapsible {if $currentAccount != $account}collapsible-closed{/if}" onclick="cj(this).toggleClass('collapsible-closed');cj('.row_{$account}').toggle();">
          <td colspan=3 style="padding-left: 20px; width: 150px;">
            <b>{$account}</b>
            <br>
            {$banames.$account}
          </td>
          <td style="width: 100px;  text-align: center;vertical-align: middle;">
            <b><span style="font-size: 18px; color: #999;">{$fs|@count}</span></b>
          </td>
          <td style="width:400px; vertical-align: middle;">
            files to read
            <button style="float: right;" onclick="document.location='?mode=group&a={$account}';">Read all files</button>
          </td>
        </tr>
      {/if} 
      {foreach from=$fs key=modified item=seqs}
        {foreach from=$seqs key=seq item=f}
          <tr class="row row_{$account}" {if $currentAccount != $account} style="display: none;"{/if}>
            <td style="width: 20px; background-color: white;">
            </td>
            <td style="width: 40px;  text-align: center;">
              <b>{$seq}</b>
            </td>
            <td style="text-align: center;">
              {$modified}
            </td>
            <td style="text-align: center;">
              <button onclick="document.location='?mode=single&a={$account}&p={$f}';">Read this file</button>
            </td>
            <td style="color: #999;width: 40px;">
              {$f}
            </td>
          </tr>
          {assign var=one value=`$account`}
        {/foreach}
      {/foreach}
    {/foreach}
  </table>
</div>

<p>
  * Since a CODA file may contain bank statements for multiple bank accounts, 
  this information is indicative.
</p>
{/if}