<?php
$PluginInfo['TopBookmarks'] = array(
    'Name' => 'Top Bookmarks',
	'Description' => 'Provides views of the most bookmarked discussions and global bookmark count in the meta area.',
    'Version' => '1.1.4',
    'RequiredApplications' => array('Vanilla' => '2.1'),  
    'RequiredTheme' => FALSE,
	'MobileFriendly' => TRUE,
    'HasLocale' => TRUE,
    'SettingsUrl' => '/settings/TopBookmarks',
    'SettingsPermission' => 'Garden.Settings.Manage',
	'RegisterPermissions' => array('Plugins.TopBookmarks.View','Plugins.TopBookmarks.ViewMarkers'), 
    'Author' => "Roger Brahmson",
	'License' => "GNU GPL2"
);
///////////////////////////////////////// 
class TopBookmarks extends Gdn_Plugin {
	// Indicates whether or not we are in the bookmarked view view
  private $CustomView = FALSE;
  
  ///////////////////////////////////////////////
  // Pagination support
  public function DiscussionsController_TopBookmarks_Create($Sender, $Args = array()) {
	$Page = '{Page}';
	$Debug=false;
	if (!$this->Accesscheck('View',0,$Debug)) {	
		$this->DieMessage('TopBookmarks'.__LINE__.' Access Not allowed');
		return;
	}
	$this->CustomView = TRUE;
    $Sender->View = 'Index';
	$Parameters = '';
	foreach ($_GET as $key => $value) {		
		if ($key == "!msg") {	
			Gdn::Controller()->Title(t($value));
		}
		$Parameters=$Parameters."&".trim($key)."=".trim($value);
	}
	$Sender->SetData('_PagerUrl', 'discussions/TopBookmarks/'.$Page.$Parameters);
    $Sender->Index(GetValue(0, $Args, 'p1'));
  }
  ///////////////////////////////
  // Hook is for Vanilla 2.2.  Is needed as Vanilla doesn't do the job internally
  public function DiscussionModel_AfterBookmark_Handler($Sender,$Args) {
	if(version_compare(APPLICATION_VERSION, '2.2', '<')) {
		echo __FUNCTION__.__LINE__.' Hook unexpected in this version of Vanilla.';
		return;	//Just to be on the safe side
    } 
	$Discussion = $Sender->EventArguments['Discussion'];
	//$UserID = $Sender->EventArguments['UserID'];
	$Bookmarked = $Sender->EventArguments['Bookmarked'];
	$DiscussionID = $Discussion->DiscussionID;
	$CountBookmarks = $Discussion->CountBookmarks;
	$FunctionCountBookmarks = $Sender->BookmarkCount($DiscussionID);
	//$Msg= 	'Note:AfterBookmark_Handler'.__LINE__.' DiscussionID:'.$DiscussionID.' Bookmarked:'.$Bookmarked.
			' Count:'.$CountBookmarks.' FCount:'.$FunctionCountBookmarks.' Version:'.APPLICATION_VERSION;
	//		  ->set('Postitnote','For Debugging:'.$Msg)		
	$Sender->SQL->update('Discussion')
		  ->set('CountBookmarks', $FunctionCountBookmarks)
		  ->where('DiscussionID', $DiscussionID)
		  ->put();
	return;
  }
  ///////////////////////////////
  // Hook is for Vanilla 2.1.  Not needed as Vanilla does the job internally
  public function DiscussionModel_AfterBookmarkDiscussion_Handler($Sender,$Args) {
	$Discussion = $Sender->EventArguments['Discussion'];
	$State = $Sender->EventArguments['State'];
	$DiscussionID = $Discussion->DiscussionID;
	$CountBookmarks = $Discussion->CountBookmarks;
	$FunctionCountBookmarks = $Sender->BookmarkCount($DiscussionID);
	//$Msg= 'Note:AfterBookmarkDiscussion_Handler'.__LINE__.' DiscussionID:'.$DiscussionID.' State:'.$State.' Count:'.$CountBookmarks.' FCount:'.$FunctionCountBookmarks.' Version:'.APPLICATION_VERSION;
	if(version_compare(APPLICATION_VERSION, '2.1', '>')) {
		echo __FUNCTION__.__LINE__.' Hook unexpected in this version of Vanilla.';
		return;	//Just to be on the safe side
    }
	//		  ->set('Postitnote','For Debugging:'.$Msg)
	$Sender->SQL->update('Discussion')
		  ->set('CountBookmarks', $FunctionCountBookmarks)
		  ->where('DiscussionID', $DiscussionID)
		  ->put();
	//}
  }
  //
  ///////////////////////////////
  public function DiscussionsController_Render_Before($Sender) {
    if($this->CustomView) {
       $Sender->SetData('CountDiscussions', Gdn::Cache()->Get('TopBookmarks-Count'));
    }
  }
  ///////////////////////////////
  // Main processing of the custom view
	public function DiscussionModel_BeforeGet_Handler($Sender) {
		$Debug=false;
		if($this->CustomView != TRUE)  return; 
		if (!$this->Accesscheck('View',0,$Debug)) {
			$this->DieMessage('TopBookmarks'.__LINE__.' Access Not allowed');
			return;
		}
		//Create a new filtered discussion list
		Gdn::Controller()->Title(t('Top Bookmarked Discussions'));
		$MinOnList = c('Plugins.TopBookmarks.MinOnList',1);
		$Backward = c('Plugins.TopBookmarks.Backward',0);
		$Backstring = $this->GetBackward($Backward,$Debug);
		$Title = 'TopBookmarks '.__LINE__.
		' Backstring:'.$Backstring.
		' Backward:'.$Backward.' MinOnList:'.$MinOnList.'  ';
		if ($Debug) Gdn::Controller()->Title($Title);
		$Sender->SQL->Where('d.CountBookmarks >=',$MinOnList);
		if ($Backstring != '0' ) {
			$Sender->SQL->Where('d.DateLastComment >=',$Backstring);
		}
		//Override Discussion Model first sorting order (code contributed by Shadowdare https://vanillaforums.org/discussion/comment/188935#Comment_188935)
		$GetPrivateObject = function &($Object, $Item) {
			$Result = &Closure::bind(function &() use ($Item) {
				return $this->$Item;
			}, $Object, $Object)->__invoke();
			return $Result;
		};
		$OrderBy = &$GetPrivateObject($Sender->SQL, '_OrderBys');
		//echo __LINE__.var_dump($OrderBy);
		$OrderBy[0] = 'd.CountBookmarks desc';	//Force our own sorting order
	}
  ///////////////////////////////////////////////
 public function categoriesController_afterCountMeta_handler($Sender) {
	$this->Listinline($Sender,'Meta',false);
 }
 ///////////////////////////////////////////////
 public function discussionsController_afterCountMeta_handler($Sender,$Args) {
	 
	//echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
	/*//// T E S T Structure upgrade (assumes Discussion Note lugin)
	if(version_compare(APPLICATION_VERSION, '2.1', '>')) {
		$Msg = __FUNCTION__.__LINE__.' Version:'.APPLICATION_VERSION;
		//Gdn::controller()->informMessage($Msg);
		$Discussion = $Sender->EventArguments['Discussion'];
		$DiscussionID = $Discussion->DiscussionID;
		$Postitnote = $Discussion->Postitnote;
		if ($DiscussionID == 607 && $Postitnote == '769') {
			//echo '<br><b>DiscussionID:</b>'.$DiscussionID.' '.$Msg;
			//$this->Structure();
			$this->CountHighhestBookmark($Sender,0);
		}
	}
	*//////////////
	
	$this->Listinline($Sender,'Meta',false);
 }
 ///////////////////////////////////////////////
 /*public function categoriesController_afterDiscussionTitle_handler($Sender) {
	$this->Listinline($Sender,'Meta',false);
	//echo "<br><b>".__FUNCTION__." testing</br>";	
 }*/
 
