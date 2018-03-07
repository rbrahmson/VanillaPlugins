<?php if (!defined('APPLICATION')) exit();
/**
 * Feed Discussions Plus Plugin
 *
 * Automatically creates new discussions based on content imported from supplied RSS/Atom feeds.
 *
 *  Shout of gratitude to Tim Gunter from the Vanilla team who inspired me with the original Feedicussion plugin!
 *
 *  Main features/changes from the Feeddiscussions plugin:
 *  Support for both ATOM and RSS encoded feeds
 *  Support for both ATOM and RSS encoded feeds
 *  Support for compressed feeds
 *  Individual settings for each feed
 *  Filtering of content imported into the Vanilla forum based on specified keywords
 *  Optional setting of minimal number of words per imported feed item (to avoid teaser feed items)
 *  Optional limit on the number of items imported per feed
 *  Option to show the feed's/site's logo in the imported discussion and discussion list
 *  Option to remove linked images from the imported feeds
 *  Option to set server window hours when feeds are imported
 *  Option to disable individual feeds (while keeping their definitions rather than deleting them)
 *  Admin button to initiate a manual feed import
 *  Detailed report of administrator's initiated feed imports
 *  Support for cron initiated feed imports
 *  List of defined feeds are sorted alphabetically with the active feeds on top
 *  Attempt to auto-discover feed url of an entered url (relies on website using feed referral tags).
 *  Support for Twitter feeds
 *  Support for Youtube channel feeds (with embedding of video clip with link to the video)
 *
 *
 *   Note:  This initial version (which was converted from our intranet) does not fully adhere to Vanilla
 *          coding standard and still contain debugging code (these will be corrected in due course).
 *  Version 2.2.1 - Added Global Options
 *                - Ability to sort feed definition list
 *                - Setting of max number of imported feeds per run (Performance control)
 *  Version 2.5.1 - Support Vanilla 2.5
 *  Version 2.5.2 - Better handling of forum source image
 *  Version 2.5.3 - Support for rich RSS/Atom content, few back end interface enhancements
 *  Version 2.5.4 - Experimental support for Instagram content, additional interface enhancements
 */
//
$PluginInfo['FeedDiscussionsPlus'] = array(
    'Name' => 'Feed Discussions Plus',
    'Description' => "Automatically create Vanilla discussions based on RSS/Atom/Twitter feeds imported content.",
    'Version' => '2.5.4',
    'RequiredApplications' => array('Vanilla' => '>=2.3'),
    'MobileFriendly' => true,
    'HasLocale' => true,
    'RegisterPermissions' => false,
    'SettingsUrl' => '/plugin/feeddiscussionsplus/listfeeds',
    'Author' => "RB, inspired by Tim Gunter's original FeedDiscussion plugin",
    'GitHub' => 'rbrahmson/FeedDiscussionsPlus',
    'License' => 'GPLv2'
);
/**
* Plugin to import feed items as Vanilla Discussions.
*/
class FeedDiscussionsPlusPlugin extends Gdn_Plugin {
    protected $feedlist = null;
    protected $rawfeedlist = null;
/**
* Return plugin message.
*
* @param flag $newvalue  request to set new message value after retrieving current value
* @param flag $backtrace caller trace
*
*  @return boolean n/a
*/
    public static function getmsg($newvalue = '-', $backtrace = "-undefined-") {
        $Msg = Gdn::session()->stash("IBPDfeedMsg");
        //echo '<br> '.__LINE__.' backtrace=' . $backtrace . ' Msg='.$Msg . '<br>';
        if ($newvalue == '-') {
            return $Msg;
            //echo __LINE__.' setting new value :'.$newvalue;
        } elseif ($newvalue == '+') {
            Gdn::session()->stash("IBPDfeedMsg", $Msg.$newvalue);
            //echo __LINE__.' setting new value :'$Msg.$newvalue;
        } else {
            Gdn::session()->stash("IBPDfeedMsg", $newvalue);
            //echo __LINE__.' setting new value :'.$newvalue;
        }
        return $Msg;
    }
/**
* Act as a mini dispatcher for API requests to the plugin.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function plugincontroller_feeddiscussionsplus_create($Sender, $Args) {
        //$this->DebugData($Args, '---Args---', 1);
        $Sender->Title('FeedDiscussionsPlus Plugin');
        $this->addpluginmenu($Sender, 'plugin/feeddiscussionsplus');
        $this->Dispatch($Sender, $Sender->RequestArgs);
    }
/**
* Mini router
*
* @param object $Sender Standard Vanilla
*
* @return bool|int
*/
    public function controller_index($Sender, $Args) {
        //$this->DebugData('', '', true, true);
        //$this->DebugData($Args, '---Args---', 1);
        $Sender->Permission('Garden.Settings.Manage');
        if (!Gdn::Session()->IsValid()) {   //Don't bother for users who are not logged on
            return;
        }
        $this->Checkprereqs();   //Check system requirements for the plugin
        //$this->controller_listfeeds($Sender, $Args);
        //return;
        $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
    }
    /**
* Add plugin menu for the settingsController.
*
* @param Standard $Sender Standard
* @param string   $Menu   Menu link
*
*  @return boolean n/a
*/
    private function addpluginmenu($Sender, $Menu) {
        // New method (only in 2.5+)
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Sender->setHighlightRoute($Menu);
        } else {
            $Sender->addSideMenu($Menu);
        }
    }
/**
* Set up Admin Panel link
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function base_getappsettingsmenuitems_handler($Sender) {
          $Menu = $Sender->EventArguments['SideMenu'];
          $Menu->AddItem('Forum', T('Forum'));
          $Menu->AddLink('Forum', T('Feed Discussions Plus'), 'plugin/feeddiscussionsplus', 'Garden.Settings.Manage');
    }
/**
* Set up Admin Panel link (Vanilla 2.5)
*/
    public function dashboardNavModule_init_handler($Sender) {
        $Sender->addLinkToSection('Forum', t('Feed Discussions Plus'), 'plugin/feeddiscussionsplus', 'Garden.Settings.Manage', '', [], ['icon' => 'icon']);
    }
/**
* Set the appropriate CSS file
*
* @param Standard $Sender Standard
*
* @return string
*/
    private function setcss($Sender) {
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Sender->addCssFile('feeddiscussionsplus.css', 'plugins/FeedDiscussionsPlus');
        } else {
            $Sender->addCssFile('feeddiscussionspluspopup.css', 'plugins/FeedDiscussionsPlus', 
                                ['Sort' => 2000]);
        }
    }
/**
* Set the CSS
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function assetmodel_stylecss_handler($Sender) {
        $this->setcss($Sender);
    }
/**
* Add CSS assets to front end.
*
* @param AssetModel $Sender
*/
    public function assetModel_afterGetCssFiles_handler($Sender) {
        $this->setcss($Sender);
    }
/**
* Set the CSS
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function base_render_before($Sender) {
        $this->setcss($Sender);
    }
/**
* Autoimport (if permissible)
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function discussioncontroller_beforediscussionrender_handler($Sender) {
        //$this->DebugData(__LINE__, '', 1);
        if (IsMobile()) {                  //Don't do that on mobile users (don't further increase response time)
            //$this->DebugData(__LINE__, '', 1);
            return;
        }
        if (!Gdn::Session()->IsValid()) {   //Don't bother for users who are not logged on
            return;
        }
        if (!c('Plugins.FeedDiscussionsPlus.Userinitiated', true)) {    //User initiated not allowed?
            //$this->DebugData(__LINE__, '', 1);
            return;
        }
        $this->checkfeeds($Sender, false); //Check the feed in autoimport mode
    }
/**
* Endpoint to trigger feed check & update.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*
*/
    public function controller_checkfeeds($Sender, $Args) {
        $i = count($Args);
        $Cron - false;
        $Backend = false;
        $Croncode = c('Plugins.FeedDiscussionsPlus.Croncode', "secretcode");
        $Backendstate = Gdn::session()->stash("FDPbackend");
        Gdn::session()->stash("FDPbackend", $Backendstate); //Preserve whatever state was set by caller
        //
        if ($i) {
            if ($i == 1 && $Args[0] == 'backend') {
                if ($Backendstate == 'Backend') {
                    $Backend = true;
                } elseif ($Backendstate == 'Inactive') {
                    echo '<h1>There are no active feeds</H1>';
                    return;
                } elseif ($Backendstate == 'Mobile') {
                    echo '<h1>Feeds import is disabled on mobile mode</H1>';
                    return;
                }
            } elseif ($i == 1) {
            } elseif (($Args[0] == 'cron') && ($Args[1] == $Croncode)) {
                echo '<br>Cron refresh ('.$Croncode.')<br>';
                $Cron = true;
            } elseif ($Args[0] == 'plugin' && $Args[1] == 'feeddiscussionsplus') {
                echo "processing completed - close this window/tab";
                return;
            }
        }
        if (IsMobile() && !$Cron) {
            echo $this->setmsg('Manual Import Disabled on Mobile');
            return;
        }
        if (!$Backend && !$Cron) {
            if (!Gdn::Session()->IsValid()) {
            //if (!$this->checklogin($Sender, $Args)) {
                $Msg = $this->setmsg('You must be logged on to run this function.');
                echo $Msg;
                return;
            }
            $Sender->Permission('Garden.Settings.Manage');
            $Msg = $this->setmsg('Wrong cron link');
            $this->DebugData($Backendstate, '---Backendstate---', 1);
            $this->DebugData($Args, '---Args---', 1);
            echo '<h1>'.$Msg.' </h1>';
            return;
        }
        $Detailedreport    = c('Plugins.FeedDiscussionsPlus.Detailedreport', false);
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Popustate = '';
        } else {
            $Popustate = c('Plugins.FeedDiscussionsPlus.Popup', '');
        }
        $this->setcss($Sender);
        //$this->DebugData($Popustate, '---Popustate---', 1);
        if ($Popustate == "Popup") {
            $Exit = '<span style="float:right">  <input type="button" value="Close" class="Button" '.
                'onClick=\'window.location.href="plugin/feeddiscussionsplus/listfeeds?'.__LINE__.'" \';></span>';
            $Exit = '<span style="float:right">  <input type="button" value="Close" class="Button" '.
                'onClick="window.location.reload()" ></span>';
        } else {
            //$this->DebugData($Popustate, '---Popustate---', 1);
            echo $this->javawindowclose('', '', '', '', true);
            $Exit = $this->javawindowclose('', 'Return to list');
        }
        //
        echo '<script type="text/javascript">';
        echo '$("#Popup div.Body").css("max-height", ($(window).height() /100)*75);';
        echo '$("#Popup div.Body").css("max-width", "90%");';
        echo '$("#Popup div.Body").css("height", "max-content");';
        echo '$("#Popup div.Body").css("width", "max-content");';
        echo '</script>';
        //
        echo '<div id=FDPreport class="FDPbatch"><div id=FDP><h1>Feed Discussions Plus - Checking feeds</h1>'.$Exit;
        echo    '<H3 id=clock>Current server hour:<b>'.date('H').
                '</b> &nbsp (Server time:'.date('Y-m-d H:i:s', time()).') </H3>';
        /*echo    '<div id=FDPprogress class="Popin TinyProgress  "'.
                ' style="background: url(images/progress.gif) no-repeat center center;padding: 10px 40px 10px 0;">'.
                '<span style="font-weight:bold;">Feed Import Report</span></div>';
        */
        //echo '<div class="Popin" rel="'.url($Url.'?DeliveryType=VIEW', true).'">Ah???</div>';
        //echo '<span style="margin: 0 0 0 20px;"><a class="Button DeleteFeed" href="'.Url('plugin/feeddiscussionsplus/listfeeds?'.__LINE__).
          '" title="'.t('Close this report and return to the setup screen').'">Close</a></span>';
        $NumCheckedFeeds = $this->Checkfeeds($Sender, true);
        if ($NumCheckedFeeds == 0) {
            $Msg = $this->setmsg('Feed Import Process Completed.');
        } elseif ($NumCheckedFeeds < 7  && !$Detailedreport) {
            $Msg = $this->setmsg('Feed Import Process Completed.');
            $Exit = '';
        } else {
            $Msg = $this->setmsg('Feed Import Process Completed.');
        }
        $Sender->JsonTarget("#FDPprogress", '<div id=FDPprogress style="display:table;">'.strip_tags("Report Results").'</div>', 'ReplaceWith');
        $Sender->jsonTarget('', '', 'Refresh');
        $this->postmsg($Sender, $this->setmsg($Msg));
        //echo '<br>'.$NumCheckedFeeds .' '.$Msg;
        if ($Popustate != "Popup") {
            echo __LINE__.$Msg;
        }
        echo $Exit.'</div></div>';
        //$this->returntoparent($Sender, $Msg);
        return;
    }
/**
* Import feeds based on the predefined criteria
*
* @param object $Sender     Standard Vanilla
* @param bool   $AutoImport Indicates manual or trigerred import
*
* @return bool|int
*/
    private function checkfeeds($Sender, $AutoImport = true) {
        //$this->DebugData($AutoImport, '---AutoImport---', 1);
        Gdn::Controller()->SetData("AutoImport", $AutoImport);
        $Controller = $Sender->ControllerName;  //Current Controller
        $MasterView = $Sender->MasterView;
        $Reportitem = 0;
        $ImportedFeeds = 0;
        $NumCheckedFeeds = 0;
        $NumInactiveFeeds = 0;
        $NumManualFeeds = 0;
        $NumOutsideWindowFeeds = 0;
        $NumNotduedFeeds = 0;
        $Checkfeed = false;
        $Detailedreport    = c('Plugins.FeedDiscussionsPlus.Detailedreport', false);
        $Globalmaximport    = c('Plugins.FeedDiscussionsPlus.Globalmaximport', 0);
        if ($Detailedreport && $AutoImport) {
            $Reportindetail = true;
        } else {
            $Reportindetail = false;
        }
        //$this->DebugData($Detailedreport, '---Detailedreport---', 1);
        // Loop through the feeds
        $Feedsarray = $this->GetFeeds();
        $NumDefined = count($Feedsarray);
        foreach ($Feedsarray as $FeedURL => $FeedData) {
            $NumCheckedFeeds +=1;
            $Forceupdate = false;
            Gdn::Controller()->SetData("{$FeedURL}", $FeedData);
            $Active = val('Active', $FeedData, 0);
            $Feedtitle = val('Feedtitle', $FeedData, "Url: ".$FeedURL);
            $Encoding = val('Encoding', $FeedData);
            $Activehours = val('Activehours', $FeedData);
            $Refresh = val('Refresh', $FeedData);
            $Validtocheck = true;
            //$this->DebugData($Active, '---Active---', 1);
            //$this->DebugData($Encoding, '---Encoding---', 1);
            if (!$Active) {
                $Validtocheck = false;
                if ($AutoImport) {
                    $NumInactiveFeeds +=1;
                    if ($Reportindetail) {
                        echo '<br><b>'.$NumCheckedFeeds.'. Skipping ' . $Encoding . ' feed "'.$Feedtitle.'" </b>'. '(Inactive feed)';
                    } else {
                        $NumCheckedFeeds -=1;
                    }
                }
            } elseif (!$this->iswithinwindow($Activehours)) {
                $Validtocheck = false;
                if ($AutoImport) {
                    $NumOutsideWindowFeeds +=1;
                    if ($Reportindetail) {
                        echo '<br><b>'.$NumCheckedFeeds.'. Skipping ' . $Encoding . ' feed "'.
                            $Feedtitle.'"  </b>(outside active hours of '.$Activehours.') </b>';
                    }
                }
            } else {        //Feed is active and within the active window
                if (!$AutoImport & $Refresh == 'Manually') {    //Manual mode but not initiated by the admin?
                    if ($Reportindetail) {
                        echo __LINE__." Skipping manually scheduled feed ".$Feedtitle;
                    }
                    $Validtocheck = false;
                    $NumManualFeeds +=1;
                }
            }
            if ($Validtocheck) {
                //$this->DebugData($FeedData, '---FeedData---', 1);
                $Added = val('Added', $FeedData, 0);
                $LastImport = val('LastImport', $FeedData);
                $NextImport = val('NextImport', $FeedData);
                $OrFilter = val('OrFilter', $FeedData);
                $AndFilter = val('AndFilter', $FeedData);
                $Filterbody = val('Filterbody', $FeedData);
                $Minwords = val('Minwords', $FeedData);
                $Maxitems = val('Maxitems', $FeedData);
                $Getlogo = val('Getlogo', $FeedData);
                $Noimage = val('Noimage', $FeedData);
                $Historical = (bool)val('Historical', $FeedData, false);
                //
                //$this->DebugData($LastImport, '---LastImport---', 1);
                //$this->DebugData($NextImport, '---NextImport---', 1);
                if (!$LastImport | $LastImport == 'never') {                //First time feed processed?
                    $LastImport = date('c');
                    $Forceupdate = true;
                    //$this->DebugData($LastImport, '---LastImport---', 1);
                    $NextImport = date('Y-m-d H:i:s', 0); //Force update
                    //$this->DebugData($NextImport, '---NextImport---', 1);
                }
                //
                $Timedate = date('Y-m-d H:i:s', time());                //Current date/time
                if ($Historical) {
                    $Forceupdate = true;
                    //$this->DebugData($Historical, '---Historical---', 1);
                } elseif ($Timedate > $NextImport) {        //Current date/time > prescribed delay
                    $Forceupdate = true;       //Current date/time
                    //$this->DebugData($Timedate.' > '.$NextImport, '---\$Timedate > \$NextImport---', 1);
                } else {
                    //$this->DebugData($Timedate.' < '.$NextImport, '---\$Timedate > \$NextImport--- | not historical', 1);
                }
                //
                if ($Forceupdate) {
                    $Reportitem += 1;
                    //$this->DebugData($AutoImport, '---AutoImport---', 1);
                    if ($AutoImport) {
                        $Msg = '<br><b>'.$Reportitem.'. Checking ' . $Encoding . ' feed "'.$Feedtitle.'</b>';
                        $Sender->JsonTarget("#FDPprogress", '<div id=FDPprogress style="display:table;">'.strip_tags(__LINE__.$Msg).'</div>', 'ReplaceWith');
                        $Sender->JsonTarget("#clock", '<div id=Head style="display:table;">'.strip_tags(__LINE__.$Msg).'</div>', 'ReplaceWith');
                        $Sender->jsonTarget('', '', 'Refresh');
                        echo $Msg;
                    }
                    $DueDate = $this->getimportdate($Sender, "Current", $Refresh, $LastImport); //Import due date/time
                    $NextDueDate = $this->getimportdate($Sender, "Next", $Refresh, $LastImport);//Next Import due date/time
                    //$this->DebugData($DueDate, '---DueDate---', 1);
                    //$this->DebugData($NextDueDate, '---NextDueDate---', 1);
                    $FeedData["LastImport"] = $LastImport;
                    $FeedData["NextImport"] = $NextDueDate;
                    $this->PollFeed($Sender, $FeedData, $AutoImport, $Reportindetail);
                    $ImportedFeeds += 1;
                    //$this->DebugData($Globalmaximport, '---Globalmaximport---', 1);
                    //$this->DebugData($ImportedFeeds, '---ImportedFeeds---', 1);
                    if ($Globalmaximport >0 && $ImportedFeeds >= $Globalmaximport) {
                        if ($AutoImport) {
                            echo '<h2> Reached Maximum import Feeds:'.$Globalmaximport.'</h2>';
                            break;
                        }
                    }
                } else {
                    if ($AutoImport && $Reportindetail) {
                        echo '<br><b>'.$NumCheckedFeeds.'. Skipping ' . $Encoding .
                        ' feed "'.$Feedtitle.'"  </b> (Feed not due for processing until '.$NextImport.')';
                          $NumNotduedFeeds += 1;
                    }
                }
            }
        }
        if ($AutoImport) {
            //
            if ($Reportitem) {
                //
                echo "<h2><b>&nbsp&nbsp&nbsp" . $NumDefined . '&nbspDefined Feeds,'. $Reportitem. '&nbspFeeds processed. </b>';
                //echo "<h2><b>&nbsp&nbsp&nbsp" . $NumCheckedFeeds . '&nbspDefined Feeds,'. $Reportitem. '&nbspFeeds processed. </b>';
                $Skipped = $NumInactiveFeeds + $NumManualFeeds + $NumOutsideWindowFeeds + $NumNotduedFeeds;
                if ($Skipped) {
                    echo '&nbsp (' . $Skipped . ' skipped:';
                    if ($NumInactiveFeeds) {
                        echo ' ' .  $NumInactiveFeeds . ' Inactive.';
                    }
                    if ($NumManualFeeds) {
                        echo ' ' .  $NumManualFeeds . ' manually controlled.';
                    }
                    if ($NumOutsideWindowFeeds) {
                        echo ' ' .  $NumOutsideWindowFeeds . ' Outside of their active hours.';
                    }
                    if ($NumNotduedFeeds) {
                        echo ' ' .  $NumNotduedFeeds . ' not due for import yet.';
                    }
                    echo ')&nbsp';
                }
                echo '</h2>';
            } else {
                echo "<h2><b>&nbsp&nbsp&nbsp None of the ".$NumDefined." defined feeds were eligble for import</h2>";
            }
        }
        return $Reportitem;
    }
/**
* Redirect through login if not logged on.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return boolean       false - not logged in, true logged it (if redirection wasn't requested)
*/
    public function checklogin($Sender, $Args) {
        //$this->DebugData('', '', true, true);
        if (Gdn::Session()->IsValid()) {
            return true;
        }
        echo $this->setmsg(t('You are not logged on'));
        //$this->DebugData($Args, '---Args---', 1);
        $Forward = '?p=plugin/feeddiscussionsplus/listfeeds'  . '?'.__LINE__;
        if (isset($Args[0])) {
            if ($Args[0] == 'relogon2') {
                echo __LINE__.' '.t('Too many redirections (Login first, then retry).');
                die(0);
            } elseif ($Args[0] == 'relogon') {
                $Forward = '?p=plugin/feeddiscussionsplus/relogon2/' . implode("/",$Args) . '?'.__LINE__;
            } else {
                $Forward = '?p=plugin/feeddiscussionsplus/relogon/' . implode("/",$Args) . '?'.__LINE__;
            }
        } else {
            $Forward = '?p=plugin/feeddiscussionsplus/listfeeds/relogon' . '?'.__LINE__;
        }
        //$this->DebugData($Args, '---Args---', 1);
        //$this->DebugData($Forward, '---Forward---', 1);
        $Msg = $this->setmsg(t('You must be logged on to access the requested function'));
        echo $Msg;
        //$Sender->InformMessage($Msg);
        Redirect('index.php?p=entry/signin&Target=index.php' . $Forward);
    }
