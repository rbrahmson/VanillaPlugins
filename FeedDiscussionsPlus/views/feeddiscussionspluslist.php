<?php if (!defined('APPLICATION')) exit();
		//if (!$this->Plugin->IsEnabled()) return;
		$this->Form->Open;	
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
		$Feedsarray = $this->Data('Feeds');
		$NumFeeds = count($Feedsarray);
		$NumFeedsActive = count(array_keys(array_column($Feedsarray, 'Active'), true));
        if (!c('Plugins.FeedDiscussionsPlus.Nosort')) {
            if (!function_exists('comp')) {
                function comp($a, $b) {
                if ($a['Active'] == $b['Active']) {
                    return $a['Feedtitle'] > $b['Feedtitle'];
                }
                return ($a['Active'] < $b['Active']);
                }
            }
            usort($Feedsarray, 'comp');
        }
        $Feedsarray = array_combine(array_column($Feedsarray, 'URL'), $Feedsarray);
		//	
		if ($NumFeedsActive) {
		   $Headmsg = "<span>".$NumFeeds." ".Plural($NumFeeds,"Defined Feed ","Defined Feeds ").',  '.$NumFeedsActive." ".Plural($NumFeedsActive,"Active Feed ","Active Feeds ")."</span>";
		} elseif ($NumFeeds) {
		   $Headmsg  = "<span>".$NumFeeds." ".Plural($NumFeeds,"Inactive Feed ","Inactive Feeds ")."</span>";
		} else {
		   $Headmsg =  T("Add your first feed import definition:");
		   //echo $Importbutton;  Import is not fully tested yet so this is disabled
		}
		//
		$Qmsg = $this->Data('Qmsg');
		if ($Qmsg != '') {
			$Titlemsg = '<br><div class=ffqmsg>'.strip_tags($Qmsg).'</div>';
			$Titlemsg = '<br><div class=ffqmsg>'.$Qmsg.'</div>';
		} else {
			$Titlemsg = '';
		}
		$Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
		echo '<div id=FDP><div Class=xUnPopup>';
		echo '<h1 title="'.$Sourcetitle.'"><!–– '.$Sourcetitle.' L#'.__LINE__.' ––>';
		echo '<span class=selflogo> </span> '.$this->Data['Title']. '&nbsp&nbsp&nbsp&nbsp&nbsp   <FFtitle>'.t($Request).' '.$Headmsg.'</FFtitle>'.$Titlemsg.'</h1>';
		echo $this->Form->Errors();
		//
	   $RestoreFeedURL = trim($this->Data('RestoreFeedURL'));
	   if ($RestoreFeedURL) {
		   $Restorebutton = '<a class="Button ffcolumn" href="' . Url('/plugin/feeddiscussionsplus/restorefeed/'.$RestoreFeedURL). 
		   '" title="' . t('Restore recently deleted feed').' '.$RestoreFeedURL.'"><ffred> ↻ </ffred>'.t("Undo Delete").'</a>';
	   } else {
		   $Restorebutton = '<a class="Button ffcolumn ffhiddenlb" title="&nbsp">  &nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp</a>';
	   }
	   if (!$NumFeeds) {
		    $Readmebutton = '';
			$Addbutton = '&nbsp';
	   } else {
			$Readmebutton =   '<a class="Button ffcolumn  " href="' . Url('/plugin/feeddiscussionsplus/Readme'). 
		   '" title="' . t('You should read this before starting').'"><FFBLUE><b>❓</b></FFBLUE> Help</a>';
			$Addbutton = '<a class="Button ffcolumn" href="' . Url('/plugin/feeddiscussionsplus/Addfeed'). 
		   '" title="' . t('Create import definition for a new feed').'"> ➕ '.t("Add Feed").'</a>';
			$Refreshbutton = '<a class="Button ffcolumn  " href="' . Url('/plugin/feeddiscussionsplus/ListFeeds'). 
		   '" title="' . t('Refresh this screen').'"> ♺ '.t("Refresh").'</a>';
	   }
	   if ($NumFeedsActive) {
		  $Checkfeedsbutton = '<a class="Button ffcolumn Popup" href="' . Url('/plugin/feeddiscussionsplus/CheckFeeds/backend'). 
	   '" target=_blank title="' . t('Initiate all feeds check to load new articles').'">Check Active Feeds Now <FFBLUE>➤↗</FFBLUE></a>';
			 Gdn::session()->stash("FDPbackend", 'Backend');
	   } else {
		   Gdn::session()->stash("FDPbackend", 'Inactive');
		   $Checkfeedsbutton = '<a class="Button ffcolumn ffdisablelb" href="' . Url('/plugin/feeddiscussionsplus/ListFeeds'). 
	   '"  title="' . t('Disabled until you have active feeds').'">Check Active Feeds Now <FFBLUE>➤↗</FFBLUE></a>';
			 Gdn::session()->stash("FDPbackend", 'Backend');
	   }
	   
	   $Importbutton =  '<br>If you have used the other <b>'.'<a target=_BLANK href="https://open.vanillaforums.com/addon/feeddiscussions-plugin" >FeedDiscussion</a></b> plugin in the past, you can '.
			'<a class="Button ffcolumn" href="'.Url('/plugin/feeddiscussionsplus/GetOldFeeds').
			'" title="Note: Import attempt is not guaranteed to succeed."><i>try to Import</i></a> the old import definitions.';
	  //
	//
   //
   echo '<FFHEAD>';
   echo '<div Class="ffspread">'.$Addbutton.$Restorebutton.$Refreshbutton.$Checkfeedsbutton.$Readmebutton;
   //echo $Importbutton;  Import is not fully tested yet so this is disabled
   echo '</div>';
   $pluginlist = explode(',','FeedDiscussions,MagpieRss');	//Add more if new ones are added
	foreach ($pluginlist as $pluginname) {
		if (c('EnabledPlugins.'.$pluginname)) {
			echo '<br><FFRED>Note:</FFRED> The <a target=_BLANK href="'.url('/plugin/'.$pluginname).'" >'.$pluginname.'</a> plugin is enabled.  It is a <i>different</i> plugin and it operates independently of this plugin.';
		}
	}
   echo ' </FFHEAD>';
   //