 ///////////////////////////////////////////////
 /*public function DiscussionsController_BeforeDiscussionContent_Handler($Sender) {
		$this->Listinline($Sender,'Meta',false);
 }
 */
///////////////////////////////////////////////
 
 /*
 public function discussionsController_afterDiscussionTitle_handler($Sender) {
		$this->Listinline($Sender,'Meta',false);
 }
 */
///////////////////////////////////////////////
 public function discussionController_DiscussionInfo_Handler($Sender, $Args) {
 	$this->Listinline($Sender,'Title',false);;	
 }
///////////////////////////////////////////////
// Function to check access to reader list
 private function Accesscheck($Permission, $Optioncheck = FALSE ,$Debug = FALSE) {
	if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' '.$CheckCategories.' Called by: ' . debug_backtrace()[1]['function'].'<br>';
	//Check Setting options if so requestd by caller
	if ($Optioncheck) {
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Optioncheck:'.$Optioncheck;
		if (!c('Plugins.TopBookmarks.'.$Optioncheck,FALSE)) {
			if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' **DENIED ACCESS';
			return FALSE;
		}
	}
	if	(Gdn::session()->checkPermission('Garden.Settings.Manage')) return TRUE; //Admins are kings
	if ($Permission == 'View'){
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Permission:'.$Permission;
		if (!c('Plugins.TopBookmarks.Needpermission')) return TRUE;
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__;
		if (Gdn::session()->checkPermission('Plugins.TopBookmarks.View')) return TRUE;
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__;
	} elseif ($Permission == 'Bookmarkers'){
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Permission:'.$Permission;
		if (!c('Plugins.TopBookmarks.ViewMarkers')) return TRUE;
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__;
		if (Gdn::session()->checkPermission('Plugins.TopBookmarks.ViewMarkers')) return TRUE;
		if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__;
	} else {
		echo '<h1>Bad Parameter '.__LINE__.' Parameter:'.$Permission.' </h1>';
		return FALSE;
	}
	if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' **DENIED ACCESS';
	return FALSE;
 }
	///////////////////////////////////////////////
  public function GetBackward($Backward,$Debug = FALSE) {
	if ($Backward != 0) {
		$Backdate = date_create();
		$Interval = $Backward.' days';
		date_sub($Backdate,date_interval_create_from_date_string($Interval));
		$Backstring = date_format($Backdate,"Y-m-d");
	} else {
		$Backstring = '0';
	}
	//if ($Debug) echo '<div class=Highbookmark><br>'.' Backward:'.$Backward.' Backstring:'.$Backstring.' </div>';
	return $Backstring;
	}
///////////////////////////////////////////////
// Function to list the top bookmarks 
 private function Listinline($Caller, $Section, $Debug = FALSE) {
	//if ($Debug == TRUE) echo "<br><b>".__FUNCTION__.' '.__LINE__.' '.$Section.' Called by: ' . debug_backtrace()[1]['function'].'<br>';	
	$Discussion = $Caller->EventArguments['Discussion'];
	if (!$this->Accesscheck('View','Listinline',$Debug)) return;
	if ($Section == "Meta") {
	} elseif ($Section == "Title") { 					
	} else {
		$this->DieMessage('BV006 - Invalid Parameter:'.$Section.' in line'.__LINE__);	
		return;
	}	
	// Get the bookmarked count for the specific discussion
	$nbsp = "&nbsp;";
	$Backward = c('Plugins.TopBookmarks.Backward',0);
	$Backstring = $this->GetBackward($Backward,$Debug);
	if ($Debug) 
		echo '<div class=Highbookmark><br>'.__LINE__.
		'<br> Backstring:'.$Backstring.
		'<br> Backward:'.$Backward.
		'<br> CountBookmarks:'.$Discussion->CountBookmarks.
		'<br> DateInserted:'.$Discussion->DateInserted.
		'<br> DateLastComment:'.$Discussion->DateLastComment.' </div>';
	$BookmarkCount=$Discussion->CountBookmarks;
	$MinOnList = c('Plugins.TopBookmarks.MinOnList',2);
	if ($BookmarkCount<$MinOnList) return;
	$CssTitle = t('Total number of bookmarks for this topmarked discussion');
	//if (c('Plugins.TopBookmarks.ShowLinks')) {
	if ($this->Accesscheck('Bookmarkers','ShowLinks',$Debug)) {
		$Url = $this->GetBookMarkersFormUrl($Discussion->DiscussionID, $Debug);
		$Anchor = anchor('★', $Url,'Hijack Popup HighbookmarkStar');
	} else {
		$Anchor = wrap('★','HighbookmarkStar');
	}
	if ($Backstring == '0' || ($Discussion->DateLastComment >= $Backstring)) {
		$BookmarkTag = '<span title="'.$CssTitle.'" class="Meta Mitem Highbookmark'.$Section.'"><b>&nbsp;</b>'.t('Top Bookmarked ').'-&nbsp;&nbsp;'.$BookmarkCount.'&nbsp;<span class="Meta Mitem HighbookmarkStar"> '.$Anchor.'</span>&nbsp;</span>';
		
		echo wrap($BookmarkTag,'span id=Topbookmark'.$Discussion->DiscussionID,' class=HighbookmarkMetaContainer'.$Section);
		

	}
   }
///////////////////////////////////////////////
	// Get control to display the list of the discussion bookmarkers 
	// This is less performance intensive as this is done on an individual discussion upon a request through the gear dropdown menu
	public function PluginController_TopBookmarks_Create($Sender, $Args) {
		// This function is supposed to be called by the link.  Here we perform several validations that everything is as it should be...
		$DiscussionID = $Args[0];
		if ($DiscussionID == NULL) {								//DiscussionID is required
			$this->DieMessage('BV002 - Missing Parameters'); return;
		}
		$Encode = intval($_GET['S']);
		if($Encode == $DiscussionID) {								//Encoded form cannot be in the clear
			$this->DieMessage('BV003 - Invalid Parameter');	return;
		} else {
			if ($Encode == null) {									//Encoding form is also required
				$this->DieMessage('BV004 - Invalid Parameter');	return;
			}
			$Simplekey = (349+Gdn::Session()->UserID);
			$D2 = $DiscussionID ^ $Simplekey;
			if  ($D2 != $Encode) {									//Encoded form does not belong to this DiscussionID
				$this->DieMessage('BV005 - Invalid Parameter:'.$Encode);
				//echo "<BR> DiscussionID, Simplekey, Encode, $D2:<br>";
				//var_dump($DiscussionID,$Simplekey,$Encode,$D2);
				//die(0);
				return;
			}
		}
		// Passed parameters seems fine so point to the specific discussion
		$DiscussionModel = new DiscussionModel();
		$Discussion = $DiscussionModel->GetID($DiscussionID);
		//Validate user has access to reader list in this very discussion
		if (!$this->Accesscheck('Bookmarkers',0,$Debug)) {
			$this->DieMessage('BV006 - Not allowed'); 
			return;
		}
		//All validations were passed, show the bookmarkers list screen
		$this->PopupBookmarkersList($Sender,$Discussion);
		// The following render is the only one that managed to refresh the screen with the hijacked style.
		$Sender->Render('Blank', 'Utility', 'Dashboard');
	}
/////////////////////////////////////////
	// Count highest number of bookmrks
	private function CountHighhestBookmark($Caller,$Debug = FALSE) {
		GDN::sql()->Reset();
		if(version_compare(APPLICATION_VERSION, '2.1', '>')) {
			$result = Gdn::sql()
					->Select("ud.DiscussionID, COUNT(ud.Bookmarked) MaxBookmarked, ud.UserID, ud.DateLastViewed")
					->From('UserDiscussion ud')
					->where('ud.Bookmarked >',0)
					->GroupBy('ud.DiscussionID')
					->OrderBy('COUNT(ud.Bookmarked)','DESC')
					->Limit(1)
					->Get();
		//->where('ud.DateLastViewed >',0)
		} else {					//Get the topbookmark fromthdiscussion table
			$result = Gdn::sql()
					->Select("d.CountBookmarks MaxBookmarked")
					->From('Discussion d')
					->where('d.CountBookmarks >',0)
					->OrderBy('d.CountBookmarks','DESC')
					->Limit(1)
					->Get();
		}
		$Rowcount = count($result);
		if ($Rowcount == 0) {
			$Msg = __FUNCTION__.__LINE__.' '.t('Error: Could not detrmine current highest count of bookmarks for existing discussions.');
			$Caller->InformMessage($Msg,'DoNotDismiss');
			echo wrap($Msg,'div'); 
			return 987654321;
		}
		 
		foreach($result as $Entry){
			//echo '<br>'.__FUNCTION__.__LINE__;
			//var_dump($Entry);
			$MaxBookmarked = $Entry->MaxBookmarked;		
			if ($Debug) 
				echo wrap('Highest Bookmark:'.$MaxBookmarked,'h1');
		}
		if (!$MaxBookmarked) {
			$Msg = __FUNCTION__.__LINE__.' '.t('Error: Could not detrmine current highest count of bookmarks for existing discussions.'); 
			$Caller->InformMessage($Msg,'DoNotDismiss');
			echo wrap($Msg,'div'); 
			return 987654321;
		}
		
		return $MaxBookmarked;
	}
/////////////////////////////////////////
	// Display the bookmarkers list form
	private function PopupBookmarkersList($Sender,$Discussion, $Debug = FALSE) {
		$Limit = 200;  //Set this to the max number of bookmarkers to show on the popup form
		$Readerlist = Gdn::SQL()->Select('UserID,Bookmarked,DateLastViewed')
						->From('UserDiscussion')
						->Where('DiscussionID', $Discussion->DiscussionID)
						->Where('Bookmarked <>', 0)
						->orderBy('DateLastViewed', 'desc')
						->limit($Limit)
						->Get();
		$Readercount = count($Readerlist);
		if ($Debug) $Sender->InformMessage($Readercount.__FUNCTION__.__LINE__);
		if ($Readercount == 0) {
			$Sender->InformMessage(t('This discussion has not been bookmarked'),'DoNotDismiss');
			return;
		}
		
		$Sender->setData('Discussion', $Discussion);
		$Sender->setData('Limit', $Limit);
		$Sender->setData('Title', t('Users that bookmarked the discussion'));
		$Sender->setData('DiscussionName', $Discussion->Name);
		$Sender->setData('Author', $Discussion->InsertUserID);
		$Sender->setData('Readerlist', $Readerlist);
		$Sender->setData('Readercount', $Readercount);
		//
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
		//$Sender->Form->setData($ConfigurationModel->Data);
		$Postback=$Sender->Form->authenticatedPostBack();
		//if ($Debug) $Sender->InformMessage($Postback.__FUNCTION__.__LINE__);
		if(!$Postback) {					//Before form submission
			//if ($Debug) $Sender->InformMessage("=No Postback=>".__FUNCTION__.__LINE__);	
			$Sender->Form->setValue('Readerlist', $Readerlist);
			$Sender->Form->setFormValue('Readerlist', $Readerlist);
		} else {								//After form submission
			return;
		}
		//
		//if ($Debug) $Sender->InformMessage("".__FUNCTION__.__LINE__);
		$View = dirname(__FILE__).DS.'views/topbookmarks.php';   
		//if ($Debug) $Sender->InformMessage($View.__LINE__);
		$Sender->render($View);
		//if ($Debug) $Sender->InformMessage(__LINE__);
	}
/////////////////////////////////////////
/////////////////////////////////////////
 public function Base_AfterDiscussionFilters_Handler($Sender) {
	//Add sidepanel link
	$Debug=false;
	if ($this->Accesscheck('View','ShowPanelLink',$Debug)) {
		$MenuName=$this->GetMenuName($Sender,'Saved',$Debug);
		echo '<li class="Activities ' . ($this->CustomView === TRUE ? ' Active' : '') . '">' .
       Anchor(Sprite('SpUnansweredQuestions') . ' ' . $MenuName,
              '/discussions/TopBookmarks', $MenuName) . '</li>';
	}
 }
/////////////////////////////////////////
 public function Base_Render_Before($Sender) {
	//Add Topbar menu link
	$Debug=false;
	if ($this->Accesscheck('View','AddToMenu',$Debug)) {
		$MenuName=$this->GetMenuName($Sender,'Saved',$Debug);
		$Sender->Menu->AddLink("Menu", $MenuName,'/discussions/TopBookmarks');
	}
	return;
 }
 
///////////////////////////////////////// 
   public function OnDisable() {
	   RemoveFromConfig('Plugins.TopBookmarks.IncompleteSetup');
   }
///////////////////////////////////////// 
   public function SettingDefaults($Sender,$CallType = '') {
	   //Set default confi options
	   $Debug = false;
	   $Needpermission = c('Plugins.TopBookmarks.Needpermission',TRUE);
	   SaveToConfig('Plugins.TopBookmarks.Needpermission',$Needpermission);
	   
	   $ViewMarkers = c('Plugins.TopBookmarks.ViewMarkers',TRUE);
	   SaveToConfig('Plugins.TopBookmarks.ViewMarkers',$ViewMarkers);
	   
	   $Backward = c('Plugins.TopBookmarks.Backward',0);
	   SaveToConfig('Plugins.TopBookmarks.Backward',$Backward);
	   
	   $MinOnList = c('Plugins.TopBookmarks.MinOnList',0);
	   if (!$MinOnList) {
		   $MinOnList = $this->CountHighhestBookmark($Sender,$Debug);
	   }
	   SaveToConfig('Plugins.TopBookmarks.MinOnList',$MinOnList);
	   
	   $AddToMenu = c('Plugins.TopBookmarks.AddToMenu',FALSE);
	   SaveToConfig('Plugins.TopBookmarks.AddToMenu',$AddToMenu);
	   
	   $ShowPanelLink = c('Plugins.TopBookmarks.ShowPanelLink',FALSE);
	   SaveToConfig('Plugins.TopBookmarks.ShowPanelLink',$ShowPanelLink);
	   if ($CallType != 'Setup') {
			$MenuName=$this->GetMenuName($this,'Default',$Debug);
	   } else {
			$MenuName=$this->GetMenuName($this,'Saved',$Debug);
		}
		SaveToConfig('Plugins.TopBookmarks.MenuName',$MenuName);

	   $Listinline = c('Plugins.TopBookmarks.Listinline',TRUE);
	   SaveToConfig('Plugins.TopBookmarks.Listinline',$Listinline);
	   
	   $ShowLinks = c('Plugins.TopBookmarks.ShowLinks',FALSE);
	   SaveToConfig('Plugins.TopBookmarks.ShowLinks',$ShowLinks);
	   
	   $ShowGear = c('Plugins.TopBookmarks.ShowGear',FALSE);
	   SaveToConfig('Plugins.TopBookmarks.ShowGear',$ShowGear);
   }
 ///////////////////////////////////////// 
   public function Structure() {
	//Database structure/updates
		if(version_compare(APPLICATION_VERSION, '2.2', '<')) return;	//No structure change for 2.1
		$Msg = 'Top Bookmarks Plugin: '.t('A one time database update is taking place during plugin setup for Vanilla Version ').APPLICATION_VERSION;
		Gdn::controller()->informMessage($Msg); 
		echo wrap(wrap($Msg,'H3 class="SettingNote"'),'BR');
		$prefix = Gdn::database()->DatabasePrefix;
		$sql = "UPDATE ".$prefix."Discussion d, 
					(SELECT ud.DiscussionID, SUM(ud.Bookmarked) AS 'CountBookmarked' 
					FROM ".$prefix."UserDiscussion ud
					GROUP BY ud.DiscussionID
					) AS ud
					SET d.CountBookmarks = ud.CountBookmarked
					WHERE d.DiscussionID = ud.DiscussionID";
		$result = Gdn::sql()->query($sql);
		$Msg = 'Top Bookmarks Plugin: '.t('The one time database update completed ');
		Gdn::controller()->informMessage($Msg); 
		echo wrap(wrap($Msg,'H3 class="SettingNote"'),'BR');
   }
 ///////////////////////////////////////// 
   public function Setup() {
	   $this->Structure();
	   //Set default config options
	   $this->SettingDefaults($this,'Setup');
   }
///////////////////////////////////////////////

