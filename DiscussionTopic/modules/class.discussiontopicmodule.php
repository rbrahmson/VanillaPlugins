<?php if (!defined('APPLICATION')) exit();
/**
* Module to add related discussions to the side panel.
*/
class DiscussionTopicModule extends Gdn_Module {

    protected $topicstring;

    /**
    * Reserved functionname returning data to the Vanilla side panel
    *
    * @param  object $Sender standard
    * @param  object $Args   standard
    *
    * @return string         formatted side panel
    */
    public function tostring($Sender, $Args) {
        global $topicstring;
        return $topicstring;
    }
    /**
    * Refresh Side Panel
    *
    * @param [type]  $DiscussionID Discussion ID
    * @param integer $Limit        Number of records to retrieve
    * @param boolean $Debug        debug request
    *
    * @return string         formatted side panel
    */
    public function refresh($DiscussionID, $Limit = 10, $Debug = false) {
        global $topicstring ;
        //
        $AlsoSql = clone Gdn::sql();    //Don't interfere with any other sql process
        $AlsoSql->Reset();              //Clean slate
        $Discussionlist = $AlsoSql      //Get expanded tag info for this discussion
            ->select('d.DiscussionID')
            ->from('Discussion d')
            ->where('d.DiscussionID', $DiscussionID)
            ->get();
        //
        $this->GetAlso($DiscussionID, $Limit, $Debug);
        return $topicstring;
    }
    /**
    * Get the data for the Side Panel
    *
    * @param [type]  $DiscussionID Discussion ID
    * @param integer $Limit        Number of records to retrieve
    * @param boolean $Debug        debug request
    *
    * @return string         formatted side panel
    */
    public function getalso($DiscussionID, $Limit = 10, $Debug = false) {
        global $topicstring ;
        //This function will return the discussions that have the same "Topic" value as the current discussion
        //
        if ($Debug) {
                $Msg = __FUNCTION__.' '.__LINE__.' Called by: ' .
                debug_backtrace()[1]['function'].
                debug_backtrace()[0]['line'].' ---> '.
                debug_backtrace()[0]['function'];
            echo wrap($Msg, 'br');
        }
        //
        $topicstring = '';
        $DiscussionModel = new DiscussionModel();
        $Discussion = $DiscussionModel->getID($DiscussionID);
        if (!$Discussion) {
            $topicstring = 'no discussion';
            return;
        }
        //First get the Topic field for the current discussion
        //
        if ($Debug) {
            $this->DebugData($DiscussionID, '---DiscussionID---', $Debug);
            $this->DebugData($Discussion->Topic, '---Topic---', $Debug);
        }
        if ($Discussion->Topic == '') {
            $topicstring = wrap('', 'span id=TitleBox');
            return;
        }
        //
        // Get the list of categories this plugin is enabled to work on
        $Catnums = c('Plugins.DiscussionTopic.CategoryNums');
        //
        //Get the cetegory ids the user is allowed to see
        $Categories = CategoryModel::getByPermission();
        $Categories = array_column($Categories, 'Name', 'CategoryID');
        //if ($Debug)  $this->DebugData($Categories, '---Categories---', $Debug);
        $Categorycount = 0;
        foreach ($Categories as $CategoryID => $CategoryName) {
            //$this->DebugData($CategoryID, '---CategoryID---', $Debug);
            //$this->DebugData($CategoryName, '---CategoryName---', $Debug);
            if ($Catnums != "") {  //Limited category list?
                if (in_array($CategoryID, $Catnums)) {  //In the list?
                    $Categorycount = $Categorycount + 1;
                    $Categorylist[$Categorycount] = $CategoryID;
                }
            } else {
                $Categorycount = $Categorycount + 1;
                $Categorylist[$Categorycount] = $CategoryID;
            }
        }
        if ($Debug) $this->DebugData($Categorycount, '---Categorycount---', $Debug);
        if ($Categorycount == 0) {
            $topicstring = wrap('','span id=TitleBox');
            return;
        }
        if ($Debug) $this->DebugData($Categorylist, '---Categorylist---', $Debug);
        //

        $Topic = $Discussion->Topic;
        $TopicTitle = str_replace(array('\'', '"'), '', $Topic);
        if ($Debug) $this->DebugData($Topic, '---Topic---', $Debug);
        $Uselimit = $Limit + 1;
        $AlsoSql = clone Gdn::sql();    //Don't interfere with any other sql process
        $AlsoSql->Reset();              //Clean slate
        $Sqlfields = 'd.DiscussionID,d.Name,d.CategoryID,d.Topic,d.TopicAnswer';
        $Discussionlist = $AlsoSql      //Get expanded tag info for this discussion
            ->select($Sqlfields)
            ->from('Discussion d')
            ->where('d.Topic', $Topic)
            ->wherein('d.CategoryID', $Categorylist)
            ->where('d.DiscussionID <>', $DiscussionID)
            ->orderby('d.TopicAnswer', 'DESC')
            ->orderby('d.DiscussionID', 'DESC')
            ->limit($Uselimit)
            ->get();
        //
        //
        if ($Debug) $this->DebugData($Discussionlist, '---Discussionlist---', $Debug);
        //
        $Rowcount = count($Discussionlist);
        if ($Debug) echo '<br>'.__LINE__.' Rowcount:'.$Rowcount;
        if ($Debug) $this->DebugData($Discussionlist, '---Discussionlist---', $Debug);
        $Panelhead = c('Plugins.DiscussionTopic.Paneltitle', t('Related Topics'));
        SaveToConfig('Plugins.DiscussionTopic.Paneltitle', $Panelhead);
        if ($Rowcount == 0) {
            $topicstring = wrap('', 'span id=TitleBox');
            return;
        }
        $More = '';
        if ($Rowcount >  $Limit) {
            $More = wrap(t('There are more').'...', 'li class=HashTagsMore Title="'.t('There are more discussions with the same topic').'"');
        }
        //
        $TopAnswerMode = c('Plugins.DiscussionTopic.TopAnswerMode', false);
        $Listcount = 0;
        foreach ($Discussionlist as $Entry) {
            if ($Debug) $this->DebugData($Entry, '---Entry---', $Debug);
            $DiscussionID = $Entry->DiscussionID;
            $Discussion = $DiscussionModel->getID($DiscussionID);
            $CategoryID = $Discussion->CategoryID;
            if (in_array($CategoryID, $Categorylist)) {  //Support Vanilla permission model -verify user can see discussions in the category
                $Title = $Discussion->Name;
                $EntryTitle = str_replace(array('\'', '"'), '', $Discussion->Name);
                $TopicAnswer = $Discussion->TopicAnswer;
                $Emphsize = ' ';
                if ($TopAnswerMode) {
                    $Emphasize = '○';
                    if ($TopicAnswer) {
                        $EntryTitle = t('Top Topic').':'.$EntryTitle;
                        //$Emphasize = '◉';
                        $Emphasize = '✪';
                    }
                }
                //if ($Debug) echo '<br>'.__LINE__.' ConversationString:'.$topicstring;
                $Anchor = wrap(wrap($Emphasize, 'span class=Emphasize id=Emphasize'.$DiscussionID).
                    Anchor(SliceString($Discussion->Name, 40), '/discussion/'.$DiscussionID.'/?Key=('.$Discussion->Topic.')', 'RelatedItemLink '), 'li class=RelatedDiscussions Title="'.$EntryTitle.'"');
                $topicstring =  ' ' . $topicstring . ' ' . $Anchor;
                $Listcount = $Listcount + 1;
            //} else {
            //  echo '<br>'.__LINE__.'Not allowed- CategoryID:'.$CategoryID.' DiscussionID:'.$DiscussionID;
            }
        }
        if (!$Listcount) {
            $topicstring = wrap('', 'span id=TitleBox');
            return;
        }
        $topicstring =  $topicstring . ' ' . $More;
        $Panelhead = anchor($Panelhead, '/plugin/DiscussionTopic/DiscussionTopicSearch?s='.$Topic, $Panelhead);
        $topicstring =  wrap(
            panelHeading($Panelhead).
            wrap($topicstring, 'ul class="PanelInfo PanelCategories" title="'.t('click to view discussion').'"'),
            'DIV class="Box BoxCategories"  id=TitleBox title="'.t('Discussions with this title:').$TopicTitle.'"'
        );
        //
        return false;
    }
    /**
     * Sett the asset name where the panel appears
     *
     * @return string Asset name
     */
    public function assettarget() {
        return 'Panel';
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
            $Message =  '>'.debug_backtrace()[1]['class'].':'.
                        debug_backtrace()[1]['function'].':'.
                        debug_backtrace()[0]['line'].' called by '.
                        debug_backtrace()[2]['function'];
        } else {
            $Message = '>'.debug_backtrace()[1]['class'].':'.debug_backtrace()[1]['function'].':'.debug_backtrace()[0]['line'].' '.$Message;
        }
        if ($Inform == true) {
            Gdn::controller()->informMessage($Message);
        } else {
            decho($Data, $Message);
        }
    }
}
