<?php
/**
 * DiscussionNote plugin. Provides the ability to attach a "post-it"-like note to a discussion.
 *
 */

$PluginInfo['DiscussionNote'] = array(
    'Name' => 'Discussion Note',
    'Description' => 'Provides the ability to attach a "post-it"-like note to a discussion.',
    'Version' => '2.1',
    'RequiredApplications' => array('Vanilla' => '2.1.13'),
    'RequiredTheme' => false,
    'MobileFriendly' => true,
    'HasLocale' => true,
    'SettingsUrl' => '/settings/DiscussionNote',
    'SettingsPermission' => 'Garden.Settings.Manage',
    'RegisterPermissions' => array('Plugins.DiscussionNote.View','Plugins.DiscussionNote.Add'),
    'Author' => "Roger Brahmson",
    'GitHub' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/DiscussionNote",
    'License' => 'GPLv2'
);
/**
* Plugin to provide the ability to attach a "post-it"-like note to a discussion.
*/
class DiscussionNote extends Gdn_Plugin {
/**
* Get control to alter the Note state of a discussion public function.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function plugincontroller_discussionnote_create($Sender, $Args) {
        // Routing to relevant functions
        $Sender->Title('DiscussionNote Plugin');
        $Sender->AddSideMenu('plugin/discussionnote');
        $this->Dispatch($Sender, $Sender->RequestArgs);
    }
/**
* Update note (the main form update function).
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function controller_discussionnoteupdate($Sender, $Args) {
        $Debug = intval($_GET['!DEBUG!']);
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $DiscussionID = intval($_GET['U']);
        if ($DiscussionID == null) {                                //DiscussionID is required
            $this->DieMessage('DN002 - Missing Parameters');
            return;
        }
        $Encode = intval($_GET['S']);
        if ($Encode == $DiscussionID) {                              //Encoded form cannot be in the clear
            $this->DieMessage('DN003 - Invalid Parameter');
            return;
        } else {
            if ($Encode == null) {                                  //Encoding form is also required
                $this->DieMessage('DN004 - Invalid Parameter');
                return;
            }
            $Simplekey = (349+Gdn::Session()->UserID);
            $D2 = $DiscussionID ^ $Simplekey;
            if ($D2 != $Encode) {                                  //Encoded form does not belong to this DiscussionID
                $this->DieMessage('DN005 - Invalid Parameter:'.$Encode);
                return;
            }
        }
        // Now we know that passed parameters are fine
        $DiscussionModel = new DiscussionModel();
        $Discussion = $DiscussionModel->GetID($DiscussionID);

        $Access = $this->CheckAccess($Sender, $Discussion, $Debug);   //Check the callers access rights to Notes.
        $Note = $Discussion->Postitnote;                            //Current note
        $Previousnote = $Note;                                      //Remember the note before any change
        $Feedback = '';
        if ($Access == 'Add') {
            $Viewonly = false;
        } elseif ($Access == 'View') {
            $Viewonly = true;
        } elseif ($Access == 'None' && $Note == "") {               //No access because note is empty (and caller has view-only rights)?
            $Feedback = 'This discussion does not have a note';
            $Viewonly = true;
        } elseif ($Access == 'None') {
            $this->DieMessage('DN001 - Not allowed');
            return;   //Should ne be here (should not have had the dispatching link)
        }
        //
        //All validations were passed, display the note form and update the database
        if ($Access != 'None') {
            $this->DebugData($Viewonly, '---Viewonly--', $Debug);
            $this->ShowNoteForm($Sender, $Discussion, $Viewonly, $Debug);  //Display the form
        }
        if ($Access == 'Add') {
            $Note = $Discussion->Postitnote;                        //Take the form input
            if ($Note == $Previousnote) {                           //No change?
                $Viewonly = true;                                   //Treat like it was a view only case
                $Feedback = t('Nothing changed');
            } else {                                                //Something was changed
                if (trim($Note) == "") {
                    $Feedback = t('Note was removed');
                    $Note = "";
                    $Noteinmetacss = 'Hijack Noteinmeta';
                } else {
                    if ($Previousnote == "") {
                        $Feedback = t('Note was assigned');
                    } elseif ($Previousnote != $Note) {
                        $Feedback = t('Note was updated');
                    }
                    $Noteinmetacss = 'Hijack Noteinmeta on';
                }
            }
        }
        if (!$Viewonly) {
            Gdn::sql() ->update('Discussion')
                ->set('Postitnote', $Note) ->where('DiscussionID', $DiscussionID)
                ->put();
            // The followig two lines are intentionally commented out (see the comment above "Base_Render_Before" function
            // for rationale.
            //$Cookiename="DNDID";                                      //Save Last updated DiscussionID in cookie
            //setcookie($Cookiename, $DiscussionID, time() + (1360), "/");  // Should be enough-JS will handle rest
            // Refresh the screen icons/links with the newly updated value
            $Sender->JsonTarget(
                '#Note' . $DiscussionID . ' .Notecss',
                $this->SetNoteLink($Sender, $DiscussionID, $Note, $Access, true, false),
                'ReplaceWith'
            );
            //
            // Refresh the screen Note with the newly updated value (only if we're embeddingthe note in the discussion list)
            if (c('Plugins.DiscussionNote.ShowinList')) {
                $Sender->JsonTarget(
                    '#Postit' . $DiscussionID,
                    wrap($Note, 'div', array('id' => 'Postit'.$DiscussionID,'class' => $Noteinmetacss)),
                    'ReplaceWith'
                );
            }
        }
        //
        if ($Feedback != '') {
            $Sender->InformMessage($Feedback);
        }
        //
        // The following render is the only one that managed to refresh the screen with the hijacked style.
        $Sender->Render('Blank', 'Utility', 'Dashboard');
        //$Sender->jsonTarget('', '', 'Refresh');           //This did not work...
    }
   ///////////////////////////////////////////////
    /*  The following will activate the JS which will refresh the embedded notes (assuming they are shown) on a periodic basis.
    //  This is only required IF there is a need for refreshing the notes when another user updates them.  Note:  when the user
    //  updates the notes, the embedded discussion list note is refreshed authomatically.  Since the need for this extra capability
    //  is quite rare this function is commented out.
    public function Base_Render_Before($Sender) {
        if ($Sender->MasterView == 'admin') return;                 // Ignore the admin panel
        $Sender->AddJsFile($this->GetResource('js/discussionnote.js', false, false));
    }
    */
/**
* Refresh a specific note (used by JS to retrieve a specific note).
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function controller_discussionnoterefresh($Sender, $Args) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $DiscussionID = intval($_GET['U']);
        if ($DiscussionID == null) {                                //DiscussionID is required
            $this->DieMessage('DNR001 - Missing Parameters');
            return;
        }
        $DiscussionModel = new DiscussionModel();
        $Discussion = $DiscussionModel->GetID($DiscussionID);
        $Access = $this->CheckAccess($Sender, $Discussion, false);
        if ($Access == "None") {                                    //View permission is required
            $this->DieMessage('DNR002 - Not allowed');
            return;
        }
        $Note = $Discussion->Postitnote;
        echo wrap($Note, 'span');
        return ;
    }
/**
* Set the CSS.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function assetModel_styleCss_handler($Sender) {
        $Sender->addCssFile('DiscussionNote.css', 'plugins/DiscussionNote');
    }
/**
* Hook before discussion list is shown (through the discussions controller).
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function discussionscontroller_beforediscussioncontent_handler($Sender) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Discussion = $Sender->EventArguments['Discussion'];
        $this->PlaceButton($Sender, $Discussion, false);
        return;
    }
/**
* Hook before discussion list is shown (through caegories controller).
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function categoriescontroller_beforediscussioncontent_handler($Sender) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Discussion = $Sender->EventArguments['Discussion'];
        $this->PlaceButton($Sender, $Discussion, false);
        return;
    }
/**
* Hook for when discussion content is shown.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function discussioncontroller_discussioninfo_handler($Sender, $Args) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Discussion = $Sender->EventArguments['Discussion'];
        $this->PlaceButton($Sender, $Discussion, false);
        $this->EmbedNote($Sender);
        $this->DebugData(__LINE__, '---', $Debug);
    }
/**
* Hook when discussion comment is shown.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function discussioncontroller_commentinfo_handler($Sender, $Args) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Discussion = $Sender->EventArguments['Discussion'];
        $LastCommentID=$Discussion->LastCommentID;
        $Comment = $Sender->EventArguments['Comment'];
        $CommentID = $Comment->CommentID;
        $this->DebugData($CommentID, '---CommentID--', $Debug);
        $this->DebugData($LastCommentID, '---LastCommentID--', $Debug);
        //
        //Show button & embedded note only on the last comment
        //
        if ($CommentID == $LastCommentID) {
            $this->PlaceButton($Sender, $Discussion, false);
            $this->EmbedNote($Sender);
        }
    }
/**
* Define the additional column used to hold the Note.
*
*  @return boolean n/a
*/
    public function structure() {
        Gdn::database()->structure()
            ->table('Discussion')
            ->column('Postitnote', 'varchar(200)', true)
            ->set();
    }
/**
* Plugin setup.
*
*  @return boolean n/a
*/
    public function setup() {
        $this->Structure();
        // Initialize plugin defaults
        if (!c('Plugins.DiscussionNote.CategoryNums')) {
            saveToConfig('Plugins.DiscussionNote.CategoryNums', '');  //Blank out limit to selected category ids
        }
        if (!c('Plugins.DiscussionNote.ShowinList')) {
            saveToConfig('Plugins.DiscussionNote.ShowinList', false); //Default will not display note within the meta area
        }
        if (!c('Plugins.DiscussionNote.PermissionView')) {
            saveToConfig('Plugins.DiscussionNote.PermissionView', false); //Default will not display note within the meta area
        }
        if (!c('Plugins.DiscussionNote.PermissionAdd')) {
            saveToConfig('Plugins.DiscussionNote.PermissionAdd', false); //Default will not display note within the meta area
        }
    }
/**
* Place the note within the meta area of the discussion list.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function embednote($Sender) {
        $Debug = false;
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        if (!c('Plugins.DiscussionNote.ShowinList') == '1') {
            return;     //Admin did not indicate that notes should be displayed in the meta area
        }
        // If not allowed to view Note then there is nothing to do
        $Discussion = $Sender->EventArguments['Discussion'];
        $Access = $this->CheckAccess($Sender, $Discussion, $Debug);
        $this->DebugData($Access, '---Access--', $Debug);
        if ($Access == 'None') {
            return;
        }
        //
        $Note = $Discussion->Postitnote;
        $Hijack = 'Hijack Noteinmeta on';
        if (trim($Note) == '') {
            $Hijack = 'Hijack Noteinmeta';
        }
        $this->DebugData($Hijack, '---Hijack--', $Debug);
        echo wrap(
            wrap(
                $Note,
                'div',
                array('id' => 'Postit'.$Discussion->DiscussionID,'class' => $Hijack)
            ),
            'div',
            array('class' => 'Noteinmetabackground')
        );
    }
/**
* Optionally place the note after the discussion title of the discussion list.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function discussionscontroller_afterdiscussiontitle_handler($Sender) {
        $this->EmbedNote($Sender);
    }
/**
* Optionally place the note after the discussion title of the discussion list by the categories controller.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function categoriescontroller_afterdiscussiontitle_handler($Sender) {
        $this->EmbedNote($Sender);
    }
/**
* Admin dashboard settings.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function settingscontroller_discussionnote_create($Sender, $Args) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Sender->permission('Garden.Settings.Manage');
        $Sender->setData('Title', t('DiscussionNote Settings'));
        $Sender->addSideMenu('dashboard/settings/plugins');
        $Sender->addCssFile('pluginsetup.css', 'plugins/DiscussionNote');
        // Get all categories.
        $Categories = CategoryModel::categories();
        // Remove the "root" categorie from the list.
        unset($Categories[-1]);
        //
        $ConfigurationModule = new ConfigurationModule($Sender);
        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $Sender->Form->SetModel($ConfigurationModel);
        $ConfigurationModule->Schema(array(
            'Plugins.DiscussionNote.PermissionAdd' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Require premission in "Roles and Permissions" to add notes</B>',
              'Items' => $PermissionAdd,
              'Description' => '<b>Note:</b>Additionally, users need discussion edit permission to add/change a note in a discussion.',
              'Default' => true),
            'Plugins.DiscussionNote.PermissionView' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Require premission in "Roles and Permissions" to view notes</B>',
              'Items' => $PermissionView,
              /*'Description' => '<b>________________________________________________</b>',*/
              'Default' => true),
            'Plugins.DiscussionNote.ShowinList' => array(
              'Control' => 'CheckBox',
              'LabelCode' => '<B>Display assigned notes within in the discussions list and the discussion post</B>',
              'Items' => $Showinlist,
              'Description' => '<b>________________________________________________</b>',
              'Default' => " "),
            'Plugins.DiscussionNote.CategoryNums' => array(
                  'Control' => 'CheckBoxList',
                  'LabelCode' => '<b>________________________________________________</b>',
                  'Items' => $Categories,
                  'Description' => '<b>Limit Notes to discussions in specific categories:</b>(no selecation enables all categories)',
                  'Options' => array('ValueField' => 'CategoryID', 'TextField' => 'Name', 'class' => 'Categorylist CheckBoxList'),
                ),
        ));
        if ($Sender->Form->authenticatedPostBack()) {
            $FormValues = $Sender->Form->formValues();
            //
            $PermissionAdd = getvalue('Plugins.DiscussionNote.PermissionAdd', $FormValues);
            $PermissionView = getvalue('Plugins.DiscussionNote.PermissionView', $FormValues);
            $ShowinList = getvalue('Plugins.DiscussionNote.ShowinList', $FormValues);
            $CategoryNums = getvalue('Plugins.DiscussionNote.CategoryNums', $FormValues);
            //
            $this->SaveconfigValue($Sender, $PermissionAdd, 'Plugins.DiscussionNote.PermissionAdd');
            $this->SaveconfigValue($Sender, $PermissionView, 'Plugins.DiscussionNote.PermissionView');
            $this->SaveconfigValue($Sender, $ShowinList, 'Plugins.DiscussionNote.ShowinList');
            $this->SaveconfigValue($Sender, $CategoryNums, 'Plugins.DiscussionNote.CategoryNums');
            //
            $SaveMsg = t('Your settings were saved');
            Gdn::controller()->informMessage($SaveMsg);
        } else {    // Not postback
            $Sender->Form->setValue('Plugins.DiscussionNote.PermissionAdd', c('Plugins.DiscussionNote.PermissionAdd', []));
            $Sender->Form->setValue('Plugins.DiscussionNote.PermissionView', c('Plugins.DiscussionNote.PermissionView', []));
            $Sender->Form->setValue('Plugins.DiscussionNote.ShowinList', c('Plugins.DiscussionNote.ShowinList', []));
            $Sender->Form->setValue('Plugins.DiscussionNote.CategoryNums', c('Plugins.DiscussionNote.CategoryNums', []));
        }
        $ConfigurationModule->renderAll();
    }
