<p>
  This page allows you to monitor and control the CODA import process.
</p>


<div class="crm-block crm-content-block c">
  <div class="ui-tabs ui-widget ui-widget-content ui-corner-all" id="mainTabContainer">
    <ul class="crm-contact-tabs-list ui-tabs-nav ui-helper-reset ui-helper-clearfix ui-widget-header ui-corner-all" role="tablist">
      <li class="crm-tab-button ui-state-default ui-corner-top {if $page eq 'files'}ui-tabs-active ui-state-active{/if}  ui-corner-bottom" id="tab_files" >
        <a title="Input files" href="?page=files" class="ui-tabs-anchor" >
          <span> </span> Input files                
          <em>17</em>
        </a>
      </li>
      <li class="crm-tab-button ui-state-default ui-corner-top {if $page eq 'stmts'}ui-tabs-active ui-state-active{/if} ui-corner-bottom" id="tab_stmts" >
        <a title="Bank statements to process" href="?page=stmts" class="ui-tabs-anchor" >
          <span> </span> Bank statements to process                
          <em>15</em>
        </a>
      </li>
    </ul>

{if $page eq 'files'}
    <div class="ui-tabs-panel ui-widget-content ui-corner-bottom" >
      <div class="contactTopBar contact_panel">
        <div class="contactCardLeft">
          {foreach from=$fs item=f}
            <input type="submit" value="Read"> {$f.name} - {$f.when}
            <br/>
          {/foreach}
        </div> <!-- end of left side -->
        <div class="contactCardRight">
          Hihi
        </div> <!-- end of right side -->
      </div>
    </div>
{/if}

{if $page eq 'stmts'}
    <div class="ui-tabs-panel ui-widget-content ui-corner-bottom" >
      <div class="contactTopBar contact_panel">
        
        <div class="contactCardLeft">
          Hoho
        </div> <!-- end of left side -->
        <div class="contactCardRight">
          Hihi
        </div> <!-- end of right side -->
      </div>
    </div>
{/if}
    
    <div class="clear"></div>
  </div>
  <div class="clear"></div>
</div>

