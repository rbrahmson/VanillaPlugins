<?php
/**
 * Topic form.
 *
 */
defined('APPLICATION') or die;
//
echo $this->Form->Open();
echo $this->Form->Errors();
$Topic = trim($this->data('Topic'));
$DefaultTopic = $this->data('DefaultTopic');
$DiscussionTitle = $this->data('DiscussionName');
$FormMsg = $this->data('FormMsg');
$ModeMsg = $this->data('ModeMsg');
$ModeName = $this->data('ModeName');
$TopicAnswer = $this->data('TopicAnswer');
$TopAnswerMode = $this->data('TopAnswerMode');
if ($Topic == "") {
    $Label = t('Enter new Topic');
} else {
    $Label = t('Current Topic');
}

echo '<br><div Class="Topichead">'.$DiscussionTitle.'</div>';

echo '<ul><li Class="TopicNew">'.$this->Form->Label($Label.':', 'Topic');

echo $this->Form->TextBox(
    'Topic',
    array('MultiLine' => false,
    'class' => 'TopicBox',
    'rows' => 1)
);
echo '<br>'.t('Note: To match other discussions with deterministic topics (see the plugin readme file)'.
            'you must enclose the topic in double quotes.').'<br>';
echo '<br>';
echo $FormMsg;
//
echo '<br>';
echo "</li></ul>";
echo '<div  >';
echo $this->Form->button(t('Save'), array('type' => 'submit', 'name' => 'RegularSave'));
if ($TopAnswerMode == 1) {
    echo $this->Form->button(t('Save as Top Topic'), array('type' => 'submit', 'name' => 'TopSave'));
}
if (($DefaultTopic != '') & ($ModeName != 'Manual')) {
    echo $this->Form->button(t('Generate'), array('type' => 'submit', 'name' => 'Generate'));
}
if ($Topic != "") {
    echo $this->Form->button(t('Remove'));
}
echo $this->Form->button(t('Cancel'));
echo '</div></div>';