/**
* Update feed import settings.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_updatefeed($Sender, $Args) {
        //$this->DebugData('', '', true, true);
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $this->setcss($Sender);
        $Postback = $Sender->Form->AuthenticatedPostback();
        if (!$Postback) {            //Initial form setup
            $Msg = $this->getmsg('' , 'get:'.__FUNCTION__.__LINE__);
            //$this->DebugData($Msg, '---Msg---', 1);
            //echo '<span>'.__FUNCTION__.' L#'.__LINE__.' Not Postback</span>';
            $FeedKey = val(0, $Args, null);
            if (empty($FeedKey)) {
                  $Msg = $this->setmsg(' Missing required parameter.');
                  $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
            } else {
                $Feed = $this->GetFeed($FeedKey);
            }
            if (empty($Feed)) {
                  decho($Feed);
                  decho($Args);
                  $Msg = $this->setmsg(' The feed was deleted before it could be displayed.');
                  echo $Msg;
                  die(0);
                  $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
            }
            //$this->DebugData($Feed, '---Feed---', 1);
            //$this->DebugData($Feed["Refresh"], '---Feed["Refresh"]---', 1);
            //Initial form setting
            //
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', 'Update');
            $Sender->SetData('Feed', $Feed);
            $Sender->SetData('FeedKey', $FeedKey);
            $Sender->SetData('FeedURL', $Feed['URL']);
            $Sender->Form->setValue('FeedURL', $Feed['URL']);
            $Sender->Form->setValue('Active', $Feed['Active']);
            $Sender->Form->setValue('Refresh', $Feed['Refresh']);
            $Sender->SetData('Refresh', $Feed['Refresh']);
            $Sender->Form->setValue('Feedtitle', $Feed['Feedtitle']);
            $Sender->Form->setValue('Historical', $Feed['Historical']);
            $Sender->Form->setValue('Getlogo', $Feed['Getlogo']);
            $Sender->Form->setValue('Noimage', $Feed['Noimage']);
            $Sender->Form->setValue('Category', $Feed['Category']);
            $Sender->Form->setValue('OrFilter', $Feed['OrFilter']);
            $Sender->Form->setValue('AndFilter', $Feed['AndFilter']);
            $Sender->Form->setValue('Filterbody', $Feed['Filterbody']);
            $Sender->Form->setValue('Minwords', $Feed['Minwords']);
            $Sender->Form->setValue('Activehours', $Feed['Activehours']);
            $Sender->Form->setValue('Maxitems', $Feed['Maxitems']);
            //
            $this->renderview($Sender, 'feeddiscussionsplusedit', $Msg);
            return;
        }
        //Form Postback
        //echo '<span>'.__FUNCTION__.' L#'.__LINE__.' <b> Postback</b></span>';
        //
        $Sender->SetData('Categories', CategoryModel::Categories());
        $Sender->SetData('Mode', 'Update');
        $FormPostValues = $Sender->Form->FormValues();
        $Sender->SetData('FormPostValues', $FormPostValues);
        //
        $Feedarray = $this->getfeedfromform($FormPostValues);
        $Sender->SetData('FeedURL', $Feedarray['FeedURL']);
        //  Handle CUT & Paste requests
        if (isset($FormPostValues["Paste"])) {
            $Feed = $this->GetFeed($Feedarray['FeedURL'], false);
            $this->pasteparms($Sender);
            $Sender->SetData('Feed', $Feed);
            $Sender->SetData('Mode', 'Update');
            //$this->DebugData($Copystate, '---Copystate---', 1);
            $Msg = $this->getmsg('' , 'get:'.__FUNCTION__.__LINE__);
            $this->renderview($Sender, 'feeddiscussionsplusedit', $Msg);
            return;
        } elseif (isset($FormPostValues["Copy"])) {
            $Copyfeed = $this->copyparms($Sender, $FormPostValues);
            $Feed = $this->GetFeed($Feedarray['FeedURL'], false);
            $Sender->SetData('Feed', $Feed);
            $Sender->SetData('Mode', 'Update');
            $Msg = $this->getmsg('' , 'get:'.__FUNCTION__.__LINE__);
            //$this->DebugData($Msg, '---Msg---', 1);
            $this->renderview($Sender, 'feeddiscussionsplusedit', $Msg);
            return;
        }
        //  
        $Defaults = $this->feeddefaults();
        $Feedarray = array_merge($Defaults, $Feedarray);
        //$this->DebugData($Feedarray, '---Feedarray---', 1);
        $FeedRSS = $this->validatefeed($Sender, $Feedarray, 'Update');  //Validate form inputs in "Update" mode
        //$this->DebugData($FeedRSS["FeedURL"], '---FeedRSS["FeedURL"]---', 1);
        $Feed = $this->GetFeed($FeedRSS["FeedURL"], false);
        $Sender->SetData('Feed', $Feed);
        if ($FeedRSS['Error']) {
            $Msg = $this->setmsg($FeedRSS['Error']);
            if ($FeedRSS["SuggestedURL"]) {
                $FeedRSS["FeedURL"] = $FeedRSS["SuggestedURL"];
                $Sender->Form->setFormValue('FeedURL', $FeedRSS["SuggestedURL"]);
            }
            $Sender->Form->AddError($Msg);
            $this->renderview($Sender, 'feeddiscussionsplusedit', '');
            return;
        }
        //No errors, perform the update
        $Feedarray["RSSimage"] = $FeedRSS['RSSimage'];
        $Feedarray["Encoding"] = $FeedRSS['Encoding'];
        $Feedarray["FeedURL"] = $FeedRSS['FeedURL'];
        $Feedarray["Feedtitle"] = $FeedRSS['Feedtitle'];
        $Feedarray["InternalURL"] = $FeedRSS['InternalURL'];
        $Feedarray["Scheme"] = $FeedRSS['Scheme'];
        //Calculate and set next import due date/time
        $LastImport = val('LastImport', $Feed);
        $Refresh = val('Refresh', $Feedarray);
        if ($Refresh == 'Manually') {
            $Feedarray["NextImport"] = '';
        } else {
            $Feedarray["NextImport"] = $this->getimportdate($Sender, "Next", $Refresh, $LastImport);
        }
        //$this->DebugData($Feedarray, '---Feedarray---', 1);
        //$this->DebugData($Feedarray["FeedURL"], '---Feedarray["FeedURL"]---', 1);
        //$FeedKey = self::EncodeFeedKey($FeedURL);
        //$this->UpdateFeed($FeedKey, $Feedarray);
        $this->AddFeed($Feedarray["FeedURL"], $Feedarray);
        $Msg = $this->setmsg('"'.SliceString($FeedRSS['Feedtitle'], 40).
                                '" Feed import definition updated.');
        //$this->DebugData($Msg, '---Msg---', 1);
        //$this->postmsg($Sender, $Msg);
        if (!$Feedarray['Active']) {
            $Msg = $Msg . '&nbsp ⛔ Note: Feed is inactive. ';
        } elseif ($Refresh == 'Manually') {
            $Msg = $Msg . "&nbsp This feed won't be imported unless you import it manually. ";
        } else {
            $Timedate = date('Y-m-d H:i:s', time());
            if ($Timedate > $Feedarray["NextImport"]) {
                $Msg = $Msg . '&nbsp <ffgreen>&nbsp&nbsp</ffgreen> Next import is due now ';
            } else {
                $Msg = $Msg . '&nbsp <ffred>&nbsp&nbsp</ffred> Next import is due on '. substr($Feedarray["NextImport"], 0, 16);
            }
        }
        //$this->DebugData($Msg, '---Msg---', 1);
        if (c('Plugins.FeedDiscussionsPlus.Returntolist', false)) { //Prefer to return to the feeds list screen?
            //$this->DebugData($Msg, '---Msg---', 1);
            $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
        }
        $this->renderview($Sender, 'feeddiscussionsplusedit', $Msg);
        return;
        //
    }
/**
* Add feed import settings.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_addfeed($Sender, $Args) {
        //$this->DebugData('','',true,true);
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $Postback = $Sender->Form->AuthenticatedPostback();
        $Mode = 'Add';
        $Savedmode = $Sender->Data('Mode');
        if (!$Postback) {            //Initial form setup
            //echo '<span>L#'.__LINE__.'</span>';
            $this->SetStash($Mode, 'Mode');
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', $Mode);
            //$Msg = $this->GetStash('Msg');
            $Msg = $this->getmsg('', 'get:'.__FUNCTION__.__LINE__);
        } else {  //Form Postback
            //$this->DebugData($Mode, '---Mode---', 1);
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', 'Add');
            $FormPostValues = $Sender->Form->FormValues();
            $Sender->SetData('FormPostValues', $FormPostValues);
            $Feedarray = $this->getfeedfromform($FormPostValues);
            $Defaults = $this->feeddefaults();
            $Feedarray = array_merge($Defaults, $Feedarray);
            //  Handle CUT & Paste requests
            if (isset($FormPostValues["Paste"])) {
                $this->pasteparms($Sender);
                $Sender->SetData('Mode', 'Add');
                $Sender->SetData('FeedURL', $FormPostValues['FeedURL']);
                $Sender->SetData('Feed', $Feedarray);
                //
                $Msg = $this->getmsg('' , 'get:'.__FUNCTION__.__LINE__);
                $this->renderview($Sender, 'feeddiscussionsplusedit', $Msg);
                return;
            } elseif (isset($FormPostValues["Copy"])) {
                $Copyfeed = $this->copyparms($Sender, $FormPostValues);
                $Sender->SetData('Mode', 'Add');
                $Sender->SetData('FeedURL', $FormPostValues['FeedURL']);
                $Sender->SetData('Feed', $Feedarray);
                $this->renderview($Sender, 'feeddiscussionsplusedit', '');
                return;
            }
            //decho ($Feedarray);
            $FeedRSS = $this->validatefeed($Sender, $Feedarray, 'Add');  //Validate form inputs in "Add" mode
            if (isset($FeedRSS['Error']) && trim($FeedRSS['Error'])) {
                $Msg = trim(strip_tags($FeedRSS['Error']));
                if ($Msg == "?") {
                    $this->helpfeedurl($Sender);
                    $Msg = '';
                } else {
                    $Msg = $this->setmsg($FeedRSS['Error'], true, false);
                    $FeedRSS['Error']='';
                    if (isset($FeedRSS["SuggestedURL"])) {
                        $FeedRSS["FeedURL"] = $FeedRSS["SuggestedURL"];
                        $Sender->Form->setFormValue('FeedURL', $FeedRSS["FeedURL"]);
                        $Sender->SetData('FeedURL', $FeedRSS["FeedURL"]);
                    } elseif (isset($FeedRSS['FeedURL'])) {
                        $Sender->Form->setFormValue('FeedURL', $FeedRSS["FeedURL"]);
                        $Sender->SetData('FeedURL', $FeedRSS['FeedURL']);
                    }
                    $Sender->Form->AddError($Msg, 'FeedURL');
                    $this->renderview($Sender, 'feeddiscussionsplusedit', '');
                    return;
                }
                if (trim($Msg) != '') {
                    $Sender->Form->AddError($Msg);
                }
                $Sender->SetData('Mode', 'Add');
                $Sender->SetData('Feed', $FeedRSS);
                $this->renderview($Sender, 'feeddiscussionsplusedit', __line__.$Msg);
                return;
            }
            //No errors, add the feed
            $Feedarray["RSSimage"] = $FeedRSS['RSSimage'];
            $Feedarray["Encoding"] = $FeedRSS['Encoding'];
            $Feedarray["Feedtitle"] = $FeedRSS['Feedtitle'];
            $Feedarray["InternalURL"] = $FeedRSS['InternalURL'];
            $Feedarray["Scheme"] = $FeedRSS['Scheme'];
            //$this->DebugData($Feedarray["FeedURL"], '---Feedarray["FeedURL"]---', 1);
            //Calculate and set next import due date/time
            $Refresh = val('Refresh', $Feedarray);
            if ($Refresh == 'Manually') {
                $Feedarray["NextImport"] = '';
            } else {
                $Feedarray["NextImport"] = $this->getimportdate($Sender, "Next", $Refresh, 'never');
            }
            $Url = $this->rebuildurl($Feedarray["FeedURL"], '');
            //$this->DebugData($Url, '---Url---', 1);
            $this->AddFeed($Url, $Feedarray);
            $Sender->SetData('Mode', 'Update');
            $Sender->SetData('FeedURL', $Feedarray['FeedURL']);
            $Msg = $this->setmsg('"'.SliceString($FeedRSS['Feedtitle'], 40).
                                '" Feed import definition added.');
            if (!$Feedarray['Active']) {
                $Msg = $Msg . '&nbsp ⛔ Note: Feed is inactive. ';
            } elseif ($Refresh != 'Manually') {
                $Msg = $Msg . '&nbsp Next import is due on '. $Feedarray["NextImport"];
            } else {
                $Msg = $Msg . "&nbsp This feed won't be imported unless you import it manually. ";
            }
            //
            if (c('Plugins.FeedDiscussionsPlus.Returntolist', false)) { //Prefer to return to the feeds list screen?
                //decho ($Msg);
                $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
            }
            $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/updatefeed/'.self::EncodeFeedKey($Url), $Msg);
        }
        //echo '<span>L#'.__LINE__.'</span>';
        $this->postmsg($Sender, $Msg);
        $this->renderview($Sender, 'feeddiscussionsplusedit', $Msg);
    }
/**
* Restore a deleted feed
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_restorefeed($Sender, $Args) {
        //var_dump($Feedkey, $FeedURL, $Mode);
        //$this->DebugData($Args, '---Args---', 1);
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $FeedURL = implode('/', $Args);
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        $RestoreFeedURL = $Sender->Data('RestoreFeedURL');
        //$this->DebugData($RestoreFeedURL, '---RestoreFeedURL---', 1);
        $Feed = $this->getstash('RestoreFeed');
        if ($Feed) {
            //$this->DebugData($Feed, '---Feed---', 1);
            $this->AddFeed($this->rebuildurl($Feed['FeedURL'], ''), $Feed);
            $Msg = $this->setmsg(' Feed "'.$Feed["Feedtitle"].'" Restored');
        } else {
            $this->AddFeed($this->rebuildurl($Feed['FeedURL'], ''), $Feed);
            $Msg = __LINE__.' could not restore';
        }
        $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
    }
/**
* Plugin global options.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_global($Sender, $Args) {
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $Msg = '';
        if ($Sender->Form->AuthenticatedPostback()) {    //Postback
            $FormPostValues = $Sender->Form->FormValues();
            //$this->DebugData($FormPostValues, '---FormPostValues---', 1);
            //die(0);
            $Msg = '';
            if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
                $Popustate = '';
            } else {
                $Popustate = c('Plugins.FeedDiscussionsPlus.Popup', '');
            }
            if (isset($FormPostValues["Return"])) {      // cancel/return
                $Msg = $this->setmsg('global settings not changed since last save');
                $this->returntoparent($Sender, $Msg);
                return;
            } elseif (isset($FormPostValues["Save"])) {
                $Croncode = trim($FormPostValues["Croncode"]);
                $Feedusername = trim($FormPostValues["Feedusername"]);
                if ($Feedusername == '') {
                    if (!empty(Gdn::userModel()->getByUsername("Feed"))) {
                        $Feedusername = 'Feed';
                    } else {
                        $Feedusername = 'System';
                    }
                }
                $Returntolist = $FormPostValues["Returntolist"];
                $Userinitiated = $FormPostValues["Userinitiated"];
                $Detailedreport = $FormPostValues["Detailedreport"];
                $Globalmaximport = $FormPostValues["Globalmaximport"];
                //
                $User = Gdn::userModel()->getByUsername(trim($Feedusername));
                try {
                    if (empty($Feedusername)) {
                        throw new Exception($this->setmsg('"'.$Feedusername.'" not found. Use the "Add a User" button to add users'));
                    }
                    if (empty($User)) {
                        throw new Exception($this->setmsg('"'.$Feedusername.'" not found. Use the "Add a User" button to add users'));
                    }
                    if (!ctype_alnum($Croncode)) {
                        throw new Exception($this->setmsg('"'.$Croncode.'" not valid. Use simple alphanumeric string for cron code'));
                    }
                    if ($Globalmaximport === '0') {
                        $Globalmaximport == ' ';
                        $Sender->Form->setFormValue("Globalmaximport", '');
                    }
                    if ($Globalmaximport) {
                        if (!ctype_digit($Globalmaximport)) {
                            throw new Exception($this->setmsg('"'.$Globalmaximport.
                                                              '" not a valid Maximum import items integer. Leave blank or zero for no maximum. '));
                        }
                        if (!$Userinitiated) {
                            $this->postmsg($Sender, ' Maximum import items number ignored when user-initiated import is disabled.', false, true);
                        }
                    }
                } catch (Exception $e) {
                        $Msg = T($e->getMessage());
                }
                if ($Msg) {
                    $Sender->Form->AddError($Msg);
                    $this->renderview($Sender, 'feeddiscussionsplusconfig', $Msg);
                    return;
                } else {
                    SaveToConfig('Plugins.FeedDiscussionsPlus.Croncode', $Croncode);
                    SaveToConfig('Plugins.FeedDiscussionsPlus.Feedusername', $Feedusername);
                    SaveToConfig('Plugins.FeedDiscussionsPlus.Returntolist', $Returntolist);
                    SaveToConfig('Plugins.FeedDiscussionsPlus.Userinitiated', $Userinitiated);
                    SaveToConfig('Plugins.FeedDiscussionsPlus.Detailedreport', $Detailedreport);
                    SaveToConfig('Plugins.FeedDiscussionsPlus.Globalmaximport', $Globalmaximport);
                    $Msg = $this->setmsg('Options Saved');
                }
                //
                if ($Popustate == 'Popup') {
                    $this->setjsonmsg($Sender, $this->setmsg($Msg));
                    $Sender->Render('Blank', 'Utility', 'Dashboard');  //force redraw of underlying window
                } else {
                    $this->renderview($Sender, 'feeddiscussionsplusconfig', $Msg);
                }
                return;
            } else {
                $Msg = __LINE__.' Make your choices and press a button';
                $this->renderview($Sender, 'feeddiscussionsplusconfig', $Msg);
            }  
       } else {  //Initial Form setup
            //Some of the config options are hidden from the admin
            $Getlogo  = c('Plugins.FeedDiscussionsPlus.GetLogo', true);
            $Croncode = c('Plugins.FeedDiscussionsPlus.Croncode', 'code');
            $Feedusername = c('Plugins.FeedDiscussionsPlus.Feedusername', 'Feed');
            $Returntolist = c('Plugins.FeedDiscussionsPlus.Returntolist', false);
            $Allowupdate = c('Plugins.FeedDiscussionsPlus.allowupdate', false);
            $Showurl    = c('Plugins.FeedDiscussionsPlus.showurl', false);
            $Detailedreport    = c('Plugins.FeedDiscussionsPlus.Detailedreport', false);
            $Userinitiated = c('Plugins.FeedDiscussionsPlus.Userinitiated', true);
            $Globalmaximport = c('Plugins.FeedDiscussionsPlus.Globalmaximport', 200);
            //
            $Sender->Form->setValue('Croncode', $Croncode);
            $Sender->Form->setValue('Feedusername', $Feedusername);
            $Sender->Form->setValue('Returntolist', $Returntolist);
            $Sender->Form->setValue('Userinitiated', $Userinitiated);
            $Sender->Form->setValue('Detailedreport', $Detailedreport);
            $Sender->Form->setValue('Globalmaximport', $Globalmaximport);
            //
            //$Msg = $this->GetStash('Msg');
            $Msg = $this->getmsg('', 'get:'.__FUNCTION__.__LINE__);
        }
        $this->renderview($Sender, 'feeddiscussionsplusconfig', $Msg);
    }
/**
* Display and manage list of feeds.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_listfeeds($Sender, $Args) {
        //$this->DebugData('', '', true);
        //$this->DebugData($Args, '---Args---', 1);
        if (!Gdn::Session()->IsValid()) {
            echo $this->setmsg(t('You are not logged on'));
            if ($Args[0] == 'relogon2') {
                die(0);
            }
        }
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        //$this->DebugData($Args, '---Args---', 1);
        $this->setcss($Sender);
        //
        $Postback = $Sender->Form->AuthenticatedPostback();
        $i = 0;
        if (isset($Args[0])) {
            if ($Args[0] == 'relogon') { 
                $i +=1;
                $Msg = $this->postmsg($Sender, 'You are now logged on and can proceed.', false, true, false, true);
                echo $Msg;
            }
            if ($Args[$i] == 'Refresh') {    //Popup "return"
                $Sender->Render('Blank', 'Utility', 'Dashboard');  //force redraw of underlying window
                return;
            } elseif ($Args[$i] == 'Cancel') {  //Popdown "return"
                $Sender->jsonTarget('', '', 'Refresh');
            } elseif ($Args[$i] == 'Return') {  //Standard "return"
                //Nothing special
            }
        }
        if ($Postback) {    //Postback
            $FormPostValues = $Sender->Form->FormValues();
            //$this->DebugData($FormPostValues, '---FormPostValues---', 1);
            //  Handle Sort request
            if (isset($FormPostValues["Sort"])) {
                $Sortby = $FormPostValues["Sortby"];
                SaveToConfig('Plugins.FeedDiscussionsPlus.Sortby', $Sortby);
                $Sorts = array(
                   "Feedtitle"  => T("Sorted by feed title"),
                   "NextImport"  => T("Sorted by next import date"),
                   "LastImport"  => T("Sorted by last import date"),
                   "Category"  => T("Sorted by category"),
                   "URL"  => T("Sorted by url"),
                   "Nosort"  => T("Not sorted"),
                );
                $Msg = $this->setmsg($Sorts[$Sortby]);
            } else {
                $Msg = '-';
            }
            //  Handle Add request
            if (isset($FormPostValues["Add"])) {
                $this->pasteparms($Sender);
                $Sender->SetData('Mode', 'Add');
                //$this->controller_addfeed($Sender, $Args);
            }
        } else {  //Initial Form setup
            //Initial form setting
            touchConfig('Plugins.FeedDiscussionsPlus.Popup', 'Popup');
            //$Msg = $this->GetStash('Msg', false, false);    
            //$Msg = $this->GetStash('Msg');
            $Msg = $this->getmsg('', 'get:'.__FUNCTION__.__LINE__);
            //$this->DebugData($Msg, '---Msg---', 1);
            $Sortby = c('Plugins.FeedDiscussionsPlus.Sortby', 'Feedtitle');
            $RestoreFeedURL = (string)$this->GetStash('RestoreFeedURL');
            $Sender->SetData('RestoreFeedURL', $RestoreFeedURL);
            //$this->DebugData($RestoreFeedURL, '---RestoreFeedURL---', 1);
            //$this->DebugData($Sender->Data, '---Sender->Data---', 1);
        }
        $Sender->SetData('Feeds', $this->GetFeeds());
        $this->renderview($Sender, 'feeddiscussionspluslist', $Msg);
    }
/**
* Toggle active state of a feed.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_togglefeed($Sender, $Args) {
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
            $Feed = $this->GetFeed($FeedKey);
            $Active = $Feed['Active'];
            $Feedtitle = $Feed['Feedtitle'];
            if ($Active) {
                   $Msg = $this->setmsg('The "'.$Feedtitle.'" feed has been deactivated.');
                   $Active = false;
            } else {
                   $Msg = $this->setmsg('The "'.$Feedtitle.'" feed has been activated.');
                   $Active = true;
            }
            $this->UpdateFeed($FeedKey, array(
                'Active'     => $Active
            ));
        } else {
              $Msg = $this->setmsg(T("Invalid toggle request"));
        }
        $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
    }
/**
* Schedule next update feed date to now.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_resetfeed($Sender, $Args) {
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
            $Feed = $this->GetFeed($FeedKey);
            $Feedtitle = $Feed['Feedtitle'];
            $NextImport = date('Y-m-d H:i:s', time());
            $Msg = $this->setmsg('The "'.$Feedtitle.'" feed next import date is set to '.$NextImport);
            $this->UpdateFeed($FeedKey, array(
                'NextImport' => $NextImport
            ));
        } else {
            $Msg = $this->setmsg(T("Invalid schedule request"));
        }
        $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
        //
    }
/**
* Display the readme screen.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_readme($Sender, $Args) {
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $this->renderview($Sender, 'help', '');
    }
/**
* Display the Add first feed screen.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_addfirst($Sender, $Args) {
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        $this->renderview($Sender, 'Addfirst', '');
    }
/**
* Load form with feed fields.
*
* @param object $Sender Standard Vanilla
* @param Array  $Feed   Feed import definition
* @param String $Method value setting method
*
* @return bool|int
*/
    public function loadformfields($Sender, $Feed, $Method = 'setValue') {
        //$this->DebugData($Method, '---Method---', 1);
        //$this->DebugData($Feed['URL'], '---$Feed[\'URL\']---', 1);
        if (strtolower($Method) == 'setvalue') {
            $Sender->Form->setValue('FeedURL', $Feed['URL']);
            $Sender->Form->setValue('Historical', $Feed['Historical']);
            $Sender->Form->setValue('Refresh', $Feed['Refresh']);
            $Sender->Form->setValue('Active', $Feed['Active']);
            $Sender->Form->setValue('OrFilter', $Feed['OrFilter']);
            $Sender->Form->setValue('AndFilter', $Feed['AndFilter']);
            $Sender->Form->setValue('Filterbody', $Feed['Filterbody']);
            $Sender->Form->setValue('Minwords', $Feed['Minwords']);
            $Sender->Form->setValue('Maxitems', $Feed['Maxitems']);
            $Sender->Form->setValue('Activehours', $Feed['Activehours']);
            $Sender->Form->setValue('Getlogo', $Feed['Getlogo']);
            $Sender->Form->setValue('Noimage', $Feed['Noimage']);
            $Sender->Form->setValue('Category', $Feed['Category']);
            $Sender->Form->setValue('Feedtitle', $Feed['Feedtitle']);
            //
            $Sender->setData('Feedtitle', $Feed['Feedtitle']);
            $Sender->SetData('Feeds', $this->GetFeeds());
        } elseif (strtolower($Method)== 'setdata') {
            $Sender->setData('FeedURL', $Feed['URL']);
            $Sender->setData('Historical', $Feed['Historical']);
            $Sender->setData('Refresh', $Feed['Refresh']);
            $Sender->setData('Active', $Feed['Active']);
            $Sender->setData('OrFilter', $Feed['OrFilter']);
            $Sender->setData('AndFilter', $Feed['AndFilter']);
            $Sender->setData('Filterbody', $Feed['Filterbody']);
            $Sender->setData('Minwords', $Feed['Minwords']);
            $Sender->setData('Maxitems', $Feed['Maxitems']);
            $Sender->setData('Activehours', $Feed['Activehours']);
            $Sender->setData('Getlogo', $Feed['Getlogo']);
            $Sender->setData('Noimage', $Feed['Noimage']);
            $Sender->setData('Feedtitle', $Feed['Feedtitle']);
            $Sender->setData('Category', $Feed['Category']);
            $Sender->SetData('Feeds', $this->GetFeeds());
        } else {
              echo __LINE__.' Error in '.__CLASS__.' function '.__FUNCTION__.' wrong Method parameter:'.$Method;
                die(0);
        }
    }
