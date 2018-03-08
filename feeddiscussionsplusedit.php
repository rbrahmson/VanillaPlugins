<?php if (!defined('APPLICATION')) exit();
		$Mode = $this->Data('Mode');
		$Feed = $this->Data('Feed');
		$FeedKey = $this->Data('FeedKey');
		$FormPostValues = $this->Data('FormPostValues');
        //decho ($FormPostValues);
        $FeedURL = val('FeedURL', $FormPostValues, $this->Data('FeedURL'));
        //decho ($FeedURL);
        if (isset($Feed['Refresh'])) {
            $Refresh = $Feed['Refresh'];
        } else {
            $Refresh = '';
        }
        if (isset($Feed['LastImport'])) {
            $LastImport = $Feed['LastImport'];
        } else {
            $LastImport = '';
        }
        if ($LastImport == '' | $LastImport == 'never') {
            $LastImportmsg = '<FFtext>  Feed has not yet been imported</FFtext>';
        } else {
            $LastImportmsg = '<FFtext>  last import:'.$LastImport.'</FFtext>';
        }
        if (isset($Feed['SuggestedURL'])) {
            $SuggestedURL = trim($Feed["SuggestedURL"]);
        } else {
            $SuggestedURL = '';
        }
        $Internalurlmsg = '';
        if (c('Plugins.FeedDiscussionsPlus.showurl',false)) {
            $Internalurlmsg = '<FFNote>Url:&nbsp<b>'.$Feed['InternalURL'].'</b></FFNote>';
        }
        $Copybutton = '';
		if ($Mode == 'Add') {
			$Process = Url('plugin/feeddiscussionsplus/addfeed///Add');
			$Defaultrefresh = '1w';
            $LastImportmsg = '';
		} elseif ($Mode == 'Update') {
			$Process = Url('plugin/feeddiscussionsplus/updatefeed');
			$Defaultrefresh = val('Refresh', $this->Form->FormValues(), $Feed["Refresh"]);
            $Copybutton = '<span title="Copy settings">'.$this->Form->button(' 📄📄 Copy', array('type' => 'submit', 'name' => 'Copy', 'class' => "Button ffcolumn")).'</span>';
			if (!$FeedURL) {
				$Msg = '<h1><redtext>'.__FILE__.' Line '.__LINE__.' error - missing feed url</redtext>';
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
		$Processbutton = '<span title="Save">'.$this->Form->button(" 🔽 ".t($Mode), array('type' => 'submit', 'class' => "Button ffcolumn", 'name' => $FeedKey)).'</span>';
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
           $Pastebutton = '<span title="Paste settings">'.$this->Form->button(' 📑 Paste', array('type' => 'submit', 'name' => 'Paste', 'class' => "Button ffcolumn")).'</span>';
	    } else {
           $Pastebutton = '';
	    }
        //
		echo '<div id=FDP><div id=FDPEDIT>';
        if (isset($Feed['Feedtitle'])) {
            $Feedtitle = (string)$Feed['Feedtitle'];
        } else {
            $Feedtitle = '';
        }
        if (isset($Feed['Encoding'])) {
            $Encoding = $Feed['Encoding'];
        } else {
            $Encoding = '';
        }
        if (isset($Feed['RSSimage'])) {
            $RSSimage = $Feed['RSSimage'];
        } else {
            $RSSimage = '';
        }
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
        $Title = $Plugininfo["Name"];
        $Version = $Plugininfo["Version"];
        $IconUrl = $Plugininfo["IconUrl"];
		$Canelbutton = '<span style="margin: 0 0 0 20px;"><a class="Button ffcolumn DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/listfeeds/Not saved/?'.__LINE__).
			'" title="'.t('Return to the definitions list').'">☰ Return to list</a></span>';
		//
        $Sourcefile = pathinfo(__FILE__)["basename"];
        $Qmsg = FeedDiscussionsPlusPlugin::getmsg('', 'GETVIEW,'.$Sourcefile. ', L#'.__LINE__);
		if ($Qmsg) {
			$Titlemsg = '<br><div class=ffqmsg>' . $Qmsg . '</div>';
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
            //echo 'l#:'.__LINE__;
            $FeedURL = val('FeedURL', $this->Form->FormValues(), null);
        }
        //decho ($this->Form->FormValues());
        $Allowupdate = false;
        if (c('Plugins.FeedDiscussionsPlus.allowupdate', false)) {
            $Allowupdate = true;
        }
        //
        // If the following validator stops working you can use the next one.
        $Validatorurl='https://validator.w3.org/feed/check.cgi?url='. $FeedURL;
        //$Validatorurl='http://www.feedvalidator.org/check?url='. $FeedURL;
        //
        $Feedvalidator =   '<span >&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp<a class="Button ffcolumn DeleteFeed  " target=_BLANK href="' . $Validatorurl .
		   '" title="' . t('Run external feed validator if you have problems with this feed.').'"><b>Issues ?</b> Run Feed Validator</a><span>';
        $Twitterid = false;
        $Instaid = false;
        if ($Encoding == 'Twitter' | substr($FeedURL,0,1) == '@') {
            $Twitterid = true;
        } elseif ($Encoding == 'Instagram' | substr($FeedURL,0,1) == '!') {
            $Instaid = true;
        }
        $Urltitle= '';
        $Urllabel= '<FFlabel>Feed URL:</FFlabel>';
        if (empty($FeedURL) or trim($FeedURL) == '?') {
            //echo 'l#:'.__LINE__;
            $Feedvalidator = '';
            $Urltitle= 'title ="Enter feed URL"';
            $Mode = 'Add';
            $Internalurlmsg = ' ';
        } elseif ($Allowupdate) {
            $Urllabel= '<FFlabel>Feed source:</FFlabel>';
        } elseif ($Instaid) {
            $Feedvalidator = '';
            $Urllabel= '<FFlabel>Instagram entity</FFlabel>';
        } elseif ($Twitterid) {
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
                 $Logowrapclass = 'RSSlogowrap RSSlogoedit Twitterlogo ';
             } else {
                 $Logowrapclass = 'RSSlogowrap RSSlogoedit ';
             }
             if ($Allowupdate) {
                 $Logowrapclass = $Logowrapclass . ' RSSlogoallowedit ';
             }
             $Logo = '<span class="'.$Logowrapclass.'"> <img src="' . $RSSimage . '" id=RSSimage class=RSSimagebe title=" " ></span> ';
             $Logooption = '<span> <img src="' . $RSSimage . '" id=RSSimage class=RSSimageoption title=" " ></span> ';
		 } else {
			 $Logo = '';
			 $Logooption = '';
		 }
        //
        $Buttonbar = '<div style="display:inline-flex;float:right;"> '.$Processbutton.$Canelbutton.$Copybutton.$Pastebutton.$Feedvalidator.'</div>';
        $Buttonbar = '<ffhead><div Class="ffspread"> '.$Processbutton.$Canelbutton.$Copybutton.$Pastebutton.$Feedvalidator.'</div></ffhead>';
        //
		echo $Buttonbar;
        echo '<h4><FFBIG>❂</FFBIG>General Options</h4>';
		//
		echo '<FFUfeedhead>';
            $Highlightclass = '';
            $Helpmsg = '<FFsniptext> Enter ? for help on valid inputs</FFsniptext>';
            $Highlightclass = '';
            if ($SuggestedURL) {
                $SuggestedURL = '';
                $Highlightclass = 'redinput';
                $Urllabel = '<redtext>Suggested&nbspURL:</redtext>&nbsp&nbsp&nbsp';
                $Logo = '';
                $Encoding = '';
                $Internalurlmsg = '';
                $Helpmsg = '<FFsniptext><bluetext>&nbspThe url you entered pointed to the suggested feed url</bluetext></FFsniptext>';
            }
            if (($Encoding != '') & ($Encoding != 'N/A')) {
                $Encodingmsg = '<FFNote>(Feed type:&nbsp'.$Encoding.')</FFNote>';
            }
			echo '<span '.$Urltitle.' >'.$this->Form->Label($Urllabel, 'FeedURL').'</span>';
            if ($Allowupdate) {
                echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput '.$Highlightclass, 'maxlength' => 200, 'value' => $FeedURL)).
                    $Helpmsg . $Logo.' &nbsp&nbsp&nbsp '.$Encodingmsg.'&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp'.'</FFline>'.$Internalurlmsg ;
			} elseif (($Mode == 'Update')) {
                $Helpmsg = '';
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput NoInput ', 'maxlength' => 1, 'value' => $FeedURL)).'<FFtext>'.$FeedURL.'<br><bluetext>'.$Feedtitle.'</bluetext></FFtext>&nbsp&nbsp&nbsp'.$Logo.'&nbsp&nbsp&nbsp'.$Encodingmsg.'&nbsp&nbsp&nbsp'.$Internalurlmsg;
			} else { // assume Add request
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput '.$Highlightclass, 'maxlength' => 200, 'value' => $FeedURL)). $Helpmsg .
                ' &nbsp' . '</FFline>';
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
		echo '<FFline>'.$this->Form->CheckBox('Getlogo', "Show the feed's logo", array('value' => '1', 'class' => 'FFCHECKBOX  labelupdate')).$Logooption."<FFchecktext> (show feed's logo in the discussion and the discussion list)</FFchecktext></FFline>";
		//
		echo '<FFline>'.$this->Form->CheckBox('Noimage', "Don't include images", array('value' => '1', 'class' => 'FFCHECKBOX'))."<FFchecktext> (Don't include <i>embedded</i> images when saving feeds as discussions)</FFchecktext></FFline>";
		//
        if (c('Tagging.Discussions.Enabled',false)) {
            echo '<FFline>';
            echo $this->Form->Label('<FFlabel>Tag Feeds:</FFlabel>', 'Feedtag');
            echo $this->Form->TextBox('Feedtag', array('class' => 'InputBox')).'<FFtext> Optionally tag imported discussion with the  specified tags. (comma separated alphabetic tags)</FFtext>';
            echo '</FFline>';
        }
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
		echo 'If filters are specified, a feed items must match the following filters to be imported. ';
		echo '</FFline>';
		//
		echo '<FFline>'.$this->Form->CheckBox('Filterbody', "Filter full Content", array('value' => '0', 'class' => 'FFCHECKBOX'))."<FFchecktext>If left unchecked filters only apply to the feed item <b>titles</b> (More efficient but may miss some items).</FFchecktext></FFline>";
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
      $this->Form->close;

?>