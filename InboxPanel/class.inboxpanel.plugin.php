<?php if (!defined('APPLICATION')) exit();

$PluginInfo['InboxPanel'] = array(
    'Name' => 'Inbox Panel',
    'Description' => 'This plugin adds the conversation (PM) inbox to the side panel.  Includes popup preview, tooltips, baloon counter, auto-refresh, links to respond, links to view all messages and start a new message',
    'Version' => '2.5.0',
    'RequiredApplications' => array('Vanilla' => '2.5'),  /*Tested environment*/
    'RequiredTheme' => FALSE,
    'RequiredPlugins' => FALSE,
	'MobileFriendly' => FALSE,
    'HasLocale' => TRUE,
    'SettingsUrl' => '/plugin/inboxpanel',
    'SettingsPermission' => 'Garden.Settings.Manage',
	'RegisterPermissions' => array('Plugins.inboxpanel.View'),
    'Author' => "Roger Brahmson",
    'License' => 'GPLv2'
);


//  Plugin to add inbox to the side panel to make private conversations more accessible. For that reason the inbox side panel is shown on a broad
//  list of controllers to extend the user visibility to the inbox throughout the site.   If this is not desired then readjust the list of controllers
//  in the "$AllowedControllers" variable (search deep below).
//
//
class InboxPanelPlugin extends Gdn_Plugin {

  public function PluginController_InboxPanel_create($Sender, $Args) {
	$Sender->Title('Inbox Plugin');
	// New method (only in 2.5+)
    if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
        $Sender->setHighlightRoute('plugin/inboxpanel');
    } else {
        $Sender->addSideMenu('plugin/inboxpanel');
    }

