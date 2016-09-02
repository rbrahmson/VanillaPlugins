<?php
/**
 * DiscussionTopic plugin.
 *
 */

$PluginInfo['DiscussionTopic'] = array(
    'Name' => 'DiscussionTopic',
    'Description' => 'Adds a side panel of discussions sharing similar topics. Topics can be automatically derived from discussion title language analysis, administrator defined "Priority Phrases", double quoted phrases, or entered manually.',
    'Version' => '3.1.4',       //Applying some coding Standadrs
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'RequiredTheme' => false,
    'MobileFriendly' => false,
    'HasLocale' => true,
    'SettingsUrl' => '/settings/DiscussionTopic',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'RegisterPermissions' => array('Plugins.DiscussionTopic.View','Plugins.DiscussionTopic.Manage'),
    'Author' => "Roger Brahmson",
    'PluginConstants' => array('Startgen' => '0','Maxbatch' => '10000'),
    'License' => "GNU GPL3"
);
/**
* Plugin to discover and save the discussion topic.
*/
class DiscussionTopicPlugin extends Gdn_Plugin {

/**
* This hook handles the saving of the initial discussion body (but not comments).
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function discussionModel_beforesavediscussion_handler($Sender, $Args) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        //
        $FormPostValues = val('FormPostValues', $Sender->EventArguments, array());
        //
        $DiscussionID = val('DiscussionID', $FormPostValues, 0);
        $CategoryID = val('CategoryID', $FormPostValues, 0);
        //
        $CategoryNums = c('Plugins.DiscussionTopic.CategoryNums');
        if ($Debug) {
            $this->DebugData($CategoryNums, '---Catnums---', $Debug);
        }
        if ($CategoryNums != "") {  //Limited category list?
            if (!in_array($CategoryID, $CategoryNums)) {
                return;
            }
        }
        //
        $CommentID = val('CommentID', $FormPostValues, 0);
        $Name = val('Name', $FormPostValues, '');
        $Body = val('Body', $FormPostValues, '');
        $Topic = val('Topic', $FormPostValues, '');
        $TopicAnswer  = val('TopicAnswer ', $FormPostValues, '');
        if ($DiscussionID == 0) {
            $TopicAnswerVal = false;
        }
        if ($TopicAnswer != '') {
            $TopicAnswerVal = true;
        }
        if ($Debug) {
            $this->DebugData($Sender->EventArguments, '---$Sender->EventArguments---', $Debug);
            $this->DebugData($Args, '---$Args---', $Debug);
            $this->DebugData($DiscussionID, '---DiscussionID---', $Debug);
            $this->DebugData($CategoryID, '---CategoryID---', $Debug);
            $this->DebugData($CommentID, '---CommentID---', $Debug);
            $this->DebugData($Name, '---Name---', $Debug);
            $this->DebugData($Topic, '---Topic---', $Debug);
            $this->DebugData($TopicAnswerVal, '---TopicAnswerVal---', $Debug);
            //$this->DebugData($FormPostValues,'---FormPostValues---',$Debug);
            $Debug = false;
        }
        //
        //if (substr($Body.'          ',0,9) == "**DEBUG*!") $Debug = true;
        //
        $Extract = $this->gettopic($Name, '', $Debug);
        //
        //if (substr($Body.'          ', 0, 9) == "**DEBUG*!") {
        //  $Debug = true;
        //}
        if ($Debug) {
            $this->DebugData($Extract, '---Extract---', $Debug);
        }
        $Sender->EventArguments['FormPostValues']['Topic'] = $Extract.$Debug;
        $Sender->EventArguments['FormPostValues']['TopicAnswer'] = $TopicAnswerVal;
        //if (substr($Body.'          ', 0, 10)  == "**DEBUG*!/") {
        //  die(0);//quick and dirty way to see online feedback iteratively
        //}
    }
/**
* Dispatcher.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function plugincontroller_discussiontopic_create($Sender, $Args) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        //if ($Debug) $this->DebugData($Sender->RequestArgs,'---Sender->RequestArgs---',$Debug);
        if ($Sender->RequestArg[0] == 'Search') {
            $this->Controller_DiscussionTopicSearch($Sender, $Args);
            return;
        } elseif ($Sender->RequestArg[0] == 'TopicSearch') {
            $this->Controller_DiscussionTopicSearch($Sender, $Args);
            return;
        }
        $this->Dispatch($Sender, $Sender->RequestArgs);
    }
/**
* Handle Discussion Topic update request from the gear (options menu).
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function controller_discussiontopicsetTopic($Sender, $Args) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        //$DiscussionID = $Args[0];
        $DiscussionID = intval($_GET['D']);
        if ($Debug) {
            $this->DebugData($DiscussionID, '---DiscussionID---', $Debug);
        }
        if ($DiscussionID == null) {                        //DiscussionID is required
            $this->DieMessage('DA002 - Missing Parameters');
            return;
        }
        $Encode = intval($_GET['S']);
        if ($Encode == $DiscussionID) {                      //Encoded form cannot be in the clear
            $this->DieMessage('DA003 - Invalid Parameter');
            return;
        } else {
            if ($Encode == null) {                          //Encoding form is also required
                $this->DieMessage('DA004 - Invalid Parameter');
                return;
            }
            $SimpleKey = (367+Gdn::Session()->UserID);
            $D2 = $DiscussionID ^ $SimpleKey;
            if ($D2 != $Encode) {                          //Encoded form does not belong to this DiscussionID
                $this->DieMessage('DA005 - Invalid Parameter:'.$Encode);
                return;
            }
        }
        // Now we know that passed parameters are fine
        $DiscussionModel = new DiscussionModel();
        $Discussion = $DiscussionModel->GetID($DiscussionID);
        $Topic = $Discussion->Topic;
        $Referer = $_SERVER["HTTP_REFERER"];
        $DisplayingDiscussion = strpos($Referer, 'discussion/'.$DiscussionID);
        //Non zero value indicates we're browing a single discussion (so side panel is possible)
        $PreTopic = $Discussion->Topic;                         //Save value before the form is displayed
        $PreTopicAnswer = $Discussion->TopicAnswer;
        $Update = false;
        //
        $this->ShowTopicForm($Sender, $Discussion, $Debug);//Display the form
        //
        $Topic = $Discussion->Topic;
        $TopicAnswer = $Discussion->TopicAnswer;
        if ($Topic != $PreTopic | $TopicAnswer != $PreTopicAnswer) {      //Don't bther to change anything if nothing was changed
            SaveToConfig('Plugins.DiscussionTopic.Cleared', false);
            Gdn::sql() ->update('Discussion')
                    ->set('TopicAnswer', $TopicAnswer)
                    ->set('Topic', $Topic)
                    ->where('DiscussionID', $DiscussionID)
                    ->put();
            //
            $Sender->JsonTarget(
                '#Topic'.$DiscussionID,
                $this->DisplayTopicAnswer($Topic, $TopicAnswer, $DiscussionID, t('Discussion Topic'), '', '', 'TopicInPost '),
                'ReplaceWith'
            );
            //
            if ($DisplayingDiscussion > 0) {                            //Refresh side panel only when displaying a single discussion.
                $ModuleContent = new DiscussionTopicModule($Sender);
                $Limit = c('Plugins.DiscussionTopic.Panelsize', 8);
                $TopicBox =  wrap($ModuleContent->Refresh($DiscussionID, $Limit, $Debug));
                $Sender->JsonTarget('#TitleBox', $TopicBox, 'ReplaceWith');
            }
        }
        $Sender->Render('Blank', 'Utility', 'Dashboard');
    }
/**
* Display the Topic form.
*
* @param Standard $Sender     Standard
* @param Object   $Discussion Discussion Object
* @param boolean  $Debug      Debugging Request
*
*  @return boolean n/a
*/
    private function showTopicform($Sender, $Discussion, $Debug = false) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $Topic = $Discussion->Topic;
        $DefaultTopic = $this->gettopic($Discussion->Name, '', $Debug);
        $Sender->setData('Topic', $Topic);
        $Sender->setData('DefaultTopic', $DefaultTopic);
        $Sender->setData('DiscussionName', $Discussion->Name);
        $Sender->setData('TopAnswerMode', c('Plugins.DiscussionTopic.TopAnswerMode', false));
        $Sender->setData('TopicAnswer', $Discussion->TopicAnswer);
        //Modes: 1=manual, 2=Deterministic, 3=Heuristic, 4=Progressive (Both 2&3)
        $Mode = 1 + c('Plugins.DiscussionTopic.Mode', 0);
        $ModeArray = array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
        $ModeName = $ModeArray[$Mode];
        //
        $ModeMsg = wrap(t('Current mode is ').$ModeName, 'div');
        $Sender->setData('ModeMsg', $ModeMsg);
        //
        switch ($DefaultTopic) {
            case '':
                if ($Mode == 1) {
                    $FormMsg = wrap(t('The plugin is in manual mode - it does not auto-generate discussion Topics.'), 'div');
                } else {
                    $FormMsg = wrap(t('There is no auto-generated Topic with the current settings and this discussion title.'), 'div');
                }
                break;
            case $Topic:
                $FormMsg = wrap(t('Default Topic matches the saved discussion Topic'), 'div');
                break;
            default:
                $FormMsg = wrap(t('Default (autogenerated) Topic:').'<b>'.$DefaultTopic.'</b>', 'div ');
        }
        if ($Discussion->TopicAnswer) {
            $FormMsg .= wrap('<br><b>'.t('Current Topic is marked as top Topic').'</b>', 'div');
        }
        $Sender->setData('FormMsg', $FormMsg);
        //
        $Sender->Form = new Gdn_Form();
        $Validation = new Gdn_Validation();
        $Postback=$Sender->Form->authenticatedPostBack();
        if (!$Postback) {            //Before form submission
            $Sender->Form->setValue('Topic', $Topic);
        } else {                        //After form submission
            $FormPostValues = $Sender->Form->formValues();
            if ($Sender->Form->ErrorCount() == 0) {
                if (isset($FormPostValues['Cancel'])) {
                    return;
                }
                //
                $this->DebugData($FormPostValues, '---$FormPostValues---', $Debug);
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
                    if (trim($Topic) != '') {
                        return;
                    }
                    $Sender->Form->addError(t('You cannot save an empty Topic.'), 'Topic');
                } elseif (isset($FormPostValues[t('TopSave')])) {
                    $Topic = strip_tags($FormPostValues['Topic']);
                    $Discussion->Topic = $Topic;
                    $Discussion->TopicAnswer = true;
                    if (trim($Topic) != '') {
                        return;
                    }
                    $Sender->Form->addError(t('You cannot save an empty Topic.'), 'Topic');
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
/**
* Process the Guide Review request.
*
*  @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function controller_discussiontopicguide($Sender) {
        $Sender->permission('Garden.Settings.Manage');
        $View = $this->getView('CustomizationandSetupGuide.htm');
        $Sender->render($View);
    }
/**
* Process the Temporaty Sort by Topic request.
*
*  @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function controller_discussiontopicsortbyTopic($Sender) {
        $Sender->permission('Garden.Settings.Manage');
        if (c('Plugins.DiscussionTopic.SortByTopic')) {
            saveToConfig('Plugins.DiscussionTopic.SortByTopic', false);
        } else {
            saveToConfig('Plugins.DiscussionTopic.SortByTopic', true);
        }
        redirect('/discussions');
    }
/**
* Process the search request.
*
*  @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function controller_discussiontopicsearch($Sender) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        if (!CheckPermission('Plugins.DiscussionTopic.View')) {
            return;
        }
        foreach ($_GET as $Key => $Value) {
            if ($Key == "!DEBUG!") {
                $Debug = $Value;
            } elseif ($Key == "try") {
                $Search = $Value;
                $SearchTopic = true;
                if ($Search != '') {
                    $this->FilterTopic($Search, $SearchTopic, $Debug);
                }
            } elseif ($Key == "s") {
                $Search = $Value;
                $SearchTopic = false;
                if ($Search != '') {
                    $this->FilterTopic($Search, $SearchTopic, $Debug);
                }
            }
        }
        //if ($Debug) $this->DebugData($Search, '---Search---', $Debug);
        $this->showsearchform($Sender, $Search, $SearchTopic, $Debug);//Display the form
        //if ($Debug) $this->DebugData($Search, '---Search---', $Debug);
        $Sender->Render('Blank', 'Utility', 'Dashboard');
    }
/**
* Display the Topic Search form.
*
*  @param [type]  $Sender      Standard
*  @param [type]  $Search      Initial Value (optional)
*  @param boolean $SearchTopic [description]
*  @param boolean $Debug       debug request
*
*  @return boolean n/a
*/
    private function showsearchform($Sender, $Search, $SearchTopic = false, $Debug = false) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $Sender->Form = new Gdn_Form();
        $Validation = new Gdn_Validation();
        $Sender->setData('Searchstring', $Search);
        $Postback=$Sender->Form->authenticatedPostBack();
        if (!$Postback) {            //Before form submission
            $Sender->Form->setValue('Searchstring', $Search);
            $Sender->Form->setFormValue('Searchstring', $Search);
            $Sender->Form->setFormValue('SearchTopic', $SearchTopic);
        } else {                        //After form submission
            $FormPostValues = $Sender->Form->formValues();
            //if ($Debug) $this->DebugData($FormPostValues,'---FormPostValues---',$Debug);
            $Data = $Sender->Form->formValues();
            if ($Debug) $this->DebugData($Data,'---Data---',$Debug);
            if ($Sender->Form->ErrorCount() == 0) {
                if (isset($FormPostValues['Cancel'])) {
                    return;
                }
                if (isset($FormPostValues['TopicSearch'])) {
                    $Search = $FormPostValues['Searchstring'];
                    $Sender->Form->SetFormValue('Searchstring', $Search);
                    $this->FilterTopic($Search, false, $Debug);
                } elseif (isset($FormPostValues['FreeFormSearch'])) {
                    $Search = $FormPostValues['Searchstring'];
                    $Sender->Form->SetFormValue('Searchstring', $Search);
                    $this->FilterTopic($Search, true, $Debug);
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
/**
* Place a Topic search menu link on the menu bar.
*
*  @param object $Sender Standard
*
*  @return boolean n/a
*/
    public function base_render_before($Sender) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $ErrorMsg = Gdn::session()->stash('IBPTopicMsg');
        if ($ErrorMsg != '') {
            Gdn::session()->stash('IBPTopicMsg', '');
            Gdn::controller()->informMessage($ErrorMsg);
        }
        //
        $Controller = $Sender->ControllerName;                  //Current Controller
        $MasterView = $Sender->MasterView;
        $AllowedControllers = array('discussionscontroller','discussioncontroller','categoriescontroller');     //Add other controllers if you want
        //$this->DebugData($Controller,'---Controller---',$Debug);
        //$this->DebugData($MasterView,'---MasterView---',$Debug);
        if (!c('Plugins.DiscussionTopic.Showmenu', false)) {
            return;
        }
        if (!CheckPermission('Plugins.DiscussionTopic.View')) {
            return;
        }
        //
        if (InArrayI($Controller, $AllowedControllers)) {
            $Css = 'Popup TopicSearch"  Target="_self';
            $Sender->Menu->AddLink(
                "Menu",
                t('Topic-Search'),
                '/plugin/DiscussionTopic/DiscussionTopicSearch?s=',
                false,
                array('class' => $Css, 'target' => '_self')
            );
        }
    }
/**
* Handle Database update request.
*
*  @param object $Sender Standard
*
*  @return boolean n/a
*/
    public function controller_discussionTopicUpdate($Sender) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $Sender->permission('Garden.Settings.Manage');
        //
        if (!Gdn::Session()->CheckPermission('Plugins.DiscussionTopic.Manage')) {
            echo (wrap(
                'DiscussionTopic plugin ('.__LINE__.') you need to set "Plugins.DiscussionTopic.Manage" Permission to use this function.',
                h1
            ));
            return ;
        }
        //
        $Debug = intval($_GET['!DEBUG!T']);
        if ($Debug) {
            $this->DebugData($_GET, '---$_GET---', $Debug);
        }
        //
        $Restart = intval($_GET['restart']);
        if ($Debug) {
            $this->DebugData($Restart, '---Restart---', $Debug);
        }
        //
        $CssUrl = '/' .  Gdn::Request()->WebRoot() . '/plugins/DiscussionTopic/design/pluginsetup.css?v=2.1.3';
        echo '<link rel="stylesheet" type="text/css" href="' . $CssUrl . '/plugins/DiscussionTopic/design/pluginsetup.css?v=2.1.3" media="all" />';
        //
        //  Handle Topic Clearing Requests
        //
        $Clear = intval($_GET['clear']);
        if ($Debug) {
            $this->DebugData($Clear, '---Clear---', $Debug);
        }
        if ($Clear) {
            $SqlHandle = clone Gdn::sql();//Don't interfere with any other sql process
            $SqlHandle->Reset();
            $Updates = $SqlHandle->update('Discussion d')
                    ->set('d.Topic', null)
                    ->set('d.TopicAnswer', null)
                    ->where('d.DiscussionID <>', 0)
                    ->put();
            $RowCount = count($Updates);
            if ($Debug) {
                $this->DebugData($RowCount, '---Rowcount---', $Debug);
            }
            SaveToConfig('Plugins.DiscussionTopic.Parttialupdate', false);
            SaveToConfig('Plugins.DiscussionTopic.Cleared', true);
            SaveToConfig('Plugins.DiscussionTopic.HighupdateID', 0);
            echo $this->javawindowclose();      //Initialize (Add Javascript to window
            echo $this->javawindowclose(
                Wrap('Topic Data Removed!', 'h2').'Click ',
                'Exit',
                '  to return to the settings screen.',
                'div class="SettingLink"'
            );
            //
            return;
        }
        //
        //
        $IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup', true);
        if ($IncompleteSetup) {
            $Msg = 'DiscussionTopic plugin configuration is incomplete.  The admin needs to complete the configuration without remaining error messages';
            //**Gdn::controller()->informMessage($Msg);
            echo Wrap($Msg, 'H1');
            return;
        }
        //
        $Limit = intval($_GET['limit']);
        if ($Debug) {
            $this->DebugData($Limit, '---Limit---', $Debug);
        }
        $Urllimit = 0 +$Limit;
        if ($Urllimit == 0 | !is_numeric($Urllimit)) {
            $Limit = c('Plugins.DiscussionTopic.Maxrowupdates', 10);
            if ($Debug) {
                $this->DebugData($Limit, '---Limit---', $Debug);
            }
        }
        //
        $Updatecount = $this->updateextract($Limit, $Restart, $Debug);
        if ($Debug) {
            $this->DebugData($Updatecount, '---Updatecount---', $Debug);
        }
    }
/**
 * Update old entries with the extract.
 *
 * @param integer $Limit   Amount of discussions to update in a single batch
 * @param boolean $Restart Request to start the update from the beginning (new batch)
 * @param boolean $Debug   Debug request
 *
 * @return int the count of updated discussions
 *
 */
    private function updateextract($Limit = 10, $Restart = false, $Debug = false) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        //
        $IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup', true);
        if ($IncompleteSetup) {
            $Msg = 'DiscussionTopic plugin configuration is incomplete.  The admin needs to complete the configuration without remaining error messages';
            //**Gdn::controller()->informMessage($Msg);
            echo Wrap($Msg, 'H1');
            return 0;
        }
        //
        $DiscussionModel = new DiscussionModel();
        // Initialize the screen javascript
        echo $this->javawindowclose();//Initialize (Add Javascript to window
        //
        $CategoryNums = c('Plugins.DiscussionTopic.CategoryNums');
        //
        //Get the cetegory ids the user is allowed to see
        $Categories = CategoryModel::getByPermission();
        $Categories = array_column($Categories, 'Name', 'CategoryID');
        //if ($Debug) $this->DebugData($Categories,'---Categories---',$Debug);
        $Categorycount = 0;
        foreach ($Categories as $CategoryID => $CategoryName) {
            //$this->DebugData($CategoryID,'---CategoryID---',$Debug);
            //$this->DebugData($CategoryName,'---CategoryName---',$Debug);
            if ($CategoryNums != "") {
                if (in_array($CategoryID, $CategoryNums)) {//In the list?
                    $Categorycount = $Categorycount + 1;
                    $CategoryList[$Categorycount] = $CategoryID;
                }
            } else {
                $Categorycount = $Categorycount + 1;
                $CategoryList[$Categorycount] = $CategoryID;
            }
        }
        if ($Debug) {
            $this->DebugData($CategoryList, '---Categorylist---', $Debug);
        }
        //
        $UpdateGen = c('Plugins.DiscussionTopic.Updategen', 1);
        if ($Restart) {/*New batch*/
            $StartID = 0;
            $Newgen = 1 + $UpdateGen;
            SaveToConfig('Plugins.DiscussionTopic.HighupdateID', 0);
            $Title = 'New update batch'.str_repeat("&nbsp", 40).' (Batch#'.$Newgen.')';
        } else {    /*Continued batch*/
            $StartID = c('Plugins.DiscussionTopic.HighupdateID', 0);
            $Title = 'Continuing update batch'.str_repeat("&nbsp", 40).' (Batch#'.(1+$UpdateGen).')';
        }
        if ($Debug) {
            $this->DebugData($StartID, '---StartID---', $Debug);
        }
        $UseLimit = $Limit + 1;
        $SqlHandle = clone Gdn::sql();//Don't interfere with any other sql process
        $SqlHandle->Reset();            //Clean slate
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
        if ($Debug) {
            echo '<br>'.__LINE__.' Rowcount:'.$RowCount;
        }
        if ($RowCount == 0) {
            echo wrap(
                '<br> DiscussionTopic.'.__LINE__.' Nothing available for updating the extracts using the current criteria.
                        You may <b>start</b> a new update batch by using the appropriate link in the configuration panel.',
                'div class=SettingLink'
            );
            return 0;
        }
        //
        $Listcount = 0;
        $SqlHandle->Reset();
        $Title = wrap('<b>DiscussionTopic plugin multiple discussions update.</b><br>'.c('Plugins.DiscussionTopic.ModeName').' Mode.'.$Title, 'div');
        ///echo wrap(str_repeat("&nbsp",120),'div class=Settingsqueeze');
        //
        if ($RowCount >  $Limit) {
            $Title .= wrap(
                '<br>Click '.Anchor('Continue', 'plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0', 'Popup ContinueButton ').
                ' to <b>continue</b> the current update batch '.
                $this->javawindowclose('or click the ', 'Exit', ' button to return to the setting screen.  ', '', $Debug),
                'b '
            );
        } else {
            $Title .= $this->javawindowclose('Click ', 'Exit', ' to return to the setting screen. ', 'div', $Debug);
        }
        echo wrap($Title, 'div class=SettingLink');
        $ReportRowNumber = c('Plugins.DiscussionTopic.ReportRowNumber', 0);
        //
        foreach ($Discussionlist as $Entry) {
            $Listcount += 1;
            if ($Listcount <= $Limit) {
                //if ($Debug) $this->DebugData($Entry,'---Entry---',$Debug);
                $DiscussionID = $Entry->DiscussionID;
                $Discussion = $DiscussionModel->getID($DiscussionID);
                $Name = $Entry->Name;
                $Topic = $this->gettopic($Name, '', $Debug);
                $ReportRowNumber += 1;
                $TopicAnswerNote = '';
                if ($Entry->TopicAnswer) {
                    $TopicAnswerNote = wrap(' ***Was Top Topic*** ', 'span style="color:red"');
                }
                echo wrap(
                    '<br>'.$ReportRowNumber.' ID:<b>'.$DiscussionID.$TopicAnswerNote.
                    ' </b>Title:<b>'.SliceString($Name, 60).' </b>Keywords:<b>'.$Topic.'</b>',
                    'span'
                );
                $SqlHandle->update('Discussion d')
                    ->set('d.Topic', $Topic)
                    ->set('d.TopicAnswer', false)
                    ->where('d.DiscussionID', $DiscussionID)
                    ->put();
                $HighWatermark = $DiscussionID;
            }
        }
        $UpdateTopMessage = wrap('<b> '. ($Listcount-1) .' rows updated.</b>', 'span');
        SaveToConfig('Plugins.DiscussionTopic.Cleared', false);
        if ($RowCount >  $Limit) {//Batch incomplete
            echo wrap($UpdateTopMessage.' <b>Note:</b> More rows can be updated.', 'span class=UpdateTopMessage');
            SaveToConfig('Plugins.DiscussionTopic.Parttialupdate', true);
            SaveToConfig('Plugins.DiscussionTopic.HighupdateID', $HighWatermark);
            SaveToConfig('Plugins.DiscussionTopic.ReportRowNumber', $ReportRowNumber);
        } else {            //Batch completed
            echo wrap($UpdateTopMessage.' Note: No more rows can be updated under the current settings.', 'span class=UpdateTopMessage');
            SaveToConfig('Plugins.DiscussionTopic.Parttialupdate', false);
            $Newgen = 1 + $UpdateGen;
            SaveToConfig('Plugins.DiscussionTopic.Updategen', $Newgen);
            SaveToConfig('Plugins.DiscussionTopic.HighupdateID', 0);
            SaveToConfig('Plugins.DiscussionTopic.ReportRowNumber', 0);
        }
        //
        return $Listcount;
    }
/**
 * Adds a close button and script along with the user instruction.
 *
 * @param  string  $Prefix Descriptive text ahead of the button
 * @param  string  $Button Actual button text
 * @param  string  $Suffix Descriptivetext followingthe button
 * @param  string  $Class  Optional CSS class
 * @param  boolean $Debug  Debug request
 *
 * @return [type]          [description]
 */
    private function javawindowclose($Prefix = '', $Button = '', $Suffix = '', $Class = '', $Debug = false) {
        $String = $Prefix;
        if ($Button != '') {
            $String .= wrap('<input type="button" value="' . $Button . '" onclick="windowClose();">', 'span ');
        }
        if ($Suffix != '') {
            $String .= $Suffix;
        }
        if ($String != '') {
            if ($Class !=  '') {
                $String = wrap($String, $Class);
            }
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
/**
 * Build a topic from a string.
 *
 * @param  string  $String   String to analyze
 * @param  string  $Simulate Processing mode to simulate (insteat od the saved mode)
 * @param  boolean $Debug    Debug request
 *
 * @return [type]          [description]
 */
    private function gettopic($String, $Simulate = '', $Debug = false) {
        if (Gdn::session()->checkPermission('Plugins.DiscussionTopic.Manage')) {
            if (substr($String, 0, 7) == '!DEBUG!') {
                $String = substr($String, 7);
                $Debug = true;
            }
        }
        //
        if ($Debug) {
            $this->DebugData('', '', $Debug, true);    //Trace Function calling
            $this->DebugData($String, ' $String');
            $this->DebugData($Simulate, ' $Simulate');
        }
        $OriginalString = $String;
        //Modes: 1=manual, 2=Deterministic, 3=Heuristic, 4=Progressive (Both 2&3)
        $Mode = 1 + c('Plugins.DiscussionTopic.Mode', 0);
        $ModeArray = array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
        $ModeName = $ModeArray[$Mode];
        //
        if ($Simulate != '') {
            $Mode = array_search($Simulate, $ModeArray);
            $ModeName = $Simulate;
        }
        if ($Debug) {
            $this->DebugData($Mode, ' $Mode');
        }
        if ($Debug) {
            $this->DebugData($ModeName, ' $ModeName');
        }
        if ($ModeName == 'Manual') {
            return;
        }
        //
        //Clean up the sentence;
        $String = $this->CleanString($String, $ModeName, $Debug);
        if ($Debug) {
            decho($String, ' $String');
        }
        //Modes: 1=Manual, 2=Deterministic, 3=Heuristic, 4=Progressive (2 & 3)
        if (substr($String, 0, 1) == '"') {
            if ($Debug) {
                decho($String, ' $String');
            }
            if ($ModeName != 'Heuristic') {
                return $String;   //Deterministic or Progressive modes
            }
        }
        if ($ModeName == 'Deterministic') {
            return '';            //No double quoted string and detrministic, so no Topic to return.
        }        //  Handle Heuristic and Progressive modes
        //Get the global update generation number
        $UpdateGen = c('Plugins.DiscussionTopic.Updategen', 1);
        SaveToConfig('Plugins.DiscussionTopic.Updategen', $UpdateGen);
        // Get the Noise Words
        $NoiseArray = $this->GetNoiseArray($Debug);
        //if ($Debug) $this->DebugData($NoiseArray,'---Noisearray---',$Debug);
        //
        //Get the number of significant keywords
        $SigWords = c('Plugins.DiscussionTopic.Sigwords', 2);
        //
        //Check which text Analyzer to use
        $AnalyzerName = c('Plugins.DiscussionTopic.Analyzername', '');
        if ($AnalyzerName == '') {
            echo wrap('DiscussionTopic plugin - Missing Analyzer name', 'h1');
            return '';
        }
        //Analyze the words - in this section we perform language analysis.  This is where you will place your own language analyzer.
        if ($AnalyzerName == 'PosTagger') {
            $Tagger = new PosTagger('lexicon.txt');
            $Tags = $Tagger->tag($String);
            //$Tags = $Tagger->tag($OriginalString);
            //if ($Debug) $this->DebugData($Tags,'---Tags---',$Debug);
            //Remap PosTagger to Textrazor response
            $Words = array_map(function ($tag) {
                return array(
                    'token' => $tag['token'],
                    'stem' => PorterStemmer::Stem($tag['token']),
                    'partOfSpeech' => $tag['tag']
                );
            }, $Tags);
            //if ($Debug) $this->DebugData($Words,'---Words---',$Debug);
        } elseif ($AnalyzerName == 'TextRazor') {
            $Textrazorkey = c('Plugins.DiscussionTopic.TextRazorKey', c('Plugins.DiscussionTopic.TEXTRAZORAPIKEY', ''));
            if ($Textrazorkey == '') {
                echo wrap('DiscussionTopic plugin - Missing Textraor API key', 'h1');
                return '';
            }
            //
            $Textrazor = new TextRazor();
            $Extractor = C('Plugins.DiscussionTopic.Extractor', 'words');
            $Textrazor->addExtractor($Extractor);
            $Textrazor->setLanguageOverride('eng');
            $text = $String;
            $Response = $Textrazor->analyze($text);
            //if ($Debug) $this->DebugData($Response,'---Response---',$Debug);
            $Words = $Response['response']['sentences'][0]['words'];
        }
        //Parse Response
        $String = ' ';
        if (isset($Words)) {
            $Verbs = array();
            $Nouns = array();
            $Adjectives = array();
            $i = 0;
            $n = 0;
            $v = 0;
            $jj = 0;
            $Keywords = array();
            foreach ($Words as $Entry) {
                if ($Debug) {
                    decho($Entry, ' $Entry');
                }
                /*Sample structure: Entry--- array   - This is the expected structure of language analysis.  Use it with otter language analyzers
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
                //$Partofspeech = "NN";                 //Uncomment this line to disable language analysis
                if (strlen($Token) > 1 && !in_array($Token, $NoiseArray)) {
                    $i += 1;
                    $Catchprefix = substr($Partofspeech, 0, 2);//The first two letters denotic the part of speech in a sentence
                    switch ($Catchprefix) {
                        case "NN":                              //The only accepted words are nouns and verbs
                        case "XC":
                            $Nouns[$n] =  ucwords($Stem);
                            $n += 1;
                            break;
                        case "VB":                              //Accepting verbs
                            $Verbs[($v)] =  ucwords($Stem);
                            $v += 1;
                            break;
                        case "JJ":                              //Accepting verbs
                            $Adjectives[($v)] =  ucwords($Stem);
                            $jj += 1;
                            break;
                    }
                }
            }
            //
            if ($Debug) {
                decho($Nouns, ' $Nouns');
            }
            if ($Debug) {
                decho($Verbs, ' $Verbs');
            }
            if ($Debug) {
                decho($Adjectives, ' $Adjectives');
            }
            //
            for ($j = 0; $j<count($Nouns); $j++) {                      //Give priority to nouns
                $Keywords[$j] = $Nouns[$j];
            }
            for ($k = 0; $k<count($Verbs); $k++) {                      //Only then look at verbs
                $j += 1;
                $Keywords[$j] = $Verbs[$k];
                if ($Debug) {
                    echo wrap('k:'.$k.' j:'.$j.', $Verbs[$k]:'.$Verbs[$k], 'div');
                }
            }
            for ($k = 0; $k<count($Adjectives); $k++) {                     //Only then look at Adjectives
                $j += 1;
                $Keywords[$j] = $Adjectives[$k];
                if ($Debug) {
                    echo wrap('k:'.$k.' j:'.$j.', $Adjectives[$k]:'.$Adjectives[$k], 'div');
                }
            }
            //
            if ($Debug) {
                decho($Keywords, ' $Keywords');
            }
            //
            $Keywords = array_unique($Keywords);
            $Keywords = array_filter($Keywords);
            ksort($Keywords);                                                   //Standardize Topic order (login failed == failed login)
            //if ($Debug) $this->DebugData($Keywords,'---Keywords---',$Debug);
            $Keywords = array_slice($Keywords, 0, $SigWords);
            $String = implode(",", $Keywords);
        }
        if ($AnalyzerName == 'TextRazor') {
            unset($Textrazor);
        }
        $String = trim($String);
        if ($Debug) {
            decho($String, ' $String');
        }
        return $String;
    }
/**
 * Extract quoted string (if any).
 *
 * @param  string  $String String to analyze
 * @param  boolean $Debug  Debug request
 *
 * @return string The exctacted quoted string
 */
    private function getquoted($String, $Debug = true) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        preg_match_all('/"([^"]+)"/', $String, $Results);
        if ($Debug) {
            $this->DebugData($Results, ' $Results');
        }
        foreach ($Results[1] as $Quoted) {
            if ($Debug) {
                $this->DebugData($Quoted, ' $Quoted');
            }
            if (strlen(trim($Quoted)) > 2) {
                return '"'.trim($Quoted).'"';
            }
        }
        return $String;
    }
/**
 * Prepare String for analyzing.
 *
 * @param  string  $String   String to process
 * @param  string  $ModeName Processing mode
 * @param  boolean $Debug    Debug request
 *
 * @return String  Result String
 */
    private function cleanstring($String, $ModeName, $Debug = false) {
        if ($Debug) {
            //$this->DebugData('','',$Debug,true);  //Trace Function calling;
            $this->DebugData($String, ' $String');
            $this->DebugData($ModeName, ' $ModeName');
        }
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        $String =  $this->ChangeStringByNoise($String, $Debug);
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        ////
        // Replace multiple spaces with single spaces
        $String = preg_replace('!\s+!', ' ', $String);
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        // Acronym replacement
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        $String = $this->ChangeByAcronym($String, $Debug);
        $Newstring = $this->ChangeByPriority($String, $Debug);
        if ($Debug) {
            $this->DebugData($Newstring, ' $Newstring');
        }
        // Priority phrases replacement
        if ($Newstring != $String) {
            return $Newstring;       //Substitution was made
        }        //
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        // Extract Quoted strings for Deterministic/Progressive modes
        if ($ModeName != 'Heuristic') {
            //Following substitution extract quoted string (if any) and if found use it as the Topic
            $String = $this->GetQuoted($String, $Debug);
            if (substr($String, 0, 1) == '"') {
                return $String;
            }
        }
        // No quoted strings, let's start the hard language analysys work.
        // Clear unnecessary punctioations
        $String = preg_replace("/(?![$])\p{P}/u", " ", strtolower($String));
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        //
        // Clear numbers leaving words with embedded numbers
        $Name = preg_replace("/(\b)[0-9]+(\b)/", ' ', $String);
        if ($Debug) {
            $this->DebugData($Name, ' $Name');
        }
        //
        // Tokenize (except for quoted texts
        preg_match_all("/(['\".'\"])(.*?)\\1|\S+/", $Name, $Result);
        $Tokens = $Result[0];
        if ($Debug) {
            $this->DebugData($Tokens, ' $Tokens');
        }
        //
        // Remove Noise Words
        $Tokens = $this->ChangeTokensByNoise($Tokens, $Debug);
        if ($Debug) {
            $this->DebugData($Tokens, ' $Tokens');
        }
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
        if ($Debug) {
            $this->DebugData($Tokens, ' $Tokens');
        }
        $String = implode(" ", $Tokens);
        //
        //if ($Debug) $this->DebugData($String,' $String');
        return $String;
    }
/**
 * Build the array of noise words.
 *
 * @param  boolean $Debug Debug request
 *
 * @return array noise array
 */
    private function getnoisearray($Debug = false) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $NoiseWords = strtolower(c('Plugins.DiscussionTopic.Noisewords', ' '));
        $Localnoisearray = $this->GetExplode($NoiseWords, 0);
        $Globalnoisearray = array('');
        $Globalnoisearray = t('DiscussionTopicNoisewords1');
        //if ($Debug) $Globalnoisearray = array('1global23');  //T E S T I N G
        if ($Debug) {
            $this->DebugData($Tokens, ' Tokens');
        }
        $NoiseArray = array_merge($Localnoisearray, $Globalnoisearray);
        $NoiseArray = array_change_key_case($NoiseArray, CASE_LOWER);
        if ($Debug) {
            $this->DebugData($NoiseArray, ' NoiseArray');
        }
        return $NoiseArray;
        //
    }
/**
 * Remove noise words from a string.
 *
 * @param  string  $String String to process
 * @param  boolean $Debug  Debug request
 *
 * @return String  Result String
 */
    private function changestringbynoise($String, $Debug = false) {
        //$Debug = true;
        if ($Debug) {
            //$this->DebugData('','',$Debug,true);  //Trace Function calling
            decho(func_get_args(), ' Passed Parameters');
        }
        // Clear unnecessary punctioations
        $String = str_replace(array('.', ','), ' ', $String);
        //
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        // Clear numbers leaving words with embedded numbers
        $Name = preg_replace("/(\b)[0-9]+(\b)/", ' ', $String);
        if ($Debug) {
            $this->DebugData($Name, ' $Name');
        }
        //
        // Tokenize (except for quoted texts
        preg_match_all("/(['\".'\"])(.*?)\\1|\S+/", $Name, $Result);
        $Tokens = $Result[0];
        //
        $NoiseArray = $this->GetNoiseArray($Debug);
        if ($Debug) {
            $this->DebugData($NoiseArray, ' NoiseArray');
        }
        //
        $Tokens = array_udiff($Tokens, $NoiseArray, 'strcasecmp');
        if ($Debug) {
            $this->DebugData($Tokens, ' Tokens');
        }
        $String = implode(" ", $Tokens);
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        return $String;
    }
/**
 * Remove noise words from a token array.
 *
 * @param  array   $Tokens Tokens array to process
 * @param  boolean $Debug  Debug request
 *
 * @return Array  Result array without the noise words
 */
    private function changetokensbynoise($Tokens, $Debug = false) {
        //$Debug = true;
        if ($Debug) {
            //$this->DebugData('','',$Debug,true);  //Trace Function calling
            decho(func_get_args(), ' Passed Parameters');
        }
        //
        $NoiseArray = $this->GetNoiseArray($Debug);
        if ($Debug) {
            $this->DebugData($NoiseArray, __LINE__);
        }
        //
        $Tokens = array_values(array_diff($Tokens, $NoiseArray));
        if ($Debug) {
            $this->DebugData($Tokens, ' $Tokens');
        }
        return $Tokens;
    }
/**
 * Add double quotes around priority phrases in a string.
 *
 * @param  string  $String String to process
 * @param  boolean $Debug  Debug request
 *
 * @return String  Result String (original or the priority phrase)
 */
    private function changebypriority($String, $Debug = false) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        //$this->DebugData($String,' $String');
        //
        $PriorityList = c('Plugins.DiscussionTopic.Prioritylist', '');
        $PriorityArray = $this->GetExplode($PriorityList, 0);
        if ($Debug) {
            $this->DebugData($PriorityArray, ' PriorityArray');
        }
        //if ($Debug) $this->DebugData($String,' $String');
        // Priority phrases replacement
        foreach ($PriorityArray as $Entry => $Priority) {
            $SaveString = $String;
            $String = preg_replace('/\b' . preg_quote($Priority) . '\b/i', '"'.$Priority.'"', $String);
            //if ($Debug) $this->DebugData($Priority,'---Priority---',$Debug);
            //if ($Debug) $this->DebugData($String,' $String');
            if ($SaveString != $String) {//Substitution was made
                if ($Debug) {
                    $this->DebugData($String, ' $String');
                }
                $String = $this->GetQuoted($String, $Debug);
                if ($Debug) {
                    $this->DebugData($String, ' $String');
                }
                return $String;
            }
        }
        return $String;
    }
/**
 * Change string's acronyms with their respective definitions.
 *
 * @param  string  $String String to process
 * @param  boolean $Debug  Debug request
 *
 * @return String  Result String
 */
    private function changebyacronym($String, $Debug = true) {
        $this->DebugData('', '', $Debug, true);  //Trace Function calling;
        //$this->DebugData($String,' $String');
        //
        $Acronyms = c('Plugins.DiscussionTopic.Acronyms', '');
        $LocalAcronymArray = $this->GetExplodeByKey($Acronyms, 0);
        if ($Debug) {
            $this->DebugData($LocalAcronymArray, ' LocalAcronymArray');
        }
        $GlobalAcronymArray = t('DiscussionTopicAcronyms');
        $AcronymArray = array_merge($LocalAcronymArray, $GlobalAcronymArray);
        if ($Debug) {
            $this->DebugData($AcronymArray, ' AcronymArray');
        }
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        // Acronym replacement
        $String = str_ireplace(array_keys($AcronymArray), array_values($AcronymArray), $String);
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        return $String;
    }
/**
 * Plugin setup.
 *
 *  @return  n/a
 */
    public function setup() {
        $this->Structure();
        $this->InitializeConfig();
    }
/**
 * Plugin disable hook.
 *
 *  @return  n/a
 */
    public function ondisable() {
        $this->Structure();
    }
/**
 * Plugin Initialization function.
 *
 *  @return  n/a
 */
    private function initializeconfig() {
        //Set default config options
        touchConfig(array(
                'Plugins.DiscussionTopic.Noisewords' => 'Vanilla,forum',
                'Plugins.DiscussionTopic.Acronyms' => 'btn=button,config=configuration,db=database',
                'Plugins.DiscussionTopic.Sigwords' => '2',
                'Plugins.DiscussionTopic.Paneltitle' => 'Related Discussions',
                'Plugins.DiscussionTopic.Analyzer' => array('1'),
                'Plugins.DiscussionTopic.Extractor' => 'words',
                'Plugins.DiscussionTopic.Testtitle' => 'How does the "Discussion Topic" plugin works it\'s magic?',
                'Plugins.DiscussionTopic.Maxrowupdates' => 10,
                'Plugins.DiscussionTopic.Updategen' => 1,
                'Plugins.DiscussionTopic.Parttialupdate' => 0,
                'Plugins.DiscussionTopic.HighupdateID' => 0,
                'Plugins.DiscussionTopic.Mode' => 3,
                'Plugins.DiscussionTopic.Showmenu' => true,
                'Plugins.DiscussionTopic.TopAnswerMode' => false
            ));
        //Force default config options
        SaveToConfig('Plugins.DiscussionTopic.RedoConfig', false);
        SaveToConfig('Plugins.DiscussionTopic.FirstSetupDone', false);
        SaveToConfig('Plugins.DiscussionTopic.ReportRowNumber', 0);
        SaveToConfig('Plugins.DiscussionTopic.SortByTopic', 0);
    }
/**
 * Plugin database structure hook.
 *
 *  @return  n/a
 */
    public function structure() {
        Gdn::database()->structure()
            ->table('Discussion')
            ->column('Topic', 'varchar(100)', true)
            ->column('TopicAnswer', 'tinyint(1)', '0')
            ->set();
    }
/**
 * Add plugin's CSS.
 *
 *  @param Standard $Sender Standard
 *
 *  @return  n/a
 */
    public function assetmodel_stylecss_handler($Sender) {
        $Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');
    }
/**
 * Plugin Settings.
 *
 *  @param Standard $Sender Standard
 *  @param Standard $Args   Standard
 *
 *  @return  n/a
 */
    public function settingscontroller_discussiontopic_create($Sender, $Args) {
        $Debug = false;
        $Sender->permission('Garden.Settings.Manage');
        if ($Debug) {
            echo __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'];
        }
        //if ($Debug) $this->DebugData($Args,'---Args---',$Debug);
        $Sender->addCssFile('pluginsetup.css', 'plugins/DiscussionTopic');
        $Sender->addSideMenu('dashboard/settings/plugins');
        //
        $PluginInfo = Gdn::pluginManager()->getPluginInfo('DiscussionTopic');
        //if ($Debug) this->DebugData($PluginInfo,'---Plugininfo---',$Debug);
        $Constants = $PluginInfo['PluginConstants'];
        $MaxBatch = $Constants['Maxbatch'];
        //
        if (c('Plugins.DiscussionTopic.RedoConfig', false)) {
            $this->InitializeConfig();
        }
        //
        $IncompleteSetup = c('Plugins.DiscussionTopic.IncompleteSetup', false);
        $GotError =false;
        $TopWarning = '';
        $FieldErrors = '';
        $FeedbackArray = array();
        //
        $AnalyzerArray = array(0 => '?', 1 => 'PosTagger', 2 =>'TextRazor');
        //
        $ModeArray = array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
        //
        $ConfigurationModule = new ConfigurationModule($Sender);
        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $Sender->Form->SetModel($ConfigurationModel);
        if ($Debug) {
            echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
        }
        //
        if ($Sender->Form->authenticatedPostBack()) {
            if ($Debug) {
                echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
            }
            $Saved = $Sender->Form->showErrors();
            $Saved = $Sender->Form->Save();
            $FormPostValues = $Sender->Form->formValues();
            $Sender->Form->SetData($FormPostValues);
            $Validation = new Gdn_Validation();
            $Data = $Sender->Form->formValues();
            //if ($Debug) $this->DebugData($Sender->Form,'---Form---',$Debug);
            //if ($Debug) $this->DebugData($Data,'---Data---',$Debug);
            //
            $NoiseWords = strtolower(getvalue('Plugins.DiscussionTopic.Noisewords', $Data));
            //      Flag to link DiscussionTopics
            $Acronyms = getvalue('Plugins.DiscussionTopic.Acronyms', $Data);
            //
            $Analyzer = getvalue('Plugins.DiscussionTopic.Analyzer', $Data);
            //
            $PriorityList = getvalue('Plugins.DiscussionTopic.Prioritylist', $Data);
            //
            $Mode = getvalue('Plugins.DiscussionTopic.Mode', $Data);
            //
            $SigWords = getvalue('Plugins.DiscussionTopic.Sigwords', $Data);
            //
            $TestTitle = getvalue('Plugins.DiscussionTopic.Testtitle', $Data);
            //
            $Paneltitle = getvalue('Plugins.DiscussionTopic.Paneltitle', $Data);
            //
            $MaxRowUpdates = getvalue('Plugins.DiscussionTopic.Maxrowupdates', $Data);
            // Max batch size is a plugininfo constant
            $FieldErrors .= $this->CheckField(
                $Sender,
                $MaxRowUpdates,
                array('Integer' => ' ','Min' => '2','Max' => $MaxBatch),
                'Number of discussions to update in the Table Update batch ',
                'Plugins.DiscussionTopic.Maxrowupdates'
            );
            //
            $FieldErrors .= $this->CheckField(
                $Sender,
                $Paneltitle,
                array('Required' => 'Title'),
                'Side Panel Title',
                'Plugins.DiscussionTopic.Paneltitle'
            );
            //
            $AnalyzerName = $AnalyzerArray[$Analyzer+1];
            SaveToConfig('Plugins.DiscussionTopic.Analyzername', $AnalyzerName);
            //
            $ModeName = $ModeArray[$Mode+1];
            SaveToConfig('Plugins.DiscussionTopic.ModeName', $ModeName);
            //if ($Debug) $this->DebugData($Mode,'---Mode---',$Debug);
            //if ($Debug) $this->DebugData($ModeName,'---ModeName---',$Debug);
            //
            $FieldErrors .= $this->CheckField(
                $Sender,
                $SigWords,
                array('Required' => 'Integer','Min' => '2','Max' => '4'),
                'Number of keywords',
                'Plugins.DiscussionTopic.Sigwords'
            );
            if ($Debug) {
                $this->DebugData($FieldErrors, '---FieldErrors---', $Debug);
            }
            //
            if ($Debug) {
                echo '<br>'.__LINE__.'FieldErrors:'.$FieldErrors;
            }
            //
            if ($FieldErrors != '') {
                $GotError=true;
                $Sender=$Validation->addValidationResult('Plugins.DiscussionTopic.CategoryNums', ' ');
                $TopWarning = t('Errors need to be corrected. Incomplete settings saved');
                //**Gdn::controller()->informMessage($TopWarning);//,'DoNotDismiss');
            }
            if (!$Validation->validate($FormPostValues)) {
                $GotError=true;
            }
            if ($GotError) {
                if ($Debug) {
                    echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
                }
                SaveToConfig('Plugins.DiscussionTopic.IncompleteSetup', true);
                $Sender=$Validation->addValidationResult('Plugins.DiscussionTopic.SearchBody', ' ');
            } else {
                // No errors
                SaveToConfig('Plugins.DiscussionTopic.FirstSetupDone', true);
                if ($Debug) {
                    echo '<br>'.__LINE__.'IncompleteSetup:'.$IncompleteSetup;
                }
                SaveToConfig('Plugins.DiscussionTopic.IncompleteSetup', false);
                if ($TestTitle != '') {
                    SaveToConfig('Plugins.DiscussionTopic.Testtitle', '');
                    $Simulate = $ModeName;
                    if ($ModeName == 'Manual') {
                        $Simulate = 'Progressive';
                    }
                    $Extract = $this->gettopic($TestTitle, $Simulate, $Debug);
                    $ExtractTitle = '';
                    if ($Extract == '') {
                        if ($ModeName == 'Deterministic') {              //Deterministic mode
                            $ExtractNote = 'The test discussion name did not generate any title - Deterministic mode is used and double-quoted texts not found';
                        } elseif ($ModeName == 'Manual') {
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
                        $ExtractNote =  wrap('Test title:<b>'.$TestTitle.'</b><br>', 'span class=Error').
                                        wrap($Simulate . ' mode generated Topic: <b>'.$Extract.'</b>', 'span class=Error');
                    }
                    $FeedbackArray['SimulatedTitle'] = $ExtractNote;
                    $FeedbackArray['SimulatedNote'] = '<a href="#test" class="SettingTest" '.$ExtractTitle.'>🔴 Click to jump to the test results section</a>';
                    SaveToConfig('Plugins.DiscussionTopic.Testtitle', '');
                    //$AddError = $Sender->Form->addError(wrap($ExtractNote,'span class=SettingTest'),'Plugins.DiscussionTopic.Testtitle');
                }
            }
            //
        } else {    // Not postback
            //SaveToConfig('Plugins.DiscussionTopic.Testtitle','');
            if (c('Plugins.DiscussionTopic.IncompleteSetup')) {
                $TopWarning = 'Previously saved settings are incomplete/invalid.  Review and save correct values.';
            }
            $Sender->Form->SetData($ConfigurationModel->Data);
        }
        //
        $PluginConfig = $this->SetConfig($FeedbackArray, $Debug);// Array('TopWarning' => $TopWarning),$Debug);
        $ConfigurationModule->initialize($PluginConfig);
        $ConfigurationModule->renderAll();
    }
/**
 * Function to handle future saving of arrary as lists  (for future expansion)
 *
 * @param [type] $Variable Variable
 * @param string $Default  Default/Set Value
 *
 *  @return  n/a
 */
    private function setvariabledefault($Variable, $Default = '') {
        $Value = c($Variable, $Default);
        SaveToConfig($Variable, $Value);
    }
/**
* Explode by a comma delimiter, trim the values and remove empty entries if any.
*
* @param  string  $String String to process
* @param  boolean $Debug  Debug request
*
*  @return  string
*/
    private function getexplode($String, $Debug) {
        //$this->DebugData('', '',$Debug, true); //Trace Function calling
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        $Array = explode(',', $String);
        $Array = array_map('trim', $Array);
        $Array = array_filter($Array);
        if ($Debug) {
            $this->DebugData($Array, '---Array---', $Debug);
        }
        return $Array;
    }
/**
* Explode a key=value sets by a comma delimiter, trim the values and remove empty entries if any.
*
* @param  string  $String String to process
* @param  boolean $Debug  Debug request
*
*  @return  keyed array
*/
    private function getexplodebykey($String, $Debug) {
        //$this->DebugData('', '',$Debug, true); //Trace Function calling
        if ($Debug) {
            $this->DebugData($String, ' $String');
        }
        //$String = str_replace("'", "\'", $String);
        $Array = explode(',', $String);
        $Array = array_map('trim', $Array);
        //
        for ($i = 0; $i<count($Array); $i++) {
            list($Key, $Value) = explode('=', $Array[$i]);
            $Key = strtolower(trim($Key));
            $Keyarray[$Key] = trim($Value);
        }
        if ($Debug) {
            $this->DebugData($Keyarray, '---Keyarray---', $Debug);
        }
        return $Keyarray;
    }
/**
 * Set Confogiration Array
 *
 * @param array  $Errors array or error messages
 * @param bool   $Debug  debug request
 *
*  @return  config array
*/
    private function setconfig($Errors = array(), $Debug = false) {
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
        if ($SimulatedNote != '') {
            $SimulatedNote = wrap($SimulatedNote, 'span  class=SettingAsideN');
        }
        $SimulatedTitle = trim($Errors['SimulatedTitle']);
        if ($SimulatedTitle != '') {
            $SimulatedTitle = wrap($SimulatedTitle, 'div class=SettingTest id="#test"');
            SaveToConfig('Plugins.DiscussionTopic.Testtitle', '');
        }
        //
        $PluginInfo = Gdn::pluginManager()->getPluginInfo('DiscussionTopic');
        //if ($Debug) this->DebugData($PluginInfo,'---Plugininfo---',$Debug);
        $Constants = $PluginInfo['PluginConstants'];
        $Title = Wrap($PluginInfo['Name'].'-'.' Version '.$PluginInfo['Version'].' Settings', 'div class=SettingHead');
        //
        $LocalPlace = '/plugin/DiscussionTopic/locale/en-CA/definitions.php';
        //
        $Mode = 1+c('Plugins.DiscussionTopic.Mode', 0);
        $ModeArray = array(0 => '?', 1 =>'Manual', 2 =>'Deterministic', 3 => 'Heuristic', 4 => 'Progressive');
        $ModeName = $ModeArray[$Mode];
        //if ($Debug) $this->DebugData($ModeName,'---ModeName---',$Debug);
        //
        $UpdateGen = c('Plugins.DiscussionTopic.Updategen', 1);
        $Continueurl = '' ;
        $CompletedNote = '';
        $Initializetext = '';
        if (c('Plugins.DiscussionTopic.FirstSetupDone', false)) {
            $CompletedNote = wrap('🔴 Previous update batch (#'.$UpdateGen.') completed.', 'span  class=SettingAsideRedN  ');
        }
        if (c('Plugins.DiscussionTopic.Parttialupdate', false)) {
            $Continueurl = wrap(
                'Click'.Anchor(
                    'continue',
                    'plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=0&limit=0',
                    'Button  ',
                    array('target'=>"New")
                ).
                ' to <b>continue</b> batch '.$UpdateGen.
                ' update to process discussion titles not processed by the previous batch (remember, only '.
                c('Plugins.DiscussionTopic.Maxrowupdates').' records are handled at a time)',
                'span  class=SettingAsideN  '
            );
            $CompletedNote = '';
        }
        $Clearurl = wrap(
            'Click'.
            Anchor('remove', 'plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0,&clear=1', 'Button  ', array('target'=>"New")).
                    ' to <b>delete </b> any previously saved titles.<b>Use with care!</b>',
            'span  class=SettingAsideN'
        );
        if (c('Plugins.DiscussionTopic.Cleared', false)) {
            $Clearurl = '';
            $CompletedNote = $CompletedNote = wrap('▷ Topic data cleared.', 'span  class=SettingAsideRedN  ');
        }
        $Restarturl = wrap(
            'Click'.Anchor(
                'Start',
                'plugin/DiscussionTopic/DiscussionTopicUpdate/?&restart=1&limit=0 ',
                'Button   ',
                array('target'=>"New")
            ).
            ' to <b>start a new update batch</b> (fresh analysis of discussion titles)',
            'span  class=SettingAsideN  '
        )
        .$Clearurl;
        //
        if (c('Plugins.DiscussionTopic.IncompleteSetup', false) | $ModeName == 'Manual') {
            $Continueurl = '' ;
            $Restarturl = '';
            $CompletedNote = '';
        }
        if ($Continueurl.$Restarturl != '') {
            $Initializetext = wrap(wrap('<b>Discussion Table Update</b>', 'h3').wrap(
                wrap('Titles are attached to discussions when the discussion body (not comments) are saved.', 'span  class=SettingAsideN').
                            wrap('You can use this Table Update process to attach titles to discussions without Topics (e.g. discussions created before plugin activation) or to reconstruct the Topics when you change some of the settings.', 'span  class=SettingAsideN').
                            $CompletedNote.wrap('The following update options are available:', 'span  class=SettingAsideU').
                            $Continueurl.$Restarturl,
                'span  class=SettingAsideN'
            ), 'div class=SettingAside');
        }
        $ConstructionNotes = wrap(wrap('<b>Topic Construction notes</b>', 'h3').wrap(
            wrap('In Deterministic mode Priority phrases add quotes to the phrases in the discussion title.  Then <i>the first</i> double-quoted string within the title is picked as the Topic.', 'span  class=SettingAsideN').
                            wrap('To increase the matching of related discussions, Heuristic mode uses simulated word roots ("Stems"). The resulting Topic may seem mistyped - this is not a bug.', 'span  class=SettingAsideN').
                            wrap('Heuristic process is inherently implerfect, especially with free form user input.', 'span  class=SettingAsideN').
                            ' ',
            'span '
        ), 'div class=SettingAside');
        $NoiseNotes = wrap(wrap('<b>Nose word note</b>', 'h3').wrap(
            wrap('The noise words specified on this screen are adeed to the noise word definitions in the plugin LOCALE file. For English, the locale is in: '.$LocalPlace, 'span  class=SettingAsideN').
                            ' ',
            'span '
        ), 'div class=SettingAside');
        $AcronymsNotes = wrap(wrap('<b>Substitute Words note</b>', 'h3').wrap(
            wrap('The substitute words specified on this screen are adeed to the substitute word definitions in the plugin LOCALE file mentioned above.', 'span  class=SettingAsideN').
                            wrap('Hint: If you substitute the word with a double-quoted phrase, it will behave as a Priority Phrase.', 'span  class=SettingAsideN').
                            ' ',
            'span '
        ), 'div class=SettingAside');
        $CustomizationGuide     = wrap('○ Click '.Anchor('here', 'plugin/DiscussionTopic/DiscussionTopicGuide', array('class'=>"NoPopup",'target'=>"Popup")).
                                    ' for the Customization Guide.', 'span  class=SettingAsideN');
        $GeneralNotes = wrap(wrap('<b>General Notes</b>', 'h3').wrap(
            $SimulatedNote.$CustomizationGuide.
                            wrap('○ With the exceptions noted below, the Topic analysis takes place when a discussion is saved, so the performance impact should be small.', 'span  class=SettingAsideN').
                            wrap('○ By limiting the processing to specific categories you can further minimize the performance impact.', 'span  class=SettingAsideN').
                            wrap('○ If you have a process that saves many discussions at a time (e.g. feed imports) that process will be impacted.', 'span  class=SettingAsideN').
                            wrap('○ Another example of saving many discussions at a time is the (re)updating of many previosly saved discussions through the <b>Table Update</b> process below.  For that reason the update process is done in batches and you can sepecify the number of records in the update batch.', 'span  class=SettingAsideN').
                            ' ',
            'span '
        ), 'div class=SettingAsideWide');
        $Snippets   =       wrap(wrap('<b>Visibility Options Examples</b>', 'h3').
                            wrap('Side Panel', 'span  class=SettingAsideSubhead').
                            wrap('<img src="../plugins/DiscussionTopic/sidepanelsnippet.jpg" class=Snippet>').
                            wrap('Discussion List', 'span class=SettingAsideSubhead').
                            wrap('<img src="../plugins/DiscussionTopic/discussionlistmetaareasnippet.jpg" class=Snippet>').
                            wrap('Discussion Meta Area', 'span class=SettingAsideSubhead').
                            wrap('<img src="../plugins/DiscussionTopic/discussionbodymetaareasnippet.jpg" class=Snippet>'), 'div class=SettingAsideWide');
        //
        $WarnGarden = '';
        $Sidepanelnote = 'FYI, if you use the ModuleSort plugin, the internal name of the sidepanel is \'DiscussionTopicModule\'.';
        // Get all categories.
        $Categories = CategoryModel::categories();
        // Remove the "root" categorie from the list.
        unset($Categories[-1]);
        //
        $AnalyzerArray = array(0 => '?', 1 => 'PosTagger', 2 =>'TextRazor');
        unset($AnalyzerArray);
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
            'Description' =>    $Snippets.wrap('<b>Side Panel Title</b>', 'span class=SettingSubh').
                                wrap(
                                    'Enter the title for the side panel showing discussions with related title content:'.
                                    '<br>(Important: Requires "View" permission in the plugin section of "Roles and Permissions")',
                                    'span class=SettingText title="'.$Sidepanelnote.'"'
                                ),
            'LabelCode' =>      wrap(
                '<b>Visibility&nbspOptions</b>',
                'span class=SettingNewSection'
            ).wrap(' ', 'span class=Settingsqueeze'),
            'Default' => 'Common Topic',
            'Options' => array('Class' => 'Textbox')),
        //
            'Plugins.DiscussionTopic.Showinlist' => array(
            'Control' => 'Checkbox',
            'LabelCode' =>  wrap(
                '1. Display the discussion Topic in the <i>discussion list</i> meta area',
                'span class=SettingText title="'.$Sidepanelnote.'"'
            ),
            'Description' =>    '',
            'Default' => false,
            'Options' => array('Class' => 'Optionlist ')),
        //
            'Plugins.DiscussionTopic.Showindiscussion' => array(
            'Control' => 'Checkbox',
            'LabelCode' =>  wrap(
                '2. Display the discussion Topic in the <i>discussion body</i>',
                'span class=SettingText'
            ),
            'Description' =>    '',
            'Default' => false,
            'Options' => array('Class' => 'Optionlist ')),
        //
            'Plugins.DiscussionTopic.ShowHeuristic' => array(
            'Control' => 'Checkbox',
            'LabelCode' =>  wrap('3. For the two options above, display <i>heuristic</i> Topics if deterministic ones are not found.', 'span class=SettingText').
                    wrap('See below Progressives Topic construction.', 'span class=SettingTextContinue'),
            'Description' =>    '',
            'Default' => false,
            'Options' => array('Class' => 'Optionlist ')),
        //
            'Plugins.DiscussionTopic.Showgear' => array(
            'Control' => 'Checkbox',
            'LabelCode' =>  wrap(
                '4. Provide the ability to set the discussion Topic through a link in the discussion option list (the "gear" <b>❅</b>)',
                'span class=SettingText'
            ).
                    wrap('(Important: Requires "manage" permission in the plugin section of "Roles and Permissions".)', 'span class=SettingTextContinue'),
            'Description' =>    '',
            'Default' => false,
            'Options' => array('Class' => 'Optionlist ')),
        //
            'Plugins.DiscussionTopic.TopAnswerMode' => array(
            'Control' => 'Checkbox',
            'LabelCode' =>  wrap('5. Provide the ability to mark discussions as "Top Topics". Top Topics will be pushed to the top of the side panel.', 'span class=SettingText').                wrap('Useful when discussions offer solutions to frequently asked questions', 'span class=SettingTextContinue').
                            wrap('(Important: Requires options 4 above (Setting Topics through the options menu).', 'span class=SettingTextContinue'),
            'Description' =>    '',
            'Default' => false,
            'Options' => array('Class' => 'Optionlist ')),
        //
            'Plugins.DiscussionTopic.Showmenu' => array(
            'Control' => 'Checkbox',
            'LabelCode' =>  wrap(
                'Display a "Topic-Search" menu in the menu bar',
                'span class=SettingText'
            ),
            'Description' =>    '',
            'Default' => false,
            'Options' => array('Class' => 'Optionlist ')),
        //
            'Plugins.DiscussionTopic.Mode' => array(
            'Control' => 'Radiolist',
            'Items' => array(
                '1. Manual - Topic must be set manually through the options menu (Obviously, Visibility option 4 above must be enabled).',
                '2. Deterministic - Pick a "Double-Quoted-Text" or "Priority Phrases" (see below) in the discussion Name as the discussion Topic.',
                '3. Heuristic - Attempt language analysis of the discussion name to determine the discussion Topic.',
                '4. Progressive - Try the Deterministic approach and if double-quoted string is not found try Heuristics.'),
            'Description' =>    $ConstructionNotes.wrap('Select one of these Topic construction modes:', 'span class=SettingText'),
            'LabelCode' =>      wrap('<b>Discussion&nbspTopic&nbspConstruction&nbspMode</b>', 'span class=SettingNewSection'),
            'Default' => '4',
            'Options' => array( 'Class' => 'RadioColumn ')),
        //
            'Plugins.DiscussionTopic.Prioritylist' => array(
            'Control' => 'TextBox',
            'Description' =>    wrap('Optionally enter priority phrases to be selected as Topics when they appear <b>without quoted</b> in the    discussion title.', 'span class=SettingText').
                                wrap('When Discussion Topic Construction mode 1 or 3 are active, discussion title analysis will behave', 'span class=SettingText').
                                wrap('as if these phrases were entered with double quotes. (Optionally enter comma delimited phrases):', 'span class=SettingText'),
            'LabelCode' =>      wrap('<b>Priority&nbspPhrases </b>', 'div class=SettingHead').wrap(' ', 'span class=Settingsqueeze'),
            'Default' => 'something has gone wrong,advanced editor',
            'Options' => array('MultiLine' => true, 'class' => 'TextBox')),
        //
            'Plugins.DiscussionTopic.Noisewords' => array(
            'Control' => 'TextBox',
            'Description' =>    wrap('Optionally enter comma-separated words to be ignored in the discussion title analysis:', 'span class=SettingText'),
            'LabelCode' =>      $NoiseNotes.wrap('<b>Ignorable&nbspWords </b>(Noise words)', 'div class=SettingHead').wrap(' ', 'span class=Settingsqueeze'),
            'Default' => 'and,or,if',
            'Options' => array('MultiLine' => true, 'class' => 'TextBox')),
        //
            'Plugins.DiscussionTopic.Acronyms' => array(
            'Control' => 'TextBox',
            'Description' =>    wrap('Enter phrases to be substituted for others. Format: acronym=phrase,acronym=phrase', 'span class=SettingText'),
            'LabelCode' =>      $AcronymsNotes.wrap('<b>Substitute Words</b> (Acronyms)', 'div class=SettingHead').wrap(' ', 'div class=Settingsqueeze'),
            'Default' => 'btn=button,config=configuration,db=database',
            'Options' => array('MultiLine' => true, 'class' => 'TextBox')),
        //
            'Plugins.DiscussionTopic.Sigwords' => array(
            'Control' => 'TextBox',
            'Description' =>    wrap('Enter the number of keywords to use for matching similar discussion titles.', 'span class=SettingText').
                                wrap('A high number reduces matching chances (since all keywords must exist in the matching discussion titles)', 'span class=SettingText').
                                wrap('A small number increases the chances for false positives.  Select a number between 2 to 4:', 'span class=SettingText'),
            'LabelCode' =>      wrap('<b>Number of Keywords</b> (Only if Heuristic Analysis is active)', 'span class=SettingHead').wrap(' ', 'span class=Settingsqueeze'),
            'Default' => '2',
            'Options' => array('Class' => 'Textbox')),
        /***   This is for future use ***
            'Plugins.DiscussionTopic.Analyzer' => array(
            'Control' => 'Radiolist',
            'Items' => Array('TextRazor (see www.textrazor.com for free or paid license)','PosTagger (internal and free)'),
            'Description' =>    wrap('Select one of these options (see the readme file for differences):','span class=SettingText'),
            'LabelCode' =>      wrap('<b>Grammatical Analizer</b>  (Only if Heuristic Analysis is active)','span class=SettingHead').wrap(' ','span class=Settingsqueeze'),
            'Default' => 'TextRazor'),
        //
        //  'Plugins.DiscussionTopic.Extractor' => array(
        //  'Control' => 'TextBox',
        //  'Description' =>    wrap('Extractor Phrase (don\'t change unless you modify the plugin code):','span class=SettingText'),
        //  'LabelCode' =>      wrap('<b> TextRazor Options:</b>','span class=SettingHead').wrap(' ','span class=Settingsqueeze'),
        //  'Default' => 'words'),
        //
            'Plugins.DiscussionTopic.TextRazorKey' => array(
            'Control' => 'TextBox',
            'Description' =>    wrap('If you selected "Textrazor" as your Grammatical Analizer, specify the TextRazor API key (See www.textrazor.com for free or paid API key):','span class=SettingText'),
            'LabelCode' =>      wrap(' ','span class=Settingsqueeze'),
            'Default' => '?'),
        ***/
            'Plugins.DiscussionTopic.Testtitle' => array(
            'Control' => 'TextBox',
            'Description' =>    wrap('<b>After</b> you save your settings you can see the Topic determined by the saved options. <br>Optionally enter a test discussion title below:', 'div class=SettingText'),
            'LabelCode' =>      wrap('Discussion Testing:', 'span class=SettingHead id="test"'),
            'Options' => array('MultiLine' => false, 'class' => 'TextWideInputBox'),
            'Default' => ' '),
        //
            'Plugins.DiscussionTopic.Maxrowupdates' => array(
            'Control' => 'TextBox',
            'LabelCode' =>  $SimulatedTitle.$Initializetext.wrap('<b>Table Update-Adding Topics to previously saved discussions</b>', 'span class=SettingHead'),
            'Description' =>        wrap('Enter the number of rows to update in a single update batch (See the readme file):', 'div class=Settinghead'),
            'Default' => 1000,
            'Options' => array('Class' => 'Textbox')),
        //
        //
        //
        );
        //if ($Debug) $this->DebugData($XPluginConfig,'---XPluginConfig---',$Debug);
        return $PluginConfig;
    }
/**
 * Check Configuration Settings.
 *
 * @param object $Sender standard
 * @param string $Type   Type of errors
 * @param bool   $Debug  debug request
 *
*  @return string - error message
*/
    private function checksettings($Sender, $Type = 'All', $Debug = false) {
        //$this->DebugData('', '',$Debug, true); //Trace Function calling
        $Warn = '';
        $Error = '';
        //Get the menu filled variables
        $Data = $Sender->Form->formValues();
        $NoiseWords = getvalue('Plugins.DiscussionTopic.Noisewords', $Data);
        $Acronyms = getvalue('Plugins.DiscussionTopic.Acronyms', $Data);
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
            $Error = substr($Error, 4);
            //$Error = Wrap(substr($Error,4),'span class=SettingError');
        }
        if ($Warn) {
            $Warn = substr($Warn, 4);
            $Warn = Wrap(substr($Warn, 4), 'span class=SettingWarning');
        }
        if ($Debug) {
            echo wrap('...'.__LINE__.' Error:'.$Error.' Warn:'.$Warn, 'p class=SettingWarning');
        }
        $Result = $Error.$Warn;
        return $Result;
    }
/**
* Field valdation.
*
* @param object $Sender    standard
* @param string $Field     Field to check
* @param array  $Checks    type of validations to perform
* @param string $Title     Error message title
* @param string $Fieldname External field name for message construction
* @param string $Style     Error message HTML style
* @param bool   $Debug     debug request
*
*  @return string - error message
*/
    private function checkfield(
        $Sender,
        $Field = false,
        $Checks = array('Required'),
        $Title = 'Field',
        $Fieldname = '',
        $Style = 'span class=SettingError',
        $Debug = false
    ) {
        $Errormsg='';
        foreach ($Checks as $Test => $Value) {
            //echo '<br>'.__line__.$Errormsg;
            if ($Errormsg == '') {
                //echo '<br>'.__LINE__.'Test:'.$Test.' Value:'.$Value.' on:'.$Field;
                if ($Test == 'Required') {
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
                } elseif (($Test == 'Integer' | $Test == 'Min' | $Test == 'Max') && !ctype_digit($Field)) {
                    $Errormsg='must be an integer';
                } elseif (($Test == 'Numeric' | $Test == 'Min' | $Test == 'Max') && !is_numeric($Field)) {
                    $Errormsg='must be numeric';
                } elseif ($Test == 'Title' && preg_match("/[^A-Za-z,.\s]/", $Field)) {
                    $Errormsg='must be valid words';
                } elseif ($Test == 'Alpha' && preg_match("/[0-9]+/", $Field)) {
                    $Errormsg='must be alphabetic';
                } elseif ($Test == 'Min') {
                    if ($Field < $Value) {
                        $Errormsg='must not be less than '.$Value;
                    }
                } elseif ($Test == 'Max') {
                    if ($Field > $Value) {
                        $Errormsg='must not be greater than '.$Value;
                    }
                }
            }
        }
        //echo '<br>'.__line__.$Errormsg;
        if ($Errormsg != '') {
            $Errormsg = wrap(t($Title).' '.t($Errormsg), $Style);
            if ($Fieldname != '') {
                $AddError = $Sender->Form->addError($Errormsg, $Fieldname);
            }
        }
        //echo '<br>'.__line__.$Errormsg;
        return $Errormsg;
    }
/**
* Get Topic to show the user (if settings allow that).
*
* @param string  $Topic    string to optionally display
* @param string  $Location Where to show the string (must match plugin configuration saved settings)
* @param boolean $Debug    [description]
*
*  @return string - either the topic or an empty string
*/
    private function gettopictoshow($Topic, $Location = 'inlist', $Debug = false) {
        //$this->DebugData('', '',$Debug, true); //Trace Function calling
        if (trim($Topic) == '') {
            return '';
        }
        if (!Gdn::session()->checkPermission('Plugins.DiscussionTopic.View')) {
            return;
        }
        if (substr($Topic, 0, 1) == '"') {
            if (!c('Plugins.DiscussionTopic.'.$Location, false)) {
                return '';
            }
        } else {
            if (!c('Plugins.DiscussionTopic.ShowHeuristic', false)) {
                return '';
            }
        }
        return $Topic;
    }

/**
* Display the keywords within the discussion body.
*
* @param object $Sender standard
*
* @return na
*/
    public function discussioncontroller_aftercommentformat_handler($Sender) {
        $Debug = false;
        $this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');
        //
        $Object = $Sender->EventArguments['Object'];
        //$FormatBody = $Object->FormatBody;
        //$CommentID = getValueR('CommentID',$Object);
        $DiscussionID = getValueR('DiscussionID', $Object);
        $Topic = getValueR('Topic', $Object);
        //$this->DebugData($DiscussionID,'---DiscussionID---',$Debug);
        //$this->DebugData($Topic,'---Topic---',$Debug);
        $ShowTopic = $this->GetTopicToShow($Topic, 'Showindiscussion', $Debug);
        $TopicAnswer = getValueR('TopicAnswer', $Object);
        echo $this->DisplayTopicAnswer($ShowTopic, $TopicAnswer, $DiscussionID, 'Discussion Topic', '', '', 'TopicInPost ', 'div');
    }
/**
 * Create the html to display the topic
 *
 * @param string $Topic            topic text
 * @param bool   $TopicAnswer      Indicate the topic is a "top topic"
 * @param int    $DiscussionID     DiscussionID
 * @param string $Prefix           Prefix text
 * @param string $DefaultEmphasize Non top-topic emphasis character
 * @param string $Emphasize        top-topic emphasis character
 * @param string $Style            html style
 *
 * @return  string - html
 */
    private function displayTopicAnswer(
        $Topic,
        $TopicAnswer,
        $DiscussionID,
        $Prefix = 'Discussion Topic',
        $DefaultEmphasize = '○',
        $Emphasize = '◉',
        $Style = 'TopicInPost'
    ) {

        if ($Topic== '') {
            return wrap(wrap(' ', 'span class=Emphasize id=Emphasize'.$DiscussionID).' ', 'span '.'id=Topic'.$DiscussionID);
        }
        $Anchor = anchor(
            $Topic,
            '/plugin/DiscussionTopic/DiscussionTopicSearch?s='.$Topic,
            array('Title' => t('click to view discussions with matching Topics'))
        );
        $AnswerMsg = $Topic;
        if ($Prefix != '') {
            $AnswerMsg = t($Prefix).':'.$Anchor;//$Topic;
        }
        if ($TopicAnswer) {
            $Title = 'Title="'.t('This discussion is marked as top Topic').'"';
        } else {
            $Emphasize = $DefaultEmphasize;
            $Title = '';
        }
        //
        return wrap(
            wrap(
                $Emphasize,
                'span class=Emphasize id=Emphasize'.$DiscussionID
            )
            .$AnswerMsg,
            'span class='.$Style.' '.'id=Topic'.$DiscussionID.' '.$Title
        );
    }
/**
 * Display the topic in the discussion
*
* @param object $Sender standard
*
* @return na
*/
    public function postcontroller_afterDiscussionformoptions_handler($Sender) {
        $Debug = false;
        //
        $Discussion = getvalue('Discussion', $Sender->Data);
        $TopicAnswer = getvalue('TopicAnswer', $Discussion);
        $Topic = getvalue('Topic', $Discussion);
        $ShowTopic = $this->GetTopicToShow($Topic, 'Showindiscussion', $Debug);
        if ($ShowTopic == '') {
            return;
        }
        echo $this->DisplayTopicAnswer(
            $ShowTopic,
            $TopicAnswer,
            $Discussion->DiscussionID,
            'Saved discussion Topic',
            '',
            ' ',
            'TopicInPost '
        );
    }
/**
 * Display the side panel
*
* @param object $Sender standard
*
* @return na
*/
    public function discussioncontroller_beforediscussionrender_handler($Sender) {
        $Debug = false;
        //if (!c('Plugins.DiscussionTopic.Showrelated',false)) return;
        $Sender->addCssFile('discussiontopic.css', 'plugins/DiscussionTopic');
        $Limit = c('Plugins.DiscussionTopic.Panelsize', 8);
        $ModuleToAdd = new DiscussionTopicModule($Sender);
        $Sender->AddModule($ModuleToAdd, 'Panel', $Sender);
        $ModuleToAdd->GetAlso($Sender->data('Discussion.DiscussionID'), $Limit, $Debug);
    }
/**
* Display the topic in the discussion list (Category Controller hook)
*
* @param object $Sender standard
* @param object $Args   Discussion Object
*
* @return na
*/
    public function categoriescontroller_aftercountmeta_handler($Sender, $Args) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $this->ListInline($Args['Discussion'], $Debug);
    }
/**
* Display the topic in the discussion list (Discussion Controller hook)
*
* @param object $Sender standard
* @param object $Args   Discussion Object
*
* @return na
*/
    public function discussionscontroller_aftercountmeta_handler($Sender, $Args) {
        $Debug = false;
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $this->ListInline($Args['Discussion'], $Debug);
    }
/**
* Display the topic in the discussion list
*
* @param object $Discussion Discussion Object
* @param bool   $Debug      debug request
*
* @return na
*/
    private function listinline($Discussion, $Debug = false) {
        //$this->DebugData('','',$Debug,true);  //Trace Function calling
        $ShowTopic = $this->GetTopicToShow($Discussion->Topic, 'Showinlist', $Debug);
        echo $this->DisplayTopicAnswer($ShowTopic, $Discussion->TopicAnswer, $Discussion->DiscussionID, 'Discussion Topic', '', '', 'TopicInList ');
    }
/**
* Add the Topic display function into the discussion list gear
*
* @param object $Sender standard
*
* @return na
*/
    public function discussionscontroller_discussionoptions_handler($Sender) {
        $this->AddToGear($Sender);
    }
/**
* Add the Topic display function into the discussion list gear
*
* @param object $Sender standard
*
* @return na
*/
    public function categoriescontroller_discussionoptions_handler($Sender) {
        $this->AddToGear($Sender);
    }
/**
* Add the Topic display function into the gear of the specific discussion
*
* @param object $Sender standard
* @param object $Args   standard
*
* @return na
*/
    public function base_discussionoptions_handler($Sender, $Args) {
        if (!Gdn::session()->checkPermission('Plugins.DiscussionTopic.Manage')) {
            return;
        }
        if (!c('Plugins.DiscussionTopic.Showgear', false)) {
            return '';
        }
        //
        $Discussion = $Args['Discussion'];
        $DiscussionID = $Discussion->DiscussionID;
        $Text='Set Discussion Topic';
        $SimpleKey =(367+Gdn::Session()->UserID);
        $Encode = $DiscussionID ^ $SimpleKey;
        $Url = '/dashboard/plugin/DiscussionTopic/DiscussionTopicSetTopic/?D='.$DiscussionID.'&S='.$Encode;
        $Css = 'Popup SetTopiccss';
        //if ($Debug) $this->DebugData($Url,'---Url---',$Debug);
        //
        $Args['DiscussionOptions']['DiscussionTopic'] = array('Label' => $Text,'Url' => $Url,'Class' => $Css);
    }
    /////////////////////////////////////////////////////////
    // Function to add the function into the gear
/**
* Add the Topic display function into the gear (in discussion lists)
*
* @param object $Sender standard
*
* @return na
*/
    private function addtogear($Sender) {
        $Debug = false;
        if (!Gdn::session()->checkPermission('Plugins.DiscussionTopic.Manage')) {
            return;
        }
        if (!c('Plugins.DiscussionTopic.Showgear', false)) {
            return '';
        }
        $Discussion = $Sender->EventArguments['Discussion'];
        // If limited to specific category numbers and discussion is not listed then exit
        $CategoryNums = c('Plugins.DiscussionTopic.CategoryNums');
        if ($Debug) {
            $this->DebugData($CategoryNums, '---Catnums---', $Debug);
        }
        if ($CategoryNums != "") {  //Limited category list?
            if (!in_array($Discussion->CategoryID, $CategoryNums)) {//Not in the list?
                if ($Debug) { //**Gdn::controller()->informMessage($this->DebugData($CategoryID,'---CategoryID---','',0,' ',true));
                    return;
                }
            }
        }
        //  Construct the link and add to the gear
        $DiscussionID = $Discussion->DiscussionID;
        $Text='Set Discussion Topic';
        $SimpleKey =(367+Gdn::Session()->UserID);
        $Encode = $DiscussionID ^ $SimpleKey;
        $Url = '/dashboard/plugin/DiscussionTopic/DiscussionTopicSetTopic/?D='.$DiscussionID.'&S='.$Encode;
        $Css = 'Popup SetTopiccss';
        $Sender->Options .= '<li>'.anchor(t($Text), $Url, $Css).'</li>';
    }
/**
* Terminate with a severe message
*
* @param [type] $Message error message to display
*
* @return na
*/
    private function diemessage($Message) {
        echo "<P>DiscussionTopic Plugin Message:<H1><B>".$Message."<N></H1></P>";
        throw new Gdn_UserException($Message);
    }
/**
* Initiate filter discussion list by topic
*
* @param string $Search      Filter string
* @param bool   $SearchTopic Indicator that filter string is NOT already conveted to a topic string
* @param bool   $Debug       debug request
*
* @return na
*/
    private function filtertopic($Search, $SearchTopic = false, $Debug = false) {
        $this->DebugData('','',$Debug,true);  //Trace Function calling
        if ($Debug) $this->DebugData($Search,'---Search---',$Debug);
        if ($Search == '') {
            Gdn::session()->stash('IBPTopicMsg', t('Error: Search argument not specified'));
            if ($Debug) $this->DebugData($Search,'---Search---',$Debug);
            Redirect('..'.url('/discussions'));
        }
        if ($SearchTopic) {
            $Search = $this->gettopic($Search, '', $Debug);
            if ($Debug) $this->DebugData($Search,'---Search---',$Debug);
            if ($Search == '') {
                Gdn::session()->stash('IBPTopicMsg', t('Error: Search argument arguments are defined as noise words'));
            }
        }
        $this->SetStash($Search, $Debug);
        //Gdn::session()->stash('IBPTopicSearch', $Search);
        $Title = t('Topic').':'.str_replace(array('\'', '"'), '', $Search);
        if ($Debug) $this->DebugData($Search,'---Search---',$Debug);
        Redirect('..'.url('/discussions'));
    }
/**
* Stash a value (wrapper that resolves stash function bug when used in DiscussionModel_BeforeGet (try /discussions/tagged/tagname)
*
* @param string $Search Filter string
* @param bool   $Debug  debug request
*
* @return na
*/
    private function setstash($Search, $Debug) {
        //Gdn::session()->stash('IBPTopicSearch', $Search);
        //Use the cookie method
        setcookie("IBPTopicSearch", $Search, time() + (30), "/");
    }
/**
* Retieve a stashed value.
*
* @return string - stached value
*/
    private function getstash() {
        //return Gdn::session()->stash('IBPTopicSearch');
        //Use the cookie method
        if (!isset($_COOKIE['IBPTopicSearch'])) {
            return '';
        } else {
            $Search = $_COOKIE['IBPTopicSearch'];
            $this->SetStash('');
            return $Search;
        }
    }
    ////////////////////////////////////////////////////////
    // This hook does two things:  filtering (facilitates the search function) and
    // Overriding discussion list sort order (for debugging/analysis only).
/**
* Filter/Sort the discussion list by the stashed topic
*
* @param object $Sender standard
*
* @return na
*/
    public function discussionmodel_beforeget_handler($Sender) {
        //
        $Search = $this->GetStash();
        if ($Search) {
            //A different filteringmechanism will be used in Vanila 2.3
            $Sender->SQL->Where('d.Topic', $Search);
            Gdn::Controller()->Title(t('Searching Topic').':'.strip_tags(str_replace(array('\'', '"'), '', $Search)));
            return;
        }
        //
        $IsAdmin = Gdn::Session()->CheckPermission('Garden.Users.Edit');
        if (!$IsAdmin) {
            return;
        }
        //
        //This sorting method will bereplaced in Vanila 2.3
        if (!c('Plugins.DiscussionTopic.SortByTopic', false)) {
            return;
        }
        Gdn::Controller()->Title(wrap(Anchor('Click to remove sorting by Topic', 'plugin/DiscussionTopic/DiscussionTopicSortbyTopic', array('class'=>"hijack")).
        '  - list sorted by discussion Topic (for Topic analysis purposes)', 'span class=Titlesortnotice'));
        $GetPrivateObject = function &($Object, $Item) {
            $Result = &Closure::bind(function &() use ($Item) {
                return $this->$Item;
            }, $Object, $Object)->__invoke();
            return $Result;
        };
        $OrderBy = &$GetPrivateObject($Sender->SQL, '_OrderBys');
        //echo __LINE__.var_dump($OrderBy);
        $OrderBy[0] = 'd.Topic ASC';//Force our own sorting order
    }
/**
* Display data for debugging
*
* @param [type]  $Data    Variable to display
* @param [type]  $Message Message assiciated with the displayed variable
* @param boolean $Debug   Debuggin flag - if unset do nothing.
* @param boolean $Inform  Indicate to use Vanilla's InforMessage to display the debugging info
*
* @return na
*/
    private function debugdata($Data, $Message, $Debug = true, $Inform = false) {
        if ($Debug == false) {
            return;
        }
        if ($Message == '') {
            $Message = '>'.debug_backtrace()[1]['class'].':'.debug_backtrace()[1]['function'].':'.debug_backtrace()[0]['line'].' called by '.debug_backtrace()[2]['function'];
        } else {
            $Message = '>'.debug_backtrace()[1]['class'].':'.debug_backtrace()[1]['function'].':'.debug_backtrace()[0]['line'].' '.$Message;
        }
        if ($Inform == true) {
            Gdn::controller()->informMessage($Message);
        } else {
            echo '<pre style="text-align: left; padding: 0 4px;">'.$Message;
            var_dump($Data);
            echo '</pre>';
        }
    }
    /////////////////////////////////////////////////////////
}

//Include the PorterStemmer Stemmer
require_once('PorterStemmer.php');
//Include the PosTagger parser
require_once('PosTagger.php');
//Include the TextRazor parser
//require_once('TextRazor.php');
