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
 *  Revised user interface
 *
 *   Note:  This initial version (which was converted from our intranet) does not fully adhere to Vanilla
 *          coding standard and still contain debugging code (these will be corrected in due course).
 *
 */
//
$PluginInfo['FeedDiscussionsPlus'] = array(
    'Name' => 'Feed Discussions Plus',
    'Description' => "Automatically create Vanilla discussions based on RSS/Atom feed imported content.",
    'Version' => '2.1.b4',
    'RequiredApplications' => array('Vanilla' => '2.3'),
    'MobileFriendly' => true,
    'HasLocale' => true,
    'RegisterPermissions' => false,
    'SettingsUrl' => '/plugin/feeddiscussionsplus/addfeed',
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
* Act as a mini dispatcher for API requests to the plugin appion.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function plugincontroller_feeddiscussionsplus_create($Sender) {
        $Sender->Title('FeedDiscussionsPlus Plugin');
        $Sender->AddSideMenu('plugin/feeddiscussionsplus');
        $this->Dispatch($Sender, $Sender->RequestArgs);
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
* Set the CSS
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function assetmodel_stylecss_handler($Sender) {
        $Sender->addCssFile('feeddiscussionspluspopup.css', 'plugins/FeedDiscussionsPlus');
    }
/**
* Set the CSS
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function base_render_before($Sender) {
        $Sender->addCssFile('feeddiscussionspluspopup.css', 'plugins/FeedDiscussionsPlus');
    }
/**
* Include Javascript in the discussion view
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function discussioncontroller_beforediscussionrender_handler($Sender) {
        //$this->DebugData(__LINE__, '', 1);
        if (c('Plugins.FeedDiscussionsPlus.Userinitiated', 'true')) {
            if ($this->checkfeeds($Sender, false)) {
                  $Sender->AddJsFile('feeddiscussionsplus.js', 'plugins/FeedDiscussionsPlus');
            }
        }
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
        $croncode = c('Plugins.FeedDiscussionsPlus.croncode', 'secret');
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
                }
            } elseif ($i == 1) {
            } elseif (($Args[0] == 'cron') && ($Args[1] == $croncode)) {
                echo '<br>Cron refresh ('.$croncode.')<br>';
                $Cron = true;
            } elseif ($Args[0] == 'plugin' && $Args[1] == 'feeddiscussionsplus') {
                echo "processing completed - close this window/tab";
                return;
            }
        }
        if (!$Backend & ! $Cron) {
            $Sender->Permission('Garden.Settings.Manage');
            echo '<h1>Wrong link '.__LINE__.' </h1>';
            return;
        }
        $Sender->AddCssFile('_admin.scss', 'applications/dashboard/scss/legacy');
        $Sender->AddCssFile('feeddiscussionspluspopup.css', 'plugins/FeedDiscussionsPlus');
        $Exit = '<span style="float:right">  <input type="button" value="Close" class="Button" '.
                'onClick=\'window.location.href="plugin/feeddiscussionsplus/addfeed" \';></span>';
        echo '<div id=FDPreport class="FDPbatch"><div id=FDP><h1>Feed Discussions Plus - Checking feeds</h1>'.$Exit;
        echo '<span >Current server hour:<b>'.date('H').'</b> &nbsp (Server time:'.date('Y-m-d H:i:s', time()).')</span>';
        //echo '<span style="margin: 0 0 0 20px;"><a class="Button DeleteFeed" href="'.Url('/plugin/feeddiscussionsplus/ListFeeds').
          '" title="'.t('Close this report and return to the setup screen').'">Close</a></span>';
        $this->Checkfeeds($Sender, true);
        echo $Exit.'</div></div>';
    }
/**
* Import feeds based on thepredefined criteria
*
* @param object $Sender     Standard Vanilla
* @param bool   $AutoImport Indicates manual or trigerred import
*
* @return bool|int
*/
    public function checkfeeds($Sender, $AutoImport = true) {
        //$this->DebugData(__LINE__, '', 1);
        Gdn::Controller()->SetData("AutoImport", $AutoImport);
        $Controller = $Sender->ControllerName;  //Current Controller
        $MasterView = $Sender->MasterView;
        $NumCheckedFeeds = 0;
        $NumInactiveFeeds = 0;
        $NumOutsideWindowFeeds = 0;
        $NumNotduedFeeds = 0;
        $Checkfeed = false;
        // Loop through the feeds
        foreach ($this->GetFeeds() as $FeedURL => $FeedData) {
            $NumCheckedFeeds +=1;
            $Forceupdate = false;
            Gdn::Controller()->SetData("{$FeedURL}", $FeedData);
            $Active = val('Active', $FeedData, 0);
            $Feedtitle = val('Feedtitle', $FeedData);
            $Encoding = val('Encoding', $FeedData);
            if (!$Feedtitle) {
                  $Feedtitle = "Url: ".$FeedURL;
            }
            $Activehours = val('Activehours', $FeedData);
            //$this->DebugData($Feedtitle, '---Feedtitle---', 1);
            //$this->DebugData($Active, '---Active---', 1);
            //$this->DebugData($Encoding, '---Encoding---', 1);
            if (!$Active) {
                if ($AutoImport) {
                    $NumInactiveFeeds +=1;
                    echo '<br><b>'.$NumCheckedFeeds.'. Skipping ' . $Encoding . ' feed "'.$Feedtitle.'" </b>'. '(Inactive feed)';
                }
            } elseif (!$this->iswithinwindow($Activehours)) {
                if ($AutoImport) {
                    $NumOutsideWindowFeeds +=1;
                    echo '<br><b>'.$NumCheckedFeeds.'. Skipping ' . $Encoding . ' feed "'.
                    $Feedtitle.'"  </b>(outside active hours of '.$Activehours.') </b>';
                }
            } else {
                $Added = val('Added', $FeedData, 0);
                $LastImport = val('LastImport', $FeedData);
                $Active = val('Active', $FeedData);
                $OrFilter = val('OrFilter', $FeedData);
                $AndFilter = val('AndFilter', $FeedData);
                $Minwords = val('Minwords', $FeedData);
                $Maxitems = val('Maxitems', $FeedData);
                $Getlogo = val('Getlogo', $FeedData);
                $Noimage = val('Noimage', $FeedData);
                //$this->DebugData($FeedData, '---FeedData---', 1);
                //$this->DebugData($Added, '---Added---', 1);
                //$this->DebugData($LastImport, '---LastImport---', 1);
                //$this->DebugData($OrFilter, '---OrFilter---', 1);
                //$this->DebugData($AndFilter, '---AndFilter---', 1);
                //$this->DebugData($Minwords, '---Minwords---', 1);
                // Check feed here
                $LastImport = $LastImport == 'never' ? null : strtotime($LastImport);
                //$this->DebugData($LastImport, '---LastImport---', 1);
                //$this->DebugData(date('c', $LastImport), '---LastImport---', 1);
                if (is_null($LastImport)) {
                      $Forceupdate = true;
                      //$this->DebugData($LastImport, '---LastImport---', 1);;
                }
                //$this->DebugData(date('c', $LastImport), '---LastImport---', 1);
                $Historical = (bool)val('Historical', $FeedData, false);
                $Delay = val('Refresh', $FeedData);
                $DelayStr = '+'.str_replace(array(
                      'm',
                      'h',
                      'd',
                      'w'
                ), array(
                      'minutes',
                      'hours',
                      'days',
                      'weeks'
                ), $Delay);
                $DelayMinTime = strtotime($DelayStr, $LastImport);
                //
                //$LastImportdate = date('Y-m-d H:i:s', $LastImport);    //Last import date/time
                $Delaydate = date('Y-m-d H:i:s', $DelayMinTime);      //Date/time from which to check feed
                $Timedate = date('Y-m-d H:i:s', time());                //Current date/time
                if ($Historical) {
                    $Forceupdate = true;
                } elseif ($Timedate > $Delaydate) {        //Current date/time > prescribed delay
                    $Forceupdate = true;
                }
                //
                if ($Forceupdate) {
                //$this->DebugData($AutoImport, '---AutoImport---', 1);
                    if ($AutoImport) {
                          echo '<br><b>'.$NumCheckedFeeds.'. Checking ' . $Encoding . ' feed "'.$Feedtitle.'</b>';
                    }
                    $this->PollFeed($FeedURL, $AutoImport, $LastImport, $OrFilter, $AndFilter, $Minwords, $Maxitems, $Getlogo, $Noimage);
                } else {
                    if ($AutoImport) {
                        echo '<br><b>'.$NumCheckedFeeds.'. Skipping ' . $Encoding .
                        ' feed "'.$Feedtitle.'"  </b> (Feed not due for processing until '.$Delaydate.')';
                          $NumNotduedFeeds += 1;
                    }
                }
            }
        }
        if ($AutoImport) {
            echo "<h2><b>&nbsp&nbsp&nbsp" . $NumCheckedFeeds . ' Feeds processed. </b>';
            $Skipped = $NumInactiveFeeds + $NumOutsideWindowFeeds + $NumNotduedFeeds;
            if ($Skipped) {
                echo '' . $Skipped . ' skipped:';
                if ($NumInactiveFeeds) {
                    echo ' ' .  $NumInactiveFeeds . ' Inactive.';
                }
                if ($NumOutsideWindowFeeds) {
                    echo ' ' .  $NumOutsideWindowFeeds . ' Outside of their active hours.';
                }
                if ($NumNotduedFeeds) {
                    echo ' ' .  $NumNotduedFeeds . ' not due for import yet.';
                }
            }
            echo '</h2>';
        }
        return $NumCheckedFeeds;
    }
/**
* Mini router
*
* @param object $Sender Standard Vanilla
*
* @return bool|int
*/
    public function controller_index($Sender) {
        //$this->DebugData('', '', true, true);
        $Sender->Permission('Garden.Settings.Manage');
        $this->Checkprereqs();   //Check system requirements for the plugin
        //$Msg =  '<span>L#'.__LINE__.'</span>';
        $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
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
        $Sender->AddCssFile('feeddiscussionspluspopup', 'plugins/FeedDiscussionsPlus');
        $Postback = $Sender->Form->AuthenticatedPostback();
        //var_dump($Args);
        if (!$Postback) {            //Initial form setup
            //echo '<span>'.__FUNCTION__.' L#'.__LINE__.'</span>';
            $Arg0 = val(0, $Args, null);
            //decho ($Arg0);
            $FeedKey = $Arg0;
            $Feed = $this->GetFeed($FeedKey);
            if (empty($Feed)) {
                  $Msg = __LINE__.' The feed was deleted before it could be displayed.';
                  $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
            }
            //$this->DebugData($Feed, '---Feed---', 1);
            //Initial form setting
            $Sender->Title($this->GetPluginKey('Name'));
            $Sender->SetData('Description', $this->GetPluginKey('Description'));
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', 'Update');
            $Sender->SetData('Feed', $Feed);
            $Sender->SetData('FeedKey', $FeedKey);
            $Sender->SetData('FeedURL', $Feed['URL']);
            $Sender->Form->setValue('FeedURL', $Feed['URL']);
            $Sender->Form->setValue('Active', $Feed['Active']);
            $Sender->Form->setValue('Reset', $Feed['Reset']);
            $Sender->Form->setValue('Refresh', $Feed['Refresh']);
            $Sender->SetData('Refresh', $Feed['Refresh']);
            $Sender->Form->setValue('Feedtitle', $Feed['Feedtitle']);
            $Sender->Form->setValue('Historical', $Feed['Historical']);
            $Sender->Form->setValue('Getlogo', $Feed['Getlogo']);
            $Sender->Form->setValue('Noimage', $Feed['Noimage']);
            $Sender->Form->setValue('Category', $Feed['Category']);
            $Sender->Form->setValue('OrFilter', $Feed['OrFilter']);
            $Sender->Form->setValue('AndFilter', $Feed['AndFilter']);
            $Sender->Form->setValue('Minwords', $Feed['Minwords']);
            $Sender->Form->setValue('Activehours', $Feed['Activehours']);
            $Sender->Form->setValue('Maxitems', $Feed['Maxitems']);
            //
            $Msg = (string)$this->GetStash();
            if ($Msg!='') {
                $Sender->InformMessage($Msg);
                $Sender->SetData('Qmsg', $Msg);
            }
        } else {  //Form Postback
            //echo '<span>'.__FUNCTION__.' L#'.__LINE__.'</span>';
            $Sender->SetData('FeedURL', $Feedarray['FeedURL']);
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', 'Update');
            $FormPostValues = $Sender->Form->FormValues();
            //decho ($FormPostValues);
            //$this->DebugData($FormPostValues["FeedURL"], '---FormPostValues["FeedURL"]---', 1);
            $Feedarray = $this->getfeedfromform($FormPostValues);
            $Defaults = $this->feeddefaults();
            $Feedarray = array_merge($Defaults, $Feedarray);
            $FeedRSS = $this->validatefeed($Feedarray, 'Update');  //Validate form inputs in "Update" mode
            if ($FeedRSS['Error']) {
                $Msg = $FeedRSS['Error'];
                $Sender->setData('FeedURL', $FeedRSS['URL']);
                //$this->DebugData($FeedRSS, '---FeedRSS---', 1);
                $Sender->Form->AddError($FeedRSS['Error']);
                $Sender->InformMessage($Msg);
                //$Sender->SetData('Qmsg',$Msg);
                //$this->SetStash($Msg,'Qmsg');
            } else {
                $Feedarray["RSSimage"] = $FeedRSS['RSSimage'];
                $Feedarray["Encoding"] = $FeedRSS['Encoding'];
                $Feedarray["Feedtitle"] = $FeedRSS['Title'];
                //
                //$this->DebugData($Feedarray, '---Feedarray---', 1);
                $this->AddFeed($Feedarray['FeedURL'], $Feedarray);
                $Msg = 'The "'.$FeedRSS['Title'].'" feed was updated.';
                if (!$Feedarray['Active']) {
                    $Msg = $Msg . '<FFRED> Remember to activate the added feed (it is now inactive).</FFRED> ';
                }
                $Msg = $FeedRSS['FeedKey'] . ' -- ' . $Msg;
                $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
            }
        }
        //echo '<span>'.__FUNCTION__.' L#'.__LINE__.'</span>';
        $Sender->SetData('Mode', 'Update');
        $this->renderview($Sender, 'feeddiscussionsplusedit.php');
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
        $Postback = $Sender->Form->AuthenticatedPostback();
        $Mode = 'Add';
        $Savedmode = $Sender->Data('Mode');
        if (!$Postback) {            //Initial form setup
            //echo '<span>L#'.__LINE__.'</span>';
            $this->SetStash($Mode, 'Mode');
            $Sender->Title($this->GetPluginKey('Name'));
            $Sender->SetData('Description', $this->GetPluginKey('Description'));
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', $Mode);
            $Msg = (string)$this->GetStash();
            if ($Msg!='') {
                $Sender->InformMessage($Msg);
                $Sender->SetData('Qmsg', $Msg);
            }
        } else {  //Form Postback
            //$this->DebugData($Mode, '---Mode---', 1);
            $Sender->SetData('Categories', CategoryModel::Categories());
            $Sender->SetData('Mode', $Mode);
            $FormPostValues = $Sender->Form->FormValues();
            //decho ($FormPostValues);
            //$this->DebugData($FormPostValues["FeedURL"], '---FormPostValues["FeedURL"]---', 1);
            $Feedarray = $this->getfeedfromform($FormPostValues);
            //echo '<span>L#'.__LINE__.'</span>';
            $Defaults = $this->feeddefaults();
            $Feedarray = array_merge($Defaults, $Feedarray);
            //decho ($Feedarray);
            $FeedRSS = $this->validatefeed($Feedarray, 'Add');  //Validate form inputs in "Add" mode
            if ($FeedRSS['Error']) {
                //$this->DebugData($FeedRSS['Error'], "---FeedRSS['Error']---", 1);
                $Msg = $FeedRSS['Error'];
                if ($FeedRSS["SuggestedURL"]) {
                    $FeedRSS["SuggestedURL"] = '';
                    //$this->DebugData($SuggestedURL, '---SuggestedURL---', 1);
                    //$this->DebugData($FeedRSS, '---FeedRSS---', 1);
                    $Sender->Form->setFormValue('FeedURL', $FeedRSS["URL"]);
                    $Sender->Form->AddError($Msg, "FeedURL");
                    $Msg = "The url you specified is a regular web page,not a feed: ".
                            $FormPostValues["FeedURL"];
                }
                $FeedRSS['Error']='';
                $this->renderview($Sender, 'feeddiscussionsplusedit.php', $Msg);
                return;
            } else {
                $Feedarray["RSSimage"] = $FeedRSS['RSSimage'];
                $Feedarray["Encoding"] = $FeedRSS['Encoding'];
                $Feedarray["Feedtitle"] = $FeedRSS['Title'];
                //$this->DebugData($Feedarray, '---Feedarray---', 1);
                $this->AddFeed($this->rebuildurl($Feedarray['FeedURL'], ''), $Feedarray);
                $Sender->SetData('Mode', 'Update');
                $Sender->SetData('FeedURL', $Feedarray['FeedURL']);
                //$this->DebugData($Mode, '---Mode---', 1);
                $Msg = 'The "'.$FeedRSS['Title'].'" feed was added.';
                if (!$Feedarray['Active']) {
                    $Msg = $Msg . '<FFRED> Remember to activate the added feed (it is now inactive).</FFRED> ';
                }
                $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
            }
        }
        //echo '<span>L#'.__LINE__.'</span>';
        $this->renderview($Sender, 'feeddiscussionsplusedit.php', $Msg);
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
        $FeedURL = implode('/', $Args);
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        $RestoreFeedURL = $Sender->Data('RestoreFeedURL');
        //$this->DebugData($RestoreFeedURL, '---RestoreFeedURL---', 1);
        $Feed = $this->getstash('RestoreFeed');
        if ($Feed) {
            //$this->DebugData($Feed, '---Feed---', 1);
            $this->AddFeed($this->rebuildurl($Feed['FeedURL'], ''), $Feed);
            $Msg = ' Feed "'.$Feed["Feedtitle"].'" Restored';
        } else {
            $this->AddFeed($this->rebuildurl($Feed['FeedURL'], ''), $Feed);
            $Msg = __LINE__.' could not restore';
        }
        $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
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
        $Categories = CategoryModel::Categories();
        //
        if ($Sender->Form->AuthenticatedPostback()) {    //Postback
            $FormPostValues = $Sender->Form->FormValues();
            //$this->DebugData($FormPostValues, '---FormPostValues---', 1);
        } else {  //Initial Form setup
            $FormPostValues = $Sender->Form->FormValues();
            //$this->DebugData($FormPostValues, '---FormPostValues---', 1);
            //Initial form setting
            $RestoreFeedURL = (string)$this->GetStash('RestoreFeedURL');
            $Sender->SetData('RestoreFeedURL', $RestoreFeedURL);
            //$this->DebugData($RestoreFeedURL, '---RestoreFeedURL---', 1);
            //$this->DebugData($Sender->Data, '---Sender->Data---', 1);
            $Sender->Title($this->GetPluginKey('Name'));
            $Sender->SetData('Description', $this->GetPluginKey('Description'));
            $Sender->SetData('Categories', $Categories);
            $Sender->SetData('Feeds', $this->GetFeeds());
            $Msg = (string)$this->GetStash('Qmsg');
            $this->SetStash($Msg, '');
            if ($Msg!='') {
                $Sender->InformMessage($Msg);
                $Sender->SetData('Qmsg', $Msg);
            }
        }
        $Sender->SetData('Feeds', $this->GetFeeds());
        $this->renderview($Sender, 'feeddiscussionspluslist.php');
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
        $Sender->Permission('Garden.Settings.Manage');
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
            $Feed = $this->GetFeed($FeedKey);
            $Active = $Feed['Active'];
            $Feedtitle = $Feed['Feedtitle'];
            if ($Active) {
                   $Msg = 'The "'.$Feedtitle.'" feed has been deactivated.';
                   $Active = false;
            } else {
                   $Msg = 'The "'.$Feedtitle.'" feed has been activated.';
                   $Active = true;
            }
            $this->UpdateFeed($FeedKey, array(
                'Active'     => $Active
            ));
        } else {
              $Msg = __LINE__.' '.T("Invalid toggle request");
        }
        $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
          //
    }
/**
* Clear last update feed date.
*
* @param object $Sender Standard Vanilla
* @param object $Args   Standard Vanilla
*
* @return bool|int
*/
    public function controller_resetfeed($Sender, $Args) {
        $Sender->Permission('Garden.Settings.Manage');
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
            $Feed = $this->GetFeed($FeedKey);
            $Feedtitle = $Feed['Feedtitle'];
            $Msg = 'The "'.$Feedtitle.'" feed last import date was reset to "never".';
            $this->UpdateFeed($FeedKey, array(
                'LastImport'     => 'never'
            ));
        } else {
            $Msg = __LINE__.' '.T("Invalid Reset request");
        }
        $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
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
        $Sender->Permission('Garden.Settings.Manage');
        $this->renderview($Sender, 'help.php');
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
        $Sender->Permission('Garden.Settings.Manage');
        $this->renderview($Sender, 'Addfirst.php');
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
            $Sender->Form->setValue('FeedURL', $Feed[URL]);
            $Sender->Form->setValue('Historical', $Feed['Historical']);
            $Sender->Form->setValue('Refresh', $Feed['Refresh']);
            $Sender->Form->setValue('Active', $Feed['Active']);
            $Sender->Form->setValue('OrFilter', $Feed['OrFilter']);
            $Sender->Form->setValue('AndFilter', $Feed['AndFilter']);
            $Sender->Form->setValue('Minwords', $Feed['Minwords']);
            $Sender->Form->setValue('Maxitems', $Feed['Maxitems']);
            $Sender->Form->setValue('Activehours', $Feed['Activehours']);
            $Sender->Form->setValue('Getlogo', $Feed['Getlogo']);
            $Sender->Form->setValue('Noimage', $Feed['Noimage']);
            $Sender->Form->setValue('Category', $Feed[Category]);
            $Sender->Form->setValue('Feedtitle', $Feed['Feedtitle']);
            //
            $Sender->setData('Feedtitle', $Feed['Feedtitle']);
            $Sender->SetData('Feeds', $this->GetFeeds());
        } elseif (strtolower($Method)== 'setdata') {
            $Sender->setData('FeedURL', $Feed[URL]);
            $Sender->setData('Historical', $Feed['Historical']);
            $Sender->setData('Refresh', $Feed['Refresh']);
            $Sender->setData('Active', $Feed['Active']);
            $Sender->setData('OrFilter', $Feed['OrFilter']);
            $Sender->setData('AndFilter', $Feed['AndFilter']);
            $Sender->setData('Minwords', $Feed['Minwords']);
            $Sender->setData('Maxitems', $Feed['Maxitems']);
            $Sender->setData('Activehours', $Feed['Activehours']);
            $Sender->setData('Getlogo', $Feed['Getlogo']);
            $Sender->setData('Noimage', $Feed['Noimage']);
            $Sender->setData('Feedtitle', $Feed['Feedtitle']);
            $Sender->setData('Category', $Feed[Category]);
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
              'Minwords'       => '',
              'Activehours'       => '00-24',
              'Maxitems'       => null,
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
        $Sender->Permission('Garden.Settings.Manage');
        //$this->DebugData($Args, '---Args---', 1);
        $FeedKey = val(1, $Sender->RequestArgs, null);
        if (!is_null($FeedKey) && $this->HaveFeed($FeedKey)) {
            $Feed = $this->GetFeed($FeedKey, true);
            decho($Feed);
            $FeedURL = $Feed["FeedURL"];
            //$this->DebugData($FeedURL, '---FeedURL---', 1);
            $Feedtitle = $Feed['Feedtitle'];
            $this->SetStash($Feed, 'RestoreFeed');
            $this->SetStash($Feed["FeedURL"], 'RestoreFeedURL');
            $Sender->SetData('RestoreFeedURL', __LINE__.$FeedURL);
            $this->RemoveFeed($FeedKey);
            $Restorebutton = '<a class="Button " href="' . Url('/plugin/feeddiscussionsplus/restorefeed/'.$RestoreFeedURL).
                '" title="' . t('Restore recently deleted feed').' '.$RestoreFeedURL.'"><ffred> ↻ </ffred>'.t("Undo Delete").'</a>';
            $Msg = 'Feed "'.$Feed["Feedtitle"].'" was deleted. Click '.$Restorebutton. ' to restore it.';
        } else {
            $Msg = __LINE__.' Invalid prameters';
        }
        $this->redirecttourl($Sender, '/plugin/feeddiscussionsplus/ListFeeds', $Msg);
    }
