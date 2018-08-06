<?php
$PluginInfo['Hashtag'] = array(
    'Name' => 'Hashtag',
	'Description' => 'Automaticlly creates Vanilla Tags from title or content #Hashtags.',
    'Version' => '1.1.2.5',
    'RequiredApplications' => array('Vanilla' => '2.5'),
    'RequiredTheme' => FALSE,
	'MobileFriendly' => TRUE,
    'HasLocale' => TRUE,
	'SettingsUrl' => '/settings//Hashtag',
    'SettingsPermission' => 'Garden.Settings.Manage',
	'RegisterPermissions' => array('Plugins.Hashtag.View','Plugins.Hashtag.Add'),
    'Author' => "Roger Brahmson",
	'Github' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/Hashtag",
	'Source' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/Hashtag",
	'License' => "GNU GPL3"
);
/////////////////////////////////////////

class HashtagPlugin extends Gdn_Plugin {
	///////////////////////////////////////////////
	// Set the CSS
	public function assetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('hashtag.css', 'plugins/Hashtag');
    }
	///////////////////////////////////////////////
	//Plugin Settings
    public function settingsController_Hashtag_create ($Sender, $Args) {
        $Sender->permission('Garden.Settings.Manage');
		$Debug = false;
		$Sender->addCssFile('hashtag.css', 'plugins/Hashtag');
        $Sender->setData('Title', t('Settings for the Hashtag Plugin'));
        //
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('Hashtag', Gdn_PluginManager::ACCESS_PLUGINNAME);
        //var_dump ($Plugininfo);
        $Msg = '<center>'.$Plugininfo["Name"] . ' Plugin (Version:' . $Plugininfo["Version"]. ') Settings</center><br>';
        //
        //Verify tags can be added
        if (version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Taggingsetting = 'Tagging.Discussions.Enabled';
            $Tagpermission = 'Vanilla.Tagging.Add';
            $Turnonmsg = anchor("Click to activate tagging", '/settings/tagging', '  PopupWindow', 
                    array('rel' => 'nofollow'));
            $RandM = anchor("Roles and Permissions", '/dashboard/role', '  PopupWindow', 
                    array('rel' => 'nofollow'));;
        } else {
            $Taggingsetting = 'EnabledPlugins.Tagging';
            $Tagpermission = 'Plugins.Tagging.Add';
            $Turnonmsg = anchor("Click to view the plugins list then activate the Tagging plugin", '/settings/plugins/all/Tagging', '  PopupWindow', 
                    array('rel' => 'nofollow'));
            $RandM = anchor("Roles and Permissions", '/dashboard/role', '  PopupWindow', 
                    array('rel' => 'nofollow'));
        }
        if (!c($Taggingsetting)) {
            $Msg .= '<p><b>Warning: Tagging is not enabled yet. The Hashtag plugin required that you turn on Tagging. </b>'.
                            $Turnonmsg.'</p>';
        }
        $Msg .= '<b>General Note:</b></span>'.
								$Textstyle.'This plugin auto-creates tags from #Hashtags embedded in the discussion.   This does not override the required Vanilla permissions.'.'</span>'.
								$Textstyle.' For this plugin to work the following two permissions must be set in '.$RandM.':'.'</span>'.
								$Textstyle.'<br><b>(1) </b>'. $Tagpermission .'  <i>and</i>  <b>(2)</b> Plugins.Hashtag.Add'.'<br><br></span>';
        //
        
        $Sender->Title($Msg);
        $Sender->setData('Title', $Msg);
        //
        $Sender->addSideMenu('dashboard/settings/plugins');
		$this->SettingDefaults($Sender,'settingsController');
		$Goterror =false;
		$TopWarning = '';
		$FieldErrors = '';
		//
		$ConfigurationModule = new ConfigurationModule($Sender);
		$Validation = new Gdn_Validation();
		$ConfigurationModel = new Gdn_ConfigurationModel($Validation);
		$Sender->Form->SetModel($ConfigurationModel);
		//
		if ($Sender->Form->authenticatedPostBack()) {
			$Saved = $Sender->Form->showErrors();
			$Saved = $Sender->Form->Save();
			$FormPostValues = $Sender->Form->formValues();
			$Sender->Form->SetData($FormPostValues);
			$Validation = new Gdn_Validation();
			$Data = $Sender->Form->formValues();
			//		Flag to also check body for hashtags
			$SearchBody = getvalue('Plugins.Hashtag.SearchBody',$Data);
			//		Flag to link hashtags
			$EmbedLinks = getvalue('Plugins.Hashtag.EmbedLinks',$Data);
			//  Minimum number of letters in an #Hashtag
			$Minletters = getvalue('Plugins.Hashtag.Minletters',$Data);
			$FieldErrors = $this->CheckField($Sender,$Minletters,
							Array('Integer'=>'?','Required'=> 'Integer','Min'=> 4,'Max'=>10),
							'Minimum number of letters in a #hashtag','Plugins.Hashtag.Minletters');
			//  Maximum number of letters in an #Hashtag
			$Maxletters = getvalue('Plugins.Hashtag.Maxletters',$Data);
			if ($FieldErrors == '') $FieldErrors = $this->CheckField($Sender,$Maxletters,
							Array('Integer'=>'?','Required'=> 'Integer','Min'=> 4,'Max'=>140),
							'Maximum number of letters in a #hashtag','Plugins.Hashtag.Maxletters');
			if ($FieldErrors == '') {
				if ($Minletters >= $Maxletters) {
					$FieldErrors = wrap('Maximum number of letters should be bigger than the minimum number of letters.',
										'span class=SettingError');
					$addError = $Sender->Form->addError($FieldErrors,'Plugins.Hashtag.Maxletters');
				}
			}
			// Validate flags	
			if ($FieldErrors == '' && $EmbedLinks) {
				if (!$SearchBody) {
					$FieldErrors = wrap('You turned on "Link Embedded #Hashtags" which required the "Check Body for #Hashtags" Option.',
									'span class=SettingWarning');
					$addError = $Sender->Form->addError($FieldErrors,'Plugins.Hashtag.EmbedLinks');
				} elseif (c('Garden.Format.Hashtags')) {
					$FieldErrors = wrap('You turned on "Link Embedded #Hashtags". This will be ignored until you turn off "Garden.Format.Hashtags" in config.php.',
									'span class=SettingWarning');
					$addError = $Sender->Form->addError($FieldErrors,'Plugins.Hashtag.EmbedLinks');
				}
			}
			//
			if ($FieldErrors != '') {
				$Goterror=true;
				$Sender=$Validation->addValidationResult('Plugins.Hashtag.SearchBody', ' ');
				$TopWarning = t('Errors need to be corrected. Incomplete settings saved');
				Gdn::controller()->informMessage($TopWarning);//,'DoNotDismiss');
			}
			if (!$Validation->validate($FormPostValues)) $Goterror=true;
			if ($Goterror) {
				$Sender=$Validation->addValidationResult('Plugins.Hashtag.SearchBody', ' ');
				SaveToConfig('Plugins.Hashtag.IncompleteSetup',TRUE);
			} else {
				SaveToConfig('Plugins.Hashtag.IncompleteSetup',FALSE);
			}
		// NOT POSTBACK
		} else {
			if (c('Plugins.Hashtag.IncompleteSetup')) 
				$TopWarning = 'Previously saved settings are incomplete/invalid.  Review and save correct values.';
			$Sender->Form->SetData($ConfigurationModel->Data);
        }
		//
		$PluginConfig = $this->SetConfig($Sender,Array('TopWarning' => $TopWarning),$Debug);
		$ConfigurationModule->initialize($PluginConfig);
		$ConfigurationModule->renderAll();
    }
