<?php echo $this->Form->Open();
echo '<div id=FDP><div id=HELP><div ID=xContent>';
$Closebutton = '<h1><a class="Button ffcolumn  " style="float: right;" href="' . Url('/plugin/feeddiscussionsplus/ListFeeds'). 
	   '" title="' . t('Return to the configuration screen').'"> <b style="font-size:15px;">☒</b> '.t("Close").'</a></h1>';
echo $Closebutton;
include_once "CustomizationandSetupGuide.htm";
echo $Closebutton;
echo '</div></div></div>';?>