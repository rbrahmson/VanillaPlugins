<?php if (!defined('APPLICATION')) exit();
//  Module to add related discussions to the side panel.
class DiscussionTopicModule extends Gdn_Module {

 protected $TopicString;

/////////////////////////////////////
  public function toString ($Sender,$Args) {
	global $TopicString ;
	//$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].debug_backtrace()[0]['line'].' ---> '. 	debug_backtrace()[0]['function'];
	//return $Msg;
	return $TopicString;
  }
/////////////////////////////////////
  public function Refresh($DiscussionID, $Limit = 10, $Debug = false) {
	global $TopicString ;
	//
	$AlsoSql = clone Gdn::sql();	//Don't interfere with any other sql process
	$AlsoSql->Reset();				//Clean slate
	$Discussionlist = $AlsoSql		//Get expanded tag info for this discussion
		->select('d.DiscussionID')
		->from('Discussion d')
		->where('d.DiscussionID', $DiscussionID)
		->get();
	//
	$this->GetAlso($DiscussionID, $Limit, $Debug);
	return $TopicString;
  }
/////////////////////////////////////
  public function GetAlso($DiscussionID, $Limit = 10, $Debug = false) {
	global $TopicString ;
	//This function will return the discussions that have the same "Topic" value as the current discussion
	//
	if ($Debug) {
			$Msg = __FUNCTION__.' '.__LINE__.' Called by: ' . debug_backtrace()[1]['function'].debug_backtrace()[0]['line'].' ---> '. debug_backtrace()[0]['function'];
			echo Wrap($Msg,'br');
	}
	//
	$TopicString = '';
	$DiscussionModel = new DiscussionModel();
	$Discussion = $DiscussionModel->getID($DiscussionID);
	if (!$Discussion) {
		$TopicString = 'no discussion';
		return;
	}
	//First get the Topic field for the current discussion
	//
	if ($Debug) {
		$this->Showdata($DiscussionID,__LINE__.'---DiscussionID---','',0,' ',true);
		$this->Showdata($Discussion->Topic,__LINE__.'---Topic---','',0,' ',true);
	}	
	if ($Discussion->Topic == '') {
		$TopicString = wrap('','span id=TitleBox');
		return;
	}
	//
	// Get the list of categories this plugin is enabled to work on
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
		if ($Catnums != "") {  //Limited category list?
			if (in_array($CategoryID, $Catnums)) {	//In the list?
				$Categorycount = $Categorycount + 1;
				$Categorylist[$Categorycount] = $CategoryID;
			}
		} else {
			$Categorycount = $Categorycount + 1;
			$Categorylist[$Categorycount] = $CategoryID;
		}
	}
	if ($Debug) $this->Showdata($Categorycount,__LINE__.'---Categorycount---','',0,' ',true);
	if ($Categorycount == 0) {
		$TopicString = wrap('','span id=TitleBox');
		return;
	}
	if ($Debug) $this->Showdata($Categorylist,__LINE__.'---Categorylist---','',0,' ',true);
	//
	