/**
* Import FeedDiscussions Plugin defined feeds.
*
* @param object $Sender Standard Vanilla
*
* @return bool|int
*/
    public function controller_getoldfeeds($Sender) {
        $Sql = clone Gdn::sql();
        $Sql->reset();
        $User = '0';
        $UserMetaData = $Sql
              ->select('*')
              ->from('UserMeta u')
              ->where('u.UserID <', '1')
              ->where('u.Name like', 'Plugin.FeedDiscussions.Feed.%')
              ->get();

        $i = $UserMetaData->numRows();
        $olds = 0;
        foreach ($UserMetaData as $Entry) {
            $Name = $Entry->Name;
            $Value = $Entry->Value;
            $DecodedFeedItem = json_decode($Value, true);
            $FeedURL = val('URL', $DecodedFeedItem, null);
            $FeedKey = self::EncodeFeedKey($FeedURL);
            if (is_null($FeedKey)) {
                continue;
            }
            if ($this->HaveFeed($FeedKey)) {
                continue;                       //Already imported (of we have the same url...;
            }
            $olds += 1;
            $this->AddFeed($this->rebuildurl($FeedURL, ''), array(
              'Historical'   => val('Historical', $DecodedFeedItem, null),
              'Refresh'     => val('Refresh', $DecodedFeedItem, null),
              'Category'    => val('Category', $DecodedFeedItem, null),
              'Added'       => val('Added', $DecodedFeedItem, null),
              'Active'       => false,
              'Feedtitle'       => '',
              'OrFilter'       => '',
              'AndFilter'       => '',
              'Filterbody'      => false,
              'Minwords'       => '',
              'Activehours'       => '00-24',
              'Maxitems'       => null,
              'NextImport'     => null,
              'LastImport'   => val('LastImport', $DecodedFeedItem, null)
              ));
              echo '<br>Imported definiton for '.$FeedURL;
        }
        //
        echo '<h3>'.$olds.' FeedDiscussions feed definitions imported into the new FeedDiscussions Plus Plugin</h3>';
        echo 'Press the <a class="Button UpdateFeed" href="'.Url('/plugin/feeddiscussionsplus/').
              '" >return</a> button to exit.';
        return;
    }
/**
* Check whether current server hour is within the defined active window hours.
*
* @param string $Activehours window hours
*
* @return bool|int
*/
    public function iswithinwindow($Activehours) {
        //$this->DebugData($Activehours, '---Activehours---', 1);
        if ($Activehours == '') {
              return true;    // Eligible
        }
        $CurrentHour = (integer)date('H');
        //$this->DebugData($CurrentHour, '---CurrentHour---', 1);
        $Times = $arr = explode("-", $Activehours, 3);
        $Activefrom = (integer)$Times[0];
        $Activeto = (integer)$Times[1];
        //$this->DebugData($Times, '---Times---', 1);
        if ($Activefrom == $Activeto) {
            if ($CurrentHour != $Activefrom) {
                 //echo '<br>'.__LINE__.'CurrentHour:'.$CurrentHour.' Activefrom:'.$Activefrom.'<br>' ;
                 return false; //outside window
            }
        } elseif ($Activefrom < $Activeto) {
            if ($CurrentHour < $Activefrom) {
                 //echo '<br>'.__LINE__.'CurrentHour:'.$CurrentHour.' Activefrom:'.$Activefrom.'<br>' ;
                 return false; //outside window
            }
            if ($CurrentHour > $Activeto) {
                 //echo '<br>'.__LINE__.'CurrentHour:'.$CurrentHour.' Activeto:'.$Activeto.'<br>' ;
                 return false; //outside window
            }
        } else {
            if ($CurrentHour < $Activefrom && $CurrentHour > $Activeto) {
                     //echo '<br>'.__LINE__.'CurrentHour:'.$CurrentHour.' Activefrom:'.$Activefrom.'<br>' ;
                     return false; //outside window
            }
        }
        return true;
    }
/**
* Validate form entered Active hours field.
*
* @param string $Activehours window hours
*
* @return string error message
*/
    public function validatehours($Activehours) {
        //$this->DebugData($Activehours, '---Activehours---', 1);
        if ($Activehours == '') {
            return '';
        }
        $Times = $arr = explode("-", $Activehours, 3);
        $Count = count($Times);
        $Activefrom = $Times[0];
        $Activeto = $Times[1];
        if (($Count !=2)) {
            return '"<B>'.$Activehours.'</B>" is not a valid "Active Between" input. Valid format:nn-nn';
        }
        if (!preg_match("/^-?[0-9]+$/", $Activefrom) | !preg_match("/^-?[0-9]+$/", $Activeto)) {
            return '"<B>'.$Activehours.'</B>" is not a valid "Active Between" input (hours must be numeric). Valid format:nn-nn';
        }
        if ((0+$Activeto>24) | (0+$Activefrom>24)) {
            return '"<B>'.$Activehours.'</B>" is not a valid "Active Between" input (Hour element cannotexceed 24).';
        }
        return '';
    }
/**
* Delete a feed.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_deletefeed($Sender, $Args) {
        if (!$this->checklogin($Sender, $Args)) {
            echo $this->setmsg(t('You are not logged on'));
            return;
        }
        $Sender->Permission('Garden.Settings.Manage');
        //$this->DebugData($Args, '---Args---', 1);
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
            $Feed = $this->GetFeed($FeedKey, true);
            //decho($Feed);
            $FeedURL = $Feed["FeedURL"];
            //$this->DebugData($FeedURL, '---FeedURL---', 1);
            $Feedtitle = $Feed['Feedtitle'];
            $this->SetStash($Feed, 'RestoreFeed');
            $this->SetStash($Feed["FeedURL"], 'RestoreFeedURL');
            $Sender->SetData('RestoreFeedURL', __LINE__.$FeedURL);
            $this->RemoveFeed($FeedKey);
            $Restorebutton = '<a class="Button " href="' . Url('/plugin/feeddiscussionsplus/restorefeed/'.$RestoreFeedURL).
                '" title="' . t('Restore recently deleted feed').' '.$RestoreFeedURL.'"><ffred> ↻ </ffred>'.t("Undo Delete").'</a>';
            //
            $Restorebutton = '<a class="Button ffcolumn ffundelete" href="' . Url('/plugin/feeddiscussionsplus/restorefeed/'.$RestoreFeedURL).
          '" title="' . t('Restore recently deleted feed').' '.$RestoreFeedURL.'"> ↻ '.t("Undo Delete").'</a>';   
            //    
            $Msg = $this->setmsg('Feed "'.$Feed["Feedtitle"].'" was deleted. Click '.$Restorebutton. ' to restore it.');
        } else {
            $Msg = $this->setmsg(' Invalid prameters');
        }
        $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds?'.__LINE__, $Msg);
    }
/**
* Establish copy/paste data bin.
*
* @param object $Sender Standard Vanilla
*
* @return flag whether there is anything in the copy/paste bin
*/
    private function setcopypaste($Sender) {
        //$this->DebugData(__LINE__, '', 1);
        $Copied = $Sender->Data('Copied');
        //$this->DebugData($Copied, '---Copied---', 1);
        if (!$Copied) {
            $Copied = $this->GetStash('Copied');
        }
        //$this->DebugData($Copied, '---Copied---', 1);
        if ($Copied) {
            $Sender->SetData('Copied', true);
            $this->SetStash($Copied, 'Copied');     //Reestablish stash
        }
        //$this->DebugData($Copied, '---Copied---', 1);
        return $Copied;
    }
/**
* Copy feed selected definition parameters.
*
* @param object $Sender         Standard Vanilla
* @param object $FormPostValues Standard Vanilla
*
* @return array of copied parameters
*/
    public function copyparms($Sender, $FormPostValues) {
        $Copyfields =  array(
                "Refresh", "Category", "OrFilter", "AndFilter", "Filterbody", "Active", "Activehours", "Minwords", "Maxitems", "Getlogo", "Noimage", "Historical"
                );
        foreach ($Copyfields as $Key) {
            $Copyfeed[$Key] = $FormPostValues[$Key];
            //if ($Key == 'Historical') $this->DebugData($FormPostValues, '---FormPostValues---', 1);
        }
        $this->SetStash($Copyfeed, 'Copyfeed');
        $Sender->SetData('Copied', true);
        $this->SetStash(true, 'Copied');
        $this->postmsg($Sender, 'Feed settings were copied.', false, true, false, true);
        //postmsg($Sender, $Msg, $Inform = false, $Addline = true, $Adderror = false, $Stash = false)
        return $Copyfeed;
    }
/**
* Paste feed selected definition parameters on the form.
*
* @param object $Sender Standard Vanilla
*
* @return N/A
*/
    public function pasteparms($Sender) {
        $Copyfeed = $this->GetStash('Copyfeed', true);  //Fetch & Retain
        if (empty($Copyfeed)) {
            $Sender->SetData('Copied', false);
            $this->postmsg($Sender, 'Nothing to paste');
        } else {
            $Sender->SetData('Copied', true);
            $this->SetStash(false, 'Copied');
            foreach (array_keys($Copyfeed) as $Key) {
                $Sender->Form->setFormValue($Key, $Copyfeed[$Key]);
                //if ($Key == 'Refresh') $this->DebugData($Copyfeed[$Key], '---Copyfeed[Key]---', 1);
            }
            $this->postmsg($Sender, 'Feed settings were pasted over <FFYELLOW>but not saved</FFYELLOW>.', false, true, false, true);
            //postmsg($Sender, $Msg, $Inform = false, $Addline = true, $Adderror = false, $Stash = false)
        }
    }
/**
* Validate a feed.
*
* @param object $Sender    Standard
* @param object $Feedarray Feed definition
* @param object $Mode      Validation mode (Add/Update)
*
* @return bool|int
*/
    private function validatefeed($Sender, $Feedarray, $Mode = 'Add') {
        //$this->DebugData(__LINE__, '', 1);
        //$this->DebugData($Feedarray, '---Feedarray---', 1);
        $Feedarray["FeedURL"] =  explode(' ', trim($Feedarray["FeedURL"]))[0];
        //$this->postmsg($Sender, 'Feedarray["FeedURL"]:'.$Feedarray["FeedURL"]);
        if (substr($Feedarray["FeedURL"], 0, 1) == '@') {
            $Feedarray["FeedURL"] = strtolower($Feedarray["FeedURL"]);
        } elseif (substr($Feedarray["FeedURL"], 0, 1) == '#') {
            $Feedarray["FeedURL"] = strtolower($Feedarray["FeedURL"]);
        } elseif (substr($Feedarray["FeedURL"], 0, 1) == '!') {
            $Feedarray["FeedURL"] = strtolower($Feedarray["FeedURL"]);
        }
        //
        try {
            if (empty($Feedarray["FeedURL"])) {
                throw new Exception($this->setmsg(" You must supply a valid Feed URL"));
            }
            if ($Feedarray["FeedURL"] == '?') {
                throw new Exception("?");
            }
            if ($Mode == 'Add') {
                if ($this->HaveFeed($Feedarray["FeedURL"], false)) {
                    if (substr($Feedarray["FeedURL"], 0, 1) == '@') {
                        $Msg = $this->setmsg('The Twitter user "<b>' . $Feedarray["FeedURL"] . '</b>" is already in the list');
                    } elseif (substr($Feedarray["FeedURL"], 0, 1) == '#') {
                        $Msg = $this->setmsg('The Twitter hashtag "' . $Feedarray["FeedURL"] . '" is already in the list');
                    } elseif (substr($Feedarray["FeedURL"], 0, 1) == '!') {
                        $Msg = $this->setmsg('The Instagram entity "' . $Feedarray["FeedURL"] . '" is already in the list');
                    } else {
                        $Msg = $this->setmsg('The Feed URL you supplied is already in the list');
                    }
                    $FeedRSS["FeedURL"] = $Feedarray["FeedURL"];
                    throw new Exception($this->setmsg($Msg));
                }
            }
            // Get RSS Data
            $FeedRSS = $this->getfeedrss($Sender, $Feedarray["FeedURL"], false);     //Request metadata only
            //$this->DebugData($FeedRSS, '---FeedRSS---', 1);
            //$this->postmsg($Sender, 'FeedRSS["FeedURL"]:'.$FeedRSS["FeedURL"]);
            //$this->postmsg($Sender, 'FeedRSS["InternalURL"]:'.$FeedRSS["InternalURL"]);
            if ($FeedRSS["Error"] != '') {
                $FeedRSS["Error"] = $this->setmsg($FeedRSS["Error"]);
                if (!empty($FeedRSS["SuggestedURL"])) {
                    //$this->DebugData($FeedRSS["Redirect"], '---FeedRSS["Redirect"]---', 1);
                    //$this->DebugData($FeedRSS["SuggestedURL"], '---FeedRSS["SuggestedURL"]---', 1);
                    //$Feedarray["FeedURL"] . ' ', false);
                    if ($FeedRSS["Redirect"]) {
                        $Msg = $this->setmsg('The url you specified redirects to a regular web page,not a feed: '.$FeedRSS["InternalURL"]);
                    } else {
                        $Msg = $this->setmsg('The url you specified is a regular web page,not a feed: '.$FeedRSS["FeedURL"]);
                    }
                    //$this->postmsg($Sender, $Msg);
                    throw new Exception($this->setmsg($Msg));
                }
                throw new Exception($this->setmsg($FeedRSS['Error']));
            }
            //$this->postmsg($Sender, __FUNCTION__.' '.__LINE__.' InternalURL:'.$FeedRSS['InternalURL']);
            //
            //$this->DebugData($FeedRSS["Scheme"], '---FeedRSS["Scheme"]---', 1);
            if (!array_key_exists($Feedarray["Category"], CategoryModel::Categories())) {
                throw new Exception($this->setmsg("Specify a valid destination category"));
            }
            // Validate maximum number of items
            $Feedarray["Maxitems"] = trim($Feedarray["Maxitems"]);
            if (!empty($Feedarray["Maxitems"]) && !is_numeric($Feedarray["Maxitems"])) {
                throw new Exception('"'.$Feedarray["Maxitems"].'" is not valid. If you specify a maximum number of items it must be numeric.');
            }
            // Validate minimum number of words
            if (trim($Feedarray["Minwords"]) && !is_numeric($Feedarray["Minwords"])) {
                throw new Exception($this->setmsg('"'.$Feedarray["Minwords"].
                                                  '" is not valid. If you specify a minimum number of words it must be numeric.'));
            }
            // Validate Active hours (format hh-hh)
            $Msg = $this->validatehours($Feedarray["Activehours"]);
            if ($Msg != '') {
                throw new Exception($this->setmsg($Msg));
            }
        } catch (Exception $e) {
            $FeedRSS['Error'] = T($this->setmsg($e->getMessage()));
            $FeedRSS['Error'] = T($e->getMessage());
            //$this->DebugData($FeedRSS, '---FeedRSS---', 1);
        }
        //decho ($FeedRSS);
        return $FeedRSS;
    }
/**
* Display feedurl conventions.
*
* @param bool $Sender standard
*
* @return none
*/
    protected function helpfeedurl($Sender) {
        //$this->DebugData(__LINE__, '', 1);
        //postmsg($Sender, $Msg, $Inform = false, $Addline = true, $Adderror = false, $Stash = false)
        $this->postmsg($Sender, " Valid inputs: ", false, false, true);
        $this->postmsg($Sender, "&nbsp Url of a feed (supported feed formats: RSS, ATOM, RDFprism)", false, false, true);
        $this->postmsg($Sender, "&nbsp URL of a website (if it references a feed, the referenced feed's url will be used)", false, false, true);
        $this->postmsg($Sender, "&nbsp Shortened url (e.g. bitly url). The referenced url will be used.", false, false, true);
        $this->postmsg($Sender, "&nbsp @TwitterID for twitter feed stream (e.g. @vanilla)", false, false, true);
    }
/**
* Get feed definitions array.
*
* @param bool $Raw   Request for raw list
* @param bool $Regen Request array regeneration
*
* @return none
*/
    protected function getfeeds($Raw = false, $Regen = false) {
        if (is_null($this->feedlist) || $Regen) {
            $FeedArray = $this->GetUserMeta(0, "Feed.%");
            $this->feedlist = array();
            $this->rawfeedlist = array();
            foreach ($FeedArray as $FeedMetaKey => $FeedItem) {
                $DecodedFeedItem = json_decode($FeedItem, true);
                $FeedURL = val('URL', $DecodedFeedItem, null);
                $FeedKey = self::EncodeFeedKey($FeedURL);
                $DecodedFeedItem["FeedKey"] = $FeedKey;
                if (is_null($FeedURL)) {
                    continue;
                }
                $this->rawfeedlist[$FeedKey] = $this->feedlist[$FeedURL] = $DecodedFeedItem;
            }
        }
        return ($Raw) ? $this->rawfeedlist : $this->feedlist;
    }
