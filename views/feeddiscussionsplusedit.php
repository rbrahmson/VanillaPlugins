<?php if (!defined('APPLICATION')) exit();
		//if (!$this->Plugin->IsEnabled()) return;
		//echo '<link rel="stylesheet" type="text/css" href="'. asset("plugins/FeedDiscussionsPlus/design/feeddiscussionsplus.css").'" />';
		//
		$Mode = $this->Data('Mode');
		$Feed = $this->Data('Feed');
		$FeedKey = $this->Data('FeedKey');
		$FeedURL = $this->Data('FeedURL');
		//echo "<br>".__LINE__." Mode:".$Mode." Feedurl:".$FeedURL.' File:'.__FILE__.'<br>';
		if ($Mode == 'Add') {
			$Process = Url('plugin/feeddiscussionsplus/addfeed///Add');
			$Defaultrefresh = '1w';
		} elseif ($Mode == 'Update') {
			$Process = Url('plugin/feeddiscussionsplus/updatefeed');
			$Defaultrefresh = $this->Data('Refresh');
		} else {
			$Process = Url('plugin/feeddiscussionsplus/updatefeed');
			if (!$FeedURL) {
				$Msg = '<h1>'.__FILE__.' Line '.__LINE__.' error - missing feed url';
				throw new Gdn_UserException($Msg);
			}
		}
		$Processbutton = $this->Form->button(t($Mode), array('type' => 'submit', 'name' => $FeedKey));
		$Processtitle = $Mode.' Settings for Feed Import';
		echo $this->Form->Open(array(
         'action'  => $Process
		));
		//echo "<br>".__LINE__." Form Action Process:".$Process.'<br>';
		echo '<div id=xPopup><div id=FDP>';
		$this->AddCssFile('feeddiscussionsplus.css', 'plugins/FeedDiscussionsPlus');
		$LastImport = $Feed['LastImport'];
		$Feedtitle = (string)$Feed['Feedtitle'];
		$Encoding = $Feed['Encoding'];
		$RSSimage = $Feed['RSSimage'];
		$Title = $this->Data['Title'];
		$Canelbutton = '<span style="margin: 0 0 0 20px;"><a class="Button DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/ListFeeds').
			'" title="'.t('Return to the definitions list').'">Return</a></span>';
		//
		if ($RSSimage) {
			 $Logo = '<span class="RSSimageboxtitle" > <img src="' . $RSSimage . '" id=RSSimage class=RSSimagebe ></span> ';
			 $Logooption = '<span   > <img src="' . $RSSimage . '" id=RSSimage class=RSSimageoption ></span> ';
		 } else {
			 $Logo = '';
			 $Logooption = '';
		 }
		//
		$Qmsg = $this->Data('Qmsg');
		if ($Qmsg != '') {
			$Titlemsg = '<br><div class=ffqmsg>'.strip_tags($Qmsg).'</div>';
		} else {
			$Titlemsg = '';
		}
		$Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
		echo '<h1 title="'.$Sourcetitle.'"> <span class=selflogo> </span> '. $Title . '  -  ' . $Processtitle.'  <fftitle>Press' . $Canelbutton . ' to return to the defined feeds list.</fftitle>'.$Titlemsg.'</h1>';
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
               "2w"  => T("Every 2 Weeks")
            );
        //
		echo '<ul><li>';
		//
        echo '<h4><FFBIG>❂</FFBIG>General Options</h4>';
		//
		echo '<FFUfeedhead>';
			echo $this->Form->Label('<FFlabel>Feed URL</FFlabel>', 'FeedURL');
			if ($Mode == 'Update') {
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput NoInput ', 'maxlength' => 1,)).'<FFFIELD>'.$FeedURL.'</FFFIELD>&nbsp&nbsp&nbsp'.'<FFBLUE>'.$Feedtitle.'</FFBLUE>'.$Logo;
				$Validatorurl='http://www.feedvalidator.org/check?url='. $FeedURL;
				// If the following validator stops working you can use the previous one.
				$Validatorurl='https://validator.w3.org/feed/check.cgi?url='. $FeedURL;
				$Feedvalidator =   '<span ><a class="Button PopupWindow " target=_BLANK href="' . $Validatorurl . 
		   '" title="' . t('Run external feed validator if you have problems with this feed.').'"><FFBLUE><b>Issues ?</b></FFBLUE> Run Feed Validator</a><span>';
			} else { //no url - assume Add request
				$Feedvalidator = '';
				echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox WideInput', 'maxlength' => 200,));
			}
			
				echo $Feedvalidator;
		echo '</FFUfeedhead>';
		//
		echo '<FFline>'.$this->Form->CheckBox('Historical', '<b>Import Historical Posts</b>', array('value' => '1', 'class' => 'FFCHECKBOX')).'<FFchecktext> Requests import of older feed posts.  This is automatically unchecked after the first import.</FFchecktext></FFline>';
		//
		if ($LastImport != 'never') {
			echo '<FFline>'.$this->Form->CheckBox('Reset', '<b>Check feed ASAP</b>', array('value' => '0', 'class' => 'FFCHECKBOX')).'<FFchecktext> Check & update feed when changes are saved (this one-time ignores the frequency check set below). </FFchecktext></FFline>';
		}
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
            ))."<FFdroptext> (How often to check the feed for new items to import)</FFdroptext>";
		//
		echo '</FFline>';
		echo '<ffinputs>';
		//
		echo '<FFline>';
		echo $this->Form->Label('<FFlabel>Maximum items:</FFlabel>', 'Maxitems');
        echo $this->Form->TextBox('Maxitems', array('class' => 'InputBox')).'<FFtext> The maximum number of items to import from this feed each time it is checked (leave blank for no limit)</FFtext>';
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
		echo $this->Form->TextBox('Minwords', array('class' => 'InputBox')).'<FFtext> Minimum number of words a feed item <b>body</b> must contain a to be imported. Use this to ignore mostly empty items</FFtext>';
		echo '</FFline>';
		echo '</ffinputs>';
		//
	  echo '</ul>';
	  echo '<div>'; 
	  /*
	  if ($FeedURL == '') { 
		echo $this->Form->button(t("Add"), array('type' => 'submit', 'name' => $FeedKey));
	  } else {  
		//echo $this->Form->button(t("Save"), array('type' => 'submit', 'name' => $FeedKey));
		echo $this->Form->button(t("Update"), array('type' => 'submit', 'name' => $FeedKey));
	  }
	  */
	  echo $Processbutton;
	  echo $Canelbutton;
	  echo '</div></div></div></div>'; 
?>