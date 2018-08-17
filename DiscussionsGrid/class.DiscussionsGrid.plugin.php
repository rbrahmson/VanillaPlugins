<?php
/**
 * Plugin to display Discussion Lists in a responsive grid format and on different themes..
 *
 */
$PluginInfo['DiscussionsGrid'] = array(
    'Name' => 'DiscussionsGrid',
    'Description' => 'Display Discussion Lists in a responsive grid format and on different themes.',
    'Version' => '2.2.6',
    'RequiredApplications' => array('Vanilla' => '2.3'),
    'RequiredTheme' => false,
    'MobileFriendly' => true,
    'HasLocale' => false,
    'usePopupSettings' => false,
    'SettingsUrl' => '/settings/DiscussionsGrid',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'RegisterPermissions' => array('Plugins.DiscussionsGrid.PermissionGrid','Plugins.DiscussionsGrid.PermissionToggle'),
    'Author' => "Roger Brahmson",
    'GitHub' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/DiscussionsGrid",
    'License' => 'GPLv2'
);
/**
* Plugin to display Discussion Lists in a responsive grid-like format.
*/
class DiscussionsGridPlugin extends Gdn_Plugin {
/**
* Add plugin menu for the settingsController.
*
* @param Standard $Sender Standard
* @param string   $Menu   Menu link
*
*  @return boolean n/a
*/
    private function addpluginmenu($Sender, $Menu) {
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Sender->setHighlightRoute($Menu);
        } else {
            $Sender->addSideMenu($Menu);
        }
    }
/**
* Render plugin view.
*
* @param Standard $Sender   Standard
* @param string   $Viewname Name of view to render
* @param string   $Path     Path to the view
*
*  @return boolean n/a
*/
    private function renderpluginview($Sender, $Viewname, $Path) {
        $Sender->render($this->getView($Viewname));
    }
/**
* Return event argument object (compatibility layer for Vanilla pre 2.5).
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
* @param string   $Object "Discussion" or "Comment"
*
*  @return boolean n/a
*/
    private function getobjectfromevent($Sender, $Args, $Object = "Discussion") {
        // New method (only in 2.5+)
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            if (isset($Args[$Object])) {
              return $Args[$Object];
            } else {
              Return null;
            }
        }
        if (isset($Sender->EventArguments[$Object])) {
            return $Sender->EventArguments[$Object];
        } else {
            return null;
        }
    }
/**
* Set the CSS.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function assetModel_styleCss_handler($Sender) {
        //$this->DebugData('','',true,true);
        $Sender->addCssFile('discussionsgrid.css', 'plugins/DiscussionsGrid');
    }
/**
* Plugin setup.
*
*  @return boolean n/a
*/
    public function setup() {
        touchconfig('Plugins.DiscussionsGrid.PermissionGrid', true);
        touchconfig('Plugins.DiscussionsGrid.PermissionToggle', true);
        touchconfig('Plugins.DiscussionsGrid.Textsize', 0);
        touchconfig('Plugins.DiscussionsGrid.Showimage', false);
        touchconfig('Plugins.DiscussionsGrid.Hidecounts', false);
        touchconfig('Plugins.DiscussionsGrid.Showtags', false);
        touchconfig('Plugins.DiscussionsGrid.SkipAnnouncements', false);
        touchconfig('Plugins.DiscussionsGrid.Onlytype', false);
        touchconfig('Plugins.DiscussionsGrid.Listicon', '<span class="gridicontoggle">☰</span>');
        touchconfig('Plugins.DiscussionsGrid.Gridicon', '<span class="gridicontoggle">☷</span>');
        touchconfig('Plugins.DiscussionsGrid.List', "List");
        touchconfig('Plugins.DiscussionsGrid.Grid', "Grid");
        touchconfig('Plugins.DiscussionsGrid.Minimagesize', "20");
    }
