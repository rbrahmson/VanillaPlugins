<?php
$PluginInfo['DiscussionTopic'] = array(
    'Name' => 'DiscussionTopic',
	'Description' => 'Add a Topic field to discussion in manual or automated way and display side panel of discussions sharing the same topic.  Useful for support forums or to increase user engagement.',
    'Version' => '2.1.1',
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'RequiredTheme' => false,
	'MobileFriendly' => false,
    'HasLocale' => true,
	'SettingsUrl' => '/settings/DiscussionTopic',
    'SettingsPermission' => 'Garden.Settings.Manage',
	'RegisterPermissions' => array('Plugins.DiscussionTopic.View','Plugins.DiscussionTopic.Manage'),
    'Author' => "Roger Brahmson",
	'GitHub' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/DiscussionTopic",
	'PluginConstants' => array('Startgen' => '100','Maxbatch' => '10000'),
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
		}
		//
		$FormPostValues = val('FormPostValues', $Sender->EventArguments, array());
		//
		$DiscussionID = val('DiscussionID', $FormPostValues, 0);
		$CategoryID = val('CategoryID', $FormPostValues, 0);
		//
		$Catnums = c('Plugins.DiscussionTopic.CategoryNums');
		if ($Debug) $this->Showdata($Catnums,'---Catnums---','',0,' ',true);
		if ($Catnums != "") {  //Limited category list?
			if (!in_array($CategoryID, $Catnums)) {	//Not in the list?
				if ($Debug) //**Gdn::controller()->informMessage($this->Showdata($CategoryID,__LINE__.'---CategoryID---','',0,' ',true));
				return;
			}
		}
		//
		$CommentID = val('CommentID', $FormPostValues, 0);
		$Name = val('Name', $FormPostValues, '');
		$Body = val('Body', $FormPostValues, '');
		$Topic = val('Topic', $FormPostValues, '');	
		if ($Debug) {
			$this->Showdata($DiscussionID,'---DiscussionID---','',0,' ',true);
			$this->Showdata($CategoryID,'---CategoryID---','',0,' ',true);
			$this->Showdata($CommentID,'---CommentID---','',0,' ',true);
			$this->Showdata($Name,'---Name---','',0,' ',true);
			$this->Showdata($Topic,'---Topic---','',0,' ',true);
			//$this->Showdata($FormPostValues,'---FormPostValues---','',0,' ',true);
		}
		//
		if (substr($Body.'          ',0,9) == "**DEBUG*!") $Debug = true;
		//
		$Extract = $this->GetSubject($Sender,$Name,'',$Debug);
		//
		if ($Debug) $this->Showdata($Extract,__LINE__.'---Extract---','',0,' ',true);
		$Sender->EventArguments['FormPostValues']['Topic'] = $Extract;
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
		//if ($Debug) $this->Showdata($Sender->RequestArgs,__LINE__.'---Sender->RequestArgs---','',0,' ',true);
		if ($Sender->RequestArg[0] == 'Search') {
			$this->Controller_DiscussionTopicSearch($Sender,$Args);
			return;
		}
		$this->Dispatch($Sender, $Sender->RequestArgs);
		//
	}
	/////////////////////////////////////////////////////////
	//Handle Discussion Topic update request
	public function Controller_DiscussionTopicSetTopic($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//$DiscussionID = $Args[0];
		$DiscussionID = intval($_GET['D']);
		if ($Debug) $this->Showdata($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
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
		$Topic = $Discussion->Topic;
		if ($Debug) $this->Showdata($Topic,__LINE__.'---Topic---','',0,' ',true);
		$this->ShowTopicForm($Sender,$Discussion,$Debug);	//Display the form
		$Topic = $Discussion->Topic;
		SaveToConfig('Plugins.DiscussionTopic.Cleared',false);
		if ($Debug) $this->Showdata($Topic,__LINE__.'---Topic---','',0,' ',true);
		Gdn::sql() ->update('Discussion')
				->set('Topic', $Topic) ->where('DiscussionID', $DiscussionID)
				->put();
		// Refresh the screen topic with the newly updated value (only if topic shown in the discussion list)
		if (c('Plugins.DiscussionTopic.Showinlist')) {			
			$Sender->JsonTarget('#Topic' . $DiscussionID,
					wrap($Topic, 'div', array('id' => 'Topic'.$DiscussionID,'class' => 'TopicInList')),'ReplaceWith');
		}
		$Sender->Render('Blank', 'Utility', 'Dashboard');
	}	
	/////////////////////////////////////////////////////////
	// Display the Topic form
	private function ShowTopicForm($Sender,$Discussion, $Debug = false) {
		if ($Debug) {
			//**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
			echo(__LINE__.'DiscussionID='.$Discussion->DiscussionID);
		}
		$Topic = $Discussion->Topic;
		$Sender->setData('Topic', $Topic);
		$Sender->setData('DiscussionName', $Discussion->Name);
		$DefaultTopic = $this->GetSubject($Sender,$Discussion->Name,'',$Debug);
		$Sender->setData('DefaultTopic',$DefaultTopic);
		//
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
		$Postback=$Sender->Form->authenticatedPostBack();
		//if ($Debug) //**Gdn::controller()->informMessage($Postback.__FUNCTION__.__LINE__);
		if(!$Postback) {					//Before form submission
			//if ($Debug) //**Gdn::controller()->informMessage("=No Postback=>".__FUNCTION__.__LINE__);	
			$Sender->Form->setValue('Topic', $Topic);
			$Sender->Form->setFormValue('Topic', $Topic);
		} else {								//After form submission	
			//if ($Debug) //**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
			$FormPostValues = $Sender->Form->formValues();
//			if (!$Validation->validate($FormPostValues)) {
//				
//			}
			if($Sender->Form->ErrorCount() == 0){
				//if ($Debug) //**Gdn::controller()->informMessage(__LINE__);
				if (isset($FormPostValues['Cancel'])) {
					if ($Debug) //**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
					return;
				}
				//
				if (isset($FormPostValues['Remove'])) {
					//if ($Debug) //**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
					$Discussion->Topic = "";
					return;
				} elseif (isset($FormPostValues['Generate'])) {
					$Discussion->Topic = $DefaultTopic;
					//if ($Debug) //**Gdn::controller()->informMessage($Topic.__LINE__);
					$Sender->Form->SetFormValue('Topic', $DefaultTopic);
					$Sender->Form->addError('Verify auto-generated topic', 'Topic');
					//return;
				} elseif (isset($FormPostValues['Save'])) {
					$Topic = $FormPostValues['Topic'];
					$Discussion->Topic = $Topic;
					//if ($Debug) //**Gdn::controller()->informMessage($Topic.__LINE__);
					return;
				}
				//
				//if ($Debug) //**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
				//$Sender->Form->SetFormValue('Topic', $Topic);
            } else {
				//if ($Debug) //**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
				$Sender->Form->setData($FormPostValues);
			}
		}
		//
		//if ($Debug) //**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
		$View = $this->getView('Topic.php');
		if ($Debug) echo wrap(__FUNCTION__.__LINE__.'View:'.$View,'div');//**Gdn::controller()->informMessage($View.__FUNCTION__.__LINE__);
		$Sender->render($View);
		//if ($Debug) //**Gdn::controller()->informMessage(__LINE__);
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
		//$this->Showdata($Sender->$Args,__LINE__.'---Args---','',0,' ',true);	
		if (!CheckPermission('Plugins.DiscussionTopic.view')) return;
		if ($Debug) $this->Showdata($_GET,__LINE__.'---$_GET---','',0,' ',true);
		foreach ($_GET as $key => $value) {		
			if ($key == "s") {	
				$Search = $value;
			} elseif ($key == "limit") {	
				$Limit = $value;
			} elseif ($key == "!DEBUG!T") {	
				$Debug = $value;
			}			
		}
		if ($Debug) $this->Showdata($Search,__LINE__.'---Search---','',0,' ',true);
		$Search = $this->ShowSearchForm($Sender,$Search,$Debug);	//Display the form
		if ($Debug) $this->Showdata($Search,__LINE__.'---Search---','',0,' ',true);
		//
		$Sender->Render('Blank', 'Utility', 'Dashboard');
	}
	/////////////////////////////////////////////////////////
	// Display the Search form
	private function ShowSearchForm($Sender,$Search, $Debug = false) {
		if ($Debug) {
			////**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
			$this->Showdata($Search,__LINE__.'---Search---','',0,' ',true);
		}
		//
		$Sender->setData('Searchstring', $Search);
		$Sender->Form = new Gdn_Form();
		$Validation = new Gdn_Validation();
		$Postback=$Sender->Form->authenticatedPostBack();
		if(!$Postback) {					//Before form submission	
			$Sender->Form->setValue('Searchstring', $Search);
			$Sender->Form->setFormValue('Searchstring', $Search);
		} else {								//After form submission	
			$FormPostValues = $Sender->Form->formValues();
			//if ($Debug) $this->Showdata($FormPostValues,__LINE__.'---FormPostValues---','',0,' ',true);
			$Data = $Sender->Form->formValues();
			//if ($Debug) $this->Showdata($Data,__LINE__.'---Data---','',0,' ',true);
			if($Sender->Form->ErrorCount() == 0){
				if (isset($FormPostValues['Cancel'])) {;
					return '';
				}
				if (isset($FormPostValues['Search'])) {
					//if ($Debug) $this->Showdata($Search,__LINE__.'---Search---','',0,' ',true);
					$Search = $FormPostValues['Searchstring'];
					//if ($Debug) $this->Showdata($Search,__LINE__.'---Search---','',0,' ',true);
					//if ($Debug) ////**Gdn::controller()->informMessage($Search.__LINE__);
					$Sender->Form->SetFormValue('Searchstring', $Search);
					if ($Debug) $this->Showdata($Search,__LINE__.'---Search---','',0,' ',true);
					if ($Search != '') {
						$Url = '/discussions/Filterdiscussion/?!msg=Topic Search&Topic=LK:'.$Search;
						$Url = '/plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=-1';
						//if ($Debug) $this->Showdata($Url,__LINE__.'---Url---','',0,' ',true);
						$Sender->Form->close();
						redirect($Url);
						//die(0);
					}
				}
				$Sender->Form->close();
				return $Search;
            } else {
				//if ($Debug) ////**Gdn::controller()->informMessage("".__FUNCTION__.__LINE__);
				$Sender->Form->setData($FormPostValues);
			}
		}
		//
		$View = $this->getView('Topicsearch.php');
		if ($Debug) echo wrap(__FUNCTION__.__LINE__.'View:'.$View,'div');
		$Sender->render($View);
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
		$Controller = $Sender->ControllerName;						//Current Controller
		$MasterView = $Sender->MasterView;
		//if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__.' Controller:'.$Controller.' MasterView:'.$MasterView."<BR>";
		$DisallowedControllers = Array('settingscontroller');	//Add other controllers if you want
		if (InArrayI($Controller, $DisallowedControllers)) return;
		//
		if (!c('Plugins.DiscussionTopic.Showmenu', false)) return;				
		if (!CheckPermission('Plugins.DiscussionTopic.View')) return;
		//if ($Debug)	echo "<br>".__FUNCTION__.'  '.__LINE__."<BR>";
		$Css = 'hijack Popup hijack';
		$Css = 'TopicSearch ';
		//>addLink('Discussions', t('Discussions'), '/discussions', false, ['Standard' => true]);
		$Sender->Menu->AddLink("Menu", t('Topic-Search'),'/plugin/DiscussionTopic/DiscussionTopicSearch?s=',false,
								 array('class' => $Css));
	} 
	/////////////////////////////////////////////////////////
	//Handle Database update request
	public function Controller_DiscussionTopicUpdate($Sender,$Args) {
		$Debug = false;
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		$Sender->permission('Garden.Settings.Manage');
		
		if (!Gdn::Session()->CheckPermission('Plugins.DiscussionTopic.Manage')) {
			echo wrap('DiscussionTopic plugin ('.__LINE__.') you need to set "Plugins.DiscussionTopic.Manage" Permission to use this function.',h1);
			return ;
		}
		//
		$Debug = intval($_GET['!DEBUG!T']);
		if ($Debug) $this->Showdata($_GET,__LINE__.'---$_GET---','',0,' ',true);
		//
		$Restart = intval($_GET['restart']);
		if ($Debug) $this->Showdata($Restart,__LINE__.'---Restart---','',0,' ',true);
		//
		$Clear = intval($_GET['clear']);
		if ($Debug) $this->Showdata($Clear,__LINE__.'---Clear---','',0,' ',true);
		if ($Clear) {
			$AlsoSql = clone Gdn::sql();	//Don't interfere with any other sql process
			$AlsoSql->Reset();
			$Updates = $AlsoSql->update('Discussion d')
					->set('d.Topic', null)
					->where('d.DiscussionID <>', 0)
					->put();
			$Rowcount = count($Updates);
			if ($Debug) $this->Showdata($Rowcount,__LINE__.'---Rowcount---','',0,' ',true);
			SaveToConfig('Plugins.DiscussionTopic.Parttialupdate',false);
			SaveToConfig('Plugins.DiscussionTopic.Cleared',true);
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',0);
			echo wrap(Wrap('Topic Data Removed!','h1').Anchor(T('<br>Click to return to the setting screen<br>'), '/settings/DiscussionTopic'),'div ');
			return;
		}
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
		if ($Debug) $this->Showdata($Limit,__LINE__.'---Limit---','',0,' ',true);
		//
		$Urllimit = 0 +$Limit;
		if ($Urllimit == 0 | !is_numeric($Urllimit)) {
			$Limit = c('Plugins.DiscussionTopic.Maxrowupdates',10);
			if ($Debug) $this->Showdata($Limit,__LINE__.'---Limit---','',0,' ',true);
		}
		$Updatecount = $this->Updateextract($Sender,$Limit,$Restart,$Debug);
		if ($Debug) $this->Showdata($Updatecount,__LINE__.'---Updatecount---','',0,' ',true);
		
	}
	/////////////////////////////////////////////////////////
	//Update old entries with the extract.
	public function Updateextract($Sender, $Limit = 10, $Restart = false, $Debug = false) {
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->Showdata($Limit,__LINE__.'---Limit---','',0,' ',true);
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
		//
		//
		$Catnums = c('Plugins.DiscussionTopic.CategoryNums');
		//
		//Get the cetegory ids the user is allowed to see 
		$Categories = CategoryModel::getByPermission();
		$Categories = array_column($Categories, 'Name', 'CategoryID');
		//if ($Debug) $this->Showdata($Categories,__LINE__.'---Categories---','',0,' ',true);
		$Categorycount = 0;
		foreach ($Categories as $CategoryID  => $CategoryName) {
			//$this->Showdata($CategoryID,__LINE__.'---CategoryID---','',0,' ',true);
			//$this->Showdata($CategoryName,__LINE__.'---CategoryName---','',0,' ',true);
			if ($Catnums != "") {
				if (in_array($CategoryID, $Catnums)) {	//In the list?
					$Categorycount = $Categorycount + 1;
					$Categorylist[$Categorycount] = $CategoryID;
				}
			} else {
				$Categorycount = $Categorycount + 1;
				$Categorylist[$Categorycount] = $CategoryID;
			}
		}
		if ($Debug) $this->Showdata($Categorylist,__LINE__.'---Categorylist---','',0,' ',true);
		//
		$Updategen = substr(c('Plugins.DiscussionTopic.Updategen',100),0,3);
		if ($Restart) {		/*New session*/
			$StartID = 0;
			$Newgen = 1 + $Updategen;
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',0);
			$Title = 'New update session'.str_repeat("&nbsp",40).' (Session#'.$Newgen.')';
			//if ($Debug) $this->Showdata($Updategen,__LINE__.'---Updategen---','',0,' ',true);
		} else {			/*Continued session*/
			$StartID = c('Plugins.DiscussionTopic.HighupdateID',0);
			$Title = 'Continuing update session'.str_repeat("&nbsp",40).' (Session#'.$Updategen.')';
			//if ($Debug) $this->Showdata($Updategen,__LINE__.'---Updategen---','',0,' ',true);
		}
		if ($Debug) $this->Showdata($StartID,__LINE__.'---StartID---','',0,' ',true);
		$Uselimit = $Limit + 1;
		$AlsoSql = clone Gdn::sql();	//Don't interfere with any other sql process
		$AlsoSql->Reset();				//Clean slate
		$Sqlfields = 'd.DiscussionID,d.Name,d.CategoryID,d.Topic';
		$Discussionlist = $AlsoSql		//Get expanded tag info for this discussion
			->select($Sqlfields)
			->from('Discussion d')
			->where('d.DiscussionID >', $StartID)
			->wherein('d.CategoryID', $Categorylist)
			->orderby('d.DiscussionID')
			->limit($Uselimit)
			->get();
		//
		$Rowcount = count($Discussionlist);
		if ($Debug) echo '<br>'.__LINE__.' Rowcount:'.$Rowcount;
		if ($Rowcount == 0) {
			echo wrap('<br> DiscussionTopic.'.__LINE__.' Nothing available for updating the extracts using the current criteria.  
						You may <b>start</b> a new update session by using the appropriate link in the configuration panel.',
						'div ');
			return 0;
		}
		//
		$Listcount = 0;
		$AlsoSql->Reset();
		echo wrap('<br><b>DiscussionTopic plugin multiple discussions update.</b><br> '.c('Plugins.DiscussionTopic.Modename').' Mode. '.$Title,'div');
				//'div class="SettingNote" ; style=";max-width: 90%;display: inline-block;"');
		echo wrap(str_repeat("&nbsp",120),'div class=Settingsqueeze');
		if ($Rowcount >  $Limit) {
			echo wrap('<br>Click '.Anchor('here','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0','Popup').
					' to <b>continue</b> the current update session.','div  ');
		} else {
			echo wrap(Anchor(T('<br>Click to return to the setting screen<br>'), '/settings/DiscussionTopic'),'div ');
		}
		foreach($Discussionlist as $Entry){
			$Listcount += 1;
			if ($Listcount <= $Limit) {
				//if ($Debug) $this->Showdata($Entry,__LINE__.'---Entry---','',0,' ',true);
				$DiscussionID = $Entry->DiscussionID;	
				$Discussion = $DiscussionModel->getID($DiscussionID);
				$Name = $Entry->Name;
				$Topic = $this->GetSubject($Sender,$Name,'',$Debug);
				echo wrap('<br>'.$Listcount.' ID:<b>'.$DiscussionID.' </b>Title:<b>'.SliceString($Name,60).' </b>Keywords:<b>'.$Topic.'</b>','span');
				$AlsoSql->update('Discussion d')
					->set('d.Topic', $Topic)
					->where('d.DiscussionID', $DiscussionID)
					->put();
				$Highwatermark = $DiscussionID;
			}
		}
		echo wrap('<br><b> '. ($Listcount-1) .' rows updated.</b>','span');
		SaveToConfig('Plugins.DiscussionTopic.Cleared',false);
		if ($Rowcount >  $Limit) {	//Session incomplete
			echo wrap(' Note: there are more rows that can be updated.<br>');
			SaveToConfig('Plugins.DiscussionTopic.Parttialupdate',true);
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',$Highwatermark);
			//echo wrap('<br>Click '.Anchor('here','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0','Popup').
			//		' to <b>continue</b> the current update session.','div  ');
		} else {					//Session omplete
			echo wrap(' Note: No more rows that can be updated under the current settings.<br>');
			SaveToConfig('Plugins.DiscussionTopic.Parttialupdate',false);
			$Newgen = 1 + $Updategen;
			SaveToConfig('Plugins.DiscussionTopic.Updategen',$Newgen);
			SaveToConfig('Plugins.DiscussionTopic.HighupdateID',0);
		}
		//
		return $Listcount;
	}
	/////////////////////////////////////////////////////////
	//Build the extract from the discussion title.
	public function GetSubject($Sender,$String,$Simulate = '', $Debug = false) {
		
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
			$this->Showdata($String,__LINE__.'---String---','',0,' ',true);
			$this->Showdata($Simulate,__LINE__.'---Simulate---','',0,' ',true);
		}
		//Modes: 1=manual, 2=Deterministic, 3=Heuristic, 4=Progressive (Both 2&3)
		$Mode = 1 + c('Plugins.DiscussionTopic.Mode','0'); 
		$Modearray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
		$Modename = $Modearray[$Mode];
		// 
		if ($Simulate != '') {
			$Mode = array_search($Simulate, $Modearray);
			$Modename = $Simulate;
		}
		if ($Debug) $this->Showdata($Mode,__LINE__.'---Mode---','',0,' ',true);
		if ($Debug) $this->Showdata($Modename,__LINE__.'---Modename---','',0,' ',true);
		if ($Modename == 'Manual') return;
		//
		//Clean up the sentence;
		$String = $this->Cleanstring($Sender,$String,$Modename,$Debug);
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		//Modes: 1=Manual, 2=Deterministic, 3=Heuristic, 4=Progressive (2 & 3)
		if (substr($String,0,1) == '"') {
			if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
			if ($Modename != 'Heuristicistic') return $String;		//Deterministic or Progressive modes
		}
		if ($Modename == 'Deterministic') return '';				//No double quoted string and detrministic, so no topic to return.
		//	Handle Heuristic and Progressive modes
		//Get the global update generation number
		$Updategen = c('Plugins.DiscussionTopic.Updategen',100);
		SaveToConfig('Plugins.DiscussionTopic.Updategen',$Updategen);
		// Get the Noise Words
		$Noisewords = c('Plugins.DiscussionTopic.Noisewords',' ');
		$Localnoisearray = $this->GetExplode($Noisewords,0);
		$Globalnoisearray = array('');
		$Globalnoisearray = t('DiscussionTopicNoisewords1');
		//if ($Debug) $Globalnoisearray = array('!Debug');
		$Noisearray = array_merge($Localnoisearray,$Globalnoisearray);
		$Noisearray = array_change_key_case($Noisearray,CASE_LOWER);
		//if ($Debug) $this->Showdata($Noisearray,__LINE__.'---Noisearray---','',0,' ',true);
		//
		//Get the number of significant keywords
		$Sigwords = c('Plugins.DiscussionTopic.Sigwords',2);
		//
		//Check which text Analyzer to use
		$Analyzername = c('Plugins.DiscussionTopic.Analyzername','');
		if ($Analyzername == '') {
			echo wrap('DiscussionTopic plugin - Missing Analyzer name','h1');
			return '';	
		}
		//Analyze the words
		//if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		if ($Analyzername == 'PosTagger') {
			$Tagger = new PosTagger('lexicon.txt');
			$Tags = $Tagger->tag($String);
			//if ($Debug) $this->Showdata($Tags,__LINE__.'---Tags---','',0,' ',true);
			//Remap PosTagger to Textrazor response
			$Words = array_map(function($tag) {
				return array(
					'token' => $tag['token'],
					'stem' => PorterStemmer::Stem($tag['token']),
					'partOfSpeech' => $tag['tag']
				);
			}, $Tags);
			//if ($Debug) $this->Showdata($Words,__LINE__.'---Words---','',0,' ',true);
		} 
		elseif ($Analyzername == 'TextRazor') {		
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
			//if ($Debug) $this->Showdata($Response,__LINE__.'---Response---','',0,' ',true);
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
				if ($Debug) $this->Showdata($Entry,__LINE__.'---Entry---','',0,' ',true);
				/*Sample structure:	Entry--- array 
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
				if (strlen($Token) > 1 && !in_array($Token,$Noisearray)) {
					$i += 1;
					$Catchprefix = substr($Partofspeech,0,2);
					switch ($Catchprefix) {
						case "NN":
						case "XC":
							$Nouns[$n] = $Stem;
							$n += 1;
							break;
						case "VB":
							$Verbs[($v)] = $Stem;
							$v += 1;
							break;
					}
				}
			}
			//
			if ($Debug) $this->Showdata($Nouns,__LINE__.'---Nouns---','',0,' ',true);
			if ($Debug) $this->Showdata($Verbs,__LINE__.'---Verbs---','',0,' ',true);
			for ($j = 0;$j<count($Nouns); $j++ ) {
				$Keywords[$j] = $Nouns[$j];
			}
			for ($k = 0;$k<count($Verbs); $k++ ) {
				$j += 1;
				$Keywords[$j] = $Verbs[$k];
				if ($Debug) echo wrap('k:'.$k.' j:'.$j.', $Verbs[$k]:'.$Verbs[$k],'div');
			}
			if ($Debug) $this->Showdata($Keywords,__LINE__.'---Keywords---','',0,' ',true);
			//
			$Keywords = array_unique($Keywords);
			$Keywords = array_filter($Keywords); 
			ksort($Keywords);
			//if ($Debug) $this->Showdata($Keywords,__LINE__.'---Keywords---','',0,' ',true);
			$Keywords = array_slice($Keywords, 0, $Sigwords);
			//sort($Keywords);
			//$String = $Updategen.','.implode(",",$Keywords);
			$String = implode(",",$Keywords);
		}
		if ($Analyzername == 'TextRazor') unset($Textrazor); 
		$String = trim($String);
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		return $String;   
	}
	/////////////////////////////////////////////////////////
	//Extract quoted string (if any).
	public function GetQuoted($Sender,$String,$Debug = true) {
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		}
		//
		preg_match_all('/"([^"]+)"/', $String, $Results);
		if ($Debug) $this->Showdata($Results,__LINE__.'---Results---','',0,' ',true);
		foreach ($Results[1] as $Quoted) {
			if ($Debug) echo '<br>'.__LINE__.' Quoted='.$Quoted.'<br>';
			if (strlen(trim($Quoted)) > 2) return '"'.trim($Quoted).'"';
		}
		return $String;
	}
	/////////////////////////////////////////////////////////
	//Build the extract from the discussion title.
	public function Cleanstring($Sender, $String, $Modename, $Debug = false) {
		if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->Showdata($String,__LINE__.'---String---','',0,' ',true);
			$this->Showdata($Modename,__LINE__.'---Modename---','',0,' ',true);
		}
		// Replace multiple spaces with single spaces
		$String = preg_replace('!\s+!', ' ', $String);
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		if ($Modename != 'Heuristic') {
			// Priority phrases replacement
			$Newstring = $this->ChangeByPriority($Sender,$String,$Debug);
			if ($Debug) $this->Showdata($Newstring,__LINE__.'---String---','',0,' ',true);
			// Priority phrases replacement
			if ($Newstring != $String) {			//Substitution was made
				return $Newstring;
			}
		}
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		// Acronym replacement
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		$String = $this->ChangeByAcronym($Sender,$String,$Debug);
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		// Extract Quoted strings for Deterministic/Progressive modes
		if ($Modename != 'Heuristic') {
			//Following substitution extract quoted string (if any) and if found use it as the topic
			$String = $this->GetQuoted($Sender,$String,$Debug);
			if (substr($String,0,1) == '"') return $String;
			// No quoted strings, let's start the hard work.
		}
		// Clear unnecessary punctioations
		$String = preg_replace("/(?![$])\p{P}/u", "", strtolower($String));
		//
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		//
		// Clear numbers leaving words with embedded numbers
		$Name = preg_replace("/(\b)[0-9]+(\b)/", ' ', $String);
		if ($Debug) $this->Showdata($Name,__LINE__.'---Name---','',0,' ',true);
		//
		// Tokenize (except for quoted texts
		preg_match_all("/(['\".'\"])(.*?)\\1|\S+/", $Name, $Result);
		$Tokens = $Result[0];
		if ($Tokens[0] == 'sample') $this->Showdata($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		//if ($Debug) $this->Showdata($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		//
		// Remove Noise Words
		$Tokens = $this->ChangeByNoise($Sender,$Tokens,$Debug);
		if ($Debug) $this->Showdata($Tokens,__LINE__.'---Tokens---','',0,' ',true);
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
		//if ($Debug) $this->Showdata($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		$String = implode(" ",$Tokens);
		//
		//if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		return $String;
	}
	/////////////////////////////////////////////////////////
	public function ChangeByNoise ($Sender,$Tokens,$Debug = false) {
		//$Debug = true;
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
		}
		//
		$Noisewords = strtolower(c('Plugins.DiscussionTopic.Noisewords',' '));
		$Localnoisearray = $this->GetExplode($Noisewords,0);
		$Globalnoisearray = array('');
		$Globalnoisearray = t('DiscussionTopicNoisewords1');
		if ($Debug) $Globalnoisearray = array('123');  //T E S T I N G
		if ($Debug) $this->Showdata($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		$Noisearray = array_merge($Localnoisearray,$Globalnoisearray);
		$Noisearray = array_change_key_case($Noisearray,CASE_LOWER);
		if ($Debug) $this->Showdata($Noisearray,__LINE__.'---Noisearray---','',0,' ',true);
		//
		$Tokens = array_values(array_diff($Tokens,$Noisearray));
		if ($Debug) $this->Showdata($Tokens,__LINE__.'---Tokens---','',0,' ',true);
		return $Tokens;
	}
	/////////////////////////////////////////////////////////
	public function ChangeByPriority ($Sender,$String,$Debug = false) {
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		}
		//
		$Prioritylist = c('Plugins.DiscussionTopic.Prioritylist','');
		$Priorityarray = $this->Getexplode($Prioritylist,0);
		//if ($Debug) $this->Showdata($Priorityarray,__LINE__.'---Priorityarray---','',0,' ',true);
		//if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		// Priority phrases replacement
		foreach($Priorityarray as $Entry => $Priority) { 
			$Savestring = $String;
			$String = preg_replace('/\b' . preg_quote($Priority) . '\b/i', '"'.$Priority.'"',$String);
			//if ($Debug) $this->Showdata($Priority,__LINE__.'---Priority---','',0,' ',true);
			//if ($Debug) $this->Showdata($Savestring,__LINE__.'---Savestring---','',0,' ',true);
			if ($Savestring != $String) {			//Substitution was made
				if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
				$String = $this->GetQuoted($Sender,$String,$Debug);
				if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
				return $String;
			}
		}
		return $String;
	}
	/////////////////////////////////////////////////////////
	public function ChangeByAcronym ($Sender,$String,$Debug = false) {
        if ($Debug) {
			$Msg = '... '. __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].' ---> '. debug_backtrace()[0]['function'];
			//**Gdn::controller()->informMessage($Msg);
			echo Wrap($Msg,'br');
			$this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		}
		//
		$Acronyms = c('Plugins.DiscussionTopic.Acronyms','');
		$LocalAcronymarray = $this->GetexplodeByKey($Acronyms,0);
		$GlobalAcronymarray = t('DiscussionTopicAcronyms');
		$Acronymarray = array_merge($LocalAcronymarray,$GlobalAcronymarray);	
		$Acronymarray = array_change_key_case($Acronymarray,CASE_LOWER);
		if ($Debug) $this->Showdata($Acronymarray,__LINE__.'---Acronymarray---','',0,' ',true);
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		// Acronym replacement
		foreach($Acronymarray as $Acronym => $Replacement) { 
			$String = preg_replace('/\b' . preg_quote($Acronym) . '\b/i', $Replacement,$String);
//			if ($Debug) $this->Showdata($Acronym,__LINE__.'---Acronym---','',0,' ',true);
//			if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		}
		// 
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		return $String;
    }
	/////////////////////////////////////////////////////////
	public function Setup () {
        $this->Structure();
    }
	/////////////////////////////////////////////////////////
	public function onDisable () {
        $this->Structure();
    }
	/////////////////////////////////////////////////////////
	public function Structure () {
        Gdn::database()->structure()
            ->table('Discussion')
            ->column('Topic', 'varchar(100)', true)
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
		//if ($Debug) $this->Showdata($Args,__LINE__.'---Args---','',0,' ',true);
		$Sender->addCssFile('pluginsetup.css', 'plugins/DiscussionTopic');
        $Sender->addSideMenu('dashboard/settings/plugins');
		// Default configuration variables
		$this->SetVariableDefault('Plugins.DiscussionTopic.Noisewords','Vanilla,forum');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Acronyms','btn=button,config=configuration,db=database');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Sigwords','2');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Paneltitle','Related Discussions');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Analyzer',array('1'));
		$this->SetVariableDefault('Plugins.DiscussionTopic.Extractor','words');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Testtitle','');
		$this->SetVariableDefault('Plugins.DiscussionTopic.Maxrowupdates',10);
		$this->SetVariableDefault('Plugins.DiscussionTopic.Updategen',100);
		$this->SetVariableDefault('Plugins.DiscussionTopic.HighupdateID',0);
		$this->SetVariableDefault('Plugins.DiscussionTopic.Mode',array('1'));
		//
		$Plugininfo = Gdn::pluginManager()->getPluginInfo('DiscussionTopic');
		//if ($Debug) this->Showdata($Plugininfo,__LINE__.'---Plugininfo---','',0,' ',true);
		$Constants = $Plugininfo['PluginConstants'];
		$Maxbatch = $Constants['Maxbatch'];
		//
		//
		$IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup',false);
		$Goterror =false;
		$TopWarning = '';
		$FieldErrors = '';
		$Feedbackrray = array();
		//
		$Analyzerarray = Array(0 => '?', 1 => 'PosTagger', 2 =>'TextRazor');
		//
		$Modearray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
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
			//if ($Debug) $this->Showdata($Sender->Form,__LINE__.'---Form---','',0,' ',true);	
			//if ($Debug) $this->Showdata($Data,__LINE__.'---Data---','',0,' ',true);	
			//
			$Noisewords = strtolower(getvalue('Plugins.DiscussionTopic.Noisewords',$Data));
			//		Flag to link DiscussionTopics
			$Acronyms = getvalue('Plugins.DiscussionTopic.Acronyms',$Data);
			//
			$Analyzer = getvalue('Plugins.DiscussionTopic.Analyzer',$Data);
			//
			$Prioritylist = getvalue('Plugins.DiscussionTopic.Prioritylist',$Data);
			//
			$Mode = getvalue('Plugins.DiscussionTopic.Mode',$Data);
			//
			$Sigwords = getvalue('Plugins.DiscussionTopic.Sigwords',$Data);
			//
			$Testtitle = getvalue('Plugins.DiscussionTopic.Testtitle',$Data);
			//
			$Paneltitle = getvalue('Plugins.DiscussionTopic.Paneltitle',$Data);
			//
			$Maxrowupdates = getvalue('Plugins.DiscussionTopic.Maxrowupdates',$Data);
			// Max batch size is a plugininfo constant
			$FieldErrors .= $this->CheckField($Sender,$Maxrowupdates,
							Array('Integer' => ' ','Min' => '2','Max' => $Maxbatch),
							'Maximum rows to update (on utility setup)','Plugins.DiscussionTopic.Maxrowupdates');
			//
			$FieldErrors .= $this->CheckField($Sender,$Paneltitle,
							Array('Required' => 'Title'),
							'Side Panel Title','Plugins.DiscussionTopic.Paneltitle');
			//
			$Analyzername = $Analyzerarray[$Analyzer+1];
			SaveToConfig('Plugins.DiscussionTopic.Analyzername',$Analyzername);
			//
			$Modename = $Modearray[$Mode+1];
			SaveToConfig('Plugins.DiscussionTopic.Modename',$Modename);
			//if ($Debug) $this->Showdata($Mode,__LINE__.'---Mode---','',0,' ',true);
			//if ($Debug) $this->Showdata($Modename,__LINE__.'---Modename---','',0,' ',true);			
			//
			$FieldErrors .= $this->CheckField($Sender,$Sigwords,
							Array('Required' => 'Integer','Min' => '2','Max' => '4'),
							'Number of keywords','Plugins.DiscussionTopic.Sigwords');
			if ($Debug) $this->Showdata($FieldErrors,__LINE__.'---FieldErrors---','',0,' ',true);
			//
			if ($Debug) echo '<br>'.__LINE__.'FieldErrors:'.$FieldErrors;
			//
			if ($FieldErrors != '') {
				$Goterror=true;
				$Sender=$Validation->addValidationResult('Plugins.DiscussionTopic.CategoryNums', ' ');
				$TopWarning = t('Errors need to be corrected. Incomplete settings saved');
				//**Gdn::controller()->informMessage($TopWarning);//,'DoNotDismiss');
			}
			if (!$Validation->validate($FormPostValues)) $Goterror=true;
			if ($Goterror) {		
				if ($Debug) echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
				SaveToConfig('Plugins.DiscussionTopic.IncompleteSetup',true);
				$Sender=$Validation->addValidationResult('Plugins.DiscussionTopic.SearchBody', ' ');
			} else {
				if ($Debug) echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
				SaveToConfig('Plugins.DiscussionTopic.IncompleteSetup',false);
				if ($Testtitle != '') {
					SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
					$Simulate = $Modename;
					if ($Modename == 'Manual') $Simulate = 'Progressive';
					$Extract = $this->GetSubject($Sender,$Testtitle,$Simulate,$Debug);
					if ($Extract == '' ) {
						if ($Modename == 'Deterministic') 	{						//Deterministic mode
							$Extractnote = 'The test discussion name did not generate any title - Deterministic mode is used and double-quoted texts not found';
						} elseif ($Modename == 'Manual') 	{
							$Extractnote = 'No results can be shown in manual mode.';
						} elseif ($Modename == 'Heuristic') {
							$Extractnote = 'The test discussion name did not generate any title - all the words were noise words.';
						} elseif ($Modename == 'Progressive') {
							$Extractnote = 'The test discussion name did not generate any title - quoted texts not found and all the words were noise words.';
						} else {
							$Extractnote = 'The test discussion name did not generate any title - check your current settings.';
						}
					} else {
						$Extractnote =	wrap('Test title:<b>'.$Testtitle.'</b><br>','span').
										wrap($Simulate . ' mode generated topic: <b>'.$Extract.'</b>','span');
						}
					$AddError = $Sender->Form->addError(wrap($Extractnote,'span class=SettingTest'),'Plugins.DiscussionTopic.Testtitle');	
				}
			}
		//
		} else {			// Not postback
			SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
			if (c('Plugins.DiscussionTopic.IncompleteSetup')) 
				$TopWarning = 'Previously saved settings are incomplete/invalid.  Review and save correct values.';
			$Sender->Form->SetData($ConfigurationModel->Data);
        }
		//
		$PluginConfig = $this->SetConfig($Sender,$Feedbackrray,$Debug);// Array('TopWarning' => $TopWarning),$Debug);
		$ConfigurationModule->initialize($PluginConfig);
		$ConfigurationModule->renderAll();
    }
	///////////////////////////////////////////////////////// 
	// Function to handle future saving of arrary as lists  (Not 
   public function SetVariableDefault($Variable,$Default = '') {
	   $Value = c($Variable,$Default);
	   SaveToConfig($Variable,$Value);
   }
	///////////////////////////////////////////////////////// 
   public function Getexplode($String,$Debug) {
		if ($Debug) echo '<br><b>'.__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].'</b>';
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		//$String = str_replace("'", "\'", $String);
		$Array = explode(',',$String);
		$Array = array_map('trim', $Array);
		if ($Debug) $this->Showdata($Array,__LINE__.'---Array---','',0,' ',true);
		return $Array;
   }
	///////////////////////////////////////////////////////// 
   public function GetexplodeByKey($String,$Debug) {
		if ($Debug) echo '<br><b>'.__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].'</b>';
		if ($Debug) $this->Showdata($String,__LINE__.'---String---','',0,' ',true);
		//$String = str_replace("'", "\'", $String);
		$Array = explode(',',$String);
		$Array = array_map('trim', $Array);
		
		for ($i = 0;$i<count($Array); $i++ ) {
			list($Key, $Value) = explode('=', $Array[$i]);
			$Key = strtolower(trim($Key));
			$Keyarray[$Key] = strtolower(trim($Value));
		}
		
		if ($Debug) $this->Showdata($Keyarray,__LINE__.'---Acronymarray---','',0,' ',true);
		return $Keyarray;
   }
	/////////////////////////////////////////////////////////
	// Set Confogiration Array
	public function SetConfig($Sender,$Errors = Array(),$Debug) {
		$Separator = '<span class=SettingSep>&nbsp</span>';
		$Headstyle = '<span class=SettingHead>#&nbsp&nbsp';
		$Subhstyle = 'span class=SettingSubh';
		$Textstyle = '<span class=SettingText>';
		$Warnstyle = '<span class=SettingWarning>';
		$Errorstyle = '<span class=SettingError>';
		$Squeeze = '<span class=Settingsqueeze> </span>';
		$Notestyle = '<span class=SettingNote>';
		$Topmessage = '';
		$Testresult = '';
		if (trim($Errors['TopWarning'])) {
			$Topmessage .= $Warnstyle.$Errors['TopWarning'].'</span>';
		//} else {
			//$Topmessage = wrap(wrap('See the readme file for more detailed description.','div class=SettingNote'),'div');
		}
		if (trim($Errors['Testresult'])) $Testresult = wrap($Errors['Testresult'],'div class=SettingNote');
		//
		$Plugininfo = Gdn::pluginManager()->getPluginInfo('DiscussionTopic');
		//if ($Debug) this->Showdata($Plugininfo,__LINE__.'---Plugininfo---','',0,' ',true);
		$Constants = $Plugininfo['PluginConstants'];
		$Title = Wrap($Plugininfo['Name'].'-'.' Version '.$Plugininfo['Version'].' Settings','div class=SettingHead');
		//
		$Localplace = '/plugin/DiscussionTopic/locale/en-CA/definitions.php';
		//
		$Mode = 1 + c('Plugins.DiscussionTopic.Mode','0'); 
		$Modearray = Array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
		$Modename = $Modearray[$Mode];
		//if ($Debug) $this->Showdata($Modename,__LINE__.'---Modename---','',0,' ',true);
		//
		$Updategen = c('Plugins.DiscussionTopic.Updategen',$Constants['Startgen']);
		$Continueurl = '' ;
		$Initializetext = '';
		if (c('Plugins.DiscussionTopic.Parttialupdate',false)) {
			$Continueurl = wrap('Click'.Anchor('continue','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0','Button Popup').
					' to <b>continue</b> session '.$Updategen.
					' update to process discussion titles not processed by the previous session (remember, only '.c('Plugins.DiscussionTopic.Maxrowupdates').' records are handled at a time)','span  class=SettingAsideN  ');
		}
		$Clearurl = wrap('Click'.Anchor('remove','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0,&clear=1','Button Popup').
					' to <b>delete </b> the previously saved titles.<b>Use with care!</b>','span  class=SettingAsideN');
		
		if (c('Plugins.DiscussionTopic.Cleared',false)) $Clearurl = '';
		$Restarturl = wrap('<b>After</b> you save you can click '.Anchor('Start','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0','Button Popup').
					' to <b>start a new update session</b> (fresh analysis of discussion titles)','span  class=SettingAsideN  ').
					wrap('You can click '.Anchor('remove','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0,&clear=1','Button Popup').
					' to <b>delete </b> the previously saved titles.<b>Use with care!</b>','span  class=SettingAsideN  ');		
		$Restarturl = wrap('Click'.Anchor('Start','plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0','Button Popup').
					' to <b>start a new update session</b> (fresh analysis of discussion titles)','span  class=SettingAsideN  ').$Clearurl;
		//
		if (c('Plugins.DiscussionTopic.IncompleteSetup',false) | $Modename == 'Manual'  )  {
			$Continueurl = '' ;
			$Restarturl = '';
		}
		if ($Continueurl.$Restarturl != '') $Initializetext = wrap(wrap('<b>Discussion Table Update</b>','h3').wrap(
							wrap('Titles are attached to discussions when the discussion body (not comments) are saved.','span  class=SettingAsideN').
								wrap('You can use this Table Update process to attach titles to discussions without topics (e.g. discussions created before plugin activation) or to reconstruct the topics when you change some of the settings.','span  class=SettingAsideN').
								wrap('The following update options are available:','span  class=SettingAsideU').
							$Continueurl.$Restarturl,'span  class=SettingAsideN'),'div class=SettingAside');
		$ConstructionNotes = wrap(wrap('<b>Topic Construction notes</b>','h3').wrap(
							wrap('In Deterministic mode Priority phrases add quotes to the phrases in the discussion title.  Then <i>the first</i> double-quoted string within the title is picked as the topic.','span  class=SettingAsideN').
							wrap('To increase the matching of related discussions, Heuristic mode uses simulated word roots ("Stems"). The resulting topic may seem mistyped - this is not a bug.','span  class=SettingAsideN').
							wrap('Heuristic process is inherently implerfect, especially with free form user input.','span  class=SettingAsideN').
							' ','span '),'div class=SettingAside');
		$NoiseNotes = wrap(wrap('<b>Nose word note</b>','h3').wrap(
							wrap('The noise words specified on this screen are adeed to the noise word definitions in the plugin LOCALE file. For English, the locale is in: '.$Localplace,'span  class=SettingAsideN').
							' ','span '),'div class=SettingAside');
		$AcronymsNotes = wrap(wrap('<b>Substitute Words note</b>','h3').wrap(
							wrap('The substitute words specified on this screen are adeed to the substitute word definitions in the plugin LOCALE file mentioned above.','span  class=SettingAsideN').
							wrap('Hint: If you substitute the word with a double-quoted phrase, it will behave as a Priority Phrase.','span  class=SettingAsideN').
							' ','span '),'div class=SettingAside');
		$GeneralNotes = wrap(wrap('<b>General Notes</b>','h3').wrap(
							wrap('Always refer to the <b>readme</b> file for detailed information.','span  class=SettingAsideN').
							wrap('With the exceptions noted below, the Topic analysis takes place when a discussion is saved, so the performance impact should be small.','span  class=SettingAsideN').
							wrap('By limiting the processing to specific categories you can further minimize the performance impact.','span  class=SettingAsideN').
							wrap('If you have a process that saves many discussions at a time (e.g. feed imports) that process will be impacted.','span  class=SettingAsideN').
							wrap('Another example of saving many discussions at a time is the (re)updating of many previosly saved discussions through the <b>Table Update</b> process below.  For that reason you are given the opportunity to sepecify the number of records to update at a time (see below).','span  class=SettingAsideN').
							' ','span '),'div class=SettingAsideWide');
							//
		$WarnGarden = '';
		$Sidepanelnote = 'FYI, if you use the ModuleSort plugin, the internal name of the sidepanel is \'DiscussionTopicModule\'.';
		// Get all categories.
		$Categories = CategoryModel::categories();
		// Remove the "root" categorie from the list.
		unset($Categories[-1]);
		//
		$Analyzerarray = Array(0 => '?', 1 => 'PosTagger', 2 =>'TextRazor');
		unset($Analyzerarray );
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
			'Description' => 	wrap('<b>Side Panel Title</b>','span class=SettingSubh').
								wrap('Enter the title for the side panel showing discussions with related title content:',
								'span class=SettingText title="'.$Sidepanelnote.'"'),
			'LabelCode' => 		wrap('<b>Visibility&nbspOptions</b>','span class=SettingPostCatlist').wrap(' ','span class=Settingsqueeze'),
			'Default' => 'Common Topic',
			'Options' => array('Class' => 'Textbox')),
		//
			'Plugins.DiscussionTopic.Showinlist' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('1. Display the discussion topic in the <i>discussion list</i> meta area',
								'span class=SettingText'),
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
					wrap('(Requires "manage" permission in the plugin section of "Roles and Permissions".)','span class=SettingTextContinue'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		//
		/**************  To be completed in a future release ******
			'Plugins.DiscussionTopic.Showmenu' => array(
			'Control' => 'Checkbox',
			'LabelCode' => 	wrap('Display a "Topic-Search" menu in the menu bar',
								'span class=SettingText'),
			'Description' => 	'',	
			'Default' => false,
			'Options' => array('Class' => 'Optionlist ')),
		*************/
		//
			'Plugins.DiscussionTopic.Mode' => array(
			'Control' => 'Radiolist',
			'Items' => Array(	
				'1. Manual - Topic must be set manually through the options menu (Obviously, Visibility option 4 above must be enabled).',
				'2. Deterministic - Pick a "Double-Quoted-Text" or "Priority Phrases" (see below) in the discussion Name as the discussion topic.',
				'3. Heuristic - Attempt language analysis of the discussion name to determine the discussion topic.',
				'4. Progressive - Try the Deterministic approach and if double-quoted string is not found try Heuristics.'),
			'Description' => 	$ConstructionNotes.wrap('Select one of these topic construction modes:','span class=SettingText'),
			'LabelCode' => 		wrap('<b>Discussion&nbspTopic&nbspConstruction&nbspMode</b>','span class=SettingHead'),
			'Default' => 'Both',
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
			'Description' => 	wrap('<br><b>After</b> you save your settings you can see the Topic determined by the saved options. <br>Optionally enter a test discussion title below:','div class=SettingText'),
			'LabelCode' => 		wrap('<b>Discussion  Testing:</b>','span class=SettingHead'),
			'Options' => array('MultiLine' => false, 'class' => 'TestWideInputBox'),
			'Default' => ' '),
		//
			'Plugins.DiscussionTopic.Maxrowupdates' => array(
			'Control' => 'TextBox',
			'LabelCode' => 	$Initializetext.wrap('<b>Table Update-Adding topics to previously saved discussions</b>','span class=SettingHead'),
			'Description' => 		wrap('Enter the maximum number of rows to update in a single update session (See the readme file):','div class=Settinghead'),
			'Default' => 100,
			'Options' => array('Class' => 'Textbox')),
		//
		//
		//
		);
	
		//if ($Debug) $this->Showdata($XPluginConfig,__LINE__.'---XPluginConfig---','',0,' ',true);
		return $PluginConfig;
	}
	///////////////////////////////////////////////////////// 
