<?php if (!defined('APPLICATION')) exit(); ?>
<?php if (!$this->Plugin->IsEnabled()) return;
		$NumFeeds = count($this->Data('Feeds'));
		$Feedtitle = (string)$this->Data('Feedtitle');
		$Mode = $this->Data('Mode');		
		$Restore = $this->Data('Restore');
	    $FeedURL = GetValue('FeedURL', $this->Data, NULL);
		//
		if ($Feedtitle != '') {
			$Ftitle = '<FFBLUE>'.$Feedtitle.'</FFBLUE>';
		} else {
			$Ftitle = '';
		}
		if ($Qmsg == '') {
			$Qmsg = $Ftitle = '';
		}
		$Qmsg = $this->Data('Qmsg');
		echo '<h1>';
		if ($NumFeeds) {
			echo T($this->Data['Title']). ' Add or Update a Feed  <FFcenter>(Use the <a href=#displayonform>"<FFBOX><FFBLUE>📄⤴</FFBLUE> Display on Form</FFBOX>"</a> button below to load an existing feed definition)</FFcenter>';
		} else {
			echo T($this->Data['Title']).' Add Feed';
		}
		echo '</h1>';
		if ($Qmsg != '') {
			echo '<span><FFline><FFmsg><FFBLUE>'.t($Qmsg).'</FFBLUE></FFmsg></FFline></span>';
		}
		echo '<FFRED style="float:right;background-color:white">'. $Mode. ' feed mode</FFRED>  ';
?>
<!  No need for this - the plugin can be deactivated through the admin plugins panel.>
<div style="display:none">
<div class="Info">
   <?php echo T($this->Data['Description']); ?>
</div>
<!  above is hidden legacy code >
<div class="FilterMenu">
      <?php
      echo Anchor(
         T($this->Plugin->IsEnabled() ? 'Disable' : 'Enable'),
         $this->Plugin->AutoTogglePath(),
         'SmallButton'
      );
   ?>