/**
* Fetch feed items and conditionally save as new discussions.
*
* @param string $Sender         standard
* @param array  $FeedData       Feed import defintion
* @param bool   $AutoImport     Indicates automatic import
* @param bool   $Reportindetail request detailed report
*
* @return none
*/
    protected function pollfeed($Sender, $FeedData, $AutoImport, $Reportindetail) {
          //$this->DebugData($LastImportDate, '---LastImportDate---', 1);
        $NumCheckedItems = 0;
        $NumFilterFailedItems = 0;
        $NumSavedItems = 0;
        $NumAlreadySavedItems = 0;
        $NumSkippedOldItems = 0;
        $NumSkippedItems = 0;
        $FeedURL = $FeedData["FeedURL"];
        $InternalURL = $FeedData["InternalURL"];    //Try to reduce redirection
        $OrFilter = $FeedData["OrFilter"];
        $AndFilter = $FeedData["AndFilter"];
        $Filterbody = $FeedData["Filterbody"];
        $Minwords = $FeedData["Minwords"];
        $Maxitems = val("Maxitems", $FeedData, 0);
        $Getlogo = $FeedData["Getlogo"];
        $Noimage = $FeedData["Noimage"];
        $Category = $FeedData["Category"];
        $Historical = $FeedData["Historical"];
        $LastImport = $FeedData["LastImport"];
        $NextImport = $FeedData["NextImport"];
        $Maxbodysize = (int) c('Vanilla.Comment.MaxLength', 1000)-40;
        //$this->DebugData($LastImport, '---LastImport---', 1);
        //$this->DebugData($NextImport, '---NextImport---', 1);
        //$this->DebugData($Historical, '---Historical---', 1);
        //
        //$this->DebugData($AutoImport, '---AutoImport---', 1);
        $FeedRSS = $this->getfeedrss($Sender, $FeedURL, true, false, $AutoImport);    //Request metadata AND data
        if ($FeedRSS['Error']) {
            if ($AutoImport) {
                echo '<br>'.__LINE__.' Error fetching url:'.$FeedURL;
                echo '<br>'.__LINE__.' Feed does not have a valid RSS definition.'.$FeedRSS['Error'].'<br>';
            }
            return false;
        }
        $Encoding = $FeedRSS['Encoding'];
        $Updated = $FeedRSS['Updated'];
        //
        $Itemkey = 'channel.item';
        $Datekey = 'pubDate';
        $Contentkey = 'description';
        if ($Encoding == 'Atom' OR $Encoding == 'Rich Atom' ) {
            $Itemkey = 'entry';
            $Datekey = 'published';
            $Contentkey = 'content';
            $Linkkey = '@attributes.href';
        } elseif ($Encoding == 'RSS' OR $Encoding == 'Rich RSS' OR $Encoding == 'Instagram') {
            $Itemkey = 'channel.item';
            $Datekey = 'pubDate';
            $Contentkey = 'description';
            $Linkkey = '0';
        } elseif ($Encoding == 'Twitter' | $Encoding == '#Twitter') {
            $Itemkey = 'channel.item';
            $Datekey = 'pubDate';
            $Contentkey = 'description';
            $Linkkey = '0';
        } elseif ($Encoding == 'RDFprism') {
            $Itemkey = 'item';
            $Datekey = 'prismpublicationDate';
            $Contentkey = 'description';
            $Linkkey = 'link';
        } elseif ($Encoding == 'Youtube') {
            $Itemkey = 'entry';
            $Datekey = 'published';
            $Contentkey = 'mediagroup.mediadescription';
            $Linkkey = 'link';
        } elseif ($Encoding == 'New') {          //Change to a different encoding and set the tags as necessary
            $Itemkey = 'channel.item';
            $Datekey = 'pubDate';
            $Contentkey = 'description';
            $Linkkey = '0';
        } else {
            if ($AutoImport) {
                echo '<br>'.__LINE__.' Feed does not have a recognizable RSS/Atom encoding<br>';
            }
            return false;
        }
        //
        //$this->DebugData(gettype($FeedRSS["RSSdata"]), '---gettype(/$FeedRSS["RSSdata"])---', 1);
        $RSSdata = (array)$FeedRSS["RSSdata"];
        //$this->DebugData($RSSdata, '---RSSdata---', 1);
        //$this->DebugData($Encoding, '---Encoding---', 1);
        //$this->DebugData($Itemkey, '---Itemkey---', 1);
        $Items = valr($Itemkey, $RSSdata, valr('entry', $RSSdata, valr('channel.item', $RSSdata, '')));
        //$this->DebugData((array)$Items, '---Items---', 1);
        if (!$Items) {
            //$this->DebugData(htmlspecialchars(substr($RSSdata,0,500)), "---RSSdata(0:500)---", 1);
            //if (!array_key_exists('item', $RSSdata)) {
            if ($AutoImport) {
                echo '<br> Feed is empty or does not have a valid RSS content (code '.__LINE__.')<br>';
            }
            return false;
        }
        $Feed = $this->GetFeed($FeedURL, false);
        //$this->DebugData($Feed, '---Feed---', 1);
        //
        $DiscussionModel = new DiscussionModel();
        $DiscussionModel->SpamCheck = false;
        //$LastPublishDate = val('LastPublishDate', $Feed, date('c'));
        $LastPublishDate = val('LastPublishDate', $Feed, val('published', $Feed, date('c')), date('c'));
        $feedupdated = $LastPublishDate;
        //$this->DebugData($LastPublishDate, '---LastPublishDate---', 1);
        //$this->DebugData($feedupdated, '---feedupdated---', 1);
        $LastPublishTime = strtotime($LastPublishDate);
        $FeedLastPublishTime = 0;
        //Get userid of "Feed" id if one was predefined so it would be the saving author of new discussions.
        $Feedusername = c('Plugins.FeedDiscussionsPlus.Feedusername', 'Feed');
        $User = Gdn::userModel()->getByUsername(trim($Feedusername));
        if (empty($User)) {
            $FeedUserid = 0;
        } else {
            $FeedUserid = $User->UserID;
        }
        //$this->DebugData($FeedUserid, '---FeedUserid---', 1);
        $Skipall = false;
        //
        foreach ($Items as $Item) {
            $Item = (array)$Item;
            //$this->DebugData($Item, '---Item---', 1);
            //$this->DebugData($Item->description, '---Item-description ---', 1);
            //$Description = (string)$Item["description"];
            //$Description2 = (string)$Item["description2"];
            //$this->DebugData($Description, '---Description---', 1);
            //$this->DebugData($description2, '---description2---', 1);
            //$this->DebugData(gettype($Description), '---gettype(Description)---', 1);

            if (!$Skipall) {
                $Item = (array)$Item;
                //$Item["content"] = __LINE__.' T E S T';
                //$this->DebugData(array_keys($Item), '---array_keys($Item)---', 1);
                if (($Encoding == "RSS" OR $Encoding == 'Atom') AND (isset($Item["description2"]))) {
                    $Encoding = 'Rich ' . $Encoding;
                    //$this->DebugData($Encoding, '---Encoding---', 1);
                }
                $NumCheckedItems +=1;
                /*if ($NumCheckedItems == 1) {
                    $this->DebugData($Item, '---Item---', 1);
                }
                */
                $Skipitem = false;
                //$this->DebugData((array)$Item["author"], '---(array)Item["author"]---', 1);
                //$this->DebugData((array)$Item["guid"], '---(array)Item["guid"]---', 1);
                //$this->DebugData($Item["link"], '---(array)Item["link"]---', 1);
                //decho ($Item["link"]);
                $Link = (array)json_decode(json_encode($Item["link"]), true);
                //$this->DebugData($Link, '---Link---', 1);
                $Searchlink = array("0.@attributes.href", "href", "@attributes.link", "@attributes.href", $Linkkey, 0);
                foreach ($Searchlink as $Searchkey) {
                    $Itemurl = trim((string)valr($Searchkey, $Link));
                    //echo "<br>".__LINE__." Searchkey:".$Searchkey." Itemurl:".$Itemurl;
                    if ($Itemurl) {
                        if (substr($Itemurl, 0, 1) == "/") {
                            $Domain = @parse_url($this->rebuildurl($FeedURL, 'https'), PHP_URL_HOST);
                            //$this->DebugData($Domain, '---Domain---', 1);
                            $Itemurl = $this->rebuildurl($Domain . $Itemurl, 'https');
                        }
                        //$this->DebugData($Itemurl, '---Itemurl---', 1);
                        break;
                    }
                }
                //$Itemurl = trim((string)valr('link', $Item, valr('@attributes.href', (array)$Item["link"])));
                //$this->DebugData(gettype($Item["link"]), '---gettype(/$Item["link"])---', 1);
                //
                $Title = (string)valr('title', $Item);
                //$this->DebugData($Title, '---Title---', 1);
                //
                $FeedItemGUID = trim((string)valr('guid', $Item, valr('id', $Item)));
                if (empty($FeedItemGUID)) {
                    $FeedItemGUID = valr('link', $Item);
                }
                //$this->DebugData($FeedItemGUID, '---FeedItemGUID---', 1);
                $FeedItemID = substr(md5($FeedItemGUID), 0, 30);
                //$ItemPubDate = (string)val($Datekey, $Item, null);
                $ItemPubDate = (string)valr($Datekey, $Item, valr('pubDate', $Item, valr('published', $Item, '')));
                if (is_null($ItemPubDate)) {
                    $date = new DateTime(date());
                    $ItemPubDate = $date->format("Y-m-d H:i:s");
                } else {
                    $date = new DateTime($ItemPubDate);
                    $ItemPubDate = $date->format("Y-m-d H:i:s");
                }
                //$this->DebugData($ItemPubDate, '---ItemPubDate---', 1);
                if (is_null($ItemPubDate)) {
                    $date = new DateTime();
                } else {
                    $date = new DateTime($ItemPubDate);
                }
                $ItemUpdated = $date->format("Y-m-d H:i:s");
                //$this->DebugData($ItemUpdated, '---ItemUpdated---', 1);
                if (is_null($ItemPubDate)) {
                    $ItemPubTime = time();
                } else {
                    $ItemPubTime = strtotime($ItemPubDate);
                }
                if ($ItemPubTime > $FeedLastPublishTime) {
                    $FeedLastPublishTime = $ItemPubTime;
                }
                //$this->DebugData($ItemPubTime, '---ItemPubTime---', 1);
                //$this->DebugData(date('c', $ItemPubTime), '---ItemPubTime(formatted)---', 1);
                if ((!$Historical) && ($ItemPubDate < $LastPublishDate)) {
                    //echo '<br>'.__LINE__.' Skiping old item.  ItemPubDate:'.$ItemPubDate.' < LastPublishDate:'.$LastPublishDate;
                    $NumSkippedOldItems += 1;
                    $NumSkippedItems += 1;
                    $Skipitem = true;
                } else {
                   //echo '<br>'.__LINE__.' Processing new item.  ItemPubDate:'.$ItemPubDate.' < LastPublishDate:'.$LastPublishDate;
                }
                //echo '<br>'.__LINE__.' Skipall:'.$Skipall.' NumSavedItems:'.$NumSavedItems.' NumSavedItems:'.$NumSavedItems;
                if (($Skipall == false)  && (($Maxitems>0) && ($NumSavedItems == $Maxitems))) {
                    if ($AutoImport) {
                        echo "  Reached maximum items to import:".$Maxitems.'. ';
                    }
                    $Skipall = true;
                }
                if ($Skipall) {
                      $Skipitem = true;
                      $NumSkippedItems += 1;
                }
                if (!$Skipitem) {
                    $ExistingDiscussion = $DiscussionModel->GetWhere(array(
                            'ForeignID' => $FeedItemID
                    ));
                }
                if (!$Skipitem && $ExistingDiscussion && $ExistingDiscussion->NumRows()) {
                      $Skipitem = true;
                      $NumSkippedItems += 1;
                      $NumAlreadySavedItems += 1;
                }
                if (!$Skipitem) {
                    $this->EventArguments['Publish'] = true;
                    $this->EventArguments['FeedURL'] = $FeedURL;
                    $this->EventArguments['Feed'] = &$Feed;
                    $this->EventArguments['Item'] = &$Item;
                    $this->FireEvent('FeedItem');
                    $RPublish = $this->EventArguments['Publish'];
                    if (!$this->EventArguments['Publish']) {
                        $Skipitem = true;
                        $NumSkippedItems += 1;
                    }
                    $StoryTitle = strip_tags(SliceString($Title, 100));
                    $StoryBody = $this->getstorybody($Item, $Contentkey);
                    //$this->DebugData($StoryBody, '---StoryBody---', 1);
                    if ($Filterbody) {
                        $Filtercontent = $StoryTitle . ' ' . $StoryBody;
                    } else {
                        $Filtercontent = $StoryTitle;
                    }
                    //
                    $StoryPublished = date("Y-m-d H:i:s", $ItemPubTime);
                    $Domain = @parse_url($Itemurl, PHP_URL_HOST);
                    //$this->DebugData($Itemurl, '---Itemurl---', 1);
                }
                if (!$Skipitem  && $AutoImport && $Reportindetail) {
                    echo '<br>&nbsp&nbsp&nbsp Processing item #'.$NumCheckedItems.':"'.SliceString($StoryTitle, 40).'".  ';
                }
                if (!$Skipitem && $OrFilter != '') {
                    //$this->DebugData($OrFilter, '---OrFilter---', 1);
                    $Tokens = explode(",", $OrFilter);
                    $Found = false;
                    foreach ($Tokens as $Token) {
                        //$this->DebugData($StoryTitle, '---OrFilter test--- on: '.$Token.' ', 1);
                        if (preg_match('/\b'.$Token.'\b/i', $Filtercontent)) {
                            if ($AutoImport && $Reportindetail) {
                                echo " Matched OR Filter:".$Token." ";
                            }
                            $Found = true;
                        }
                    }
                    if (!$Found) {
                        //$this->DebugData($StoryTitle, '---Filters NOT Matched---Filters:'.$OrFilter.' ', 1);
                        if ($AutoImport && $Reportindetail) {
                              echo " Did not match filters:".$OrFilter." ";
                        }
                        $Skipitem = true;
                        $NumSkippedItems += 1;
                        $NumFilterFailedItems +=1;
                    }
                }
                //
                if (!$Skipitem && $AndFilter != '') {
                    //$this->DebugData($AndFilter, '---AndFilter---', 1);
                    $Tokens = explode(",", $AndFilter);
                    foreach ($Tokens as $Token) {
                        if (!$Skipitem && !preg_match('/\b'.$Token.'\b/i', $Filtercontent)) {
                            //$this->DebugData($StoryTitle, '---AndFilter NOT Matched--- on: '.$Token.' ', 1);
                            if ($AutoImport && $Reportindetail) {
                                echo " Did not match AND filter:".$Token." ";
                            }
                            $Skipitem = true;
                            $NumSkippedItems += 1;
                            $NumFilterFailedItems +=1;
                        }
                    }
                    //$this->DebugData($StoryTitle, '---AndFilterMatch---AndFilters: '.$AndFilter.' ', 1);
                    if (!$Skipitem && $AutoImport && $Reportindetail) {
                           echo " Matched AND filter:".$AndFilter." ";
                    }
                }
                //
                if (!$Skipitem && $Minwords != '') {
                    //$this->DebugData($Minwords, '---Minwords---', 1);
                    if (str_word_count(strip_tags($StoryBody))<$Minwords) {
                        //$this->DebugData($StoryBody, '---Minwords Not Matched---', 1);
                        if ($AutoImport && $Reportindetail) {
                            echo " Did not match minimum number of words:".$Minwords." ";
                        }
                        $Skipitem = true;
                        $NumSkippedItems += 1;
                        $NumFilterFailedItems +=1;
                    } else {
                        //$this->DebugData($StoryBody, '---Minwords Match---', 1);
                        if ($AutoImport && $Reportindetail) {
                            echo " Matched minimum number of words:".$Minwords." ";
                        }
                    }
                }
                //
                if (!$Skipitem) {
                    if (!$Noimage) {
                        if ($Encoding == 'Youtube') {
                            $StoryBody = $this->getyoutubevideo($Sender, $Itemurl) . $StoryBody;
                        } elseif ($Encoding == 'Twitter') {
                            //$this->DebugData($Itemurl, "---Itemurl---", 1);
                            $StoryBody = $this->gettwittercard($Sender, $Itemurl, $StoryBody). $StoryBody;
                            //$this->DebugData($StoryBody, '---StoryBody ---', 1);
                        }
                    } else {
                        $StoryBody = $this->Imagereformat($StoryBody, '!');
                    }
                    //
                    //$this->DebugData($StoryTitle, '---StoryTitle---', 1);
                    //$ParsedStoryBody = '<div class="AutoFeedDiscussion">'.$StoryBody.
                    //'</div> <br/><div class="AutoFeedSource">Source: '.$FeedItemGUID.'</div>';
                    //$l = strlen($StoryBody);
                    //$this->DebugData($l, '---l ---', 1);
                    //$this->DebugData($Maxbodysize, '---Maxbodysize ---', 1);
                    $ParsedStoryBody = '<div class="AutoFeedDiscussion">'.substr($StoryBody, 0, $Maxbodysize).'</div>';
                    $FeedKey = self::EncodeFeedKey($FeedURL);
                    $DiscussionData = array(
                                'Name'          => $StoryTitle,
                                'Format'        => 'Html',
                                'CategoryID'    => $Category,
                                'ForeignID'     => substr($FeedItemID, 0, 30),
                                'Type'          => 'Feed',
                                'Body'          => $ParsedStoryBody,
                                'Attributes'    => array(
                                    'FeedURL'            => $FeedURL,
                                    'Itemurl'            => $Itemurl,
                                    'FeedKey'            => $FeedKey,
                                  ),
                            );
                    //Post as feed ID if one was defined
                    if ($FeedUserid) {
                           $InsertUserID = $FeedUserid;
                    } else {
                        // Post as Minion (if one exists) or the system user
                        if (Gdn::PluginManager()->CheckPlugin('Minion')) {
                            $Minion = Gdn::PluginManager()->GetPluginInstance('MinionPlugin');
                            $InsertUserID = $Minion->GetMinionUserID();
                        } else {
                            $InsertUserID = Gdn::UserModel()->GetSystemUserID();
                        }
                    }
                    $DiscussionData[$DiscussionModel->DateInserted] = $StoryPublished;
                    $DiscussionData[$DiscussionModel->InsertUserID] = $InsertUserID;
                    $DiscussionData[$DiscussionModel->DateUpdated]  = $StoryPublished;
                    $DiscussionData[$DiscussionModel->UpdateUserID] = $InsertUserID;

                    $this->EventArguments['FeedDiscussion'] = &$DiscussionData;
                    $this->FireEvent('Publish');

                    $RFeedDiscussion = $this->EventArguments['FeedDiscussion'];
                    $RPublish = $this->EventArguments['Publish'];
                    //$this->DebugData($DiscussionData, '---$DiscussionData---', 1);
                    //$this->DebugData($DiscussionData["Name"], '---$DiscussionData["Name"]---', 1);
                    //$this->DebugData($RFeedDiscussion, '---RFeedDiscussion---', 1);
                    //$this->DebugData($RPublish, '---RPublish---', 1);
                    //

                    if (!$this->EventArguments['Publish']) {
                          $Skipitem = true;
                          $NumSkippedItems += 1;
                    }
                    if (!$Skipitem) {
                        $DiscussionData[$DiscussionModel->Type] = 'Feed';
                        $DiscussionModel->Validation->Results(true);
                        setValue('Type', $DiscussionModel, 'Feed');
                        //$this->DebugData($DiscussionData["Name"], '---$DiscussionData["Name"]---', 1);
                        echo '<span style="Display:none;visibility:invisible;">';
                        $InsertID = $DiscussionModel->Save($DiscussionData);
                        echo '</span>';
                        //$this->DebugData($DiscussionData["Name"], '---$DiscussionData["Name"]---', 1);;
                        if ($InsertID) {
                            $NumSavedItems += 1;
                            //var_dump ($InsertID);
                        } else {
                            if ($AutoImport) {
                                 echo '<br>'.__LINE__.' Failed save';
                                 decho($DiscussionData);
                            }
                        }
                        $LastSavedItemPubTime = date('c', $ItemPubTime);
                        //$this->DebugData($DiscussionData["Name"], '---Saved...---', 1);
                        $this->EventArguments['DiscussionID'] = $InsertID;
                        $this->EventArguments['Vaidation'] = $DiscussionModel->Validation;
                        $this->FireEvent('Published');
                        // Reset discussion validation
                        $DiscussionModel->Validation->Results(true);
                        //$LastPublishDate = date('c', $FeedLastPublishTime);
                        $LastPublishDate = date('Y-m-d H:i:s', $FeedLastPublishTime);
                    }
                }
                $LastImport = date('Y-m-d H:i:s');
                //$this->DebugData($LastSavedItemPubTime, '---LastSavedItemPubTime---', 1);
                //$this->DebugData($LastPublishDate, '---LastPublishDate---', 1);
                //$this->DebugData($LastImport, '---LastImport---', 1);
                //$this->DebugData($NextImport, '---NextImport---', 1);
                //$this->DebugData($FeedURL, '---FeedURL---', 1);
                $FeedKey = self::EncodeFeedKey($FeedURL);
                $this->UpdateFeed($FeedKey, array(
                    'Active'     => true,
                    'LastImport'     => $LastImport,
                    'NextImport'     => $NextImport,
                    'FeedURL'     => $FeedURL,
                    'Historical'     => false,
                    'Encoding'     => $Encoding,
                    'Compressed'    => $FeedRSS['Compressed'],
                    'LastPublishDate' => $LastPublishDate
                ));
            }
        }
        //$this->DebugData($NextImport, '---NextImport---', 1);
        //$this->DebugData($LastImport, '---LastImport---', 1);
        $this->UpdateFeed($FeedKey, array(
                    'NextImport'     => $NextImport,
                    'LastImport'     => $LastImport,
                    'FeedURL'     => $FeedURL,
                ));
        //
        if ($AutoImport) {
            if ($NumCheckedItems == ($NumAlreadySavedItems + $NumSkippedOldItems)) {
                echo '<span>-No new items </span>';
            } else {
                echo '<span><br>&nbsp&nbsp&nbsp'.$NumCheckedItems. ' items processed, '.
                     $NumSavedItems . ' items saved, ' .
                     $NumSkippedItems . ' skipped ('.
                     $NumAlreadySavedItems . ' previously saved, '. $NumSkippedOldItems . ' old items. '.
                     $NumFilterFailedItems . " Didn't match filters)</span>";
            }
        }
    }
