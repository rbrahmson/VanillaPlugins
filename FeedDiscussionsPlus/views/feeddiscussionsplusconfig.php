<?php   if (!defined('APPLICATION')) exit();
    if(version_compare(APPLICATION_VERSION, '2.5', '<=')) {
      echo '<script type="text/javascript">
      $("#Popup div.Body").css("max-height", ($(window).height() /100)*90);
      $("#Popup div.Body").css("max-width", "90%");
      $("#Popup div.Body").css("height", "max-content");
      $("#Popup div.Body").css("width", "max-content");

      $("div.Popup input.Button").css("margin", "0");
      $("div.Popup .Content form").css("padding", "0px 1px 1px 0px");
      </script>';
      $Popustate = c('Plugins.FeedDiscussionsPlus.Popup',''); //Look at the saved popup state
    }  else {
        $Popustate = '';
    }
    //	
        if ($Popustate == 'Popup') {
            $Nextpopup = 'PopupWindow';
        } else {
            $Nextpopup = 'Popup';
        }
        $Processbutton = '<span title="Save and return to feed definition list" class="buttonitem  ">'.$this->Form->button("📥 Save", array('type' => 'submit', 'name' => 'Save', 'id' => 'gsave', 'class' => 'Button ffcolumn')).'</span>';
        $Returnbutton = '<span title="Return to feed definition list" class="buttonitem  ">'.$this->Form->button("↩ Return to list.", array('name' => 'Return', 'id' => 'gcancel', 'class' => 'Button ffcolumn')).'</span>';
        //$Returnbutton = '';
        $Cancelbutton = '<span style="margin: 0 0 0 20px;"><a class="Button buttonitem " href="'.
                        Url('plugin/feeddiscussionsplus/listfeeds//?'.__LINE__.'crash').
                        '" title="'.t('Return to the definitions list').'">☰ Cancel</a></span>';
        $Cancelbutton = '';
        $Readmebutton = '<a class="Button buttonitem ffcolumn'.$Nextpopup.
                '" target=_BLANK href="' .
                Url('/plugin/feeddiscussionsplus/Readme').
                '" title="' . t('You should read this before starting').
                '"><FFBLUE><b>❓</b></FFBLUE> Readme</a>';
        $Readmelink = '<span title ="Show Readme" class="buttonlink" style="text-decoration: unset;">Click '.
          anchor('<b>❓</b>Readme', Url('../plugin/feeddiscussionsplus/Readme'), 'SmallButton  '. $Nextpopup, ["target" => "_blank"]).'for more information.</span>';
        $Processtitle = 'Global Settings';
        //
        $Adduserbutton =   '<a class="Button buttonaside PopupWindow"  target=_BLANK href="' . Url('/dashboard/user/add').
        '" title="' . t('Add user').'"><FFBLUE><b>👥</b></FFBLUE> Add a User</a>';
        $Savedcron = c('Plugins.FeedDiscussionsPlus.Croncode', "secretcode");
        $Cronexample = url("plugin/feeddiscussionsplus/CheckFeeds/cron/".$Savedcron, true);
        $Saveduser = c('Plugins.FeedDiscussionsPlus.Feedusername', 'Feed');
        $Edituserurl =   '/profile/edit/'.$Saveduser;
        $User = Gdn::userModel()->getByUsername(trim($Saveduser));
        if (empty($User)) {
            $Edituserbutton = '';
        } else {
            $Userphoto = val('PhotoUrl', $User);
            if (empty($Userphoto)) {
                if (function_exists('UserPhotoDefaultUrl')) {
                    $Userphoto = userPhotoDefaultUrl($User, ['Size' => 25]);
                }
            }
            if ($Userphoto) {
                $Userphoto = '<span><img  style="width:25px;float:right" class="GMiniphoto" src="'.$Userphoto.'"></span>';
            }
            $Edituserbutton = '<a class="Button buttonaside PopupWindow"  target=_BLANK href="' .
              Url($Edituserurl). '" title="' .
              t('Edit previously saved author name').'">'.
              $Userphoto.'Edit&nbspuser&nbsp"'.$Saveduser.'"&nbsp</a>';
        }
        $Userbuttons = '<span class="GAside">'.$Adduserbutton.$Edituserbutton."</span>";
		    echo $this->Form->Open(array(
         'action'  => Url('plugin/feeddiscussionsplus/global')
		      ));
		    echo '<div id=Popup><div id=FDP>';
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
        $Title = $Plugininfo["Name"];
        $Version = $Plugininfo["Version"];
        $IconUrl = $Plugininfo["IconUrl"];
        $Qmsg = FeedDiscussionsPlusPlugin::getmsg('', 'GETVIEW'.__FUNCTION__.__LINE__);
        if ($Qmsg) {
            $Titlemsg = '<br><div class=ffqmsg>' . $Qmsg . '</div>';
        } else {
            $Titlemsg = '';
        }
		    $Sourcetitle = 'Source:'.pathinfo(__FILE__)["basename"];
		    echo '<h1 id=Gh1 title="'.$Sourcetitle.'"> <span class=selflogo> </span> '. $Title . ' (Version ' . $Version.')  -  ' . $Processtitle.'   '.$Titlemsg.'</h1>';
        //
		    echo $this->Form->Errors();
		    echo '<div class="Globaloptions" >';
        //
		    echo '<ul><li>';
        //
        $Buttonbar = '<ffhead><div class="ffspread">'.
                     $Processbutton.' &nbsp&nbsp&nbsp  '.
                     $Cancelbutton.' &nbsp&nbsp&nbsp  '.
                     $Returnbutton.' &nbsp&nbsp&nbsp  '.
                     $Readmebutton.'</div></ffhead>';
        //
        echo $Buttonbar;
        //
        echo '<h4><FFBIG><b>💻</b></FFBIG>Presentation Options</h4>';
        echo '<ffinputs>';
        //
        echo '<FFlineglobal>';
        echo $this->Form->Label('<FFlabelglobal>Import save userid:</FFlabelglobal>', 'Feedusername');
        $RSSthumbs = '<span title ="Show RSS icons" class="buttonlink" style="text-decoration: underline;">'.anchor(t('RSS thumbnail'), 'https://www.google.com/search?q=rss+icon&safe=active&tbs=isz:i,sur:fmc&tbm=isch&source=lnt&sa=X&ved=0ahUKEwjwvrmY-MXXAhXGxYMKHXr1ASUQpwUIHg&biw=1600&bih=769&dpr=1', 'SmallButton PopupWindow ').'</span>';
        echo $this->Form->TextBox('Feedusername', array('class' => 'InputBox GlobalInput'))."The user acting as the saved discussion author.<FFtext>".$Userbuttons." If not provided the <b>system</b> userid will be used. <b>Hint</b>: Use an ".$RSSthumbs." image for that userid thumbnail.</FFtext>";
        echo '</FFlineglobal>';
        //
        echo '<FFlineglobal>'.$this->Form->CheckBox('Returntolist', 'Return to list after save', array('value' => '1', 'class' => 'ffcheckboxglobal'))."<ffchecktext> After saving a feed definition either return to list of feeds or stay on the feed definition screen</ffchecktext></FFlineglobal>";
        //
        echo '<h4> <b>⏬</b> Global Import Options</h4>';
        //
		    echo '<FFlineglobal>'.$this->Form->CheckBox('Userinitiated', "Users initiated import", array('value' => '1', 'class' => 'ffcheckboxglobal')).'<ffchecktext> If set, when users open a discussion for viewing the background import process starts. '.$Readmelink.'</ffchecktext></FFlineglobal>';
		    //
		    echo '<FFlineglobal>'.$this->Form->CheckBox('Detailedreport', 'Detailed import Report', array('value' => '1', 'class' => 'ffcheckboxglobal'))."<ffchecktext> Provide details on import reports (applicable to admin initiated imports)</ffchecktext></FFlineglobal>";
		    //
		    echo '<FFlineglobal>';
		    echo $this->Form->Label('<FFlabelglobal>Feeds per import:</FFlabelglobal>', 'Globalmaximport');
        echo $this->Form->TextBox('Globalmaximport', array('class' => 'InputBox'))."The number of feeds to process per per user-initiated import.<FFtext> Leave blank or zero for no limit. This is a performance option for enabled Users initiated imports. ".$Readmelink."</FFtext>";
		    echo '</FFlineglobal>';
        //
		    echo '<FFlineglobal>';
		    echo $this->Form->Label('<FFlabelglobal>Secret cron token:</FFlabelglobal>', 'Croncode');
        echo $this->Form->TextBox('Croncode', array('class' => 'InputBox'))."<FFtext> a character token required as parameter to the feed import cron. ".$Readmelink."</FFtext>";
        echo '<div style="Float:right">&nbsp&nbsp&nbsp&nbsp&nbsp&nbsp<b>cron example:</b>'.$Cronexample.'</div>';
        echo '</FFlineglobal>';
    //
    echo '</ffinputs>';
    echo '</ul>';
    echo '<div>';
    echo $Buttonbar;
    echo '</div></div></div></div>';
    $this->Form->close;
?>