</div>
</div>
<div class="AddFeed">
   <?php 
      echo $this->Form->Open(array(
         'action'  => Url('plugin/feeddiscussions/addfeed')
      ));
      echo $this->Form->Errors();
      
      $Refreshments = array(
               "1m"  => T("Every Minute"),
               "5m"  => T("Every 5 Minutes"),
               "30m" => T("Twice Hourly"),
               "1h"  => T("Hourly"),
               "1d"  => T("Daily"),
               "3d"  => T("Every 3 Days"),
               "1w"  => T("Weekly"),
               "2w"  => T("Every 2 Weeks")
            );
      
   ?>
      <ul>
         <li>
         <?php
			echo '<FFline>';
			echo $this->Form->Label('<FFlabel>Feed URL</FFlabel>', 'FeedURL');
			 if ($FeedURL == '') {
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput', 'maxlength' => 200,));
				echo '<div><FFnote>'.t('Note: URL should be unique (regardless of any filters you may apply).').'</FFnote></div>';
			 } else {
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput NoInput ', 'maxlength' => 1,)).'<FFFIELD>'.$FeedURL.'</FFFIELD>&nbsp&nbsp&nbsp'.'<FFBLUE>'.$Feedtitle.'</FFBLUE>';
				//echo '<span>'.$FeedURL.'</span>';
			 }
			echo '</FFline><FFline>';
			echo $this->Form->CheckBox('Historical', 'Import Historical Posts', array('value' => '1', 'class' => 'FFCHECKBOX')).'<FFchecktext> Requests import of older feed posts.</FFchecktext>';
			echo '</FFline><FFline>';
            echo $this->Form->Label('<FFlabel>Target Category</FFlabel>', 'Category');
            echo $this->Form->CategoryDropDown('Category');
			echo '</FFline><FFline>';
			echo $this->Form->CheckBox('Active', 'Activate the feed', array('value' => '1', 'class' => 'FFCHECKBOX'))."<FFchecktext> (uncheck to deactivate while keeping the inactive definitions)</FFchecktext>";
			echo '</FFline>';
         ?></li>
               
         <li><?php
			echo '<h4><FFBIG>🏃</FFBIG>Performance controls</h4>';
			//echo '<div>';
			echo '<FFline>';
            echo $this->Form->Label('<FFlabellong>Maximum Polling Frequency</FFlabellong>', 'Refresh');
            echo $this->Form->DropDown('Refresh', $Refreshments, array(
               'value'  => "1d"
            ));
			//echo 'Frequent checks imports more current items but cost more.'; 
			echo '</FFline>';
			//echo '</div>';
			echo '<ffinputs>';
			echo '<note>The following options are optional. Leave blank to ignore:</note>';
			echo '<FFline>';
			echo $this->Form->Label('<FFlabel>Maximum items:</FFlabel>', 'Maxitems');
            echo $this->Form->TextBox('Maxitems', array('class' => 'InputBox')).'<FFtext> The maximum number of items to import from this feed each time it is checked (Unless historical items are requested)</FFtext>';
			echo '</FFline><FFline>';
			echo $this->Form->Label('<FFlabel>Active between:</FFlabel>', 'Activehours');
            echo $this->Form->TextBox('Activehours', array('class' => 'InputBox')).'<FFtext> Feeds are checked whenever a user dispays a discussion. This option limits checking for new feed items between the specified hours (format:hh-hh.  Examples:0-5 or 14-21). Your current server time:<b>'.date('H').'</B></FFtext>';
			echo '</FFline>';
			echo '<h4><FFBIG>☶</FFBIG> Feed item filters</h4>';
			echo '<FFline>';
			echo '<note>If specified, a feed items must match the following filters to be imported. ';
			echo 'When specifying multiple words they must be comma delimited.  The match is case-insensitive.</note>';
			echo '</FFline><FFline>';
            echo $this->Form->Label('<FFlabel>OR Filter:</FFlabel>', 'Filter');
            echo $this->Form->TextBox('Filter', array('class' => 'InputBox WideInput')).'<FFtext> Any matched word in the <b>title</b> will satisfy the filter</FFtext>';
			echo '</FFline><FFline>';
            echo $this->Form->Label('<FFlabel>AND Filter:</FFlabel>', 'AndFilter');
            echo $this->Form->TextBox('AndFilter', array('class' => 'InputBox WideInput')).'<FFtext> All words must match  the <b>title</b> to satisfy the filter</FFtext>';
			echo '</FFline><FFline>';
            echo $this->Form->Label('<FFlabel>Minumum words:</FFlabel>', 'Minwords');
            echo $this->Form->TextBox('Minwords', array('class' => 'InputBox')).'<FFtext> Minimum number of words a feed item <b>body</b> must contain a to be imported. Use this to ignore mostly empty items</FFtext>';
			echo '</FFline>';
			echo '</ffinputs>';
         ?></li>
      </ul>
   <?php
	  if ($Restore == True) { 
		$Updatebutton = '<span style="margin: 0 0 0 20px;"><FFDISABLE>⇲ Update Feed</FFDISABLE>';
		$Addbutton = $this->Form->button(" ↩ Restore Feed", ['Name' => 'Add','Title' => t('Restore or add a different Feed definition')]);
	  } else if ($FeedURL == '') { 
		$Updatebutton = '<span style="margin: 0 0 0 20px;"><FFDISABLE>⇲ Update Feed</FFDISABLE>';
		$Addbutton = $this->Form->button(" ➕ Add Feed", ['Name' => 'Add','Title' => t('Add Feed definition')]);
	  } else { 
		//echo '<span style="float:right">'.$this->Form->button('Update Feed', ['Title' => t('Update Feed definition')]).'</span>';
		$Addbutton = '<span style="margin: 0 0 0 20px;"><FFDISABLE> ➕ Add Feed</FFDISABLE>';
		$Updatebutton = $this->Form->button('⇲ Update Feed', ['Name' => 'Update','Title' => t('Update Feed definition')]);
	  }
	  echo '<FFline>';
	  //echo $this->Form->Close(" ➕ Add Feed");
	  echo $Addbutton;
	  echo $Updatebutton;
	  ?>
	  <span style="margin: 0 0 0 20px;"><a class="Button DeleteFeed" href="<?php echo Url('/plugin/feeddiscussions/').'" title="'.t('Clear Form'); ?>"><FFBLUE>♻</FFBLUE> Clear Form</a></span>
	  <?php
	  echo '</FFline>';
   ?>
</div>
<?php
	$Feedsarray = $this->Data('Feeds');
	$NumFeeds = count($Feedsarray);
	$NumFeedsActive = count(array_keys(array_column($Feedsarray, 'Active'), true));
	function comp($a, $b) {
    if ($a['Active'] == $b['Active']) {
        return $a['Feedtitle'] > $b['Feedtitle'];
    }
    return ($a['Active'] < $b['Active']);
}
	usort($Feedsarray, 'comp');
	$Feedsarray = array_combine(array_column($Feedsarray, 'URL'), $Feedsarray);
	//
   echo '<h3>';
   if ($NumFeedsActive) {
	   echo '<FFcenter>';
	   echo "<span>".$NumFeeds." ".Plural($NumFeeds,"Defined Feed","Defined Feeds").',  '.$NumFeedsActive." ".Plural($NumFeedsActive,"Active Feed","Active Feeds")."</span>";
	   ?>  <a class="Button UpdateFeed" href="<?php echo Url('/plugin/feeddiscussions//CheckFeeds').'" target=_blank title="'.t('Initiate all feeds check to load new articles'); ?>">Check Active Feeds Now <FFBLUE>➤↗</FFBLUE></a><?php	   
	   echo '</FFcenter>';
   } elseif (NumFeeds) {
	   echo "<FFcenter><span>".$NumFeeds." ".Plural($NumFeeds,"Inactive Feed","Inactive Feeds")."</span></FFcenter>";
   } else {
	   echo T("You have no active feeds at this time.");
   }
   echo '</h3>';