/**
* Decide user access right to a discussion .
*
* @param Standard $Sender     Standard
* @param Standard $Discussion Discussion object
* @param Standard $Debug      Trace request
*
* @return string Returns one of "None", "View", "Add"
*/
    private function checkaccess($Sender, $Discussion, $Debug = false) {
        $Debug = intval($_GET['!DEBUG!']);
        $this->DebugData('', '', $Debug, true);  //Trace Function calling
        $DiscussionID = $Discussion->DiscussionID;
        $Session = Gdn::Session();
        if (!$Session->IsValid()) {
            $Msg = "Must be logged on";
            $this->DebugData($Msg, '---Msg--', $Debug);
            echo $Msg;
            return "None";
        }
        $this->DebugData($DiscussionID, '---DiscussionID--', $Debug);
        if ($Session->checkPermission('Garden.Settings.Manage')) {
            $this->DebugData($DiscussionID, '---DiscussionID--', $Debug);
            return "Add";   //Admins are kings
        }
        //
        //Verify user is allowed to view discussions in the discussion category
        //
        if (!$Session->CheckPermission('Vanilla.Discussions.View', true, 'Category', $Discussion->PermissionCategoryID)) {
            $this->DebugData($DiscussionID, '---DiscussionID--', $Debug);
            return "None";
        }
        //
        //Additionally, if config setting requires permission to View note butpermission not set,return "None"
        //
        if ((c('Plugins.DiscussionNote.PermissionView') == '1') &&
            (!$Session->checkPermission('Plugins.DiscussionNote.View'))) {
            $this->DebugData($DiscussionID, '---DiscussionID--', $Debug);
            return "None";
        }
        //
        //If Notes are limited to specific categories verify this dicussion is in the permitted cetegories
        //
        $Catnums = c('Plugins.DiscussionNote.CategoryNums');
        $this->DebugData($Catnums, '---Catnums--', $Debug);
        if ($Catnums != "") {  //Limited category list?
            $this->DebugData($Discussion->CategoryID, '---Discussion->CategoryID--', $Debug);
            if (!in_array($Discussion->CategoryID, $Catnums)) { //Not in the list?
                $this->DebugData($Discussion->CategoryID, '---Discussion->CategoryID--', $Debug);
                return "None";
            }
        }
        //
        // We now know the user is allowed to see notes.
        //Now check whether add/update/delete permission is granted
        //If the user is the creator of thediscussion then add/update permission is allowed
        //
        if ($Discussion->InsertUserID == $Session->UserID) {
            $this->DebugData($Session->UserID, '---Session->UserID--', $Debug);
            return "Add";
        }
        //
        //if config setting requires permission to add/edit/delete notes and permission is note set only allow View
        //
        if ((c('Plugins.DiscussionNote.PermissionAdd') == '1') &&
            (!$Session->checkPermission('Plugins.DiscussionNote.Add'))) {
            $this->DebugData($Session->UserID, '---Session->UserID--', $Debug);
            return "View";
        }
        //
        // If user is allowed to edit/add discussions in the category then add/update permission is also allowed
        if ($Session->CheckPermission('Vanilla.Discussions.Edit', true, 'Category', $Discussion->PermissionCategoryID) ||
            $Session->CheckPermission('Vanilla.Discussions.Add', true, 'Category', $Discussion->PermissionCategoryID)) {
            $this->DebugData($Session->UserID, '---Session->UserID--', $Debug);
            return "Add";
        }
        //
        // So user is not allowed to add but is allowed to view. But is there anything to show?
        //
        if ($Discussion->Postitnote == "") {
            return "None";   //Nothing to show so no access.
        }
        $this->DebugData($Session->UserID, '---Session->UserID--', $Debug);
        return "View";
    }
