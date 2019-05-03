<?php if (!defined('APPLICATION')) exit();
echo $this->Form->Open();
echo $this->Form->Errors();
echo '<div>';

$Plugininfo = Gdn::pluginManager()->getPluginInfo('FilterDiscussion');
$Title = 'DiscussionFilter '.Gdn::Translate('Version').' '.$Plugininfo["Version"].' '.Gdn::Translate('Settings');
$Fields = implode(', ',$this->Data["Fields"]);
echo "<h1>".$Title.'</h1><br>'; 
echo '<b>General syntax of the url:</b>/discussions/filterdiscussion?column=operator:value&column=operator:value etc.<br>';
echo '<b>Valid operators:</b> EQ, NE, GT, LT, NL, NN (equal, not equal, greater than, less than, null and not null)<br>';
echo '<b>Example:</b>/discussions/filterdiscussion?Prefix=EQ:Video&InsertUserID=EQ:13&CategoryID=EQ:6 <br>';
echo '<b>Special column names:</b> {\$category},{\$insertusername},{\$updateuserrname} - indicates that the value is the name rather than number<br>';
echo '<b>Example:</b>/discussions/filterdiscussion?{\$category}=EQ:Sport&{\$insertusername}=EQ:Joe doe<br><br>';
//
echo '<b>'.t('Optionally limit allowed Discussion table field names (column names):').'</b><br>';
echo $this->Form->TextBox('Plugins.FilterDiscussion.Fieldnames', array('class'=>'NameInput','size'=>"120"));
echo '<br>'.t('(Use comma sepators between field names. Case Sensitive! Ensure you are accurate with SQL column names!)');
echo '<br><b>'.t('The full list of current columns in the Discussion table is shown below').'</b><br>'.$Fields.'<br><br>';
//
echo '<b>'.t('List the url parameters you want ignored (This is to accommodate other plugins that read url parameters):').'</b><br>';
echo $this->Form->TextBox('Plugins.FilterDiscussion.Ignoreparms', array('class'=>'NameInput','size'=>"120"));
echo '<br>'.t('Use comma sepators between ignorable parameter names').'<br><br>';
//
echo '<b>'.t('Specify the default filter to apply when no filter is provided </b>(through parameters or through another plugin):').'</b><br>';
echo $this->Form->TextBox('Plugins.FilterDiscussion.DefaultFilter', array('class'=>'NameInput','size'=>"120"));
echo '<br>'.t('Must follow the same syntax rules as passing filter through the URL parameter. Saved filters are supported').'<br>';
//
echo '<br><b>'.t('Specify saved filters').'</b><br>'.t(' Saved filters can be involed by using "?!filter=Saved-Filter-Name" instead of the whole parameter').'</b><br>';
echo t("Specify a name and it's associated saved filter parameters. This allows use of ?!filter=saved-name in lieu of long url parameter lists").'<br>';
echo t("It also hides the fitering parameters from the user.").'<br>';
//
echo '<b>Saved Filter parameter example:</b> Prefix=EQ:Video&InsertUserID=EQ:13&CategoryID=EQ:6<br>';
echo '<table><thead><tr><th>';
echo Gdn::Translate('Filter Name');
echo '</th><th class="Alt">';
echo Gdn::Translate('Filter Parameters').'  <br>';
echo '</th></tr></thead><tbody><tr><td ></td></tr>';
for ($x = 1; $x <= 20; $x++) {	
	echo '<tr><td><span class=savedfilterpad>'.str_pad($x, 2, "0", STR_PAD_LEFT).' </span><span class=savedfilter>'; 
    
	echo $this->Form->TextBox('Plugins.FilterDiscussion.SavedName'.$x, array('class'=>'NameInput','size'=>'10'));
	echo ' </span></td>  <td >  <span class=savedfilter>' ;
	echo $this->Form->TextBox('Plugins.FilterDiscussion.SavedFilter'.$x, array('class'=>'NameInput','size'=>'100'));
	echo '</span></td></tr>';
}
echo '</tbody></table><br>';
echo '</div>';
echo $this->Form->Close('Save');?>