    public function settingsController_TopBookmarks_create ($Sender, $Args) {
        $Sender->permission('Garden.Settings.Manage');
		$Debug = false;
		$Sender->addCssFile('TopBookmarks.css', 'plugins/TopBookmarks');
        $Sender->setData('Title', t('Settings for the TopBookmarks Plugin'));
        $Sender->addSideMenu('dashboard/settings/plugins');
		$this->SettingDefaults($Sender,'settingsController');
		$Goterror =false;
		$MinWarning = '';
		$Feedback = '';
		$TopError = '';
		$TopWarning = '';
		$BackwardError = '';
		$MinOnListError = '';
		$MenuNameError = '';
		$ConfigurationModule = new ConfigurationModule($Sender);
		$Validation = new Gdn_Validation();
		$ConfigurationModel = new Gdn_ConfigurationModel($Validation);
		$MaxBookmarked = $this->CountHighhestBookmark($Sender,$Debug);
		$Sender->Form->SetModel($ConfigurationModel);
		if ($Sender->Form->authenticatedPostBack()) {
			//Echo '<br>  At postback '.__LINE__;
			$FormPostValues = $Sender->Form->formValues();
			$Sender->Form->SetData($FormPostValues);
			//$Sender->Form->SetData($ConfigurationModel->Data);
			$Validation = new Gdn_Validation();
			$Feedback = $this->CheckSettings($Sender,'All',$Debug);
			if ($Feedback){ 
			  $Sender->StatusMessage = t('Verify your settings again. Incomplete settings saved');
			  $Validation->addValidationResult('Plugins.TopBookmarks.Backward', $Feedback);
			}
			$Data = $Sender->Form->formValues();
			$Backward = getvalue('Plugins.TopBookmarks.Backward',$Data);
			$BackwardError = $this->CheckField($Sender,$Backward,
							Array('Integer'=>'?','Required'=> 'Integer','Min'=> 0,'Max'=>600),
							'Number of recent activity days (Aging)',$Debug);
			$MinOnList = getvalue('Plugins.TopBookmarks.MinOnList',$Data);
			$MinOnListError = $this->CheckField($Sender,$MinOnList,
							Array('Required'=> 'Integer','Min'=> 1,'Max'=>99999),
							'Minimum number of bookmarks for a discussion to be considered "Top Bookmarked"',$Debug);
			
			if (is_numeric($MinOnList) && ($MinOnList > $MaxBookmarked)) {
				$MinWarning = wrap('Note: The current highest number of bookmarks for a discussion in your forum is '.$MaxBookmarked.
				'. If you set "(B) Minimum Count" to a higher number, at this time no discussion will be considered Top-Bookmarked'
							,'span class=SettingNote');
			}
			
			$MenuName = getvalue('Plugins.TopBookmarks.MenuName',$Data);
			$MenuNameError = $this->CheckField($Sender,$MenuName,
							Array('Required'=> 'Alpha'),
							'Menu link title',$Debug);
			$Validation->applyRule('Plugins.TopBookmarks.Backward','Required',t('"(A) Aging" is required'));
			$Validation->applyRule('Plugins.TopBookmarks.Backward','Integer',t('"(A) Aging" must be numeric'));
			$Validation->applyRule('Plugins.TopBookmarks.MenuName','Required',t('Specify a valid name for Menu Link Title'));
			//$Validation->applyRule('Plugins.TopBookmarks.MenuName','Username',t('Specify a valid name for Menu Link Title'));
			$AddToMenu = getvalue('Plugins.TopBookmarks.AddToMenu',$Data);
			$ShowPanelLink = getvalue('Plugins.TopBookmarks.ShowPanelLink',$Data);
			if (!$AddToMenu && ! $ShowPanelLink) {
				$Goterror=true;
				$Sender->InformMessage('FYI, if neither option 1 nor 2 are selected then the Top Bookmarks discussion list will not be accessible','DoNotDismiss');
			}
			if (trim($BackwardError.$MinOnListError.$MenuNameError)) {
				$Goterror=true;
				$Validation->addValidationResult('Plugins.TopBookmarks.Backward', 'Errors need to be corrected');
			}
			if (!$Validation->validate($FormPostValues)) $Goterror=true;
			
			if ($Goterror) {
				$Sender->Form->setValidationResults($Validation->Results());
				SaveToConfig('Plugins.TopBookmarks.IncompleteSetup',TRUE);
			} else {
				SaveToConfig('Plugins.TopBookmarks.IncompleteSetup',FALSE);
			}
		// NOT POSTBACK
		} else {  
			$Sender->Form->SetData($ConfigurationModel->Data);
			$Feedback .= wrap(wrap('Note: See the readme file for help','div class=SettingNote'),'br');		
			$MinWarning = wrap('<B>★ </b> The current highest number of bookmarks for a discussion in your forum is '.$MaxBookmarked.
				'. If you set "(B) Minimum Count" to a higher number, at this time no discussion will be considered Top-Bookmarked'
							,'span class=SettingText');
        }		
		$PluginConfig = $this->SetConfig($Sender,
						Array(	'Feedback' => $Feedback,
								'TopError' => $TopError,
								'TopWarning' => $TopWarning,
								'Backward'  =>$BackwardError,
								'MinOnList' =>$MinOnListError,
								'MinOnListNote' =>$MinWarning,
								'MenuName' =>$MenuNameError),$Debug);
		$ConfigurationModule->initialize($PluginConfig);

		$ConfigurationModule->renderAll();
		
    }
///////////////////////////////////////// 
// Set Confogiration Array
 public function SetConfig($Sender,$Errors = Array(),$Debug) {
	$DefaultMenuName=$this->GetMenuName($Sender,'Default',$Debug);
	$Separator = '<span class=SettingSep>&nbsp</span>';
	$Headstyle = '<span class=SettingHead>★&nbsp&nbsp';
	$Subhstyle = '<span class=SettingSubh>';
	$Textstyle = '<span class=SettingText>';
	$Warnstyle = '<span class=SettingWarning>';
	$Errorstyle = '<span class=SettingError>';
	$Squeeze = '<span class=Settingsqueeze> </span>';
	$Notestyle = '<span class=SettingNote>';
	$BookmarkTag = '<span class="Meta Mitem HighbookmarkMeta">'.t('Top Bookmarked ').'-&nbsp;321<span class="HighbookmarkStar">★</span></span>';
	$Topmessage = '';
	$Feedback = $Errors['Feedback'];
	if (trim($Errors['TopError'])) $Topmessage = $Errorstyle.$Errors['TopError'].'</span>';
	if (trim($Errors['TopWarning'])) $Topmessage .= $Warnstyle.$Errors['TopWarning'].'</span>';
	
	//$Feedback .= __LINE__.$Feedback;
	//echo '<br>'.__FUNCTION__.__LINE__.$Feedback.'<br>';
	$PluginConfig = array(
		/*- Option to require "TopBookmarks View" permission to see the top bookmarks-*/
			'Plugins.TopBookmarks.Needpermission' => array(
			'Control' => 'CheckBox',
			'Description' => 	$Feedback.
								$Topmessage.
								$Headstyle.'<b>Permission Settings (Important!)'.
								$Textstyle.'(Admins are always authorized)</span></span>',
			'LabelCode' => 		$Textstyle.'Require "TopBookmarks View" permission in "Roles and Permissions" to see the Top Bookmarks</span>'.$Squeeze,
			'Default' => TRUE),	
		/*- Option to require "TopBookmarks ViewMarkers" permission to see the users who bookmarked a discussion-*/
			'Plugins.TopBookmarks.ViewMarkers' => array(
			'Control' => 'CheckBox',
			'Description' => 	'',
			'LabelCode' => 		$Textstyle.'Require "TopBookmarks ViewMarkers" permission in "Roles and Permissions" to see who bookmarked the top bookmarked discussions</span>'.$Squeeze,
			'Default' => TRUE),				
		/*- How many days backward to include in the top bookmarks-*/
			'Plugins.TopBookmarks.Backward' => array(
			'Control' => 'textbox',
			'LabelCode' => $Separator.$Headstyle.'<b>Top Bookmarks Definition:</b></span>'.$Textstyle.'</b><n>There are two parameters to the definition<br> (A) <b>Aging:</b> How "aged" is the bookmarked discussion (aging works from the most recent date of creation/comment) <b>and</b><br>(B)<b> Minimum Count:</b> The minimum count of the discussion bookmarks to be considered "Top Bookmarked" </span>',
			'Description' => $Textstyle.'<b>(A)</b> Number of recent activity days to include in top bookmarks (leave zero disregard discussion aging):</span>'.$Errors['Backward'],
			'Default' => '0'),
		/*- Specify minimum nuber of bookmarks listed on the Top-Bookmarks discussion list*/
			'Plugins.TopBookmarks.MinOnList' => array(
			'Control' => 'textbox',
			'Description' => $Textstyle.'<b>(B)</b> Minimum number of bookmarks for a discussion to be considered "Top Bookmarked".<br>Note: Pick a number not too high as to have an empty list and not too low as to be meaningless:<span>'.$Errors['MinOnList'].$Errors['MinOnListNote'],
			'LabelCode' => '' ,
			'Default' => 2),
		/*- Option to show Bookmark List as a menu option (sorting discussions by Bookmark Rank)*/
			'Plugins.TopBookmarks.AddToMenu' => array(
			'Control' => 'CheckBox',
			'Description' => $Separator.$Headstyle.'<b>Top Bookmarks Display Options</b></span>'.$Textstyle.'</span>',
			'LabelCode' => '<b>Option 1:</b> Add a menu bar link to display the top bookmarked Discussions (sorted by the number of Bookmarks)',
			'Default' => FALSE),
		/*- Option to show side panel link to top bookmarks List (sorting discussions by Bookmark Rank)-*/
			'Plugins.TopBookmarks.ShowPanelLink' => array(
			'Control' => 'CheckBox',
			'Description' => '',
			'LabelCode' => '<b>Option 2:</b> Add a sidepanel link to display the top bookmarked Discussions (sorted by the number of Bookmarks)',
			'Default' => FALSE),
		/*- Option to name the link to the bookmarked discussion list (Options 1 & 2 above)*/
			'Plugins.TopBookmarks.MenuName' => array(
			'Control' => 'textbox',
			'Description' => $Textstyle."Note:pick a name distinguished from the user's own Bookmarks menu option".' (default is "'.$DefaultMenuName.'"):<span>'.$Errors['MenuName'],
			'LabelCode' => $Squeeze."For Options 1 and 2 above specify the menu link title",
			'Default' => $DefaultMenuName),
		/*- Option to show Bookmark Rank within the list of discussions-*/
			'Plugins.TopBookmarks.Listinline' => array(
			'Control' => 'CheckBox',
			'Description' => '',
			'LabelCode' => '<b>Option 3:</b> Display Top Bookmark information in the meta area. Example:'.
							wrap($BookmarkTag,'span',' class=HighbookmarkMetaContainerSettings'),
			'Default' => FALSE),
		/*- Option to include a link to a popup that shows who bookmarked this discussion*/
			'Plugins.TopBookmarks.ShowLinks' => array(
			'Control' => 'CheckBox',
			'Description' => $Squeeze.'<b>Bookmarkers Identity options</b> (all are subject to permission setting above):',
			'LabelCode' => '<b>Option 4:</b> When Displaying Top Bookmarks information in the meta area, make the star icon a popup link to show who bookarked the discussion',
			'Default' => FALSE),
		/*- Option to add a gear option to show who bookmarked this discussion*/
			'Plugins.TopBookmarks.ShowGear' => array(
			'Control' => 'CheckBox',
			'Description' => '',
			'LabelCode' => '<b>Option 5:</b> Add an options gear (<b>❁</b>) link to show who bookmarked a Top Bookmarked discussion',
			'Default' => FALSE),
        );
	 return $PluginConfig;
 }
///////////////////////////////////////// 
// Check speicific field, return error message 
 public function CheckField($Sender,$Field=FALSE,$Checks=Array('Required'),$Title = 'Field',$Debug) {
	$Errormsg='';
	foreach ($Checks as $Test => $Value) {
		//echo '<br>'.__LINE__.'Test:'.$Test.' Value:'.$Value.' on:'.$Field;
		if($Test == 'Required') {
			if ($Field == '') {
				$Errormsg='is required';
			} else {
				if ($Value == 'Integer' && !is_numeric($Field)) {
					$Errormsg='must be numeric';
				} elseif ($Value == 'Alpha' && preg_match("/[0-9]+/", $Field)) {
					$Errormsg='must be alphabetic';
				}
			}
		} elseif  ($Test == 'Integer' && !is_numeric($Field)) { 
			$Errormsg='must be numeric';
		} elseif  ($Test == 'Alpha' && preg_match("/[0-9]+/", $Field)) { 
			$Errormsg='must be alphabetic';
		} elseif  ($Test == 'Min') {
			if ($Field < $Value) $Errormsg='must not be less than '.$Value;
		} elseif ($Test == 'Max') {
			if ($Field > $Value) $Errormsg='must not be greater than '.$Value;
		}
	}
	//echo '<br>'.__line__.$Errormsg;
	if ($Errormsg != '') {
		$Errormsg = wrap($Title.' '.t($Errormsg),'span class=SettingError');
	}
	//echo '<br>'.__line__.$Errormsg;
	return $Errormsg;
 }
///////////////////////////////////////// 
// Check Configuration Settings
 public function CheckSettings($Sender,$Type='All',$Debug) {
	 if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
	//
	$DefaultMenuName=$this->GetMenuName($Sender,'Default',$Debug);
	$Warn = '';
	$Error = '';
	//Get the menu filled variables
	$Data = $Sender->Form->formValues();
	//$Needpermission = getvalue('Plugins.TopBookmarks.Needpermission',$Data);
	//$ViewMarkers = getvalue('Plugins.TopBookmarks.ViewMarkers',$Data);
	$Backward = getvalue('Plugins.TopBookmarks.Backward',$Data);
	$MinOnList = getvalue('Plugins.TopBookmarks.MinOnList',$Data);
	$AddToMenu = getvalue('Plugins.TopBookmarks.AddToMenu',$Data);
	$ShowPanelLink = getvalue('Plugins.TopBookmarks.ShowPanelLink',$Data);
	$MenuName = getvalue('Plugins.TopBookmarks.MenuName',$Data);
	$Listinline = getvalue('Plugins.TopBookmarks.Listinline',$Data);
	$ShowLinks = getvalue('Plugins.TopBookmarks.ShowLinks',$Data);
	$ShowGear = getvalue('Plugins.TopBookmarks.ShowGear',$Data);
	//
	if ($MenuName == '') $MenuName = $DefaultMenuName;
	//if ($Debug) echo '<br>'.__LINE__.'MenuName:'.$MenuName;
	if (is_numeric($MenuName) || $MenuName==t('Bookmarks') || preg_match("/[0-9]+/", $MenuName))  {
		SaveToConfig('Plugins.TopBookmarks.MenuName',$DefaultMenuName);
		$Error .= '<br>Invalid menu link title:"'.$MenuName.'" ';
	}
	//	
	if ($Type == 'All' || $Type == 'Errors') {
		//if ($Debug) echo '<br>'.__LINE__.'Backward:'.$Backward;
		if ($Backward == '') $Backward = 0;
		if (!is_numeric($Backward) || $Backward < 0 || $Backward > 600) {
			if (!is_numeric($Backward)) { 
				$Error  = $Error .'<br>Invalid Top Bookmark Definition(A): "'.$Backward.'" is not numeric';
			} else {
				$Error  = $Error .'<br>Invalid Top Bookmark Definition (A):"'.$Backward.'". Shoule be between 1 and 600)';
			}
		}
		//
		if (!is_numeric($MinOnList) || ($MinOnList < 2 || $MinOnList > 99999)) {
			if (!is_numeric($MinOnList)) {
				$Error  = $Error .'<br>Invalid Top Bookmark Definition (B):"'.$MinOnList.'" is not numeric';
			} else {
				$Error  = $Error .'<br>Invalid Top Bookmark Definition (B): "'.$MinOnList.'". Value must be between 2 and 99999';
			}
		}
		if (!$Listinline && $ShowLinks) {
			$Error .= '<br>For option 4 to be active you must also activate option 3.';
		}
	}
	if ($Type == 'All' || $Type == 'Warnings') {
		if (!$AddToMenu && !$ShowPanelLink) {
			$Warn .= "<br>If you don't specify Option 1 or Option 2 the users will not be able to see the top bookmarked discussions.";
		}
		if (!$Listinline && !$ShowPanelLink && !$ShowGear && !$AddToMenu) {
			$Warn .= "<br>If you don't select options 1, 2, 4, or 5, nothing will show up...";
		}

	if ($Type != 'All' && $Type != 'Warnings' && $Type != 'Errors')
		return 'Error - Parameter '.$Type.' Unaccepted by '.__FUNCTION__;
	}
	//
	//if ($Debug) echo '<br>'.__LINE__.' Listinline:'.$Listinline.' ShowPanelLink:'.$ShowPanelLink.' ShowGear:'.$ShowGear.
		' AddToMenu:'.$AddToMenu.' ShowLinks:'.$ShowLinks.' Backward:'.$Backward;

	//*****************************************//
	if ($Error) {
		$Error = substr($Error,4);
		//$Error = Wrap(substr($Error,4),'span class=SettingError');
	}
	if ($Warn) {
		$Warn = substr($Warn,4);
		//$Warn = Wrap(substr($Warn,4),'span class=SettingWarning');
	}
	if ($Debug) 
		echo wrap('...'.__LINE__.' Error:'.$Error.' Warn:'.$Warn,'p class=SettingWarning');
	$Result = $Error.$Warn;
	return $Result;
 }
	///////////////////////////////////////// 
	// Get the name of the menu
	public function GetMenuName($Sender,$Request='Saved',$Debug) {
		$Default="Top-Bookmarks";
		if ($Request == 'Default') return $Default;
        $MenuName=c('Plugins.TopBookmarks.MenuName');
		if (trim($MenuName) == '' || is_numeric($MenuName) || $MenuName==t('Bookmarks') || preg_match("/[0-9]+/", $MenuName)) {
			//echo '<br>'.__LINE__.' MenuName:'.$MenuName.' Default:'.$Default;
			//echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
			$MenuName=$Default;
		}
		return $MenuName;
    }
	///////////////////////////////////////////////
	// Set the CSS
	public function assetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('TopBookmarks.css', 'plugins/TopBookmarks');
    }
	///////////////////////////////////////////////
	// Function to construct the link to the Top-Bookmarks list function
	private function GetBookMarkersFormUrl($DiscussionID, $Debug = FALSE) {
		//	Construct the link and add to the gear 
		$Text=t('View Bookmarkers...');
		$Simplekey =(349+Gdn::Session()->UserID); 
		$Encode = $DiscussionID ^ $Simplekey;
		$Url = '/dashboard/plugin/TopBookmarks/'.$DiscussionID.'&S='.$Encode; 
		return $Url;
	}
	///////////////////////////////////////////////
	// Function to add the bookmarkers list function into the gear
	private function AddToGear($Sender, $Args, $Debug = FALSE) {
		$Discussion = $Sender->EventArguments['Discussion'];
		//Verify user has right to display bookmarkers
		if (!$this->Accesscheck('Bookmarkers','ShowGear',$Debug)) return;
		//	Add the link to the gear  
		$DiscussionID = $Discussion->DiscussionID;
		$Url = $this->GetBookMarkersFormUrl($DiscussionID, $Debug);
		$Text = 'Show Bookmarkers';
		$Sender->Options .= '<li>'.anchor(t($Text), $Url,'Hijack Popup ReadbyGear').'</li>'; 
		return;		
	}
	///////////////////////////////////////////////
	public function CategoriesController_DiscussionOptions_Handler($Sender, $Args) {
		$this->AddToGear($Sender, $Args);
	}
	///////////////////////////////////////////////
	public function discussionsController_discussionOptions_handler($Sender, $Args) {
		$this->AddToGear($Sender, $Args);
	}
		///////////////////////////////////////////////
   	// Terminate with a severe message
	public function DieMessage($Message) {
		echo "<P>TopBookmarks Plugin Message:<H1><B>".$Message."</B></H1></P>";
		throw new Gdn_UserException($Message);
	}
	///////////////////////////////////////////////
	// Display data for debugging
	public function Showdata($Data, $Message, $Find, $Nest=0, $BR='<br>') {
		//var_dump($Data);
		echo "<br>".str_repeat(".",$Nest*4)."<B>(".($Nest).") ".$Message."</B>";
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
			echo wrap('"'.$Data.'"','b');
			//var_dump($Data);
		}
	}
	///////////////////////////////////////////////
}