/**
* Fetch YouTube video from a specific YouTube feed item.
*
* @param object $Sender  standard
* @param string $Itemurl url of feed
*
* @return array
*/
    protected function getyoutubevideo($Sender, $Itemurl) {
        //$this->DebugData(__LINE__, '', 1);
        //$this->DebugData($Itemurl, '---Itemurl---', 1);
        $Query   = @parse_url($Itemurl, PHP_URL_QUERY);
        //$this->DebugData($Query, '---Query---', 1);  //Expecting "v=knjht2aXBIk"
        $Youtubeid = substr($Query, 2);
        //$this->DebugData($Youtubeid, '---Youtubeid---', 1);  //Expecting "v=12345678901"
        if (!$Youtubeid) {
            $Youtubeid = '12345678901';
        }
        $Youtubesnap = 'https://img.youtube.com/vi/' . $Youtubeid . '/default.jpg';
        return t('Click to view YouTube video:') .
               '<div><a id=FDPyoutube rel="nofollow" target="_blank" href="' .
               $Itemurl . '" ><img width="240" height="135" alt="" src="' .
               'https://img.youtube.com/vi/' . $Youtubeid . '/default.jpg'.
               '" ></a></div><br>';
    }
/**
* Fetch twittercard from a specific twitter item.
*
* @param object $Sender    standard
* @param string $Url       url of feed
* @param string $StoryBody twit feed content
*
* @return array
*/
    protected function gettwittercard($Sender, $Url, $StoryBody) {
        //$this->DebugData(__LINE__, '', 1);
        //$this->DebugData($Url, '---Url---', 1);
        //
        $Itemid = substr($Url, strrpos($Url, '/') + 1);
        //
        $Webpage = $this->fetchurl($Url, true);
        //$this->DebugData(substr($Webpage["data"],0,600), '---Webpage 0:600---', 1);
        if ($Webpage["Error"] or empty($Webpage["data"])) {
            return '';
        }
        //
        $Item = $Webpage["data"];
        //
        $Segment = $this->getbetweentexts($Item, 'class="card2 js-media-container', '</div>');
        if ($Segment) {
            $Segment = $this->getbetweentexts($Segment, 'data-card-url="', '"');
            if ($Segment) {
                //$this->DebugData($Segment, '---Segment---', 1);
                $Webpage = $this->fetchurl(trim($Segment), true, 3);  //Up to 3 refollows
                //$this->DebugData($Webpage["Error"], '---Webpage["Error"]---', 1);
                if (!isset($Webpage["Error"]) & $Webpage["Error"] != '') {
                    $Segment = $this->getbetweentexts($Webpage["data"], '<meta name="twitter:image" content="', '"');
                    //$this->DebugData($Segment, '---Segment---', 1);
                    //Verify original content doesn't include the image/video content
                    if (($Segment) & (stripos($StoryBody, $Segment) === false)) {
                        $Embed = __LINE__.'<div class=FDP'.__LINE__.'id=FDP'.__LINE__.
                                '><img width="240" alt="" src="' .
                                $Segment . '" ></div><br>';
                        //$this->DebugData($Embed, '---Embed---', 1);
                        return $Embed;
                    }
                }
            }
        }
        //  Try og cards instead
        $Cardtypes = array('property="og:video:url"' => 'Video',
                           'property="og:video:secure_url"' => 'Video',
                           'property="og:image"' => 'Image',
                           );
        foreach ($Cardtypes as $Tag1 => $Type) {
            $Segment = $this->getbetweentexts($Item, $Tag1, '>');
            if ($Segment != '') {
                $Segment = $this->getbetweentexts($Segment, 'content="', '"');
                if ($Segment != '') {
                    //Check whether the original content already has the image/video content
                    if (stripos($StoryBody, $Segment) === false) {      //Image/video already in content?
                        //$this->DebugData($Segment, '---Segment already in source---', 1);
                    } elseif ($Type == 'Image') {
                        $Embed = __LINE__.'<div id=FDP'.__LINE__.'><img width="240" max-height="135" alt="" src="' .
                               $Segment . '" ></div><br>';
                    } elseif ($Type == 'Video') {
                        $Image = $this->getbetweentexts($Item, 'property="og:image"', '>');
                        if ($Image) {
                            $Image = $this->getbetweentexts($Image, 'content="', '"');
                        }
                        if ($Image) {
                            $Embed = __LINE__.t('Click to view video:') .
                               '<div><a class=FDPvideo'.__LINE__.' id=FDPvideo'.__LINE__.' rel="nofollow" target="_blank" href="' .
                               $Segment . '" ><img width="240" max-height="135" alt="" src="' . $Image . '" ></a></div><br>';
                        } else {
                            $Embed = __LINE__.t('Click to view video:').
                                '<div><a class=FDPvideo'.__LINE__.' id=FDPvideo'.__LINE__.' rel="nofollow" target="_blank" href="' .
                                $Segment . '" >Click for Video</a></div><br>';
                        }
                    } else {
                        $Embed = __LINE__.'<div id=FDPvideo'.__LINE__.' class=FDPvideo'.__LINE__.'></div>';
                    }
                    //$this->DebugData($Embed, '---Embed---', 1);
                    return $Embed;
                }
            }
        }
    }
/**
* Get the feed item body.
*
* @param string $Item       full feed entry content
* @param string $Contentkey Key tag where the content resides
*
* @return string
*/
    protected function getstorybody($Item, $Contentkey) {
        //$this->DebugData(__LINE__, '', 1);
        //$Item = (array)json_decode(json_encode($Item), true);
        //$this->DebugData($Contentkey, '---Contentkey---', 1);
        //$StoryBody = (string)
                    valr($Contentkey, $Item, valr('description', $Item, valr('content', $Item, valr('summary', $Item, ''))), ' ');

        $Searchbody = array("description2", "description", $Contentkey, "content", 'summary');
        foreach ($Searchbody as $Searchkey) {
            $StoryBody = trim((string)valr($Searchkey, $Item));
            if ($StoryBody) {
                //$this->DebugData(htmlspecialchars($StoryBody), '---htmlspecialchars(StoryBody)---', 1);
                return (string)$StoryBody;
            }
        }
        return '';
    }
/**
* Create instagram feed from instagram xml page.
*
* @param string $FeedRSS Source from url
* @param string $InstaID Instragram entity
* @param string $Link    Link to Instragram entity
* @param bool   $Data    Indication whether data requested
*
* @return string
*/
    protected function getinstafeed($FeedRSS, $InstaID, $Link, $Data) {
        //$this->DebugData(__LINE__, '', 1);
        //$this->DebugData(gettype($FeedRSS), '---gettype(/$FeedRSS)---', 1);
        //$this->DebugData(substr($FeedRSS,0,500), '---substr(/$FeedRSS,0,500)---', 1);
        $Instadata = (array)json_decode($FeedRSS, true);
        //decho (json_last_error());
        //$this->DebugData(gettype($Instadata), '---gettype(/$Instadata)---', 1);
        //var_dump(get_object_vars($FeedRSS));
        //$this->DebugData(array_keys($Instadata), '---array_keys(/$Instadata)---', 1);
        //$this->DebugData($Instadata, '---/$Instadata---', 1);
        $InstaUser = $Instadata["user"];
        //$this->DebugData($InstaUser, '---/$InstaUser---', 1);
        if (isset($Instadata["user"]["profile_pic_url"])) {
            $Response["RSSimage"] = $Instadata["user"]["profile_pic_url"];
        } elseif (is_set($Instadata["graphql"]["profile_pic_url"])) {
            $Response["RSSimage"] = $Instadata["graphql"]["profile_pic_url"];
        } else {
            $Response["RSSimage"] = null;
        }
        //$this->DebugData($Response["RSSimage"], '---/$Response["RSSimage"]---', 1);
        $Instadata = $Instadata["user"]["media"]["nodes"];
        //$this->DebugData($Instadata, '---/$Instadata---', 1);
        $i = 0;
        $RSSdata =  '';
        $RSShead = '<?xml version="1.0" encoding="utf-8"?>';//  encoding="ISO-8859-1";
        $RSShead .= '<rss version="2.0">';
        $RSShead .= '<channel>';
        $RSShead .= '<title>Instagram feed for ' . $InstaID . '</title>';
        $RSShead .= '<link>' . $Link . '</link>';
        if (!$Data) {
            $i = count($Instadata);
        }
        $Instapubdate = '';
        while($i <= count($Instadata)) {
            $RSSitem  = '';
            $Instaitem = $Instadata[$i];
            $i += 1;
            //$this->DebugData(array_keys($Instaitem), '---array_keys(/$Instaitem)---', 1);
            $Instatypename = $Instaitem["__typename"];
            $Instathumbnail = $Instaitem["thumbnail_src"];
            $Instaisvideo = $Instaitem["is_video"];
            $Instacode = $Instaitem["code"];
            $Instadate = (int)$Instaitem["date"];
            //$this->DebugData($Instatypename, '---Instatypename---', 1);
            //$this->DebugData($Instaisvideo, '---Instaisvideo---', 1);
            $Instadate = date("Y-m-d\TH:i:s",$Instadate);
            if ($Instapubdate == '') {
                $Instapubdate = $Instadate;
            }
            //$this->DebugData($Instadate, '---Instadate---', 1);
            $Instacaption = strip_tags($Instaitem["caption"]);
            $Instacaption = $Instaitem["caption"];
            //$Instacaption = Gdn_Format::replaceButProtectCodeBlocks('/(^|[\s,\.>])\#([\w\-]+)(?=[\s,\.!?<]|$)/i', 
            //                  '', $Instacaption);
            $Instacaption = preg_replace('/#([\w-]+)/i', '', $Instacaption);
            $Instacaption = preg_replace('/@([\w-]+)/i', '', $Instacaption);
            $Instatitle = str_replace(array('()', ' ) ', ' >'),
                                        array(' ', '', '', ''),  
                                     $Instacaption);
            //
            $Instatitle = SliceString($Instatitle, 150);
            //$this->DebugData($Instatitle, '---Instatitle---', 1);
            if ($Instaisvideo) {
            } elseif($Instatypename == "GraphImage") {
                $RSSitem .= '<item>';
                $RSSitem .= '<title><![CDATA[' . $Instatitle . ' >]]></title>';
                $Instadesc = '<description><![CDATA[' . $Instacaption . ' ' . 
                              '<br><img height="300" src="' . $Instathumbnail . '" >]]></description>';
                //$this->DebugData($Instadesc, '---Instadesc---', 1);
                $RSSitem .= $Instadesc;
                $RSSitem .= '<link>' . "https://www.instagram.com/p/" . $Instacode . "/</link>";
                $RSSitem .= '<pubDate>' . $Instadate . '</pubDate>';
                $RSSitem .= '</item>';
                //$this->DebugData($RSSitem, '---RSSitem--- i:'.$i, 1);
            }
            $RSSdata .= $RSSitem;
            //$this->DebugData($RSSdata, '---RSSdata---', 1);
        
        }
        $Response["FeedRSS"] = $RSShead . 
                               '<lastBuildDate>' . $Instapubdate . '</lastBuildDate>'.
                               '<pubDate>' . $Instapubdate . '</pubDate>'.
                               '<description>Instagram RSS feed for ' . $InstaID . '</description><language>en-us</language>'.
                               $RSSdata .'</channel></rss>';
        //$this->DebugData($Response["FeedRSS"], '---Response["FeedRSS"]---', 1);
        //$this->DebugData(array_keys($Instadata), '---array_keys(/$Instadata)---', 1);
        return $Response;
    }
/**
* Add feed to feed definitions array.
*
* @param string $FeedURL url of feed
* @param array  $Feed    Feed array to save into the feed definitions
*
* @return array
*/
    protected function addfeed($FeedURL, $Feed) {
        //$this->DebugData(__LINE__, '', 1);
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        $FeedURL = $this->rebuildurl($FeedURL, '');
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        $FeedKey = self::EncodeFeedKey($FeedURL);
        $Feed['URL'] = $FeedURL;
        $Feed['FeedKey'] = $FeedKey;
        $EncodedFeed = json_encode($Feed);
        $this->SetUserMeta(0, "Feed.{$FeedKey}", $EncodedFeed);
        // Regenerate the internal feed list
        $this->GetFeeds(true, true);
    }
/**
* Update feed in the feed definitions array.
*
* @param string       $FeedKey         Key of feed
* @param string/array $FeedOptionKey   Attribute(s) key
* @param string/array $FeedOptionValue Attribute(s) value
*
* @return array
*/
    protected function updatefeed($FeedKey, $FeedOptionKey, $FeedOptionValue = null) {
        $Feed = $this->GetFeed($FeedKey);
        if (!is_array($FeedOptionKey)) {
            $FeedOptionKey = array($FeedOptionKey => $FeedOptionValue);
        }
        if (is_array($Feed)) { 
            $Feed = array_merge($Feed, $FeedOptionKey);
        } 
        $Feed['FeedKey'] = $FeedKey;
        $EncodedFeed = json_encode($Feed);
        $this->SetUserMeta(0, "Feed.{$FeedKey}", $EncodedFeed);
        // Regenerate the internal feed list
        $this->GetFeeds(true, true);
    }
/**
* Remove feed from the feed definitions array.
*
* @param string $FeedKey    Key of feed
* @param bool   $PreEncoded indicates whether the info is already encoded
*
* @return array
*/
    protected function removefeed($FeedKey, $PreEncoded = true) {
        $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedKey) : $FeedKey;
        $this->SetUserMeta(0, "Feed.{$FeedKey}", null);
        // Regenerate the internal feed list
        $this->GetFeeds(true, true);
    }
/**
* Fetch feed definition from the feed definitions array.
*
* @param string $FeedKey    Key of feed
* @param bool   $PreEncoded indicates whether the info is already encoded
*
* @return array
*/
    protected function getfeed($FeedKey, $PreEncoded = true) {
        $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedKey) : $FeedKey;
        $Feeds = $this->GetFeeds(true);
        if (array_key_exists($FeedKey, $Feeds)) {
            return $Feeds[$FeedKey];
        }
        return null;
    }
/**
* Check whether feed exists in the feed definitions array.
*
* @param string $FeedKey    Key of feed
* @param bool   $PreEncoded indicates whether the info is already encoded
*
* @return bool
*/
    protected function havefeed($FeedKey, $PreEncoded = true) {
        $FeedKey = (!$PreEncoded) ? self::EncodeFeedKey($FeedKey) : $FeedKey;
        $Feed = $this->GetFeed($FeedKey);
        if (!empty($Feed)) {
            return true;
        }
        return false;
    }
/**
* Prepare and return logo string for presentation.
*
* @param string $Logourl  Logo url
* @param string $Type     indicates typeof display (list,item,etc.)
* @param string $Link     Where logo should link
* @param string $Tooltip  Tooltip
* @param string $Encoding Logo feed Encoding
*
* @return string
*/
    protected function logowrap($Logourl, $Type, $Link, $Tooltip = '', $Encoding = '', $Classprefix = 'FDP') {
        $Logoclass = $Classprefix . 'logo'.$Type;
        $Linkclass = $Classprefix . 'link'.$Type;
        $Logowrapclass = $Classprefix . 'logowrap';
        $Atsignclass = $Classprefix . 'atsign ' . $Classprefix . 'atsign' . $Type;
        $Markclass = $Classprefix . 'instasign' . $Type;
        $Addwrapclass = '';
        $Addmark = '';
        $Useencoding = '';
        if ($Encoding == 'RSS' OR $Encoding == 'Atom') {
            if (c('Plugins.FeedDiscussionsPlus.Marklogo', false)) {
                $Useencoding = 'Feed';
            }
        } else {
            $Useencoding = $Encoding;
        }
        if ($Useencoding == 'Feed' OR $Useencoding == 'Twitter' OR $Useencoding == 'Instagram' OR $Useencoding == 'Pinterest') {
            $Addwrapclass = $Classprefix . $Type . 'wrap';
            $Markclass = $Classprefix . $Useencoding  . $Type;
            $Markid = $Classprefix . $Useencoding . 'img';
            $Markimage = $Useencoding . 'Mark.png';
            $Bottom = 25;
            $Addmark = '<img class=' . $Markclass . ' style="Bottom:' . $Bottom . 'px;" id=' . $Markid . ' src="' . 
                                url('plugins/FeedDiscussionsPlus/design/' . $Markimage) . '" >' ;
        }
        //
        $Logo = '<span class="'.trim($Logowrapclass . ' ' . $Addwrapclass).'" id="'.$Logowrapclass.'"><img src="' . $Logourl . 
                '" id=RSSimage class="'.$Logoclass.'" title="'.$Tooltip.'"> ' . $Addmark . '</span> ';                
        if ($Link) {
            //$this->DebugData($Link, '---Link---', 1);
            $Logo = ' '.anchor($Logo, $Link, $Linkclass, array('rel' => 'nofollow', 'target' => '_BLANK', 'id' => 'RSSlink', 'class' => $Classprefix . 'link'));
        }
        //$this->DebugData(htmlspecialchars($Logo), '---htmlspecialchars(Logo)---', 1);
        return $Logo;
    }
/**
* Optionally place the RSS Image within the meta area of the discussion list.
*
* @param object $Sender standard
* @param string $Type   indicates whether the embed request is on an individual discussion or a list
*
* @return none
*/
    public function embedrssimage($Sender, $Type = 'list') {
        //$this->DebugData(__LINE__, '', 1);
        $Discussion = $Sender->EventArguments['Discussion'];
        //$this->DebugData($Discussion->Attributes, '---Discussion->Attributes---', 1);
        //
        $Feed = $this->getdiscussionfeed($Discussion);    //Try to get feed info for the current discussion
        if (!$Feed) {
            //$FeedURL = $Discussion->Attributes['FeedURL'];
            //$this->DebugData($FeedURL, '---FeedURL---', 1);
            //var_dump($Discussion->Type, $Discussion->Attributes);
            return;
        }
        if ($Feed == -1) {
            if ($Type == 'item') {
                echo $this->setmsg(t('Imported from a malfunctioning feed'), true);
            }
            return;
        }
        if ($Type == 'list') {            //Discussion list
            if (!$Feed['Getlogo']) {
                return;
            }
        }
        //
        $FeedURL = $Feed['FeedURL'];
        $RSSimage = $Feed['RSSimage'];
        $Scheme = $Feed['Scheme'];
        //$this->DebugData($RSSimage, '---RSSimage---', 1);
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        //$this->DebugData($Scheme, '---Scheme---', 1);
        if (!$FeedURL | !$RSSimage) {       //if there is no url or image ignore this discussion
            return;
        }
        $Encoding = $Feed['Encoding'];
        $Logoclass = 'RSSimage'.$Type.'0 ';
        $Logowrapclass = 'RSSlogowrap';
        $Oneitemclass = 'RSSlogobox';
        $Listitemclass = 'RSSlistlogobox';
        if ($Encoding == 'Twitter') {
            $Logowrapclass = $Logowrapclass . ' Twitterlogo';
        }
        $Itemurl = val('Itemurl', $Discussion->Attributes);
        //If there is no link to the imported item (RSS feeds are unreliable...) then revert to the feed's url
        if (!$Itemurl) {
             //Comment out next line if you want to disable link to the feed
             $Itemurl = $this->rebuildurl($FeedURL, 'https');
             //echo '<!--debug '.__LINE__.' --> ';
        }
        //$this->DebugData($Discussion->Attributes, '---Discussion->Attributes---', 1);
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        //$this->DebugData($Feed, '---Feed---', 1);
        //$this->DebugData($Itemurl, '---Itemurl---', 1);
        //
        $Logo = '<img src="' . $RSSimage . '" id=RSSimage class=RSSimage'.$Type.'0 title="'.$Feed["Feedtitle"].'"> ';
        $Logo = '<span class="'.$Logowrapclass.'" id=RSSlogowrap><img src="' . $RSSimage . '" id=RSSimage class="'.$Logoclass.'" title="'.$Feed["Feedtitle"].'"></span> ';
        if ($Encoding == 'Twitter') {
            $Logo = $Logo . '<span id=RSSatsign class="RSSatsign">@</span>';
        }
        //$this->DebugData(htmlspecialchars($Logo), '---htmlspecialchars(Logo)---', 1); 
        $Logo = $this->logowrap($RSSimage, $Type, $Itemurl, $Feed["Feedtitle"], $Encoding);
        //
        
        if ($Type == 'list') {            //Discussion list
            echo $Logo;
        } elseif ($Type == 'item') {      //Discussion item
            if ($Feed['Getlogo']) {
                echo $Logo;
            }
            echo wrap(
                    wrap(
                        ' '.anchor(T('Imported from:').$Feed["Feedtitle"].'<span class="RSSsourcetext" title="'.
                        t('Click to view the feed source').'">ℹ</span> ', $Itemurl, ' ', array('rel' => 'nofollow', 'target' => '_BLANK', 'class' => 'RSStextlink')),
                        'span',
                        array('class' => 'RSSsource')
                    ),
                    'span',
                    array('class' => 'RSSsourcebox')
                );
        }
        $this->repositionfeedimage($Type);
    }
