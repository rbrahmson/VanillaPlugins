<?php if (!defined('APPLICATION')) exit(); ?>
<?php //if (!$this->Plugin->IsEnabled()) return;
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
		$Qmsg = $this->Data('Qmsg');
		$Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
		echo '<h1 title="'.$Sourcetitle.'"><div id=Popup><!�� '.$Sourcetitle.' L#'.__LINE__.' ��>';
		if ($Mode == 'Add') {
			$Modetitle = 'Add a new feed import definition';
		} elseif ($Mode == 'Model') {
			$Modetitle = 'Add a new feed import definition using the loaded prameters';
		} elseif ($Mode == 'Update') {
			$Modetitle = 'Update feed import definition';
		} elseif ($Restore) {
			$Modetitle = 'Restore deleted feed import definition';
		}
		echo $this->Data['Title']. '   <FFtitle>'.$Modetitle.'</FFtitle>';
		//if ($NumFeeds) {
		//	echo T($this->Data['Title']). '   <FFcenter>(Use the <a href=#displayonform>"<FFBOX><FFBLUE>📄⤴</FFBLUE> Display on Form</FFBOX>"</a> button below to load an existing feed definition)</FFcenter>';
		//} else {
		//	echo T($this->Data['Title']).' Add Feed';
		//}
		
		echo '</h1>';
		if ($Qmsg != '') {
			echo '<span><FFline><FFmsg><FFBLUE>'.t($Qmsg).'</FFBLUE></FFmsg></FFline></span>';
		}
		
	//
	echo '<span><a class="Button" href="' . Url('/plugin/feeddiscussionsplus/ListFeeds'). 
	   '"  beta</a>List Test</span> ';
	//
		//echo '<FFRED style="float:right;background-color:white">'. $Mode. ' feed mode</FFRED>  ';
?>
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
         'action'  => Url('plugin/feeddiscussionsplus/addfeed')
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
			 //if ($FeedURL == '') {
			if ($Mode == 'Add' | $Mode == 'Model') {
				if ($Mode == 'Model') {
					$FeedURL = '';
				}
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
            echo $this->Form->TextBox('Activehours', array('class' => 'InputBox')).'<FFtext> Feeds are checked whenever a user dispays a discussion. This option limits checking for new feed items between the specified server hours (format:hh-hh.  Examples:0-5 or 14-21). <br>Your current server clock hour (as detected by Vanilla):<b>'.date('H').'</B></FFtext>';
			echo '</FFline>';
			echo '<h4><FFBIG>☶</FFBIG> Feed item filters</h4>';
			echo '<FFline>';
			echo '<note>If specified, a feed items must match the following filters to be imported. ';
			echo 'When specifying multiple words they must be comma delimited.  The match is case-insensitive.</note>';
			echo '</FFline><FFline>';
            echo $this->Form->Label('<FFlabel>OR Filter:</FFlabel>', 'OrFilter');
            echo $this->Form->TextBox('OrFilter', array('class' => 'InputBox WideInput')).'<FFtext> Any matched word in the <b>title</b> will satisfy the filter</FFtext>';
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
	  <span style="margin: 0 0 0 20px;"><a class="Button DeleteFeed" href="<?php echo Url('/plugin/feeddiscussionsplus/').'" title="'.t('Clear Form'); ?>"><FFBLUE>♻</FFBLUE> Clear Form</a></span>
	  <?php
	  echo '</FFline>';
   ?>
</div>
<?php
	$Feedsarray = $this->Data('Feeds');
	$NumFeeds = count($Feedsarray);
	$NumFeedsActive = count(array_keys(array_column($Feedsarray, 'Active'), true));
	if (!function_exists('comp')) {
		function comp($a, $b) {
		if ($a['Active'] == $b['Active']) {
			return $a['Feedtitle'] > $b['Feedtitle'];
		}
		return ($a['Active'] < $b['Active']);
	}
}
	usort($Feedsarray, 'comp');
	$Feedsarray = array_combine(array_column($Feedsarray, 'URL'), $Feedsarray);
	//
	//
   echo '<h3>';
   if ($NumFeedsActive) {
	   echo '<FFcenter>';
	   echo "<span>".$NumFeeds." ".Plural($NumFeeds,"Defined Feed ","Defined Feeds ").',  '.$NumFeedsActive." ".Plural($NumFeedsActive,"Active Feed ","Active Feeds ")."</span>";
	   echo '<a class="Button UpdateFeed Popup" href="' . Url('/plugin/feeddiscussionsplus//CheckFeeds/backend'). 
	   '" target=_blank title="' . t('Initiate all feeds check to load new articles').'">Check Active Feeds Now <FFBLUE>➤↗</FFBLUE></a></FFcenter>';
   } elseif ($NumFeeds) {
	   echo "<FFcenter><span>".$NumFeeds." ".Plural($NumFeeds,"Inactive Feed ","Inactive Feeds ")."</span></FFcenter>";
   } else {
	   echo T("You have no defined feeds at this time.");
	   echo '<br>If you have used the other <b>FeedDiscussion</b> plugin in the past, you can try to '.
			'<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/GetOldFeeds').
			'" title="Try to import old feed import definitions">Import</a> the old import definitions.';
   }
   echo '</h3>';
