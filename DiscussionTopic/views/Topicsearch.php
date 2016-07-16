<?php defined('APPLICATION') or die;
echo $this->Form->Open();
echo $this->Form->Errors();
$Searchstring = trim($this->data('Searchstring'));
if ($Searchstring == "") { 
	$Label = t('Enter Topic Search string');
} else {
	$Label = t('Enter Topic');
}	

echo '<br><div Class="Topichead">'.t('Topic Search').'</div>';

echo '<ul><li Class="TopicNew">'.$this->Form->Label($Label.':','Searchstring');

echo $this->Form->TextBox('Searchstring',
								array('MultiLine' => false,
										'class' => 'TopicBox',
										'rows' => 1));
echo wrap('<br>','div ');
echo "</li></ul>";
echo '<div  >';
echo $this->Form->button(t('Search'));
echo $this->Form->button(t('Cancel')); 
echo '</div></div>';

