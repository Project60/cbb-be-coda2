# Structure of the plugin

All classes reside in the `CRM/Becoda2/PluginImpl/` folder. The general concept is this :

* Becoda2.php contains the actual plugin implementation
* File.php contains a class responsible for reading a CODA file and processing the headers
* Reader/V2.php is the base record interpreter for CODA v2.x
* Reader/V2xxx.php extend for the specific syntaxes of banks/releases

We have encountered files containing CODA data for multiple accounts. For that reason, the outer loop handles the physical file as a sequence of batches
which can have different CODA versions (however unlikely this is).

The Becoda2 plugin will instantiate the Becoda2_File class using a pathname. This instance functions like a stream and will (at the highest level) return 
an object representing an individual set of CODA instructions (one bank statement for one account) :

  $cf = new CRM_Becoda2_PluginImpl_File( $pathname );
  while ($cbatch = $cf->nextBatch()) {
    ...
    }

Inside, the File instance will create the appropriate reader to pull individual records from the CODA file:

  ...
  while ($record = $cf->nextRecord()) {
    ...
    $this->addBtx( $record, $batch );
    }
  ...