/**
* Script to reposition feed image on author's image.
*
* @param string $Type   indicates whether the embed request is on an ind
*
* @return string (domain or a url)
*/
    public function repositionfeedimage($Type) {
        echo '<script language="javascript" type="text/javascript"> '.
             '$("#FDP .PhotoWrap").css({opacity:  "0.01", zoom: "0.01"});'.
             '</script>';
        return;
        /*
        if ($Type == 'list') {      
            echo '<script language="javascript" type="text/javascript"> '.
                 'var float = $("#FDP .PhotoWrap").css("float"); '.
                 'var margin = $("#FDP .PhotoWrap").css("margin"); '.
                 'var padding = $("#FDP .PhotoWrap").css("padding"); '.
                 '$("#FDP #RSSlogowrap").css({float: float});'.
                 'var cssposition = $("#FDP .PhotoWrap").css("position");'.
                 'var position = $("#FDP .PhotoWrap").position();'.
                 'var display = $("#FDP .PhotoWrap").css("display"); '.
                 '$("#FDP #xxxRSSimage").css({float: float,
                                                visibility: "visible",
                                                top: position.top, 
                                                left: position.left,
                                                display: display,
                                                margin: margin,
                                                position: "relative"
                                                });'. 
                 '$("#FDP #xxxRSSimage").css({float: "unset",
                                                visibility: "visible",
                                                display: display,
                                                margin: margin,
                                                position: "relative"
                                                });'. 
                 '$("#FDP #xxxRSSatsign").css({visibility: "visible",
                                                float: "unset",
                                                display: "inline-block",
                                                margin: margin,
                                                position: "absolute"
                                                });'. 
                 '$("#FDP #xxxRSSlogowrap").css({float: float, width: "0px", height: "40px"});'.
                 '$("#FDP .RSSlistlogobox").css({height:  "1px", display: "block"});'.
                 '$("#FDP .PhotoWrap").css({opacity:  "0.01", zoom: "0.01"});'.
                 '</script>'; 
            return;
        }
        if ($Type == 'item') {      
            echo '<script language="javascript" type="text/javascript"> '.
                 '$("#FDP .PhotoWrap").css({opacity:  "0.01", zoom: "0.01"});'.
                 'var float = $("#FDP .PhotoWrap").css("float"); '.
                 'var float = "unset";'.
                 'var display = $("#FDP .PhotoWrap").css("display"); '.
                 'var cssposition = $("#FDP .PhotoWrap").css("position");'.
                 'var offset = $("#FDP .PhotoWrap").offset();'.
                 'var margin = $("#FDP .PhotoWrap").css("margin");'.
                 'var position = $("#FDP .PhotoWrap").position();'.
                 '$("#FDP #xxxRSSlogowrap").css({float: float, width: "0px"});'.
                 '$("#FDP #xxxRSSimage").css({position: "initial",
                                                visibility: "visible",
                                                float: float
                                                });'. 
                 '$("#FDP #xxxRSSatsign").css({visibility: "visible",
                                                float: "unset",
                                                display: "inline-block",
                                                margin: "0px",
                                                position: "absolute"
                                                });'. 
                 '$("#FDP #xxxRSSimage").css({float: float,
                                                visibility: "visible",
                                                top: "unset", 
                                                left: "unset",
                                                display: display,
                                                margin: margin,
                                                position: "relative"
                                                });'.
                 '</script>'; 
            return;
        }
        */
    }
/**
* Get the domain of a url.
*
* @param string $Url url
*
* @return string (domain or a url)
*/
    public function getdomain($Url) {
        //$this->DebugData(__LINE__, '', 1);
        $Domain = @parse_url($Url, PHP_URL_HOST);
        if (!$Domain) {
            $Domain = @parse_url($this->rebuildurl($Url, 'http'), PHP_URL_HOST);
        }
        return $Domain;
    }
/**
* Get the feed info for an existing discussion.
*
* @param object $Discussion discussion object
*
* @return array (feed info or null)
*/
    public function getdiscussionfeed($Discussion) {
        //$this->DebugData(__LINE__, '', 1);
        if ($Discussion->Type != 'Feed') {
            return null;
        }
        $FeedURL = $Discussion->Attributes['FeedURL'];
        if (!$FeedURL) {
            return null;
        }
        $FeedKey = self::EncodeFeedKey($FeedURL);
        $Feed = $this->GetFeed($FeedKey);
        if (empty($Feed)) {    //Feed definition deleted...
            //$this->DebugData($FeedURL, '---FeedURL---', 1);
            //$this->DebugData($FeedKey, '---FeedKey---', 1);
            if ($Discussion->Attributes['FeedKey']) {
                //$this->DebugData($FeedKey, '---FeedKey---', 1);
                 $Feed = $this->GetFeed($Discussion->Attributes['FeedKey']);
                 if (empty($Feed)) {
                     return -1;
                 } else {
                     return $Feed;
                 }
            }
            return -1;
        }
        return $Feed;
    }
/**
* Remove the feed info from an existing discussion.
*
* @param object $Discussion discussion object
*
* @return none
*/
    public function unfeed($Discussion) {
        $DiscussionModel = new DiscussionModel();
        $DiscussionModel->SpamCheck = false;
        //$this->DebugData($Discussion->DiscussionID, '---Discussion->DiscussionID---', 1);
        $UpdateData = array('DiscussionID' => $Discussion->DiscussionID,
                            'Type' => null
                            );
        $UpdateID = $DiscussionModel->Save($UpdateData);
        //$this->DebugData($UpdateID, '---UpdateID---', 1);
    }
/**
* Get an entity from the feed source.
*
* @param String/array $Source  source to search for entity information
* @param String       $Key1    Key to search for entity value
* @param String       $Key2    Alternative Key to search for entity value
* @param String       $Source2 Alternative source to search for entity value
* @param String       $Default default value for the entity
*
* @return string of found text
*/
    public function getentity($Source, $Key1, $Key2 = '', $Source2 = '', $Default = '') {
       //Because RSS feeds are oftentimes non-standard conforming with mixed standards tags we need
       // multiple attemps to get the value of keyed element.
       // First attempt is using the first parameter key most matching the encoding (e.g. Atom).
       // Second the alternate key (e..g RSS),  If all fail a brute force scan is made against the keys.
       // The caller should specify the most likely key first.
       //echo '<br>'.__LINE__.htmlentities(substr($Source,0,500)).'<br>';
        $Value = valr($Key1, $Source);
        //echo '<br>'.__LINE__.' First try, Key:'.$Key1.' Value:'.$Value.'<br>';
        //
        if (empty($Value)) {
            //echo '<br>'.__LINE__.' Key1:'.$Key1.' Key2:'.$Key2;
            if ($Key2) {
                $Value = valr($Key2, $Source);
            }
            //echo '<br>'.__LINE__.'2nd try Value:'.$Value.'<br>';
            if (!$Value) {
                //echo '<br>'.__LINE__.' Key1:'.$Key1.' Key2:'.$Key2;
                $Value = $this->getbetweentags($Source, $Key1);
                //echo '<br>'.__LINE__.'3rd try Value:'.$Value.'<br>';
                if (!$Value) {
                    //echo '<br>Not is first source'.__LINE__.' Key1:'.$Key1.' Key2:'.$Key2;
                    if ($Key2) {
                        $Value = $this->getbetweentags($Source, $Key2);
                    }
                    if (!$Value) {
                        //echo '<br> even brute scan of source 1 failed'.__LINE__.' Key1:'.$Key1.' Key2:'.$Key2;
                        if ($Source2) {
                            //echo '<br>checking alternate source source'.__LINE__;
                            $Value = $this->getentity($Source2, $Key1, $Key2, '', $Default);
                        } else {
                            $Value = $Default;
                        }
                    }
                }
            }
        }
        //echo '<br>'.__LINE__.' Key1:'.$Key1.' Key2:'.$Key2.' Returned:'.$Value.'<br>';
        return $Value;
    }
/**
* Reformat/remove html image attribute.
*
* @param string $Image text with image link
* @param string $Style type of manipulation to perform on the image string
*
* @return none
*/
    public function imagereformat($Image, $Style = '') {
        //echo '<br>'.__LINE__.htmlentities(substr($Image,0,500));
        if ($Style == '!') {    //Request to remove image?
            $Image = preg_replace("/<img[^>]+\>/i", "(<i>image removed</i>) ", $Image);
            //echo '<br>'.__LINE__.htmlentities(substr($Image,0,500));
            //$this->DebugData(strlen($Image), '---strlen($Image)---', 1);
        } elseif ($Style == '!50') {    //Request to resize image to 50px
            //$Pattern = "/(<img\s+).*?src=((\".*?\")|(\'.*?\')|([^\s]*)).*>/is";
            $Pattern = "/(<img\s+).*?src=((\".*?\")|(\'.*?\')|([^\s]*)).*?>/is";
            $Style = '<img style="max-width=50px max-height=50px" src=$2>';
            $Image = preg_replace($Pattern, $Style, $Image);
            //$this->DebugData(strlen($Image), '---strlen($Image)---', 1);
        } else {  //Default is to resize image to 150px
            if (!$Style) {
                $Style = "style='max-width: 150px;max-height=200px'";
            }
            $Pattern = "/style=[\'\"][^\"|^\']*[\'\"]/";
            $Image = preg_replace($Pattern, $Style, $Image);
            //$this->DebugData(strlen($Image), '---strlen($Image)---', 1);
        }
        //echo '<br>'.__LINE__.htmlentities(substr($Image,0,500));
        return $Image;
    }
/**
* Insert feed meta info.
*
* @param object $Sender standard
* @param string $Args   standard
*
* @return none
*/
    public function categoriescontroller_beforediscussioncontent_handler($Sender, $Args) {
        //echo '<div>L#'.__LINE__.'</div>';
        $this->discussionsController_beforeDiscussionContent_handler($Sender, $Args);
    }
/**
* Insert feed meta info.
*
* @param object $Sender standard
* @param string $Args   standard
*
* @return none
*/
    public function discussionsController_beforeDiscussionContent_handler($Sender, $Args) {
       //echo '<div>L#'.__LINE__.'</div>';
        $this->setfdpspan($Sender->EventArguments['Discussion']);
    }
/**
* Insert id to allow css manipulation.
*
* @param object $Discussion discussion object
*
* @return none
*/
    private function setfdpspan($Discussion) {
        //echo '<span>L#'.__LINE__.'</span>';
        if (($Discussion->Type != 'Feed')) {
            echo '<span id=NOFDP  > <!–– '.__LINE__.' ––> ';
            return;
        }
        $Feed = $this->getdiscussionfeed($Discussion);
        if (!$Feed) {
            echo '<span id=NOFDP  > <!–– '.__LINE__.' ––> ';
            return;
        } elseif ($Feed == -1) {
            echo '<span id=NOFDP  > <!–– ERROR Retrieving Feed  '.__LINE__.' ––> ';
            return;
        } elseif (!$Feed['Getlogo']) {
            echo '<span id=NOFDP >  <!–– '.__LINE__.' ––> ';
            return;
        }
        echo '<span id=FDP> <!-- '.__LINE__.' --> ';
        return;
    }
/**
* Insert feed meta info in the discussion list.
*
* @param object $Sender standard
*
* @return none
*/
    public function base_beforediscussioncontent_handler($Sender) {
        //echo '<span>L#'.__LINE__.'</span>';
        $this->EmbedRSSImage($Sender, 'list');
    }
/**
* Close the span with ID that includes the feed meta info.
*
* @param object $Sender standard
*
* @return none
*/
    public function base_afterdiscussioncontent_handler($Sender) {
        //echo '<div>L#'.__LINE__.'</div>';
        echo '</span> <!–– closing ID=FDP or NOFDP '.__LINE__.' ––> ';   // Close Close <span id=FDP> or  <span id=NOFDP>
    }
/**
* Insert feed meta info in a specific discussion.
*
* @param object $Sender standard
* @param string $Args   standard
*
* @return none
*/
    public function discussioncontroller_beforediscussiondisplay_handler($Sender, $Args) {
        //echo '<div>L#'.__LINE__.'</div>';
        $this->setfdpspan($Sender->EventArguments['Discussion']);
    }
/**
*  Close span used to add feed logo.
*
* @param object $Sender standard
* @param string $Args   standard
*
* @return none
*/
    public function discussioncontroller_authorinfo_handler($Sender, $Args) {
        //echo '<div>L#'.__LINE__.'</div>';
        $this->EmbedRSSImage($Sender, 'item');
        echo '</span> <!–– closing ID=FDP or NOFDP '.__LINE__.' ––> ';   // Close <span id=FDP> or  <span id=NOFDP>
    }
/**
* Get feed's logo
*
* @param string $Url      Feed's url
* @param string $Encoding Feed's encoding
*
* @return logo url
*/
    private function getlogo($Url, $Encoding = '') {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Encoding, '---Encoding---', 1);
        //$this->DebugData($Url, '---Url---', 1);
        $Imagepath = url("plugins/FeedDiscussionsPlus/design/", true);
        //$this->DebugData($Imagepath, '---Imagepath---');
        if ($Encoding == 'Twitter') {
            //return 'https://avatars.io/twitter/'  . substr($Url,1);  //Alternate method
            $RSSimage = 'https://twitter.com/' . substr($Url, 1) . '/profile_image?size=original';
            //$this->DebugData($RSSimage, '---RSSimage---', 1);
            $Urldata = $this->fetchurl($RSSimage, $Retry = true, $Follow = 1);
            //$Urldata["data"] = __line__;
            //$this->DebugData($Urldata["InternalURL"], '---Urldata["InternalURL"]---', 1);
            $Size=getimagesize($Urldata["InternalURL"]);
            if (isset($Size[0])) {
                //$this->DebugData($Urldata["InternalURL"], '---Urldata["InternalURL"]---', 1);
                return $Urldata["InternalURL"];
            }
            $Url = $Urldata["InternalURL"];
            //$Url = 'https://'.substr($Url,1).'.com';  
            //$this->DebugData($Url, '---Url---', 1);
        } elseif ($Encoding == '#Twitter') {
            return 'https://pbs.twimg.com/profile_images/588413275659972608/twWBhD7b_400x400.png';
        } elseif ($Encoding == 'Instagram') {
            return $Imagepath . "instalogo.jpg";
        }
        $Parsedurl = @parse_url($Url);
        //
        //$this->DebugData($Parsedurl, '---Parsedurl---', 1);
        if (!$Parsedurl["scheme"]) {
            $Url = $this->rebuildurl($Url, 'HTTP');   //For the purpose of the logo we don't care it it's https
            $Parsedurl = @parse_url($Url);
            //$this->DebugData($Parsedurl, '---Parsedurl---', 1);
        }
        //$this->DebugData($Url, '---Url---', 1);
        $Ignorepattern = '/(.doubeclick|.staticworld|feedproxy.google|feedburner.com|blogspot.com|.rackcdn|empty|twitter|facebook|google_plus|linkedin|instagram.com|vulture)/i'; //Ignore few logo domains
        if (preg_match($Ignorepattern, $Url)) {
            //$this->DebugData($Url, '---Url in the ignore logo list---', 1);
            if (preg_match('/(feedproxy.google|blogspot.com)/i', $Url)) {
                $Logo = $Imagepath . "feedburner.png";
            } elseif (preg_match('/(feedburner.com)/i', $Url)) {
                $Logo = $Imagepath . "feedburner.png";
            } elseif (preg_match('/(twitter.com)/i', $Url)) {
                $Logo = $Imagepath . "twitter.png";
            } elseif (preg_match('/(instagram.com)/i', $Url)) {
                $Logo = $Imagepath . "instalogo.jpg";
            } else {
                $Logo = $Imagepath . "noimage.png";
            }
            //$this->DebugData($Logo, '---Returning logo---', 1);
            return $Logo;
        }
        //$this->DebugData($Domain, '---Domain---', 1);
        if ($Parsedurl["host"]) {
            $Logo = 'https://logo.clearbit.com/' . $Parsedurl["host"] . '?size=46';
            $Size=getimagesize($Logo);
            if (isset($Size[0])) {
            //if ($this->iswebUrl($Logo)) {
                //$this->DebugData($Logo, '---logo---', 1);
                if ($Size[0]>$Size[1]) {
                    $Ratio = $Size[0] / 40;
                    $Smallside = $Size[1] / $Ratio;
                } else {
                    $Ratio = $Size[1] / 40;
                    $Smallside = $Size[0] / $Ratio;
                }
                //$this->DebugData($Size[0], '---Size[0]---', 1);
                //$this->DebugData($Size[1], '---Size[1]---', 1);
                //$this->DebugData($Ratio, '---Ratio---', 1);
                //$this->DebugData($Smallside, '---Smallside---', 1);
                if ($Smallside < 5)  {
                    $Logo = 'https://www.google.com/s2/favicons?domain_url='.$Parsedurl["host"];
                    //$this->DebugData($Logo, '---logo---', 1);
                } else {
                    //$Logo = $Logo . '?size=46';
                    //$this->DebugData($Logo, '---logo---', 1);
                }
            } else {
                $Logo = 'https://www.google.com/s2/favicons?domain_url='.$Parsedurl["host"];
            }
        } else {
            $Logo = '';
        }
        //$this->DebugData($Logo, '---Logo---', 1);
        //die(0);
        return $Logo;
    }
/**
* Check if url is accessible
*
* @param string $Url Url to check
*
* @return string
*/
    private function isweburl($Url) {
        return @file_get_contents(trim($Url), 0, null, 0, 1);  //Note that we only fetch a ittle snip
    }
/**
* Return url data
*
* @param string $Url    Url to fetch
* @param string $Retry  Request to retry as https
* @param int    $Follow Number of times to refetch (redirects) content from a set of meta referrers tage (limited support)
*
* @return array of data and possibly error message
*/
    private function fetchurl($Url, $Retry = true, $Follow = 0) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Url, '---Url---', 1);
        $Response['InternalURL'] = $Url;
        $Response['Error'] = '';
        $Response['Compressed'] = '';
        $Response['Scheme'] = '';
        $Response['Redirect'] = false;
        $Proxy = new ProxyRequest();
        $Pagedata = $Proxy->Request(array(
            'URL' => $Url, 'Debug' => false, 'SSLNoVerify' => true
        ));
        $ResponseHeaders = $Proxy->ResponseHeaders;
        $RHtext = reset(array_keys($ResponseHeaders));
        $RHlocation = $ResponseHeaders["Location"];
        //$this->DebugData($ResponseHeaders, '---ResponseHeaders---', 1);
        //$this->DebugData($RHlocation, '---RHlocation---', 1);
        //$this->DebugData($RHtext, '---RHtext---', 1);
        $Status = $Proxy->ResponseStatus;
        //$this->DebugData($Proxy, '---Proxy---', 1);
        //$this->DebugData($Status, '---Status---', 1);
        $Pagedata = $Proxy->ResponseBody;
        if (!$RHtext) {        //In case of error
            $RHtext = $Status;
        }
        switch ($Status) {
            case 200:       //What we expect
                if (isset($ResponseHeaders["Content-Encoding"])) {
                    $Zipped = $ResponseHeaders["Content-Encoding"];
                    if ($Zipped == 'gzip') {              //Compressed site?
                        $Response['Compressed'] = $Zipped;
                        $Pagedata = gzdecode(Pagedata);    //Uncompress it
                    }
                }
                //$Response["data"] = $Pagedata;
                $Response["data"] = $this->removescripts($Pagedata);  // return the data without Java scripts
                //
                if ($RHlocation) {
                    $InternalURL = $RHlocation;
                    if (empty($InternalURL)) {
                        $Response['InternalURL'] = $Url;
                    } else {
                        $Response['InternalURL'] = $InternalURL;
                        //$this->DebugData(strtolower($this->rebuildurl($Response['InternalURL'])), '---lowercase rebuild Response[InternalURL]---', 1);
                        //$this->DebugData(strtolower($this->rebuildurl($Url)), '---lowercase rebuild URL---', 1);
                        if (strtolower($this->rebuildurl($Response['InternalURL'])) !=
                            strtolower($this->rebuildurl($Url))) {
                            $Response["Redirect"] = true;
                        }
                        //$this->DebugData($Response["Redirect"], '---Response["Redirect"]---', 1);
                    }
                    $Response["Scheme"] = parse_url($Response['InternalURL'], PHP_URL_SCHEME);
                    if ($Response["Redirect"]) {
                        if ($Follow < 1) {      //No more follows?
                            return $Response;
                        }
                        $Redirect = $Response['InternalURL'];
                        //echo '<br>'.__LINE__.'Redirecting from:'.$Url.' to:'.$Redirect.' Follow:'.$Follow;
                        $Response = $this->fetchurl($Redirect, true, ($Follow-1));   //One less follow/redirects
                        return $Response;
                    }
                } elseif (!$Scheme) {
                    $Response["Scheme"] = parse_url($Response['Url'], PHP_URL_SCHEME);                    
                }
                //$this->DebugData($Scheme, '---Scheme---', 1);
                //var_dump($Proxy);
                //$this->DebugData(htmlspecialchars(substr($Response["data"],0,100)), '---Response[data] 0:100---', 1);
                if ($Follow < 1) {      //No more follows?
                    return $Response;
                }
                //Handle webpage embedded follow requests (if any).
                /* Examples:
                    <meta name="referrer" content="always"><noscript><META http-equiv="refresh" content="0;URL=http://for.tn/2leJLTJ"></noscript><title>http://for.tn/2leJLTJ</title></head><script>window.opener = null; location.replace("http:\/\/for.tn\/2leJLTJ")</script>
                  and
                    <META http-equiv="refresh" content="0;URL=http://bitly.com/2ykaWkG">
                  and 
                    <noscript><meta content="0; URL=https://mobile.twitter.com/i/nojs_router?path=%2Fhp%2Fprofile_image%3Fsize%3Doriginal" http-equiv="refresh" /></noscript>
                */
                $Redirect = $this->getbetweentexts($Response["data"], '<META http-equiv="refresh" content="0;URL=', '"');
                //$this->DebugData($Response["InternalURL"], '---Response["InternalURL"]---', 1);
                //$this->DebugData($ResponseHeaders["location"], '---ResponseHeaders["location"]---', 1);
                //if($Follow > 3) die(0);
                
                if (!$Redirect) {
                    $Redirect = $this->getbetweentexts($Response["data"], '<noscript><meta content="0; URL=', '" http-equiv="refresh"');
                    if ($Redirect) {
                        $Redirect = explode('"', $Redirect)[0];
                        //$this->DebugData($Redirect, '---Redirect---', 1);
                    }
                }
                if ($Redirect) {
                    //echo '<br>'.__LINE__.'Redirecting from:'.$Url.' to:'.$Redirect.' Follow:'.$Follow;
                    $Response = $this->fetchurl($Redirect, true, ($Follow-1));   //One less refollow/redirects
                    //$this->DebugData(substr($Response["data"], 0, 500),           '---$Response["data"]0:500---', 1);
                    $Response["Redirect"] = true;               //Simulate redirections
                }
                return $Response;
            case 404:
                $Response['Error'] = $this->setmsg(' Error ' . $RHtext . ': The specified url was not found '. $Url);
                return $Response;
            case 400:
            case 500:
                $Url =  @parse_url($Url, PHP_URL_HOST).@parse_url($Url, PHP_URL_PATH);
                $Response['Error'] = $this->setmsg(' Error ' . $RHtext . ': The server url was not accessible at this time: '. $Url);
                return $Response;
            default:
                $Statustext = $Proxy->ResponseBody;
                //$this->DebugData($Statustext, '---Statustext---', 1);
                if ($Retry) {
                    if (0 === strpos($Url, 'https')) {
                        $Url = $this->rebuildurl($Url, 'https');
                        return $this->fetchurl($Url, false); //Do not retry again
                    }
                    if (0 === strpos($Statustext, 'SSL certificate problem:') |
                       (0 === strpos($Statustext, 'error:14077458:SSL routines:SSL23_GET_SERVER_HELLO:tlsv1 unrecognized name'))) {
                        $Url = $this->rebuildurl($Url, 'https');
                        return $this->fetchurl($Url, false); //Do not retry again
                    }
                }
        }
        $Response['Error'] = $this->setmsg('Error ' . $Status . $RHtext . ': '. $Statustext);
        //die(0);
        return $Response;
    }
