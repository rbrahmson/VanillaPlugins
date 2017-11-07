<?php if (!defined('APPLICATION')) exit();
		//if (!$this->Plugin->IsEnabled()) return;
		//echo '<link rel="stylesheet" type="text/css" href="'. asset("plugins/FeedDiscussionsPlus/design/feeddiscussionsplus.css").'" />';
		//
		$Mode = "Restore";
		$Title = $this->Data['Title'];
		
		$FeedURL = $this->Data('FeedURL');
		$Feed = $this->Data('Feed');
		$LastImport = $Feed['LastImport'];
		$Active = $Feed['Active'];
		$Feedtitle = (string)$Feed['Feedtitle'];
		$Encoding = $Feed['Encoding'];
		$RSSimage = $Feed['RSSimage'];
		$OrFilter = $Feed['OrFilter'];
		$AndFilter = $Feed['AndFilter'];
		$Minwords = $Feed['Minwords'];
		$Historical = $Feed['Historical'];
		$Getlogo = $Feed['Getlogo'];
		$Noimage = $Feed['Noimage'];
		$Maxitems = $Feed['Maxitems'];
		$Activehours = $Feed['Activehours'];
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
		$Frequency = GetValue($Feed['Refresh'], $Refreshments, T('Unknown'));
        $Category = $this->Data("Categories.{$CategoryID}.Name", 'Root');
		
		echo "<br>".__LINE__." Mode:".$Mode." Feedurl:".$FeedURL.' File:'.__FILE__.'<br>';
		$Processbutton = $this->Form->button(t($Mode), array('type' => 'submit', 'name' => $FeedKey));
		$Processtitle = $Mode.' Settings for Feed Import';
		//
		echo $this->Form->Open(array(
         'action'  => Url('plugin/feeddiscussionsplus/restorefeed/'.$FeedURL.'/Restore')
		));
		//
		echo '<div id=xPopup><div id=FDP>';
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
		$Qmsg = $this->Data('Qmsg');
		$this->SetData('Qmsg', __FUNCTION__.__LINE__);
		$Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
		echo '<h1 title="'.$Sourcetitle.'"> <span class=selflogo> </span> '. $Title . '  -  ' . $Processtitle.'  <fftitle>Press' . $Canelbutton . ' to return to the defined feeds list.</fftitle></h1>';
		if ($Qmsg != '') {
			echo '<FFmsg><FFBLUE>'.strip_tags($Qmsg).'</FFBLUE></FFmsg>';
		}
		echo $this->Form->Errors();
		echo '<div class="FeedContent" style="line-height: 25px;font-size: 13px;margin: 0px 4px 0px 6px;">';
        //;
		//
		echo '<ul><li>';
		//
		echo '<h4><FFUfeedhead>';
		
		echo $Encoding.' Feed URL: '.$FeedURL.'<div>'.$Logooption.'</div>'.$Feedtitle;
		
		echo '</FFUfeedhead></h4>';
		//
		if ($Historical) echo '<br>Feed is set to import historical posts on the next import';
		//
		echo '<br>Target category:'.$Category;
		
		if ($Active) {
			echo '<br>Feed is active';
		} else {
			echo '<br>Feed is inactive';
		}
		//
		echo '<br>Import frequency:'.$Frequency;
		//
		if ($OrFilter) echo '<br> OR Filter: '.$OrFilter;
		//
		if ($AndFilter) echo '<br> AND Filter: '.$AndFilter;
		//
		if ($Minwords) echo '<br> Minimum number of words to import post: '.$Minwords;
		//
		if ($Activehours) echo '<br> Active import hours:'.$Activehours;
		//
		if ($Noimage) echo '<br> Images are not included in saved posts.';
		//
		if ($Maxitems) echo '<br> Maximum number of saved items per import:'.$Maxitems;
		//
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