///////////////////////////////////////// 
   public function SettingDefaults($Sender,$CallType = '') {
	   //Set default confi options
	   $Debug = false;
	   
	   $Minletters = c('Plugins.Hashtag.Minletters',4);
	   SaveToConfig('Plugins.Hashtag.Minletters',$Minletters);
	   
	   $Maxletters = c('Plugins.Hashtag.Maxletters',140);
	   SaveToConfig('Plugins.Hashtag.Maxletters',$Maxletters);
	   
	   $SearchBody = c('Plugins.Hashtag.SearchBody',false);
	   SaveToConfig('Plugins.Hashtag.SearchBody',$SearchBody);
	   
	   $EmbedLinks = c('Plugins.Hashtag.EmbedLinks',false);
	   SaveToConfig('Plugins.Hashtag.EmbedLinks',$EmbedLinks);
	   
	   
   }
/////////////////////////////////////////
// Set Confogiration Array
 public function SetConfig($Sender,$Errors = Array(),$Debug) {
	$Separator = '<span class=SettingSep>&nbsp</span>';
	$Headstyle = '<span class=SettingHead>#&nbsp&nbsp';
	$Subhstyle = '<span class=SettingSubh>';
	$Textstyle = '<span class=SettingText>';
	$Warnstyle = '<span class=SettingWarning>';
	$Errorstyle = '<span class=SettingError>';
	$Squeeze = '<span class=Settingsqueeze> </span>';
	$Notestyle = '<span class=SettingNote>';
	$Topmessage = '';
	if (trim($Errors['TopWarning'])) $Topmessage .= $Warnstyle.$Errors['TopWarning'].'</span>';
	$WarnGarden = '';
	if (c('Garden.Format.Hashtags')) $WarnGarden = '<span class=SettingGardenWarning>'.t('Right now it is <b>not</b> set to false.').'</span>';
	//Verify tags can be added
    if (version_compare(APPLICATION_VERSION, '2.5', '>=')) {
        $Taggingsetting = 'Tagging.Discussions.Enabled';
        $Tagpermission = 'Vanilla.Tagging.Add';
        $Turnonmsg = anchor("Click to activate tagging", '/settings/tagging', '  PopupWindow', 
                array('rel' => 'nofollow'));;
    } else {
        $Taggingsetting = 'EnabledPlugins.Tagging';
        $Tagpermission = 'Plugins.Tagging.Add';
        $Turnonmsg = anchor("Click to view the plugins list then activate the Tagging plugin", '/settings/plugins/all/Tagging', '  PopupWindow', 
                array('rel' => 'nofollow'));;
    }
    $Notactivemsg = '';
    if (!c($Taggingsetting)) {
        $Notactivemsg = '<p><b>Note: Tagging is not enabled yet.  The Hashtag plugin required that you turn on Tagging.</b><br>'.
                        $Turnonmsg.'</p>';
    }
    //
	$PluginConfig = array(
		/*- Option to search body for #Hashtags-*/
			'Plugins.Hashtag.SearchBody' => array(
			'Control' => 'CheckBox',
			'Description' => 	$Topmessage.
                                $Notactivemsg.
								$Headstyle.'<b>Check Body for #Hashtags'.'</span>',
			'LabelCode' => 		$Textstyle.'Search the discussion and comment bodies for #Hashtags (otherwise only the discussion title is scanned)</span>'.$Squeeze,
			'Default' => TRUE),
		/*- Option to turn off "" in the body (so it won't do search on hashtags (because they might be searchable as tags if autohshtag is enabled on the body)-*/
			'Plugins.Hashtag.EmbedLinks' => array(
			'Control' => 'CheckBox',
			'Description' => wrap('Link Embedded #Hashtags<br>','span class=SettingHead').
							 $Textstyle.'<div><b>Note:</b> Three conditions must exist for this feature to be active:'.
							 '<h5><b>(1)</b> The "Check Body for #Hashtags" above must be checked.'.'</h5>'.
							 $Textstyle.'<h5><b> (2)</b> "Garden.Format.Hashtags" must be set to "false" in config.php. '.$WarnGarden.'</h5>'.
							 $Textstyle.'<h5><b> (3)</b> Plugins.Hashtag.View must be set in Roles and Permissions'.'</h5></div></span>',
			'LabelCode' => 	$Textstyle.'Set the embedded #hashtags to link to other discussions tagged with the same hashtags.</span>'.$Squeeze,
			'Default' => TRUE),
		/*- Minimum number of letters in a #word to be considered a Hashtag-*/
			'Plugins.Hashtag.Minletters' => array(
			'Control' => 'textbox',
			'Description' => $Textstyle.'Minimum number of letters in a #word to be considered a Hashtag:<span>'.$Errors['Minletters'],
			'LabelCode' => $Headstyle.'Hashtag Sizing:<span>',
			'Default' => 4),
		/*- Maximum number of letters in a #word to be considered a Hashtag-*/
			'Plugins.Hashtag.Maxletters' => array(
			'Control' => 'textbox',
			'Description' => $Textstyle.'Maximum number of letters in a #word to be considered a Hashtag:<span>'.$Errors['Maxletters'],
			'LabelCode' => '' ,
			'Default' => 140),
	);
	 return $PluginConfig;
 }
 ///////////////////////////////////////// 
