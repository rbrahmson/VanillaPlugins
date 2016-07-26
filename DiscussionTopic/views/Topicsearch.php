<?php defined('APPLICATION') or die;
$SearchSubject = $this->data('SearchSubject');

$CloseScript = 		'<script language="JavaScript" type="text/javascript">
						function CloseAndRefresh()  {
							$( "div" ).remove( ".Overlay" );
							$( "div" ).remove( "@Popup" );
							$( "div" ).remove( "#Popup" );
							//self.close();
						}
					  </script>';
echo $CloseScript;
//						
echo $this->Form->Open();
echo $this->Form->Errors();

$Searchstring = trim($this->data('Searchstring'));
$Label = t('Enter Search Argument');
if ($SearchSubject) $Label = t('Enter free form discussion title to search for similar topics');

echo '<br><div Class="Topichead">'.t('Topic Search').'</div>';
//
echo 	wrap(t('To search by exact topic enter the topic and click the "Search by topic" button').'<br>'.
		 	 t('To search by discussion name type in discussion subject <i>in free form</i> and clicl the "Search by subject" button'),'div');
echo '<ul>';
//
echo '<li Class="TopicNew">'.$this->Form->Label($Label.':','Searchstring');
echo $this->Form->TextBox('Searchstring',array('MultiLine' => false,'class' => 'SearchTopicBox','rows' => 1));

echo wrap('<br>','div ');
echo '</li>';
//
echo "</ul>";

echo '<div  >';

echo $this->Form->button(t('Search by topic'), array('type' => 'submit','name' => 'TopicSearch','onClick' => "CloseAndRefresh()"));
echo $this->Form->button(t('Search by subject'), array('type' => 'submit','name' => 'SubjectSearch','onClick' => "CloseAndRefresh()"));
//echo $this->Form->button(t('Search by topic'), array('type' => 'submit','name' => 'TopicSearch'));
//echo $this->Form->button(t('Search by subject'), array('type' => 'submit','name' => 'SubjectSearch'));


echo $this->Form->button(t('Cancel')); 

echo '</div></div>';