?>
<div class="ActiveFeeds">
<?php
	if (!$NumFeeds) {
	 echo '<ffcenter>'.'Read the customization guide and then add your first feed'.'</ffcenter>';
	 $Addbutton = '<a class="Button ffhighlightbutton" href="' . Url('/plugin/feeddiscussionsplus/Addfeed/Add'). 
		   '" title="' . t('Create import definition for a new feed').'"> ➕ '.t("Add Feed").'</a>';
     echo $Addbutton;
	 echo '<div id=HELP><div class=ffembedhelp>';
	 include_once "AddfirstGuide.htm";
     echo 'For detailed information see the full' .
            '<a href="'.Url('/plugin/feeddiscussionsplus/Readme').
            '" > Customization Guide.';
	 echo '</div></div>';
   } else {
      foreach ($Feedsarray as $FeedURL => $FeedItem) {
         $LastPublishDate = $FeedItem['LastPublishDate'];
         $LastUpdate = $FeedItem['LastImport'];
         $CategoryID = $FeedItem['Category'];
		 $Active = $FeedItem['Active'];
		 
		 $EncodingMsg = '<span class="Encodingbe">'.$FeedItem['Compressed'].' '.$FeedItem['Encoding'].' feed</span>';
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
			 $Resetbutton = '<FFDISABLE title="Disabled button">↺ Reset</FFDISABLE>';
		 } else {
			 $Resetbutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/resetfeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Reset last update so feed would be loaded next time').'"><FFGREEN>↺</FFGREEN> Reset</a>';
		 }
		 
		 
		 $Editbutton = '<a class="Button UpdateFeed  " id=displayonform href="'.Url('/plugin/feeddiscussionsplus/updatefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Edit feed definitions'). '"><FFBLUE>📄</FFBLUE> Edit</a>';
		 
		 $Modelbutton = '<a class="Button UpdateFeed" id=displayonform href="'.Url('/plugin/feeddiscussionsplus/loadfeedform/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'/model" title="'.t('Load definition on the form above to allow additions').'"><FFBLUE>📄⤴</FFBLUE> Use as model</a>';
		 $Modelbutton = '';
		 $Displaybutton = '<a class="Button UpdateFeed" id=displayonform href="'.Url('/plugin/feeddiscussionsplus/loadfeedform/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Load definition on the form above to allow updates'). '"><FFBLUE>📄⤴</FFBLUE> Display</a>';
		 
		 $Deletebutton = '<a class="Button DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/deletefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Careful...'). '"><FFRED>✘</FFRED> Delete</a>';
		 
		 
		 if ($FeedItem['RSSimage']) {
			 $Logo = '<span class="RSSimageboxbe"> <img src="' . $FeedItem['RSSimage'] . '" id=RSSimage class=RSSimagebe title="' . $FeedItem['Feedtitle'] . '" ></span> ';
		 } else {
			 $Logo = '';
		 }
		 
		 $FeedKey = $FeedItem['FeedKey'];
         //echo "Internal Key:<FFRED>".$FeedKey.'</FFRED>';
		 $OrFilter = $FeedItem['OrFilter'];
		 $AndFilter = $FeedItem['AndFilter'];
		 $Minwords = $FeedItem['Minwords'];
		 $Historical = $FeedItem['Historical'];
		 $Refresh = $FeedItem['Refresh'];
		 $Getlogo = $FeedItem['Getlogo'];
		 $Noimage = $FeedItem['Noimage'];
		 $Maxitems = $FeedItem['Maxitems'];
		 $Activehours = $FeedItem['Activehours'];
		 $Ftitle = (string)$FeedItem['Feedtitle'];
         $Frequency = GetValue($Refresh, $Refreshments, T('Unknown'));
         $Category = $this->Data("Categories.{$CategoryID}.Name", 'Root');
		 $Buttons = '<span class="RSSbuttonboxbe" >'.
				$Resetbutton.
				$Togglebutton.
				$Editbutton.
				$Modelbutton.
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
					 echo '<div>';
					 if ($Historical) echo '<span class="Attrbe">Note: Historical posts requested on next feed check.</span>';
					 if ($Getlogo)
						 echo '<span class="Attrbe">Showing the feed\'s logo.</span>';
					 if ($Noimage)
						 echo '<span class="Attrbe">Removing images on import.</span>';
					 echo '</div>';
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
 </div> <!--  -->
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