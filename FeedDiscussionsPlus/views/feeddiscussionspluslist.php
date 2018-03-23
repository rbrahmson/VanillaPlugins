<?php if (!defined('APPLICATION')) exit();
    echo $this->Form->Open();
    echo $this->Form->errors();
    $Qmsg = FeedDiscussionsPlusPlugin::getmsg('', 'GETVIEW'.__FUNCTION__.__LINE__);
    //
    $Categories = CategoryModel::Categories();
    $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
    $Title = $Plugininfo["Name"];
    $Version = $Plugininfo["Version"];
    $IconUrl = $Plugininfo["IconUrl"];
    $Hideinactive = c('Plugins.FeedDiscussionsPlus.Hideinactive', false);
    //
    $Refreshments = array(
               "1m"  => T("Every&nbspMinute"),
               "5m"  => T("Every&nbsp5&nbspMinutes"),
               "30m" => T("Twice&nbspHourly"),
               "1h"  => T("Hourly"),
               "1d"  => T("Daily"),
               "3d"  => T("Every&nbsp3&nbspDays"),
               "1w"  => T("Weekly"),
               "2w"  => T("Every&nbsp2&nbspWeeks"),
               "3w"  => T("Every&nbsp3&nbspWeeks"),
               "4w"  => T("Every&nbsp4&nbspWeeks"),
               "Monday"  => T("Every&nbspMonday"),
               "Tuesday"  => T("Every&nbspTuesday"),
               "Wednesday"  => T("Every&nbspWednesday"),
               "Thursday"  => T("Every&nbspThursday"),
               "Friday"  => T("Every&nbspFriday"),
               "Saturday"  => T("Every&nbspSaturday"),
               "Sunday"  => T("Every&nbspSunday"),
               "Manually"  => 'Manually&nbspvia&nbspthe&nbspCheck&nbspActive&nbspFeed&nbspNow&nbspbutton',
            );
    //
    $Serverhour = (integer)date('H');
    $Sessionhour = ((integer)(Gdn::session()->User->HourOffset)+(integer)date('H'));
    $Settiontime = strtotime((Gdn::session()->User->HourOffset)." hours", strtotime(date('Y-m-d H:i:s', time())));
    if ($Serverhour != $Sessionhour) {
        $Hournote = '<span class="Serverhour" style="Float:right;">Server hour:<b>'.$Serverhour.
            '</b>, your session hour:<b>'.$Sessionhour.'</b></span>';
    } else {
        $Hournote = '';
    }
    //
    $Sorts = array(
               "Feedtitle"  => T("Title"),
               "NextImport"  => T("Next import"),
               "LastImport"  => T("Last import"),
               "Category"  => T("Category"),
               "URL"  => T("url"),
               "Encoding"  => T("Feed type"),
               "Nosort"  => T("Not sorted"),
            );
    //
    $Feedsarray = $this->Data('Feeds');
    $NumFeeds = count($Feedsarray);
    $Readytoimport = 0;
    $NumFeedsActive = count(array_keys(array_column($Feedsarray, 'Active'), true));
    $Sortbutton = '';
    $Searchbutton = '';
    $Searchfor = '';
    $Searchformsg = '';
    $Sortby = c('Plugins.FeedDiscussionsPlus.Sortby', 'Feedtitle');
    if ($NumFeeds > 2) {
        $Defaulsortby = c('Plugins.FeedDiscussionsPlus.Sortby', 'Feedtitle');
        if (IsMobile()) {
            $Sortclick = "(Select sort order and press";
            $Searchclick = '<i class="fas fa-search"></i>(enter text and and press';
        } else {
            $Sortclick = "(Select sort order and click";
            $Searchclick = '(enter text and and click';
        }
        $Sortbutton = '<span class="Sortby">'.
                           $this->Form->Label('Sort by', 'Sortby').
                           $this->Form->DropDown('Sortby', $Sorts, array('value'  => $Defaulsortby)).$Sortclick.
                           $this->Form->button("🔃 Sort", array('type' => 'submit', 'name' => 'Sort', 'class' => 'Button Sortbutton')).
                           " )</span>";
        //
        $Searchfor = $this->Data('Searchfor');
                           //$this->Form->Label('Search', 'Searchfor').
        $Searchbutton = '<span class="Sortby">'.
                           $this->Form->TextBox('Searchfor', array('class' => 'InputBox ', 'maxlength' => 200, 'value' => $Searchfor, 
                           "title" => t('Search in the feed title and url'))).
                           $this->Form->button('🔍', array('type' => 'submit', 'name' => 'Search', 'class' => ' ', "title" => t('Search in the feed title and url'), 'style' => "min-width: 1px;")).
                           "</span>";
        //
        if ($Sortby != 'Nosort') {
            usort($Feedsarray, function ($a, $b) use ($Sortby, $Categories) {
                if ($a['Active'] == $b['Active']) {
                    if ($Sortby == 'Category') {
                        return val('Name', $Categories[$a['Category']], '?') > val('Name', $Categories[$b['Category']], '?');
                    } else {
                        return (strtolower($a[$Sortby]) > strtolower($b[$Sortby]));
                    }
                }
                return ($a['Active'] < $b['Active']);
            });
        }
    }
    //
    if ($NumFeedsActive) {
        $Headmsg = "<span>".$NumFeeds." ".
              Plural($NumFeeds, "Defined Feed ", "Defined Feeds ").
              ',  '.$NumFeedsActive." ".
              Plural($NumFeedsActive, "Active Feed ", "Active Feeds ").
              "</span>".$Sortbutton.$Searchbutton.$Hournote;
    } elseif ($NumFeeds) {
        $Headmsg  = "<span>".$NumFeeds." ".
              Plural($NumFeeds, "Inactive Feed ", "Inactive Feeds ").
              "</span>".$Sortbutton.$Searchbutton.$Hournote;
    } else {
        $Headmsg =  T("Add your first feed import definition:");
        //echo $Importbutton;  Import is not fully tested yet so this is disabled
    }
    //
    if ($Qmsg) {
        $Titlemsg = '<br><div id=popmsg class=ffqmsg>'.$Qmsg.'</div>';
    } else {
        $Titlemsg = '<span id=popmsg></span>';
    }
    $Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
    echo '<div id=FDP Class=fdplist><div Class=xUnPopup>';
    echo '<h1 title="'.$Sourcetitle.'"><!–– '.$Sourcetitle.' L#'.__LINE__.' ––>';
    echo '<span class=selflogo> <img src="'.url('plugins/FeedDiscussionsPlus/icon.png').'"></span> '.
          $Title . ' (Version ' . $Version.')   '.
          '&nbsp&nbsp&nbsp&nbsp&nbsp   <FFtitle>'.' '.
          $Headmsg.'</FFtitle>'.$Titlemsg.'</h1>';
    echo $this->Form->Errors();
    //
    $Popup = c('Plugins.FeedDiscussionsPlus.Popup', '');
    $Globalbutton = '<a class="Button ffcolumn '.$Popup.' " href="' . Url('/plugin/feeddiscussionsplus/global').
       '" title="' . t('Global settings for all the feeds').'"><FFBLUE><b>✨</b></FFBLUE> Global Settings</a>';
        //
    $RestoreFeedURL = trim($this->Data('RestoreFeedURL'));
    if ($RestoreFeedURL) {
        $Restorestyle = 'ffcolumn ffundelete';
    } else {
        $Restorestyle = 'ffcolumn ffhiddenlb';
    }
    $Restorebutton = '<a class="Button ' . $Restorestyle . '" href="' . Url('/plugin/feeddiscussionsplus/restorefeed/'.$RestoreFeedURL).
          '" title="' . t('Restore recently deleted feed').' '.$RestoreFeedURL.'"> ↻ '.t("Undo Delete").'</a>';
    //
    if (c('Vanilla.Comment.UserPhotoFirst',false)) {
        echo '<FFline><b>Note</b>: "Vanilla.Comment.UserPhotoFirst" is set to "true". This disables the plugin\'s ability to display the feeds logos. See "Readme" for more information';
    }
    //
    if (!$NumFeeds) {
        $Readmebutton = '';
        $Addbutton = '&nbsp';
    } else {
        $Readmebutton =   '<a class="Button ffcolumn  " href="' .
          Url('/plugin/feeddiscussionsplus/Readme').
          '" title="' . t('You should read this before starting').
          '"><FFBLUE><b>❓</b></FFBLUE> Readme</a>';
        $Addbutton = '<a class="Button ffcolumn" href="' .
          Url('/plugin/feeddiscussionsplus/Addfeed').
          '" title="' . t('Create import definition for a new feed').
          '"> ➕ '.t("Add Feed").'</a>';
        $Refreshbutton = '<a class="  Button ffcolumn" href="' .
          Url('/plugin/feeddiscussionsplus/listfeeds').'?'.__LINE__.'" title="'.
          t('Refresh this screen following changes in other browsers/tabs').
          '"> ♺ '.t("Refresh").'</a>';
    }
    if ($NumFeedsActive) {
        $Checkfeedsbutton = '<span id=CheckImport0><a id=CheckImport class="Button ffcolumn '.$Popup .
          '" href="' . Url('/plugin/feeddiscussionsplus/CheckFeeds/backend').
          '" target=_blank title="' . t('Initiate all feeds check to load new articles').
          '">Check Active Feeds Now <FFBLUE>➤↗</FFBLUE></a></span>';
        Gdn::session()->stash("FDPbackend", 'Backend');
    } else {    //No active feeds
        Gdn::session()->stash("FDPbackend", 'Inactive');
        $Checkfeedsbutton = '<a class="Button ffcolumn ffdisablelb" href="' .
            Url('/plugin/feeddiscussionsplus/listfeeds').
            '"  title="' . t('Disabled until you have active feeds').
            '">Check Active Feeds Now <FFBLUE>➤↗</FFBLUE></a>';
    }
    if (IsMobile() && !$Cron) {
        Gdn::session()->stash("FDPbackend", 'Mobile');
        $Checkfeedsbutton = '<a class="Button ffcolumn ffdisablelb" style="visibility:hidden;" href="' .
            Url('/plugin/feeddiscussionsplus/listfeeds').
            '"  title="' . t('Only enabled in desktop environment').
            '">Check Active Feeds Now <FFBLUE>âž¤â†—</FFBLUE></a>';
    }
    //
    $Importbutton =  '<br>If you have used the other <b>'.
        '<a target=_BLANK href="https://open.vanillaforums.com/addon/feeddiscussions-plugin" >FeedDiscussion</a></b> plugin in the past, you can '.
        '<a class="Button ffcolumn" href="'.
        Url('/plugin/feeddiscussionsplus/GetOldFeeds').
        '" title="Note: Import attempt is not guaranteed to succeed."><i>try to Import</i></a> the old import definitions.';
    //
    echo '<FFHEAD>';
    echo '<div Class="ffspread">'.$Addbutton.$Restorebutton.$Refreshbutton.$Checkfeedsbutton.$Globalbutton.$Readmebutton;
    //echo $Importbutton;  Import is not fully tested yet so this is disabled
    echo '</div>';
    $pluginlist = explode(',', 'FeedDiscussions,MagpieRss');  //Add more if new ones are added
    foreach ($pluginlist as $pluginname) {
        if (c('EnabledPlugins.'.$pluginname)) {
            echo '<br>Note: The <a target=_BLANK href="'.
                url('/plugin/'.$pluginname).'" >'.$pluginname.
                '</a> plugin is enabled.  It is a <i>different</i> plugin and it operates independently of this plugin.';
        }
    }
    echo ' </FFHEAD>';
    if ($Hournote) {
        echo '<span class=RSServertime>Server date/time:&nbsp&nbsp'.date('Y-m-d H:i:s', time()).
                '<br>Session date/time:'.date('Y-m-d H:i:s', $Settiontime).
                '</span>';
    } else {
       echo '<span class=RSServertime>Server date/time:&nbsp&nbsp'.date('Y-m-d H:i:s', time()).
                '</span>';
    }
    echo '<div class="ActiveFeeds">';
    //
    $Hides = 0;
    if (!$NumFeeds) {
        echo '<ffcenter>'.'Read the customization guide and then add your first feed'.'</ffcenter>';
        $Addbutton = '<a class="Button ffhighlightbutton" href="' .
              Url('/plugin/feeddiscussionsplus/Addfeed/Add').
              '" title="' . t('Create import definition for a new feed').
              '"> ➕ '.t("Add Feed").'</a>';
        echo $Addbutton;
        echo '<div id=HELP><div class=ffembedhelp>';
        include_once "AddfirstGuide.htm";
        echo 'For detailed information see the full' .
            '<a href="'.Url('/plugin/feeddiscussionsplus/Readme').
            '" > Customization Guide.';
        echo '</div></div>';
    } else {
        echo '<div class="FDPtable"><!--'.__LINE__.'  -->';
        foreach ($Feedsarray as $FeedURL => $FeedItem) {
            $FeedURL = $FeedItem['FeedURL'];
            $Active = $FeedItem['Active'];
            $Hide = false;
            if ($Hideinactive AND $Active) {
                $Hide = true;
            }
            if ($Searchfor) {
                if (stripos(' '.$FeedItem['Feedtitle'].' '.$FeedItem['InternalURL'].' '.$FeedItem['FeedURL'], $Searchfor) == false) {
                    $Hide = true;
                    $Hides += 1;            
                    $Searchformsg = '('.$Hides.' feed(s) hidden by search for "'.$Searchfor.'")';
                }
            }
            if (!$Hide) {
                $FeedKey = $FeedItem['FeedKey'];
                if (!isset($FeedItem['Compressed'])) {
                    $FeedItem['Compressed'] = '';
                }
                if (!isset($FeedItem['InternalURL'])) {
                    $FeedItem['InternalURL'] = '';
                }
                if (!isset($FeedItem['LastPublishDate'])) {
                    $FeedItem['LastPublishDate'] = '';
                }
                if (!isset($FeedItem['NextImport'])) {
                    $FeedItem['NextImport'] = '';
                }
                $LastPublishDate = $FeedItem['LastPublishDate'];
                $LastImport = $FeedItem['LastImport'];
                $NextImport = $FeedItem['NextImport'];
                $Category = val('Name',$Categories[$FeedItem['Category']], '??');
                $AnchorUrl = $FeedItem['FeedURL'];
                $AnchorUrl = $FeedURL;
                $Encoding = $FeedItem['Encoding'];
                $Feedtypeicon = "";
                if ($Encoding == "RSS" | $Encoding == "Rich RSS") {
                    $Feedtypeicon = '<i class="fas fa-rss-square icon" > </i>';
                } elseif ($Encoding == "Atom" | $Encoding == "Rich Atom") {
                    $Feedtypeicon = '<i class="fas fa-rss icon" > </i>';
                } elseif($Encoding == "Youtube") {
                    $Feedtypeicon = '<i class="fab fa-youtube icon" > </i>';
                } elseif($Encoding == "Instagram") {
                    $Feedtypeicon = '<i class="fab fa-instagram icon" > </i>';
                } elseif($Encoding == "Twitter") {
                    $Feedtypeicon = '<i class="fab fa-twitter-square icon" > </i>';
                }
                $EncodingMsg = '<span class="Encodingbe">'.$FeedItem['Compressed'].
                                ' '.$FeedItem['Encoding'].' feed</span>';
                if ($FeedItem['Encoding'] == 'Twitter') {
                    $AnchorUrl = 'twitter.com/'.$FeedURL;
                } elseif ($FeedItem['Encoding'] == '#Twitter') {
                    $AnchorUrl = 'twitter.com/hashtag/'.substr($FeedURL,1);
                }
                $FeedItemStyle = '';
                $FeedItemClass = 'FDPtable-row';
                $Resetbutton = '<FFDISABLE title="Disabled button">↺ Schedule</FFDISABLE>';
                $Resetstyle = 'Button UpdateFeed ffdisabled';
                $Resettitle = t('Disabled button');
                $Resettaction = '/listfeeds?'.__LINE__;
                $Resetelement = 'div';
                if ($Active) {
                    $Activemsg = '<span class="Activebe"><FFActive>Active</FFActive></span>';
                    $Togglebutton = '<a class="Button UpdateFeed" href="'.
                                    Url('/plugin/feeddiscussionsplus/togglefeed/'.$FeedKey).
                                    '" title="'.t("Deactivate feed but keep it's definition").
                                    '">⛔ Deactivate</a>';
                    if ($NextImport != '' && $NextImport != 'never' && ($NextImport >  date('Y-m-d H:i:s', time()))) {
                        $Resetstyle = 'Button UpdateFeed ';
                        $Resettitle = t('Schedule feed import time ASAP');
                        $Resettaction = 'resetfeed/'.$FeedKey;
                        $Resetelement = 'a';
                    }  else {
                        $Readytoimport += 1;
                    }
                } else {    //Not Active
                    $FeedItemStyle = ' Style="background: whitesmoke;"';
                    $FeedItemClass = 'FDPtable-row-inactive';
                    $Activemsg = '<span class="Inactivebe">Inactive</FFInactive></span>';
                    $Togglebutton = '<a class="Button UpdateFeed" href="'.
                            Url('/plugin/feeddiscussionsplus/togglefeed/'.$FeedKey).
                            '" title="'.t('Activate feed').
                            '">✔&nbsp&nbsp&nbspActivate&nbsp&nbsp&nbsp&nbsp</a>';
                }
                $Resetbutton = '<' . $Resetelement . ' class="'. $Resetstyle . ' " href="'.
                                      Url('/plugin/feeddiscussionsplus/'.$Resettaction).
                                      '" title="'.$Resettitle.
                                      '">↺ Schedule </' . $Resetelement . '>';
                $Editbutton = '<a class="Button UpdateFeed  " id=displayonform href="'.
                              Url('/plugin/feeddiscussionsplus/updatefeed/'.$FeedKey).
                              '" title="'.t('Edit feed definitions').
                              '"><FFBLUE>📄</FFBLUE> Edit</a>';
                $Modelbutton = '<a class="Button UpdateFeed" id=displayonform href="'.
                              Url('/plugin/feeddiscussionsplus/loadfeedform/'.$FeedKey).
                              '/model" title="'.t('Load definition on the form above to allow additions').
                              '"><FFBLUE>📄⤴</FFBLUE> Use as model</a>';
                $Modelbutton = '';    //Future development
                $Deletebutton = '<a class="Button UpdateFeed" href="'.
                              Url('/plugin/feeddiscussionsplus/deletefeed/'.$FeedKey).
                              '" title="'.t('Careful...'). '">✘ Delete</a>';
                $Getlogo = $FeedItem['Getlogo'];
                $Logowrapclass = 'RSSlogowrap';
                $Logoimgclass =  'RSSlistlogo';
                $Logowrapclass = 'FDPlogowrap FDPlogowrapbend';
                $Logoimgclass =  'FDPlogobend';
                $Addspan = '';
                /*if (!$Getlogo) {
                    //$Logowrapclass = $Logowrapclass . ' RSSlistlogooff';
                    $Logowrapclass = $Logowrapclass . ' FDPlogooffbend';
                }*/
                if ($FeedItem['RSSimage']) {
                    if ($FeedItem['Encoding'] == "xxxTwitter") {
                        $Addspan =  '<span id=RSSatsign class="FDPatsign FDPatsignbend" title="'.$FeedItem['Feedtitle'].'">@</span>';
                        //$Logowrapclass = $Logowrapclass . ' RSSlogolist Twitterlistlogo ';
                        //$Logowrapclass = $Logowrapclass . ' FDPTwitterwrap ';
                        $Logo = '<img src="' .
                                $FeedItem['RSSimage'] . '" id=RSSimage class=FDPlogobend title="' .
                                $FeedItem['Feedtitle'] . '" > ' . $Addspan; 
                    } else {
                        $Logo = '<img src="' .
                                $FeedItem['RSSimage'] . '" id=RSSimage class=FDPlogobend title="' .
                                $FeedItem['Feedtitle'] . '" > ';
                    }
                    $Logo = '<img src="' .
                                $FeedItem['RSSimage'] . '" id=RSSimage class=FDPlogobend title="' .
                                $FeedItem['Feedtitle'] . '" > ';
                    $Bottom = 40;
                    if ($FeedItem['Encoding'] == "Twitter") {
                        $Logo = $Logo . '<img class=FDPatsignbendimg style="Bottom:' . $Bottom . 'px;" id=FDPatsignbendimg src="' . 
                                url('plugins/FeedDiscussionsPlus/design/TwitterMark.png') . 
                                '" >' ;
                        $Bottom += 16;
                    } elseif ($FeedItem['Encoding'] == "Instagram") {
                        $Logo = $Logo . '<img class=FDPinstasignbendimg style="Bottom:' . $Bottom . 'px;" id=FDPinstasignbendimg src="' . 
                                url('plugins/FeedDiscussionsPlus/design/instalogoc.jpg') . 
                                '" >' ;
                        $Bottom += 16;
                    }
                    if (!$Getlogo) {
                        /*$Logo = $Logo . ' <span class="FDPnologobend" <img class="FDPnologobendimg" id=FDPnologobendimg src="' . 
                                url('plugins/FeedDiscussionsPlus/design/unimage.png') . 
                                '" ></span>' ;
                        */
                        $Logo = $Logo . '<img class=FDPnologobendimg style="Bottom:' . $Bottom . 'px;" id=FDPnologobendimg src="' . 
                                url('plugins/FeedDiscussionsPlus/design/unimage.png') . 
                                '" >' ;
                        $Bottom += 40;
                    }
                    $Logo = '<span class="'.$Logowrapclass.'" id=FDPlogowrap>'.$Logo.' </span> ';
                } else {
                    $Logo = '';
                }
                //
                $OrFilter = $FeedItem['OrFilter'];
                $AndFilter = $FeedItem['AndFilter'];
                $Minwords = $FeedItem['Minwords'];
                $Historical = $FeedItem['Historical'];
                $Refresh = $FeedItem['Refresh'];
                $Noimage = $FeedItem['Noimage'];
                $Maxitems = $FeedItem['Maxitems'];
                $Activehours = $FeedItem['Activehours'];
                $InternalURL = $FeedItem['InternalURL'];
                $Ftitle = (string)$FeedItem['Feedtitle'];
                $Feedtag = val('Feedtag',$FeedItem, null);
                $Frequency = GetValue($Refresh, $Refreshments, T('Unknown'));
                if (c('Plugins.FeedDiscussionsPlus.showurl', false)) {
                     $Internalurlmsg = ' Url:'.$InternalURL;
                } else {
                     $Internalurlmsg = '';
                }
                $Buttons = '<span class="RSSbuttonboxbe" >'.
                    $Editbutton.
                    $Resetbutton.
                    $Togglebutton.
                    $Modelbutton.
                    $Deletebutton.
                    '</span>';
                $Leftblock = '<span class="RSSleftblock">'.$Logo.$Activemsg.'</span> ';
                $Rigtblock = '<span class="RSSrightblock">'.$Buttons. '</span> ';
                $Leftblock = '<span class="FDPtable-cell-left">'.$Logo.
                             '<span class="FDPlogotextbend">'.$Activemsg.$EncodingMsg.'</span> </span> ';
                $Rigtblock = '<span class="FDPtable-cell-right">'.$Buttons. '</span> ';
                echo '<div class="'.$FeedItemClass.'" >';
                //
                echo $Leftblock;
                //--------- Middle Block----
                //Calculate next import message
                $Diffmsg = '';
                $Nextmsg = '';
                if ($Active) {
                    $Timedate = date('Y-m-d H:i:s', time());
                    $Todaydt = new DateTime($Timedate);
                    $Nextdt = new DateTime($NextImport);
                    $Diff = date_diff($Nextdt, $Todaydt, true);
                    $Diffdays = (int) $Diff->format("%a");
                    $Diffmsg = '<ffcircle style="background:gray;">'.$Diffdays.'</ffcircle> days until import';
                    //////////
                    if ($Refresh == "Manually") {
                        $Diffmsg = '';
                        $Nextmsg =  '<span class="Attrbe" title="Click the \'Check Active Feeds Now\' button initiate import"><FFGRAY>●</FFGRAY><b>Manual&nbspimport</b></span>';
                    } elseif ($Timedate > $NextImport) {
                        $Diffmsg = '<span class=Diffmsgoverdue>➤ Ready</span>';
                        $Nextmsg = '<ffred>●</ffred><b>Next import:</b>'.$NextImport;
                    } elseif ($Diffdays == 1) {
                        $Diffmsg = '<ffcircle style="background:orange;">'.$Diffdays.'</ffcircle> day until import';
                        $Nextmsg = '<fforange>●</fforange><b>Next import:</b>'.$NextImport;
                    } elseif ($Diffdays == 0) {
                        $Diffhours = (int) $Diff->format("%h");
                        $Diffmsg = '<ffcircle style="background:green;">'.$Diffhours.'</ffcircle> hours until import</i>';
                        $Nextmsg = '<ffgreen>●</ffgreen><b>Next import:</b>'.$NextImport.'</span>';
                        if ($Diffhours == 0) {
                            $Diffminutes = (int) $Diff->format("%i");
                            if ($Diffminutes) {
                                $Diffmsg = '<ffcircle style="background:green;">'.$Diffminutes.'</ffcircle> minutes until import</i>';
                            } else {
                                $Diffmsg = '<span class=Diffmsgoverdue>➤ Ready</span>';
                                $Nextmsg = '<ffred>●</ffred><b>Next import:</b>'.$NextImport;
                            }
                        }
                    } else {
                        $Nextmsg = '<ffgray>●</ffgray><b>Next import:</b>'.$NextImport.'</span>';
                    }
                } else {
                    $Diffmsg = '<span > Inactive </span>';
                }
                echo '<span class="FDPtable-cell"><!--'.__LINE__.'  -->';
                echo '<span class="Diffmsg">'.$Diffmsg.'</span>';
                echo    '<span class="RSSdetailbe"><!--'.__LINE__.'  -->'.
                    '<div class="FeedItemTitle"><FFBLUE>'.$Feedtypeicon.' '.
                    $FeedItem["Feedtitle"].'</FFBLUE>   </div>'.
                 '<div class="FeedContent"><!--'.__LINE__.'  -->'.
                    '<div class="FeedItemURL"><!--'.__LINE__.'  -->';
                echo Anchor($FeedURL, 'http://'.$AnchorUrl, ["target" => "_blank"]).$Internalurlmsg.'</div>';
                if (c('Plugins.FeedDiscussionsPlus.showkey', false)) {
                    echo '<span style="color:red !important;"> FeedKey:'.$FeedKey.'&nbsp </span>';
                }
                echo '<div class="FeedItemInfo"><!--'.__LINE__.'  -->';
                if ($LastImport != 'never' && $LastImport != '') {
                    echo '<span class="Attrbe"><b>Last&nbspImport:</b>'.$LastImport.'</span>';
                } else {
                    $LastImport = 'never';
                    echo '<span class="Attrbe" ><b>Last&nbspImport:</b><span><FFInactive>Ø&nbspnot&nbspyet</FFInactive></span></span>';
                }
                echo '<span class="Attrbe" >'. $Nextmsg .'</span>';
                /*if ($Active) {
                    $Timedate = date('Y-m-d H:i:s', time());
                    $Todaydt = new DateTime($Timedate);
                    $Nextdt = new DateTime($NextImport);
                    $Diff = date_diff($Nextdt, $Todaydt, true);
                    $Diffdays = (int) $Diff->format("%a");
                    if ($Refresh == "Manually") {
                    } elseif ($Diffdays == 0) {
                        if ($Timedate > $NextImport) {
                            echo '<span class="Attrbe" ><ffred>●</ffred><b>Next import:</b>'.$NextImport.'</span>';
                        } else {
                            echo '<span class="Attrbe" ><ffgreen>●</ffgreen><b>Next import:</b>'.$NextImport.'</span>';
                        }
                    } elseif ($Diffdays == 1) { //Import due tomorrow
                        echo '<span class="Attrbe" ><fforange>●</fforange><b>Next import:</b>'.$NextImport.'</span>';
                    } else {    //Import due in few days
                        echo '<span class="Attrbe" ><ffgray>●</ffgray><b>Next import:</b>'.$NextImport.'</span>';
                    }
                }
                */
                if ($Refresh == "Manually") {
                    echo '<span class="Attrbe" title="Click the \'Check Active Feeds Now\' button initiate import"><FFGRAY>●</FFGRAY><b>Manual&nbspimport</b></span>';
                } else {
                    echo '<span class="Attrbe" ><b>Refresh:</b>&nbsp'.trim($Frequency).'</span>';
                }
                echo '<span class="Attrbe" ><b>Category:</b>&nbsp'.trim($Category).'</span>';
                if ($OrFilter) {
                    echo '<span class="Attrbe"><b>OR&nbspFilter:</b>&nbsp'.$OrFilter.'</span>';
                }
                if ($AndFilter) {
                    echo '<span class="Attrbe"><b>AND&nbspFilter:</b>&nbsp'.$AndFilter.'</span>';
                }
                if ($Minwords) {
                    echo '<span class="Attrbe" ><b>Min.&nbspWords:</b>&nbsp'.$Minwords.'</span>';
                }
                if ($Maxitems) {
                    echo '<span class="Attrbe"><b>Max&nbspItems:</b>&nbsp'.$Maxitems.'</span>';
                }
                if ($Activehours) {
                    echo '<span class="Attrbe"><b>Active&nbspbetween:</b>&nbsp'.$Activehours.'</span>';
                }
                if ($Feedtag) {
                    echo '<span class="Attrbe"><b>Tagged as:</b>&nbsp'.$Feedtag.'</span>';
                }
                if ($Historical) {
                    echo '<span class="Attrbe"><b>Note:</b>&nbspHistorical&nbspposts&nbsprequested on&nbspnext&nbspfeed&nbspimport.</span>';
                }
                if ($Getlogo) {
                    echo '<span class="Attrbe">Showing&nbspthe&nbspfeed\'s&nbsplogo.</span>';
                }
                if ($Noimage) {
                    echo '<span class="Attrbe">Removing&nbspimages&nbspon&nbspimport.</span>';
                }
                echo '<div>';
                echo '</div><!--'.__LINE__.'  -->'.
                    '</div><!--'.__LINE__.'  -->'.
                '</div><!--'.__LINE__.'  -->'.
              '</span><!--'.__LINE__.'  -->'.
            '</span><!--'.__LINE__.'  -->'.
            $Rigtblock.
          '</div><!--'.__LINE__.'  -->';
        } //Show inactive text
      } //Foreach loop
   }   //
      if ($NumFeeds && !IsMobile()) {
            echo '<div id=CheckImport1 style="display:table-caption;text-align:center;min-width: 200px;"><span id=CheckImport2 >'.
              $Readytoimport.
              '</span>'.Plural($Readytoimport, " feed is", " feeds are").
              ' ready for import '.$Searchformsg.' </div>';
      }
  echo '</div> <!--'.__LINE__. '  -->';
 echo '</div> <!--'.__LINE__.'  -->';
      if (!IsMobile() && $Readytoimport == 0) {
        echo '<script type="text/javascript">';
        $js = 'var text="";
                $("#CheckImport2").each(function(){
                    if ($(this).text() == "0") {
                       $("#CheckImport1").css("color", "red");
                       //$("#CheckImport2").css("background", "red");
                       $("#CheckImport").attr("Class", "Button ffcolumn ffdisablelb");
                       //$("#CheckImport").replaceWith("<span>No feeds are ready for import</span>" );
                    } else {
                        text=text+" 1 "+$(this).text();
                        $("#CheckImport1").css("color", "unset");
                        //$("#CheckImport2").css("background", "unset");
                        $("#CheckImport").css("visibility", "unset");
                    }
                });';
        //
        echo $js;
        echo '</script>';
        /*
        echo '';
        echo '$("#CheckImport2").css("visibility", "unset");';
        echo '$("#CheckImport2").css("color", "red");';
        echo '</script>';
      } else {
        echo '<script type="text/javascript">';
        echo '$("#CheckImport2").css("visibility", "unset");';
        echo '$("#CheckImport2").css("color", "unset");';
        echo '</script>';
      */
      }
 echo $this->Form->close;
 /*
 ?>
 <script>
var myFunc = $.popup.close;
$.popup.close = function () {
  myFunc.apply(this, arguments);
  location.reload();
};
</script>
<?php
*/
 ?>