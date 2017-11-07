<?php if (!defined('APPLICATION')) exit();
		//if (!$this->Plugin->IsEnabled()) return;
		$this->Form->Open;	
        //
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
        $Title = $Plugininfo["Name"];
        $Version = $Plugininfo["Version"];
        $IconUrl = $Plugininfo["IconUrl"];
        //
		$Refreshments = array(
               "1m"  => T("Every&nbspMinute"),
               "5m"  => T("Every&nbsp5&nbspMinutes"),
               "30m" => T("Twice&nbspHourly"),
               "1h"  => T("Hourly"),
               "1d"  => T("Daily"),
               "3d"  => T("Every&nbsp3&nbspDays"),
               "1w"  => T("Weekly"),
               "2w"  => T("Every&nbsp2&nbspWeeks"),
               "3w"  => T("Every&nbsp3&nbspWeeks"),
               "4w"  => T("Every&nbsp4&nbspWeeks"),
               "Monday"  => T("Every&nbspMonday"),
               "Tuesday"  => T("Every&nbspTuesday"),
               "Wednesday"  => T("Every&nbspWednesday"),
               "Thursday"  => T("Every&nbspThursday"),
               "Friday"  => T("Every&nbspFriday"),
               "Saturday"  => T("Every&nbspSaturday"),
               "Sunday"  => T("Every&nbspSunday"),
               "Manually"  => 'Manually&nbspvia&nbspthe&nbspCheck&nbspActive&nbspFeed&nbspNow&nbspbutton',
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
		echo '<span class=selflogo> </span> '.$Title . ' (Version ' . $Version.')   '. '&nbsp&nbsp&nbsp&nbsp&nbsp   <FFtitle>'.t($Request).' '.$Headmsg.'</FFtitle>'.$Titlemsg.'</h1>';
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
		   '" title="' . t('You should read this before starting').'"><FFBLUE><b>❓</b></FFBLUE> Readme</a>';
			$Addbutton = '<a class="Button ffcolumn" href="' . Url('/plugin/feeddiscussionsplus/Addfeed'). 
		   '" title="' . t('Create import definition for a new feed').'"> ➕ '.t("Add Feed").'</a>';
			$Refreshbutton = '<a class="Button ffcolumn  " href="' . Url('/plugin/feeddiscussionsplus/ListFeeds'). 
		   '" title="' . t('Refresh this screen following changes in other browsers/tabs').'"> ♺ '.t("Refresh").'</a>';
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
    echo '<div class="ActiveFeeds">';
    //
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
      echo '<div class="FDPtable"><!--'.__LINE__.'  -->';
      foreach ($Feedsarray as $FeedURL => $FeedItem) {
         $LastPublishDate = $FeedItem['LastPublishDate'];
         $LastImport = $FeedItem['LastImport'];
         $NextImport = $FeedItem['NextImport'];
         $CategoryID = $FeedItem['Category'];
		 $Active = $FeedItem['Active'];
         $AnchorUrl = $FeedItem['FeedURL'];
		 
		 $EncodingMsg = '<span class="Encodingbe">'.$FeedItem['Compressed'].' '.$FeedItem['Encoding'].' feed</span>';
         if ($FeedItem['Encoding'] == 'Twitter') {
             $AnchorUrl = 'twitter.com/'.$FeedURL;
         } elseif ($FeedItem['Encoding'] == '#Twitter') {
             $AnchorUrl = 'twitter.com/hashtag/'.substr($FeedURL,1);
         }
		 $FeedItemStyle = '';
		 $FeedItemClass = 'FDPtable-row';
         $Resetbutton = '<FFDISABLE title="Disabled button">↺ Schedule</FFDISABLE>';
		 if ($Active) {
			 $Activemsg = '<span class="Activebe"><FFActive>Active</FFActive></span>';
			 $Togglebutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/togglefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t("Deactivate feed but keep it's definition").'"><FFRED>⛔</FFRED> Deactivate</a>';
             if ($NextImport != '' && $NextImport != 'never' && ($NextImport >  date('Y-m-d H:i:s', time()))) {
                 $Resetbutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/resetfeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Schedule feed import time ASAP').'"><FFGREEN>↺</FFGREEN> Schedule</a>';
             }
		 } else {
			 $FeedItemStyle = ' Style="background: whitesmoke;"';
             $FeedItemClass = 'FDPtable-row-inactive';
			 $Activemsg = '<span class="Inactivebe">Inactive</FFInactive></span>';
			 $Togglebutton = '<a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/togglefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Activate feed').'"><FFGREEN>✔</FFGREEN>&nbsp&nbsp&nbspActivate&nbsp&nbsp&nbsp&nbsp</a>';
		 }
		 $Editbutton = '<a class="Button UpdateFeed  " id=displayonform href="'.Url('/plugin/feeddiscussionsplus/updatefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Edit feed definitions'). '"><FFBLUE>📄</FFBLUE> Edit</a>';
		 
		 $Modelbutton = '<a class="Button UpdateFeed" id=displayonform href="'.Url('/plugin/feeddiscussionsplus/loadfeedform/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'/model" title="'.t('Load definition on the form above to allow additions').'"><FFBLUE>📄⤴</FFBLUE> Use as model</a>';
		 $Modelbutton = '';
		 
		 $Deletebutton = '<a class="Button DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/deletefeed/'.FeedDiscussionsPlusPlugin::EncodeFeedKey($FeedURL)).'" title="'.t('Careful...'). '"><FFRED>✘</FFRED> Delete</a>';
		 
		 
		 if ($FeedItem['RSSimage']) {
             if ($FeedItem['Encoding'] == "Twitter") {
                $Logo = '<span class="RSSimageboxtwitter"> <img src="' . $FeedItem['RSSimage'] . '" id=RSSimage class=RSSimagebe title="' . $FeedItem['Feedtitle'] . '" ></span> ';            
             } else {              
                $Logo = '<span class="RSSimageboxbe"> <img src="' . $FeedItem['RSSimage'] . '" id=RSSimage class=RSSimagebe title="' . $FeedItem['Feedtitle'] . '" ></span> ';
             }
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
         if (c('Plugins.FeedDiscussionsPlus.showurl',false)) {
             $Internalurl = ' Url:'.$FeedItem['InternalURL'];
         }
		 $Buttons = '<span class="RSSbuttonboxbe" >'.
				$Editbutton.
				$Resetbutton.
				$Togglebutton.
				$Modelbutton.
				$Deletebutton.
				'</span>'; 
				
		 $Leftblock = '<span class="RSSleftblock">'.$Logo.$Activemsg.'</span> ';
		 $Rigtblock = '<span class="RSSrightblock">'.$Buttons. '</span> ';
		 $Leftblock = '<span class="FDPtable-cell-left">'.$Logo.$Activemsg.$EncodingMsg.'</span> ';
		 $Rigtblock = '<span class="FDPtable-cell-right">'.$Buttons. '</span> ';
		 
         //echo '<div class="FeedItem" '.$FeedItemStyle.'>';
         //echo '<div class="FDPtable-row" '.$FeedItemStyle.'>';
         echo '<div class="'.$FeedItemClass.'" >';
		 //echo '<span class="RSSimageboxbe"> <img src="' . $FeedItem['RSSimage'] . '" id=RSSimage class=RSSimagebe title="' . $FeedItem['Feedtitle'] . '" ></span> ';
		 //  
		 echo $Leftblock;
        //--------- Middle Block----
		//echo '<span class="RSSmidblock"><!--'.__LINE__.'  -->';
		echo '<span class="FDPtable-cell"><!--'.__LINE__.'  -->';
            echo    '<span class="RSSdetailbe"><!--'.__LINE__.'  -->'.
                '<div class="FeedItemTitle"><FFBLUE>'.
                $FeedItem["Feedtitle"].'</FFBLUE>   </div>'.
             '<div class="FeedContent"><!--'.__LINE__.'  -->'.
                '<div class="FeedItemURL">';
            echo Anchor($FeedURL,'http://'.$AnchorUrl,["target" => "_blank"]).
                $Internalurl.'</div>';;
            echo '<div class="FeedItemInfo"><!--'.__LINE__.'  -->';
              if ($LastImport != 'never' & $LastImport != '') {
                  echo '<span class="Attrbe"><b>Last&nbspImport:</b>'.$LastImport.'</span>';
              } else {
                  echo '<span class="Attrbe" ><b>Last&nbspImport:</b><span><FFInactive>Ø&nbspnot&nbspyet</FFInactive></span></span>';
              }
              if ($Active) {
                  $Timedate = date('Y-m-d H:i:s', time());
                  if ($Refresh == "Manually") {
                  } elseif ($NextImport == '' | $NextImport == 'never') {
                    echo '<span class="Attrbe" ><b> ⚪&nbspImport&nbspdue&nbspnow</b></span>';
                  } elseif ($Timedate < $NextImport) {
                    echo '<span class="Attrbe" ><b> 🔴&nbspNext&nbspimport&nbspdue&nbspon:</b>'.$NextImport.'</span>';
                  } else {
                    echo '<span class="Attrbe" ><b> 🔵&nbspNext&nbspimport&nbspis&nbspdue:</b>'.$NextImport.'</span>';
                  }
              }
              if ($Refresh == "Manually") {
                  echo '<span class="Attrbe" title="Click the \'Check Active Feeds Now\' button initiate import"><b>Manual&nbspimport</b></span>';
              } else {
                  echo '<span class="Attrbe" ><b>Refresh:</b>&nbsp'.trim($Frequency).'</span>';
              }
              echo '<span class="Attrbe" ><b>Category:</b>&nbsp'.trim($Category).'</span>';
              
             if ($OrFilter) {
                 echo '<span class="Attrbe"><b>OR&nbspFilter:</b>&nbsp'.$OrFilter.'</span>';
             }
             if ($AndFilter) {
                 echo '<span class="Attrbe"><b>AND&nbspFilter:</b>&nbsp'.$AndFilter.'</span>';
             }
             if ($Minwords) {
                 echo '<span class="Attrbe" ><b>Min.&nbspWords:</b>&nbsp'.$Minwords.'</span>';
             }
             if ($Maxitems) {
                 echo '<span class="Attrbe"><b>Max&nbspItems:</b>&nbsp'.$Maxitems.'</span>';
             }
             if ($Activehours) {
                 echo '<span class="Attrbe"><b>Active&nbspbetween:</b>&nbsp'.$Activehours.'</span>';
             }
            if ($Historical) {
                echo '<span class="Attrbe"><b>Note:</b>&nbspHistorical&nbspposts&nbsprequested on&nbspnext&nbspfeed&nbspimport.</span>';
            }
            if ($Getlogo) {
                 echo '<span class="Attrbe">Showing&nbspthe&nbspfeed\'s&nbsplogo.</span>';
            }
            if ($Noimage) {
                 echo '<span class="Attrbe">Removing&nbspimages&nbspon&nbspimport.</span>';
            }
            echo '<div>';
            echo '</div><!--'.__LINE__.'  -->'.
                '</div><!--'.__LINE__.'  -->'.
            '</div><!--'.__LINE__.'  -->'.
		  '</span><!--'.__LINE__.'  -->'.
		'</span><!--'.__LINE__.'  -->'.
        $Rigtblock.
      '</div><!--'.__LINE__.'  -->';
      }
   }
    echo '</div> <!--'.__LINE__.'  -->';
 echo '</div> <!--'.__LINE__.'  -->';

 /*
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