// Check Configuration Settings
 public function CheckSettings($Sender,$Type='All',$Debug) {
	 if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
	//
	$Warn = '';
	$Error = '';
	//Get the menu filled variables
	$Data = $Sender->Form->formValues();
	$SearchBody = getvalue('Plugins.Hashtag.SearchBody',$Data);
	$EmbedLinks = getvalue('Plugins.Hashtag.EmbedLinks',$Data);
	$Minletters = getvalue('Plugins.Hashtag.Minletters',$Data);
	$Maxletters = getvalue('Plugins.Hashtag.Maxletters',$Data);
	//
	if ($Type == 'All' || $Type == 'Errors') {
		//if ($Debug) echo '<br>'.__LINE__.'Backward:'.$Backward;
		if ($Minletters == '') $Minletters = 4;
		if (!is_numeric($Minletters) || $Minletters < 4 || $Minletters > 10) {
			if (!is_numeric($Minletters)) { 
				$Error  = $Error .'<br>Invalid minimum number of #Hashtag letters: "'.$Minletters.'" is not numeric';
			} else {
				$Error  = $Error .'<br>Invalid minimum number of #Hashtag letters:"'.$Minletters.'". Should be between 4 and 10.';
			}
		}
		//
		if ($Maxletters == '') $Maxletters = 4;
		if (!is_numeric($Maxletters) || $Maxletters < 4 || $Maxletters > 140) {
			if (!is_numeric($Maxletters)) { 
				$Error  = $Error .'<br>Invalid Maximum number of #Hashtag letters: "'.$Maxletters.'" is not numeric';
			} else {
				$Error  = $Error .'<br>Invalid Maximum number of #Hashtag letters:"'.$Maxletters.'". Should be between 4 and 10.';
			}
		}
		//
		if (!($Maxletters > $Minletters)) {
			$Error  = $Error .'<br>Maximum number of letters should be larger than the Minimum number of letters';
		}
		//
	}
	if ($Type == 'All' || $Type == 'Warnings') {
		if ($EmbedLinks && c('Garden.Format.Hashtags')) {
			$Warn  = $Warn .'<br>You turned on "Link Embedded #Hashtags". This will be ignored until you turn off "Garden.Format.Hashtags" in config.php';
		}
		if ($EmbedLinks && !$SearchBody) {
			$Warn  = $Warn .'<br>     You turned on "Link Embedded #Hashtags" which required the "Check Body for #Hashtags" Option. ';
		}
	}

	if ($Type != 'All' && $Type != 'Warnings' && $Type != 'Errors') {
		return 'Error - Parameter '.$Type.' Unaccepted by '.__FUNCTION__;
	}
	//
	//*****************************************//
	if ($Error) {
		$Error = substr($Error,4);
		//$Error = Wrap(substr($Error,4),'span class=SettingError');
	}
	if ($Warn) {
		$Warn = substr($Warn,4);
		$Warn = Wrap(substr($Warn,4),'span class=SettingWarning');
	}
	if ($Debug) 
		echo wrap('...'.__LINE__.' Error:'.$Error.' Warn:'.$Warn,'p class=SettingWarning');
	$Result = $Error.$Warn;
	return $Result;
 }
 ///////////////////////////////////////// 