/**
* Conditionaly place an Note "button".
*
* @param Standard $Sender     Standard
* @param Standard $Discussion Discussion object
* @param Standard $Debug      Trace request
*
* @return string Returns one of "None", "View", "Add"
*/
    private function placebutton($Sender, $Discussion, $Debug = false) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Note = $Discussion->Postitnote;
        $DiscussionID = $Discussion->DiscussionID;
        $this->DebugData($Note, '---Note--', $Debug);
        //
        // Obtain user Note access to the discussion
        //
        $Link = true;
        $Access = $this->CheckAccess($Sender, $Discussion, $Debug);
        $this->DebugData($Access, '---Access--', $Debug);
        $this->DebugData($Link, '---Link--', $Debug);
        if ($Access == "None") {
            $Link = false;
        }
        $this->DebugData($Link, '---Link--', $Debug);
        //
        //Set the actual button
        //
        echo $this->SetNoteLink($Sender, $DiscussionID, $Note, $Access, $Link, $Debug);
    }
/**
* Display the note form.
*
* @param Standard $Sender     Standard
* @param Standard $Discussion Discussion object
* @param string   $Viewonly   type of button to show
* @param Standard $Debug      Trace request
*
* @return n/a
*/
    private function shownoteform($Sender, $Discussion, $Viewonly = true, $Debug = false) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $this->DebugData($Viewonly, '---Viewonly--', $Debug);
        $Note = $Discussion->Postitnote;
        $Sender->setData('Note', $Note);
        $Sender->setData('Viewonly', $Viewonly);
        $Sender->setData('DiscussionName', $Discussion->Name);
        //
        $Sender->Form = new Gdn_Form();
        $Validation = new Gdn_Validation();
        //$Sender->Form->setData($ConfigurationModel->Data);
        $Postback=$Sender->Form->authenticatedPostBack();
        $this->DebugData($Postback, '---Postback--', $Debug);
        //
        if (!$Postback) {                    //Before form submission
            $this->DebugData($Note, '---Nopostback,Note--', $Debug);
            $Sender->Form->setValue('Note', $Note);
            $Sender->Form->setFormValue('Note', $Note);
        } else {                                //After form submission
            $FormPostValues = $Sender->Form->formValues();
            $this->DebugData($FormPostValues, '---FormPostValues--', $Debug);
            //if (!$Validation->validate($FormPostValues)) {
            //
            //}
            if ($Sender->Form->ErrorCount() == 0) {
                $this->DebugData($FormPostValues, '---FormPostValues--', $Debug);
                if (isset($FormPostValues['Cancel'])) {
                    $this->DebugData($FormPostValues['Cancel'], '---FormPostValues["Cancel"]--', $Debug);
                    return;
                }
                if (!$Viewonly) {
                    if (isset($FormPostValues['Remove'])) {
                        $this->DebugData($FormPostValues['Remove'], '---FormPostValues["Remove"]--', $Debug);
                        $Discussion->Postitnote = "";
                        return;
                    } elseif (isset($FormPostValues['Save'])) {
                        $Note = $FormPostValues['Note'];
                        if (strlen($Note) > 200) {
                            $Sender->InformMessage('Note is limited to 200 characters');
                        }
                        $Discussion->Postitnote = strip_tags(sliceString($Note, 200));
                        $this->DebugData($Note, '---Note--', $Debug);
                        return;
                    }
                }
                $this->DebugData($Note, '---Note--', $Debug);
                $Sender->Form->SetFormValue('Note', $Note);
            } else {
                $this->DebugData($Note, '---Note--', $Debug);
                $Sender->Form->setData($FormPostValues);
            }
        }
        //
        $this->DebugData($Note, '---Note--', $Debug);
        $View = $this->getView('note.php');
        $this->DebugData($View, '---View--', $Debug);
        $Sender->render($View);
    }
