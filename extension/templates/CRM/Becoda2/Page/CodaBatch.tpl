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
  tr.row1 td {ldelim}
  padding: 0px 4px;
  {rdelim}
  .log pre {ldelim}
  line-height: 1.2em;
  {rdelim}
  .log pre span.bb {ldelim} font-weight: bold; {rdelim}
  .log pre span.red {ldelim} color: red; {rdelim}
  span.rek {ldelim} 
  color: #999;
  font-size: 10px;
  {rdelim}
</style>

<button style="float: right;" onclick="document.location = './dashboard';">Back to CODA dashboard</button>

{if $pagemode == 'single'}
  <p>
    Processed {$path} ...
  </p>
  <div class="log">
    <pre>{foreach from=$log item=logitem}<span class="{$logitem.cls}">{$logitem.msg}</span>
      {/foreach}</pre>
  </div>
  <p>
    <button onclick="document.location = '?mode=list&a={$account}';">Back to list of CODA statements</button>
  </p>
{/if}

  
  
{if $pagemode == 'list'}
  <p>
    These are the bank statements to process :
  </p>

  <div class="crm-container">
    <table>
      {assign var=one value=''}
      {foreach from=$batches key=account item=stmts}
        {if ($one != $account)}
          <tr style="height: 8px; background-color: #cef;" class="collapsible {if $currentAccount != $account}collapsible-closed{/if}" onclick="cj(this).toggleClass('collapsible-closed');
cj('.row_{$account}').toggle();">
            <td colspan=3 style="padding-left: 20px; width: 150px;">
              <b>{$account}</b>
              <br>
              {$banames.$account}
            </td>
            <td style="width: 100px;  text-align: center;vertical-align: middle;">
              <b><span style="font-size: 18px; color: #999;">{$stmts|@count}</span></b>
            </td>
            <td style="width:400px;vertical-align: middle;" colspan="2">
              statements to process
              <button style="float: right;" onclick="document.location = '../banking/import';">Import</button>
            </td>
          </tr>
        {/if} 
        {foreach from=$stmts key=seq item=stmt}
          <tr class="row row1 row_{$account}" {if $currentAccount != $account} style="display: none;"{/if}>
            <td style="width: 20px; background-color: white;" rowspan="2">
            </td>
            <td style="width: 40px;  text-align: center; vertical-align: middle;" rowspan=2>
              <b>{$seq}</b>
            </td>
            <td style="text-align: center;">
              <span class="rek" >{$stmt.starting_date}</span>
            </td>
            <td  style="text-align: center;">
              <span class="rek">{$stmt.ending_date}</span>
            </td>
            <td style="width: 40px;  text-align: center; vertical-align: middle;" rowspan=2>
              {$stmt.ntx} TX
            </td>
            <td style="text-align: center;vertical-align: middle;"  rowspan=2>
            </td>
          </tr>
          <tr class="row row_{$account}" {if $currentAccount != $account} style="display: none;"{/if}>
            <td style="text-align: center;background-color: #ffc;">
              {$stmt.starting_balance} &euro;
            </td>
            <td  style="text-align: center;background-color: #ffc;">
              {$stmt.ending_balance} &euro;
            </td>

          </tr>
          {assign var=one value=`$account`}
        {/foreach}
      {/foreach}
    </table>
  </div>


{/if}