/**
* Check if url is accessible
*
* @param string $Url       Url to reformat
* @param string $Setscheme Scheme to use in reformatted utl
*
* @return reformatted url
*/
    private function rebuildurl($Url, $Setscheme = '') {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Url, '---Url---', 1);
        //$this->DebugData(parse_url($Url), '---parse_url(Url)---', 1);
        $Url = trim($Url);
        if (substr($Url, 0, 1) == '@' | substr($Url, 0, 1) == '#'| substr($Url, 0, 1) == '!') {
            return $Url;
        }
        $Domain = @parse_url($Url, PHP_URL_HOST);
        $Path   = @parse_url($Url, PHP_URL_PATH);
        $Query   = @parse_url($Url, PHP_URL_QUERY);
        $Scheme   = @parse_url($Url, PHP_URL_SCHEME);
        //$this->DebugData($Scheme, '---Scheme---', 1);
        if (!$Scheme) {
        }
        if ($Setscheme == -1) {
            if ($Scheme == "https") {
               $Setscheme =  "http";
            } else {
               $Setscheme =  "https";
            }                
        } 
        if ($Setscheme) {
            $Url = $Setscheme . '://' . $Domain . $Path;
        } else {
            $Url = $Domain . $Path;
        }
        if ($Query) {
            $Url = $Url . '?' . $Query;
        }
        //$this->DebugData($Url, '---Url---', 1);
        return $Url;
    }
/**
* Try to discover feed for a url
*
* @param object $Sender    Standard
* @param string $Url       Url for which to check for referral to a feed url
* @param string $Websource Web source for which to check for referral to a feed url
*
* @return string url of feed referred to by the passed web source
*/
    private function discoverfeed($Sender, $Url, $Websource) {
        //Check for feed url within the web page itself:
        //<link rel="alternate" type="application/rss+xml" title="whateve.r." href="url of feed" />
        //<link rel="alternate" type="application/rss+xml" title="blah" href="http://feeds.feedburner.com/something">
        //<link rel="search" type="application/opensearchdescription+xml" href="https://www.youtube.com/opensearch?locale=en_US" title="YouTube Video Search">
        $Headtag = $this->getbetweentags($Websource, 'head');
        //echo '<br>'.__LINE__.': '.substr(htmlspecialchars($Headtag),0,1500);
        $Results = $this->getwithintag($Headtag, 'link');
        //$this->DebugData($Results, '---Results---', 1);
        $Foundlinktag = false;
        $Foundsearchtag = false;
        foreach ($Results as $Link) {
            preg_match_all('/(\w+)\s*=\s*(?|"([^"]*)"|\'([^\']*)\')/', $Link, $Sets, PREG_SET_ORDER);
            //$this->DebugData($Sets, '---Sets---', 1);
            foreach ($Sets as $Keywords) {
                //$this->DebugData($Keywords, '---Keywords---', 1);
                //$this->DebugData($Foundsearchtag, '---Foundsearchtag---', 1);
                //$this->DebugData(strtolower($Keywords[1]), '---strtolower(Keywords[1])---', 1);
                if (strtolower($Keywords[1])== 'rel') {
                    if (strtolower($Keywords[2]) == 'alternate') {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        $Foundlinktag = true;
                        $Foundsearchtag = false;
                    } elseif (strtolower($Keywords[2]) == 'search') {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        $Foundsearchtag = true;
                    }
                } elseif ($Foundlinktag && strtolower($Keywords[1])== 'href') {
                    //$this->DebugData($Keywords, '---Keywords---', 1);
                    if (substr($Keywords[2], 0, 1) == '/') {              //relative url?
                        $Keywords[2] = $Url . $Keywords[2];
                        //$this->DebugData($Keywords[2], '---Keywords[2]---', 1);
                    }
                    if (stripos($Keywords[2], 'feedburner.com')) {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        return trim($Keywords[2]).'?format=xml';
                    } else {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        return trim($Keywords[2]);
                    }
                } elseif ($Foundsearchtag && (strtolower($Keywords[1])== 'href')) {
                    //$this->DebugData($Keywords, '---Keywords---', 1);
                    if (stripos($Keywords[2], "youtube.com")) {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        $i = stripos($Websource, '"rssUrl":"https://www.youtube.com/feeds/videos.xml?channel_id=');
                        //$this->DebugData($i, '---i---', 1);
                        if ($i) {
                            $j = stripos(substr($Websource, $i+18), ',');
                            if ($j>20) {
                                $Channelurl = substr($Websource, $i+18, ($j-1));
                                //$this->DebugData($Channelurl, '---Channelurl---', 1);
                                //expected format: https://www.youtube.com/feeds/videos.xml?channel_id=12345678901234567890123456
                                return $Channelurl;
                            }
                        }
                    } else {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        //ignoring
                    }
                }
            }
        }
        // Standardized method failed, try implied method.
        //
        //  Handle sites where feed tags are inserted at the end of the url
        //
        $Inserts = array('tumblr.com' => '/rss',
                         'blogspot.com' => '/feeds/posts/default',
                        'vanillaforums.com' => '/feed.rss'   //Just for demo
                        );
        foreach ($Inserts as $Search => $Insert) {
            //$this->DebugData($Search, '---Search---', 1);
            $i = stripos($Url, $Search);
            //$this->DebugData($Url, '---Url---', 1);
            //$this->DebugData($i, '---i---', 1);
            if ($i !== false) {
                    $Url = $Url . $Insert;
                    //$this->DebugData($Url, '---Url---', 1);
                    return $Url;
                return trim(substr_replace($Url, $Search.$Insert, $i, strlen($Search)));
                //$this->DebugData($Replace, '---Replace---', 1);
                return trim($Replace);
            }
        }
        //
        //  Handle sites where feed tags are inserted in the middle of the url
        $Inserts = array('medium.com' => "/feed",
                        'tumblr.com' => '/rss',
                        'vanillaforums.com/discussions/' => 'feed.rss'   //Just for demo
                        );
        foreach ($Inserts as $Search => $Insert) {
            //$this->DebugData($Search, '---Search---', 1);
            $i = strrpos($Url, $Search);
            //$this->DebugData($Url, '---Url---', 1);
            //$this->DebugData($i, '---i---', 1);
            if ($i !== false) {
                $j = strlen($Search);
                //$this->DebugData($j, '---j---', 1);
                //$this->DebugData(strlen($Url), '---strlen(Url)---', 1);
                //$this->DebugData($Url, '---Url---', 1);
                $Replace = substr_replace($Url, $Search.$Insert, $i, $j);
                //$this->DebugData($Replace, '---Replace---', 1);
                return trim($Replace);
            }
        }
        //
        return '';
    }
/**
* Redirect to a url
*
* @param object $Sender Standard
* @param string $Url    Plugin url to display
* @param string $Msg    Optional message to display on the displayed url
*
* @return none
*/
    private function redirecttourl($Sender, $Url, $Msg) {
        if ($Msg) {
            $this->postmsg($Sender, $Msg, false, false, false, true);
            //postmsg($Sender, $Msg, $Inform = false, $Addline = true, $Adderror = false, $Stash = false) {
        }
        if (stripos($Url, "_DIE_") !==false) {
            $this->DebugData($Msg, '---Msg---', 1);
            die(0);
        }
        Redirect($Url);
    }
/**
* Render a view, possibly with a message
*
* @param object $Sender   Standard
* @param string $Viewname View to display
* @param string $Msg      Optional message to display on the displayed url
*
* @return none
*/
    private function renderview($Sender, $Viewname, $Msg) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Msg, '---Msg---', 1);
        $Copystate = $this->setcopypaste($Sender);  //Establish access to the copy/paste bin
        $this->postmsg($Sender, $Msg, false, false, false, true);//Post messages (if any)
        //
        // New method (only in 2.5+)
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Sender->render($Viewname, '', 'plugins/FeedDiscussionsPlus');
        } else {
            $Viewname .= '.php';
            $View = $this->getView($Viewname);
            $Sender->render($View);
        }
        //
    }
/**
* Return to parent form regardless of the child popup state
*
* @param object $Sender   Standard
* @param string $Msg      message to display
*
* @return none
*/
    private function returntoparent($Sender, $Msg) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Msg, '---Msg---', 1);
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Popustate = '';
        } else {
            $Popustate = c('Plugins.FeedDiscussionsPlus.Popup', '');
        }
        if ($Popustate == 'Popup') {
            $Sender->jsonTarget('.InformMessages', '', 'Remove');
            $Sender->jsonTarget('.Overlay', '', 'Remove');
            $Sender->jsonTarget('#Popup', '', 'Remove');
            $this->setjsonmsg($Sender, $this->setmsg($Msg));
            $Sender->Render('Blank', 'Utility', 'Dashboard');  //force redraw of parent window
        } elseif ($Popustate  == 'Popdown') {
            $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds/Cancel/?'.
                    __LINE__, $this->setmsg($Msg));
            return;
        } else {
            $this->redirecttourl($Sender, 'plugin/feeddiscussionsplus/listfeeds/Return/?'.
                    __LINE__, $this->setmsg($Msg));
            return;
        }
    }
/**
* Set message on parent screen via jsontarget
*
* @param object $Sender Standard
* @param string $Msg    message to display on parent
*
* @return none
*/
    private function setjsonmsg($Sender, $Msg) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Msg, '---Msg---', 1);
        $Sender->JsonTarget("#popmsg", '<div id=popmsg class=ffqmsg  style="display:table;">'.$Msg.'</div>', 'ReplaceWith');
    }
/**
* Reformat a message to be displayed on the next page
*
* @param string $Msg        Message to display on the displayed url
* @param binary $Addline    request to add line number of the source message
* @param binary $Addversion request to prefix message with plugin version number
*
* @return reformatted message
*/
    private function setmsg($Msg, $Addline = true, $Addversion = false) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Msg, '---Msg---', 1);
        $Prefix = strstr(trim($Msg).' ', ' ', true);
        //$this->DebugData($Prefix, '---Prefix---', 1);
        if (preg_match("#^[/.0-9]+$#", $Prefix)) {  //Numeric prefix?
            $Suffix =  strstr($Msg.' ', ' ');
            //$this->DebugData($Suffix, '---Suffix---', 1);
            //$Msg = $Prefix . ' <b>' . $Suffix . '</b>';
            $Msg = $Prefix . ' ' . $Suffix;
            if ($Addline) {
                $Msg =  debug_backtrace()[0]['line'] . '.' . $Msg;
            }
        } else {
            $Msg ='<b>' . $Msg . '</b>';
            if ($Addline) {
                $Msg = debug_backtrace()[0]['line'] . ' ' . $Msg;
            }
        }
        //$this->DebugData($Msg, '---Msg---', 1);                    
        if ($Addversion) {
            $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
            $Msg = $Plugininfo["Version"] . ':' . $Msg;
        }
        return $Msg;
    }
/**
* Que a message to be displayed on the next page
*
* @param object $Sender   Standard
* @param string $Msg      Message to display on the displayed url
* @param binary $Inform   request to issue informmessage service
* @param binary $Addline  request to add line number of the source message
* @param binary $Adderror request to post message via form AddError
* @param binary $Stash    request to stash the message for later viewing
*
* @return none
*/
    private function postmsg($Sender, $Msg, $Inform = false, $Addline = true, $Adderror = false, $Stash = false) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Msg, '---Msg---', 1);
        if (empty($Msg)) {
            //$Sender->SetData('Qmsg', $Msg);
            //$this->SetStash('', 'Msg', false);
            $Qmsg = $this->getmsg('', 'get:'.__FUNCTION__.__LINE__);
            //$this->DebugData($Qmsg, '---Qmsg---', 1);
            return;
        }
        if ($Addline) {
            $Msg = debug_backtrace()[0]['line'] . '.' . $Msg;
        }
        $Qmsg = $this->getmsg('', 'get:'.__FUNCTION__.__LINE__);
        //$this->DebugData($Qmsg, '---Qmsg---', 1);
        if ($Qmsg) {
            $Msg = $Qmsg . '<br>' . $Msg;
        }
        //$this->DebugData($Msg, '---Msg---', 1);
        $Previousmsg = $this->getmsg($Msg, 'get:'.__FUNCTION__.__LINE__);
        //$this->DebugData($Previousmsg, '---Previousmsg---', 1);
        $Previousmsg = $this->getmsg('', 'get:'.__FUNCTION__.__LINE__);
        //$this->DebugData($Previousmsg, '---Previousmsg---', 1);
        if ($Inform) {
            //$Sender->InformMessage($Msg);
            echo '<h1>:'.$Msg.'</h1>';
        }
        if ($Adderror) {
            $Sender->Form->AddError($Msg);
            //$this->DebugData($Msg, '---Msg---', 1);
        } else {
            //$this->DebugData($Msg, '---Msg---', 1);
            if ($Stash) {
                //$this->SetStash($Msg, 'Msg', false); //false: use the cookie method     
                $Qmsg = $this->getmsg($Msg, 'get:'.__FUNCTION__.__LINE__);
                //$this->DebugData($Qmsg, '---Qmsg---', 1);
            }
        }
    }
/**
* Fetch form values into feed array
*
* @param object $FormPostValues Standard
*
* @return array - feed definition entry
*/
    private function getfeedfromform($FormPostValues) {
        //$this->DebugData('','',true,true);
        return array(
            'FeedURL'   => $FormPostValues["FeedURL"],
            'Historical'   => $FormPostValues["Historical"],
            'Refresh'     => $FormPostValues["Refresh"],
            'Category'    => $FormPostValues["Category"],
            'Active'       => $FormPostValues["Active"],
            'OrFilter'       => strip_tags($FormPostValues["OrFilter"]),
            'AndFilter'       => strip_tags($FormPostValues["AndFilter"]),
            'Filterbody'       => $FormPostValues["Filterbody"],
            'Minwords'       => $FormPostValues["Minwords"],
            'Maxitems'       => $FormPostValues["Maxitems"],
            'Getlogo'       => $FormPostValues["Getlogo"],
            'Noimage'       => $FormPostValues["Noimage"],
            'Activehours'       => $FormPostValues["Activehours"]
          );
    }
/**
* Set defaults for feed array
*
*
* @return array - feed definition entry
*/
    private function feeddefaults() {
        //$this->DebugData('','',true,true);
        // Change default below
        return array(
                    'Historical'  => 1,
                    'Refresh'     => '3d',
                    'Category'    => -1,
                    'OrFilter'    => '',
                    'AndFilter'   => '',
                    'Filterbody'  => false,
                    'Active'      => 1,
                    'Activehours' => '05-22',
                    'Minwords'    => '10',
                    'Maxitems'    => '20',
                    'LastImport'  => "never",
                    'NextImport'  => null,
                    'Getlogo'     => true,
                    'Noimage'     => false,
                    'Added'       => date('Y-m-d H:i:s'),
              );
    }
    /**
* Calculate next import due date for a feed
*
* @param object $Sender     Standard
* @param string $Mode       Either "Current" to get current import date or "Next" to get next import date
* @param string $Refresh    Feed refresh frequency
* @param string $LastImport Date of last import
*
* @return string -  Next Import due date
*/
    private function getimportdate($Sender, $Mode = "Current", $Refresh = '1w', $LastImport = null) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Refresh, '---Refresh---', 1);
        //$this->DebugData($LastImport, '---LastImport---', 1);
        $DelayStr = strtr(
            $Refresh,
            array
                (
                   "1m"  => "+1 minutes",
                   "5m"  => "+5 minutes",
                   "30m" => "+30 minutes",
                   "1h"  => "+1 hour",
                   "1d"  => "+1 days",
                   "3d"  => "+3 days",
                   "1w"  => "+1 weeks",
                   "2w"  => "+2 weeks",
                   "3w"  => "+3 weeks",
                   "4w"  => "+4 weeks",
                   "Monday"  => "Monday",
                   "Tuesday"  => "Tuesday",
                   "Wednesday"  => "Wednesday",
                   "Thursday"  => "Thursday",
                   "Friday"  => "Friday",
                   "Saturday"  => "Saturday",
                   "Sunday"  => "Sunday",
                   "Manually"  => '',
                )
        );
        //$this->postmsg($Sender, __LINE__.' LastImport:'.$LastImport);
        if ($DelayStr == '') {
            return '';
        }
        if ($LastImport == '' | $LastImport == 'never') {
            $Base = time();
        } else {
            $Base = strtotime($LastImport);
            if ($Mode == 'Next' && substr($DelayStr, 0, 1) != '+') {
                $DelayStr = 'next '.$DelayStr;
            }
        }
        $NextDueDate = date('Y-m-d H:i:s', strtotime($DelayStr, $Base));
        //$this->postmsg($Sender, __LINE__.' DelayStr:'.$DelayStr.' NextDueDate:'.$NextDueDate);
        return $NextDueDate;
    }
