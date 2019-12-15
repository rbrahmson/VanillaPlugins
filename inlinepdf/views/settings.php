<?php defined('APPLICATION') or die ?>
<h1><?=$this->title()?></h1>
<?=$this->Form->open()?>
<?=$this->Form->errors()?>
<?=$this->Form->simple($this->data('Schema'))?>
<?=$this->Form->close('Save')?>