?>
<div class="ActiveFeeds">
<?php
   if ($NumFeeds) {
      foreach ($Feedsarray as $FeedURL => $FeedItem) {
         $LastUpdate = $FeedItem['LastImport'];
         $CategoryID = $FeedItem['Category'];
		 $Active = $FeedItem['Active'];
		 $FeedItemStyle = '';
		 if ($Active) {
			 $Activemsg = '<span><FFActive>✔ Active</FFActive></span>';
			 $Togglebutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussions/togglefeed/'.FeedDiscussionsPlugin::EncodeFeedKey($FeedURL)).'" title="'.t("Deactivate feed but keep it's definition").'"><FFRED>⛔</FFRED> Deactivate feed</a>';
		 } else {
			 $FeedItemStyle = ' Style="background: whitesmoke;"';
			 $Activemsg = '<span><FFInactive>⛔ Inactive</FFInactive></span> ';
			 $Togglebutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussions/togglefeed/'.FeedDiscussionsPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Activate feed').'"><FFGREEN>✔</FFGREEN> Activate feed</a>';
		 }
		 if ($LastUpdate == 'never') {
			 $LastUpdate = '<span><FFInactive>Ø never</FFInactive></span>';
			 $Resetbutton = '<FFDISABLE>↺ Reset Last Update</FFDISABLE>';
		 } else {
			 $Resetbutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussions/resetfeed/'.FeedDiscussionsPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Reset last update so feed would be loaded next time').'"><FFGREEN>↺</FFGREEN> Reset Last Update</a>';
		 }
		 $Filter = $FeedItem['Filter'];
		 $AndFilter = $FeedItem['AndFilter'];
		 $Minwords = $FeedItem['Minwords'];
		 $Historical = $FeedItem['Historical'];
		 $Maxitems = $FeedItem['Maxitems'];
		 $Activehours = $FeedItem['Activehours'];
		 $Ftitle = (string)$FeedItem['Feedtitle'];
         $Frequency = GetValue($FeedItem['Refresh'], $Refreshments, T('Unknown'));
         $Category = $this->Data("Categories.{$CategoryID}.Name", 'Root');

         echo '<div class="FeedItem" '.$FeedItemStyle.'>';
?>		 
			   <span class="FeedItemTitle"><?php echo $FeedItem['Feedtitle'].'   '.$Activemsg; ?></span>
			<div class="DeleteFeed">
				<?php echo $Resetbutton; ?>
				<?php echo $Togglebutton; ?>
               <a class="Button UpdateFeed" id=displayonform href="<?php echo Url('/plugin/feeddiscussions/loadfeedform/'.FeedDiscussionsPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Load definition on the form above to allow updates'); ?>"><FFBLUE>📄⤴</FFBLUE> Display on form</a>
               <a class="Button DeleteFeed" href="<?php echo Url('/plugin/feeddiscussions/deletefeed/'.FeedDiscussionsPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Careful-delete cannot be undone'); ?>"><FFRED>✘</FFRED> Delete Feed</a>
            </div><br>
            <div class="FeedContent">
               <div class="FeedItemURL"><?php echo Anchor($FeedURL,'http://'.$FeedURL,["target" => "_blank"]); ?></div>
               <div class="FeedItemInfo">
                  <span>Updated: <b><?php echo trim($LastUpdate);?></b></span>
                  <span>Refresh: <b><?php echo trim($Frequency); ?></b></span>
                  <span>Category: <b><?php echo $Category; ?></b></span>
				  <?php
				     if ($Filter) echo "<span>OR Filter: <b>".$Filter.'</b></span>';
				     if ($AndFilter) echo "<span>AND Filter: <b>".$AndFilter.'</b></span>';
				     if ($Minwords) echo '<span>Min. Words: <b>'.$Minwords.'</b></span>';
					 if ($Maxitems) echo '<span>Max Items: <b>'.$Maxitems.'</b></span>';
					 if ($Activehours) echo '<span>Active between: <b>'.$Activehours.'</b></span>';
					 if ($Historical) echo '<span><b>Note:</b>Historical posts requested on next feed check.</span>';
				  ?>
               </div>
            </div>
         </div>
<?php
      }
   }
?>
</div>
<script type="text/javascript"> 
   jQuery(document).ready(function($) {
      
      // Show drafts delete button on hover
      // Show options on each row (if present)
      $('div.ActiveFeeds div.FeedItem').livequery(function() {
         var row = this;
         var del = $(row).find('div.DeleteFeed');
         $(del).hide();
         $(row).hover(function() {
            $(del).show();
            $(row).addClass('Active');
         }, function() {
            if (!$(del).find('div.FeedItem').hasClass('ActiveFeed'))
               $(del).hide();
               
            $(row).removeClass('ActiveFeed');
         });
      });
   
   });
</script>