/**
* Fetch RSS/Atom feed from the supplied Url
*
* @param object $Sender      Standard
* @param string $Url         The feed Url
* @param bool   $Data        Request to fetch feed data into its array
* @param bool   $SSLNoVerify Request to bypass SSL verification
* @param bool   $AutoImport  Autoimport request
*
* @return Array - Feed information array
*/
    private function getfeedrss($Sender, $Url, $Data = false, $SSLNoVerify = false, $AutoImport = false) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Url, '---Url---', 1);
        //$this->DebugData($SSLNoVerify, '---SSLNoVerify---', 1);
        //$this->DebugData($AutoImport, '---AutoImport---', 1);
        //    Set response defaults
        $Url = trim($Url);
        $Response = array(
            'URL' => $Url,
            'FeedURL' => $Url,
            'InternalURL' => $Url,
            'SuggestedURL' => '',
            'Action' => '',
            'Error' => '',
            'Encoding' => 'N/A',
            'Updated' => '',
            'Feedtitle' => '',
            'RSSimage' => '',
            'Scheme' => '',
            'RSSdata' => ''
        );
        $TwitterID = '';
        $Hashtag = '';
        $Encoding = '';
        //  Convert TwitterID to https://twitrss.me/twitter_user_to_rss/?user=TwitterID
        if (Substr($Url, 0, 1) =="@") {
            $TwitterID = substr($Url, 1);
            //$this->DebugData($TwitterID, '---TwitterID---', 1);
            if (preg_match('/^[A-Za-z0-9_]{1,15}$/', $TwitterID)) {
                $Response["URL"] = strtolower($Url);
                $Url = 'twitrss.me/twitter_user_to_rss/?user=@' . $TwitterID;
                $Response['InternalURL'] = $Url;
                $Encoding = 'Twitter';
                $Response["RSSimage"] = $this->Getlogo($Response['URL'], $Encoding);
                $Response["Scheme"] = "@";
            } else {
                $Response['Error'] = $this->setmsg(" invalid Twitter ID: ".$Url);
                return $Response;
            }
        //  Convert Twitter hashtag to https://queryfeed.net/tw?q=Hashtag
        } elseif (Substr($Url, 0, 1) == "#") {
            $Hashtag = substr($Url, 1);
            //$this->DebugData($Hashtag, '---Hashtag---', 1);
            if (preg_match('/^[A-Za-z0-9_]{1,15}$/', $Hashtag)) {
                $Response['URL'] = strtolower($Url);
                $Url = trim('queryfeed.net/tw?q=' . $Hashtag);
                $Response['InternalURL'] = $Url;
                $Encoding = '#Twitter';
                $Response['RSSimage'] = $this->Getlogo($Response['URL'], $Encoding);
                $Response["Scheme"] = "#";
            } else {
                $Response['Error'] = $this->setmsg(" invalid Twitter hashtag: ".$Url);
                return $Response;
            }
        } elseif (Substr($Url, 0, 1) == "!") {
            //Convert !instagram-ID to https://www.instagram.com/ID/?__a=1
            $InstaID = substr($Url, 1);
            $Response["URL"] = strtolower($Url);
            $Url = 'https://www.instagram.com/' . $InstaID . "/?__a=1";
            $Response['InternalURL'] = $Url;
            $Encoding = 'Instagram';
            $Response["Scheme"] = "!";
        /*} elseif (Substr($Url, 0, 1) == "https://www.instagram.com") {
            $Response['Error'] = "?";
            return $Response;
        */
        } elseif (trim($Url) == "?") {
            $Response['Error'] = "?";
            return $Response;
        }
        //  Fetch the web page
        $Webpage = $this->fetchurl($Url, true);  //With retry option
        //$this->DebugData(htmlspecialchars(substr($Webpage["data"],0,600)), '---Webpage 0:600---', 1);
        if ($Webpage["Error"]) {
            $Response['Error'] = $Webpage["Error"];
            return $Response;
        }
        $FeedRSS = $Webpage["data"];
        $Response['Compressed'] = $Webpage["Compressed"];
        $Response['InternalURL'] = $Webpage["InternalURL"];
        $Response["Redirect"] = $Webpage["Redirect"];
        if ($Response["Scheme"] == "") {
            $Response["Scheme"] = $Webpage["Scheme"];
        }
        //$this->DebugData($Response["Scheme"], '---Response["Scheme"]---', 1);
        //$URL = $Webpage["InternalURL"];
        //
        if (!$Encoding) {
            $Encoding = $this->getencoding($Sender, $FeedRSS);
        }
        //$this->DebugData($Encoding, '---Encoding---', 1);
        $Response['Encoding'] = $Encoding;
        switch ($Encoding) {
            case 'Atom':
            case 'RSS':     //bypass simplexml parsing error
                $FeedRSS = strtr($FeedRSS, 
                            array("<content:encoded>" => '<description2>', 
                                  "</content:encoded>" => "</description2>", 
                                  'class="SC_TBlock">loading...' => ">"
                                  ));
                //$this->DebugData(htmlspecialchars(substr($FeedRSS,0,2500)), "---FeedRSS(0:2500)---", 1);
            case 'Twitter':
            case '#Twitter':
                break;
            case 'Instagram':
                break;
            case 'BadURL':
                $Response['Error'] = $this->setmsg(" Url not found or is not a feed.");
                return $Response;
            case 'HTML':
                $Response['Error'] = $this->setmsg("URL points to a regular web page, not a feed.");
                //Check for feed url within the web age itself:
                //<link rel="alternate" type="application/rss+xml" title="whateve.r." href="url of feed" />
                $SuggestedURL = trim($this->discoverfeed($Sender, $Url, $FeedRSS));
                //$this->DebugData($Response["Redirect"], '---Response["Redirect"]---', 1);
                //$this->DebugData($SuggestedURL, "---SuggestedURL---", 1);
                if (strtolower($SuggestedURL) == strtolower($Response["InternalURL"]) |
                    strtolower($SuggestedURL) == strtolower($Response["URL"])) {
                    //$this->DebugData($SuggestedURL, "---SuggestedURL---", 1);
                    $SuggestedURL = '';
                    if ($Response["InternalURL"] != $Response["URL"]) {
                        //$this->DebugData($Response, '---Response---', 1);
                    }
                }
                if ($SuggestedURL) {        //Discovered implied feed?
                    $Response['Error'] = "Consider the suggested feed instead:".$SuggestedURL;
                    //$this->DebugData($Feedurl, '---Feedurl---', 1);
                    $Response['SuggestedURL'] = $SuggestedURL;
                }
                return $Response;
                break;
            case 'Youtube':
                // Replace all "<media:" tags with "<media" to bypass simplexml parsing error
                $FeedRSS = strtr($FeedRSS, array('media:description' => 'mediadescription', 'media:group' => 'mediagroup'));
                break;
            case 'RDFprism':
                //$this->DebugData(htmlspecialchars(substr($FeedRSS,0,600)), '---RSS 0:600---', 1);
                // Replace all "<prism:" "<dc:" tags with "<prism:" "<dc" to bypass simplexml parsing error
                $FeedRSS = strtr($FeedRSS, array('<prism:' => '<prism', "<dc:" => "<dc", '</prism:' => '</prism', "</dc:" => "</dc"));
                break;
            case 'RDF':
                // Replace all "<prism:" tags with "<prism:" to bypass simplexml parsing error
                $FeedRSS = strtr($FeedRSS, array('prism:' => 'prism'));
                break;
            case 'New':
                // Place code for new encoding format;
                break;
            default:
                $Response['Error'] = $this->setmsg(" URL content is not a recognized feed (code ".$Encoding.")");
                if ($AutoImport) {
                    echo '<br>'.$Response['Error'];
                    echo '<br> URL first 1500 characters:<b>'.htmlspecialchars(substr($FeedRSS, 0, 1500)).'</b><br>';
                }
                return $Response;
        }
        //
        if ($InstaID) {
            $Instadata = $this->getinstafeed($FeedRSS, $InstaID, $Response['InternalURL'], $Data);
            $FeedRSS = $Instadata["FeedRSS"];
            if ($Instadata["RSSimage"]) {
                $Response["RSSimage"] = $Instadata["RSSimage"];
            } else {                
                $Response["RSSimage"] = $this->Getlogo($Response['URL'], $Encoding);
            }
            //$Response["RSSimage"] = '';
            //$Response['Error'] = $this->setmsg("Instagram not supported.");
            //return $Response;
        }
        libxml_use_internal_errors(true);
        $RSSdata = simplexml_load_string($FeedRSS, 'SimpleXMLElement', LIBXML_NOCDATA );
        
        //$RSSdata = simplexml_load_string($FeedRSS, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NOBLANKS );
        //$this->DebugData(gettype($RSSdata), '---gettype(/$RSSdata)---', 1);
        if ($RSSdata === false) {
            //$RSSdata = (array)$FeedRSS;        //Aggressive mode...
            //$this->DebugData($RSSdata, '---RSSdata---', 1);
            if ($AutoImport) {
                echo "<br>".__LINE__."Failed loading XML ";
                $Response['Error'] = $this->setmsg(" URL content is not in a valid format feed");
                foreach (libxml_get_errors() as $error) {
                    echo "<br>".__LINE__.' ', $error->message;
                    var_dump ($error);
                }
                $this->DebugData($FeedRSS, '---FeedRSS---', 1);
                return $Response;
            }
        }
        //
        if ($Data | $TwitterID | $Hashtag | $InstaID) {
            //$this->DebugData(htmlspecialchars(substr($FeedRSS,0,500)), '---FeedRSS(0:500)---', 1);
            $Response['RSSdata']  = $RSSdata;           //Return data, not just the metadata
            //$Response['RSSdata']  = $Webpage["data"];
        }
        //$this->DebugData($Encoding, '---Encoding---', 1);
        //$this->DebugData($RSSdata, '---RSSdata---', 1);
        //$this->DebugData(htmlspecialchars(substr($RSSdata,0,1500)), '---RSSdata(0:1500)---', 1);
        //
        switch ($Encoding) {
            case 'Atom':
                $Updated = (string)$this->getentity($RSSdata, 'updated', 'channel.lastBuildDate', $FeedRSS, '');
                $Feedtitle = (string)$this->getentity($RSSdata, 'title', 'channel.title', $FeedRSS, '');
                break;
            case 'Instagram':
                $Updated = (string)$this->getentity($RSSdata, 'updated', 'channel.lastBuildDate', $FeedRSS, '');
                $Feedtitle = (string)$this->getentity($RSSdata, 'title', 'channel.title', $FeedRSS, '');
                break;
            case 'RSS':
                $Updated = (string)valr('channel.lastBuildDate', $RSSdata, $this->getbetweentags($FeedRSS, 'lastBuildDate'));
                $Feedtitle = (string)$this->getentity($RSSdata, 'title', 'channel.title', $FeedRSS, '');
                break;
            case 'RDFprism':
                $Updated = (string)valr('channel.prismcoverDisplayDate', $RSSdata, valr('item.dcdate', $RSSdata, ''));
                $Feedtitle = (string)valr('channel.title', $RSSdata, '');
                break;
            case 'RDF':
                $Updated = (string)val('updated', $RSSdata, '');
                $Feedtitle = (string)valr('channel.title', $RSSdata, '');
                break;
            case 'Youtube':
                $Updated = (string)val('published', $RSSdata, '');
                $Feedtitle = (string)valr('title', $RSSdata, '');
                break;
            case 'Twitter':
                $Item = $this->getbetweentags($FeedRSS, 'item');  // Validate twitter input
                if (empty($Item)) {
                    $Response['Error'] = $this->setmsg(' @'.$TwitterID . ' does not exist or has no feed stream');
                    return $Response;
                }
                $Feedtitle = "@" . $TwitterID . " Twitter feed";
                break;
            case '#Twitter':
                $Item = $this->getbetweentags($FeedRSS, 'item');  // Validate twitter input
                if (empty($Item)) {
                    $Response['Error'] = $this->setmsg(' #'.$Hashtag . ' does not exist or has no feed stream');
                    return $Response;
                }
                $Feedtitle = "#" . $Hashtag . " Twitter search";
                break;
            case 'New':     //Enter appropriate keys to read this encoding
                $Updated = (string)val('updated', $RSSdata, '');
                $Feedtitle = (string)$this->getentity($RSSdata, 'title', 'channel.title', $FeedRSS, '');
                break;
            default:
                $Response['Error'] = $this->setmsg(' Unexpected feed encoding format:'.$Encoding);
                return $Response;
        }
        //
        //$this->DebugData($Updated, '---Updated---', 1);
        //$this->DebugData($Feedtitle, '---Feedtitle---', 1);
        //$this->DebugData($Channel, '---Channel---', 1);
        //$date = new DateTime($Updated);
        //$Updated = $date->format("Y-m-d H:i:s");
        //$this->DebugData($Updated, '---Updated---', 1);
        $Response['Updated'] =  '';//$Updated;
        $Response['Feedtitle'] =  $Feedtitle;
        //
        //
        if ((!$Response['RSSimage']) & (c('Plugins.FeedDiscussionsPlus.GetLogo', false))) {
            //$this->postmsg($Sender, __LINE__.'Image:'.$Response['RSSimage']);
            if ($Encoding == 'Twitter' | $Encoding == '#Twitter') {
                $Response['RSSimage'] = $this->Getlogo($Response['URL'], $Encoding);
                return $Response;
            }
            if (!$Response['RSSimage']) {
                if (preg_match('/(feedproxy.google|blogspot.com)/i', $Response['InternalURL'])) {
                    $GDImage = $this->getwithintag($FeedRSS, 'gd:image');
                    //$this->DebugData($GDImage, '---GDImage---', 1);
                    if (empty($GDImage)) {
                        $Response['RSSimage'] = $this->Getlogo($Response['InternalURL'], $Encoding);
                    } else {
                        $Response['RSSimage'] = trim($this->getfromatribute($GDImage[0], 'src'), "'");
                    }
                    //$this->DebugData($Response['RSSimage'], "---\$Response['RSSimage']---", 1);
                } else {
                    $Link = (string)valr('link', $RSSdata, $this->getbetweentags($FeedRSS, 'link'));
                    //$this->DebugData($Link, '---Link---', 1);
                    if (substr($Link, 0, 20) == "https://twitter.com/") {
                        $Link = substr($Link, 20);
                        //$this->DebugData($Link, '---Link---', 1);
                        $Response['RSSimage'] = $this->Getlogo("@".$Link, "Twitter");
                        return $Response;
                    }
                    if ($Link == '') {
                        $Link = $Response['InternalURL'];
                    }
                    //$this->DebugData($Link, '---Link---', 1);
                    $Response['RSSimage'] = $this->Getlogo($Link, $Encoding);
                    //$this->DebugData($Response['RSSimage'], "---\$Response['RSSimage']---", 1);
                }
            }
            //$this->postmsg($Sender, __LINE__.'Image:'.$Response['RSSimage']);
            if (!$Response['RSSimage']) {
                $Response['RSSimage'] = $this->Getlogo($Response['InternalURL'], $Encoding);
                //$this->postmsg($Sender, __LINE__.'Image:'.$Response['RSSimage']);
            }
        }
        //
        //$this->postmsg($Sender, __LINE__.'Image:'.$Response['RSSimage']);
        //
        return $Response;
    }
/**
* Get the encoding format for a web page
*
* @param object $Sender  standard
* @param object $FeedRSS page source
*
* @return string - feed enclding
*/
    private function getencoding($Sender, $FeedRSS) {
        //$this->tracemsg($Sender);
        $Tags = array(  'Youtube' => "<feed xmlns:yt",
                        'Atom' => '<Feed',
                        'RSS' => '<rss',
                        'RDFprism' => 'prism:coverDisplayDate',  //!Important: must precede the RDF format tag
                        'RDFprism' => 'prism:publicationName',   //!Important: must precede the RDF format tag
                        'RDFprism' => 'prism:publicationDate',   //!Important: must precede the RDF format tag
                        'RDF' => '<rdf:RDF',
                        'HTML' => "<!DOCTYPE html"
                        );
        //$this->DebugData(htmlspecialchars(substr($FeedRSS, 0, 500)), '---$FeedRSS 0:500---', 1);
        //  Search assists are urls displayed by service providers when a url is invalid (or blocked)/
        //  They mask real "not found" errors because they appear to be valid urls.
        //  The following code segment handles search assists.
        //  You can add additional search asisst urls to the "Verizon" example
        $Searchassists = array(
                '<meta http-equiv="refresh" content="0;url=http://searchassist.verizon.com');
        //echo '<p>'.__LINE__.' '.htmlspecialchars(substr($FeedRSS,0,1000)).'</p>';
        foreach ($Tags as $Encoding => $Identifier) {
            //$this->DebugData($Encoding, '---Encoding---', 1);
            $i = stripos($FeedRSS, $Identifier);
            if ($i !== false) {
                if ($Encoding == 'HTML') {
                    foreach ($Searchassists as $Needle) {
                        if (stripos($FeedRSS, $Needle) !== false) {
                            return 'BadURL';
                        }
                        //echo '<p>'.__LINE__.' '.htmlspecialchars($FeedRSS).'</p>';
                    }
                }
                //$this->DebugData($Encoding, '---Encoding---', 1);
                return $Encoding;
            }
        }
        //echo '<p>'.__LINE__.' '.htmlspecialchars(substr($FeedRSS,0,100)).'</p>';
        return 'BadURL';
    }
/**
* Get text between two strings in a source string
*
* @param string $Source page source
* @param string $Tag1   beginning tag identifier
* @param string $Tag2   ending tag identifier
*
* @return string - found text
*/
    private function getbetweentexts($Source, $Tag1, $Tag2) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Tag1, '---Tag1---', 1);
        $i = stripos($Source, $Tag1);
        if ($i === false) {
            //$this->DebugData(substr($Source,0,200), '---Source 0:200---', 1);
            return '';
        }
        //$this->DebugData($Tag2, '---Tag2---', 1);
        $Source = substr($Source, $i+strlen($Tag1));
        //$this->DebugData($Source, '---Source---', 1);
        $i = stripos($Source, $Tag2);
        if ($i === false) {
            //$this->DebugData($Source, '---Source---', 1);
            return '';
        }
        $Source = substr($Source, 0, $i+strlen($Tag2)-1);
        //$this->DebugData($Source, '---Source---', 1);
        return $Source;
    }
/**
* Get text within an html tag
*
* @param string $Source page source
* @param string $Tag    tag identifier
*
* @return string - found text
*/
    private function getwithintag($Source, $Tag) {
        $Pattern = "/<".$Tag." (.*?)>/si";
        preg_match_all($Pattern, $Source, $Matches);
        //$this->DebugData($Matches, '---Matches---', 1);
        return $Matches[1];
    }
/**
* Get text between html tags
*
* @param string $Source page source
* @param string $Tag    tag identifier
*
* @return string - found text
*/
    private function getbetweentags($Source, $Tag) {
        $Pattern = "#<\s*?$Tag\b[^>]*>(.*?)</$Tag\b[^>]*>#s";
        preg_match($Pattern, $Source, $Matches);
        if (isset($Matches[1])) {
            return $Matches[1];
        }
        return '';
    }
/**
* Get text from attribute string/array
*
* @param string $Source    page source
* @param string $Attribute Attribute identifier in the form of Attribute=value
*
* @return string - found value
*/
    private function getfromatribute($Source, $Attribute) {
        $Pattern = '/(\w+)\s*=\s*("[^"]*"|\'[^\']*\')/';
        //$this->DebugData($Source, '---Source---', 1);
        if (is_string($Source)) {
            $Source = (array)$Source;
        }
        foreach ($Source as $Entry) {
            //$this->DebugData($Entry, '---Entry---', 1);
            preg_match_all($Pattern, $Entry, $Sets, PREG_SET_ORDER);
            //$this->DebugData($Sets, '---Sets---', 1);
            foreach ($Sets as $Keywords) {
                //$this->DebugData($Keywords, '---Keywords---', 1);
                if ($Keywords[1] == $Attribute) {
                    return $Keywords[2];
                }
            }
        }
        return null;
    }
/**
* Stash a value
*
* @param string $Value       Value to stash
* @param string $Type        Keyword type (Key identifier)
* @param flag   $Stashmethod Use the stash method
*
* @return none
*/
    private function setstash($Value, $Type = 'Msg', $Stashmethod = true) {
        //$this->DebugData('','',true,true);
        if ($Stashmethod) {
            Gdn::session()->stash("IBPDfeed".$Type, $Value);
        } else {        //Use the cookie method
            setcookie("IBPDfeed".$Type, $Value, time() + (30), "/");
        }
    }
/**
* Fetch a stashed value
*
* @param string $Type        Keyword type (Key identifier)
* @param bool   $Retain      Reestablish the stashed value (usually destroyed by the fetch)
* @param flag   $Stashmethod Use the stash method
*
* @return string
*/
    private function getstash($Type = 'Msg', $Retain = false, $Stashmethod = true) {
        //$this->DebugData('','',true,true);
        if ($Stashmethod) {
            $Value = Gdn::session()->stash("IBPDfeed".$Type);
            if ($Retain & !empty($Value)) {
                $this->setstash($Value, $Type, $Stashmethod);
            }
        } else {        //Use the cookie method
            if (!isset($_COOKIE["IBPDfeed".$Type])) {
                return '';
            } else {
                $Value = $_COOKIE["IBPDfeed".$Type];
                if (!$Retain) {
                    $this->SetStash('', $Type, $Stashmethod);
                }
            }
        }
        return $Value;
    }
/**
* Remove scripts from source url
*
* @param string $Source source url
*
* @return string
*/
    private function removescripts($Source) {
        $Result = preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $Source, -1, $Count);
        //$this->DebugData($Count, '---Count---', 1);
        return $Result;
    }
/**
* Remove extra blanks from source url
*
* @param string $Source source url
*
* @return string
*/
    private function compactblanks($Source) {
        $Source = str_replace("\0", " ", $Source);
        $Source = preg_replace("/ {2,}/", "/1", $Source);
        $Source = preg_replace('#\R+#', '', trim($Source));
        $Source = preg_replace("/[\r\n]+/", "\n", $Source);
        //$Source = wordwrap($Source,120, '<br/>', true);
        //echo ('<br>'.__LINE__.' '.substr(strip_tags($Source), 0, 120).'<br>');
        //die(0);
        return $Source;
    }
/**
* Encode feed key
*
* @param string $Key Feed's entry key
*
* @return string
*/
    public static function encodefeedkey($Key) {
        return md5($Key);
    }
/**
* Check plugin prerequisites
*
* @return none
*/
    public function checkprereqs() {
        $Msg = '';
        if (!function_exists('curl_exec')) {
              $Msg = "PHP's cURL package is not installed.";
        } elseif (!extension_loaded('simplexml')) {
              $Msg = "PHP's simplexml package is not installed.";
        } elseif (!function_exists('simplexml_load_string')) {
              $Msg = "PHP's simplexml_load_string function is not available.";
        }
        if ($Msg) {
              $Msg = $this->setmsg($Msg." It is required for the Feed Discussions Plus plugin.");
              echo "<H1><B>".$Msg."<N></H1>";
              echo "Here are your current PHPinfo settings:";
              echo phpinfo();
              throw new Gdn_UserException($Msg);
        }
        return;
    }
/**
* Check plugin setup
*
*
* @return none
*/
    public function setup() {
        $this->Checkprereqs();
        $this->Structure();
        //External settings
        touchConfig('Plugins.FeedDiscussionsPlus.Sortby', 'Feedtitle');
        touchConfig('Plugins.FeedDiscussionsPlus.GetLogo', true);
        touchConfig('Plugins.FeedDiscussionsPlus.Croncode', 'code');
        touchConfig('Plugins.FeedDiscussionsPlus.Feedusername', 'Feed');
        touchConfig('Plugins.FeedDiscussionsPlus.Returntolist', false);
        touchConfig('Plugins.FeedDiscussionsPlus.Userinitiated', true);
        touchConfig('Plugins.FeedDiscussionsPlus.Detailedreport', false);
        touchConfig('Plugins.FeedDiscussionsPlus.Globalmaximport', 5);
        //Internal settings
        touchConfig('Plugins.FeedDiscussionsPlus.showkey', false);
        touchConfig('Plugins.FeedDiscussionsPlus.allowupdate', false);
        touchConfig('Plugins.FeedDiscussionsPlus.showurl', false);
        touchConfig('Plugins.FeedDiscussionsPlus.Hideinactive', false);
    }
/**
* Set plugin data structure
*
* @return none
*/
    public function structure() {
    }
/**
* Insert a window close button script
*
* @param string $Prefix  Optional text to precede the button
* @param string $Button  button text
* @param string $Suffix  Optional post button text
* @param string $Class   Optional CSS class
* @param string $Refresh Optional text to precede the button
*
* @return none
*/
    private function javawindowclose($Prefix = '', $Button = '', $Suffix = '', $Class = '', $Refresh = false) {
        //  Process button
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
        if ($Refresh) {
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
        } else {
            $CloseScript = '<body "><script language="javascript" type="text/javascript">
                            function windowClose() {
                            window.open(\'\',\'_parent\',\'\');
                            window.close();
                            }
                        </script>';
        }
        return $CloseScript;
    }
/**
* Debugging function
*
* @param object $Sender standard
* @param string $Msg    text to display
*
* @return none
*/
    private function tracemsg($Sender, $Msg) {
        if ($Msg == '') {
            if (isset(debug_backtrace()[0]['line'])) {
                $Line0 = debug_backtrace()[0]['line'];
            } else {
                $Line0 = '';
            }
            if (isset(debug_backtrace()[1]['line'])) {
                $Line1 = debug_backtrace()[1]['line'];
            } else {
                $Line1 = '';
            }
            $Msg = '>'.debug_backtrace()[1]['class'].':'.
              debug_backtrace()[1]['function'].':'.$Line0.
              ' called by '.debug_backtrace()[2]['function'].' @ '.$Line1;
            //$this->DebugData($Msg, '&nbsp', true, true);
        }
        $this->postmsg($Sender, $Msg, false, false);
    }
/**
* Debugging function
*
* @param string $Data    data to debug
* @param string $Message text to display
* @param bool   $Debug   indicator whether debugging is active
* @param bool   $Inform  request to queue informmsg
*
* @return none
*/
    private function debugdata($Data, $Message, $Debug = true, $Inform = false) {
        if ($Debug == false) {
            return;
        }
        if (isset(debug_backtrace()[0]['line'])) {
            $Line0 = debug_backtrace()[0]['line'];
        } else {
            $Line0 = '';
        }
        if (isset(debug_backtrace()[1]['line'])) {
            $Line1 = debug_backtrace()[1]['line'];
        } else {
            $Line1 = '';
        }
        $Color = 'color:red;';
        if ($Message == '') {
            $Message = '>'.debug_backtrace()[1]['class'].':'.
              debug_backtrace()[1]['function'].':'.$Line0.
              ' called by '.debug_backtrace()[2]['function'].' @ '.$Line1;
            $Color = 'color:blue;';
        } else {
            $Message = '>'.debug_backtrace()[1]['class'].':'.debug_backtrace()[1]['function'].':'.debug_backtrace()[0]['line'].' '.$Message;
        }
        if ($Inform == true) {
            ///Gdn::controller()->informMessage($Message);
            //decho($Data, $Message);
        }
        echo '<pre style="font-size: 1.3em;text-align: left; padding: 0 4px;'.$Color.'">'.$Message;
        Trace(__LINE__.' '.$Message.' '.$Data);
        if ($Data != '') {
            if (is_string($Data)) {
                echo $this->compactblanks(highlight_string($Data, 1));
            } else {
                var_dump($Data);
            }
        }
        echo '</pre>';
        //
    }
    /////////////////////////////////////////////////////////
}