// Check speicific field, return error message 
 public function CheckField($Sender,$Field=FALSE,$Checks=Array('Required'),$Title = 'Field', $Fieldname = '', $Style = 'span class=SettingError', $Debug = false) {
	$Errormsg='';
	foreach ($Checks as $Test => $Value) {
		//echo '<br>'.__line__.$Errormsg;
		if ($Errormsg == '') {
			//echo '<br>'.__LINE__.'Test:'.$Test.' Value:'.$Value.' on:'.$Field;
			if($Test == 'Required') {
				if ($Field == '') {
					$Errormsg='is required';
				} else {
					if ($Value == 'Integer' && !ctype_digit($Field)) {
						$Errormsg='must be an integer';
					} elseif ($Value == 'Numeric' && !is_numeric($Field)) {
						$Errormsg='must be numeric';
					} elseif ($Value == 'Alpha' && preg_match("/[0-9]+/", $Field)) {
						$Errormsg='must be alphabetic';
					}
				}
			} elseif  (($Test == 'Integer' | $Test == 'Min' | $Test == 'Max') && !ctype_digit($Field)) { 
				$Errormsg='must be an integer';
			} elseif  (($Test == 'Numeric' | $Test == 'Min' | $Test == 'Max') && !is_numeric($Field)) { 
				$Errormsg='must be numeric';
			} elseif  ($Test == 'Alpha' && preg_match("/[0-9]+/", $Field)) { 
				$Errormsg='must be alphabetic';
			} elseif  ($Test == 'Min') {
				if ($Field < $Value) $Errormsg='must not be less than '.$Value;
			} elseif ($Test == 'Max') {
				if ($Field > $Value) $Errormsg='must not be greater than '.$Value;
			}
		}
	}
	//echo '<br>'.__line__.$Errormsg;
	if ($Errormsg != '') {
		$Errormsg = wrap(t($Title).' '.t($Errormsg),$Style);
		if ($Fieldname != '') $addError = $Sender->Form->addError($Errormsg, $Fieldname);
	}
	//echo '<br>'.__line__.$Errormsg;
	return $Errormsg;
 }
	//////////////////////////////////////
	//Handle auto-linking #hashtags embedded in the body 
    public function DiscussionController_AfterCommentFormat_Handler($Sender) {
		$Debug = false;
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
			Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//  Do this only if Vanilla doesn't format hashtgs and the admin asked for this option
		if (c('Garden.Format.Hashtags')) 		return;  
		if (!c('Plugins.Hashtag.EmbedLinks')) 	return;
		if (!c('Plugins.Hashtag.SearchBody')) 	return;
		if (!Gdn::session()->checkPermission('Plugins.Hashtag.View')) return; //This requires View Hashtags permission
		//
        $Object = $Sender->EventArguments['Object'];
		$FormatBody = $Object->FormatBody;
		$CommentID = getValueR('CommentID',$Object);
		$DiscussionID = getValueR('DiscussionID',$Object);
		$Name = '';
		//
		if ($Debug) {
			$this->Showdata($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
			$this->Showdata($CommentID,__LINE__.'---CommentID---','',0,' ',true);
			$this->Showdata($Name,__LINE__.'---Name---','',0,' ',true);
			$this->Showdata($Object->FormatBody,__LINE__.'---$Object->FormatBody---','',0,' ',true);
			$this->Showdata($Object,__LINE__.'---Object---','',0,' ',true);
		}
		//
		// Handle #hashtag embedded in the body 
		// creating links like: /discussions/tagged/hashtag
		$Mixed = Gdn_Format::replaceButProtectCodeBlocks(
			'/(^|[\s,\.>])\#([\w\-]+)(?=[\s,\.!?<]|$)/i',
			'\1'.anchor('#\2', '/discussions/tagged/\2 ').'\3',
			$FormatBody);
		if ($Debug) $this->Showdata($Mixed,__LINE__.'---Mixed---','',0,' ',true);
		$Sender->EventArguments['Object']->FormatBody = $Mixed;
	}
	/////////////////////////////////////
    public function GetCurrentTags($Sender,$Discussion,$Debug = false) {
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].debug_backtrace()[0]['line'].' ---> '. debug_backtrace()[0]['function'];
			Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//
		$DiscussionID = $Discussion->DiscussionID;
		$Sqlfields = 't.TagID, t.Name, t.FullName, td.DiscussionID, td.TagID';
		if ($Debug) {
			$Sqlfields = 't.TagID, t.Name, t.FullName, t.CountDiscussions, t.Dateinserted tDateinserted, td.Dateinserted tdDateinserted, td.DiscussionID, td.TagID';
			//$this->Showdata($Discussion,__LINE__.'---Discussion---','',0,' ',true);
			//decho($Discussion);
			$this->Showdata($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
			$this->Showdata($Discussion->Name,__LINE__.'---Name---','',0,' ',true);
			$this->Showdata($Discussion->Body,__LINE__.'---Body---','',0,' ',true);
		}	
		//
		$TagSql = clone Gdn::sql();	//Don't interfere with any other sql process
		$TagSql->Reset();			//Clean slate
		$Taglist = $TagSql		//Get expanded tag info for this discussion
			->select($Sqlfields)
            ->from('TagDiscussion td')
			->join('Tag t', 't.TagID = td.TagID')
            ->where('td.discussionID', $DiscussionID)
            ->get()->resultArray();
		if ($Debug) $this->Showdata($Taglist,__LINE__.'---Taglist---','',0,' ',true);
		/*Sample Structure:
			Taglist--- array 
			....(1) array(5):[0]: value: array 
			........(2) _integer:TagID value:"7"
			........(2) string:Name value:"test"
			........(2) string:FullName value:"#test"
			........(2) _integer:CountDiscussions value:"2"
			........(2) string:tDateinserted value:"2015-05-10 13:28:44"
			........(2) string:tdDateinserted value:"2015-05-10 23:39:12"
			........(2) _integer:DiscussionID value:"1627"
			....(1) array(5):[1]: value: array 
			........(2) _integer:TagID value:"10"
			........(2) string:Name value:"self"
			........(2) string:FullName value:"#self"
			........(2) _integer:CountDiscussions value:"1"
			........(2) string:tDateinserted value:"2015-05-10 23:39:12"
			........(2) string:tdDateinserted value:"2015-05-10 23:39:12"
			........(2) _integer:DiscussionID value:"1627"
		*/
		//$Alltags = rtrim($Hashtags.','.$Tags,', ');
		//if ($Debug) $this->Showdata($Alltags,__LINE__.'---Alltags---','',0,' ',true);
		return $Taglist;
	}
	/////////////////////////////////////
    public function GetHashTags($Sender,$Name,$Body,$Debug = false) {
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].debug_backtrace()[0]['line'].' ---> '. debug_backtrace()[0]['function'];
			Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
        //$Debug = true;
		//Search for hashtags
		$Body = str_replace("&nbsp;"," ",$Name.' '.$Body." ");						//These html spaces interfere with parsing blanks...
		preg_match_all('/#([^\s]+)/',$Body, $Matches);
		$Tagarray = $Matches[0];
		//var_dump($Matches);
		var_dump($Matches[0]);
		$Minletters = c('Plugins.Hashtag.Minletters',4);
		$Maxletters = c('Plugins.Hashtag.Maxletters',140);
		$Pattern = '/^(?=.{'.$Minletters.','.$Maxletters.'}$)(#|\x{ff03}){1}([0-9_\p{L}]*[_\p{L}][0-9_\p{L}]*)$/u';
		$unwantedChars = array(',', '.', "'", '"', '!', '?' ,'&nbsp;'); // create array with unwanted chars
		$Hashtags = '';
		foreach ($Tagarray as $Key => $Tag) {
			if ($Debug) $this->Showdata($Tag,__LINE__.' Tag:','',0,' ',true);
			if ($Debug) echo "<br>Key:".$Key."Tag:".$Tag.'<br>';
			$Sanitized = trim(rtrim(strip_tags($Tag),',!?."'."'"));
			$Sanitized = strtok(str_replace($unwantedChars, '  ', strtolower(strip_tags($Tag))).' ',' '); // remove unwanted chars and use lowecase
			if (!preg_match($Pattern, $Sanitized)) {
				if ($Debug) $this->Showdata($Sanitized,'Invalid hashtag:','',0,' ',true);
				if ($Debug) echo '<br>Invalid hashtag:'.$Sanitized,'<br>';
				unset($Tagarray[$Key]);
			} else {
				$Hashtags = $Sanitized .', '.$Hashtags;
				//if ($Debug) $this->Showdata($Hashtags,__LINE__.' Hashtags:','',0,' ',true);
			}
		}
		$Hashtags = rtrim($Hashtags,', ');
		if ($Debug) $this->Showdata($Hashtags,__LINE__.' Hashtags:','',0,' ',true);
		if ($Debug) die(0);
		return $Hashtags;
    }
	///////////////////////////////////////
	//This functionality is performed by the DiscussionController_AfterCommentFormat_Handler
	public function	xxxREDUNDANTxxxDiscussioncontroller_BeforeDiscussionDisplay_handler($Sender,$Args) {
	// Optionally replace embedded hashtags with links to the tags.
		$Debug = false;
		$Args = $Sender->EventArguments;
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			//$this->Showdata($Args,'---Args---','',0,' ',false);
		}
		//  Do this only if Vanilla doesn't format hashtgs and the admin asked for this option
		if (c('Garden.Format.Hashtags')) 		return;  
		if (!c('Plugins.Hashtag.EmbedLinks')) 	return;
		if (!c('Plugins.Hashtag.SearchBody')) 	return;
		if (!Gdn::session()->checkPermission('Plugins.Hashtag.View')) return; //This required View Hashtags permission
		//
		$DiscussionID = val('DiscussionID', $Args['Discussion'], 0);
		$CommentID = val('CommentID', $Args['Discussion'], 0);
		$Tags = val('Tags', $Args['Discussion'], '');
		$Name = val('Name', $Args['Discussion'], '');
		$Body = val('Body', $Args['Discussion'], '');
		//
		if ($Debug) {
			$this->Showdata($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
			$this->Showdata($CommentID,__LINE__.'---CommentID---','',0,' ',true);
			$this->Showdata($Name,__LINE__.'---Name---','',0,' ',true);
			$this->Showdata($Body,__LINE__.'---Body---','',0,' ',true);
			//$this->Showdata($Tags,__LINE__.'---Tags---','',0,' ',true);
		}
		// Handle #hashtag embedded in the body 
		// creating links like: /discussions/tagged/hashtag
		$Mixed = Gdn_Format::replaceButProtectCodeBlocks(
			'/(^|[\s,\.>])\#([\w\-]+)(?=[\s,\.!?<]|$)/i',
			'\1'.anchor('#\2', '/discussions/tagged/\2 ').'\3',
			$Body
		);
		if ($Debug) $this->Showdata($Mixed,__LINE__.'---Mixed---','',0,' ',true);
		$Sender->EventArguments['Discussion']->Body = $Mixed;
	}
	///////////////////////////////////////
	//This hook handles the saving of comments (but not the initial discussion body).
	public function PostController_AfterCommentSave_Handler($Sender, $Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
        //Verify tags can be added
        if (version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Taggingsetting = 'Tagging.Discussions.Enabled';
            $Tagpermission = 'Vanilla.Tagging.Add';
        } else {
            $Taggingsetting = 'EnabledPlugins.Tagging';
            $Tagpermission = 'Plugins.Tagging.Add';
        }
		if (!Gdn::session()->checkPermission($Tagpermission)) return; //This required Add Tags permission
		if (!Gdn::session()->checkPermission('Plugins.Hashtag.Add')) return; //This required Add Hashtags permission
		if (!c($Taggingsetting)) 	return;		//If t then we're done here
		if (!c('Plugins.Hashtag.SearchBody')) 	return;		//If not porcessing the body then we're done here
		//
		$Discussion = $Sender->EventArguments['Discussion'];
		$Comment = $Sender->EventArguments['Comment'];
		$Body = $Comment->Body;
		//
		//if ($Debug) {
			//$this->Showdata($Discussion,__LINE__.'---Discussion---','',0,' ',true);
			//decho($Discussion);
			//$this->Showdata($Discussion->DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
			//$this->Showdata($Discussion->CategoryID,__LINE__.'---CategoryID---','',0,' ',true);
			//$this->Showdata($Discussion->Name,__LINE__.'---Name---','',0,' ',true);
			//$this->Showdata($Body,__LINE__.'---Body---','',0,' ',true);
		//}	
		//Get the hashtags embedded in the comment.
		$Hashtags = $this->GetHashTags($Sender,'',$Body,0,false,0,$Debug);		//Get the content embedded hashtags
		//if ($Debug) $this->Showdata($Hashtags,__LINE__.'---Hashtags---','',0,' ',true);
		//
		//if (trim($Hashtags) == '') return;
		// Now add the hashtags to this discussion
		$TagModel = new TagModel;
		$Types = array();
		TagModel::instance()->saveDiscussion($Discussion->DiscussionID, $Hashtags, $Types, $Discussion->CategoryID);
		return;
		//The following code is not executed.  Turns out the Tag model ignored suplicate tags so we don't need
		//to remove duplicated.  I keep it here just in case something changes in the future...
		//
		// Check existing discussion tags
		$Currenttags = $this->GetCurrentTags($Sender,$Discussion,0,$Debug);
		//if ($Debug) $this->Showdata($Currenttags,__LINE__.'---Currenttags---','',0,' ',true);
		//  Go over the current tag list and check with hashtags needs to be added to this discussion
		$Hashtagarray =array_map("trim",  explode(',',$Hashtags)); 
		//if ($Debug) $this->Showdata($Hashtagarray,__LINE__.'---Hashtagarray---','',0,' ',true);
		// Scan the current tags for this discussion
		foreach ($Currenttags as $Outerkey => $Outervalue) {
			//echo '<br>'.__LINE__.' Outerkey:'.$Outerkey.' Outervalue:'.$Outervalue;
			foreach ($Outervalue as $Key => $Value) {
				if ($Key == 'FullName') {
					$Entry = array_search(trim($Value), $Hashtagarray);
					echo '<br>'.__LINE__.' Key:'.$Key.' Value:'.$Value.' Entry:'.$Entry;
					if ($Entry !== false) unset($Hashtagarray[$Entry]);
				}
			}
		}
		if ($Debug) $this->Showdata($Hashtagarray,__LINE__.'---Hashtagarray---','',0,' ',true);
		//
		if (count($Hashtagarray) == 0) return;
		echo '<br>'.__LINE__.' Number of tags to add:'.count($Hashtagarray);
		// Now add the tags for the hashtags not already tagged in this discussion		
		$TagModel = new TagModel;
		$FormTags = implode(',', $Hashtagarray);
		$Types = array();
		TagModel::instance()->saveDiscussion($Discussion->DiscussionID, $FormTags, $Types, $Discussion->CategoryID);
		
	}
	///////////////////////////////////////	
	//This hook handles the saving of the initial discussion body (but not comments).
	public function TaggingPlugin_SaveDiscussion_handler($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = 'Saving Discussion... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];;
			Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
        //Verify tags can be added
        if (version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Taggingsetting = 'Tagging.Discussions.Enabled';
            $Tagpermission = 'Vanilla.Tagging.Add';
        } else {
            $Taggingsetting = 'EnabledPlugins.Tagging';
            $Tagpermission = 'Plugins.Tagging.Add';
        }
		if (!Gdn::session()->checkPermission($Tagpermission)) return; //This required Add Tags permission
		if (!Gdn::session()->checkPermission('Plugins.Hashtag.Add')) return; //This required Add Hashtags permission
		if (!c($Taggingsetting)) 	return;		//If t then we're done here
		if (!c('Plugins.Hashtag.SearchBody')) 	return;		//If not porcessing the body then we're done here
		//
		$FormPostValues = val('Data', $Sender->EventArguments, array());
		$Tags = val('Tags', $Sender->EventArguments,0);
		$CategoryID = val('CategoryID', $Sender->EventArguments,0);
		//
		$DiscussionID = val('DiscussionID', $FormPostValues, 0);
		$CommentID = val('CommentID', $FormPostValues, 0);
		$Tags = val('Tags', $FormPostValues, '');
		$Name = val('Name', $FormPostValues, '');
		$Body = val('Body', $FormPostValues, '');
		
		if ($Debug) {
			//echo "<br>DiscussionID:".$DiscussionID.'<br>';
			//echo "<br>CommentID:".$CommentID.'<br>';
			//echo "<br>Name:".$Name.'<br>';
			//echo "<br>Body:".$Body.'<br>';
			//echo "<br>Tags:".$Tags.'<br>';
			//decho ($FormPostValues);
			$this->Showdata($DiscussionID,'---DiscussionID---','',0,' ',true);
			$this->Showdata($CommentID,'---CommentID---','',0,' ',true);
			$this->Showdata($Name,'---Name---','',0,' ',true);
			$this->Showdata($Body,'---Body---','',0,' ',true);
			$this->Showdata($Tags,'---Tags---','',0,' ',true);
			$this->Showdata($FormPostValues,'---FormPostValues---','',0,' ',true);
			//die(0);
		}
		
		$SearchBody = c('Plugins.Hashtag.SearchBody',FALSE);
		if (!$SearchBody) {									//Automatic Hashtags only set on the discussion title?
			if ($CommentID) return;							//Then if this is a comment there is nothing more to do
			$Body = '';										//Don't look at the body
		}
		$Hashtags = $this->GetHashTags($Sender,$Name,$Body,$Debug);		//Get the content embedded hashtags
		if ($Debug) $this->Showdata($Hashtags,__LINE__.'---Hashtags---','',0,' ',true);
		$Alltags = rtrim($Hashtags.','.$Tags,', ');
		if ($Debug) $this->Showdata($Alltags,__LINE__.'---Alltags---','',0,' ',true);
		$Sender->EventArguments['Tags'] = $Alltags;			//and add them to the list of tags on the form
		//die(0);
	}
	///////////////////////////////////////
	// Display data for debugging
	public function Showdata($Data, $Message, $Find, $Nest=0, $BR='<br>', $Echo = true) {
		//var_dump($Data);
		$Line = "<br>".str_repeat(".",$Nest*4)."<B>(".($Nest).") ".$Message."</B>";
		if ($Echo) echo $Line;
		else Gdn::controller()->informMessage($Line);
		
		$Nest +=1;
		if ($Nest > 20) {
			echo wrap('****Nesting Truncated****','h1');
			return;	
		}
		if ($Message == 'DUMP') echo '<br> Type:'.gettype($Data).'<br>';//var_dump($Data);
		if  (is_object($Data) || is_array($Data)) {
			echo ' '.gettype($Data).' ';
			if (is_array($Data) && !count($Data)) echo '....Debug:'.$Data[0];
			foreach ($Data as $key => $value) {
				if  (is_object($value)) {
					$this->Showdata($value,' '.gettype($value).'('.count($value).'):'.$key.' value:','',$Nest,'<n>');
				} elseif (is_array($value)) {
					$this->Showdata($value,' '.gettype($value).'('.count($value).'):['.$key.']: value:','',$Nest,'<n>');
				} elseif (is_bool($value)) {
					$this->Showdata($value,' '.gettype($value).':'.$key.' value[]:','',$Nest,'<n>');
				} elseif (is_string($value)) {
					$this->Showdata($value,' '.gettype($value).':'.$key.' value:','',$Nest,'<n>');
				} else {
					$this->Showdata($value,'_'.gettype($value).':'.$key.'   value:','',$Nest,'<n>');
				}
			}
		} else {
			if ($Echo) 
				echo wrap('"'.$Data.'"','b');
			else Gdn::controller()->informMessage($Data,'DoNotDismiss');
			//var_dump($Data);
		}
	}
	///////////////////////////////////////////////

}