	$Topic = $Discussion->Topic;
	$TopicTitle = str_replace(array('\'', '"'), '', $Topic); 
	if ($Debug) $this->Showdata($Topic,__LINE__.'---Topic---','',0,' ',true); 
	$Uselimit = $Limit + 1;
	$AlsoSql = clone Gdn::sql();	//Don't interfere with any other sql process
	$AlsoSql->Reset();				//Clean slate
	$Sqlfields = 'd.DiscussionID,d.Name,d.CategoryID,d.Topic,d.TopicAnswer';
	$Discussionlist = $AlsoSql		//Get expanded tag info for this discussion
		->select($Sqlfields)
		->from('Discussion d')
		->where('d.Topic', $Topic)
		->wherein('d.CategoryID', $Categorylist)
		->where('d.DiscussionID <>', $DiscussionID)
		->orderby('d.TopicAnswer','DESC')
		->orderby('d.DiscussionID','DESC')
		->limit($Uselimit)
		->get();
	//
	//
	if ($Debug) $this->Showdata($Discussionlist,__LINE__.'---$Discussionlist---','',0,' ',true);
	//
	$Rowcount = count($Discussionlist);
	if ($Debug) echo '<br>'.__LINE__.' Rowcount:'.$Rowcount;
	if ($Debug) $this->Showdata($Discussionlist,__LINE__.'---Discussionlist---','',0,' ',true);
	$Panelhead = c('Plugins.DiscussionTopic.Paneltitle',t('Related Topics'));
	SaveToConfig('Plugins.DiscussionTopic.Paneltitle',$Panelhead);
	if ($Rowcount == 0) {
		$TopicString = wrap('','span id=TitleBox');
		return;
	}
	$More = '';
	if ($Rowcount >  $Limit) {
		$More = wrap(t('There are more').'...','li class=HashTagsMore Title="'.t('There are more discussions with the same topic').'"');
	}
	//
	$TopAnswerMode = c('Plugins.DiscussionTopic.TopAnswerMode',false);
	$Listcount = 0;
	foreach($Discussionlist as $Entry){
		if ($Debug) $this->Showdata($Entry,__LINE__.'---Entry---','',0,' ',true);
		$DiscussionID = $Entry->DiscussionID;	
		$Discussion = $DiscussionModel->getID($DiscussionID);
		$CategoryID = $Discussion->CategoryID;
		if (in_array($CategoryID,$Categorylist)) {	//Support Vanilla permission model -verify user can see discussions in the category
			$Title = $Discussion->Name;
			$EntryTitle = str_replace(array('\'', '"'), '', $Discussion->Name); 
			$TopicAnswer = $Discussion->TopicAnswer;
			$Emphsize = ' ';
			if ($TopAnswerMode) {
				$Emphasize = '○';
				if ($TopicAnswer) {
					$EntryTitle = t('Top Topic').':'.$EntryTitle;
					$Emphasize = '◉';
				}
			}
			//if ($Debug) echo '<br>'.__LINE__.' ConversationString:'.$TopicString; 
			$Anchor = wrap(wrap($Emphasize,'span class=Emphasize id=Emphasize'.$DiscussionID).
				Anchor(SliceString($Discussion->Name,40),'/discussion/'.$DiscussionID.'/?Key=('.$Discussion->Topic.')','RelatedItemLink '),'li class=RelatedDiscussions Title="'.$EntryTitle.'"');
			$TopicString =  ' ' . $TopicString . ' ' . $Anchor;
			$Listcount = $Listcount + 1;
		//} else {
		//	echo '<br>'.__LINE__.'Not allowed- CategoryID:'.$CategoryID.' DiscussionID:'.$DiscussionID; 
		}
	}
	if (!$Listcount) {
		$TopicString = wrap('','span id=TitleBox');
		return;
	}
	$TopicString =  $TopicString . ' ' . $More; 
	$Panelhead = anchor($Panelhead,'/plugin/DiscussionTopic/DiscussionTopicSearch?s='.$Topic,$Panelhead);
	$TopicString =	wrap(panelHeading($Panelhead).
							wrap($TopicString,'ul class="PanelInfo PanelCategories" title="'.t('click to view discussion').'"'),
								'DIV class="Box BoxCategories"  id=TitleBox title="'.t('Discussions with this title:').$TopicTitle.'"');
	//
	return false;		
  }
  /////////////////////////////////////////
  public function assetTarget() {
	return 'Panel';
  }
  ///////////////////////////////////////
	// Display data for debugging
	public function Showdata($Data, $Message, $Find, $Nest=0, $BR='<br>', $Echo = true) {
		//var_dump($Data);
		$Line = "<br>".str_repeat(".",$Nest*4)."<B>(".($Nest).") ".$Message."</B>";
		if ($Echo) echo $Line;
		else Gdn::controller()->informMessage($Line);
		
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
	///////////////////////////////////////////////
}