/**
* Validate a feed.
*
* @param object $Feedarray Feed definition
* @param object $Mode      Validation mode (Add/Update)
*
* @return bool|int
*/
    private function validatefeed($Feedarray, $Mode = 'Add') {
        //$this->DebugData(__LINE__, '', 1);
        //$this->DebugData($Feedarray, '---Feedarray---', 1);
        $FeedRSS = array('FeedURL'   => $Feedarray["FeedURL"],
                        'Error' => false
                        );
        try {
            if (empty($Feedarray["FeedURL"])) {
                throw new Exception(__LINE__." You must supply a valid Feed URL");
            }
            if ($Mode == 'Add') {
                if ($this->HaveFeed($Feedarray["FeedURL"], false)) {
                    throw new Exception(__LINE__." The Feed URL you supplied is already in the list");
                }
            }
            // Get RSS Data
            $FeedRSS = $this->getfeedrss($Feedarray["FeedURL"], false);     //Request metadata only
            //$this->DebugData($FeedRSS, '---FeedRSS---', 1);
            if ($FeedRSS['Error']) {
                throw new Exception(__LINE__."   ".$FeedRSS['Error']);
            }
            //
            if (!array_key_exists($Feedarray["Category"], CategoryModel::Categories())) {
                throw new Exception("Specify a valid destination category");
            }
            // Validate maximum number of items
            if (trim($Feedarray["Maxitems"]) && !is_numeric($Feedarray["Maxitems"])) {
                throw new Exception('"'.$Feedarray["Maxitems"].'" is not valid. If you specify a maximum number of items it must be numeric.');
            }
            // Validate minimum number of words
            if (trim($Feedarray["Minwords"]) && !is_numeric($Feedarray["Minwords"])) {
                throw new Exception('"'.$Feedarray["Minwords"].'" is not valid. If you specify a minimum number of words it must be numeric.');
            }
            // Validate Active hours (format hh-hh)
            $Msg = $this->validatehours($Feedarray["Activehours"]);
            if ($Msg != '') {
                throw new Exception($Msg);
            }
        } catch (Exception $e) {
            $FeedRSS['Error'] = T($e->getMessage());
        }
        //decho ($FeedRSS);
        return $FeedRSS;
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
* @param string $FeedURL        Request for raw list
* @param bool   $AutoImport     Indicates automatic import
* @param string $LastImportDate last import date
* @param string $OrFilter       keywords filter (any match will satify filter)
* @param string $AndFilter      keywords filter (all must match to satify filter)
* @param string $Minwords       minimum number of words required to import item
* @param string $Maxitems       Maximum number of items to save per import
* @param bool   $Getlogo        Request to fetch the feed logo
* @param bool   $Noimage        Indicates that item's image urls are to be removed
*
* @return none
*/
    protected function pollfeed($FeedURL, $AutoImport, $LastImportDate, $OrFilter, $AndFilter, $Minwords, $Maxitems, $Getlogo, $Noimage) {
          //$this->DebugData($LastImportDate, '---LastImportDate---', 1);
        $NumCheckedItems = 0;
        $NumFilterFailedItems = 0;
        $NumSavedItems = 0;
        $NumAlreadySavedItems = 0;
        $NumSkippedOldItems = 0;
        $NumSkippedItems = 0;
        //
        //$this->DebugData($AutoImport, '---AutoImport---', 1);
        $FeedRSS = $this->getfeedrss($FeedURL, true, false, $AutoImport);    //Request metadata and data
        if ($FeedRSS['Error']) {
            if ($AutoImport) {
                echo '<br>'.__LINE__.' Feed does not have a valid RSS definition.'.$FeedRSS['Error'].'<br>';
            }
            return false;
        }
        $Encoding = $FeedRSS['Encoding'];
        if ($Getlogo) {
            $Logo = $FeedRSS['RSSimage'];
            //$this->DebugData($Logo, '---Logo---', 1);
        }
        $Updated = $FeedRSS['Updated'];
        //
        $Itemkey = 'channel.item';
        $Datekey = 'pubDate';
        $Contentkey = 'description';
        if ($Encoding == 'Atom') {
            $Itemkey = 'entry';
            $Datekey = 'published';
            $Contentkey = 'content';
            $Linkkey = '@attributes.href';
        } elseif ($Encoding == 'RSS') {
            $Itemkey = 'channel.item';
            $Datekey = 'pubDate';
            $Contentkey = 'description';
            $Linkkey = '0';
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
                echo '<br> Feed does not have a valid RSS content (code '.__LINE__.')<br>';
            }
            return false;
        }
        $Feed = $this->GetFeed($FeedURL, false);
        $DiscussionModel = new DiscussionModel();
        $DiscussionModel->SpamCheck = false;
        $Historical = val('Historical', $Feed);
        //$LastPublishDate = val('LastPublishDate', $Feed, date('c'));
        $LastPublishDate = val('LastPublishDate', $Feed, val('published', $Feed, date('c')), date('c'));
        $feedupdated = $LastPublishDate;
        //$this->DebugData($feedupdated, '---feedupdated---', 1);
        $LastPublishTime = strtotime($LastPublishDate);
        $FeedLastPublishTime = 0;
        //Get userid of "Feed" id if one was predefined so it would be the saving author of new discussions.
        $User = Gdn::userModel()->getByUsername(trim(t('Feed')));
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
            //$Item["content"] = __LINE__.' T E S T';
            //$this->DebugData($Item, '---Item---', 1);
            //$this->DebugData(array_keys($Item), '---array_keys($Item)---', 1);
            $NumCheckedItems +=1;
            $Skipitem = false;
            //$this->DebugData((array)$Item["author"], '---(array)Item["author"]---', 1);
            //$this->DebugData((array)$Item["guid"], '---(array)Item["guid"]---', 1);
            //$this->DebugData((array)$Item["link"], '---(array)Item["link"]---', 1);
            $Link = (array) $Item["link"];
            $Itemurl = trim((string)valr("@attributes.link", $Link, valr("@attributes.href", $Link, valr($Linkkey, $Link))));
            //$Itemurl = trim((string)valr('link', $Item, valr('@attributes.href', (array)$Item["link"])));
            //$this->DebugData(gettype($Item["link"]), '---gettype(/$Item["link"])---', 1);
            //
            //$this->DebugData($Link, '---Link---', 1);
            //$this->DebugData($Itemurl, '---Itemurl---', 1);
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
            if (($Historical == false) && ($ItemPubDate < $LastPublishDate)) {
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
                $StoryTitle = array_shift($Trash = explode("\n", (string)val('title', $Item)));
                $StoryBody = (string)valr($Contentkey, $Item, valr('description', $Item, valr('content', $Item, valr('summary', $Item, ''))), ' ');
                //
                if ($FeedRSS['Noimage']) {
                    $StoryBody = $this->Imagereformat($StoryBody, '!');
                }
                //
                $StoryPublished = date("Y-m-d H:i:s", $ItemPubTime);
                $Domain = @parse_url($Itemurl, PHP_URL_HOST);
                //$this->DebugData($Itemurl, '---Itemurl---', 1);
            }
            if (!$Skipitem  && $AutoImport) {
                echo '<br>&nbsp&nbsp&nbsp Processing item #'.$NumCheckedItems.':"'.SliceString($StoryTitle, 40).'".  ';
            }
            if (!$Skipitem && $OrFilter != '') {
                //$this->DebugData($OrFilter, '---OrFilter---', 1);
                $Tokens = explode(",", $OrFilter);
                $Found = false;
                foreach ($Tokens as $Token) {
                    //$this->DebugData($StoryTitle, '---OrFilter test--- on: '.$Token.' ', 1);
                    if (preg_match('/\b'.$Token.'\b/i', $StoryTitle)) {
                        if ($AutoImport) {
                            echo " Matched OrFilter:".$Token." ";
                        }
                        $Found = true;
                    }
                }
                if (!$Found) {
                    //$this->DebugData($StoryTitle, '---Filters NOT Matched---Filters:'.$OrFilter.' ', 1);
                    if ($AutoImport) {
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
                    if (!$Skipitem && !preg_match('/\b'.$Token.'\b/i', $StoryTitle)) {
                        //$this->DebugData($StoryTitle, '---AndFilter NOT Matched--- on: '.$Token.' ', 1);
                        if ($AutoImport) {
                            echo " Did not match AND filter:".$Token." ";
                        }
                        $Skipitem = true;
                        $NumSkippedItems += 1;
                        $NumFilterFailedItems +=1;
                    }
                }
                //$this->DebugData($StoryTitle, '---AndFilterMatch---AndFilters: '.$AndFilter.' ', 1);
                if (!$Skipitem && $AutoImport) {
                       echo " Matched AND filter:".$AndFilter." ";
                }
            }
            //
            if (!$Skipitem && $Minwords != '') {
                //$this->DebugData($Minwords, '---Minwords---', 1);
                if (str_word_count(strip_tags($StoryBody))<$Minwords) {
                    //$this->DebugData($StoryBody, '---Minwords Not Matched---', 1);
                    if ($AutoImport) {
                        echo " Did not match minimum number of words:".$Minwords." ";
                    }
                    $Skipitem = true;
                    $NumSkippedItems += 1;
                    $NumFilterFailedItems +=1;
                } else {
                    //$this->DebugData($StoryBody, '---Minwords Match---', 1);
                    if ($AutoImport) {
                        echo " Matched minimum number of words:".$Minwords." ";
                    }
                }
            }
            if (!$Skipitem) {
                //$this->DebugData($StoryTitle, '---StoryTitle---', 1);
                //$ParsedStoryBody = '<div class="AutoFeedDiscussion">'.$StoryBody.
                //'</div> <br/><div class="AutoFeedSource">Source: '.$FeedItemGUID.'</div>';
                $ParsedStoryBody = '<div class="AutoFeedDiscussion">'.$StoryBody.'</div>';

                $DiscussionData = array(
                            'Name'          => $StoryTitle,
                            'Format'        => 'Html',
                            'CategoryID'     => $Feed['Category'],
                            'ForeignID'      => substr($FeedItemID, 0, 30),
                            'Type'             => 'Feed',
                            'Body'          => $ParsedStoryBody,
                            'Attributes' => array(
                                'FeedURL'            => $FeedURL,
                                'Itemurl'            => $Itemurl,
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
                $DiscussionData[$DiscussionModel->DateUpdated] = $StoryPublished;
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
                    setValue('Type', $DiscussionModel, 'Feed');

                    $InsertID = $DiscussionModel->Save($DiscussionData);
                    if ($InsertID) {
                        //var_dump ($InsertID);
                    } else {
                         echo '<br>'.__LINE__.' Failed save';
                         $NumSavedItems -= 1;
                    }
                    $LastSavedItemPubTime = date('c', $ItemPubTime);
                    $NumSavedItems += 1;
                    //$this->DebugDatl($DiscussionData["Name"], '---Saved...---', 1);
                    //die(0);
                    $this->EventArguments['DiscussionID'] = $InsertID;
                    $this->EventArguments['Vaidation'] = $DiscussionModel->Validation;
                    $this->FireEvent('Published');

                    // Reset discussion validation
                    $DiscussionModel->Validation->Results(true);
                }
            }
            //$LastPublishDate = date('c', $FeedLastPublishTime);
            $LastPublishDate = date('Y-m-d H:i:s', $FeedLastPublishTime);
            $LastImport = date('Y-m-d H:i:s');
            //$this->DebugData($LastSavedItemPubTime, '---LastSavedItemPubTime---', 1);
            //$this->DebugData($LastPublishDate, '---LastPublishDate---', 1);
            //$this->DebugData($LastImport, '---LastImport---', 1);
            $FeedKey = self::EncodeFeedKey($FeedURL);
            $this->UpdateFeed($FeedKey, array(
                'LastImport'     => $LastImport,
                'FeedURL'     => $FeedURL,
                'RSSimage'     => $Logo,
                'Historical'     => false,
                'Encoding'     => $Encoding,
                'Compressed'    => $FeedRSS['Compressed'],
                'LastPublishDate' => $LastPublishDate
            ));
        }
        //
        if ($AutoImport) {
            echo '<span><br>&nbsp&nbsp&nbsp'.$NumCheckedItems. ' items processed, '.
                 $NumSavedItems . ' items saved, ' .
                 $NumSkippedItems . ' skipped ('.
                 $NumAlreadySavedItems . ' previously saved, '. $NumSkippedOldItems . ' old items. '.
                 $NumFilterFailedItems . " Didn't match filters)</span>";
        }
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
        $FeedURL = $this->rebuildurl($FeedURL, '');
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
        $Feed = array_merge($Feed, $FeedOptionKey);
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
        $Feed = $this->getdiscussionfeed($Discussion);    //Try to get feed info for the current discussion
        if (!$Feed) {
            return;
        }
        //
        $FeedURL = $Feed['FeedURL'];
        $RSSimage = $Feed['RSSimage'];
        //$this->DebugData($RSSimage, '---RSSimage---', 1);
        //$this->DebugData($FeedURL, '---FeedURL---', 1);
        if (!$FeedURL | !$RSSimage) {       //if there is no url or image ignore this disussion
            return;
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
        //echo '<!--debug '.__LINE__.' --> ';
        //highlight_string(__LINE__.$Logo);
        if ($Type == 'list') {            //Discussion list
            if (!$Feed['Getlogo']) {
               //echo '<!--debug '.__LINE__.' --> ';
                return;
            }
            echo anchor($Logo, '/discussion/'.$Discussion->DiscussionID);
            return;
        } else {                         //Showing a specific discussion
            //echo '<!--debug '.__LINE__.' --> ';
            if ($Feed['Getlogo']) {
                if ($Itemurl) {            //Show logo with a link
                    echo wrap(
                        wrap(
                            ' '.anchor($Logo, $Itemurl, ' ', array('rel' => 'nofollow', 'target' => '_BLANK')),
                            'span',
                            array('class' => 'RSSsource')
                        ),
                        'span',
                        array('class' => 'RSSlogobox')
                    );
                    //echo '<!--debug '.__LINE__.' --> ';
                } else {                    //Show logo without a link
                    echo wrap(wrap($Logo, 'span', array('class' => 'RSSsource')), 'span', array('class' => 'RSSlogobox'));
                }
            }
            if ($Itemurl) {
                echo wrap(
                    wrap(
                        ' '.anchor(T('Imported from:').' '.$Feed["Feedtitle"], $Itemurl, ' ', array('rel' => 'nofollow', 'target' => '_BLANK')),
                        'span',
                        array('class' => 'RSSsource')
                    ),
                    'span',
                    array('class' => 'RSSsourcebox')
                );
            } else {
                echo wrap(wrap(' '.T('Imported from:').' '.
                               $Feed["Feedtitle"], 'span', array('class' => 'RSSsource')), 'span', array('class' => 'RSSsourcebox'));
            }
        }
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
            return null;
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
        }
        if (!$Feed['Getlogo']) {
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
* Insert feed image info in a specific discussion.
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
* @param string $Url Feed's url
*
* @return logo url
*/
    private function getlogo($Url) {
        //$this->DebugData($Url, '---Url---', 1);
        $Parsedurl = @parse_url($Url);
        //$this->DebugData($Parsedurl, '---Parsedurl---', 1);
        if (!$Parsedurl["scheme"]) {
            $Url = $this->rebuildurl($Url, 'HTTP');   //For the purpose of the logo we don't care it it's https
            $Parsedurl = @parse_url($Url);
            //$this->DebugData($Parsedurl, '---Parsedurl---', 1);
        }
        //$this->DebugData($Url, '---Url---', 1);
        $Ignorepattern = '/(.doubeclick|.staticworld|feedproxy.google|feedburner.com|blogspot.com|.rackcdn|empty|twitter|facebook|google_plus|linkedin|vulture)/i'; //Ignore few logo domains
        if (preg_match($Ignorepattern, $Url)) {
            //$this->DebugData($Url, '---Url in the ignore logo list---', 1);
            if (preg_match('/(feedproxy.google|blogspot.com)/i', $Url)) {
                $Logo = 'plugins/FeedDiscussionsPlus/design/feedburner.png';
            } elseif (preg_match('/(feedburner.com)/i', $Url)) {
                $Logo = 'plugins/FeedDiscussionsPlus/design/feedburner.png';
            } else {
                $Logo = 'plugins/FeedDiscussionsPlus/design/feedblank.png';
            }
            //$this->DebugData($Logo, '---Returning logo---', 1);
            return $Logo;
        }
        //$this->DebugData($Domain, '---Domain---', 1);
        if ($Parsedurl["host"]) {
            $Logo = 'https://logo.clearbit.com/' . $Parsedurl["host"];
            if ($this->iswebUrl($Logo)) {
                //$this->DebugData($Logo, '---logo---', 1);
                $Logo = $Logo . '?s=46';
            } else {
                $Logo = 'https://www.google.com/s2/favicons?domain_url='.$Parsedurl["host"];
                //$this->DebugData($Logo, '---logo---', 1);
            }
        } else {
            $Logo = '';
        }
        //$this->DebugData($Logo, '---Returning logo---', 1);
        //highlight_string($Logo);
        //var_dump($Logo)
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
* Check if url is accessible
*
* @param string $Url       Url to reformat
* @param string $Setscheme Scheme to use in reformatted utl
*
* @return reformatted url
*/
    private function rebuildurl($Url, $Setscheme = '') {
        //$this->DebugData($Url, '---Url---', 1);
        $Domain = @parse_url($Url, PHP_URL_HOST);
        $Path   = @parse_url($Url, PHP_URL_PATH);
        if ($Setscheme) {
            $Url = $Setscheme . '://' . $Domain . $Path;
        } else {
            $Url = $Domain . $Path;
        }
        //$this->DebugData($Url, '---Url---', 1);
        return $Url;
    }
/**
* Try to discover feed for a url
*
* @param object $Sender    Standard
* @param string $Websource Web source for which to check for referral to a feed url
*
* @return string url of feed referred to by the passed web source
*/
    private function discoverfeed($Sender, $Websource) {
        //Check for feed url within the web page itself:
        //<link rel="alternate" type="application/rss+xml" title="whateve.r." href="url of feed" />
        //<link rel="alternate" type="application/rss+xml" title="blah" href="http://feeds.feedburner.com/something">
        $Headtag = $this->getbetweentags($Websource, 'head');
        //echo '<br>'.__LINE__.': '.substr(htmlspecialchars($Headtag),0,1500);
        $Results = $this->getwithintag($Headtag, 'link');
        //$this->DebugData($Results, '---Results---', 1);
        $Foundlinktag = false;
        foreach ($Results as $Link) {
            preg_match_all('/(\w+)\s*=\s*(?|"([^"]*)"|\'([^\']*)\')/', $Link, $Sets, PREG_SET_ORDER);
            //$this->DebugData($Sets, '---Sets---', 1);
            foreach ($Sets as $Keywords) {
                if (strtolower($Keywords[1])== 'rel' && strtolower($Keywords[2]) == 'alternate') {
                    //$this->DebugData($Keywords, '---Keywords---', 1);
                    $Foundlinktag = true;
                } elseif ($Foundlinktag && strtolower($Keywords[1])== 'href') {
                    //$this->DebugData($Keywords, '---Keywords---', 1);
                    if (stripos($Keywords[2], 'feedburner.com')) {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        return trim($Keywords[2]).'?format=xml';
                    } else {
                        //$this->DebugData($Keywords, '---Keywords---', 1);
                        return trim($Keywords[2]);
                    }
                }
            }
        }
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
            $this->postmsg($Sender, $Msg);
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
        if ($Msg) {
            $this->postmsg($Sender, $Msg);
        }
        $View = $this->getView($Viewname);
        $Sender->render($View);
    }
/**
* Que a message to be displayed on the next page
*
* @param object $Sender Standard
* @param string $Msg    Message to display on the displayed url
*
* @return none
*/
    private function postmsg($Sender, $Msg) {
        $Sender->InformMessage($Msg);
        $Sender->SetData('Qmsg', $Msg);
        $this->SetStash($Msg, 'Qmsg');
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
            'Minwords'       => (integer)$FormPostValues["Minwords"],
            'Maxitems'       => (integer)$FormPostValues["Maxitems"],
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
                    'Refresh'     => '1d',
                    'Category'    => -1,
                    'OrFilter'    => '',
                    'AndFilter'   => '',
                    'Active'      => 1,
                    'Activehours' => '05-22',
                    'Minwords'    => '10',
                    'Maxitems'    => '20',
                    'LastImport'  => "never",
                    'Getlogo'     => true,
                    'Noimage'     => false,
                    'Added'       => date('Y-m-d H:i:s'),
              );
    }
/**
* Fetch RSS/Atom feed from the supplied Url
*
* @param string $Url         The feed Url
* @param bool   $Data        Request to fetch feed data into its array
* @param bool   $SSLNoVerify Request to bypass SSL verification
* @param bool   $AutoImport  Autoimport request
*
* @return Array - Feed information array
*/
    private function getfeedrss($Url, $Data = false, $SSLNoVerify = false, $AutoImport = false) {
        //$this->DebugData('','',true,true);
        //$this->DebugData($Url, '---Url---', 1);
        //$this->DebugData($SSLNoVerify, '---SSLNoVerify---', 1);
        //$this->DebugData($AutoImport, '---AutoImport---', 1);
        //    Set response defaults
        $Response = array(
            'URL' => $Url,
            'SuggestedURL' => '',
            'Action' => '',
            'Error' => '',
            'Encoding' => 'N/A',
            'Updated' => '',
            'Title' => '',
            'RSSimage' => '',
            'RSSdata' => ''
        );
        //
        $Proxy = new ProxyRequest();
        $FeedRSS = $Proxy->Request(array(
         'URL' => $Url, 'Debug' => false, 'SSLNoVerify' => $SSLNoVerify
        ));
        //
        $Status = $Proxy->ResponseStatus;
        $Statustext = $Proxy->ResponseBody;
        //decho ($Proxy->ResponseHeaders);
        $Zipped = $Proxy->ResponseHeaders["Content-Encoding"];
        $Response['Compressed'] = $Zipped;
        if ($Zipped == 'gzip') {              //Compressed site?
            $FeedRSS = gzdecode($FeedRSS);    //Uncompress it
        }
        //$this->DebugData($Statustext, '---Statustext---', 1);
        //$this->DebugData(htmlspecialchars(substr($FeedRSS,0,500)), '---FeedRSS(0:500)---', 1);
        //decho ($Proxy->ContentType);//e.g. "application/atom+xml;charset=utf-8" or "application/rss+xml; charset=utf-8" or "text/xml; charset=utf-8"
        //decho ($Proxy->responseClass);
        //decho ($Proxy->ResponseStatus);    //e.g. 200 (OK), etc.
        //decho ($FeedRSS['error']);                    // "<"
        //decho ($FeedRSS['error_description']);        // "<"
        if ($Status == 200 | substr($Statustext, 0, 1) == '<') {  //It's what we expect
            $Statustext = '';
        }
        //$this->DebugData($Statustext, '---Statustext---', 1);
        if ($Statustext) {
            //decho ($Pr);
            //if (!$SSLNoVerify &&
            //    (0 === strpos($Statustext, 'SSL certificate problem: unable to get local issuer certificate'))) {
            //
            if (!$SSLNoVerify &&
                (0 === strpos($Statustext, 'SSL certificate problem:'))) {
                $Url = $this->rebuildurl($Url, 'https');
                return $this->getfeedrss($Url, $Data, true, $AutoImport);
            }
            if ($Status) {
                $Response['Error'] = 'Error ' . $Status . ': '. $Statustext;
            } else {
                $Response['Error'] = $Statustext;
            }
            if (0 === strpos($Statustext, 'error:14077458:SSL routines:SSL23_GET_SERVER_HELLO:tlsv1 unrecognized name')) {
                $Url = $this->rebuildurl($Url, 'https');
                return $this->getfeedrss($Url, $Data, true, $AutoImport);
            }
        } else {
            //echo '<br>'.__LINE__;
            $Encoding = $this->getencoding($FeedRSS);
            if ($Encoding == 'BadURL') {
                $Response['Error'] = __LINE__." Url not found or is not a feed.";
                return $Response;
            }
            if ($Encoding == 'HTML') {
                $Response['Error'] = __LINE__." URL  looks like this is a web page, not a feed.";
                //Check for feed url within the web age itself:
                //<link rel="alternate" type="application/rss+xml" title="whateve.r." href="url of feed" />
                $SuggestedURL = trim($this->discoverfeed($Sender, $FeedRSS));
                if ($SuggestedURL) {        //Discovered implied feed?
                    $Response['Error'] = "Consider the suggested feed instead:".$SuggestedURL;
                    //$this->DebugData($Feedurl, '---Feedurl---', 1);
                    $Response['URL'] = $SuggestedURL;
                    $Response['SuggestedURL'] = $SuggestedURL;
                }
                return $Response;
            }
            //$this->DebugData($Encoding, '---Encoding---', 1);
            if ($Encoding == 'Atom' | $Encoding == 'RSS' | $Encoding == 'New') {
                $Response['Encoding'] = $Encoding;
            } else {
                //$this->DebugData($Encoding, '---Encoding---', 1);
                $Response['Error'] = ' ,'.__LINE__." URL content is not a recognized feed (code ".$Encoding.")";
                if ($AutoImport) {
                    echo '<br>'.$Response['Error'];
                    echo '<br>'.__LINE__.' URL first 1500 characters:<b>'.htmlspecialchars(substr($FeedRSS, 0, 1500)).'</b><br>';
                }
                return $Response;
            }
            //$this->DebugData($Encoding, '---Encoding---', 1);
            libxml_use_internal_errors(true);
            $RSSdata = simplexml_load_string($FeedRSS);//."<broken><xml></broken>");
            //$this->DebugData(gettype($RSSdata), '---gettype(/$RSSdata)---', 1);
            if ($RSSdata === false) {
                $RSSdata = $FeedRSS;        //Aggressive mode...
                //$this->DebugData($RSSdata, '---RSSdata---', 1);
                if (true == false) {    //Turn to true to display xml failure info
                    echo "<br>".__LINE__."Failed loading XML ";
                    $Response['Error'] = __LINE__." URL content is not a valid formatter feed";
                    foreach (libxml_get_errors() as $error) {
                        echo "<br>", $error->message;
                    }
                    return;
                }
            }
            //$this->DebugData(htmlspecialchars(substr($FeedRSS,0,500)), '---FeedRSS(0:500)---', 1);
            if ($Data) {
                $Response['RSSdata']  = $RSSdata;
            }
            //$this->DebugData($RSSdata, '---RSSdata---', 1);
            //$this->DebugData(htmlspecialchars(substr($RSSdata,0,1500)), '---RSSdata(0:1500)---', 1);
            //
            if ($Encoding == 'Atom') {
                $Updated = (string)$this->getentity($RSSdata, 'updated', 'channel.lastBuildDate', $FeedRSS, '');
            } elseif ($Encoding == 'New') {        //Enter appropriate keys to read this encoding
                $Updated = (string)val('updated', $RSSdata, '');
            } else {
                $Encoding = 'RSS';
                $Updated = (string)valr('channel.lastBuildDate', $RSSdata, $this->getbetweentags($FeedRSS, 'lastBuildDate'));
            }
            $Response['Encoding'] = $Encoding;
            $Title = (string)$this->getentity($RSSdata, 'title', 'channel.title', $FeedRSS, '');
            if (c('Plugins.FeedDiscussionsPlus.GetLogo', false)) {
                $Link = (string)valr('link', $RSSdata, $this->getbetweentags($FeedRSS, 'link'));
                //$this->DebugData($Link, '---Link---', 1);
                if ($Link == '') {
                    $Link = $Url;
                }
                //$this->DebugData($Link, '---Link---', 1);
                $Response['RSSimage'] = $this->Getlogo($Link);
                //$this->DebugData($Response['RSSimage'], "---\$Response['RSSimage']---", 1);
            }
            //
            //$this->DebugData($Updated, '---Updated---', 1);
            $date = new DateTime($Updated);
            $Updated = $date->format("Y-m-d H:i:s");
            //$this->DebugData($Updated, '---Updated---', 1);
            $Response['Updated'] =  $Updated;
            $Response['Title'] = $Title;
            //$this->DebugData($Channel, '---Channel---', 1);
            //$this->DebugData($Title, '---Title---', 1);
        }
        //$Response['RSSdata'] = 'testing';
        //$this->DebugData(htmlspecialchars($Response['RSSdata']), '---Response[RSSSdata]---', 1);
        //$this->DebugData($Response['Channel'], '---Response[Channel]---', 1);
        //
        return $Response;
    }
/**
* Get the encoding format for a web page
*
* @param object $FeedRSS page source
*
* @return string - feed enclding
*/
    private function getencoding($FeedRSS) {
        //$this->DebugData('','',true,true);
        $Tags = array(    'Atom' => '<Feed',
                        'RSS' => '<rss',
                        'HTML' => "<!DOCTYPE html"
                        );
        //  Search assists are urls displayed by service providers when a url is invalid (or blocked)/
        //  They mask real "not found" errors because they appear to be valid urls.
        //  The following code segment handles search assists.
        //  You can add additional search asisst urls to the "Verizon" example
        $Searchassists = array(
                '<meta http-equiv="refresh" content="0;url=http://searchassist.verizon.com');
        //echo '<p>'.__LINE__.' '.htmlspecialchars(substr($FeedRSS,0,1000)).'</p>';
        foreach ($Tags as $Encoding => $Identifier) {
            //$this->DebugData($Encoding, '---Encoding---', 1);
            //$this->DebugData(htmlspecialchars($Identifier), '---Identifier---', 1);
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
                return $Encoding;
            }
        }
        //echo '<p>'.__LINE__.' '.htmlspecialchars(substr($FeedRSS,0,100)).'</p>';
        return 'Unknown Format';
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
        return $Matches[1];
    }
/**
* Stash a value
*
* @param string $Search Value to stash
* @param string $Type   Keyword type (Key identifier)
*
* @return none
*/
    private function setstash($Search, $Type = 'Msg') {
        Gdn::session()->stash("IBPDfeed".$Type, $Search);
        return;
        //Use the cookie method
        setcookie("IBPDfeed".$Type, $Search, time() + (30), "/");
    }
/**
* Fetch a stashed value
*
* @param string $Type Keyword type (Key identifier)
*
* @return string
*/
    private function getstash($Type = 'Msg') {
        return Gdn::session()->stash("IBPDfeed".$Type);
        //Use the cookie method
        if (!isset($_COOKIE["IBPDfeed".$Type])) {
            return '';
        } else {
            $Search = $_COOKIE["IBPDfeed".$Type];
            $this->SetStash('');
            return $Search;
        }
    }
/**
* Remove scripts from source url
*
* @param string $Source source url
*
* @return string
*/
    private function clearJavascript($Source) {
        return preg_replace('/<script\b[^>]*>(.*?)<\/script>/is', "", $Source);
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
        echo ('<br>'.__LINE__.' '.substr(strip_tags($Source), 0, 120).'<br>');
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
              $Msg .= " It is required for the Feed Discussions Plus plugin.";
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
        touchConfig('Plugins.FeedDiscussionsPlus.Nosort', false);
        touchConfig('Plugins.FeedDiscussionsPlus.GetLogo', true);
        touchConfig('Plugins.FeedDiscussionsPlus.Userinitiated', 'true');
        touchConfig('Plugins.FeedDiscussionsPlus.croncode', 'secret');
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
* Debuggin function
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
        $Color = 'color:red;';
        if ($Message == '') {
            $Message = '>'.debug_backtrace()[1]['class'].':'.
              debug_backtrace()[1]['function'].':'.debug_backtrace()[0]['line'].
              ' called by '.debug_backtrace()[2]['function'].' @ '.debug_backtrace()[1]['line'];
            $Color = 'color:blue;';
        } else {
            $Message = '>'.debug_backtrace()[1]['class'].':'.debug_backtrace()[1]['function'].':'.debug_backtrace()[0]['line'].' '.$Message;
        }
        if ($Inform == true) {
            Gdn::controller()->informMessage($Message);
        }
        echo '<pre style="font-size: 1.3em;text-align: left; padding: 0 4px;'.$Color.'">'.$Message;
                    Trace(__LINE__.' '.$Message.' '.$Data);
        if ($Data != '') {
            var_dump($Data);
        }
        echo '</pre>';
        //
    }
    /////////////////////////////////////////////////////////
}
