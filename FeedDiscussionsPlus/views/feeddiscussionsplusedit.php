<?php if (!defined('APPLICATION')) exit();
		$Mode = $this->Data('Mode');
		$Feed = $this->Data('Feed');
		$FeedKey = $this->Data('FeedKey');
        $FeedURL = val('FeedURL', $this->Form->FormValues(), $this->Data('FeedURL'));
        //echo __LINE__."FeedURL = ".$FeedURL.' <br>';
        $Refresh = $Feed["Refresh"];       
        $LastImport = $Feed["LastImport"];
        if ($LastImport == '' | $LastImport == 'never') {
            $LastImportmsg = '<FFtext>  Feed has not yet been imported</FFtext>';
        } else {
            $LastImportmsg = '<FFtext>  last import:'.$LastImport.'</FFtext>';
        }
        $SuggestedURL = trim($Feed["SuggestedURL"]);
        if (c('Plugins.FeedDiscussionsPlus.showurl',false)) {
            $Internalurlmsg = 'Url:&nbsp<b>'.$Feed['InternalURL'].'</b>';
        }
		if ($Mode == 'Add') {
			$Process = Url('plugin/feeddiscussionsplus/addfeed///Add');
			$Defaultrefresh = '1w';
            $LastImportmsg = '';
		} elseif ($Mode == 'Update') {
			$Process = Url('plugin/feeddiscussionsplus/updatefeed');
			$Defaultrefresh = val('Refresh', $this->Form->FormValues(), $Feed["Refresh"]);
            $Copybutton = '<span title="Copy settings">'.$this->Form->button(' 📄📄 Copy', array('type' => 'submit', 'name' => 'Copy')).'</span>';
			if (!$FeedURL) {
				$Msg = '<h1><FFRED>'.__FILE__.' Line '.__LINE__.' error - missing feed url</FFRED>';
                echo $Msg;
				//throw new Gdn_UserException($Msg);
			}
		} else {
			$Process = Url('plugin/feeddiscussionsplus/updatefeed');
			if (!$FeedURL) {
				$Msg = '<h1>'.__FILE__.' Line '.__LINE__.' error - missing feed url';
                echo $Msg;
				//throw new Gdn_UserException($Msg);
			}
		}
		//
		$Processbutton = '<span title="Save">'.$this->Form->button(" 🔽 ".t($Mode), array('type' => 'submit', 'name' => $FeedKey)).'</span>';
		$Processtitle = $Mode.' Settings for Feed Import';
		echo $this->Form->Open(array(
         'action'  => $Process
		));
		//echo "<br>".__LINE__." Form Action Process:".$Process.'<br>';
        //
        $Copied = $this->Data('Copied');
        //echo "<br>".__LINE__." Copied:".$Copied.'<br>';
        //
	    if ($Copied) {
           $Pastebutton = '<span title="Paste settings">'.$this->Form->button(' 📑 Paste', array('type' => 'submit', 'name' => 'Paste')).'</span>';
	    } else {
           $Pastebutton = '';
	    }
        //
		echo '<div id=xPopup><div id=FDP>';
		$this->AddCssFile('feeddiscussionsplus.css', 'plugins/FeedDiscussionsPlus');
		$LastImport = $Feed['LastImport'];
		$Feedtitle = (string)$Feed['Feedtitle'];
		$Encoding = $Feed['Encoding'];
		$RSSimage = $Feed['RSSimage'];
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
        $Title = $Plugininfo["Name"];
        $Version = $Plugininfo["Version"];
        $IconUrl = $Plugininfo["IconUrl"];
		$Canelbutton = '<span style="margin: 0 0 0 20px;"><a class="Button DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/ListFeeds').
			'" title="'.t('Return to the definitions list').'">☰ Return to list</a></span>';
		//
		$Qmsg = $this->Data('Qmsg');
        $this->SetData('Qmsg', __FUNCTION__.__LINE__);
		if ($Qmsg) {
			$Titlemsg = '<br><div class=ffqmsg>'.$Qmsg.'</div>';
		} else {
			$Titlemsg = '';
		}
		$Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
		echo '<h1 title="'.$Sourcetitle.'"> <span class=selflogo> </span> '. $Title . ' (Version ' . $Version.')  -  ' . $Processtitle.'   '.$Titlemsg.'</h1>';
		//
        //
		echo $this->Form->Errors();
		echo '<div class="FeedContent" style="line-height: 25px;font-size: 13px;margin: 0px 4px 0px 6px;">';
		$Refreshments = array(
               "1m"  => T("Every Minute"),
               "5m"  => T("Every 5 Minutes"),
               "30m" => T("Twice Hourly"),
               "1h"  => T("Hourly"),
               "1d"  => T("Daily"),
               "3d"  => T("Every 3 Days"),
               "1w"  => T("Weekly"),
               "2w"  => T("Every 2 Weeks"),
               "3w"  => T("Every 3 Weeks"),
               "4w"  => T("Every 4 Weeks"),
               "Monday"  => T("Every Monday"),
               "Tuesday"  => T("Every Tuesday"),
               "Wednesday"  => T("Every Wednesday"),
               "Thursday"  => T("Every Thursday"),
               "Friday"  => T("Every Friday"),
               "Saturday"  => T("Every Saturday"),
               "Sunday"  => T("Every Sunday"),
               "Manually"  => 'Manual (Button on settings screen)',
            );
        //
		echo '<ul><li>';
        //
        if (empty($FeedURL)) {
            $FeedURL = val('FeedURL', $this->Form->FormValues(), null);
        }
        //decho ($this->Form->FormValues());
        if (c('Plugins.FeedDiscussionsPlus.allowupdate', false)) {
            $Allowupdate = true;
        }
        //
        // If the following validator stops working you can use the next one.
        $Validatorurl='https://validator.w3.org/feed/check.cgi?url='. $FeedURL;
        //$Validatorurl='http://www.feedvalidator.org/check?url='. $FeedURL;
        //
        $Feedvalidator =   '<span >&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp<a class="Button DeleteFeed  " target=_BLANK href="' . $Validatorurl . 
		   '" title="' . t('Run external feed validator if you have problems with this feed.').'"><b>Issues ?</b> Run Feed Validator</a><span>';
        $Twitterid = false;
        $Urltitle= '';
        $Urllabel= '<FFlabel>Feed URL:</FFlabel>';
        if (empty($FeedURL) or trim($FeedURL) == '?') {
            $Feedvalidator = '';
            $Urltitle= 'title ="Enter feed URL"';
            $Mode = 'Add';    
            $Internalurlmsg = '????????????????????????????????????/';
        } elseif ($Allowupdate) {
            $Urllabel= '<FFlabel>Feed source:</FFlabel>';
        } elseif ($Encoding == 'Twitter' | substr($FeedURL,0,1) == '@') {
            $Twitterid = true;
            $Feedvalidator = '';
            $Urllabel= '<FFlabel>Twitter ID</FFlabel>';
        } elseif ($Encoding == '#Twitter' | substr($FeedURL,0,1) == '#') {
            $Twitterid = false;
            $Feedvalidator = '';
            $Urllabel= '<FFlabel>Twitter hashtag</FFlabel>';
        } elseif ($Mode == 'Add') {
            $Urllabel= '<FFlabel>Feed URL or <b>@</b>twitterid</FFlabel>';
        }
        //
		if ($RSSimage) {
             if ($Twitterid) {    
                 $Logo = '<span class="RSSimageboxtwitter"> <img src="' . $RSSimage . '" id=RSSimage class=RSSimagebe title=" " ></span> ';
             } else {
                 $Logo = '<span class="RSSimageboxtitle"> <img src="' . $RSSimage . '" id=RSSimage class=RSSimagebe title=" " ></span> ';
             }
             $Logooption = '<span> <img src="' . $RSSimage . '" id=RSSimage class=RSSimageoption title=" " ></span> ';
		 } else {
			 $Logo = '';
			 $Logooption = '';
		 }
        //
        $Buttonbar = '<div style="display:inline-flex;float:right;"> '.$Processbutton.$Canelbutton.$Copybutton.$Pastebutton.$Feedvalidator.'</div>';
        //
		echo $Buttonbar;
        echo '<h4><FFBIG>❂</FFBIG>General Options</h4>';
		//
		echo '<FFUfeedhead>';
            $Highlightclass = '';
            $Helpmsg = '<FFtext> Enter ? for help on valid inputs</FFtext>';
            if ($SuggestedURL) {
                $SuggestedURL = '';
                $Highlightclass = 'FDPredinput';
                $Urllabel = '<FFRED>Suggested&nbspURL:</FFRED>&nbsp&nbsp&nbsp';
                $Logo = '';
                $Encoding = '';
                $Internalurlmsg = '';
                $Helpmsg = '<FFtext><FFBLUE>&nbspThe url you entered pointed to the suggested feed url</FFBLUE></FFtext>';
            }
            if (($Encoding != '') & ($Encoding != 'N/A')) {
                $Encodingmsg = '(Feed type:&nbsp'.$Encoding.')';
            }
			echo '<span '.$Urltitle.' >'.$this->Form->Label($Urllabel, 'FeedURL').'</span>';
            if ($Allowupdate) {
                echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput '.$Highlightclass, 'maxlength' => 200,)).
                    $Helpmsg . '</FFline>'.$Logo.' &nbsp&nbsp&nbsp '.$Encodingmsg.'&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp'.$Internalurlmsg;
			} elseif (($Mode == 'Update')) { 
                $Helpmsg = '';
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput NoInput ', 'maxlength' => 1,)).'<FFFIELD>'.$FeedURL.'<br><FFBLUE>'.$Feedtitle.'</FFBLUE></FFFIELD>&nbsp&nbsp&nbsp'.$Logo.'&nbsp&nbsp&nbsp'.$Encodingmsg.'&nbsp&nbsp&nbsp'.$Internalurlmsg;		
			} else { //no url - assume Add request 
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput', 'maxlength' => 200,)).       $Helpmsg . '</FFline>';
			}
		echo '</FFUfeedhead>';
		//
		echo '<FFline>'.$this->Form->CheckBox('Historical', '<b>Import Historical Posts</b>', array('value' => '1', 'class' => 'FFCHECKBOX')).'<FFchecktext> Requests import of older feed posts.  This is automatically unchecked after the first import.</FFchecktext></FFline>';
		//
        echo '<FFline>'.$this->Form->Label('<FFlabel>Target Category</FFlabel>', 'Category');
        echo $this->Form->CategoryDropDown('Category').'<FFdroptext> (Select the category where imported posts are saved)</FFdroptext></FFline>';
		//
		echo '<FFline>'.$this->Form->CheckBox('Active', 'Activate the feed', array('value' => '1', 'class' => 'FFCHECKBOX'))."<FFchecktext> (uncheck to deactivate while keeping the inactive definition)</FFchecktext></FFline>";
		//
		echo '<FFline>'.$this->Form->CheckBox('Getlogo', "Show the feed's logo", array('value' => '1', 'class' => 'FFCHECKBOX')).$Logooption."<FFchecktext> (show feed's logo in the discussion and the discussion list)</FFchecktext></FFline>";
		//
		echo '<FFline>'.$this->Form->CheckBox('Noimage', "Don't include images", array('value' => '1', 'class' => 'FFCHECKBOX'))."<FFchecktext> (Don't include <i>embedded</i> images when saving feeds as discussions)</FFchecktext></FFline>";
		//
        echo '<h4><FFBIG>🏃</FFBIG>Performance controls</h4>';
		//
        echo '<FFline>'.$this->Form->Label('<FFlabellong>Feed Check Frequency</FFlabellong>', 'Refresh');
        echo $this->Form->DropDown('Refresh', $Refreshments, array(
               'value'  => $Defaultrefresh,
            ))."<FFdroptext> (How often to check the feed for new items to import)</FFdroptext>".$LastImportmsg;
		//
		echo '</FFline>';
		echo '<ffinputs>';
		//
		echo '<FFline>';
		echo $this->Form->Label('<FFlabel>Maximum items:</FFlabel>', 'Maxitems');
        echo $this->Form->TextBox('Maxitems', array('class' => 'InputBox')).'<FFtext> The maximum number of items to import from this feed each time it is checked (leave blank or zero for no limit)</FFtext>';
		echo '</FFline>';
		//
		echo '<FFline>';
		$Serverthour = (integer)date('H');
		$Sessionhour = ((integer)(Gdn::session()->User->HourOffset)+(integer)date('H'));
		if ($Serverthour != $Sessionhour) {
			$Hournote = 'Note: Your server hour is <b>'.$Serverthour.
						'</b> while your session hour is <b>'.$Sessionhour.'</b>';
		} else {
			$Hournote = 'The current server hour is '.$Serverthour;
		}
			
		echo $this->Form->Label('<FFlabel>Active between:</FFlabel>', 'Activehours');
            echo $this->Form->TextBox('Activehours', array('class' => 'InputBox')).'<FFtext> Feeds are checked whenever a user dispays a discussion. This option limits checking for new feed items between the specified <b>server</b> hours (format:hh-hh.  Examples:0-5, 21-05, or 14-21). <br>'.$Hournote . '</FFtext>';
		echo '</FFline>';
		//
		echo '<h4><FFBIG>☶</FFBIG> Feed item filters</h4>';
		//
		echo '<FFline>';
		echo '<note>If specified, a feed items must match the following filters to be imported. ';
		echo '</FFline>';
		//
		echo '<FFline>';
		echo 'When specifying multiple words they must be comma delimited.  The match is case-insensitive.</note>';
		
		echo '</FFline>';
		//
		echo '<FFline>';
        echo $this->Form->Label('<FFlabel>OR Filter:</FFlabel>', 'OrFilter');
        echo $this->Form->TextBox('OrFilter', array('class' => 'InputBox WideInput')).'<FFtext> Any matched word in the <b>title</b> will satisfy the filter</FFtext>';
		echo '</FFline>';
		//
		echo '<FFline>';
		echo $this->Form->Label('<FFlabel>AND Filter:</FFlabel>', 'AndFilter');
		echo $this->Form->TextBox('AndFilter', array('class' => 'InputBox WideInput')).'<FFtext> All words must match  the <b>title</b> to satisfy the filter</FFtext>';
		echo '</FFline>';
		//
		echo '<FFline>';
		echo $this->Form->Label('<FFlabel>Minumum words:</FFlabel>', 'Minwords');
		echo $this->Form->TextBox('Minwords', array('class' => 'InputBox')).'<FFtext> Minimum number of words a feed item <b>body</b> must contain a to be imported. Use this to ignore mostly empty items (leave blank or zero for no minimum)</FFtext>';
		echo '</FFline>';
		echo '</ffinputs>';
		//
	  echo '</ul>';
	  echo '<div>'; 
	  echo $Buttonbar;
	  echo '</div></div></div></div>'; 
?>