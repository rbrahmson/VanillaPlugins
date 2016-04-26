<?php if (!defined('APPLICATION')) exit();
//  Module to add Top Bookmarks widget to the side panel
//  TODO - this is an empty skeleton.
class TopBookmarksModule extends Gdn_Module {

  public function assetTarget () {
	return 'Panel';               
  }
  public function getData($Limit = 10) {
		//TODO - GET DATA FOR SIDEPAEL WIDGET
		$Backward = c('Plugins.TopBookmarks.Backward',0);
		$Backdate = date_create();
		if ($Backward != 0) {
			$Interval = $Backward.' days';
			date_sub($Backdate,date_interval_create_from_date_string($Interval));
			$Backstring = date_format($Backdate,'Y-m-d');
			$CssTitle = t('Number of bookmarks for this discussion in the last ').$Backward.' '.t('Days');
		} else {
			$Backstring = '0';
			$CssTitle = t('Number of bookmarks for this discussion');
		}
	
    }
  public function ToString()  {
	if (!c('Plugins.TopBookmarks.AddToMenu')) return;
	
	$UrlTitle = c('Plugins.TopBookmarks.MenuName','Highly-Bookmarked');
	$url = '/discussions/TopBookmarks';

	$String = $String.'<span><span class="SidePanelLinksItem" rel=" ';
	$String = $String.Url($Url).'">' ; 
	$String = $String.Anchor($UrlTitle, '$Url').'<br></span></span>';
	//echo $String;
	return $String;
	  
	//if ($this->_UserData->numRows() == 0) {
    //        return '';
    //}

	
	echo '<div class="Box">';
	echo panelHeading(c('Plugins.TopBookmarks.MenuName',t('Top Bookmarks')));
	echo '<ul class="PanelInfo">';
	return 'testing';
	/*foreach ($this->_UserData->Result() as $Row){
		var_dump($Row);
	}
	*/
	echo '</ul>';
	echo '</div>';
   return $String;
  }
}