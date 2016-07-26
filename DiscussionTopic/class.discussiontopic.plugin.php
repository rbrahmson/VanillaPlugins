<?php
$PluginInfo['DiscussionTopic'] = array(
    'Name' => 'DiscussionTopic',
	'Description' => 'Adds a side panel of discussions sharing similar topics.  Topics can automatically be derived through discussion title language analysis, administrator defined reserved "Priority Phrases", double quoted phrases, or entered manually.',
    'Version' => '3.1',
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'RequiredTheme' => false,
	'MobileFriendly' => false,
    'HasLocale' => true,
	'SettingsUrl' => '/settings/DiscussionTopic',
    'SettingsPermission' => 'Garden.Settings.Manage',
	'RegisterPermissions' => array('Plugins.DiscussionTopic.View','Plugins.DiscussionTopic.Manage'),
    'Author' => "Roger Brahmson",
	'GitHub' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/DiscussionTopic",
	'PluginConstants' => array('Startgen' => '0','Maxbatch' => '10000'),
	'License' => "GNU GPL3"
);
	/////////////////////////////////////////////////////////

class DiscussionTopicPlugin extends Gdn_Plugin {
	/////////////////////////////////////////////////////////
	//This hook handles the saving of the initial discussion body (but not comments).
	public function DiscussionModel_beforeSaveDiscussion_handler($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = 'Saving Discussion... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];;
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($Sender->EventArguments,__LINE__.'---$Sender->EventArguments---','',0,' ',true);
			$this->ShowData($Sender->EventArguments,__LINE__.'---$Sender->EventArguments---','',0,' ',true);
		}
		//
		$FormPostValues = val('FormPostValues', $Sender->EventArguments, array());
		//
		$DiscussionID = val('DiscussionID', $FormPostValues, 0);
		$CategoryID = val('CategoryID', $FormPostValues, 0);
		//
		$CategoryNums = c('Plugins.DiscussionTopic.CategoryNums');
		if ($Debug) $this->ShowData($CategoryNums,'---Catnums---','',0,' ',true);
		if ($CategoryNums != "") {  //Limited category list?
			if (!in_array($CategoryID, $CategoryNums)) {	//Not in the list?
				if ($Debug) //**Gdn::controller()->informMessage($this->ShowData($CategoryID,__LINE__.'---CategoryID---','',0,' ',true));
				return;
			}
		}
		//
		$CommentID = val('CommentID', $FormPostValues, 0);
		$Name = val('Name', $FormPostValues, '');
		$Body = val('Body', $FormPostValues, '');
		$Topic = val('Topic', $FormPostValues, '');	
		$TopicAnswer  = val('TopicAnswer ', $FormPostValues, '');	
		if ($DiscussionID == 0) $TopicAnswerVal = false;
		if ($TopicAnswer != '') {
			$TopicAnswerVal = true;
		}
		if ($Debug) {
			$this->ShowData($Sender->EventArguments,__LINE__.'---$Sender->EventArguments---','',0,' ',true);
			$this->ShowData($Args,__LINE__.'---$Args---','',0,' ',true);
			$this->ShowData($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
			$this->ShowData($CategoryID,__LINE__.'---CategoryID---','',0,' ',true);
			$this->ShowData($CommentID,__LINE__.'---CommentID---','',0,' ',true);
			$this->ShowData($Name,__LINE__.'---Name---','',0,' ',true);
			$this->ShowData($Topic,__LINE__.'---Topic---','',0,' ',true);
			$this->ShowData($TopicAnswerVal,__LINE__.'---TopicAnswerVal---','',0,' ',true);
			//$this->ShowData($FormPostValues,'---FormPostValues---','',0,' ',true);
			$Debug = false;
		}
		//
		//if (substr($Body.'          ',0,9) == "**DEBUG*!") $Debug = true;
		//
		$Extract = $this->GetSubject($Sender,$Name,'',$Debug);
		//
		if ($Debug) $this->ShowData($Extract,__LINE__.'---Extract---','',0,' ',true);
		$Sender->EventArguments['FormPostValues']['Topic'] = $Extract;
		$Sender->EventArguments['FormPostValues']['TopicAnswer'] = $TopicAnswerVal;
		if (substr($Body.'          ',0,10)  == "**DEBUG*!/") die(0);
	}
	/////////////////////////////////////////////////////////
	//Dispatch 
	public function PluginController_DiscussionTopic_Create($Sender, $Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//if ($Debug) $this->ShowData($Sender->RequestArgs,__LINE__.'---Sender->RequestArgs---','',0,' ',true);
		if ($Sender->RequestArg[0] == 'Search') {
			$this->Controller_DiscussionTopicSearch($Sender,$Args);
			return;
		} elseif ($Sender->RequestArg[0] == 'SubjectSearch') {
			$this->Controller_DiscussionSubjectSearch($Sender,$Args);
			return;
		}
		$this->Dispatch($Sender, $Sender->RequestArgs);
		//
	}
	/////////////////////////////////////////////////////////
	//Handle Discussion Topic update request from the gear (options menu)
	public function Controller_DiscussionTopicSetTopic($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//$DiscussionID = $Args[0];
		$DiscussionID = intval($_GET['D']);
		if ($Debug) $this->ShowData($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
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
			$SimpleKey = (367+Gdn::Session()->UserID);
			$D2 = $DiscussionID ^ $SimpleKey;
			if  ($D2 != $Encode) {									//Encoded form does not belong to this DiscussionID
				$this->DieMessage('DA005 - Invalid Parameter:'.$Encode);
				return;
			}
		}
		
		// Now we know that passed parameters are fine
		$DiscussionModel = new DiscussionModel();
		$Discussion = $DiscussionModel->GetID($DiscussionID);
		$Topic = $Discussion->Topic;		
		
		$Referer = $_SERVER["HTTP_REFERER"];
		$DisplayingDiscussion = strpos($Referer, 'discussion/'.$DiscussionID);	//Non zero value indicates we're browing a single discussion (so side panel is possible)
		$PreTopic = $Discussion->Topic;							//Save value before the form is displayed
		$PreTopicAnswer = $Discussion->TopicAnswer;
		$Update = false;
		
		$this->ShowTopicForm($Sender,$Discussion,$Debug);	//Display the form		
		
		$Topic = $Discussion->Topic;
		$TopicAnswer = $Discussion->TopicAnswer;
		if ($Topic != $PreTopic | $TopicAnswer != $PreTopicAnswer) {				//Don't bther to change anything if nothing was changed
			SaveToConfig('Plugins.DiscussionTopic.Cleared',false);
			Gdn::sql() ->update('Discussion')
					->set('TopicAnswer', $TopicAnswer) 
					->set('Topic', $Topic) 
					->where('DiscussionID', $DiscussionID)
					->put();
			// 
			$Sender->JsonTarget('#Topic'.$DiscussionID,
					$this->DisplayTopicAnswer($Sender, $Topic, $TopicAnswer, $DiscussionID, t('Discussion Topic'),'','','TopicInPost '),
					'ReplaceWith');
			// 
			if ($DisplayingDiscussion > 0) {									//Refresh side panel only when displaying a single discussion.
				$ModuleContent = new DiscussionTopicModule($Sender);
				$Limit = c('Plugins.DiscussionTopic.Panelsize',8);
				$TopicBox =  wrap($ModuleContent->Refresh($DiscussionID,$Limit,$Debug));
				$Sender->JsonTarget('#TitleBox', $TopicBox, 'ReplaceWith');			
			}
		}
		$Sender->Render('Blank', 'Utility', 'Dashboard');
	}	
	/////////////////////////////////////////////////////////
	public function assetTarget() {
		return 'Content';
	}
	/////////////////////////////////////////////////////////
	// Display the Topic form
	private function ShowTopicForm($Sender,$Discussion, $Debug = false) {
		if ($Debug) {
			//**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
			echo(__LINE__.'DiscussionID='.$Discussion->DiscussionID);
		}
		$Topic = $Discussion->Topic;		
		$DefaultTopic = $this->GetSubject($Sender,$Discussion->Name,'',$Debug);
		$Sender->setData('Topic', $Topic);
		$Sender->setData('DefaultTopic',$DefaultTopic);
		$Sender->setData('DiscussionName', $Discussion->Name);	
		$Sender->setData('TopAnswerMode', c('Plugins.DiscussionTopic.TopAnswerMode',false));	
		$Sender->setData('TopicAnswer', $Discussion->TopicAnswer);
		//Modes: 1=manual, 2=Deterministic, 3=Heuristic, 4=Progressive (Both 2&3)
		$Mode = 1 + c('Plugins.DiscussionTopic.Mode',0); 
		$ModeArray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
		$ModeName = $ModeArray[$Mode];
		// 
		$ModeMsg = wrap(t('Current mode is ').$ModeName,'div');
		$Sender->setData('ModeMsg',$ModeMsg);
		//
		switch ($DefaultTopic) {							
			case '':
				if ($Mode == 1) {
					$FormMsg = wrap(t('The plugin is in manual mode - it does not auto-generate discussion topics.'),'div');
				} else {
					$FormMsg = wrap(t('There is no auto-generated topic with the current settings and this discussion title.'),'div');
				}
				break;
			case $Topic:
				$FormMsg = wrap(t('Default topic matches the saved discussion topic'),'div');
				break;
			default:
				$FormMsg = wrap(t('Default (autogenerated) topic:').'<b>'.$DefaultTopic.'</b>','div ');
		}		
		if ($Discussion->TopicAnswer) $FormMsg .= wrap('<br><b>'.t('Current topic is marked as top topic').'</b>','div');
		$Sender->setData('FormMsg',$FormMsg);
		//
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
		$Postback=$Sender->Form->authenticatedPostBack();
		if(!$Postback) {					//Before form submission
			$Sender->Form->setValue('Topic', $Topic);
		} else {								//After form submission	
			$FormPostValues = $Sender->Form->formValues();
			if($Sender->Form->ErrorCount() == 0){
				if (isset($FormPostValues['Cancel'])) {
					return;
				}
				//
				$this->ShowData($FormPostValues,__LINE__.'---$FormPostValues---','',0,' ',true);
				if (isset($FormPostValues['Remove'])) {
					$Discussion->Topic = "";
					$Discussion->TopicAnswer = false;
					return;
				} elseif (isset($FormPostValues[t('Generate')])) {
					$Discussion->Topic = $DefaultTopic;
					$Sender->Form->SetFormValue('Topic', $DefaultTopic);
					$Discussion->TopicAnswer = false;
					$Sender->Form->SetFormValue('TopicAnswer', false);
					$Sender->Form->addError(t('Topic was auto-generated but not saved yet.'), 'Topic');
				} elseif (isset($FormPostValues['RegularSave'])) {
					$Topic = strip_tags($FormPostValues['Topic']);
					$Discussion->Topic = $Topic;
					$Discussion->TopicAnswer = false;
					if (trim($Topic) != '') return;
					$Sender->Form->addError(t('You cannot save an empty topic.'), 'Topic');
				} elseif (isset($FormPostValues[t('TopSave')])) {	
					$Topic = strip_tags($FormPostValues['Topic']);
					$Discussion->Topic = $Topic;
					$Discussion->TopicAnswer = true;
					if (trim($Topic) != '') return;
					$Sender->Form->addError(t('You cannot save an empty topic.'), 'Topic');
				} else {
					$Sender->Form->addError(t('Verify entered data and press one of the buttons.'), 'Topic');
				}
            } else {
				$Sender->Form->setData($FormPostValues);
			}
		}
		$View = $this->getView('Topic.php');
		$Sender->render($View);
		return ;
	}
	/////////////////////////////////////////////////////////
	// Process the Guide Review request
	public function Controller_DiscussionTopicGuide($Sender,$Args) {
		$Sender->permission('Garden.Settings.Manage');
		$View = $this->getView('CustomizationandSetupGuide.htm');
		$Sender->render($View);
	}
	/////////////////////////////////////////////////////////
	// Process the Temporaty Sort by Topicrequest
	public function Controller_DiscussionTopicSortbytopic($Sender,$Args) {
		$Sender->permission('Garden.Settings.Manage');
		if (c('Plugins.DiscussionTopic.SortByTopic')) {
			saveToConfig('Plugins.DiscussionTopic.SortByTopic', false);
		} else {
			saveToConfig('Plugins.DiscussionTopic.SortByTopic', true);
		}
		redirect('/discussions');
	}
	/////////////////////////////////////////////////////////
	// Process the search request
	public function Controller_DiscussionTopicSearch($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		if (!CheckPermission('Plugins.DiscussionTopic.View')) return;
		foreach ($_GET as $Key => $Value) {		
			if ($Key == "!DEBUG!") {	
				$Debug = $Value;
			} elseif ($Key == "try") {	
				$Search = $Value;
				$SearchSubject = true;
				if ($Search != '') $this->FilterTopic($Search, $SearchSubject, $Debug);
			} elseif ($Key == "s") {	
				$Search = $Value;
				$SearchSubject = false;
				if ($Search != '') $this->FilterTopic($Search, $SearchSubject, $Debug);
			}			
		}
		if ($Debug) $this->ShowData($Search,__LINE__.'---Search---','',0,' ',true);
		$this->ShowSearchForm($Sender,$Search,$SearchSubject,$Debug);	//Display the form
		if ($Debug) $this->ShowData($Search,__LINE__.'---Search---','',0,' ',true);
		$Sender->Render('Blank', 'Utility', 'Dashboard');
	}
	/////////////////////////////////////////////////////////
	// Display the Topic Search form
	private function ShowSearchForm($Sender, $Search, $SearchSubject = false, $Debug = false) {
		if ($Debug) {
			$this->ShowData($Search,__LINE__.'---Search---','',0,' ',true);
		}
		//
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
		$Sender->setData('Searchstring', $Search);
		$Postback=$Sender->Form->authenticatedPostBack();
		if(!$Postback) {					//Before form submission	
			$Sender->Form->setValue('Searchstring', $Search);
			$Sender->Form->setFormValue('Searchstring', $Search);
			$Sender->Form->setFormValue('SearchSubject', $SearchSubject);
		} else {								//After form submission	
			$FormPostValues = $Sender->Form->formValues();
			//if ($Debug) $this->ShowData($FormPostValues,__LINE__.'---FormPostValues---','',0,' ',true);
			$Data = $Sender->Form->formValues();
			//if ($Debug) $this->ShowData($Data,__LINE__.'---Data---','',0,' ',true);
			if($Sender->Form->ErrorCount() == 0){
				if (isset($FormPostValues['Cancel'])) {;
					return '';
				}
				if (isset($FormPostValues['TopicSearch'])) {
					$Search = $FormPostValues['Searchstring'];
					$Sender->Form->SetFormValue('Searchstring', $Search);
					$this->FilterTopic($Search, false, $Debug);
					return;			//just in case...
				} elseif (isset($FormPostValues['SubjectSearch'])) {
					$Search = $FormPostValues['Searchstring'];
					$Sender->Form->SetFormValue('Searchstring', $Search);
					$this->FilterTopic($Search, true, $Debug);
					return;			//just in case...
				} 
            } else {
				$Sender->Form->setData($FormPostValues);
			}
		}
		//
		$View = $this->getView('Topicsearch.php');
		$Sender->render($View);
		return;
	}
	/////////////////////////////////////////////////////////
	//Place a topic search menu on the menu bar
	public function Base_Render_Before($Sender, $Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//
		$ErrorMsg = Gdn::session()->stash('IBPTopicMsg');
		if ($ErrorMsg != '') {
			Gdn::session()->stash('IBPTopicMsg', '');
			Gdn::controller()->informMessage($ErrorMsg);
		}
		//
		$Controller = $Sender->ControllerName;						//Current Controller
		$MasterView = $Sender->MasterView;
		$AllowedControllers = Array('discussionscontroller','discussioncontroller','categoriescontroller');		//Add other controllers if you want
		//$this->ShowData($Controller,__LINE__.'---Controller---','',0,' ',true);
		//$this->ShowData($MasterView,__LINE__.'---MasterView---','',0,' ',true);
		
		if (!c('Plugins.DiscussionTopic.Showmenu', false)) return;				
		if (!CheckPermission('Plugins.DiscussionTopic.View')) return;
		
		if (InArrayI($Controller, $AllowedControllers)) {
			$Css = 'Popup TopicSearch"  Target="_self';
			$Sender->Menu->AddLink("Menu", t('Topic-Search'),'/plugin/DiscussionTopic/DiscussionTopicSearch?s=',false,
								 array('class' => $Css, 'target' => '_self'));
		}
	} 
	/////////////////////////////////////////////////////////
	//Handle Database update request
	public function Controller_DiscussionTopicUpdate($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			echo Wrap($Msg,'br');
		}
		$Sender->permission('Garden.Settings.Manage');
		
