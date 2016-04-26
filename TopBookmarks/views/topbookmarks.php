<?php defined('APPLICATION') or die;
?>

<?php echo $this->Form->Open();?>
<?php echo $this->Form->Errors()?>
<?php
$Discussion = $this->data('Discussion');
$DiscussionTitle = $Discussion->Name;
$Title = $this->data('Title');
$Limit = $this->data('Limit');
$Author = $Discussion->InsertUserID;
$Readerlist = $this->data('Readerlist');
$Readercount = $this->data('Readercount');

echo '<div Class="ReadbyFormHead"><H1>'.trim($DiscussionTitle)."</H1></div>";
echo '<div Class="ReadbyFormTitle">'.t($this->data('Title')).'</div>';


	$Listcount = 0;
	foreach($Readerlist as $Entry){
		//echo __LINE__.' '.var_dump($Entry);
		$User = Gdn::UserModel()->GetID($Entry->UserID);
		$Username = $User->Name;
		$Listcount = $Listcount +1;
		$Useridlist=$Useridlist.$Entry->UserID.'  ';
		$Note=' ';
		if ($Entry->UserID == Gdn::Session()->UserID) $Note .= t('*YOU*');
		if ($Entry->UserID == $Author) $Note  .= t('*Author*'); 
		if (isset($Entry->DateLastViewed)) 
			$Note  .= wrap(t(' Last VIewed:').Gdn_Format::Date($Entry->DateLastViewed).' ','span'); 
		$Photo = userPhoto($User,array('Title' => $Username.' '.$Note." "));
		$Photolist = $Photolist.wrap($Photo,'li id=readid'.$Entry->UserID,' class=Readbylistpic');
		echo '<div Class="ReadbyFormLine">'.$Listcount.'&nbsp '.$Photo.'&nbsp '.$Username.'&nbsp '.$Note.'</div>';
	}
	if ($Discussion->CountBookmarks>$Listcount) {
		$Diff = $Discussion->CountBookmarks-$Listcount;
		echo wrap('<br>'.$Diff.' '.t('additional bookmarks were made (this is not a full list)'),'H2');
	}
	if ($Listcount == $Limit) {
		echo wrap('<br>'.t('This size of this list is limitd to').' '.$Limit,'H2');
	}
//echo '<br>'.$this->Form->button(t('Return')); 
?>