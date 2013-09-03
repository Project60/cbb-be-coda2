{literal}<style>
.row {
  padding: 4px;
  border: 1px solid #eee;
  border-radius: 4px;
  margin: 0 0 4px 0;
  }
.stat {
  padding: 6px;
  background-color: #ccc;
  border-radius: 4px;
  width: 150px;
  text-align: center;
  float: left;
  }
.stat span {
  color: white;
  font-size: 48px;
  line-height: 48px;
  }
.row .comments {
  padding: 4px 4px 0px 4px;
  margin-left: 170px;
  margin-right: 100px;
  }
.row button {
  float: right;
  }
</style>{/literal}

<p>
  This page allows you to monitor and control the CODA import process.
</p>

<div>
  <div class="row">
    <div class="stat">
      Files to read
      <br/>
      <span>{$filecount}</span>
    </div>
    <button onclick="document.location='./files';">Read files</button>
    <div class="comments clearfix">
      These are CODA files placed in the inbox/ folder which need to be read. 
    </div>
  </div>
    
  <div class="row">
    <div class="stat">
      Statements to import
      <br/>
      <span>{$batchcount}</span>
    </div>
    <button onclick="document.location='./batches';">Import statements</button>
    <div class="comments clearfix">
      These are individual bank statements to convert into bank transactions. 
    </div>
  </div>
</div>

    
<div class="clear"></div>

