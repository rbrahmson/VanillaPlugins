<?php if (!defined('APPLICATION')) exit();
//  Module to add inbox to the side panel.
//

class InboxPanelModule extends Gdn_Module {

  protected $ConversationString;
	
  public function assetTarget () {
	return 'Panel';               //Ensure Inbox Panel goes into the side panel
  }

//
// Leverage the data model from the conversation object to build the data structure
// which we will parse and convert into displayable data in the toString function.
//
  public function getinboxmessages () {
	global $ConversationString;                                         //Shared Data structure
	$NumToGet = C('InboxPanel.InspectCount', 15);						//Number of conversation to retrieve
	//$this->RBDebug(__LINE__,"NumToGet:",$NumToGet,TRUE); 				//  Debugging
	$Model = new ConversationModel();                                   // New object
	$ConversationString = $Model->get2(Gdn::Session()->UserID,0,$NumToGet,array()); // Get up to $NumToGet conversations
	Gdn::userModel()->joinUsers($ConversationString, array('LastInsertUserID'));    //Complete the data structure 
  }
// get current count of inbox messsages									//An efficient way to get the alert count
  public function GetAletCount ($Sedner) {
	$CountInbox = Gdn::session()->User->CountUnreadConversations;
	//echo wrap("GetAletCount. CountInbox =".$CountInbox);
	return $CountInbox;
  }
//
// The toString function will be used to traverse the data structure, pick specific 
// elements and construct suitable HTML to be displayed in the panel.
// By Vanilla design the toString renders the data to be displayed.
//
  public function toString () {
	global $ConversationString;
	$this->getinboxmessages();	                            //Fetch the conversation data
	if (C('InboxPanel.Bubble', false) ==  true) {			//Setting requests to show new messages bubble
		$BubbleID = 'InboxAlert';							//The ID updated by the JS
	} else {
		$BubbleID = 'xInboxAlert';							//This ID is NOT updated by the JS
	}
	$PanelTitle = wrap(T('Private Messages'),'center').
					wrap(
					wrap('✉','button',
					array('Title' => t('Click to refresh'),
					'ID' => "InboxRefresh",
					'class' => 'EmailPopButton')).
					wrap(Gdn::session()->User->CountUnreadConversations,'span',
					array('ID' => $BubbleID,
					'Title' => t('Click to refresh'),
					'class' => 'EmailPopBubble')),'div',array('class' => 'EmailBubbleWrap')); 
	$ClickToRespond = T('Click to respond');
	$ClickToPreview = T('Click to preview');
	$LinkToInbox =  T('All...');
	$NewMessage = T('New...');
	$NoMessagesYet =sprintf(T('You do not have any %s.'), T('private messages'));
	$NoNewMessages =sprintf(T('You do not have any %s.'), T('new private messages'));
	$MyUserTerm = T('You');
	$MyUser = Gdn::Session()->UserID;                       //Current user 
	$MaxNumToShow = C('InboxPanel.Count', 0);				//Max Number of conversations to display
	//$this->RBDebug(__LINE__,"MaxNumToShow:",$MaxNumToShow,TRUE); //  Debugging
	if ($MaxNumToShow == 0) return;							//Require settings to be saved by admin
	///--------------------------
	//  Check whether to add "New Message" link as well
	$NewMessageLink = '';
	if (CheckPermission('Conversations.Conversations.Add')) {
	  $NewMessageLink = '<span class=NewMSG>'.Anchor($NewMessage, 'messages/add').'</span>';
	  $NewMessageLink = wrap(anchor($NewMessage, 'messages/add'),'span',array('class' => 'NewMSG'));
	}
	//  Construct the side panel heading
	$PanelHeading = '<div class="InboxTitle">'.$PanelTitle.'<div><br><span class="InboxAll"><a href="'.Url('/messages/inbox').'"> '.$LinkToInbox.'</a></span>'.$NewMessageLink;  
	
	$ShownCount = 0; // Number of shown entries
	if (count($ConversationString)){	   
		 foreach ($ConversationString as $Row){
			if ($MaxNumToShow > $ShownCount){							//Max Number of conversations to display
				if (($Row['CountNewMessages'] > 0) || !(C('InboxPanel.OnlyNew') == '1')) {
					//Construct the subject (The conversants)
					$Subject = '';
					if ($Row['Subject']) {
						$Subject = Gdn_Format::Text($Row['Subject']);
					} else {
						$Subject = '';
						foreach ($Row['Participants'] as $User) {
							$Subject = ConcatSep(', ', $Subject, FormatUsername($User, $MyUserTerm));
						}
					}
					// Construct the message Excerpt as well as the tooltip  (if it is longer than the excerpt).
					$Excerpt = SliceString(Gdn_Format::PlainText($Row['LastBody'], $Row['LastFormat']), 35);
					$CleanExcerpt = Wrap(nl2br(htmlspecialchars($Excerpt)));  //Avoid embedded HTML...
					$LongExcerpt = SliceString(Gdn_Format::PlainText($Row['LastBody'], $Row['LastFormat']), 180);
					if ($Excerpt == $LongExcerpt) {
					 $TitleExcerpt = "<P>";       //No tooltip for short messages
					} else{
					 $TitleExcerpt = '<P Title="'.nl2br(htmlspecialchars($LongExcerpt)).'">';
					}
					// Construct the Photo to show
					$LastPhoto = '';
					foreach ($Row['Participants'] as $User) {
					  if ($User['UserID'] == $Row['LastInsertUserID']) {
						$LastPhoto = $User['Photo'];  //get suffixof user photo
						$LastPhoto = Gdn_Upload::Url(ChangeBasename($LastPhoto, 'n%s'));   //Get full and altered url for user photo
						if (!isUrl($LastPhoto)) {
							$LastPhoto = Gdn_Upload::Url(ChangeBasename($LastPhoto, 'n%s'));
						} else {
							$LastPhoto = $LastPhoto;
						}
						if ($LastPhoto) break;
						} elseif (!$LastPhoto) {
							$LastPhoto= $User['Photo'];         //Still need to add code if user has no photo
						}
					}
					//     
					$MessageLink = "/messages/{$Row['ConversationID']}#latest";
					$LastPhotoHtml = '<div class="Author Photo"><a title="'.$ClickToRespond.'" href="'.Url($MessageLink).' " class="PhotoWrap"><img src="'.$LastPhoto.'" class="ProfilePhoto ProfilePhotoMedium"></a></div>'; 
					// Html for the conversation preview
					//$PreviewHtml = '<span class="MsgPreview"><a id="Private Message" class="Popup" title="'.$ClickToPreview.'" href="'.Url($MessageLink).'"> 🔎</a></span>';
					
					$PreviewHtml = Anchor(wrap('🔎','button',array('class' => 'MsgPreviewHide')),
									$MessageLink,array('Title' => $ClickToPreview,'class' => 'MsgPreview Popup'));
					
					if ($ShownCount == 0) {
						echo ('<div><ul class="PopListConversations" id="Boxinbox">');    //Split the side box headings, but only once
						echo ('<div class="BoxInboxPanel" ><h4>'.$PanelHeading.'</h4>');
						echo ('<ul class="InboxEntry" id="InboxPanel">'); 
					}
					$ShownCount = $ShownCount +1;  // Count the number of displayed messages
					//Flush the HTML onto the page 
					echo ('<li class="InboxItem" rel="');  Echo(Url($MessageLink)) ; echo('">');
					echo ($LastPhotoHtml);
					echo ($PreviewHtml);		//Preview popoup link
					echo ('<div class="ItemContent"><b class="Subject">');
					echo Anchor($Subject, "$MessageLink").'</b>';	 
					if ($Row['CountNewMessages'] > 0) {
					   echo ' <span class="HasNew"> '.Plural($Row['CountNewMessages'], '%s new', '%s new').'</span> ';
					}
					echo Wrap($TitleExcerpt.$CleanExcerpt.' ', 'div', array('class' => 'Excerpt'));
					echo ('<div class="Meta">');
					echo ' <span class="MItem">'.Plural($Row['CountMessages'], '%s message', '%s messages').'</span> ';
					echo ' <span class="MItem">'.Gdn_Format::Date($Row['LastDateInserted']).'</span> ';
					echo '</div></div></span></li>';
				   //=============================================
				}  //  end of new message test processing
			}  // End of test for max number of conversations to display
		}  //  end of Foreach message processing
	}	//  end of checking for  private conversations 
	//  Complete the HTML processing
	if ($ShownCount == 0) {  //Nothing displayed?
		if (!C('InboxPanel.HideIfNone')=='1'){
			echo ('<div><ul class="PopListConversations" id="Boxinbox">');    //Spit the side box headings, but only once
			echo ('<div class="BoxInboxPanel" ><h4>'.$PanelHeading.'</h4>');
			echo ('<ul class="InboxEntry" id="InboxPanel">'); 
			//echo ('<ul class="PopList Conversations">');
			if (C('InboxPanel.OnlyNew') == '1') $NoMessagesYet = $NoNewMessages;
			echo ('<div><li>'.$NoMessagesYet.'</li></div>');//</ul></div>');
		}
	}
	//else {
		echo ('</ul></div></div>');  //Close the open HTML tags
	//}
  }
/***********************************************************************/
	/**** Debugging Function (may be useful in other plugin development) ****/
   	public function RBDebug($Argline,$ArgVarname,$ArgVarValue,$Forcedebug) {
	  global $RBDebugActive;   
      global $RBDebugInitialized;  	 		
	  if ($RBDebugActive  || $Forcedebug){
	    if (!$RBDebugInitialized){
			$RBDebugInitialized = true;
			LogMessage(__FILE__,$Argline,'Object','Method',"**** Starting Plugin  Debug ****");
	   }
	   if (is_array($ArgVarValue)) {
			$ArgVarValue = '.._ARRAY_..'.implode(" ? ",$ArgVarValue);	
			LogMessage("Line:",$Argline,' ',' ',"RBDebug: $ArgVarname = $ArgVarValue");
	   }
	   if (substr($ArgVarname,0,1) == "-")
   	     LogMessage("Line:",$Argline,' ',' ',"RBDebug: $ArgVarname --- $ArgVarValue");
	   else 
		 LogMessage("Line:",$Argline,' ',' ',"RBDebug: $ArgVarname = $ArgVarValue");
	  }		
	}
    /***********************************************************************/
}
