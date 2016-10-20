<?php defined('APPLICATION') or die;
?>

<?php echo $this->Form->Open();?>
<?php echo $this->Form->Errors()?>
<?php
$Currenturl = '/entry/signin';
$Currenturl = urlencode($Sender->SelfUrl);
$Note = trim($this->data('Note'));
$DiscussionTitle = $this->data('DiscussionName');
$Viewonly = $this->data('Viewonly');
if ($Note == "") { 
	$Label = t('Enter new Note');
} else {
	$Label = t('Current Note');
}	

echo '<br><div Class="Notehead">'.$DiscussionTitle.'</div>';

echo '<ul><li Class="NoteNew">'.$this->Form->Label($Label.':','Note');

if (!$Viewonly) {
			echo $this->Form->TextBox('Note',
								array('MultiLine' => true,
										'class' => 'NoteBox',
										'rows' => 5));
			echo '<br>Note: length is limited to 200 characters<br>';
} else {
			echo '<div Class="NoteBox">'.$Note.'</div>';
}							
echo "</li></ul>";
echo '<div class="P">';
if (!$Viewonly) {
	echo $this->Form->button(t('Save'));
	if ($Note != "") echo $this->Form->button(t('Remove'));
}
echo $this->Form->button(t('Cancel')); 
?>
</div></div>
