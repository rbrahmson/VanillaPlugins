<?php
/**
 * RelevantTitle plugin. INITIAL BETA RELEASE - still contain debugging code
 *
 */

$PluginInfo['RelevantTitle'] = array(
    'Name' => 'RelevantTitle',
    'Description' => 'Verify that discussion titles are relevant to the discussion body. ',
    'Version' => '1.2.2',
    'RequiredApplications' => array('Vanilla' => '2.2'),
    'RequiredTheme' => false,
    'MobileFriendly' => true,
    'HasLocale' => true,
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => "Roger Brahmson",
    'License' => "GNU GPL3"
);
define('WORD_MIN_LENGTH', 2);       //Minimum word size to compare against body content
define('STEM_MIN_LENGTH', 3);       //Minimum word size to perform stemming analysis
define('STEM_PREFIX_LENGTH', 4);    //Stem size where matching as word prefix
define('STEM_EMBED_LENGTH', 8);     //Stem size where metching as embedded in word
/**
* Plugin to Require that the discussion title is relevant to the discussion body.
*/
class RelevantTitlePlugin extends Gdn_Plugin {

/**
* This hook handles the saving of the initial discussion body (but not comments).
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function discussionModel_beforesavediscussion_handler($Sender, $Args) {
        $Debug = intval($_GET['!DEBUG!']);
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $FormPostValues = $Sender->EventArguments['FormPostValues'];
        //
        // Bypass process for moved discussions with leftover redirection link
        if ($FormPostValues["Type"] == 'redirect' & $FormPostValues["Closed"]) {
            return;
        }
        //
        // Chech whether rules enforcement is in effect during save
        if (!$this->checkenforce('save', $Args['Discussion'], $Debug)) {
            return;
        }
        // Chech whether rules enforcement is in effect for the current category
        if (!$this->enforceincategory($FormPostValues["CategoryID"], $Debug)) {
            return;
        }
        //
        $TopWarning = $this->CheckTitleWords(
            $FormPostValues["Name"],
            $FormPostValues["Body"],
            intval($FormPostValues['DiscussionID']),
            c('Plugins.RelevantTitle.MinWords', 1),
            c('Plugins.RelevantTitle.MaxWords', ''),
            'longform',                      //Long form feedback
            $Debug
        );
        if (trim($TopWarning) != '') {
            $Sender->Validation->addValidationResult('Title', $TopWarning. ' '.t('Please reword'));
            echo wrap($TopWarning, 'div class="RelevantTitlehead"');
            Gdn::controller()->informMessage($TopWarning);
        }
    }

/**
* Procees discussion additions by the Feed Discussions plugin.
*
* @param object $Sender standard
* @param object $Args   standard
*
* @return na
*/
    public function feeddiscussionsplugin_publish_handler($Sender, $Args) {
    //public function feeddiscussionsplugin_publish_handler($Sender, $Args) {
        $Debug = intval($_GET['!DEBUG!']);
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        //
        // Check whether rules enforcement is in effect during feed import
        //
        if (!$this->checkenforce('feed', 0, $Debug)) {
            return;
        }
        //
        $Publish = $Sender->EventArguments['Publish'];
        $FeedDiscussion = $Sender->EventArguments['FeedDiscussion'];
        //
        // Chech whether rules enforcement is in effect for the current category
        //
        $CategoryID = $FeedDiscussion['CategoryID'];
        if (!$this->enforceincategory($CategoryID, $Debug)) {
            return;
        }
        $Name = strip_tags($FeedDiscussion['Name']);
        //
        $TopWarning = $this->CheckTitleWords(
            $Name,
            strip_tags($FeedDiscussion['Body']),
            0,
            c('Plugins.RelevantTitle.MinWords', 1),
            c('Plugins.RelevantTitle.MaxWords', ''),
            'longform',                      //Long form feedback
            $Debug
        );
        //
        if (trim($TopWarning) != '') {
            $TopWarning = 'RelevantTitle Plugin: Feed item rejected:<br> '.$Name.'<br> '.$TopWarning;
            echo wrap($TopWarning, 'div class="RelevantTitlehead"');
            Gdn::controller()->informMessage($TopWarning);
            //trace($TopWarning);
            $Sender->EventArguments['Publish'] = false;
        }
    }

/**
* Check for cornforming title if so requested (when the discussion is viewed).
*
* @param object $Sender standard
* @param object $Args   standard
*
* @return na
*/
    public function discussionController_discussioninfo_handler($Sender, $Args) {
        $Debug = intval($_GET['!DEBUG!']);
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        //
        // Used bu report link to display discussion with non-conforming messages
        //
        $TestRelatedTitle = trim($_GET['!Test!Related!Title!']);
        if ($TestRelatedTitle != 'Y') {
            return;
        }
        //
        $Sender->addCssFile('relevanttitle.css', 'plugins/RelevantTitle');
        //
        // Chech whether rules enforcement is in effect during save
        if (!$this->checkenforce('view', $Args['Discussion'], $Debug)) {
            return;
        }
        //
        $TopWarning = $this->CheckTitleWords(
            $Args['Discussion']->Name,
            $Args['Discussion']->Body,
            $Args['Discussion']->DiscussionID,
            c('Plugins.RelevantTitle.MinWords', 1),
            c('Plugins.RelevantTitle.MaxWords', ''),
            'longform',                      //Long form feedback
            $Debug
        );
        if (trim($TopWarning) != '') {
            echo wrap($TopWarning, 'div class="RelevantTitlehead"');
        }
        //
    }
/**
* Check whether rules should be enforce for the calling function.
*
* @param  string  $Caller     Calling request (e.g. "save", "feed", "view","report")
* @param  string  $Discussion Optional Discussion to validate
* @param  boolean $Debug      Debug request
*
* @return boolean True if category ID is which the rules scope
*/
    private function checkenforce($Caller, $Discussion, $Debug = false) {
        //$this->debugData('', '', $Debug);  //Trace Function calling
        //
        if (c('Plugins.RelevantTitle.IncompleteSetup', false)) {
            if ($Caller != 'report') {
                return false;       //No enforcement
            }
            $Msg = t('RelevantTitle plugin setup is incomplete');
            Gdn::controller()->informMessage($Msg, 'DoNotDismiss');
            echo wrap($Msg, 'h2');
            throw new Gdn_UserException(T($Msg));
            return false;
        }
        if ($Caller == 'report') {
            return true;             //Report can run without enforcement
        }
        // Bypass validation if enforcement is not enabled
        if (!c('Plugins.RelevantTitle.Enforce', false)) {
            if ($Caller == 'view' | $Caller == 'report') {
                echo wrap(t('RelevantTitle plugin is not set to enforce the rules'), 'div class="RelevantTitlehead"');
            }
            return false;                           // then don't enforce rules
        }
        // Bypass feed validation if enforcement is not enabled
        if ($Caller == 'feed') {
            if (!c('Plugins.RelevantTitle.EnforceOnFeeds', false)) {
                return false;                       // then don't enforce rules
            }
        } elseif ($Caller == 'save') {
            // Bypass validation discussion if saved by system
            if ($Discussion) {
                if ($Discussion->InsertUserID == Gdn::UserModel()->GetSystemUserID()) {
                    //Discussion created by the system ID?
                    return false;                           // then don't enforce rules
                }
            }
        }
        return true;
    }
/**
* Check whether rules applies to the specific category.
*
* @param  string  $CategoryID Category ID to check
* @param  boolean $Debug      Debug request
*
* @return boolean True if category ID is which the rules scope
*/
    private function enforceincategory($CategoryID, $Debug = false) {
        //$this->debugData('', '', $Debug);  //Trace Function calling
        $Categories = c('Plugins.RelevantTitle.Categories', '');
        if ($Categories != '') {
            if (!in_array($CategoryID, $Categories)) {
                return false;
            }
        }
        return true;
    }
/**
* Remove URLs from a text string.
*
* @param  string  $Text  Text to process
* @param  boolean $Debug Debug request
*
* @return string without embedded urls
*/
    private function removeurl($Text, $Debug = false) {
        //$this->debugData('', '', $Debug);  //Trace Function calling
        if ($Text == '') {
            return $Text;
        }
        $Pattern = '/\b(https?|ftp|file|mailto):\/\/[-A-Z0-9+&@#\/%?=~_|$!:,.;]*[A-Z0-9+&@#\/%=~_|$]/i';
        preg_match_all($Pattern, $Text, $matches);
        $Urls = $matches[0];    // Found embedded urls
        //
        $Clean = preg_replace($Pattern, ' >>URL<< ', $Text, -1, $Count);
        if ($Count) {
            return $Clean;
        }
        //
        return $Text;
        //
    }
/**
* Build the array of noise words.
*
* @param  array   $NoiseArray NoiseArray
* @param  boolean $Debug      Debug request
*
* @return array noise array
*/
    private function getnoisearray($NoiseArray, $Debug = false) {
        //$this->debugData('', '', $Debug);  //Trace Function calling
        if (!empty($NoiseArray)) {
            return $NoiseArray;
        }
        $NoiseWords = c('Plugins.DiscussionSubject.NoiseWords', ' ');
        $Localnoisearray = explode(',', $NoiseWords.' ,,');
        $Localnoisearray = array_map('trim', $Localnoisearray);
        $Localnoisearray = array_filter($Localnoisearray);
        //
        $Globalnoisearray = array('');
        $Globalnoisearray = t('RelevanTitleVeryShortNoiseWordsList');
        //
        $NoiseArray = array_merge($Localnoisearray, $Globalnoisearray);
        $NoiseArray = array_map('strtolower', $NoiseArray);
        $NoiseArray = array_map('ucwords', $NoiseArray);
        return $NoiseArray;
        //
    }
/**
* Validate Minimum Number of Words in the Discussion Title Exist in the Discussion Body.
*
* @param  String  $Name         Discussion TItle
* @param  String  $Body         Discussion Body
* @param  String  $DiscussionID Discussion ID (for error messages)
* @param  Int     $MinWords     Minimum number of body words that must appear in the title
* @param  Int     $MaxWords     Maximum number of body words that must appear in the title
* @param  boolean $Feedbacktype Indicated the type of feedbac to return
* @param  boolean $Debug        Debug request
*
* @return string  return empty string (title is conforming) or error message text.
*/
    private function checktitlewords(
        $Name,
        $Body,
        $DiscussionID,
        $MinWords = 2,
        $MaxWords = 2,
        $Feedbacktype = 'longform',
        $Debug = false
    ) {
        //$this->debugData('', '', $Debug);  //Trace Function calling
        //
        $LongMsg = ($Feedbacktype == 'longform')?
            sprintf(
                t('Title should have at least %s in the discussion content. '),
                Plural(number_format($MinWords), t('one related word'), t('%s related words'))
            )
            :
            ' ';
        //
        $Anchor = ($DiscussionID == 0)?
                    '<br><b>':
                    '<br>DiscussionID:<b>'.
                Anchor($DiscussionID, '/discussion/'.$DiscussionID.'/?!Test!Related!Title!=Y', array('target'=>"Discussion"));
        //
        $MinWords = ($MinWords+0);
        $MaxWords = ($MaxWords+0);
        //
        $Delimiters = array("&nbsp;", "/", "!", "#", ":", ";", "?", "-", "_","^", "'", '“', '”', ',', '(', ')', '[', ']', '~', "\"");
        $Spaces = array(' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ' );
        $Title = str_replace($Delimiters, $Spaces, $Name);
        /* Different levels of aggressiveness at tokenizong input string
        $Tokens = preg_split("/[\s,]*\\\"([^\\\"]+)\\\"[\s,]*|" . "[\s,]*'([^']+)'[\s,]*|" . "[\s,]+/",
                            $Title, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);
        //
        $Tokens = preg_split("/\s+|\b(?=[!\?\.])(?!\.\s+)/", $Title, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);  //Less aggressive
        */
        $Tokens = preg_split("/\s+/u", $Title, 0, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE);  //even Less aggressive
        $Count = count($Tokens);
        //
        if ($Count == 0) {
            if ($Feedbacktype = 'justfounds') {
                return '';
            }
            return $LongMsg.t('The discussion title is obscure.');
        }
        if ($Count < $MinWords) {
            if ($Feedbacktype != 'justfounds') {
                return $LongMsg.sprintf(t('Only %d title words found.'), $Count);
            }
        } elseif (($MaxWords>0) & ($Count > $MaxWords)) {
            if ($Feedbacktype != 'justfounds') {
                $Words = Plural(number_format($Count), t('%s word'), t('%s words'));
                return sprintf(
                    t('The title should not have more than %s.'),
                    Plural(
                        number_format($MaxWords),
                        t('%s word'),
                        t('%s words')
                    )
                );
            }
        }
        //
        $Tokens = array_map('ucwords', $Tokens);
        $Tokens = array_unique($Tokens);
        $Tokens = array_values($Tokens);
        //
        $Text = Gdn_Format::plaintext($Body);               //Only analyze plain text
        if (trim($Text) == '' & trim($Body) != '') {
            $TopWarning = t('Discussion body content is not eligible for analysis.');
            if ($Feedbacktype == 'shortform') {
                return $TopWarning;
            } elseif ($Feedbacktype == 'longform') {
                echo wrap($Anchor.' </b>Note:'.$TopWarning.' Title:'.SliceString($Title, 60).
                        ' <b>Body:</b> '. SliceString(strip_tags($Body), 30).' ', 'div');
            }
            return '';
        }
        //
        $Text = $this->removeurl($Text, $Debug);            //Remove unlinked urls Vanilla leaves behind
        $Text = trim(preg_replace('/\s+/u', ' ', $Text));   //Squeeze blanks
        //
        if (trim($Text) == '' & trim($Body) != '') {
            $TopWarning = t('Url-only discussion body is not analyzed.');
            if ($Feedbacktype == 'shortform') {
                return $TopWarning;
            } elseif ($Feedbacktype == 'longform') {
                echo wrap($Anchor.' </b>Note:'.$TopWarning.' Title:'.SliceString($Title, 60).
                    ' <b>Body:</b> '. SliceString(strip_tags($Body), 30).' ', 'div');
            }
            return '';
        }
        $Text = strip_tags($Text);                           //Clear remaining html tags
        //
        $Text = str_replace($Delimiters, $Spaces, $Text);
        if (trim($Text)=='') {
            $TopWarning = t('Empty discussion body is not analyzed.');
            echo wrap($Anchor.' </b>Note:'.$TopWarning.' Title:'.SliceString($Title, 60).
                    ' <b>Body:</b> '. SliceString(strip_tags($Body), 30).' ', 'span');
            return '';
        }
        //
        $NoiseArray = array();
        $NoiseArray = $this->GetNoiseArray($NoiseArray, $Debug);
        $NoiseWords = '';
        $FoundWords = '';
        $FoundTokens = 0;
        $Unfounds = array();
        $Count = count($Tokens);
        $j = 0;
        $i = 0;
        while ($i < $Count) {
            $Token = ucwords(strtolower(trim($Tokens[$i])));
            if (in_array($Token, $NoiseArray)) {
                $NoiseWords = $NoiseWords . '"' . $Token .'" '; //Ignored noise words
            } elseif ($Token != '' & (strlen($Token)>=WORD_MIN_LENGTH)) {
                $Pattern = '/\b'.$Token.'\b/i';                 //Search fully delimited word
                $Found = preg_match($Pattern, $Text, $FoundArray);
                //$this->DebugData($Found.':'.$Token, '---Found:Token---', $Debug);
                if ($Found) {
                    $FoundTokens += 1;
                    $FoundWords = $FoundWords . '"' . $Token .'",';
                    if ($FoundTokens >= $MinWords) {
                        return '';      //Success-return without error message
                        //Comment previous lines to reveal found related words
                        $Words = Plural(number_format($FoundTokens), t('%s word'), t('%s words'));
                        $TopWarning = sprintf(t('This discussion title is valid - found %s ("%s")'), $Words, $FoundWords);
                        return $TopWarning;
                    }
                } else {    //word no found. Process it's stem only if it makes sence
                    if ((strlen($Token)>=STEM_MIN_LENGTH) & (!preg_match("/[0-9]+/", $Token))) {
                        $Unfounds[$j] = $Token; //Only stem-search at words worth the effort
                        $j += 1;
                    }
                    //$this->DebugData($j.':'.$Token, 'j:---Not found:---', $Debug);
                }
            }
            $i +=1;
        }
        if ($Feedbacktype == 'justfounds') {   //Only report on found words (used for noisewords development)
            return $FoundWords;
        }
        //  Still missing some (or all) matching words.
        //  Trying once again with word stems (less efficient so we use this sparingly)
        //
        $Unfounds = array_unique($Unfounds);
        $i = 0;
        while ($i < $j) {                       //Scan the non-found words
            $Token = $Unfounds[$i];
            $Stem =  PorterStemmer::Stem($Token);
            $Pattern = '/\b'.$Stem.'\b/i';
            if (strlen($Stem)>=(0+STEM_EMBED_LENGTH)) {
                $Pattern = '/'.$Stem.'/i';
            } elseif (strlen($Stem)>=(0+STEM_PREFIX_LENGTH)) {
                $Pattern = '/\b'.$Stem.'/i';
            }
            $Found = preg_match($Pattern, $Text, $FoundArray);
            //$this->DebugData($Found.':'.$Stem, '---Found:Stem---', $Debug);
            if ($Found) {
                $FoundTokens += 1;
                $FoundWords = $FoundWords . '"' . $Token .'",';
                if ($FoundTokens >= $MinWords) {
                    return '';      //Success-return without error message
                    //Comment previous lines to reveal found related words
                    $Words = Plural(number_format($FoundTokens), t('%s word'), t('%s words'));
                    $TopWarning = sprintf(t('This discussion title is valid - found %s ("%s")'), $Words, $FoundWords);
                }
            }
            $i +=1;
        }
        //
        if ($FoundTokens == 0) {
            return t('Related words were not found.').' '.$LongMsg;
        } elseif ($FoundTokens < $MinWords) {
            $NoiseMsg = (trim($NoiseWords) == '')?
                ''
                :
                sprintf(t(' (ignoring:%s).'), $NoiseWords);
            $TopWarning = $LongMsg.' '.sprintf(
                t('Found only %s: %s.'),
                Plural(number_format($FoundTokens), t('one relevant word'), t('%s relevant words')),
                trim($FoundWords, ',')
            ).$NoiseMsg;
        }
        return $TopWarning;
        //
    }
/**
* Report Dispatcher.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function controller_relevanttitlereport($Sender, $Args) {
        $this->RunRelevantTitleReport($Sender);
    }
/**
* Handle Database non-conformity report request. Fublic to be callable from the view.
*
*  @param object $Sender Standard
*
*  @return boolean n/a
*/
    public function runrelevanttitlereport($Sender) {
        $Debug = intval($_GET['!DEBUG!']);
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        //
        // This fails from dispatched view!: $Sender->permission('Garden.Settings.Manage')
        if (!Gdn::Session()->CheckPermission('Garden.Users.Edit')) {  //See above comment.
            echo wrap('Function Not authorized', 'h1');
            return;
        }
        // Check whether rules enforcement is in effect during save
        if (!$this->checkenforce('report', 0, $Debug)) {
            return;
        }
        //  Set report starting point from last run or parameter
        $BatchStart = c('Plugins.RelevantTitle.BatchStart', 1);
        $Restart = intval($_GET['restart']);
        if ($Restart == 1) {
            $BatchStart = 1;
        }
        //  Set report batch size from config or passed parameter
        $Limit = intval($_GET['limit']);
        $BatchSize = (0 +$Limit);
        if ($BatchSize == 0 | !is_numeric($BatchSize)) {
            $BatchSize = c('Plugins.RelevantTitle.BatchSize', 500);
        }
        //  Handle request for found words reporing (used for noise words analisys)
        $Justfounds = intval($_GET['justfounds']);
        $Checkform = ($Justfounds)?
                        'justfounds':
                        'shortform';
        //  Get processed categories from config or passed parameter
        $Categories = $_GET['category'];
        if (trim($Categories) == '') {
            $Categories = c('Plugins.RelevantTitle.Categories', '');
        }
        //
        $Categories = $Array = array_unique($Categories);
        $Categories = $Array = array_filter($Categories);
        //
        $MinWords = c('Plugins.RelevantTitle.MinWords', 1);
        $MaxWords = c('Plugins.RelevantTitle.MaxWords', 20);
        //
        $UseLimit = ($BatchSize + 1);
        //
        $SqlHandle = clone Gdn::sql();  //Don't interfere with any other sql process
        $SqlHandle->Reset();            //Clean slate
        $Discussionlist = $SqlHandle
            ->select('d.DiscussionID,d.Name,d.CategoryID,d.Body')
            ->from('Discussion d')
            ->where('d.DiscussionID >=', $BatchStart)
            ->orderby('d.DiscussionID')
            ->limit($UseLimit);
        $CatTitle = t('All categories are being procesessed').'<br>';
        if (count($Categories)) {
            // Add categories filter to the Sql
            $Discussionlist = $SqlHandle->wherein('d.CategoryID', $Categories);
            //
            $CatTitle = t('Processed Categorie(s):<b> ').implode(',', $Categories).'</b><br>';
        }
        //  Get the record set
        $Discussionlist = $SqlHandle ->get();
        //
        //  Prepare report messages
        //
        $MaxTitle = ($MaxWords == '')?
                        '':
                        '</b> Maximum number of words:<b>'.$MaxWords.'</b>';
        //
        $EnforceMsg = (c('Plugins.RelevantTitle.Enforce', false))?
                        '':
                        wrap(t('RelevantTitle plugin rules are not enforced (see the plugin setup)'), 'div class=ReportNote');
        //
        $ReportName = ($Justfounds)?
                        t('Relevanttitle Plugin - Found Title Words Report.'):
                        t('RelevantTitle plugin Conformance Report.');
        //
        $UseMins = ($Justfounds)?
                        80:
                        $MinWords;
        //
        $Title = wrap('<h2>'.$ReportName.'</h2>'.$EnforceMsg.
            'Starting DiscussionID:<b>'.$BatchStart.'</b> Batch Size:<b>'.$BatchSize.'. </b> '.$CatTitle.
            'Title size rules:  Minimum Relevant words:<b>'.$MinWords.$MaxTitle.'</b>', 'div');
        echo wrap($Title, 'div class=ReportHead');
        //
        $ContinueMsg = wrap(
            'Click '.
            Anchor(t('Continue'), 'plugin/RelevantTitle/RelevantTitleReport/?!DEBUG!='.
                    $Debug.'&restart=0&limit=0'.$BatchSize.'&justfounds='.$Justfounds, $Button).
                    t(' to run the report from the next unprocessed record. '),
            'div  class=ReportButtons'
        );
        $RestartMsg = wrap(
            'Click '.
            Anchor(t('Restart'), 'plugin/RelevantTitle/RelevantTitleReport/?!DEBUG!='.
                    $Debug.'&restart=1&limit=0'.$BatchSize.'&justfounds='.$Justfounds, $Button).
                    t(' to run the report from the first record. '),
            'div  class=ReportButtons'
        );
        //
        $RowCount = count($Discussionlist);
        if ($RowCount == 0) {
            echo wrap(
                '<br>  The report criteria (categories or starting discussionID#) did not match any discussion records.'.
                '<br>  Consider resetting the batch start number is reset to zero. '.$RestartMsg,
                'div class=ReportMsg'
            );
            return;
        }
        //
        //  Display report headings
        //
        if ($RowCount <= $BatchSize) {
            echo $RestartMsg.wrap(
                t('No more records to process under the current settings. '),
                'div class=ReportSummary'
            );
        } else {
            echo $ContinueMsg;
        }
        //
        $ReportRowNumber = 0;
        $Listcount = 0;
        $Warnings = 0;
        $ProessList = '';
        $FoundsArray = array();
        $SqlHandle->Reset();
        echo '<span class="ReportBlock">';      //Report block wrapper
        //
        foreach ($Discussionlist as $Entry) {
            $Listcount += 1;
            $Background = ($Warnings%2 == 0)?
                        'background-color: #eef8ff;display: block;line-height: 9pt;':
                        'background-color: #fbfbee;display: block;line-height: 9pt;';
            if ($Listcount <= $BatchSize) {
                $ReportRowNumber += 1;
                $ProessList = $ProessList . ' ' . $Entry->DiscussionID;
                $HighWatermark = $Entry->DiscussionID;
                $CategoryID = $Entry->CategoryID;
                $TopWarning = $this->CheckTitleWords(
                    $Entry->Name,
                    $Entry->Body,
                    $Entry->DiscussionID,
                    $UseMins,
                    $MaxWords,
                    $Checkform,                      // feedback type
                    $Debug
                );
                //
                if (trim($TopWarning) != '') {  //Any non-conformance message?
                    $Warnings += 1;
                    if ($Justfounds) {
                        $FoundsArray = array_merge($FoundsArray, explode(',', trim($TopWarning, ',')));
                    } else {
                        $Anchor = Anchor($Entry->DiscussionID, '/discussion/'.
                            $Entry->DiscussionID.
                            '/?!Test!Related!Title!=Y', array('target'=>"Discussion"));
                        echo wrap(
                            '<br> DiscussionID:<b>'.$Anchor.'</b> CategoryID:<b>'.$CategoryID.'</b>'.
                            ' Title:<span style="Color:blue">'.SliceString($Entry->Name, 60).'</span>'.
                            ' <span style="Color:red">'.$TopWarning.'</span>',
                            'span style="'.$Background.'"'
                        );
                        $TopWarning = '';
                    }
                } else {
                    $OKMsg = wrap(
                        '<br> DiscussionID:<b>'.$Entry->DiscussionID.'</b> CategoryID:<b>'.$CategoryID.
                        ' </b>Title:<b>'.SliceString($Entry->Name, 60).' </b>  OK</b>',
                        'span'
                    );
                }
            }
        }
        echo'</span>';              //Close report block
        SaveToConfig('Plugins.RelevantTitle.BatchStart', (1+$HighWatermark));
        //
        if ($Listcount == 0) {
            echo wrap(
                '<br>  No discussions matched the current report criteria.',
                'div class=ReportMsg'
            );
        } elseif ($Justfounds) {
            $FoundsArray = array_unique($FoundsArray);
            $FoundsArray = array_values($FoundsArray);
            asort($FoundsArray);
            echo wrap(implode(", ", $FoundsArray), 'div class=ReportSummary');
            echo wrap(
                $ReportRowNumber. ' records were scanned. '.
                wordwrap('Processed DiscussionIDs: <br>'.$ProessList, 180, '<br>'),
                'div class=ReportSummary'
            );
            return;
        } else {
            $NonConformingMsg = ($Warnings)?
                        $Warnings . ' non-conforming listed above.<br>':
                        'All records were conforming.<br>';
            echo wrap(
                $ReportRowNumber. ' records were scanned. '.$NonConformingMsg.
                wordwrap('Processed DiscussionIDs: '.$ProessList, 180, '<br>'),
                'div class=ReportSummary'
            );
        }
    }
/**
* Plugin Settings.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
* @return boolean n/a
*/
    public function settingscontroller_relevanttitle_create($Sender, $Args) {
        $Sender->Permission('Garden.Settings.Manage');
        //
        $Debug = intval($_GET['!DEBUG!']);
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        //
        $Sender->addCssFile('pluginsetup.css', 'plugins/RelevantTitle');
        $Sender->addSideMenu('dashboard/settings/plugins');
        //
        $Restart = intval($_GET['restart']);    //Tricky way to restart setup
        if ($Restart == 1) {
            SaveToConfig('Plugins.RelevantTitle.BatchStart', '1');
            Redirect('..'.url('/settings/RelevantTitle'));
        }
        //  Make the admin aware of Feed Discussion plugin
        $FeedPluginMsg = (!Gdn::PluginManager()->CheckPlugin('FeedDiscussions'))?
                        wrap(
                            'Note: The '.
                            Anchor(
                                '"Feed Discussions"',
                                'http://vanillaforums.org/addon/feeddiscussions-plugin',
                                array('target'=>"New")
                            ).t(' plugin is currently not enabled.'),
                            'span  class=FieldDescriptionContinue'
                        ):
                        '';
        //
        //  Prepare simulated right-side panel messages
        //
        $BatchStart = c('Plugins.RelevantTitle.BatchStart', '1');
        $RunBatch = wrap(
            '<h3>'.t('Relevant Title Reports').'</h3>'.
            wrap(t('Remember to save settings <i>before</i> running any report.').'<br>'.
                 t('Note that results/overhead depends on the report batch size.'), 'span  class=SettingAsideN').
            wrap(
                wrap(t('Conformance Report'), 'span  class=SettingAsideSubhead').
                    t('The report reveals discussions that do not conform to the set rules.').
                ' Click '.
                Anchor(t('report'), '/plugin/RelevantTitle/RelevantTitleReport/?!DEBUG!='.$Debug.
                    '&restart=0&limit=0', 'Button', array('target'=>"New")).t(' to run this report.'),
                'span  class=SettingAsideN'
            ).
            wrap(
                wrap(t('Found Words Report'), 'span  class=SettingAsideSubhead').
                t('The report reveals the title words that were found in the discussion contents.').
                ' Click '.
                Anchor(t('report'), '/plugin/RelevantTitle/RelevantTitleReport/?!DEBUG!='.$Debug.
                    '&restart=0&limit=0&justfounds=1', 'Button', array('target'=>"New")).t(' to run report '),
                'span  class=SettingAsideN'
            ),
            'div class=SettingAside'
        );
        $IncompleteSetup = c('Plugins.RelevantTitle.IncompleteSetup', false);
        if ($IncompleteSetup) {
            $RunBatch = '';
        }
        $RulesHelp =
<<<EOL
● The plugin checks whether <i>English</i> title words appears to be related to the discussion body while ignoring some <i>noise words</i> like
 "the", "a", "will" and matching <i>some</i> word variants like "Bank", "Banks", "banking" and "banked".  For efficienty this is not a
 fullproof process but when prompted to change the title users can easily be more explicit and make it work. <br>
● URLs and html tags within the discussion body are not matched against the title.
EOL;
        $Help =
<<<EOL
● This plugin is designed to increase discussions visibility with rules that attempt to make titles relvant to the discussion content.<br>
● Depending on the rules you set, the plugin may even stop some bots in their tracks.<br>
● You can run the conformance reports against existing discussions even when the rules are not enforced.  Use this to see the effects of your title rules.
EOL;
        $Help = wrap(wrap(t('<b>Introduction</b>'), 'h3').wrap(t($Help), 'span  class=SettingAsideN'), 'div class="SettingAside"');
        $RulesHelp = wrap(wrap(t('Relevance Heuristics'), 'h3').wrap(t($RulesHelp), 'span  class=SettingAsideN'), 'div class="SettingAside"');
        //
        $RightColumn = wrap($Help.'<br>'.$RulesHelp.'<br>'.$RunBatch, 'div class="AsideColumn"');
        //
        $SetupTitle = wrap(t('Relevant Title Plugin Settings'), 'span class="SettingNote"');
        //
        //Get list of existing categories
        $DefinedCategories = CategoryModel::categories();
        // Remove the "root" categorie from the list.
        unset($DefinedCategories[-1]);
        //
        $ConfigurationModule = new ConfigurationModule($Sender);
        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $Sender->Form->SetModel($ConfigurationModel);
        //
        //  Create the form through the schema.
        //  Variable messages embedded in the labels and descriptions
        //  which along with css create setting sections and informative feedback.
        //
        $ConfigurationModule->Schema(array(
                'Plugins.RelevantTitle.Enforce' => array(
                'Control' => 'Checkbox',
                'Description' =>    wrap(t(' Rules Enforcement'), 'span class="SettingHeadFirst"').$RightColumn,
                'LabelCode' =>  wrap(
                    t('Enforce title rules when saving discussions (Turn off to run reports to check how existing discussions conform to the rules)'),
                    'span class="FieldCheckBox"'
                ),
                'Default' => false,
            ),
                'Plugins.RelevantTitle.EnforceOnFeeds' => array(
                'Control' => 'Checkbox',
                'Description' =>   '',
                'LabelCode' =>  wrap(
                    t('Enforce title rules when the "Feed Discussions" plugin is creating discussions (feed import)').$FeedPluginMsg,
                    'span class="FieldCheckBox"'
                ),
                'Default' => false,
            ),
                'Plugins.RelevantTitle.Categories' => array(
                'Control' => 'CheckBoxList',
                'Items' => $DefinedCategories,
                'Description' =>    wrap(
                    t('Select the categories where this plugin is active (no selection enables all categories):'),
                    'div class="ChecklistDescription"'
                ),
                'LabelCode' =>  wrap(t('Processing Scope '), 'div class="SettingHead"'),
                'Options' => array('ValueField' => 'CategoryID', 'TextField' => 'Name', 'class' => 'Categorylist CheckBoxList'),
                'Default' => ' ',
            ),
                'Plugins.RelevantTitle.MaxWords' => array(
                'Control' => 'textbox',
                'Description' =>    wrap(
                    t('Maximun number of words in the discussion title (Leave blank for no maximum):'),
                    'span class="FieldDescription"'
                ),
                'LabelCode' =>    wrap(t('Title Rules'), 'span class="SettingHead"'),
                'Options' => array('MultiLine' => false, 'class' => 'TextNarrowInputBox'),
                'Default' => ' '
            ),
                'Plugins.RelevantTitle.MinWords' => array(
                'Control' => 'textbox',
                'Description' =>  '<br'.wrap(
                    t('Minimum number of <i>relevant</i> words in the discussion title (See "Relevance Heuristics" on the right):'),
                    'span class="FieldDescription"'
                ),
                'LabelCode' => wrap(t(' '), 'span class="FieldDescription"'),
                'Options' => array('MultiLine' => false, 'class' => 'TextNarrowInputBox'),
                'Default' => '1'
            ),
                'Plugins.RelevantTitle.BatchStart' => array(
                'Control' => 'textbox',
                'Description' => wrap(
                    t('The DiscussionID number to start reporting from. Leave blank to start from the beginning:'),
                    'span class="FieldDescription"'
                ),
                'LabelCode' =>  wrap(t('Conformance Report Setting'), 'span class="SettingHead"').
                                wrap(
                                    t('Run report to check whether existing discussions conform to the current plugin settings.'),
                                    'span class="FieldDescriptionLine2"'
                                ),
                'Options' => array('MultiLine' => false, 'class' => 'TextNarrowInputBox'),
                'Default' => ' '
            ),
                'Plugins.RelevantTitle.BatchSize' => array(
                'Control' => 'textbox',
                'Description' =>  wrap(t('Batch Size: Maximum number of records to scan in a single report:'), 'span class="FieldDescription"'),
                'LabelCode' =>    wrap(t(''), 'span class="FieldDescription"'),
                'Options' => array('MultiLine' => false, 'class' => 'TextNarrowInputBox'),
                'Default' => '500'
            ),
        ));
        //
        if ($Sender->Form->authenticatedPostBack()) {
            $IncompleteSetup = c('Plugins.RelevantTitle.IncompleteSetup', false);
            //
            $FormValues = $Sender->Form->formValues();
            //
            $Enforce = getvalue('Plugins.RelevantTitle.Enforce', $FormValues);
            $EnforceOnFeeds = getvalue('Plugins.RelevantTitle.EnforceOnFeeds', $FormValues);
            $Categories = getvalue('Plugins.RelevantTitle.Categories', $FormValues);
            $MinWords = trim(getvalue('Plugins.RelevantTitle.MinWords', $FormValues));
            $MaxWords = trim(getvalue('Plugins.RelevantTitle.MaxWords', $FormValues));
            $BatchStart = trim(getvalue('Plugins.RelevantTitle.BatchStart', $FormValues));
            $BatchSize = trim(getvalue('Plugins.RelevantTitle.BatchSize', $FormValues));
            //
            $HasErrors = false;
            $this->SaveconfigValue($Sender, $Enforce, 'Plugins.RelevantTitle.Enforce');
            $this->SaveconfigValue($Sender, $EnforceOnFeeds, 'Plugins.RelevantTitle.EnforceOnFeeds');
            $this->SaveconfigValue($Sender, $Categories, 'Plugins.RelevantTitle.Categories');
            //
            if ($this->CheckField(
                $Sender,
                false,
                $MaxWords,
                array('Integer' => ' ','Min' => 5),
                'The maximum number of words in discussion title ',
                'Plugins.RelevantTitle.MaxWords'
            )
            ) {
                $HasErrors = true;
                $Validation->addValidationResult('Plugins.RelevantTitle.MaxWords', t('Error'));
            } else {
                $this->SaveconfigValue($Sender, $MaxWords, 'Plugins.RelevantTitle.MaxWords');
            }
            if ($this->CheckField(
                $Sender,
                false,
                $MinWords,
                array('Required' => 'Integer', 'Integer' => ' ','Min' => '1','Max' => 5),
                'Minimum number of <i>relevant</i> words in discussion title ',
                'Plugins.RelevantTitle.MinWords'
            )
            ) {
                $HasErrors = true;
            } else {
                $this->SaveconfigValue($Sender, $MinWords, 'Plugins.RelevantTitle.MinWords');
            }
            if (trim($MaxWords) != '' & ($MaxWords<= $MinWords)) {
                $HasErrors = true;
                $ErrorMsg = ' The maximum number of words must be larger than the minimum number of words... ';
                $AddError = $Sender->Form->addError($ErrorMsg, 'Plugins.RelevantTitle.MaxWords');
            }
            if ($this->CheckField(
                $Sender,
                false,
                $BatchStart,
                array('Integer' => ' ','Min' => '0'),
                'The DiscussionID number from which to start reporting ',
                'Plugins.RelevantTitle.BatchStart'
            )
            ) {
                $HasErrors = true;
            } else {
                $this->SaveconfigValue($Sender, $BatchStart, 'Plugins.RelevantTitle.BatchStart');
            }
            if ($this->CheckField(
                $Sender,
                false,
                $BatchSize,
                array('Required' => 'Integer', 'Integer' => ' ','Min' => '1'),
                'The number of records to process in a report (batch size) ',
                'Plugins.RelevantTitle.BatchSize'
            )
            ) {
                $HasErrors = true;
            } else {
                $this->SaveconfigValue($Sender, $BatchSize, 'Plugins.RelevantTitle.BatchSize');
            }
            //
            if ($HasErrors) {
                //  Handle errors
                SaveToConfig('Plugins.RelevantTitle.IncompleteSetup', true);
                $SetupTitle = $SetupTitle . '<br>'. wrap('Setting is incomplete-verify and save your settings', 'span class="SettingError"');
                $Sender->Form->showErrors();
            } else {
                //  No Errors
                SaveToConfig('Plugins.RelevantTitle.IncompleteSetup', false);
                $this->SaveconfigValue($Sender, $Enforce, 'Plugins.RelevantTitle.Enforce');
                $this->SaveconfigValue($Sender, $EnforceOnFeeds, 'Plugins.RelevantTitle.EnforceOnFeeds');
                //
                $SaveMsg = t('Your settings were saved');
                Gdn::controller()->informMessage($SaveMsg);
            }
            //
        } else {    // Not postback
            //
            $Sender->Form->setValue('Plugins.RelevantTitle.Enforce', c('Plugins.RelevantTitle.Enforce', []));
            $Sender->Form->setValue('Plugins.RelevantTitle.EnforceOnFeeds', c('Plugins.RelevantTitle.EnforceOnFeeds', []));
            $Sender->Form->setValue('Plugins.RelevantTitle.Categories', c('Plugins.RelevantTitle.Categories', []));
            $Sender->Form->setValue('Plugins.RelevantTitle.MinWords', c('Plugins.RelevantTitle.MinWords', []));
            $Sender->Form->setValue('Plugins.RelevantTitle.MaxWords', c('Plugins.RelevantTitle.MaxWords', []));
            $Sender->Form->setValue('Plugins.RelevantTitle.BatchStart', c('Plugins.RelevantTitle.BatchStart', []));
            $Sender->Form->setValue('Plugins.RelevantTitle.BatchSize', c('Plugins.RelevantTitle.BatchSize', []));
            if (c('Plugins.RelevantTitle.IncompleteSetup', false)) {
                $SetupTitle = $SetupTitle . '<br>'. wrap('Setting is incomplete-verify and save your settings', 'span class="SettingError"');
            }
        }
        //
        $Sender->SetData('Title', $SetupTitle);
        $ConfigurationModule->RenderAll();
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
* Add Settings Menu.
*
* @param Standard $Sender Standard
*
*  @return boolean n/a
*/
    public function base_getAppSettingsMenuItems_handler($Sender) {
        $Menu = &$Sender->EventArguments['SideMenu'];
        $Menu->AddItem('Forum', t('Forum'));
        $Menu->addLink('Forum', t('Relevant Titles'), 'settings/RelevantTitle', 'Garden.Settings.Manage');
    }
/**
* Plugin Dispatcher.
*
* @param Standard $Sender Standard
* @param Standard $Args   Standard
*
*  @return boolean n/a
*/
    public function plugincontroller_relevanttitle_create($Sender, $Args) {
        $Debug = false;
        //$this->DebugData('', '', $Debug, true);  //Trace Function calling
        $Request = trim($Sender->RequestArgs[0]);
        $Sender->addCssFile('pluginsetup.css', 'plugins/RelevantTitle');
        if ($Request == "RelevantTitleReport") {
            $Sender->permission('Garden.Settings.Manage');
            $Sender->Form = new Gdn_Form();
            $Sender->render($this->getView('RelevantTitleReport.php'));
            return;
        }
        if ($Request == "RawRelevantTitleReport") { //Run the report outside the Vanilla Frame
            $Sender->permission('Garden.Settings.Manage');
            $this->Controller_RelevantTitleReport($Sender, $Args);
            return;
        }
        $Sender->addSideMenu('dashboard/settings/plugins');
        $this->Settingscontroller_RelevantTitle_create($Sender, $Args);
        $this->Dispatch($Sender, $Request);
    }
/**
 * Plugin setup.
 *
 *  @return  n/a
 */
    public function setup() {
        $this->InitializeConfig();
    }
/**
 * Plugin Initialization function.
 *
 *  @return  n/a
 */
    private function initializeconfig() {
        //Set default config options
        touchConfig(array(
                'Plugins.RelevantTitle.IncompleteSetup' => false,
                'Plugins.RelevantTitle.Enforce' => false,
                'Plugins.RelevantTitle.EnforceOnFeeds' => false,
                'Plugins.RelevantTitle.Categories' => ' ',
                'Plugins.RelevantTitle.Maxrowupdates' => 10,
                'Plugins.RelevantTitle.MinWords' => 1,
                'Plugins.RelevantTitle.MaxWords' => ' ',
                'Plugins.RelevantTitle.BatchStart' => 1,
                'Plugins.RelevantTitle.BatchSize' => 100
            ));
    }
/**
* Field valdation.
*
* @param object $Sender     standard
* @param string $Errorstate Previous state of error (turned on but not off to allow cummulative error checking)
* @param string $Field      Field to check
* @param array  $Checks     type of validations to perform
* @param string $Title      Error message title
* @param string $Fieldname  External field name for message construction
* @param string $Style      Error message HTML style
* @param bool   $Debug      debug request
*
*  @return bool - error state
*/
    private function checkfield(
        $Sender,
        $Errorstate,
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
                            $Errormsg='must be an integer - "'.$Field.'" is invalid';
                        } elseif ($Value == 'Numeric' && !is_numeric($Field)) {
                            $Errormsg='must be numeric - "'.$Field.'" is invalid';
                        } elseif ($Value == 'Title' && preg_match("/[^A-Za-z,.\s]/", $Field)) {
                            $Errormsg='must be valid words - "'.$Field.'" is invalid';
                        } elseif ($Value == 'Alpha' && preg_match("/[0-9]+/", $Field)) {
                            $Errormsg='must be alphabetic - "'.$Field.'" is invalid';
                        }
                    }
                } elseif (trim($Field) == '') {  //Not required but empty field?
                    return $Errorstate;
                } elseif (($Test == 'Integer' | $Test == 'Min' | $Test == 'Max') && !ctype_digit($Field)) {
                    $Errormsg='should be an integer - "'.$Field.'" is invalid';
                } elseif (($Test == 'Numeric' | $Test == 'Min' | $Test == 'Max') && !is_numeric($Field)) {
                    $Errormsg='should be numeric - "'.$Field.'" is invalid';
                } elseif ($Test == 'Title' && preg_match("/[^A-Za-z,.\s]/", $Field)) {
                    $Errormsg='should be valid words - "'.$Field.'" is invalid';
                } elseif ($Test == 'Alpha' && preg_match("/[0-9]+/", $Field)) {
                    $Errormsg='should be alphabetic - "'.$Field.'" is invalid';
                } elseif ($Test == 'Min') {
                    if ($Field < $Value) {
                        $Errormsg='should not be less than '.$Value.' - "'.$Field.'" is invalid';
                    }
                } elseif ($Test == 'Max') {
                    if ($Field > $Value) {
                        $Errormsg='should not be greater than '.$Value.'  - "'.$Field.'" is invalid';
                    }
                }
            }
        }
        //echo '<br>'.__line__.$Errormsg;
        if ($Errormsg != '') {
            $Errorstate = true;
            $Errormsg = wrap(t($Title).' '.t($Errormsg), $Style);
            if ($Fieldname != '') {
                $AddError = $Sender->Form->addError($Errormsg, $Fieldname);
            }
        }
        return $Errorstate;
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

//Include the PorterStemmer stemmer
if (!class_exists('PorterStemmer', false)) {
    require_once('PorterStemmer.php');
}