	// get sub-pages forms ready
	$Sender->Form = new Gdn_Form();
	$this->Dispatch($Sender, $Sender->RequestArgs);

  }
  //public function settingsController_InboxPanel_create($Sender, $Args) {
  public function Controller_Index($Sender, $Args) {
	// Manage admin settings for this plugin
	global $RBDebugActive;			//Declaration of debugging via RBDebug
	$RBDebugActive = FALSE;			//Set variable to TRUE for debugging
	//$this->RBDebug(__LINE__,"---Plugin Settings starts:",__FUNCTION__,$RBDebugActive); //  Debugging


    // Ensure setting panel cannot be called by everyone
	$Sender->Permission('Garden.Settings.Manage');
	$Errormsg ='';
	$Errorcount =0;
	$FormValues = $Sender->Form->FormValues();

	// Add Settings button in the Plugins dashboard that would link to the url defined
	// in SettingsUrl in $PluginInfo above.
	// New method (only in 2.5+)
    if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
        $Sender->setHighlightRoute('plugin/inboxpanel');
    } else {
        $Sender->addSideMenu('plugin/inboxpanel');
    }
	// Set the settings panel headings
	//
	 $Sender->setData('Title', t($this->getPluginKey('Name').' '.t('Settings')).
		' - '.t($this->getPluginKey('Description')));

	$Sender->Form = new Gdn_Form();
	$Posted = $Sender->Form->AuthenticatedPostBack();
	// Set up validation rules
	$Validation = new Gdn_Validation();
	$ConfigurationModel = new Gdn_ConfigurationModel($Validation);
	$Sender->Form->SetModel($ConfigurationModel);
	$ConfigurationModel->Validation->applyRule('InboxPanel.InspectCount', 'Required',
		t('Enter the number database messages to inspect.'));
	$ConfigurationModel->Validation->applyRule('InboxPanel.Count', 'Required',
		t('Enter the number messages to display.'));
	// For future developemt
	$ConfigurationModel->Validation->applyRule('InboxPanel.Refresh', 'Required',
		t('Enter the number seconds between checking for new messages.'));
	//
	//Define our own validation function
	//It doesn't matter on which variable we set our own rule since we have access to
	//all the variables in the validation function and can cross-validate them all
	$ConfigurationModel->Validation->AddRule('OurValidation','function:InboxPanelValidation');
	$ConfigurationModel->Validation->ApplyRule('InboxPanel.PlaceOnTop','OurValidation');
	//$this->RBDebug(__LINE__,"---added our validation rule:"," ",TRUE); //  Debugging

	if ($Posted) {
		//  Check for validation results
		if (!$Validation->validate($FormValues)) {  //Vanilla Validation failed
			$Validation->addValidationResult('Count', 'Please review the instructions on the screen');
			$Sender->Form->setValidationResults($Validation->Results());
			$Sender->Form->setData($FormValues);     //Reload form with submitted data
				//$this->RBDebug(__LINE__,"FormValues:",$FormValues,TRUE); //  Debugging
			$Errorcount = $Sender->Form->ErrorCount();  // returns an error count
				//$this->RBDebug(__LINE__,"Errorcount:",$Errorcount,TRUE); //  Debugging
			removeFromConfig('InboxPanel');   //Clear up saved errors, force reinitialization
			Gdn::locale()->setTranslation('Saved', '---Nothing Saved---');
			//$ConfigurationModule = new ConfigurationModule($Sender);
		}
		else {  //no errors
			// Handle the "Place on top" configuration setting
			$Placeontop = $FormValues['InboxPanel.PlaceOnTop'];  //Place panel on top setting
			$ModuleSort = C('Modules.Vanilla.Panel');
			//$this->RBDebug(__LINE__,"ModuleSort",$ModuleSort,TRUE);		//  Debugging
			if ($Placeontop) {
				$ModuleSort = preg_replace('/\InboxPanelModule\b/', '', $ModuleSort); //Remove our module from the list
				$ModuleSort = array_filter( $ModuleSort );			// Clear empty slots
				array_unshift($ModuleSort, 'InboxPanelModule');		// Add our module upfront
				SaveToConfig('Modules.Vanilla.Panel', $ModuleSort);	// and save the setting
			}
			elseif (!empty($ModuleSort)) {
				$ModuleSort = preg_replace('/\InboxPanelModule\b/', '', $ModuleSort); //Remove our module from the list
				$ModuleSort = array_filter( $ModuleSort );			// Clear empty slots
				SaveToConfig('Modules.Vanilla.Panel', $ModuleSort);	// and save the setting
			}
		}
	}  //  end of postback handling
	//
	$ConfigurationModule = new ConfigurationModule($Sender);  // Create our instance of the object
	// Let the configuration module handle the settings
	// Define the settings model (panel fields)
	//
	// use "schema" to define but not save:
	//	$ConfigurationModule->Schema(array(
	$ConfigurationModule->initialize(array(

		'InboxPanel.InspectCount' => array(
			'Control' => 'textbox',
			'LabelCode' => 'Number of database messages to inspect (5 to 20)',
			'Description' => 'Note: If you specify below to only show new messages and the only unread messages are beyond that number then nothing will be shown.',
			'Default' => '15'),
		'InboxPanel.Count' => array(
			'Control' => 'textbox',
			'LabelCode' => 'Max Number of messages to show (2 to 20)',
			'Description' => 'The maximum number of messages to appear in the Inbox panel.',
			'Default' => '5','Number of messages to show'),
		'InboxPanel.Refresh' => array(
			'Control' => 'textbox',
			'LabelCode' => 'Number of seconds between refresh (10 to 240). Specify 0 to disable auto-refresh',
			'Description' => 'interval between checks for new messages (users can always click on the "✉" button to refresh the panel)',
			'Default' => '120'),
			 'InboxPanel.Bubble' => array(
			'Control' => 'CheckBox',
			'Description' => 'Show new messages bubble',
			'LabelCode' => 'If checked a red bubble counter will be shown near the "✉" on the panel corner (Note: This updates independently of the overall Vanilla alert bubble) ',
			'Default' => TRUE),
		 'InboxPanel.Permission' => array(
			'Control' => 'CheckBox',
			'Description' => 'Require Role Permission',
			'LabelCode' => 'If checked you need to set Role Permission (View permission for the InboxPanel in the Roles Plugins section)  ',
			'Default' => TRUE),
		'InboxPanel.OnlyNew' => array(
			'Control' => 'CheckBox',
			'LabelCode' => 'Only show new messages',
			'Description' => 'Only show messages that are marked as new',
			'Default' => FALSE),
		'InboxPanel.HideIfNone' => array(
			'Control' => 'CheckBox',
			'LabelCode' => 'Hide box if no messages',
			'Description' => 'don\'t show inbox panel if there are no messages to display',
			'Default' => FALSE),
		'InboxPanel.PlaceOnTop' => array(
			'Control' => 'CheckBox',
			'LabelCode' => 'When set and unless other plugins interfere, inbox will appear at the top of the side panel',
			'Description' => 'Place the Inbox at top of the side panel (See the Module Sort plugin by Bleistivt for more control over the order of side panel modules.)',
			'Default' => FALSE)));
	if ($Errorcount) removeFromConfig('InboxPanel');   //If there are any errors clear all configuration values
	$ConfigurationModule->renderAll();  //Display the settings panel
	//$this->RBDebug(__LINE__,"---END:",__FUNCTION__,TRUE); //  Debugging

}
	/***********************************************************************/
  public function Base_Render_Before($Sender) {
	// Conditionally add the InboxPanel module (added only if there is panel to show,
	// it is not one the dashboard, and the controller is listed in the list (see below).
	//
	global $RBDebugActive;         //Declaration of debugging via RBDebug
	$RBDebugActive = FALSE;
	$GotController = strtolower($Sender->ControllerName);  //Current controller
	$AllowedControllers = 'discussionscontroller,categoriescontroller,discussioncontroller,draftscontroller,profilecontroller,activitycontroller';  //List of contollers where Inbox sidepanel is shown

	if ($RBDebugActive) $AllowedControllers = 'messagescontroller,notificationscontroller,'.$AllowedControllers; // For debugging also show side-by-side with the Inbox

	//Don't show side panel on Admin function
	if ($Sender->MasterView == 'admin'|| !isset($Sender->Assets['Panel'])) return;
	//Don't show unless in the list of intended controllers
	if (!in_array(strtolower($Sender->ControllerName), explode(',',$AllowedControllers))) {
		//$this->RBDebug(__LINE__,"GotController:",$GotController,TRUE); //  Debugging
		//$this->RBDebug(__LINE__,"AllowedControllers:",$AllowedControllers,TRUE); //  Debugging
		return;
	}

	// If configuration requires role-based permission check for the permission
	$IsAdmin = Gdn::Session()->CheckPermission('Garden.Users.Edit');  //Admin always allowed
	$CheckRolePermission = C('InboxPanel.Permission');
	//echo ("<br>".__LINE__."=====var_dump CheckRolePermission<br>");var_dump($CheckRolePermission);
	if (!$IsAdmin) {
	  if ($CheckRolePermission == '1') {
		  if (!CheckPermission('Plugins.inboxpanel.View') ) {
			return;                                         //Exit if user doesn't have permission
		  }
	  }
	}
	// Set up CSS for the panel (except on the admin panel
	if ($Sender->MasterView == 'admin') return;

	$Sender->AddCssFile('inboxpanel.css', 'plugins/InboxPanel');

	// Now that the CSS is set, bring in the module into this controller
	$ModuleToAdd = new InboxPanelModule($Sender);   //New module
	$Sender->AddModule($ModuleToAdd);               //Add the new module
	//$Sender->AddJsFile($this->GetResource('js/inboxpanelalert.js', FALSE, FALSE));
	$Sender->addJsFile('js/inboxpanelalert.js', 'plugins/InboxPanel');

	$CurrentAlerts=Gdn::session()->User->CountUnreadConversations;	//Currelt Alert Count
	$Refresh = C('InboxPanel.Refresh', 120);						//Refresh rate

	$Sender->AddDefinition('InbooxPanelLastAlerts',$CurrentAlerts);
	$Sender->AddDefinition('InboxPanel.Refresh', $Refresh);

	$Cookiename="IBPLastAlerts";									//Save Last Alert count in cookie
	setcookie($Cookiename, $CurrentAlerts, time() + (360), "/"); 	// Should be enough-JS will handle rest
	/*
	if(!isset($_COOKIE[$Cookiename])) {
		echo "Cookie named '" . $Cookiename . "' is not set!";
	}
	*/
  }
    /////////////////
	public function Controller_InboxPanelCount($Sender) {
		//echo wrap('testing-Controller_InboxPanelUpdate');
        $Inboxcount = new InboxPanelModule($Sender);;
		echo wrap($Inboxcount->GetAletCount());
    }
  /////////////////
	public function Controller_InboxPanelUpdate($Sender) {
		//echo wrap('testing-Controller_InboxPanelUpdate');
        $Inboxcontent = new InboxPanelModule($Sender);
		echo wrap($Inboxcontent->toString());
    }
  /////////////////
  public function onDisable() {
    // Clean up when plugin in disabled
	//$this->RBDebug(__LINE__,"------",__FUNCTION__,TRUE); //  Debugging
	//Check if module sort list exists and if so remove our plugin module from the list
	$ModuleSort = C('Modules.Vanilla.Panel');
	if (!empty($ModuleSort)) {
		$ModuleSort = preg_replace('/\InboxPanelModule\b/', '', $ModuleSort); //Remove our module from the list
		$ModuleSort = array_filter( $ModuleSort );			//Clear empty slots
		SaveToConfig('Modules.Vanilla.Panel', $ModuleSort);	//and save
	}
	removeFromConfig('InboxPanel');							//remove all plugin settings
  }

	/***********************************************************************/
	/**** Debugging Function (may be useful in other plugin development) ****/
   	public function RBDebug($Argline,$ArgVarname,$ArgVarValue,$Forcedebug = 0) {
	  global $RBDebugActive;
      global $RBDebugInitialized;
	  if ($RBDebugActive  || $Forcedebug){
	    if (!$RBDebugInitialized){
			$RBDebugInitialized = true;
			LogMessage(__FILE__,$Argline,'Object','Method',"**** Starting Plugin  Debug ****");
	   }
	   if (is_array($ArgVarValue)) {
			$ArgVarValue = '.._ARRAY_..'.implode(" ◙ ",$ArgVarValue);
			LogMessage("Line:",$Argline,' ',' ',"RBDebug: $ArgVarname = $ArgVarValue");
	   }
	   if (substr($ArgVarname,0,1) == "-")
   	     LogMessage("Line:",$Argline,' ',' ',"RBDebug: $ArgVarname --- $ArgVarValue");
	   else
		 LogMessage("Line:",$Argline,' ',' ',"RBDebug: $ArgVarname = $ArgVarValue");
	  }
	}
    /***********************************************************************/
}
//
// Our own validation routine (Note: must be defined outside the Plugin class)
//
if (!function_exists('InboxPanelValidation')) {
    function InboxPanelValidation($Value, $Field = '', $PostValues = False) {
		/*var_dump($PostValues);
		Paremeters passed to our validation function:

		$Value
			string(2) "10"   <--- The specific variable where the rule was set
		$Field
			object(stdClass)#70 (1) { ["Name"]=> string(18) "InboxPanel.HideIfNone" }

		$PostValues
			string(3) "121"
			object(stdClass)#70 (1) {
				["Name"]=> string(18) "InboxPanel.Refresh" }
				array(10) {
					["TransientKey"]=> string(12) "VMLQ3BGWB4KV"
					["hpt"]=> string(0) ""
					["InboxPanel.InspectCount"]=> string(2) "15" ["InboxPanel.Count"]=> string(2) "16" ["InboxPanel.Refresh"]=> string(3) "121"
					["Checkboxes"]=> array(3) {
						[0]=> string(21) "InboxPanel.Permission"
						[1]=> string(18) "InboxPanel.OnlyNew"
						[2]=> string(21) "InboxPanel.HideIfNone" }
					["Save"]=> string(4) "Save"
					["InboxPanel.Permission"]=> bool(false) ["InboxPanel.OnlyNew"]=> bool(false) ["InboxPanel.HideIfNone"]=> bool(false) }
		*/
		$Errormsg = '';
		$F_InspectCount = $PostValues['InboxPanel.InspectCount'];
		$F_Count = $PostValues['InboxPanel.Count'];
		$F_Refresh = $PostValues['InboxPanel.Refresh'];
		//LogMessage("Line:",__LINE__,'Object','Method',"RBDebug: F_Count = $F_Count");
		//LogMessage("Line:",__LINE__,'Object','Method',"RBDebug: F_InspectCount = $F_InspectCount");
		// CHeck for value consistencies
		if (!is_numeric($F_InspectCount))
		   $Errormsg =  'Inspect DB value should be Numeric.';
		elseif (!is_numeric($F_Count))
		   $Errormsg =  'number of messages to show should be  Numeric.';
		elseif ($F_InspectCount<5 || $F_InspectCount>20)
		   $Errormsg =  'Inspect DB value should be between 5 and 20.';
		elseif ($F_Count<2 || $F_Count>20)
			$Errormsg =  'number of messages to show should be between 2 and 20.';
		elseif ($F_Count > $F_InspectCount)
		   $Errormsg =  'number of messages to show should not exceed the number of inspected database records.';
		// For future developemt
		elseif (($F_Refresh != 0) && ($F_Refresh<10 || $F_Refresh>240))
			$Errormsg =  'number of seconds between new message checks should be zero or between 10 and 240 secords.';
		//
		if (!$Errormsg == ''){
			//LogMessage("Line:",__LINE__,'Object','Method',"RBDebug: Errormsg = $Errormsg");
			return(T($Errormsg));
		}  //end error handling of our own validation
		return TRUE;
    }
}
/////////////////////////////////////////