?>
<div class="ActiveFeeds">
<?php
   if ($NumFeeds) {
      foreach ($Feedsarray as $FeedURL => $FeedItem) {
         $LastPublishDate = $FeedItem['LastPublishDate'];
         $LastUpdate = $FeedItem['LastImport'];
         $CategoryID = $FeedItem['Category'];
		 $Active = $FeedItem['Active'];
		 
		 $EncodingMsg = '<span class="Encodingbe">'.$FeedItem['Encoding'].' feed</span>';
		 $FeedItemStyle = '';
		 if ($Active) {
			 $Activemsg = '<span class="Activebe"><FFActive>Active</FFActive></span>';
			 $Togglebutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/togglefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t("Deactivate feed but keep it's definition").'"><FFRED>⛔</FFRED> Deactivate</a>';
		 } else {
			 $FeedItemStyle = ' Style="background: whitesmoke;"';
			 $Activemsg = '<span class="Inactivebe">Inactive</FFInactive></span>';
			 $Togglebutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/togglefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Activate feed').'"><FFGREEN>✔</FFGREEN>&nbsp&nbsp&nbspActivate&nbsp&nbsp&nbsp&nbsp</a>';
		 }
		 if ($LastUpdate == 'never') {
			 $LastUpdate = '<span><FFInactive>Ø never</FFInactive></span>';
			 $Resetbutton = '<FFDISABLE>↺ Reset</FFDISABLE>';
		 } else {
			 $Resetbutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/resetfeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Reset last update so feed would be loaded next time').'"><FFGREEN>↺</FFGREEN> Reset</a>';
		 }
		 
		 
		 $Editbutton = '<a class="Button UpdateFeed Popup" id=displayonform href="'.Url('/plugin/feeddiscussionsplus/editfeedform/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Edit feed definitions'). '"><FFBLUE>📄⤴</FFBLUE> Edit</a>';
		 
		 $Modelbutton = '<a class="Button UpdateFeed" id=displayonform href="'.Url('/plugin/feeddiscussionsplus/loadfeedform/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'/model" title="'.t('Load definition on the form above to allow additions').'"><FFBLUE>📄⤴</FFBLUE> Use as model</a>';
		 $Modelbutton = '';
		 $Displaybutton = '<a class="Button UpdateFeed" id=displayonform href="'.Url('/plugin/feeddiscussionsplus/loadfeedform/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Load definition on the form above to allow updates'). '"><FFBLUE>📄⤴</FFBLUE> Display</a>';
		 
		 $Deletebutton = '<a class="Button DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/deletefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Careful-delete cannot be undone'). '"><FFRED>✘</FFRED> Delete</a>';
		 
		 
		 if ($FeedItem['RSSimage']) {
			 $Logo = '<span class="RSSimageboxbe"> <img src="' . $FeedItem['RSSimage'] . '" id=RSSimage class=RSSimagebe title="' . $FeedItem['Feedtitle'] . '" ></span> ';
		 } else {
			 $Logo = '';
		 }
		 
		 
		 $OrFilter = $FeedItem['OrFilter'];
		 $AndFilter = $FeedItem['AndFilter'];
		 $Minwords = $FeedItem['Minwords'];
		 $Historical = $FeedItem['Historical'];
		 $Maxitems = $FeedItem['Maxitems'];
		 $Activehours = $FeedItem['Activehours'];
		 $Ftitle = (string)$FeedItem['Feedtitle'];
         $Frequency = GetValue($FeedItem['Refresh'], $Refreshments, T('Unknown'));
         $Category = $this->Data("Categories.{$CategoryID}.Name", 'Root');
		 $Buttons = '<span class="RSSbuttonboxbe" >'.
				$Resetbutton.
				$Togglebutton.
				$Editbutton.
				$Modelbutton.
				$Displaybutton.
				$Deletebutton.
				'</span>'; 
				
		 $Leftblock = '<span class="RSSleftblock">'.$Logo.$Activemsg.'</span> ';
		 $Rigtblock = '<span class="RSSrightblock">'.$Buttons. '</span> ';
		 //$Rigtblock = '<span class="RSSrightblock">'.$Displaybutton. '</span> ';
		 
         echo '<div class="FeedItem" '.$FeedItemStyle.'>';
		 //echo '<span class="RSSimageboxbe"> <img src="' . $FeedItem['RSSimage'] . '" id=RSSimage class=RSSimagebe title="' . $FeedItem['Feedtitle'] . '" ></span> ';
		 //  
		 echo $Leftblock;
		 echo $Rigtblock;
		 ?> 
		  <span class="RSSdetailbe">
			<div class="FeedItemTitle"><FFBLUE><?php echo $FeedItem["Feedtitle"].'</FFBLUE>   </div>';?>
            <div class="FeedContent">
               <div class="FeedItemURL"><?php echo Anchor($FeedURL,'http://'.$FeedURL,["target" => "_blank"]); ?></div>
               <div class="FeedItemInfo">
			      <?php  echo $EncodingMsg;
				  if ($LastPublishDate) echo '<span class="Attrbe">Last Published: <b>'.$LastPublishDate.'</b></span>';
				  ?>
                  <span class="Attrbe">Updated: <?php echo trim($LastUpdate);?></span>
                  <span class="Attrbe">Refresh: <?php echo trim($Frequency); ?></span>
                  <span class="Attrbe">Category: <?php echo $Category; ?></span>
				  <?php
				     if ($OrFilter) echo '<span class="Attrbe">OR Filter: '.$OrFilter.'</span>';
				     if ($AndFilter) echo '<span class="Attrbe">AND Filter: '.$AndFilter.'</span>';
				     if ($Minwords) echo '<span class="Attrbe" >Min. Words: '.$Minwords.'</span>';
					 if ($Maxitems) echo '<span class="Attrbe">Max Items: '.$Maxitems.'</span>';
					 if ($Activehours) echo '<span class="Attrbe">Active between: '.$Activehours.'</span>';
					 if ($Historical) echo '<span class="Attrbe">Note: Historical posts requested on next feed check.</span>';
				  ?>
               </div>
            </div>
		</span>
		<?php //echo $Rigtblock;?>
      </div>
<?php
      }
   }
?>
</div></div>
<?php /*
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
*/
?>