/**
* Place the Note button (with or without clickable link and the appropriate Hijack).
*
* @param Standard $Sender       Standard
* @param Integer  $DiscussionID Discussion ID
* @param string   $Note         Discussion Note
* @param string   $Access       type of access to note
* @param string   $Link         type of link to show
* @param Standard $Debug        Trace request
*
* @return string Returns one of "None", "View", "Add"
*/
    private function setnotelink($Sender, $DiscussionID, $Note, $Access = 'None', $Link = false, $Debug = false) {
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $this->DebugData($Access, '---Access--', $Debug);
        $this->DebugData($Note, '---Noted--', $Debug);
        //
        //Don't bother with a view only button if the content is embedded
        //
        if (c('Plugins.DiscussionNote.ShowinList') && $Access == 'View') {
            $Access = 'None';
        }
        if ($Access == 'None') {
            $this->DebugData($Access, '---Access--', $Debug);
            return wrap('Note', 'div id=Note'.$DiscussionID, array('class' => 'NoteVcssoff')) ;
        }
        if (trim($Note) != "") {                   //Note exists
            $this->DebugData($Access, '---Access--', $Debug);
            $Hijack = 'Popup Notecss on';
            $Tip = str_replace(" ", "&nbsp;", $Note);
            $Informcss = 'NoteVcsson';
            $Informtip = $Tip;
        } else {                                    //Note not set
            $this->DebugData($Access, '---Access--', $Debug);
            $Hijack = 'Popup Notecss';
            $Tip = "";
            $Informcss = 'NoteVcssoff';
            $Informtip = "";                        //If not allowed to change then no need for tooltip;
            if ($Access == 'View') {
                $Link = false;
            }
        }
        $this->DebugData($Access, '---Access--', $Debug);
        $this->DebugData($Link, '---Link--', $Debug);
        $Simplekey = (349+Gdn::Session()->UserID);  //VERY simple encoding of the Discussion ID
        $Encode = $DiscussionID ^ $Simplekey;       //Will be verified when a change is requested
        //plugin/discussionnote/DiscussionNoteUpdate?U=ID#&S=Encode#
        $Url = '/plugin/DiscussionNote/DiscussionNoteUpdate/?U='.$DiscussionID.'&S='.$Encode;   //Pass both Discussion ID and its encoding
        if (!$Link) {                               //User only allowed to view note state
            $this->DebugData($Access, '---Access--', $Debug);
            return wrap(T('Note'), 'div id=Note'.$DiscussionID.' class='.$Informcss.' Title='.$Informtip);
        } else {                                    //User allowed to set/reset notes,so place the links with Hijack
            $this->DebugData($Access, '---Access--', $Debug);
            return wrap(Anchor(T('Note'), $Url, $Hijack), 'div id=Note'.$DiscussionID.' Title='.$Tip);
        }
    }
/**
* Save configuration value from settings form,seralize arrays before saving(For Vanilla>2.2).
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
        echo "<P>DiscussionNote Plugin Message:<H1><B>".$Message."<N></H1></P>";
        throw new Gdn_UserException($Message);
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
        //Usage Examples:  $this->DebugData($Var, '---Var--', $Debug);
        //                 $this->DebugData(implode(" ", $Tokens), '---Tokens---', $Debug);
        //Function trace example: $this->DebugData('', '', $Debug, true);
        //
        if ($Debug == false) {
            return;
        }
        $Color = '';
        $Color = 'color:green;';
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
        echo '<pre style="text-align: left; padding: 0 4px;'.$Color.'">';
        echo $Message;
        if ($Data != '') {
            var_dump($Data);
        }
        echo '</pre>';
    }
}