// Check Configuration Settings
	public function CheckSettings($Sender,$Type='All',$Debug) {
		 if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
		//
		$Warn = '';
		$Error = '';
		//Get the menu filled variables
		$Data = $Sender->Form->formValues();
		$Noisewords = getvalue('Plugins.DiscussionTopic.Noisewords',$Data);
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
	public function CheckField($Sender,$Field=false,$Checks=Array('Required'),$Title = 'Field', $Fieldname = '', $Style = 'span class=SettingError',
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
	public function GetTopicToShow($Sender, $Topic, $Location = 'inlist', $Debug = false) {
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
		if ($Showtopic == '') return;
		echo wrap(t('Discussion topic').':'.$Showtopic.'<br>','div class=TopicInView id=Topic'.$DiscussionID);
	}
	/////////////////////////////////////////////////////////
	//public function PostController_beforeBodyInput_handler ($Sender,$Args) {
	public function PostController_afterDiscussionFormOptions_handler($Sender,$Args) {
		$Debug = false;
        // 
		$Discussion = getvalue('Discussion', $Sender->Data);
		
		$Showtopic = $this->GetTopicToShow($Sender,getvalue('Topic', $Discussion), 'Showindiscussion', $Debug);
		if ($Showtopic == '') return;
        //$Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');
		echo wrap(t('Saved discussion topic').':'.$Showtopic,'div class=TopicInPost');
    }
	/////////////////////////////////////////////////////////
	public function DiscussionController_BeforeDiscussionRender_Handler($Sender) {
		$Debug = false;
		//if (!c('Plugins.DiscussionTopic.Showrelated',false)) return;
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
		$this->Listinline($Sender,$Args['Discussion'],$Debug);
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
		$this->Listinline($Sender,$Args['Discussion'],$Debug);
	}
	public function Listinline($Sender,$Discussion,$Debug = false) {
		//if ($Debug) echo "<br><b>".__FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
		$Showtopic = $this->GetTopicToShow($Sender,$Discussion->Topic, 'Showinlist', $Debug);
		if ($Showtopic == '') return;
        //$Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');
		echo wrap(t('Discussion topic').':'.$Showtopic,
					'div class=TopicInList id=Topic'.$Discussion->DiscussionID); 
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
		$Simplekey =(367+Gdn::Session()->UserID); 
		$Encode = $DiscussionID ^ $Simplekey;
		$Url = '/dashboard/plugin/DiscussionTopic/DiscussionTopicSetTopic/?D='.$DiscussionID.'&S='.$Encode;
		$Css = 'Popup SetTopiccss';
		//if ($Debug) $this->Showdata($Url,'---Url---','',0,' ',true);
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
		$Catnums = c('Plugins.DiscussionTopic.CategoryNums');
		if ($Debug) $this->Showdata($Catnums,'---Catnums---','',0,' ',true);
		if ($Catnums != "") {  //Limited category list?
			if (!in_array($Discussion->CategoryID, $Catnums)) {	//Not in the list?
				if ($Debug) //**Gdn::controller()->informMessage($this->Showdata($CategoryID,__LINE__.'---CategoryID---','',0,' ',true));
				return;
			}
		}
		//	Construct the link and add to the gear           
		$DiscussionID = $Discussion->DiscussionID;
		$Text='Set Discussion Topic';
		$Simplekey =(367+Gdn::Session()->UserID); 
		$Encode = $DiscussionID ^ $Simplekey;
		$Url = '/dashboard/plugin/DiscussionTopic/DiscussionTopicSetTopic/?D='.$DiscussionID.'&S='.$Encode;
		$Css = 'Hijack SetTopiccss';
		$Css = 'Popup SetTopiccss';
		$Sender->Options .= '<li>'.anchor(t($Text), $Url,$Css).'</li>';  
	}
	/////////////////////////////////////////////////////////
   	// Terminate with a severe message
	public function DieMessage($Message) {
		echo "<P>DiscussionTopic Plugin Message:<H1><B>".$Message."<N></H1></P>";
		throw new Gdn_UserException($Message);
	}
	/////////////////////////////////////////////////////////
	// Display data for debugging
	public function Showdata($Data, $Message, $Find, $Nest=0, $BR='<br>', $Echo = true) {
		//var_dump($Data);
		$Line = "<br>".str_repeat(".",$Nest*4)."<B>(".($Nest).") ".$Message."</B>";
		if ($Echo) echo $Line;
		else //**Gdn::controller()->informMessage($Line);
		
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
	////////////////////////////////////////////////////////
	// Overriding discussion list sort order (for debugging/analysis only).
	public function DiscussionModel_BeforeGet_Handler($Sender) {
		$IsAdmin = Gdn::Session()->CheckPermission('Garden.Users.Edit');
		if (!$IsAdmin) return;
		if (!c('Plugins.DiscussionTopic.SortByTopic',false)) return;
		//This entire method won't be necessary in Vanila 2.3
		Gdn::Controller()->Title(wrap('DiscussionTopic Plugin-sorted by discussion topic (for topic analysis purposes)','span class=Titlesortnotice'));
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
