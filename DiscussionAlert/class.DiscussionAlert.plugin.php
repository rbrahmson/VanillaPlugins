<?php
$PluginInfo['DiscussionAlert'] = array(
    'Name' => 'Discussion Alert',
	'Description' => 'Allows users with assigned permissions to mark a discussion with an alert and users with another permission to see that a discussion was marked with an alert. Alerted Discussions behave like a "Global Bookmark".',
    'Version' => '1.3.2',
    'RequiredApplications' => array('Vanilla' => '2.1.13'),   
    'RequiredTheme' => false,
	'MobileFriendly' => true,
    'HasLocale' => true,
    'SettingsUrl' => '/settings/DiscussionAlert',
    'SettingsPermission' => 'Garden.Settings.Manage',
	'RegisterPermissions' => array('Plugins.DiscussionAlert.View','Plugins.DiscussionAlert.Add'),   
    'Author' => "Roger Brahmson",
    'License' => 'GPLv2'
);
///////////////////////////////////////// 
class DiscussionAlert extends Gdn_Plugin {
   // Set up a menu option to filter for Alerts if Admin activated that option  
   // Note: This option requires the FilterDiscussions plugin
	public function Base_Render_Before($Sender, $Args) {
		if (!c('Plugins.DiscussionAlert.AddMenu', false)) {
			return; 
		}							
		// This option requires the FilterDiscussion plugin to be enabled
		if (!c('EnabledPlugins.FilterDiscussion', false)) {
			return; 
		}
		$Sender->Menu->AddLink("Menu", t('Alerts'),'/discussions/Filterdiscussion/?Alert=EQ:1&!msg=Alerted Discussions');
		return;
	} 
	///////////////////////////////////////////////
	// Get control to alter the alert state of a discussion
	public function PluginController_DiscussionAlert_Create($Sender, $Args) {
		// This function is supposed to be called by the link.  Here we performseveral validations that everything is as it should be...
		$DiscussionID = $Args[0];
		$Access = $this->CheckAccess($Sender,$Discussion, 'Add', true);
		if (!$Access) {
			$this->DieMessage('DA001 - Not allowed'); return;
		}
		if ($DiscussionID == NULL) {								//DiscussionID is required
			$this->DieMessage('DA002 - Missing Parameters'); return;
		}
		$Encode = intval($_GET['S']);
		if($Encode == $DiscussionID) {								//Encoded form cannot be in the clear
			$this->DieMessage('DA003 - Invalid Parameter');	return;
		} else {
			if ($Encode == null) {									//Encoding form is also required
				$this->DieMessage('DA004 - Invalid Parameter');	return;
			}
			$Simplekey = (367+Gdn::Session()->UserID);
			$D2 = $DiscussionID ^ $Simplekey;
			if  ($D2 != $Encode) {									//Encoded form does not belong to this DiscussionID
				$this->DieMessage('DA005 - Invalid Parameter:'.$Encode);
				//echo "<BR> DiscussionID, Simplekey, Encode, $D2:<br>";
				//var_dump($DiscussionID,$Simplekey,$Encode,$D2);
				//die(0);
				return;
			}
		}
		// Now we know that passed parameters are fine
		$DiscussionModel = new DiscussionModel();
		$Discussion = $DiscussionModel->GetID($DiscussionID);
		// If limited to specific category numbers and discussion is not listed then exit
		if	(!Gdn::session()->checkPermission('Vanilla.DiscussionAlert.Manage') &&
			c('Plugins.DiscussionAlert.CategoryNums')) {  //Limited category list?
			//echo "<br> Limited Category IDs. Current:".$Discussion->CategoryID."<br>";
			$CategoryNums = explode(',',Gdn::config('Plugins.DiscussionAlert.CategoryNums',','));
			if (!in_array($Discussion->CategoryID, $CategoryNums)) {	//Not in the list?
				/*echo "<br> CategoryID:" . $Discussion->CategoryID . " CategoryNums:";var_dump($CategoryNums);*/
				$this->DieMessage('DA006 - Not allowed (ref='.$CategoryID.")'");
				return;
			}
		}
		//All validations were passed, update the database 
		$Alert = $Discussion->Alert;		//echo "<br> Alert=".$Alert;
		if ($Alert) {						//Is the current discussion "Alerted"? 
			$Newvalue = false;				//Then the new state if off
		} else {							
			$Newvalue = true;
		}
		Gdn::sql() ->update('Discussion')
			->set('Alert', $Newvalue) ->where('DiscussionID', $DiscussionID)
			->put();
		// Refresh the screen icons/links with the newly updated value 
		$Sender->JsonTarget('#Alert' . $DiscussionID . ' .Alertcss', 
			$this->SetAlertLink($DiscussionID,$Newvalue,true), 'ReplaceWith');
		if ($Newvalue) {						//Is the current discussion "Alerted"? 
			$Feedback = t('Alert set on discussion "').substr($Discussion->Name,0,30).'..."';
		} else {							
			$Feedback = t('Alert removed from discussion "').substr($Discussion->Name,0,30).'..."';
		}
		echo $Feedback;						//Only shows it authorized user uses browser right-click to open link ina new tab/window
		$Sender->InformMessage($Feedback);	//That's the normal feedback
		// The following render is the only one that managed to refresh the screen with the hijacked style.
		$Sender->Render('Blank', 'Utility', 'Dashboard');
		//$Sender->jsonTarget('', '', 'Refresh');			//This did not work...
	}
	///////////////////////////////////////////////
	// Set the CSS
	public function assetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('DiscussionAlert.css', 'plugins/DiscussionAlert');
    }
	///////////////////////////////////////////////
	// Sort Alerts before the rest
	public function discussionModel_beforeGet_handler($sender, $args) {
		Gdn::sql()->orderBy('Alert', 'desc');  //'asc');
		Gdn::sql()->orderBy('Postitnote', 'desc');  //'asc');
		//Gdn::sql()->orderBy('Name', 'asc');
		Gdn::sql()->orderBy('DateLastComment', 'desc');
	}
	///////////////////////////////////////////////
	public function DiscussionsController_BeforeDiscussionContent_Handler($Sender) {
	   $Discussion = $Sender->EventArguments['Discussion'];
	   $this->PlaceButton($Discussion,false);
	   return;
	}
	///////////////////////////////////////////////
	Public function CategoriesController_BeforeDiscussionContent_Handler($Sender) {
	   $Discussion = $Sender->EventArguments['Discussion'];
	   $this->PlaceButton($Discussion,false);
	   return;
	}
	///////////////////////////////////////////////
	// If user can can change the alert state then add the option to the gear menu
	public function discussionsController_discussionOptions_handler($Sender, $Args) {
		$this->AddToGear($Sender, $Args);
	}
	// Add Alert Button to bottom of form
	public function CategoriesController_DiscussionOptions_Handler($Sender, $Args) {
		$this->AddToGear($Sender, $Args);
	}
	///////////////////////////////////////////////
	// add alert button to discussion body
	public function discussionController_DiscussionInfo_Handler($Sender, $Args) {
		$Discussion = $Sender->EventArguments['Discussion'];
		$this->PlaceButton($Discussion,false);
		//echo "<br><b>".__FUNCTION__." testing</br>";	
	}
	///////////////////////////////////////////////
	// Place Alert Button on the last comment
	public function DiscussionController_CommentInfo_Handler($Sender, $Args) {
		$Discussion = $Sender->EventArguments['Discussion'];
		$LastCommentID=$Discussion->LastCommentID;
		$Comment = $Sender->EventArguments['Comment'];
		$CommentID = $Comment->CommentID;
		//echo "<br><b>".__FUNCTION__." testing</br>";
		//echo '<br>LastCommentID:'.$LastCommentID.' CommentID:'.$CommentID.'<br>';
		if ($CommentID == $LastCommentID) {
			$this->PlaceButton($Discussion,false);
		}
	}
	//////////////////////////////////////////////
	//Define the additional column used to hold the alert state
	public function Structure() {
		Gdn::database()->structure()
            ->table('Discussion')
            ->column('Alert', 'tinyint', 0)
            ->set();
	}
	///////////////////////////////////////////////
	// Plugin Setup 
	public function Setup() {
		$this->Structure();
		// Initialize plugin defaults
		if (!c('Plugins.DiscussionAlert.AddMenu')) {
            saveToConfig('Plugins.DiscussionAlert.AddMenu', false);  //Add "Alerts" menu
        }
		if (!c('Plugins.DiscussionAlert.CategoryNums')) {
			saveToConfig('Plugins.DiscussionAlert.CategoryNums', '');  //Blank out limit to selected category ids 
        }
		if (!c('Plugins.DiscussionAlert.PermissionView')) {
			saveToConfig('Plugins.DiscussionAlert.PermissionView', true); //Default will require mermission to see alerts
		}
		if (!c('Plugins.DiscussionAlert.PermissionAdd')) {
			saveToConfig('Plugins.DiscussionAlert.PermissionAdd', true); //Default will require mermission to add alerts
        }
	}
   ///////////////////////////////////////////////
    public function settingsController_DiscussionAlert_create ($Sender, $Args) {
        $Sender->permission('Vanilla.DiscussionAlert.Manage');
        $Sender->setData('Title', t('DiscussionAlert Settings'));
        $Sender->addSideMenu('dashboard/settings/plugins');
		$AlertFilter = '<br>(Note: This option uses the currently enabled FilterDiscussion plugin)';
		if (!c('EnabledPlugins.FilterDiscussion', false)) {
			$AlertFilter = '<br><b>Note:</b>This option will be ignored until you enable the FilterDiscussion plugin.';
		}
		// Get all categories.
		$Categories = CategoryModel::categories();
		// Remove the "root" categorie from the list.
		unset($Categories[-1]);
		$PermissionMsg = '<b>Note:</b>Additionally, users won\'t be able to add/remove alert unless they also have permission to edit the discussion.';
        $configurationModule = new ConfigurationModule($Sender);
        $configurationModule->initialize( array(
			/*- Option to require mermissions to see alerts-*/
			'Plugins.DiscussionAlert.PermissionView' => array(
			  'Control' => 'CheckBox',
			  'LabelCode' => '<B>Require premission in "Roles and Permissions" to view Alerts</B> (Otherwise alerts indicators are viewable to all)',
			  'Items' => $PermissionView,
			  'Description' => '<B>Permission Requirement Settings</B>',
			  'Default' => true),
			 /*- Option to require mermissions to add/delete alerts-*/
			'Plugins.DiscussionAlert.PermissionAdd' => array(
			  'Control' => 'CheckBox',
			  'LabelCode' => '<B>Require premission in "Roles and Permissions" to add/remove alerts</B>',
			  'Items' => $PermissionAdd,
			  /*'Description' => '<b>________________________________________________</b>',*/
			  'Default' => true),
			/*- Option to add Alerts menu-*/
			'Plugins.DiscussionAlert.AddMenu' => array(
			'Control' => 'CheckBox',
			'Description' => $PermissionMsg.'<br><br><b>Add "Alerts" menu</b> ',
			'LabelCode' => 'If checked a menu option will show users the list of discussions makred with Alerts'.$AlertFilter,
			'Default' => false),		
			/*- Limit to specific Category Numbers-*/
			'Plugins.DiscussionAlert.CategoryNums' => array(
			  'Control' => 'CheckBoxList',
			  'LabelCode' => '<b>________________________________________________</b>',
			  'Items' => $Categories,
			  'Description' => '<b>Limit Alerting to discussions in specific categories:</b> (no selecation enables all categories)',
			  'Options' => array('ValueField' => 'CategoryID', 'TextField' => 'Name'),
        )));
        $configurationModule->renderAll();
    }
	///////////////////////////////////////////////
	// Check access rights to view or set alerts
	private function CheckAccess($Sender,$Discussion, $Request = 'View', $Debug = false) {
		// Require signed on users
		$Session = Gdn::Session(); 
		if (!$Session->IsValid()) {
			$Msg = "Must be logged on";
			echo $Msg;
			return false;
		}
		if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__.' Request:'.$Request."<BR>";
		// Admins can do anything
		if ($Session->checkPermission('Garden.Settings.Manage')) {
			if ($Debug) echo "<br>".__FUNCTION__.' '.__LINE__."Admin.<BR>";
		  	return true;	//Admins are kings
		}
		//Verify user is allowed to view discussions in the discussion category
		if (!$Session->CheckPermission('Vanilla.Discussions.View', true, 'Category', $Discussion->PermissionCategoryID)) {
			if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__."<BR>";
			return false;
		}
		//Additionally, reject the request if config setting requires permission to View note but permission not set
		if	(!(c('Plugins.DiscussionAlert.PermissionView',true)) == false) { 
			if (!$Session->checkPermission('Plugins.DiscussionAlert.View')) {
				if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__."<BR>";
				return false;
			}
		}
		//If request is for add/remove then verify this is allowed
		if ($Request == 'Add') {
			if	(!(c('Plugins.DiscussionAlert.PermissionAdd',false) == false) && 
				(!$Session->checkPermission('Plugins.DiscussionAlert.Add'))) {
				if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__."<BR>";
				return false;
			}
		}
		//Now check whether the permission is limited to specific categories]
		$Catnums = c('Plugins.DiscussionAlert.CategoryNums');
		if ($Debug) {
				echo __LINE__."Limited Category IDs. Current:".$Discussion->CategoryID."<br>Catnums:";var_dump($Catnums);
		}
		if ($Catnums != "") {  //Limited category list?
			if ($Debug) echo "<br>".__FUNCTION__.'  '.__LINE__." CategoryID:" . $Discussion->CategoryID;
			if (!in_array($Discussion->CategoryID, $Catnums)) {	//Not in the list?
				if ($Debug) {
					echo "<br>".__LINE__." CategoryID:" . $Discussion->CategoryID . " Catnums:";
					var_dump($Catnums);
				}
				if ($Debug)	echo "<br>".__LINE__."<BR>";
				return false;
			}
		}
		if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__."<BR>";
		return true;
		//
	}
	///////////////////////////////////////////////
	// Conditionaly place an Alert "button"  
	private function PlaceButton($Discussion, $Debug = false) {
		$Alert = $Discussion->Alert;
		$DiscussionID = $Discussion->DiscussionID;
		if ($Debug) {
			echo "<BR>1.1..$Alert=";var_dump($Alert);
			echo "<BR>"; 
		}
		// If not allowed to view alert state then there is nothing to do
		$Access = $this->CheckAccess($Sender,$Discussion, 'View', true);
		if (!$Access) return;
		//
		//if (!Gdn::session()->checkPermission('Plugins.DiscussionAlert.View')) return;	
		$Link =false;																	//Defult to view only mode
		//if (Gdn::session()->checkPermission('Plugins.DiscussionAlert.Add')) $Link =true;//Change to clickable mode
		$Access = $this->CheckAccess($Sender,$Discussion, 'Add', true);
		if ($Access) $Link =true;														//Change to clickable mode
		echo $this->SetAlertLink($DiscussionID, $Alert, $Link, $Debug);					//Set the actual button
		return;
		
		// If limited to specific category numbers and discussion is not listed then exit
		if	(!Gdn::session()->checkPermission('Vanilla.DiscussionAlert.Manage') &&
			c('Plugins.DiscussionAlert.CategoryNums')) {  //Limited category list?
			if ($Debug) {
			  echo "<br> Limited Category IDs. Current:".$Discussion->CategoryID."<br>";
			}
			$CategoryNums = explode(',',Gdn::config('Plugins.DiscussionAlert.CategoryNums',','));
			if (!in_array($Discussion->CategoryID, $CategoryNums)) {	//Not in the list?
				if ($Debug) {
					echo "<br> CategoryID:" . $Discussion->CategoryID . " CategoryNums:";
					var_dump($CategoryNums);
				}
				$Link =false;	//Show alert state of historically included categories (but no change is allowed)
			}
		}
		echo $this->SetAlertLink($DiscussionID, $Alert, $Link, $Debug);					//Set the actual button
	}	
	///////////////////////////////////////////////
   // Place the Alert button (with or without clickable link and the appropriate Hijack)
	private function SetAlertLink($DiscussionID, $Alerted, $Link=false, $Debug = false) {
		if ($Alerted) { 							//Current state 
			$Hijack = 'Hijack Alertcss on';
			$Tip = T('Remove&nbsp;Alert');
			$Informcss = 'Informcsson';
			$Informtip = $Tip;
		} else {									//Alert not set
			$Hijack = 'Hijack Alertcss';
			$Tip = T("Set&nbsp;Alert");
			$Informcss = 'Informcssoff';
			$Informtip = "";						//If not allowed to change then no need for tooltip;
		}
		$Simplekey = (367+Gdn::Session()->UserID);	//VERY simple encoding of the Discussion ID 
		$Encode = $DiscussionID ^ $Simplekey;		//Will be verified when a change is requested
		$Url = '/dashboard/plugin/DiscussionAlert/'.$DiscussionID.'?S='.$Encode;	//Pass both Discussion ID and its encoding
		if (!$Link) {								//User pnly allowed to view alert state
			return wrap(T('Alert'),'div id=Alert'.$DiscussionID.' class='.$Informcss.' Title='.$Informtip); //"'.t('Alerted Discussion').'"');
		} else {									//User allowed to set/reset alerts,so place the links with Hijack
			return wrap(Anchor(T('Alert'), $Url, $Hijack),'div id=Alert'.$DiscussionID.' Title='.$Tip);
		}
	}
	///////////////////////////////////////////////
	// Function to add the Alert toggle function into the gear
	private function AddToGear($Sender, $Args) {
		if (!Gdn::session()->checkPermission('Plugins.DiscussionAlert.Add')) {
			return;
		}
		$Discussion = $Sender->EventArguments['Discussion'];
		// If limited to specific category numbers and discussion is not listed then exit
		if	(!Gdn::session()->checkPermission('Vanilla.DiscussionAlert.Manage') &&
			c('Plugins.DiscussionAlert.CategoryNums')) {  //Limited category list?
			//echo "<br> Limited Category IDs. Current:".$Discussion->CategoryID."<br>";
			$CategoryNums = explode(',',Gdn::config('Plugins.DiscussionAlert.CategoryNums',','));
			if (!in_array($Discussion->CategoryID, $CategoryNums)) {	//Not in the list?
				/*echo "<br> CategoryID:" . $Discussion->CategoryID . " CategoryNums:";var_dump($CategoryNums); */
				return;
			}
		}
		//	Construct the link and add to the gear                  
		$Discussion = $Sender->EventArguments['Discussion'];
		$DiscussionID = $Discussion->DiscussionID;
		$Text='Togglae Alert State';
		$Simplekey =(367+Gdn::Session()->UserID); 
		$Encode = $DiscussionID ^ $Simplekey;
		$Url = '/dashboard/plugin/DiscussionAlert/'.$DiscussionID.'?S='.$Encode;
		$Sender->Options .= '<li>'.anchor(t($Text), $Url,'Hijack Optionscss').'</li>';  //Change Optionscss if you dislike the icon in the menu
	}
	///////////////////////////////////////////////
   	// Terminate with a severe message
	public function DieMessage($Message) {
		echo "<P>DiscussionAlert Plugin Message:<H1><B>".$Message."<N></H1></P>";
		throw new Gdn_UserException($Message);
	}
} 