/**
* Admin dashboard settings.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function settingscontroller_discussionsgrid_create($Sender, $Args) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Sender->permission('Garden.Settings.Manage');
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('discussionsgrid');
        $Msg = '<span><img height="30px" src="'. url("/plugins/DiscussionsGrid/icon.png") .'"> </span>'.
                $Plugininfo["Name"] . ' Plugin (Version:' . $Plugininfo["Version"]. ') Settings<br><center>'.
               $Plugininfo["Description"].'</center>';
        $Sender->Title($Msg);
        $this->addpluginmenu($Sender, 'Appearance/DiscussionsGrid');
        $Sender->addCssFile('discussionsgrid.css', 'plugins/DiscussionsGrid');
        $Roleslink = ' '.anchor("Roles and Permissions", '/dashboard/role', '  PopupWindow',
                    array('rel' => 'nofollow'));
        $Separator = '';
        $Showtagswarning = '';
        $Tagicon = wrap(" ", 'span', array('class'  => "gridtagstring")).                
                    wrap("    ", 'span',array('class'  => "gridtagicon"));
        //
        if (version_compare(APPLICATION_VERSION, '2.5', '>=')) {    //If anyone wants to downgrage this plugin to older Vanilla...
            $Taggingsetting = 'Tagging.Discussions.Enabled';
            $Tagsettinglink = '<a class="buttontagaside PopupWindow"  target=_BLANK href="' .
                    Url('/settings/tagging') . '" title="' . t('Click to access the Tagging Settings').'">'.
                    t('enable tagging').'</a>';
        } else {
            $Taggingsetting = 'EnabledPlugins.Tagging';
            $Tagsettinglink = 'enable the tagging plugin';
        }
        if (!c($Taggingsetting, false)) {
            $Showtagswarning = '<br><B>Note:</B>Tagging is not currently enabled. You must '.$Tagsettinglink.' to activate this option' ;
        }
        // Get all categories.
        $Categories = CategoryModel::categories();
        // Remove the "root" category from the list.
        unset($Categories[-1]);
        //
        $ConfigurationModule = new ConfigurationModule($Sender);
        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $Sender->Form->SetModel($ConfigurationModel);
        $PermissionGrid = $PermissionToggle = $Showimage = $Textsize = $Textifnoimage = $Hidecounts = $Showtags = $SkipAnnouncements = null;
        $ConfigurationModule->Schema(array(
            'Plugins.DiscussionsGrid.PermissionGrid' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Check to require permission to display grid</B>.<br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp(Set "PERMISSIONGRID" in '.$Roleslink.')',
              'Items' => $PermissionGrid,
              'Description' => $Separator.'<h2><span style="display:initial;color:#0291db">'.strip_tags(c('Plugins.DiscussionsGrid.Gridicon', "☷")).'</span> View grid</h2>',
              'Default' => true),
            'Plugins.DiscussionsGrid.PermissionToggle' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Check to equire permission to show list/grid toggle menu</B>.<br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp(Set "PERMISSIONTOGGLE" in '.$Roleslink.')',
              'Items' => $PermissionToggle,
              'Description' => $Separator.'<h2><span style="display:initial;color:#0291db">'.
                        strip_tags(c('Plugins.DiscussionsGrid.Gridicon', "☷")." / ".c('Plugins.DiscussionsGrid.Listicon', "☰")).
                        "</span>  Toggle menu</h2>",
              'Default' => true),
            'Plugins.DiscussionsGrid.Showimage' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Display embedded discussion image in grid </B>(if image is embedded)',
              'Items' => $Showimage,
              'Description' => '<h2><span style="display:initial;color:#0291db">🖼</span> Display image in grid view</h2>',
              'Default' => true),
            'Plugins.DiscussionsGrid.Textsize' => array(
              'Control' => 'TextBox',
              'Description' => '<B>Length of discussion excerpt to display </B>(enter zero to disable excerpt display in grid)'.
                            $Separator,
              'Items' => $Textsize,
              'LabelCode' => $Separator.'<h2><span style="display:initial;color:#0291db">▤</span> Display excerpt in grid view</h2>',
              'Default' => "0"),
            'Plugins.DiscussionsGrid.Textifnoimage' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Display excerpt only if no image is displayed in the grid</B>'.
                            $Separator,
              'Items' => $Textifnoimage,
              'Description' => $Separator.'<h2><span style="display:initial;color:#0291db">‽▤</span> Conditional excerpt</h2>',
              'Default' => true),
            'Plugins.DiscussionsGrid.Hidecounts' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Hide views and comment counts</B> (Test on your active theme before disabling)'.
                            $Separator,
              'Items' => $Hidecounts,
              'Description' => $Separator.'<h2><span style="display:initial;color:#0291db">∅</span> Hide counts</h2>',
              'Default' => true),
            'Plugins.DiscussionsGrid.Showtags' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Display discussion tags</B> (for tagged discussions)'.$Showtagswarning.
                            $Separator,
              'Items' => $Showtags,
              'Description' => $Separator.'<h2><span style="display:initial;color:#0291db">'.$Tagicon.'</span> Display tags</h2>',
              'Default' => true),
            'Plugins.DiscussionsGrid.SkipAnnouncements' => array(
              'Control' => 'CheckBox',
              'LabelCode' => "<B>Don't display announcements as grid items </B><br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp(Announcements normally appear on top)".$Separator,
              'Items' => $SkipAnnouncements,
              'Description' => $Separator.'<h2><span style="display:initial;color:#0291db">↷</span> Keep Announcements intact</h2>',
              'Default' => true),
            /*
            'Plugins.DiscussionsGrid.Onlytype' => array(
              'Control' => 'TextBox',
              'LabelCode' => '<B>Limit grid view to specified discussion type (or leave blank for every type.)</B>'.
                            $Separator,
              'Items' => $Onlytype,
              'Description' => $Separator.
                  '&nbsp&nbsp&nbspLimit Grid view to discussions with specific type&nbsp&nbsp&nbsp'.
                  '<br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp🔶 (no selection enables grid on all types)&nbsp&nbsp&nbsp',
              'Default' => " "),
            */
            'Plugins.DiscussionsGrid.CategoryNums' => array(
                  'Control' => 'CheckBoxList',
                  'LabelCode' => '<h2>&nbsp&nbsp&nbsp&nbsp&nbsp<span style="display:initial;color:#0291db">❐</span> Applied Categories</h2>',
                  'Items' => $Categories,
                  'Description' => $Separator.
                  '<br>&nbsp&nbsp&nbsp(Limit Grid view to discussions in specific categories:&nbsp&nbsp&nbsp'.
                  '<br>&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp🔶 no selection enables grid on all categories)&nbsp&nbsp&nbsp',
                  'Options' => array('ValueField' => 'CategoryID', 
                                      'TextField' => 'Name', 
                                      'class' => 'Categorylist CheckBoxList',
                                      'ID'    =>  'GridCatList'),
                ),
        ));
        if ($Sender->Form->authenticatedPostBack()) {
            $FormValues = $Sender->Form->formValues();
            //
            $PermissionGrid = getvalue('Plugins.DiscussionsGrid.PermissionGrid', $FormValues);
            $PermissionToggle = getvalue('Plugins.DiscussionsGrid.PermissionToggle', $FormValues);
            $Showimage = getvalue('Plugins.DiscussionsGrid.Showimage', $FormValues);
            $Textsize = getvalue('Plugins.DiscussionsGrid.Textsize', $FormValues);
            $Textifnoimage = getvalue('Plugins.DiscussionsGrid.Textifnoimage', $FormValues);
            $Hidecounts = getvalue('Plugins.DiscussionsGrid.Hidecounts', $FormValues);
            $Showtags = getvalue('Plugins.DiscussionsGrid.Showtags', $FormValues);
            $SkipAnnouncements = getvalue('Plugins.DiscussionsGrid.SkipAnnouncements', $FormValues);
            $Onlytype = getvalue('Plugins.DiscussionsGrid.Onlytype', $FormValues);
            $CategoryNums = getvalue('Plugins.DiscussionsGrid.CategoryNums', $FormValues);
            //
            $this->SaveconfigValue($Sender, $PermissionGrid, 'Plugins.DiscussionsGrid.PermissionGrid');
            $this->SaveconfigValue($Sender, $PermissionToggle, 'Plugins.DiscussionsGrid.PermissionToggle');
            $this->SaveconfigValue($Sender, $Showimage, 'Plugins.DiscussionsGrid.Showimage');
            $this->SaveconfigValue($Sender, $Textsize, 'Plugins.DiscussionsGrid.Textsize');
            $this->SaveconfigValue($Sender, $Textifnoimage, 'Plugins.DiscussionsGrid.Textifnoimage');
            $this->SaveconfigValue($Sender, $Hidecounts, 'Plugins.DiscussionsGrid.Hidecounts');
            $this->SaveconfigValue($Sender, $Showtags, 'Plugins.DiscussionsGrid.Showtags');
            $this->SaveconfigValue($Sender, $SkipAnnouncements, 'Plugins.DiscussionsGrid.SkipAnnouncements');
            $this->SaveconfigValue($Sender, $Onlytype, 'Plugins.DiscussionsGrid.Onlytype');
            $this->SaveconfigValue($Sender, $CategoryNums, 'Plugins.DiscussionsGrid.CategoryNums');
            //
            $SaveMsg = t('Your settings were saved');
            Gdn::controller()->informMessage($SaveMsg);
        } else {    // Not postback
            $Sender->Form->setValue('Plugins.DiscussionsGrid.PermissionGrid', c('Plugins.DiscussionsGrid.PermissionGrid', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.PermissionToggle', c('Plugins.DiscussionsGrid.PermissionToggle', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.Showimage', c('Plugins.DiscussionsGrid.Showimage', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.Textsize', c('Plugins.DiscussionsGrid.Textsize', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.Textifnoimage', c('Plugins.DiscussionsGrid.Textifnoimage', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.Hidecounts', c('Plugins.DiscussionsGrid.Hidecounts', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.Showtags', c('Plugins.DiscussionsGrid.Showtags', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.SkipAnnouncements', c('Plugins.DiscussionsGrid.SkipAnnouncements', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.Onlytype', c('Plugins.DiscussionsGrid.Onlytype', []));
            $Sender->Form->setValue('Plugins.DiscussionsGrid.CategoryNums', c('Plugins.DiscussionsGrid.CategoryNums', []));
        }
        $ConfigurationModule->renderAll();
    }
/**
* Hook to display discussion image and/or content extract in grid.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return none
*/
    public function discussionsController_afterDiscussionTitle_handler($Sender, $Args) {
        //$this->DebugData('','',true,true);
        $Discussion = $Sender->EventArguments['Discussion'];
        if (!$this->iseligible($Sender, $Discussion, "T")) {
            return;
        }
        $Shown = $this->get_image($Sender, $Args) ;
        if ($Shown && c('Plugins.DiscussionsGrid.Textifnoimage', false)) {   //Image shown and text shown only if no image
            // Future use on this condition
        } else {
            $this->get_text($Sender, $Args) ;
        }
        //Show tags is tagging is active an tag showing is set
        if (version_compare(APPLICATION_VERSION, '2.5', '>=')) {    //If anyone wants to downgrage this plugin to older Vanilla...
            $Taggingsetting = 'Tagging.Discussions.Enabled';
        } else {
            $Taggingsetting = 'EnabledPlugins.Tagging';
        }
        if ((c($Taggingsetting, false)) && (c('Plugins.DiscussionsGrid.Showtags', false))) {
            if (isset($Discussion->Tags) && count($Discussion->Tags)) {
                $Taglist = wrap(t('Tags:'), 'b', array('class'  => "Meta Meta-Discussion gridtaglabel"));
                foreach ($Discussion->Tags as $Tag) {
                    $Taglist .= wrap(" ", 'span', array('class'  => "Meta Meta-Discussion gridtagstring")).                
                        anchor(wrap($Tag['FullName'], 'span',array('class'  => "Meta Meta-Discussion gridtags")), '/discussions/tagged/'.$Tag['Name']);
                }
                echo wrap($Taglist, 'div');
            }
        }
        //decho (array_keys(get_object_vars($Discussion)));
        $Fieldlist = c('Plugins.DiscussionsGrid.Showfields', array());
        foreach ($Fieldlist as $Pairs) {
            $Array = explode("=", $Pairs);
            $Field = $Array[0];
            $Name = $Array[1];
            if (isset($Discussion->$Field) && ($Discussion->$Field)) {
                if ($Name) {
                    echo wrap(t($Name).":", 'b', array('class'  => "Meta Meta-Discussion gridtaglabel"));
                }
                $this->showfield($Discussion->$Field, $Field);
                
                if ($Name) {
                    echo"<br>";
                }
            }
        }
    }
/**
* display array content in grid.
*
* @param object $Field      Discussion field 
* @param object $Name      Discussion field name
*
* @return none
*/
    private function showfield($Field, $Name) {
        if (is_array($Field)) {
            foreach ($Field as $Subfield) {
                $this->showfield($Subfield, $Name);
            }
        } elseif (is_string($Field)) {
            echo wrap($Field . ' ', 'span',array('class'  => "Meta Meta-Discussion gridfield".$Name));
        } else {
            //echo wrap($Field . '?', 'span',array('class'  => "Meta Meta-Discussion gridfield".$Name));
        }
    }
/**
* Hook to display discussion image and/or content extract in grid.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return none
*/
    public function categoriescontroller_AfterDiscussionTitle_handler($Sender, $Args) {
        $this->discussionsController_afterDiscussionTitle_handler($Sender, $Args);
    }
/**
* Fetch and possibly display discussion image in grid.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return true if image is shown
*/
    public function get_image($Sender, $Args) {
        //$this->DebugData('','',true,true);
        if (!c('Plugins.DiscussionsGrid.Showimage', false)) {
            return false;
        }
        $Link = '/discussion/'.$Sender->EventArguments['Discussion']->DiscussionID;
        $Body = $Sender->EventArguments['Discussion']->Body;
        $i = stripos($Body, "<img");
        if ($i === false) {
            //echo "no img";
            //$this->DebugData(substr($Source,0,200), '---Source 0:200---', 1);
            return false;
        }
        //$this->DebugData($Tag2, '---Tag2---', 1);
        $Image = substr($Body, $i+4);
        $i = stripos($Image, ">");
        if ($i === false) {
            //echo "no img end";
            return false;
        }
        $Image = substr($Image, 0, $i);
        /*$Image = trim($this->getbetweentexts($Sender->EventArguments['Discussion']->Body, '<img', '>'));
        if ($Image == '') {
            //$this->DebugData($Image, '---Image---', 1);
            return false;
        }
        */
        $i = preg_match_all('/src=|.jpg|.png|.gif/', $Image, $Types, PREG_OFFSET_CAPTURE+PREG_SET_ORDER);
        if ($i === 0) {
            return false;
        }
        //echo "<br>".__LINE__."<br>";
        if (!isset($Types[0][0][1])) {
            //echo "no src=";
            return false;
        }
        //var_dump ($Types[0][0][1]);
        //echo "<br>".__LINE__."<br>";
        //var_dump ($Types[1][0][1]);
        if (!isset($Types[1][0][1])) {
            //echo "bad img type";
            return false;
        }
        $Imageurl = trim(substr($Image, 4+$Types[0][0][1], $Types[1][0][1]-$Types[0][0][1]),'"');
        $Imageurl = trim($Imageurl,"'");
        //var_dump($Imageurl);
        //$this->DebugData($Imageurl, '---Imageurl---', 1);
        $Size=getimagesize($Imageurl);
        //echo "<br>".__LINE__." size:".var_dump($Size);
        //$this->DebugData($Size, '---Size---', 1);
        $Minimagesize = c('Plugins.DiscussionsGrid.Minimagesize', "20");
        if ($Size[0]< $Minimagesize || $Size[1]< $Minimagesize) {
            //$this->DebugData($Size[0], '---$Size[0]---', 1);
            return false;
        }
        //
        if ((c('Plugins.DiscussionsGrid.Textsize', false)) && (c('Plugins.DiscussionsGrid.Textifnoimage', false))) {
            $Title = $this->compactblanks(html_entity_decode(strip_tags($Sender->EventArguments['Discussion']->Name)));
            $Body  = $this->compactblanks(html_entity_decode(strip_tags($Sender->EventArguments['Discussion']->Body)));
            $Titlelen = strlen($Title);
            $Bubble = sliceString($Body, (int)3*$Titlelen);
        } else {
            $Bubble = '';
        }
        //
        $Image = '<img ' . $Image . ' >';
        //
        $Pattern = "/(<img\s+).*?src=((\".*?\")|(\'.*?\')|([^\s]*)).*?>/is";
        $Style = '<img class="gridimage" src=$2>';
        $Image =  preg_replace($Pattern, $Style, $Image);
        echo anchor(wrap($Image, 'div', ['Title' => $Bubble]), $Link);
        return true;
    }
/**
* Fetch and possibly display discussion text in grid.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return flag whether text was emitted
*/
    public function get_text($Sender, $Args) {
        //$this->DebugData('','',true,true);
        $Textsize = c('Plugins.DiscussionsGrid.Textsize', false);
        if (!$Textsize) {
            return false;
        }
        $Title = $this->compactblanks(html_entity_decode(strip_tags($Sender->EventArguments['Discussion']->Name)));
        $Body  = $this->compactblanks(html_entity_decode(strip_tags($Sender->EventArguments['Discussion']->Body)));
        $Link = '/discussion/'.$Sender->EventArguments['Discussion']->DiscussionID;
        $Titlelen = strlen($Title);
        $Bubble = sliceString($Body, (int)3*$Titlelen);
        //$this->DebugData($Visiblebody, '---Visiblebody---', 1);
        $i = min($Textsize, $Titlelen);
        $Excerpt = substr(trim($Body), 0, $i);
        if (!strlen(trim($Excerpt,' '))) {
            return false;
        }
        if ($Bubblelen <= $Textsize) {
            $Bubble = '';
        }
        if (strtolower(substr(trim($Title), 0, $i)) != strtolower($Excerpt)) {
            $Msg = wrap(sliceString($Body, $Textsize), 'div',
                    array('class'  => "gridbodytext",
                          'title'  => $Bubble
                    ));
        } else {                    //If content is repeat of title then display content past that title
            $Msg =  wrap(sliceString('<b>...</b>'.substr($Body, ($Titlelen)), $Textsize), 'div', array('class'  => "gridbodytext"));
        }
        echo anchor($Msg, $Link);
        return true;
    }
/**
* Insert Span id tag to allow css manipulation.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return none
*/
    public function discussionsController_beforeDiscussionName_handler($Sender, $args) {
        //$this->DebugData('','',true,true);
        //echo __LINE__.strtoupper(substr(__FUNCTION__,0,1));
        $this->InsertSpanID($Sender, strtoupper(substr(__FUNCTION__,0,1)), '.'.__LINE__);
        echo '<span class="GridTitleWrap" ><!-- '.__LINE__.' --> ';
    }
/**
* Insert Span id tag to allow css manipulation.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return none
*/
    public function categoriescontroller_beforeDiscussionName_handler($Sender, $args) {
        //$this->DebugData('','',true,true);
        //echo __LINE__.strtoupper(substr(__FUNCTION__,0,1));
        $this->InsertSpanID($Sender, strtoupper(substr(__FUNCTION__,0,1)), '.'.__LINE__);
        echo '<span class="GridTitleWrap" ><!-- '.__LINE__.' --> ';
    }
/**
* Insert Span id tag to allow css manipulation.
*
* @param object $Sender     standard
* @param string $Caller   Indicate the caller (discussion/categories)
* @param string $Notation Comment
*
* @return none
*/
    private function InsertSpanID($Sender, $Caller = "D", $Notation = '') {
        //$this->DebugData('','',true,true);
        if ($this->iseligible($Sender, $Sender->EventArguments['Discussion'], $Caller)) {
            echo '<div id=GRID class="DiscussionsGrid" > <!-- '.__LINE__.$Notation.' --> ';
        } else {
            echo '<div id=NOGRID  > <!-- '.__LINE__.$Notation.' --> ';
        }
    }
/**
* Close the span with ID set for css grid manipulation.
*
* @param object $Sender standard
*
* @return none
*/
    public function discussionsController_afterdiscussioncontent_handler($Sender) {
        //$this->DebugData('','',true,true);
        $this->InsertCloseSpan($Sender, $Caller = strtoupper(substr(__FUNCTION__,0,1)));
    }
    public function categoriescontroller_afterdiscussioncontent_handler($Sender) {
        //$this->DebugData('','',true,true);
        echo "</li>".'<!--'.__LINE__.' correcting discrepancy between catetories and discussions controllers--> ';
        $this->InsertCloseSpan($Sender, $Caller = strtoupper(substr(__FUNCTION__,0,1)));
    }
/**
* Insert Closing Span tag to allow css manipulation.
*
* @param object $Sender standard
* @param string $Caller Indicate the caller (discussion/categories)
*
* @return none
*/
    private function InsertCloseSpan($Sender, $Caller = "D") {
        //$this->DebugData('','',true,true);
        echo '<!--'.__LINE__.$Caller.'--></DIV> <!-- closing ID=GRID or NOGRID '.__LINE__.' --> ';   // Close  <span id=GRID> or  <span id=NOGRID>
    }
/**
* Set the grid.
*
* @param $Sender
*/
    //public function base_afterRenderAsset_handler($Sender) {
    public function base_beforeRenderAsset_handler($Sender) {
        $Controller = strtolower($Sender->ControllerName);
        //$this->DebugData($Controller, '---Controller---', 1);
        if ($Controller != 'categoriescontroller' && $Controller != 'discussionscontroller' ) {
            return;
        }
        $AssetName = valr('EventArguments.AssetName', $Sender);
        if ($AssetName != 'Content') { // && $AssetName != 'Head' ) {
            //$this->DebugData($AssetName, '---AssetName---', 1);
            return;
        }
        if (c('Vanilla.Categories.Layout') == "table" || c('Vanilla.Discussions.Layout') == "table") {
            return;
        }
        //
        if (!$this->isgridstate()) {
            return;
        }
        //
        $Calcwidth =   'var width = $(".DataList.Discussions").width();
                        var cols = ~~(width / 200);
                        var pct = (~~(width/cols))-10;
                        if (pct <100) { var pct = 100};
                        $("#Grid").css("width", pct+"px");
                        $(".DiscussionsGrid").css("width", pct+"px");';
        //
        //$Calcwidth = '$(".DiscussionsGrid").css("width", "-webkit-fill-available");';  //For debugging
        //
        echo    '<script type="text/javascript">';
        echo    '$(document).ready(function(){';
        echo        $Calcwidth;
        echo        'if ( $("#GRID").length > 0) {';
        echo        '$(".DataList.Discussions").css("display", "flex").css("flex-wrap", "wrap").css("padding", "2px"); ';
        echo        '$(".Discussion.ItemContent").css("padding-left", "4px"); ';
        if (c('Plugins.DiscussionsGrid.Hidecounts', true)) {
            echo    '$("#GRID span.MItem.MCount").css("display", "none"); ';
        }
        echo        '}';
        echo    '$(window).resize(function() {;';
        echo        $Calcwidth;
        echo    '  });';
        //echo    '   $(window).focusin(function() {;';
        //echo        $Calcwidth;
        //echo    '  });';
        echo    '});';
        echo '</script>';
    }
/**
* Check whether user is in Grid View State
*
*  @return boolean n/a
*/
    private function isgridstate() {
        if ($this->getmeta("GridMenu") == c('Plugins.DiscussionsGrid.Gridicon', "☷")." ".c('Plugins.DiscussionsGrid.Grid', "Grid")) {
            return false;   //If menu says "Grid" it means we're not in a grid state
        }
        return true;
    }
/**
* Place a Toggle menu link on the menu bar.
*
*  @param object $Sender Standard
*
*  @return boolean n/a
*/
    public function base_render_before($Sender) {
        //$this->DebugData('','',true,true);
        //
        $Controller = strtolower($Sender->ControllerName);
        //$this->DebugData($Controller, '---Controller---', 1);
        if ($Controller != 'categoriescontroller' && $Controller != 'discussionscontroller' ) {
            return;
        }
        if (c('Vanilla.Categories.Layout') == "table" || c('Vanilla.Discussions.Layout') == "table") {
            return;
        }
        //
        $Prefix = c('Plugins.DiscussionsGrid.Menu', "Menu");
        $List = c('Plugins.DiscussionsGrid.Listicon', "☰")." ".c('Plugins.DiscussionsGrid.List', "List");
        $Grid = c('Plugins.DiscussionsGrid.Gridicon', "▦")." ".c('Plugins.DiscussionsGrid.Grid', "Grid");
        $Urlkey = "Urlkey";
        $Session = Gdn::Session();
        if ((c('Plugins.DiscussionsGrid.PermissionGrid', false)) &&
            (!$Session->checkPermission('Plugins.DiscussionsGrid.PermissionGrid'))) {
            $this->Setmeta("GridMenu", false);
            return false;
        }
        if ((c('Plugins.DiscussionsGrid.PermissionToggle', false)) &&
            (!$Session->checkPermission('Plugins.DiscussionsGrid.PermissionToggle'))) {
            $this->Setmeta("GridMenu", 0);
            return false;
        }
        //
        $Sender->addCssFile('discussionsgrid.css', 'plugins/DiscussionsGrid');
        //
        $Menustate = $this->getmeta("GridMenu");
        //$this->DebugData($Menustate, '---Menustate---', 1);
        if (!$Menustate) {
            $Menustate = $List;
        }
        //if ($Menustate == $List) {
        //    saveToConfig('Plugins.DiscussionNote.ShowinList', false, array('Save' => false));
        //}
        $Sender->Menu->AddLink(
            $Prefix,
            t($Menustate),
            '/plugin/DiscussionsGrid/Toggle',
            false,
            array('class' => 'gridtoggle', 'target' => '_self')
        );
        $Url = current_url();
        if (strpos($Url, "notifications") === false) {
            $this->setmeta($Urlkey, $Url);
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
          if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Menu->AddLink('APPEARANCE', '<b style="color:#0291db">☷ </b>'.T('DiscussionsGrid'), 'settings/DiscussionsGrid', 'Garden.Settings.Manage');
          } else {
            $Menu->AddLink('Appearance', T('DiscussionsGrid'), 'settings/DiscussionsGrid', 'Garden.Settings.Manage');
          }
    }
/**
* Toggle Menu
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function PluginController_DiscussionsGrid_Create($Sender) {
        //$this->DebugData('','',true,true);
        if ($this->isgridstate()) {
            $Newstate = c('Plugins.DiscussionsGrid.Gridicon', "▦")." ".c('Plugins.DiscussionsGrid.Grid', "Grid");
        } else {
            $Newstate = c('Plugins.DiscussionsGrid.Listicon', "☰")." ".c('Plugins.DiscussionsGrid.List', "List");
        }
        $this->Setmeta("GridMenu", T($Newstate));
        $Url = $this->getmeta("Urlkey");
        if (strpos($Url, "notifications") !== false) {
            $Url = "/discussions?";
        }
        redirect($Url);
    }
/**
* Get value from usermeta.
*
* @param string $Key   Key of value
* @param flag   $Debug Indicate debug mode
*
* @return value
*/
    public function Getmeta($Key, $Debug = false) {
        //$this->DebugData('','',true,true);
        $Meta = $this->GetUserMeta(Gdn::Session()->UserID, $Key);
        //$this->DebugData($Meta, '---Meta---', 1);
        $Value = $Meta["Plugin.DiscussionsGrid.".$Key];
        return $Value;
        if ($Debug) {
            $this->DebugData($Key, '---Key---', 1);
            $this->DebugData($Value, '---Value---', 1);
        }
        return $Value;
    }
/**
* Set value for keyed usermeta.
*
* @param string   $Key   Key of value
* @param variable $Value value
* @param flag   $Debug Indicate debug mode
*
* @return value
*/
    public function Setmeta($Key, $Value, $Debug = false) {
        //$this->DebugData('','',true,true);
        $Meta = $this->SetUserMeta(Gdn::Session()->UserID, $Key, $Value);
        return $this->Getmeta($Key, $Debug);
    }
/**
* Test whether discussion is a eligible to be in a grid.
*
* @param object $Sender     standard
* @param object $Discussion discussion object
* @param string $Caller   Indicate the caller (discussion/categories/title setting [image/text extract])
*
* @return flag
*/
    private function iseligible($Sender, $Discussion, $Caller = "D") {
        if (c('Vanilla.Categories.Layout') == "table" || c('Vanilla.Discussions.Layout') == "table") {
            return false;
        }
        // If user is in LIST state don't proceed
        if (!$this->isgridstate()) {
            return;
        }
        //Verify user is allowed to view discussions in the grid view
        $Session = Gdn::Session();
        if ((c('Plugins.DiscussionsGrid.PermissionGrid', false)) &&
            (!$Session->checkPermission('Plugins.DiscussionsGrid.PermissionGrid'))) {
            return false;
        }
        if ((($Discussion->Announce)) && (c('Plugins.DiscussionsGrid.SkipAnnouncements', false))){
            return false;
        }
        // Apply grid only to a specific categories (if so set)
        $Catnums = c('Plugins.DiscussionsGrid.CategoryNums');
        if ($Catnums != "") {                           //Limited category list?
            if ($Caller != "C") {            //Not categories display?
                //return false;
            }
            if (!in_array($Discussion->CategoryID, $Catnums)) { //Not in the list?
                return false;
            }
        }
        // Apply grid only to a specific discussion type (if so set)
        $Onlytype =  c('Plugins.DiscussionsGrid.Onlytype', '');
        if ($Onlytype) {                                //Eligible only on specific discussion type?
            if ($Discussion->Type != $Onlytype) {
                return false;
            }
        }
        return true;
    }
/**
* Get text between two strings in a source string
*
* @param string $Source text source
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
* Save configuration value from settings form,serialize arrays before saving(For Vanilla>2.2).
*
* @param Standard $Sender   Standard
* @param any      $Field    Field value to save
* @param Standard $Name     Config nae to save
* @param Standard $GetValue Indicates that field has to be retrieved from the settings form
*
*  @return  n/a
*/
    private function saveconfigvalue($Sender, $Field, $Name, $GetValue = false) {
        if ($GetValue) {
            $FieldToSave = $Sender->Form->getValue($Name);
        } else {
            $FieldToSave =  $Field;
        }
        if (is_array($FieldToSave)) {   //For Vanilla 2.3 or above
            $FieldToSave = serialize($FieldToSave);
        }
        SaveToConfig($Name, $FieldToSave);
    }
/**
* Terminate with a severe error message.
*
* @param Standard $Message error message to display
*
* @return n/a
*/
    private function diemessage($Message) {
        echo "<P>DiscussionGote Plugin Message:<H1><B>".t($Message)."<N></H1></P>";
        throw new Gdn_UserException($Message);
    }
/**
 * Plugin cleanup
 *
 * This method is fired only once, immediately before the plugin is disabled, and is a great place to
 * perform cleanup tasks such as deletion of unused files and folders.
 */
    public function onDisable() {
    }
/**
* Remove extra blanks from source url
*
* @param string $Source source url
*
* @return string
*/
    private function compactblanks($Source) {
        $Source = preg_replace('!\s+!', ' ', $Source);
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
            //decho($Message);
        }
        //echo '<pre style="font-size: 1.3em;text-align: left; padding: 0 4px;'.$Color.'">'.$Message;
        echo '<pre style="font-size: 14px;text-align: left; padding: 0 4px;'.$Color.'">'.$Message;
        Trace(__LINE__.' '.$Message.' '.$Data);
        if ($Data != '') {
            //if (is_string($Data)) {
            //    echo $this->compactblanks(highlight_string($Data, 1));

            //} else {
                var_dump($Data);
            //}
        }
        echo '</pre>';
        //
    }
    /////////////////////////////////////////////////////////
}