		if (!Gdn::Session()->CheckPermission('Plugins.DiscussionTopic.Manage')) {
			echo wrap('DiscussionTopic plugin ('.__LINE__.') you need to set "Plugins.DiscussionTopic.Manage" Permission to use this function.',h1);
			return ;
		}
		//
		$Debug = intval($_GET['!DEBUG!T']);
		if ($Debug) $this->ShowData($_GET,__LINE__.'---$_GET---','',0,' ',true);
		//
		$Restart = intval($_GET['restart']);
		if ($Debug) $this->ShowData($Restart,__LINE__.'---Restart---','',0,' ',true);
		//
		$CssUrl = '/' .  Gdn::Request()->WebRoot() . '/plugins/DiscussionTopic/design/pluginsetup.css?v=2.1.3';
		//echo wrap('<head><link rel="stylesheet" type="text/css" href="/' . Gdn::Request()->WebRoot() . "/plugins/DiscussionTopic/design/pluginsetup.css?v=2.1.3" media="all" />','head').'<body>';
		echo '<link rel="stylesheet" type="text/css" href="' . $CssUrl . '/plugins/DiscussionTopic/design/pluginsetup.css?v=2.1.3" media="all" />';
		//
		//  Handle Topic Clearing Requests
		//
		$Clear = intval($_GET['clear']);
		if ($Debug) $this->ShowData($Clear,__LINE__.'---Clear---','',0,' ',true);
		if ($Clear) {
			$SqlHandle = clone Gdn::sql();	//Don't interfere with any other sql process
			$SqlHandle->Reset();
			$Updates = $SqlHandle->update('Discussion d')
					->set('d.Topic', null)
					->set('d.TopicAnswer', null)
					->where('d.DiscussionID <>', 0)
					->put();
			$RowCount = count($Updates);
			if ($Debug) $this->ShowData($RowCount,__LINE__.'---Rowcount---','',0,' ',true);
			SaveToConfig('Plugins.DiscussionTopic.Parttialupdate',false);
			SaveToConfig('Plugins.DiscussionTopic.Cleared',true);
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',0);
			echo $this->JavaWindowClose();			//Initialize (Add Javascript to window
			echo $this->JavaWindowClose(Wrap('Topic Data Removed!','h2').'Click ','Exit','  to return to the settings screen.','div class="SettingLink"');
			//
			return;
		}
		//
		//
		$IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup',true);
		if ($IncompleteSetup) {
			$Msg = 'DiscussionTopic plugin configuration is incomplete.  The admin needs to complete the configuration without remaining error messages';
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'H1');
			return;
		}
		//
		$Limit = intval($_GET['limit']);
		if ($Debug) $this->ShowData($Limit,__LINE__.'---Limit---','',0,' ',true);
		$Urllimit = 0 +$Limit;
		if ($Urllimit == 0 | !is_numeric($Urllimit)) {
			$Limit = c('Plugins.DiscussionTopic.Maxrowupdates',10);
			if ($Debug) $this->ShowData($Limit,__LINE__.'---Limit---','',0,' ',true);
		}
		//
		$Updatecount = $this->UpdateExtract($Sender,$Limit,$Restart,$Debug);
		if ($Debug) $this->ShowData($Updatecount,__LINE__.'---Updatecount---','',0,' ',true);
		
	}
	/////////////////////////////////////////////////////////
	//Update old entries with the extract.
	private function UpdateExtract($Sender, $Limit = 10, $Restart = false, $Debug = false) {
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($Limit,__LINE__.'---Limit---','',0,' ',true);
		}
		//
		$IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup',true);
		if ($IncompleteSetup) {
			$Msg = 'DiscussionTopic plugin configuration is incomplete.  The admin needs to complete the configuration without remaining error messages';
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'H1');
			return 0;
		}
		//
		$DiscussionModel = new DiscussionModel();
		// Initialize the screen javascript
		echo $this->JavaWindowClose();	//Initialize (Add Javascript to window
		//
		$CategoryNums = c('Plugins.DiscussionTopic.CategoryNums');
		//
		//Get the cetegory ids the user is allowed to see 
		$Categories = CategoryModel::getByPermission();
		$Categories = array_column($Categories, 'Name', 'CategoryID');
		//if ($Debug) $this->ShowData($Categories,__LINE__.'---Categories---','',0,' ',true);
		$Categorycount = 0;
		foreach ($Categories as $CategoryID  => $CategoryName) {
			//$this->ShowData($CategoryID,__LINE__.'---CategoryID---','',0,' ',true);
			//$this->ShowData($CategoryName,__LINE__.'---CategoryName---','',0,' ',true);
			if ($CategoryNums != "") {
				if (in_array($CategoryID, $CategoryNums)) {	//In the list?
					$Categorycount = $Categorycount + 1;
					$CategoryList[$Categorycount] = $CategoryID;
				}
			} else {
				$Categorycount = $Categorycount + 1;
				$CategoryList[$Categorycount] = $CategoryID;
			}
		}
		if ($Debug) $this->ShowData($CategoryList,__LINE__.'---Categorylist---','',0,' ',true);
		//
		$UpdateGen = c('Plugins.DiscussionTopic.Updategen',1);
		if ($Restart) {		/*New batch*/
			$StartID = 0;
			$Newgen = 1 + $UpdateGen;
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',0);
			$Title = 'New update batch'.str_repeat("&nbsp",40).' (Batch#'.$Newgen.')';
		} else {			/*Continued batch*/
			$StartID = c('Plugins.DiscussionTopic.HighupdateID',0);
			$Title = 'Continuing update batch'.str_repeat("&nbsp",40).' (Batch#'.(1+$UpdateGen).')';
		}
		if ($Debug) $this->ShowData($StartID,__LINE__.'---StartID---','',0,' ',true);
		$UseLimit = $Limit + 1;
		$SqlHandle = clone Gdn::sql();	//Don't interfere with any other sql process
		$SqlHandle->Reset();				//Clean slate
		$SqlFields = 'd.DiscussionID,d.Name,d.CategoryID,d.Topic,d.TopicAnswer';
		$Discussionlist = $SqlHandle		
			->select($SqlFields)
			->from('Discussion d')
			->where('d.DiscussionID >', $StartID)
			->wherein('d.CategoryID', $CategoryList)
			->orderby('d.DiscussionID')
			->limit($UseLimit)
			->get();
		//
		$RowCount = count($Discussionlist);
		if ($Debug) echo '<br>'.__LINE__.' Rowcount:'.$RowCount;
		if ($RowCount == 0) {
			echo wrap('<br> DiscussionTopic.'.__LINE__.' Nothing available for updating the extracts using the current criteria.  
						You may <b>start</b> a new update batch by using the appropriate link in the configuration panel.',
						'div class=SettingLink');
			return 0;
		}
		//
		$Listcount = 0;
		$SqlHandle->Reset();
		$Title = wrap('<b>DiscussionTopic plugin multiple discussions update.</b><br> '.c('Plugins.DiscussionTopic.ModeName').' Mode. '.$Title,'div ');
		///echo wrap(str_repeat("&nbsp",120),'div class=Settingsqueeze');
		//
		if ($RowCount >  $Limit) {
			$Title .= wrap('<br>Click '.Anchor('Continue','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0','Popup ContinueButton ').
						' to <b>continue</b> the current update batch '.
						$this->JavaWindowClose('or click the ','Exit',' button to return to the setting screen.  ','', $Debug),'b ');
		} else {
			$Title .= $this->JavaWindowClose('Click ','Exit',' to return to the setting screen. ','div' , $Debug);
		}
		echo wrap($Title,'div class=SettingLink');
		$ReportRowNumber = c('Plugins.DiscussionTopic.ReportRowNumber',0);
		//
		foreach($Discussionlist as $Entry){
			$Listcount += 1;
			if ($Listcount <= $Limit) {
				//if ($Debug) $this->ShowData($Entry,__LINE__.'---Entry---','',0,' ',true);
				$DiscussionID = $Entry->DiscussionID;	
				$Discussion = $DiscussionModel->getID($DiscussionID);
				$Name = $Entry->Name;
				$Topic = $this->GetSubject($Sender,$Name,'',$Debug);	
				$ReportRowNumber += 1;
				$TopicAnswerNote = '';
				if ($Entry->TopicAnswer) $TopicAnswerNote = wrap(' ***Was Top Topic*** ','span style="color:red"');
					
				echo wrap('<br>'.$ReportRowNumber.' ID:<b>'.$DiscussionID.$TopicAnswerNote.' </b>Title:<b>'.SliceString($Name,60).' </b>Keywords:<b>'.$Topic.'</b>','span');
				$SqlHandle->update('Discussion d')
					->set('d.Topic', $Topic)
					->set('d.TopicAnswer', false)
					->where('d.DiscussionID', $DiscussionID)
					
					->put();
				$HighWatermark = $DiscussionID;
			}
		}
		$UpdateTopMessage = wrap('<b> '. ($Listcount-1) .' rows updated.</b>','span');
		SaveToConfig('Plugins.DiscussionTopic.Cleared',false);
		if ($RowCount >  $Limit) {	//Batch incomplete
			echo wrap($UpdateTopMessage.' <b>Note:</b> More rows can be updated.','span class=UpdateTopMessage');
			SaveToConfig('Plugins.DiscussionTopic.Parttialupdate',true);
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',$HighWatermark);	
			SaveToConfig('Plugins.DiscussionTopic.ReportRowNumber',$ReportRowNumber);
		} else {					//Batch completed
			echo wrap($UpdateTopMessage.' Note: No more rows can be updated under the current settings.','span class=UpdateTopMessage');
			SaveToConfig('Plugins.DiscussionTopic.Parttialupdate',false);
			$Newgen = 1 + $UpdateGen;
			SaveToConfig('Plugins.DiscussionTopic.Updategen',$Newgen);
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',0);
			SaveToConfig('Plugins.DiscussionTopic.ReportRowNumber',0);
		}
		//
		return $Listcount;
	}
	/////////////////////////////////////////////////////////
	//Add a window close script.
	private function JavaWindowClose($Prefix = '',$Button = '', $Suffix = '', $Class = '', $Debug = false) {
		//
		//  Process button 
		$String = $Prefix;
		if ($Button != '') {
			$String .= wrap('<input type="button" value="' . $Button . '" onclick="windowClose();">','span ');
		}
		if ($Suffix != '') $String .= $Suffix;
		if ($String != '') {
			if  ($Class !=  '') $String = wrap($String,$Class);
			return $String;
		}
		// No button, this must be initialization request
		$CloseScript = '<body onpagehide="refreshParent();"><script language="javascript" type="text/javascript">
							window.onunload = refreshParent;
								function refreshParent() {
									window.opener.location.reload(true);	
							}
							function windowClose() { 
							window.open(\'\',\'_parent\',\'\'); 
							window.opener.location.reload(true);
							//self.opener.location.reload(); 
							window.close();
							} 
						</script>';
		return $CloseScript;
	}
	/////////////////////////////////////////////////////////
	//Build the extract from the discussion title.
	private function GetSubject($Sender,$String,$Simulate = '', $Debug = false) {	
		if (Gdn::session()->checkPermission('Plugins.DiscussionTopic.Manage')) {
			if (substr($String,0,7) == '!DEBUG!') {
				$String = substr($String,7);
				$Debug = true;
			}
		}
		//
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($String,__LINE__.'---String---','',0,' ',true);
			$this->ShowData($Simulate,__LINE__.'---Simulate---','',0,' ',true);
		}
		//Modes: 1=manual, 2=Deterministic, 3=Heuristic, 4=Progressive (Both 2&3)
		$Mode = 1 + c('Plugins.DiscussionTopic.Mode',0); 
		$ModeArray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
		$ModeName = $ModeArray[$Mode];
		// 
		if ($Simulate != '') {
			$Mode = array_search($Simulate, $ModeArray);
			$ModeName = $Simulate;
		}
		if ($Debug) $this->ShowData($Mode,__LINE__.'---Mode---','',0,' ',true);
		if ($Debug) $this->ShowData($ModeName,__LINE__.'---ModeName---','',0,' ',true);
		if ($ModeName == 'Manual') return;
		//
		//Clean up the sentence;
		$String = $this->CleanString($Sender,$String,$ModeName,$Debug);
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		//Modes: 1=Manual, 2=Deterministic, 3=Heuristic, 4=Progressive (2 & 3)
		if (substr($String,0,1) == '"') {
			if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
			if ($ModeName != 'Heuristic') return $String;		//Deterministic or Progressive modes
		}
		if ($ModeName == 'Deterministic') return '';				//No double quoted string and detrministic, so no topic to return.
		//	Handle Heuristic and Progressive modes
		//Get the global update generation number
		$UpdateGen = c('Plugins.DiscussionTopic.Updategen',1);
		SaveToConfig('Plugins.DiscussionTopic.Updategen',$UpdateGen);
		// Get the Noise Words
		$NoiseArray = $this->GetNoiseArray($Debug);
		//if ($Debug) $this->ShowData($NoiseArray,__LINE__.'---Noisearray---','',0,' ',true);
		//
		//Get the number of significant keywords
		$SigWords = c('Plugins.DiscussionTopic.Sigwords',2);
		//
		//Check which text Analyzer to use
		$AnalyzerName = c('Plugins.DiscussionTopic.Analyzername','');
		if ($AnalyzerName == '') {
			echo wrap('DiscussionTopic plugin - Missing Analyzer name','h1');
			return '';	
		}
		//Analyze the words - in this section we perform language analysis.  This is where you will place your own language analyzer.
		if ($AnalyzerName == 'PosTagger') {
			$Tagger = new PosTagger('lexicon.txt');
			$Tags = $Tagger->tag($String);
			//if ($Debug) $this->ShowData($Tags,__LINE__.'---Tags---','',0,' ',true);
			//Remap PosTagger to Textrazor response
			$Words = array_map(function($tag) {
				return array(
					'token' => $tag['token'],
					'stem' => PorterStemmer::Stem($tag['token']),
					'partOfSpeech' => $tag['tag']
				);
			}, $Tags);
			//if ($Debug) $this->ShowData($Words,__LINE__.'---Words---','',0,' ',true);
		} 
		elseif ($AnalyzerName == 'TextRazor') {		
			$Textrazorkey = c('Plugins.DiscussionTopic.TextRazorKey',c('Plugins.DiscussionTopic.TEXTRAZORAPIKEY',''));
			if ($Textrazorkey == '') {
				echo wrap('DiscussionTopic plugin - Missing Textraor API key','h1');
				return '';	
			}
			//
			$Textrazor = new TextRazor();
			$Extractor = C('Plugins.DiscussionTopic.Extractor','words');
			$Textrazor->addExtractor($Extractor);
			$Textrazor->setLanguageOverride('eng');
			$text = $String;
			$Response = $Textrazor->analyze($text);
			//if ($Debug) $this->ShowData($Response,__LINE__.'---Response---','',0,' ',true);
			$Words = $Response['response']['sentences'][0]['words'];
		}
		//Parse Response
		$String = ' ';
		if (isset($Words)) {
			$Verbs = Array();
			$Nouns = Array();
			$i = 0;
			$n = 0;
			$v = 0;
			$Keywords = Array();
			foreach ($Words as $Entry) {
				if ($Debug) $this->ShowData($Entry,__LINE__.'---Entry---','',0,' ',true);
				/*Sample structure:	Entry--- array   - This is the expected structure of language analysis.  Use it with otter language analyzers
				//....(1) _integer:position value:"0"
				//....(1) _integer:startingPos value:"0"
				//....(1) _integer:endingPos value:"9"
				//....(1) string:stem value:"embed"
				//....(1) string:lemma value:"embedding"
				//....(1) string:token value:"Embedding"
				//....(1) string:partOfSpeech value:"NN" */
				$Token = strtolower($Entry['token']);
				$Stem = $Entry['stem'];
				$Partofspeech = $Entry['partOfSpeech'];
				//$Partofspeech = "NN";						//Uncomment this line to disable language analysis
				if (strlen($Token) > 1 && !in_array($Token,$NoiseArray)) {
					$i += 1;
					$Catchprefix = substr($Partofspeech,0,2);	//The first two letters denotic the part of speech in a sentence
					switch ($Catchprefix) {
						case "NN":								//The only accepted words are nouns and verbs
						case "XC":
							$Nouns[$n] = $Stem;
							$n += 1;
							break;
						case "VB":								//Accepting verbs
							$Verbs[($v)] = $Stem;
							$v += 1;
							break;
					}
				}
			}
			//
			if ($Debug) $this->ShowData($Nouns,__LINE__.'---Nouns---','',0,' ',true);
			//
			if ($Debug) $this->ShowData($Verbs,__LINE__.'---Verbs---','',0,' ',true);
			for ($j = 0;$j<count($Nouns); $j++ ) {									//Give priority to nouns
				$Keywords[$j] = $Nouns[$j];
			}
			for ($k = 0;$k<count($Verbs); $k++ ) {									//Only then look at verbs
				$j += 1;
				$Keywords[$j] = $Verbs[$k];
				if ($Debug) echo wrap('k:'.$k.' j:'.$j.', $Verbs[$k]:'.$Verbs[$k],'div');
			}
			if ($Debug) $this->ShowData($Keywords,__LINE__.'---Keywords---','',0,' ',true);
			//
			$Keywords = array_unique($Keywords);
			$Keywords = array_filter($Keywords); 
			ksort($Keywords);														//Standardize topic order (login failed == failed login)
			//if ($Debug) $this->ShowData($Keywords,__LINE__.'---Keywords---','',0,' ',true);
			$Keywords = array_slice($Keywords, 0, $SigWords);
			$String = implode(",",$Keywords);
		}
		if ($AnalyzerName == 'TextRazor') unset($Textrazor); 
		$String = trim($String);
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		return $String;   
	}
	/////////////////////////////////////////////////////////
	//Extract quoted string (if any).
	private function GetQuoted($Sender,$String,$Debug = true) {
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		}
		//
		preg_match_all('/"([^"]+)"/', $String, $Results);
		if ($Debug) $this->ShowData($Results,__LINE__.'---Results---','',0,' ',true);
		foreach ($Results[1] as $Quoted) {
			if ($Debug) echo '<br>'.__LINE__.' Quoted='.$Quoted.'<br>';
			if (strlen(trim($Quoted)) > 2) return '"'.trim($Quoted).'"';
		}
		return $String;
	}
	/////////////////////////////////////////////////////////
	//Build the extract from the discussion title.
	private function CleanString($Sender, $String, $ModeName, $Debug = false) {
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($String,__LINE__.'---String---','',0,' ',true);
			$this->ShowData($ModeName,__LINE__.'---ModeName---','',0,' ',true);
		}
		// Replace multiple spaces with single spaces
		$String = preg_replace('!\s+!', ' ', $String);
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		if ($ModeName != 'Heuristic') {
			// Priority phrases replacement
			$Newstring = $this->ChangeByPriority($Sender,$String,$Debug);
			if ($Debug) $this->ShowData($Newstring,__LINE__.'---String---','',0,' ',true);
			// Priority phrases replacement
			if ($Newstring != $String) {			//Substitution was made
				return $Newstring;
			}
		}
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		// Acronym replacement
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		$String = $this->ChangeByAcronym($Sender,$String,$Debug);
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		// Extract Quoted strings for Deterministic/Progressive modes
		if ($ModeName != 'Heuristic') {
			//Following substitution extract quoted string (if any) and if found use it as the topic
			$String = $this->GetQuoted($Sender,$String,$Debug);
			if (substr($String,0,1) == '"') return $String;
			// No quoted strings, let's start the hard work.
		}
		// Clear unnecessary punctioations
		$String = preg_replace("/(?![$])\p{P}/u", "", strtolower($String));
		//
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		//
		// Clear numbers leaving words with embedded numbers
		$Name = preg_replace("/(\b)[0-9]+(\b)/", ' ', $String);
		if ($Debug) $this->ShowData($Name,__LINE__.'---Name---','',0,' ',true);
		//
		// Tokenize (except for quoted texts
		preg_match_all("/(['\".'\"])(.*?)\\1|\S+/", $Name, $Result);
		$Tokens = $Result[0];
		if ($Tokens[0] == 'sample') $this->ShowData($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		//if ($Debug) $this->ShowData($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		//
		// Remove Noise Words
		$Tokens = $this->ChangeByNoise($Sender,$Tokens,$Debug);
		if ($Debug) $this->ShowData($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		//
		$Count = count($Tokens);
		$i = 0;
		while (($i<$Count) && (strlen($Tokens[$i])>1)) {
			$Token = $Tokens[$i];
			//if ($Debug) echo '<br>'.__LINE__.' i='.$i.' Before:'.$Token;
			//Remove quotes
			$Token = strtolower(preg_replace("/[^a-z.\d]+/i", "", $Token));
			//if ($Debug) echo ' After1:'.$Token;
			//
			//$Token = preg_replace("/(\w)\.(?!\S)/", "$1", $Token);
			//if ($Debug) echo ' After2:'.$Token;
			// Clear words with numbers
			//$Token = preg_replace("/ ?\b[^ ]*[0-9][^ ]*\b/i", " ", strtolower($Token));
			//if ($Debug) echo ' After3:'.$Token;
			$Tokens[$i] = $Token;
			$i++ ;
		}
		//if ($Debug) $this->ShowData($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		$String = implode(" ",$Tokens);
		//
		//if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		return $String;
	}
	/////////////////////////////////////////////////////////
	private function GetNoiseArray($Debug = false) {
		//$Debug = true;
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//
		$NoiseWords = strtolower(c('Plugins.DiscussionTopic.Noisewords',' '));
		$Localnoisearray = $this->GetExplode($NoiseWords,0);
		$Globalnoisearray = array('');
		$Globalnoisearray = t('DiscussionTopicNoisewords1');
		if ($Debug) $Globalnoisearray = array('1global23');  //T E S T I N G
		if ($Debug) $this->ShowData($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		$NoiseArray = array_merge($Localnoisearray,$Globalnoisearray);
		$NoiseArray = array_change_key_case($NoiseArray,CASE_LOWER);
		if ($Debug) $this->ShowData($NoiseArray,__LINE__.'---Noisearray---','',0,' ',true);
		return $NoiseArray;
		//
	}
	/////////////////////////////////////////////////////////
	private function ChangeByNoise($Sender,$Tokens,$Debug = false) {
		//$Debug = true;
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//
		$NoiseArray = $this->GetNoiseArray($Debug);
		if ($Debug) $this->ShowData($NoiseArray,__LINE__.'---Noisearray---','',0,' ',true);
		//
		$Tokens = array_values(array_diff($Tokens,$NoiseArray));
		if ($Debug) $this->ShowData($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		return $Tokens;
	}
	/////////////////////////////////////////////////////////
	private function ChangeByPriority ($Sender,$String,$Debug = false) {
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		}
		//
		$PriorityList = c('Plugins.DiscussionTopic.Prioritylist','');
		$PriorityArray = $this->GetExplode($PriorityList,0);
		//if ($Debug) $this->ShowData($PriorityArray,__LINE__.'---Priorityarray---','',0,' ',true);
		//if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		// Priority phrases replacement
		foreach($PriorityArray as $Entry => $Priority) { 
			$SaveString = $String;
			$String = preg_replace('/\b' . preg_quote($Priority) . '\b/i', '"'.$Priority.'"',$String);
			//if ($Debug) $this->ShowData($Priority,__LINE__.'---Priority---','',0,' ',true);
			//if ($Debug) $this->ShowData($SaveString,__LINE__.'---Savestring---','',0,' ',true);
			if ($SaveString != $String) {			//Substitution was made
				if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
				$String = $this->GetQuoted($Sender,$String,$Debug);
				if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
				return $String;
			}
		}
		return $String;
	}
	/////////////////////////////////////////////////////////
	private function ChangeByAcronym ($Sender,$String,$Debug = false) {
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		}
		//
		$Acronyms = c('Plugins.DiscussionTopic.Acronyms','');
		$LocalAcronymArray = $this->GetExplodeByKey($Acronyms,0);
		$GlobalAcronymArray = t('DiscussionTopicAcronyms');
		$AcronymArray = array_merge($LocalAcronymArray,$GlobalAcronymArray);	
		$AcronymArray = array_change_key_case($AcronymArray,CASE_LOWER);
		if ($Debug) $this->ShowData($AcronymArray,__LINE__.'---Acronymarray---','',0,' ',true);
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		// Acronym replacement
		foreach($AcronymArray as $Acronym => $Replacement) { 
			$String = preg_replace('/\b' . preg_quote($Acronym) . '\b/i', $Replacement,$String);
//			if ($Debug) $this->ShowData($Acronym,__LINE__.'---Acronym---','',0,' ',true);
//			if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		}
		// 
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		return $String;
    }
	/////////////////////////////////////////////////////////
	public function Setup () {
        $this->Structure();
        $this->InitializeConfig();
    }
	/////////////////////////////////////////////////////////
	public function onDisable () {
        $this->Structure();
    }
	/////////////////////////////////////////////////////////
	private function InitializeConfig () {
        // Default configuration variables
		$this->SetVariableDefault('Plugins.DiscussionTopic.Noisewords','Vanilla,forum');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Acronyms','btn=button,config=configuration,db=database');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Sigwords','2');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Paneltitle','Related Discussions');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Analyzer',array('1'));
		$this->SetVariableDefault('Plugins.DiscussionTopic.Extractor','words');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Testtitle','How does the "Discussion Topic" plugin works it\'s magic?');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Maxrowupdates',10);
		$this->SetVariableDefault('Plugins.DiscussionTopic.Updategen',1);
		$this->SetVariableDefault('Plugins.DiscussionTopic.Parttialupdate',0);
		$this->SetVariableDefault('Plugins.DiscussionTopic.HighupdateID',0);
		$this->SetVariableDefault('Plugins.DiscussionTopic.Mode',3);
		$this->SetVariableDefault('Plugins.DiscussionTopic.Showmenu',true);
		$this->SetVariableDefault('Plugins.DiscussionTopic.TopAnswerMode',false);
		SaveToConfig('Plugins.DiscussionTopic.RedoConfig',false);
		SaveToConfig('Plugins.DiscussionTopic.FirstSetupDone',false);
		SaveToConfig('Plugins.DiscussionTopic.ReportRowNumber',0);
		SaveToConfig('Plugins.DiscussionTopic.SortByTopic',0);
    }
	/////////////////////////////////////////////////////////
	public function Structure () {
        Gdn::database()->structure()
            ->table('Discussion')
            ->column('Topic', 'varchar(100)', true)
			->column('TopicAnswer',  'tinyint(1)', '0')
            ->set();
    }
	/////////////////////////////////////////////////////////
	public function AssetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');	
    }
	/////////////////////////////////////////////////////////
	//Plugin Settings
    public function SettingsController_DiscussionTopic_create ($Sender, $Args) {
		$Debug = false;
		$Sender->permission('Garden.Settings.Manage');
		if ($Debug) echo __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
		//if ($Debug) $this->ShowData($Args,__LINE__.'---Args---','',0,' ',true);
		$Sender->addCssFile('pluginsetup.css', 'plugins/DiscussionTopic');
        $Sender->addSideMenu('dashboard/settings/plugins');
		//
		$PluginInfo = Gdn::pluginManager()->getPluginInfo('DiscussionTopic');
		//if ($Debug) this->ShowData($PluginInfo,__LINE__.'---Plugininfo---','',0,' ',true);
		$Constants = $PluginInfo['PluginConstants'];
		$MaxBatch = $Constants['Maxbatch'];
		//
		if (c('Plugins.DiscussionTopic.RedoConfig',false)) {
			$this->InitializeConfig();
		}
		//
		$IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup',false);
		$GotError =false;
		$TopWarning = '';
		$FieldErrors = '';
		$FeedbackArray = array();
		//
		$AnalyzerArray = Array(0 => '?', 1 => 'PosTagger', 2 =>'TextRazor');
		//
		$ModeArray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
		//
		$ConfigurationModule = new ConfigurationModule($Sender);
		$Validation = new Gdn_Validation();
		$ConfigurationModel = new Gdn_ConfigurationModel($Validation);
		$Sender->Form->SetModel($ConfigurationModel);
		if ($Debug) echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
		//
		if ($Sender->Form->authenticatedPostBack()) {
			if ($Debug) echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
			$Saved = $Sender->Form->showErrors();
			$Saved = $Sender->Form->Save();
			$FormPostValues = $Sender->Form->formValues();
			$Sender->Form->SetData($FormPostValues);
			$Validation = new Gdn_Validation();
			$Data = $Sender->Form->formValues();
			//if ($Debug) $this->ShowData($Sender->Form,__LINE__.'---Form---','',0,' ',true);	
			//if ($Debug) $this->ShowData($Data,__LINE__.'---Data---','',0,' ',true);	
			//
			$NoiseWords = strtolower(getvalue('Plugins.DiscussionTopic.Noisewords',$Data));
			//		Flag to link DiscussionTopics
			$Acronyms = getvalue('Plugins.DiscussionTopic.Acronyms',$Data);
			//
			$Analyzer = getvalue('Plugins.DiscussionTopic.Analyzer',$Data);
			//
			$PriorityList = getvalue('Plugins.DiscussionTopic.Prioritylist',$Data);
			//
			$Mode = getvalue('Plugins.DiscussionTopic.Mode',$Data);
			//
			$SigWords = getvalue('Plugins.DiscussionTopic.Sigwords',$Data);
			//
			$TestTitle = getvalue('Plugins.DiscussionTopic.Testtitle',$Data);
			//
			$Paneltitle = getvalue('Plugins.DiscussionTopic.Paneltitle',$Data);
			//
			$MaxRowUpdates = getvalue('Plugins.DiscussionTopic.Maxrowupdates',$Data);
			// Max batch size is a plugininfo constant
			$FieldErrors .= $this->CheckField($Sender,$MaxRowUpdates,
							Array('Integer' => ' ','Min' => '2','Max' => $MaxBatch),
							'Number of discussions to update in the Table Update batch ','Plugins.DiscussionTopic.Maxrowupdates');
			//
			$FieldErrors .= $this->CheckField($Sender,$Paneltitle,
							Array('Required' => 'Title'),
							'Side Panel Title','Plugins.DiscussionTopic.Paneltitle');
			//
			$AnalyzerName = $AnalyzerArray[$Analyzer+1];
			SaveToConfig('Plugins.DiscussionTopic.Analyzername',$AnalyzerName);
			//
			$ModeName = $ModeArray[$Mode+1];
			SaveToConfig('Plugins.DiscussionTopic.ModeName',$ModeName);
			//if ($Debug) $this->ShowData($Mode,__LINE__.'---Mode---','',0,' ',true);
			//if ($Debug) $this->ShowData($ModeName,__LINE__.'---ModeName---','',0,' ',true);			
			//
			$FieldErrors .= $this->CheckField($Sender,$SigWords,
							Array('Required' => 'Integer','Min' => '2','Max' => '4'),
							'Number of keywords','Plugins.DiscussionTopic.Sigwords');
			if ($Debug) $this->ShowData($FieldErrors,__LINE__.'---FieldErrors---','',0,' ',true);
			//
			if ($Debug) echo '<br>'.__LINE__.'FieldErrors:'.$FieldErrors;
			//
			if ($FieldErrors != '') {
				$GotError=true;
				$Sender=$Validation->addValidationResult('Plugins.DiscussionTopic.CategoryNums', ' ');
				$TopWarning = t('Errors need to be corrected. Incomplete settings saved');
				//**Gdn::controller()->informMessage($TopWarning);//,'DoNotDismiss');
			}
			if (!$Validation->validate($FormPostValues)) $GotError=true;
			if ($GotError) {		
				if ($Debug) echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
				SaveToConfig('Plugins.DiscussionTopic.IncompleteSetup',true);
				$Sender=$Validation->addValidationResult('Plugins.DiscussionTopic.SearchBody', ' ');
			} else {
				// No errors
				SaveToConfig('Plugins.DiscussionTopic.FirstSetupDone',true);
				if ($Debug) echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
				SaveToConfig('Plugins.DiscussionTopic.IncompleteSetup',false);
				if ($TestTitle != '') {
					SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
					$Simulate = $ModeName;
					if ($ModeName == 'Manual') $Simulate = 'Progressive';
					$Extract = $this->GetSubject($Sender,$TestTitle,$Simulate,$Debug);
					$ExtractTitle = '';
					if ($Extract == '' ) {
						if ($ModeName == 'Deterministic') 	{						//Deterministic mode
							$ExtractNote = 'The test discussion name did not generate any title - Deterministic mode is used and double-quoted texts not found';
						} elseif ($ModeName == 'Manual') 	{
							$ExtractNote = 'No results can be shown in manual mode.';
						} elseif ($ModeName == 'Heuristic') {
							$ExtractNote = 'The test discussion name did not generate any title - all the words were noise words.';
						} elseif ($ModeName == 'Progressive') {
							$ExtractNote = 'The test discussion name did not generate any title - quoted texts not found and all the words were noise words.';
						} else {
							$ExtractNote = 'The test discussion name did not generate any title - check your current settings.';
						}
					} else {
						$ExtractTitle = 'Title="Test result:'.$Extract.'"';
						$ExtractNote =	wrap('Test title:<b>'.$TestTitle.'</b><br>','span class=Error').
										wrap($Simulate . ' mode generated topic: <b>'.$Extract.'</b>','span class=Error');
						}
					$FeedbackArray['SimulatedTitle'] = $ExtractNote;
					$FeedbackArray['SimulatedNote'] = '<a href="#test" class="SettingTest" '.$ExtractTitle.'>🔴 Click to jump to the test results section</a>';
					
					SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
					//$AddError = $Sender->Form->addError(wrap($ExtractNote,'span class=SettingTest'),'Plugins.DiscussionTopic.Testtitle');	
				}
			}
			//
		} else {			// Not postback
			//SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
			if (c('Plugins.DiscussionTopic.IncompleteSetup')) 
				$TopWarning = 'Previously saved settings are incomplete/invalid.  Review and save correct values.';
			$Sender->Form->SetData($ConfigurationModel->Data);
        }
		//
		$PluginConfig = $this->SetConfig($Sender,$FeedbackArray,$Debug);// Array('TopWarning' => $TopWarning),$Debug);
		$ConfigurationModule->initialize($PluginConfig);
		$ConfigurationModule->renderAll();
    }
	///////////////////////////////////////////////////////// 
	// Function to handle future saving of arrary as lists  (for future expansion) 
   private function SetVariableDefault($Variable,$Default = '') {
	   $Value = c($Variable,$Default);
	   SaveToConfig($Variable,$Value);
   }
	///////////////////////////////////////////////////////// 
   private function GetExplode($String,$Debug) {
		if ($Debug) echo '<br><b>'.__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].'</b>';
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		//$String = str_replace("'", "\'", $String);
		$Array = explode(',',$String);
		$Array = array_map('trim', $Array);
		if ($Debug) $this->ShowData($Array,__LINE__.'---Array---','',0,' ',true);
		return $Array;
   }
	///////////////////////////////////////////////////////// 
   private function GetExplodeByKey($String,$Debug) {
		if ($Debug) echo '<br><b>'.__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].'</b>';
		if ($Debug) $this->ShowData($String,__LINE__.'---String---','',0,' ',true);
		//$String = str_replace("'", "\'", $String);
		$Array = explode(',',$String);
		$Array = array_map('trim', $Array);
		
		for ($i = 0;$i<count($Array); $i++ ) {
			list($Key, $Value) = explode('=', $Array[$i]);
			$Key = strtolower(trim($Key));
			$Keyarray[$Key] = strtolower(trim($Value));
		}
		
		if ($Debug) $this->ShowData($Keyarray,__LINE__.'---Acronymarray---','',0,' ',true);
		return $Keyarray;
   }
	/////////////////////////////////////////////////////////
	// Set Confogiration Array
	private function SetConfig($Sender,$Errors = Array(),$Debug) {
		$Separator = '<span class=SettingSep>&nbsp</span>';
		$Headstyle = '<span class=SettingHead>#&nbsp&nbsp';
		$Subhstyle = 'span class=SettingSubh';
		$Textstyle = '<span class=SettingText>';
		$Warnstyle = '<span class=SettingWarning>';
		$Errorstyle = '<span class=SettingError>';
		$Squeeze = '<span class=Settingsqueeze> </span>';
		$Notestyle = '<span class=SettingNote>';
		$Topmessage = '';
		if (trim($Errors['TopWarning'])) {
			$Topmessage .= $Warnstyle.$Errors['TopWarning'].'</span>';
		//} else {
			//$Topmessage = wrap(wrap('See the readme file for more detailed description.','div class=SettingNote'),'div');
		}
		$SimulatedNote = trim($Errors['SimulatedNote']);
		if ($SimulatedNote != '') $SimulatedNote = wrap($SimulatedNote,'span  class=SettingAsideN');
		$SimulatedTitle = trim($Errors['SimulatedTitle']);
		if ($SimulatedTitle != '') {
			$SimulatedTitle = wrap($SimulatedTitle,'div class=SettingTest id="#test"');
			SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
			
		}
		//
		$PluginInfo = Gdn::pluginManager()->getPluginInfo('DiscussionTopic');
		//if ($Debug) this->ShowData($PluginInfo,__LINE__.'---Plugininfo---','',0,' ',true);
		$Constants = $PluginInfo['PluginConstants'];
		$Title = Wrap($PluginInfo['Name'].'-'.' Version '.$PluginInfo['Version'].' Settings','div class=SettingHead');
		//
		$LocalPlace = '/plugin/DiscussionTopic/locale/en-CA/definitions.php';
		//
		$Mode = 1+c('Plugins.DiscussionTopic.Mode',0); 
		$ModeArray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
		$ModeName = $ModeArray[$Mode];
		//if ($Debug) $this->ShowData($ModeName,__LINE__.'---ModeName---','',0,' ',true);
		//
		$UpdateGen = c('Plugins.DiscussionTopic.Updategen',1);
		$Continueurl = '' ;
		$CompletedNote = '';
		$Initializetext = '';
		if (c('Plugins.DiscussionTopic.FirstSetupDone',false)) $CompletedNote = wrap('🔴 Previous update batch (#'.$UpdateGen.') completed.','span  class=SettingAsideRedN  ');
		if (c('Plugins.DiscussionTopic.Parttialupdate',false)) {
			$Continueurl = wrap('Click'.Anchor('continue','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0','Button  ',Array('target'=>"New")).
					' to <b>continue</b> batch '.$UpdateGen.
					' update to process discussion titles not processed by the previous batch (remember, only '.c('Plugins.DiscussionTopic.Maxrowupdates').' records are handled at a time)','span  class=SettingAsideN  ');
			$CompletedNote = '';
		}
		$Clearurl = wrap('Click'.Anchor('remove','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0,&clear=1','Button  ',Array('target'=>"New")).
					' to <b>delete </b> any previously saved titles.<b>Use with care!</b>','span  class=SettingAsideN');
		
		if (c('Plugins.DiscussionTopic.Cleared',false)) {
			$Clearurl = '';
			$CompletedNote = $CompletedNote = wrap('▷ Topic data cleared.','span  class=SettingAsideRedN  ');
		}
		$Restarturl = wrap('Click'.Anchor('Start','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0 ','Button   ',Array('target'=>"New")).
					' to <b>start a new update batch</b> (fresh analysis of discussion titles)','span  class=SettingAsideN  ').$Clearurl;
		//
		if (c('Plugins.DiscussionTopic.IncompleteSetup',false) | $ModeName == 'Manual'  )  {
			$Continueurl = '' ;
			$Restarturl = '';
			$CompletedNote = '';
		}
		if ($Continueurl.$Restarturl != '') $Initializetext = wrap(wrap('<b>Discussion Table Update</b>','h3').wrap(
							wrap('Titles are attached to discussions when the discussion body (not comments) are saved.','span  class=SettingAsideN').
							wrap('You can use this Table Update process to attach titles to discussions without topics (e.g. discussions created before plugin activation) or to reconstruct the topics when you change some of the settings.','span  class=SettingAsideN').
							$CompletedNote.wrap('The following update options are available:','span  class=SettingAsideU').
							$Continueurl.$Restarturl,'span  class=SettingAsideN'),'div class=SettingAside');
		$ConstructionNotes = wrap(wrap('<b>Topic Construction notes</b>','h3').wrap(
							wrap('In Deterministic mode Priority phrases add quotes to the phrases in the discussion title.  Then <i>the first</i> double-quoted string within the title is picked as the topic.','span  class=SettingAsideN').
							wrap('To increase the matching of related discussions, Heuristic mode uses simulated word roots ("Stems"). The resulting topic may seem mistyped - this is not a bug.','span  class=SettingAsideN').
							wrap('Heuristic process is inherently implerfect, especially with free form user input.','span  class=SettingAsideN').
							' ','span '),'div class=SettingAside');
		$NoiseNotes = wrap(wrap('<b>Nose word note</b>','h3').wrap(
							wrap('The noise words specified on this screen are adeed to the noise word definitions in the plugin LOCALE file. For English, the locale is in: '.$LocalPlace,'span  class=SettingAsideN').
							' ','span '),'div class=SettingAside');
		$AcronymsNotes = wrap(wrap('<b>Substitute Words note</b>','h3').wrap(
							wrap('The substitute words specified on this screen are adeed to the substitute word definitions in the plugin LOCALE file mentioned above.','span  class=SettingAsideN').
							wrap('Hint: If you substitute the word with a double-quoted phrase, it will behave as a Priority Phrase.','span  class=SettingAsideN').
							' ','span '),'div class=SettingAside');							
		$CustomizationGuide	= wrap('○ Click '.Anchor('here','plugin/DiscussionTopic/DiscussionTopicGuide',Array('class'=>"NoPopup",'target'=>"Popup")).
									' for the Customization Guide.','span  class=SettingAsideN');
		$GeneralNotes = wrap(wrap('<b>General Notes</b>','h3').wrap(
							$SimulatedNote.$CustomizationGuide.
							wrap('○ With the exceptions noted below, the Topic analysis takes place when a discussion is saved, so the performance impact should be small.','span  class=SettingAsideN').
							wrap('○ By limiting the processing to specific categories you can further minimize the performance impact.','span  class=SettingAsideN').
							wrap('○ If you have a process that saves many discussions at a time (e.g. feed imports) that process will be impacted.','span  class=SettingAsideN').
							wrap('○ Another example of saving many discussions at a time is the (re)updating of many previosly saved discussions through the <b>Table Update</b> process below.  For that reason the update process is done in batches and you can sepecify the number of records in the update batch.','span  class=SettingAsideN').
							' ','span '),'div class=SettingAsideWide');
		$Snippets	=		wrap(wrap('<b>Visibility Options Examples</b>','h3').
							wrap('Side Panel','span  class=SettingAsideSubhead').
							wrap('<img src="../plugins/DiscussionTopic/sidepanelsnippet.jpg" class=Snippet>').
							wrap('Discussion List','span class=SettingAsideSubhead').
							wrap('<img src="../plugins/DiscussionTopic/discussionlistmetaareasnippet.jpg" class=Snippet>').
							wrap('Discussion Meta Area','span class=SettingAsideSubhead').
							wrap('<img src="../plugins/DiscussionTopic/discussionbodymetaareasnippet.jpg" class=Snippet>'),'div class=SettingAsideWide');
		//
		$WarnGarden = '';
		$Sidepanelnote = 'FYI, if you use the ModuleSort plugin, the internal name of the sidepanel is \'DiscussionTopicModule\'.';
		// Get all categories.
		$Categories = CategoryModel::categories();
		// Remove the "root" categorie from the list.
		unset($Categories[-1]);
		//
		$AnalyzerArray = Array(0 => '?', 1 => 'PosTagger', 2 =>'TextRazor');
		unset($AnalyzerArray );
		//
		$PluginConfig = array(
		//
			'Plugins.DiscussionTopic.CategoryNums' => array(
				  'Control' => 'CheckBoxList',
				  'LabelCode' => $Title,
				  'Items' => $Categories,
				  'Description' => $Topmessage.$GeneralNotes.'<b>Select the categories where this plugin is active </b><br>(no selection enables all categories):',
				  'Options' => array('ValueField' => 'CategoryID', 'TextField' => 'Name','Class' => 'Categorylist ')
				),
		//
			'Plugins.DiscussionTopic.Paneltitle' => array(
			'Control' => 'TextBox',
			'Description' => 	$Snippets.wrap('<b>Side Panel Title</b>','span class=SettingSubh').
								wrap('Enter the title for the side panel showing discussions with related title content:'.
								'<br>(Important: Requires "View" permission in the plugin section of "Roles and Permissions")',
								'span class=SettingText title="'.$Sidepanelnote.'"'),
			'LabelCode' => 		wrap('<b>Visibility&nbspOptions</b>',
			'span class=SettingNewSection').wrap(' ','span class=Settingsqueeze'),
			'Default' => 'Common Topic',
			'Options' => array('Class' => 'Textbox')),
		//
			'Plugins.DiscussionTopic.Showinlist' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('1. Display the discussion topic in the <i>discussion list</i> meta area',
								'span class=SettingText title="'.$Sidepanelnote.'"'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
			'Plugins.DiscussionTopic.Showindiscussion' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('2. Display the discussion topic in the <i>discussion body</i>',
								'span class=SettingText'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
			'Plugins.DiscussionTopic.ShowHeuristic' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('3. For the two options above, display <i>heuristic</i> topics if deterministic ones are not found.','span class=SettingText').
					wrap('See below Progressives topic construction.','span class=SettingTextContinue'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
			'Plugins.DiscussionTopic.Showgear' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('4. Provide the ability to set the discussion topic through a link in the discussion option list (the "gear" <b>❅</b>)',
								'span class=SettingText').
					wrap('(Important: Requires "manage" permission in the plugin section of "Roles and Permissions".)','span class=SettingTextContinue'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
			'Plugins.DiscussionTopic.TopAnswerMode' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('5. Provide the ability to mark discussions as "Top Topics". Top topics will be pushed to the top of the side panel.','span class=SettingText').  				 wrap('Useful when discussions offer solutions to frequently asked questions','span class=SettingTextContinue').
							wrap('(Important: Requires options 4 above (Setting topics through the options menu).','span class=SettingTextContinue'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
			'Plugins.DiscussionTopic.Showmenu' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('Display a "Topic-Search" menu in the menu bar',
								'span class=SettingText'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
			'Plugins.DiscussionTopic.Mode' => array(
			'Control' => 'Radiolist',
			'Items' => Array(	
				'1. Manual - Topic must be set manually through the options menu (Obviously, Visibility option 4 above must be enabled).',
				'2. Deterministic - Pick a "Double-Quoted-Text" or "Priority Phrases" (see below) in the discussion Name as the discussion topic.',
				'3. Heuristic - Attempt language analysis of the discussion name to determine the discussion topic.',
				'4. Progressive - Try the Deterministic approach and if double-quoted string is not found try Heuristics.'),
			'Description' => 	$ConstructionNotes.wrap('Select one of these topic construction modes:','span class=SettingText'),
			'LabelCode' => 		wrap('<b>Discussion&nbspTopic&nbspConstruction&nbspMode</b>','span class=SettingNewSection'),			
			'Default' => '4',
			'Options' => array( 'Class' => 'RadioColumn ')),
		//
			'Plugins.DiscussionTopic.Prioritylist' => array(
			'Control' => 'TextBox',
			'Description' => 	wrap('Optionally enter priority phrases to be selected as topics when they appear <b>without quoted</b> in the 	discussion title.','span class=SettingText').
								wrap('When Discussion Topic Construction mode 1 or 3 are active, discussion title analysis will behave','span class=SettingText').
								wrap('as if these phrases were entered with double quotes. (Optionally enter comma delimited phrases):','span class=SettingText'),
			'LabelCode' => 		wrap('<b>Priority&nbspPhrases </b>','div class=SettingHead').wrap(' ','span class=Settingsqueeze'),
			'Default' => 'something has gone wrong,advanced editor',
			'Options' => array('MultiLine' => true, 'class' => 'TextBox')),
		//
			'Plugins.DiscussionTopic.Noisewords' => array(
			'Control' => 'TextBox',
			'Description' => 	wrap('Optionally enter comma-separated words to be ignored in the discussion title analysis:','span class=SettingText'),
			'LabelCode' => 		$NoiseNotes.wrap('<b>Ignorable&nbspWords </b>(Noise words)','div class=SettingHead').wrap(' ','span class=Settingsqueeze'),
			'Default' => 'and,or,if',
			'Options' => array('MultiLine' => true, 'class' => 'TextBox')),
		//
			'Plugins.DiscussionTopic.Acronyms' => array(
			'Control' => 'TextBox',
			'Description' => 	wrap('Enter phrases to be substituted for others. Format: acronym=phrase,acronym=phrase','span class=SettingText'),
			'LabelCode' => 		$AcronymsNotes.wrap('<b>Substitute Words</b> (Acronyms)','div class=SettingHead').wrap(' ','div class=Settingsqueeze'),
			'Default' => 'btn=button,config=configuration,db=database',
			'Options' => array('MultiLine' => true, 'class' => 'TextBox')),
		//
			'Plugins.DiscussionTopic.Sigwords' => array(
			'Control' => 'TextBox',
			'Description' => 	wrap('Enter the number of keywords to use for matching similar discussion titles.','span class=SettingText').
								wrap('A high number reduces matching chances (since all keywords must exist in the matching discussion titles)','span class=SettingText').
								wrap('A small number increases the chances for false positives.  Select a number between 2 to 4:','span class=SettingText'),
			'LabelCode' => 		wrap('<b>Number of Keywords</b> (Only if Heuristic Analysis is active)','span class=SettingHead').wrap(' ','span class=Settingsqueeze'),
			'Default' => '2',
			'Options' => array('Class' => 'Textbox')),
		/***   This is for future use ***
			'Plugins.DiscussionTopic.Analyzer' => array(
			'Control' => 'Radiolist',
			'Items' => Array('TextRazor (see www.textrazor.com for free or paid license)','PosTagger (internal and free)'),
			'Description' => 	wrap('Select one of these options (see the readme file for differences):','span class=SettingText'),
			'LabelCode' => 		wrap('<b>Grammatical Analizer</b>  (Only if Heuristic Analysis is active)','span class=SettingHead').wrap(' ','span class=Settingsqueeze'),
			'Default' => 'TextRazor'),
		//
		//	'Plugins.DiscussionTopic.Extractor' => array(
		//	'Control' => 'TextBox',
		//	'Description' => 	wrap('Extractor Phrase (don\'t change unless you modify the plugin code):','span class=SettingText'),
		//	'LabelCode' => 		wrap('<b> TextRazor Options:</b>','span class=SettingHead').wrap(' ','span class=Settingsqueeze'),
		//	'Default' => 'words'),
		//
			'Plugins.DiscussionTopic.TextRazorKey' => array(
			'Control' => 'TextBox',
			'Description' => 	wrap('If you selected "Textrazor" as your Grammatical Analizer, specify the TextRazor API key (See www.textrazor.com for free or paid API key):','span class=SettingText'),
			'LabelCode' => 		wrap(' ','span class=Settingsqueeze'),
			'Default' => '?'),
		***/
			'Plugins.DiscussionTopic.Testtitle' => array(
			'Control' => 'TextBox',
			'Description' => 	wrap('<b>After</b> you save your settings you can see the Topic determined by the saved options. <br>Optionally enter a test discussion title below:','div class=SettingText'),
			'LabelCode' => 		wrap('Discussion Testing:','span class=SettingHead id="test"'),
			'Options' => array('MultiLine' => false, 'class' => 'TextWideInputBox'),
			'Default' => ' '),
		//
			'Plugins.DiscussionTopic.Maxrowupdates' => array(
			'Control' => 'TextBox',
			'LabelCode' => 	$SimulatedTitle.$Initializetext.wrap('<b>Table Update-Adding topics to previously saved discussions</b>','span class=SettingHead'),
			'Description' => 		wrap('Enter the number of rows to update in a single update batch (See the readme file):','div class=Settinghead'),
			'Default' => 1000,
			'Options' => array('Class' => 'Textbox')),
		//
		//
		//
		);
	
		//if ($Debug) $this->ShowData($XPluginConfig,__LINE__.'---XPluginConfig---','',0,' ',true);
		return $PluginConfig;
	}
	///////////////////////////////////////////////////////// 
// Check Configuration Settings
	private function CheckSettings($Sender,$Type='All',$Debug) {
		 if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
		//
		$Warn = '';
		$Error = '';
		//Get the menu filled variables
		$Data = $Sender->Form->formValues();
		$NoiseWords = getvalue('Plugins.DiscussionTopic.Noisewords',$Data);
		$Acronyms = getvalue('Plugins.DiscussionTopic.Acronyms',$Data);
		//
		if ($Type == 'All' || $Type == 'Errors') {

		}
		if ($Type == 'All' || $Type == 'Warnings') {

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
	///////////////////////////////////////////////////////// 
// Check speicific field, return error message 
	private function CheckField($Sender,$Field=false,$Checks=Array('Required'),$Title = 'Field', $Fieldname = '', $Style = 'span class=SettingError',
							$Debug = false) {
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
						} elseif ($Value == 'Title' && preg_match("/[^A-Za-z,.\s]/", $Field)) { 
							$Errormsg='must be valid words';
						} elseif ($Value == 'Alpha' && preg_match("/[0-9]+/", $Field)) {
							$Errormsg='must be alphabetic';
						}
					}
				} elseif  (($Test == 'Integer' | $Test == 'Min' | $Test == 'Max') && !ctype_digit($Field)) { 
					$Errormsg='must be an integer';
				} elseif  (($Test == 'Numeric' | $Test == 'Min' | $Test == 'Max') && !is_numeric($Field)) { 
					$Errormsg='must be numeric';
				} elseif  ($Test == 'Title' && preg_match("/[^A-Za-z,.\s]/", $Field)) { 
					$Errormsg='must be valid words';
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
			  if ($Fieldname != '') $AddError = $Sender->Form->addError($Errormsg, $Fieldname);
		}
		//echo '<br>'.__line__.$Errormsg;
		return $Errormsg;
	}
	/////////////////////////////////////////////////////////
	//Get topic to show the user (subject to authorization)
	private function GetTopicToShow($Sender, $Topic, $Location = 'inlist', $Debug = false) {
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		if (trim($Topic) == '') return '';
		if (!Gdn::session()->checkPermission('Plugins.DiscussionTopic.View')) return;
		if (substr($Topic,0,1) == '"') {
			if (!c('Plugins.DiscussionTopic.'.$Location,false)) return '';
		} else {
			if (!c('Plugins.DiscussionTopic.ShowHeuristic',false)) return '';
		}
		return $Topic;
	}
	/////////////////////////////////////////////////////////
	//Enable to display the keywords within the discussion body
	public function DiscussionController_AfterCommentFormat_Handler($Sender) {
		$Debug = false;
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//  
        $Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');
		//
        $Object = $Sender->EventArguments['Object'];
		//$FormatBody = $Object->FormatBody;
		//$CommentID = getValueR('CommentID',$Object);
		$DiscussionID = getValueR('DiscussionID',$Object);
		$Showtopic = $this->GetTopicToShow($Sender, getValueR('Topic',$Object), 'Showindiscussion', $Debug);
		$TopicAnswer = getValueR('TopicAnswer',$Object);
		echo $this->DisplayTopicAnswer($Sender, $Showtopic, $TopicAnswer, $DiscussionID, 'Discussion Topic','','','TopicInPost ','div' );
	}
	/////////////////////////////////////////////////////////
	private function DisplayTopicAnswer($Sender, $Topic, $TopicAnswer, $DiscussionID, $Prefix = 'Discussion Topic', $DefaultEmphasize = '○',$Emphasize = '◉',$Style = 'TopicInPost') {
		if ($Topic== '') {
			return wrap(wrap(' ','span class=Emphasize id=Emphasize'.$DiscussionID).' ','span '.'id=Topic'.$DiscussionID);
		}
		$Anchor = anchor($Topic,'/plugin/DiscussionTopic/DiscussionTopicSearch?s='.$Topic,array('Title' => t('click to view discussions with matching topics')));
		$AnswerMsg = $Topic;
		if ($Prefix != '') $AnswerMsg = t($Prefix).':'.$Anchor;//$Topic;
		if ($TopicAnswer) {
			$Title = 'Title="'.t('This topic is marked as top topic').'"';
		} else {
			$Emphasize = $DefaultEmphasize;
			$Title = '';
		}
		
		return wrap(wrap($Emphasize,'span class=Emphasize id=Emphasize'.$DiscussionID).$AnswerMsg,'span class='.$Style.' '.'id=Topic'.$DiscussionID.' '.$Title);
		//
		
		;
    }
	/////////////////////////////////////////////////////////
	public function PostController_afterDiscussionFormOptions_handler($Sender,$Args) {
		$Debug = false;
        // 
		$Discussion = getvalue('Discussion', $Sender->Data);
		$TopicAnswer = getvalue('TopicAnswer', $Discussion);
		$Topic = getvalue('Topic', $Discussion);
		$Showtopic = $this->GetTopicToShow($Sender, $Topic, 'Showindiscussion', $Debug);
		if ($Showtopic == '') return;
		echo $this->DisplayTopicAnswer($Sender, $Showtopic, $TopicAnswer, $Discussion->DiscussionID, 'Saved discussion topic','',' ','TopicInPost ' );
    }
	/////////////////////////////////////////////////////////
	public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
		$Debug = false;
		//if (!c('Plugins.DiscussionTopic.Showrelated',false)) return;
		$Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');	
        $Limit = c('Plugins.DiscussionTopic.Panelsize',8);
        $ModuleToAdd = new DiscussionTopicModule($Sender);
		$Sender->AddModule($ModuleToAdd, 'Panel' ,$Sender);
        $ModuleToAdd->GetAlso($Sender->data('Discussion.DiscussionID'), $Limit, $Debug);
	}
	/////////////////////////////////////////////////////////
	public function categoriesController_afterCountMeta_handler($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		$this->ListInline($Sender,$Args['Discussion'],$Debug);
	}
	/////////////////////////////////////////////////////////
	public function discussionsController_afterCountMeta_handler($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}  
        //
		$this->ListInline($Sender,$Args['Discussion'],$Debug);
	}
	private function ListInline($Sender,$Discussion,$Debug = false) {
		//if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
		$Showtopic = $this->GetTopicToShow($Sender,$Discussion->Topic, 'Showinlist', $Debug);
		echo $this->DisplayTopicAnswer($Sender, $Showtopic, $Discussion->TopicAnswer, $Discussion->DiscussionID, 'Discussion topic','','','TopicInList ' );
	}
	///////////////////////////////////////////////////////// 
	// Add gear option
	public function discussionsController_discussionOptions_handler($Sender, $Args) {
		$this->AddToGear($Sender, $Args);
	}
	///////////////////////////////////////////////////////// 
	// Add gear option
	public function CategoriesController_DiscussionOptions_Handler($Sender, $Args) {
		$this->AddToGear($Sender, $Args);
	}
	///////////////////////////////////////////////////////// 
	// Add gear option
	public function Base_DiscussionOptions_Handler($Sender, $Args) {
		if (!Gdn::session()->checkPermission('Plugins.DiscussionTopic.Manage')) return;
		if (!c('Plugins.DiscussionTopic.Showgear',false)) return '';
		//          
		$Discussion = $Args['Discussion'];
		$DiscussionID = $Discussion->DiscussionID;
		$Text='Set Discussion Topic';
		$SimpleKey =(367+Gdn::Session()->UserID); 
		$Encode = $DiscussionID ^ $SimpleKey;
		$Url = '/dashboard/plugin/DiscussionTopic/DiscussionTopicSetTopic/?D='.$DiscussionID.'&S='.$Encode;
		$Css = 'Popup SetTopiccss';
		//if ($Debug) $this->ShowData($Url,'---Url---','',0,' ',true);
		// 
		$Args['DiscussionOptions']['DiscussionTopic'] = array('Label' => $Text,'Url' => $Url,'Class' => $Css);
	}
	///////////////////////////////////////////////////////// 
	// Function to add the function into the gear
	private function AddToGear($Sender, $Args) {
		$Debug = false;
		if (!Gdn::session()->checkPermission('Plugins.DiscussionTopic.Manage')) return;
		if (!c('Plugins.DiscussionTopic.Showgear',false)) return '';
		$Discussion = $Sender->EventArguments['Discussion'];
		// If limited to specific category numbers and discussion is not listed then exit
		$CategoryNums = c('Plugins.DiscussionTopic.CategoryNums');
		if ($Debug) $this->ShowData($CategoryNums,'---Catnums---','',0,' ',true);
		if ($CategoryNums != "") {  //Limited category list?
			if (!in_array($Discussion->CategoryID, $CategoryNums)) {	//Not in the list?
				if ($Debug) //**Gdn::controller()->informMessage($this->ShowData($CategoryID,__LINE__.'---CategoryID---','',0,' ',true));
				return;
			}
		}
		//	Construct the link and add to the gear           
		$DiscussionID = $Discussion->DiscussionID;
		$Text='Set Discussion Topic';
		$SimpleKey =(367+Gdn::Session()->UserID); 
		$Encode = $DiscussionID ^ $SimpleKey;
		$Url = '/dashboard/plugin/DiscussionTopic/DiscussionTopicSetTopic/?D='.$DiscussionID.'&S='.$Encode;
		$Css = 'Popup SetTopiccss';
		$Sender->Options .= '<li>'.anchor(t($Text), $Url,$Css).'</li>';  
	}
	/////////////////////////////////////////////////////////
   	// Terminate with a severe message
	private function DieMessage($Message) {
		echo "<P>DiscussionTopic Plugin Message:<H1><B>".$Message."<N></H1></P>";
		throw new Gdn_UserException($Message);
	}
	/////////////////////////////////////////////////////////
	// Display data for debugging
	private function ShowData($Data, $Message, $Find, $Nest=0, $BR='<br>', $Echo = true) {
		//var_dump($Data);
		$Line = "<br>".str_repeat(".",$Nest*4)."<B>(".($Nest).") ".$Message."</B>";
		if ($Echo) {
			echo $Line;
		} else {
			Gdn::controller()->informMessage($Line);
		}
		$Nest +=1;
		if ($Nest > 20) {
			echo wrap('****Nesting Truncated****','h1');
			return;	
		}
		if ($Message == 'DUMP') echo '<br> Type:'.gettype($Data).'<br>';//var_dump($Data);
		if  (is_object($Data) || is_array($Data)) {
			echo ' '.gettype($Data).' ';
			if (is_array($Data) && !count($Data)) echo '....Debug:'.$Data[0];
			foreach ($Data as $Key => $Value) {
				if  (is_object($Value)) {
					$this->ShowData($Value,' '.gettype($Value).'('.count($Value).'):'.$Key.' value:','',$Nest,'<n>');
				} elseif (is_array($Value)) {
					$this->ShowData($Value,' '.gettype($Value).'('.count($Value).'):['.$Key.']: value:','',$Nest,'<n>');
				} elseif (is_bool($Value)) {
					$this->ShowData($Value,' '.gettype($Value).':'.$Key.' value[]:','',$Nest,'<n>');
				} elseif (is_string($Value)) {
					$this->ShowData($Value,' '.gettype($Value).':'.$Key.' value:','',$Nest,'<n>');
				} else {
					$this->ShowData($Value,'_'.gettype($Value).':'.$Key.'   value:','',$Nest,'<n>');
				}
			}
		} else {
			if ($Echo) 
				echo wrap('"'.$Data.'"','b');
			else Gdn::controller()->informMessage($Data,'DoNotDismiss');
			//var_dump($Data);
		}
	}
	////////////////////////////////////////////////////////
	// Filter by topic
	private function FilterTopic($Search, $SearchSubject = false, $Debug) {
		if ($Search == '') {
			Gdn::session()->stash('IBPTopicMsg', t('Error: Search argument not specified'));
			Redirect('..'.url('/discussions'));
		}
		if ($SearchSubject) $Search = $this->GetSubject($Sender,$Search,'',$Debug);
		Gdn::session()->stash('IBPTopicMsg', '');
		Gdn::session()->stash('IBPTopicSearch', $Search);
		$Title = t('Topic').':'.str_replace(array('\'', '"'), '', $Search);
		Redirect('..'.url('/discussions'));
	}
	////////////////////////////////////////////////////////
	// This hook does two things:  filtering (facilitates the search function) and 
	// Overriding discussion list sort order (for debugging/analysis only).
	public function DiscussionModel_BeforeGet_Handler($Sender) {
		//
		$Search = Gdn::session()->stash('IBPTopicSearch');
		if ($Search) {
			Gdn::session()->stash('IBPTopicSearch', '');
			$Sender->SQL->Where('d.Topic',$Search);
			Gdn::Controller()->Title(t('Searching Topic').':'.strip_tags(str_replace(array('\'', '"'), '', $Search)));
			return;
		}
		//
		$IsAdmin = Gdn::Session()->CheckPermission('Garden.Users.Edit');
		if (!$IsAdmin) return;
		if (!c('Plugins.DiscussionTopic.SortByTopic',false)) return;
		//This entire method won't be necessary in Vanila 2.3
		Gdn::Controller()->Title(wrap(Anchor('Click to remove sorting by topic','plugin/DiscussionTopic/DiscussionTopicSortbytopic',Array('class'=>"Popup")).
		'  - list sorted by discussion topic (for topic analysis purposes)','span class=Titlesortnotice'));
		$Sender->SQL->Where('d.Topic >',0);

		$GetPrivateObject = function &($Object, $Item) {
			$Result = &Closure::bind(function &() use ($Item) {
				return $this->$Item;
			}, $Object, $Object)->__invoke();
			return $Result;
		};
		$OrderBy = &$GetPrivateObject($Sender->SQL, '_OrderBys');
		//echo __LINE__.var_dump($OrderBy);
		$OrderBy[0] = 'd.Topic ASC';	//Force our own sorting order
	}
	/////////////////////////////////////////////////////////
}

//Include the PorterStemmer Stemmer
require_once('PorterStemmer.php');
//Include the PosTagger parser
require_once('PosTagger.php');
//Include the TextRazor parser
//require_once('TextRazor.php');
