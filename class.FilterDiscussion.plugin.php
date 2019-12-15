<?php if(!defined('APPLICATION')) exit();
$PluginInfo['FilterDiscussion'] = array(
    'Name' => 'FilterDiscussion',
	'Description' => "FilterDiscussion - A plugin that dynamically creates custom filtered / sorted discussion lists.",
    'Version' => '1.8.5',
    'RequiredApplications' => array('Vanilla' => '2.6'),       		    /*This is what I tested it on...*/
    'RequiredTheme' => false,
	'SettingsPermission' => 'Garden.Settings.Manage',	
    'SettingsUrl' => '/settings/filterdiscussion',
	'RegisterPermissions' => array('Plugins.FilterDiscussion.View'),  	/*Permission to filter on fields*/
	'Author' => 'Roger Brahmson',
	'github' => 'https://github.com/rbrahmson/VanillaPlugins/tree/master/FilterDiscussion',
    'MobileFriendly' => TRUE,
    'HasLocale' => TRUE,
    'License' => 'GPLV3'
);
/*
The plugin filters the discussion list based on the value of the fields in the discussions database. The filtered view is flexible as the filters are specified via URL parameters using flexible syntax. For example, filtering by the CategoryID field is equivalent to display discussions for a particular category. A combination of fields is allowed and the list of supported fields is specified in the administration dashboard. 

Combination field names can be specified to create refined filters.  If the Discussion table in the database has been extended with additional fields, then these can be used as well. For example, the PrefixDiscussion plugin adds a field called "Prefix", so it is possible to filter with the "Prefix=" parameter. Example: /discussions/filterdiscussion?Prefix=EQ:Video&InsertUserID=EQ:13&CategoryID=EQ:6

The title of the resulting filtered screen can also be specified via the &!msg= parameter. Some html tags can be specified (at your risk).
Example: /discussions/filterdiscussion?Prefix=EQ:Video&!msg=<span%20style="color:white;background-color:blue">Highlighted%20Videos</span>

Named filters can be globally saved via the plugin settings through the admin dashboard. Saved filters provide the ability to apply filters
without exposing to the end user the actual parameters being used.   
For example, assume a saved filter named "AlertedVideos" is defined as "&Prefix=EQ:Video&Alert=NN&!msg=Videos of interest"
To invoke that view the following url is needed "/discussions/filterdiscussion?!filter=AlertedVideos
(The above example assumed that both PrefixDiscussion and DicsussionAlert plugins are installed 
	-- they add the Prefix and Alert fields to the discussion table).


There are three types of use cases for this plugin:
1. For administrators  - to check on content without having to go to the SQL database
2. For developers - to link to filtered views from web pages (e.g. The userid field in a discussion can link to a filtered view that shows discussions by that userid) 
3. For administrators - to add menu options for specialized views 

Special permission must be set to allow users to use the plugin.  After enabling the plugin see "Roles & Permission" in the admin dashboard.

Note: You may have other plugins that use URL parameters and you will need to define them in the "ignore list" on 
the dashboard setting for FilterDiscussion plugin so that it will ignore them and won't throw an error for undefined parameter.   This ignore list has been added in Version 1.2.

Change log:
Version 1.2 - Added ignore list (ignored url parameters that may be used by other plugins/applications)
Version 1.3 - Added saved filters (to use a named parameter to invoke multiple filters while hiding the filters from the user)
Version 1.4 - Restructured internal functions, out of arrary index bug fix, increased number of defined filters
Version 1.5 - Added relative date filtering for discussion date columns
Version 1.6 - Support for Vanilla 2.6.   
            - Support for using {$userid} in the value field to refer to one's own userid number. Example: /discussions/filterdiscussion?InsertUserID=EQ:{$userid}
            - Support for using {$category} in the column name and then the caegory name in the value to mean "EQ:caegory-number-corresponding-to-the-name".
              example: /discussions/filterdiscussion?InsertUserID=EQ:{$userid}&{$category}=General
            - Support for including {$username} and {$category} in the !msg title (if this is the last parameter)
              example: /discussions/filterdiscussion?InsertUserID=EQ:{$userid}&{$category}General&!msg={$category}
Version 1.8.1 - Support for Vanilla 2.8.   
            - Support for sorting fields (thanks to user donshakespeare who requested this feature). 
              Example:   /discussions/filterdiscussion?InsertUserID=EQ:{$userid}:d
            - support for filtering by explicit user names (rather thantheir internal numbers)
              Example:   /discussions/filterdiscussion?{$insertusername}=EQ:Joe%20Doe:a
              Example:   /discussions/filterdiscussion?{$updateusername}=EQ:Joe%20Doe:d
            - Support for relative dates (number of dates from today in the value field entered as +nn or -nn)
              Example:  /discussions/FilterDiscussion/?DateInserted=EQ:-1      //Display yesterday's discussions.
Version 1.8.3 - Support for date fields without time (just "Date").   
Version 1.8.4 - retracted: filtering on unread discussions (use operator !R).   
Version 1.8.5 - (1) Passing symbolic paameters to saved filters.
                    Example: Assume save named "RecentinCategory" defined as: DateInserted=EQ:-10&{$category}=EQ:{1}
                    Then invoking a url like "/discussions/filterdiscussion?!filter=RecentinCategory&{1}=books"
                    will be equivalent to  "/discussions/filterdiscussion?DateInserted=EQ:-10&{$category}=EQ:books"
                    The {$category} is explaied above (indicates category name filtering).  -10 represents ten days backward.
                    This is useful when invoking filters from menus or other urls.
                (2) Sorting without filtering: New operand: "sort". Syntax: fieldname=sort:order.  
                    Example: "/discussions/filterdiscussion?DiscussionID=sort:a" to sort discussions in reverse order
                    it's a clearer way of sorting than using "/discussions/filterdiscussion?InsertUserID=EQ::d" (omitting the comparison and specifying sort order)
                (3) Admin only parameter: !info - will display database info on the title line (ignored if user is not admin)
                    Sample output: "PHP Version:7.4.b2 Database Version:6.8.1b-cll-lve Database Name:abcd Database HostName:abcd Server Software: Apache"
                (4) Ignore key case (e.g. allow use of discussionId instead of DiscussionID).  This is more than a trivial change - it allows the use of multiple
                    filter parameters on the same field.  Example: "/discussions/filterdiscussion?DiscussionID=GT:2000&DISCUSSIONID=LT:2020
                    It will shows discussions with IDs between 2000 and 2020.  Note that if both DiscussionID parameters would have been specified in the same case
                    only one (usually the last) parameter would be passed to the server preventing the use of multiple conditions on the same field.
                (5) New "-a!" parameter that rmoves announcements from the resulting data. Example: "/discussions/filterdiscussion?DiscussionID=GT:2000&!a-"
                    (To show just the announcement use the Announce=EQ:1 parameter as in "/discussions/filterdiscussion?Announce=EQ:1"
                    
                    
No warranty whatsoever is implied by releasing this plugin to the Vanilla community.
*/

class FilterDiscussionPlugin extends Gdn_Plugin {

  // Indicates whether or not we are in the Filtered view
  private $CustomView = false;
  ///////////////////////////////////////////////
    public function plugincontroller_filterdiscussion_create($Sender, $Args = '') {
        $this->settingsController_FilterDiscussion_Create($Sender, $Args);
    }
  ///////////////////////////////////////////////
  // Pagination support
  public function DiscussionsController_FilterDiscussion_Create($Sender, $Args = array()) {
	$Page = '{Page}';
	if (!CheckPermission('Plugins.FilterDiscussion.View')) {	
		$this->SevereMessage(Gdn::Translate('Not Authorized'));
		Gdn::Controller()->Title(Gdn::Translate('Recent Discussions'));
		return;
	}
	$this->CustomView = TRUE;
    $Sender->View = 'Index';
	$Parameters = '';
	$i=0;
    $query = $this->getquery();
    $Parameters = implode("&", $query);
    $Sender->SetData('_PagerUrl', 'discussions/FilterDiscussion/'.$Page.'?'.$Parameters);
    $Sender->SetData('_PagerUrl', 'discussions/FilterDiscussion/'.$Page.'?!!');
    if (Gdn::request()->get('!msg')){
        Gdn::Controller()->Title(t(Gdn::request()->get('!msg')));
    }
    $Sender->Index(GetValue(0, $Args, 'p1'));
  }
  ///////////////////////////////////////////////
  // Set the count to the cache value. This will use a few more pages unless caching is enabled.
  public function DiscussionsController_Render_Before($Sender) {
    if($this->CustomView) {
       $Sender->SetData('CountDiscussions', Gdn::Cache()->Get('FilterDiscussion-Count'));
    }
  }
  ///////////////////////////////////////////////
  // Handle additional columns
	public function zzzDiscussionModel_AfterDiscussionSummaryQuery_Handler($Sender,$args) {
        $IsAdmin = Gdn::Session()->CheckPermission('Garden.Settings.Manage');
        if (!$IsAdmin) return;
        $parms = array_change_key_case($_GET);
        if (!isset($parms["!r"])) return;
        //decho ($Sender->EventArguments);
        //decho ($Sender->SQL);
        //$sql = $Sender->SQL->query($Sender->SQL);
        //decho (__LINE__);
        //decho ($sql);
        //Gdn::sql
        //$this->ShowSQL($Sender->SQL,__LINE__."Sender->SQL",'');
  }
  ///////////////////////////////////////////////
  // Handle annoncements 
	public function DiscussionModel_AfterAddColumns_Handler($Sender,$args) {
        $parms = array_change_key_case($_GET);
        if (isset($parms["!a-"])) {
            $Sender->removeAnnouncements($Sender->EventArguments['Data']);
            if (isset($parms["!msg"]) OR isset($parms["!filter"])) {
                return;
            }
            $Title = Gdn::Controller()->Title($Title) . " " .t("without announcements");
            Gdn::Controller()->Title($Title);
        } elseif (isset($parms["!a+"])) {                           //By default announcements are included so just add the title
            if (isset($parms["!msg"]) OR isset($parms["!filter"])) {
                return;
            }
            $Title = Gdn::Controller()->Title($Title) . " " .t("with announcements");
            Gdn::Controller()->Title($Title);
        }
  }
  ///////////////////////////////////////////////
  // Check the query for debugging
	private function ShowSQL($Sender,$args) {
        $IsAdmin = Gdn::Session()->CheckPermission('Garden.Settings.Manage');
        if (!$IsAdmin) return;
        //$this->Showdata($Sender->SQL,"Sender->SQL",''); 
        //$Where = val($Sender->SQL,"Where");
        //decho ($Sender->SQL);
        //$this->Showdata($Sender->SQL->Wheres,$args." Sender->SQL->Wheres'",''); 
        //decho ($args);
        //decho ($Sender->EventArguments);
        //die(0);
  }
  ///////////////////////////////////////////////
  // Main processing of the custom view
	public function DiscussionModel_BeforeGet_Handler($Sender,$args) {
		$Debug = false;
		if($this->CustomView != TRUE)  return;
        $query = $this->getquery();
		if (!CheckPermission('Plugins.FilterDiscussion.View')) {  //Validate that the user has permission to filtered views
			$Title = Gdn::Translate('Not allowed.  Unrecognized parameters:').implode("&", $query);       //If not, don't accept the parameters
			Gdn::Controller()->Title($Title);
			return;														// and return without any custom view
		}
        $IsAdmin = Gdn::Session()->CheckPermission('Garden.Settings.Manage');
        //
		/* As of Version 1.5 the list of allowable filter fields is optional.
		// Validate that passed column named are prelisted in the administrator configuration screen 
		if	(!c('Plugins.FilterDiscussion.Fieldnames')) {  				//Field names defined??
			$this->SevereMessage(Gdn::Translate('List of acceptable fields not defined. The admin needs to set them first..'));
			return;
		}
		*/
		$ValidFields = array();
		if (c('Plugins.FilterDiscussion.Fieldnames',' ') != '')		
			$ValidFields = explode(',',trim(c('Plugins.FilterDiscussion.Fieldnames',' ')));
		$Ignoreparms = explode(',',trim(c('Plugins.FilterDiscussion.Ignoreparms',' ')));
		//
		/*---------------------
		Search arguments are specified as &column=operand:value where:
			column - the column name (e.g. CategoryID)
			operand - the search type.  One of: 
				EQ - Equal, NE - Not Equal, NL - NULL, NN - Not NULL 	//Future development: LK - Like search, NK - not like search,

			Examples:  	&Prefix=NE:Help  					- Search for entries that do not have "Help" in the Prefix column
						&Prefix=NL,&Alert=NN,&InsertID=12	- Search for entries without a prefix (NULL), with an Alert (not NULL) created by userid 12
		----------------------*/
		$Title = '';
		$Titlemsg = '';
		$Urlparms = $query;
		//Allow other plugins pass the filters (which need to be in the same format as thosepassed through the url)
		$SelfPluginName = $this->PluginInfo['Index'];
		$this->EventArguments['PluginName'] = $SelfPluginName;
		$Filter = $query;
		$this->EventArguments['Filter'] = &$Filter;	//Let hook set the filter parmeters
		if ($Debug) echo '<BR>'.__LINE__.' query:'.var_dump($query);
		$Filtersource = 'URL';				//Source of Filter Parameter
		$this->EventArguments['Filtersource'] = $Filtersource;
		$this->fireEvent('UsingFilter');
		//echo '<BR>'.__LINE__.' Filter:'.var_dump($Filter);
		//-------------FIREEVENT-------------------
		//echo '<BR>'.__LINE__.' Filter:'.var_dump($Filter);
		$Urlparms = $Filter;						//Pick up the filter parmeters
		//echo '<BR>'.__LINE__.'count:'.count($Urlparms).' Urlparms:'.var_dump($Urlparms).'<br>';
		if (count($Urlparms) == 0) {						//No URL or Fireevent parameters
			//echo '<BR>'.__LINE__.' count:'.count($Urlparms).' Urlparms:'.var_dump($Urlparms).'<br>';
			$DefaultFilter = c('Plugins.FilterDiscussion.DefaultFilter',' ');
			parse_str($DefaultFilter,$Filter);
			//echo '<BR>'.__LINE__.' DefaultFilter:'.$DefaultFilter.' Filter:'.var_dump($Filter).'<br>';
			$Filtersource = 'Default';
			//echo '<BR>'.__LINE__.' Filter:'.var_dump($Filter);
			$this->EventArguments['Filter'] = &$Filter;
			$this->EventArguments['Filtersource'] = $Filtersource;
			$this->fireEvent('UsingFilter');
			//echo '<BR>'.__LINE__.' Filter:'.var_dump($Filter);
			$Urlparms = $Filter;
		}
		//echo '<BR>'.__LINE__.'count:'.count($Urlparms).' Urlparms:'.var_dump($Urlparms).'<br>';
		if (count($Urlparms) == 0) { //if (!is_array($Urlparms) || !empty($Urlparms)) {
			//echo '<BR>'.__LINE__.'count:'.count($Urlparms).' Urlparms:'.var_dump($Urlparms).'<br>';
			$this->HelpMessage(Gdn::Translate('Missing Parameters'),$ValidFields);
			//echo '<BR>'.__LINE__.'count:'.count($Urlparms).' Urlparms:'.var_dump($Urlparms).'<br>';
			return;
		}
		if ($Debug) 
			echo '<BR>'.__LINE__.'count:'.count($Urlparms).' Urlparms:'.var_dump($Urlparms).'...............';
		//Get the Discussion Table field names
		$Table = 'Discussion';
		$Fields = $this->GetColumns($Table,$Debug);
        //decho (array_keys($Fields));
		//echo '<BR>'.__FUNCTION__.__LINE__.' $Table:'.$Table.'<br>';
		//var_dump($Fields);
		//echo '<BR>'.__FUNCTION__.__LINE__.' $Table:'.$Table.'<br>';
		//
		$Numparms = count($query);
        
		$CurrentparmNum=0;
		$Gotsavedfilter = false;
		//$Likeop="Like";										//Future Development
		//
        $Userid = Gdn::Session()->UserID;
        $Username = Gdn::Session()->User->Name;
        $vars = array(
              '{$userid}'     => $Userid,
              '{$username}'   => $Username
            );
        //decho ($Numparms);
        //decho ($query);
        //decho ($query);
        $and = "";
        foreach ($query as $element) {  //cycle through getquery results
            //decho ($element);
            $entry = explode("=", $element);
            //decho ($entry);
            $key = trim($entry[0]);
            $prefix = "";
            if (substr($key,1,1) == "_") {
                $keyparts = explode("_", $key);
                if ($keyparts[0] != $key) {
                    $prefix = $keyparts[0];
                    $key = $keyparts[1];
                }
            }
            if (substr($key.' ',0,1) == "!") {
                $value = $entry[1];
                $action = '';
                $order = '';
            } else {
                $Valuearray = explode(":",$entry[1]);
                //decho ($Valuearray);
                $action = strtoupper($Valuearray[0]);
                $value = $Valuearray[1];
                $order = $Valuearray[2];
            }
            if ($Debug) 
                echo '<BR>'.__LINE__.'key:'.$key.' action:'.$action.' value:'.$value.' order:'.$order;
			$CurrentparmNum = $CurrentparmNum + 1;
			if ($Debug) echo '<BR>'.__LINE__.'CurrentparmNum:'.$CurrentparmNum.' Numparms:'.$Numparms;
			if ($CurrentparmNum > $Numparms) break;
			//if ($Debug)  Gdn::Controller()->InformMessage('Numparms:'.$Numparms.'');
                
			//if ($Debug)  Gdn::Controller()->InformMessage('Numparms:'.$Numparms.'');
			if ($Debug) echo '<BR>'.__LINE__.'CurrentparmNum:'.$CurrentparmNum.' key:'.$key.' Value='.$value;
			if ($key == "!debug" || $key == "!debug2") {								// For debugging
				$Debug = false;
				if ($value == 'Y') $Debug = true;
				//echo '<BR>'.__LINE__.'CurrentparmNum:'.$CurrentparmNum.' key:'.$key.' Value='.$value;
				//echo "<br> Ignoreparms:".$Ignoreparms."<br>";
				//$this->Showdata($Ignoreparms,'Ignoreparms','');
				continue;
			}
			elseif ($key == "{\$category}") {	// 1.6 - support for named category
                if ($value == '') {
					$this->HelpMessage(Gdn::Translate('missing category name'));  //Throw error as well as some help
					return;
                }
                $category =  $this->getcategoryid(urldecode($value));    //Get category object for the named category 
                //decho ($category);
                $categoryname = '';
                if ($category) {
                    $key  = 'CategoryID';
                    $categoryname = $category->Name;
                    $value = $action.":".$category->CategoryID.":".$order;
                    $value = $category->CategoryID;
                } else {
					$this->HelpMessage(Gdn::Translate('Invalid category name:').$value);  //Throw error as well as some help
					return;
                }               
			}
			elseif ($key == "InsertUserID" || 
                    $key == "UpdateUserID") {
                $user = Gdn::userModel()->getID($value, DATASET_TYPE_OBJECT);
                $rUsername = $user->Name;;
            }
			elseif ($key == "{\$insertusername}" || 
                    $key == "{\$updateuserrname}") {	// 1.8 - support for named users
                if ($value == '') {
					$this->HelpMessage(Gdn::Translate('missing user name'));  //Throw error as well as some help
					return;
                }
                $Username = urldecode($value);
                $user = Gdn::userModel()->getByUsername($Username);
                if ($user) {
                    $UserID = $user->UserID;
                    if ($key == "{\$insertusername}") {
                        $key  = 'InsertUserID';
                    } else {
                        $key  = 'UpdateUserID';
                    }
                    $value = $UserID;
                } else {
					$this->HelpMessage(Gdn::Translate('Invalid user name:').$Username);  //Throw error as well as some help
					return;
                }             
			}
			elseif (in_array($key, $Ignoreparms)) {					//Defined as ignored parameter?
				if ($Debug) $this->Showdata($Ignoreparms,'Ignored parameter',''); 
				continue;
			}
			// Special parameter to create the title for the result screen (may include some HTML)
			// e.g. discussions/filterdiscussion?Alert=NN&!msg=<span%20style="color:white;background-color:blue">Alerts</span>
			elseif ($key == "!info") {
                if ($IsAdmin) {
                    $rsql = clone Gdn::sql();
                    $rsql->reset();
                    $Titlemsg .= "<h2>PHP Version:" . PHP_VERSION .
                                " Database Version:".$rsql->information('Version').
                                " Database Name:".$rsql->information('DatabaseName').
                                " Database HostName:".$rsql->information('HostName').
                                " Server Software: ". Gdn::request()->getRequestArguments('server')["SERVER_SOFTWARE"].
                                "</H2>";
                    if ($Debug) echo '<br>'.__LINE__.' CurrentparmNum:'.$CurrentparmNum.'<br>';
                }
				continue;
			} 
			elseif ($key == "!r") {
				continue;
			} 
			elseif ($key == "!a-") {
				continue;
			}
			elseif ($key == "!new") {
                $Sender->SQL->Where("w.CountComments",' > ', '0');
                $Title=$Title . " New ";
                $and = " & ";
                if ($Debug) echo '<BR> EQ.  Title='.$Title.'<br>';
				continue;
			} 
			elseif ($key == "!read") {
                $Sender->SQL->Where("w.CountComments",' = ', '0');
                $Title=$Title . " read ";
                $and = " & ";
                if ($Debug) echo '<BR> EQ.  Title='.$Title.'<br>';
				continue;
			} 
			elseif ($key == "!msg") {
				$Titlemsg .= urldecode($value);
                $vars['{$category}'] = $categoryname;
                $vars['{$username}'] = $Username;
                $vars['{$rusername}'] = $rUsername;
                $vars['{$filter}'] = Gdn::request()->get('!filter');
                $Titlemsg = strtr($Titlemsg, $vars);  // 1.6 - support for dynamic reference to self userid and category name in title
				if ($Debug) echo '<br>'.__LINE__.' CurrentparmNum:'.$CurrentparmNum.'<br>';
				continue;
			}
            elseif ($prefix == "") {                        //Experimental
                if (!array_key_exists($key, $Fields)) {
                    $lcfields = array_change_key_case($Fields);
                    if (array_key_exists(strtolower($key), $lcfields)) {                    //Ignore case of key (Version 1.8.5)
                        $nameindex = array_search(strtolower($key),array_keys($lcfields));  
                        $key = array_keys($Fields)[$nameindex];                             //Use correct key case
                    } else {
                        $this->HelpMessage($key. ' '.Gdn::Translate('Not in the '.$Table.' table'),$Fields);  //Throw error as well as some help
                        return;
                    }
                }
            }
			if (is_array($ValidFields) && !empty($ValidFields) && !in_array($key, $ValidFields)) {					//Not in the allowed list?
				$this->HelpMessage($key. " ".Gdn::Translate('Not allowed'),$ValidFields);  //Throw error as well as some help
				echo "<br>Parameter ".$CurrentparmNum." ".$key. " ".Gdn::Translate('Not allowed');
				return;
			}
            $value = strtr($value, $vars);                          // 1.6 - support for dynamic reference to self userid
            if ($prefix) {                                          //Experimental
                $Searchcolumn = $prefix.'.'.$key;					//Take parameter table prefix (at caller's risk...)
            } else {
                $Searchcolumn = 'd.'.$key;					        //Default is the discussion table
            }
			$searchvalue = $value; 
			if ($order) {
                $direction = str_ireplace(["a","d"], ["asc","desc"], $order, $repcount);
                if (!$repcount) {
                    echo "<br> ".Gdn::Translate('Use a or d for sorting order');
                    $this->HelpMessage($order. " ".Gdn::Translate(' is not a valid sort order'),$ValidFields);  //Throw error as well as some help
                    return;
                }			
                if ($Debug) {
                    echo '<BR>'.__LINE__.' key:'.$key.' operand:'.$action.' value:'.$searchvalue.' order:'.$order.' direction:'.$direction.'<br>';
                }
                if ($direction) {
                    $Sender->SQL->orderBy('d.'.$key,$direction);
                }
                if ($searchvalue === "" || !$action) continue;                  //Allow sort order without filtering
            }
			// Handle the special case where the filter field is a date (DateInserted, DateUpdated, DateLastComment)
			if ($Debug) 
				echo '<BR>'.__FUNCTION__.__LINE__.' key:'.$key.' action:'.$action.' searchvalue:'.$searchvalue;
            if ($Fields[$key] == 'datetime' || $Fields[$key] == 'date') {
                $action = urldecode($action);
                if ($Debug) echo '<BR>'.__FUNCTION__.__LINE__.' key:'.$key.' Fields[$key]:'.$Fields[$key].
                     ' action:'.$action.' searchvalue:'.$searchvalue;
                $Basedate=date_create($Discussion->$key);
                $Basedate = new DateTime();
				$cdate = date_add($Basedate,date_interval_create_from_date_string($searchvalue.' days'));
                $searchvalue = date_format($cdate,'Y-m-d H:i:s');
				if ($action == 'd>') {          //compatibility with older plugin version
					$action = 'GT';
				} elseif ($action == 'd<') {    //compatibility with older plugin version
					$action = 'LT';
				}
                if ($action == "NE") {
                    $searchvalue = date_format($cdate,'Y-m-d');
                } elseif ($action == "EQ") {
                    $raction = $action;
                    $action = "RN";
                    $searchvalue = date_format($cdate,'Y-m-d');
                    $Prevdate = $cdate->modify("-1 day");
                    $searchvalue1 = date_format($Prevdate,'Y-m-d');
                    $Nextdate = $cdate->modify("+2 day");
                    $searchvalue2 = date_format($Nextdate,'Y-m-d');
                } else {
                    $searchvalue = date_format($cdate,'Y-m-d H:i:s');
                }
            }
			//
			if ($Debug) 
				echo '<BR>'.__LINE__.' Searchcolumn:'.$Searchcolumn.' action:'.$action.' searchvalue:'.$searchvalue;	
			switch  ($action) {											//Build the SQL and title based on the operation
				case "SORT":                                               //Special case - no filter, just sort
                    $direction = str_ireplace(["a","d"], ["asc","desc"], $searchvalue, $repcount);
                    if (!$repcount) {
                        echo "<br> ".Gdn::Translate('Use a or d for sorting order');
                        $this->HelpMessage($searchvalue. " ".Gdn::Translate(' is not a valid sort order'),$ValidFields);  //Throw error as well as some help
                        return;
                    }
                    if ($direction) {
                        $Sender->SQL->orderBy('d.'.$key,$direction);
                    }
					break;	
                case "LIMIT":                                               //For future development
                case "OFFSET":  
                    (is_numeric($searchvalue) && $searchvalue > 0) ? $amount = $searchvalue : $amount = 0;
                    if (!is_numeric($searchvalue)) {
                        echo "<br> ".Gdn::Translate('Use a numeric value for '.$action);
                        $this->HelpMessage($searchvalue. " ".Gdn::Translate(' is not a valid amount'));  //Throw error as well as some help
                        return;
                    }
                    //decho ($amount);
                    if ($action == "LIMIT") {
                        $Sender->SQL->limit($amount);   //FFU -- does not seem to work
                    } else {
                        $Sender->SQL->offset($amount);   //FFU -- does not seem to work
                    }
					break;											//Build the SQL and title based on the operation
				case "!R":
                    ///$Sender->SQL->Where('w.UserID  ', Gdn::Session()->UserID);	 
                    //$Sender->EventArguments['Wheres']['d.DiscussionID'] = " > " . $searchvalue;
                    $args['Wheres']['d.DiscussionID'] = " <> " . $searchvalue;
                    //$args['Wheres']['userID'] = " > " . $searchvalue;
                    //$args['Wheres']['d.DiscussionID'] = ' < 685' ;
                    //$this->Showdata($Sender->EventArguments['Wheres']," Sender->EventArguments['Wheres']",''); 
                    //$this->Showdata($Sender->EventArguments," Sender->EventArguments",''); 
                    decho ($args);
                    decho ($Sender->EventArguments['Wheres']);
					$Title=$Title . "  " . t("Unread Discussions");
                    $and = " & ";
                    $Getfilters = $Sender->getFilters();
                    decho ($Getfilters);
					break;
				case "RN":                                  //Date Range
					$Sender->SQL->Where($Searchcolumn." > ", $searchvalue1); 
					$Sender->SQL->Where($Searchcolumn." < ", $searchvalue2);
					$Title = $Title . $and . $key . " " . $raction ." " . $searchvalue."  ";
                    $and = " & ";
					break;
				case "NL":
					$Sender->SQL->Where('d.'.$key,NULL);			
					$Title=$Title . $and . $key." is NULL ";
                    $and = " & ";
					break;
				case "NN":
					$Sender->SQL->Where($Searchcolumn.' >', false);		//Not NULL  
					$Title=$Title . $and . $key." > 0 ";
                    $and = " & ";
					break;
				case "EQ":
					$Sender->SQL->Where($Searchcolumn,$searchvalue);
					$Title=$Title . $and . $key." = ".$searchvalue."  ";
                    $and = " & ";
					if ($Debug) echo '<BR> EQ.  Title='.$Title.'<br>';
					break;
				case "NE":
					$Sender->SQL->Where($Searchcolumn.' <> ',$searchvalue);	 
					$Title=$Title . $and . $key." <> ".$searchvalue."  ";
                    $and = " & ";
					break;
				case "GT":
					$Sender->SQL->Where($Searchcolumn.' > ', $searchvalue); 		
					$Title=$Title . $and . $key." > ".$searchvalue."  ";
                    $and = " & ";
					break;
				case "LT":
					$Sender->SQL->Where($Searchcolumn.' < ', $searchvalue);
					$Title=$Title . $and . $key." < ".$searchvalue."  ";
                    $and = " & ";
					break;
				case "help":
					$Title = "Valid operators: EQ, NE, LT, GT, NL, NN"; //, LK, NK";
					echo $Title;
					Gdn::Controller()->Title($Title);
					return;
				case "LK":															//Future development 
					$Sender->SQL->Where($Searchcolumn.' LIKE ', "%".$searchvalue."%"); 		//LIKE search
					$Title=$Title . $and . $key." like ".$searchvalue."  ";
                    $and = " & ";
					break;
				case "NK":	
					if ($Debug) $this->Showdata($Likeop,'Before setting Where for like','');
					$Sender->SQL->Where($Searchcolumn.' NOT LIKE ', "%".$searchvalue."%"); 		// NOT LIKE search
					$Title=$Title . $and . $key." not like ".$searchvalue."  ";
                    $and = " & ";
					break;
				
				default:
					$this->HelpMessage(Gdn::Translate("Invalid operator:").$action,$ValidFields);
					return;
				break;
			}
		}
    /*	if ($Debug) {
			echo "<BR>....SQL:<BR>";
			$MySQL=$Sender->SQL;
			$Where=$Sender->SQL->Where;
			var_dump($Where);
			//$this->Showdata($MySQL,'Before Select setting','');
			var_dump($MySQL);
		}
	*/
		if ($Titlemsg) {
            $Title = $Titlemsg;
        }
		Gdn::Controller()->Title($Title);
        //$this->ShowSQL($Sender,__LINE__."Sender->SQL",'');
        //if ($Debug) var_dump ($Sender->SQL->Where);
		//That's all folks!
	}
	///////////////////////////////////////////////
	public function getcategoryid($categoryname, $Debug = false) {
		if ($Debug) echo '<br><h>'.__LINE__.' categoryname:'.$categoryname;
        //We need to use new DB access (to ensure we don't destroy th in-flight SQL set by DiscussionModel_BeforeGet)
        $Sql = clone Gdn::sql(); 
        $Sql->reset();
        $User = '0';
        $Results = $Sql
            ->select('*')
            ->from('Category c')
            ->where('CategoryID >', 0)
            ->where('DisplayAs <>', 'Heading')
            ->Where('Name', $categoryname)
            ->get()
            ->firstRow();
        //decho ($Results);
        return $Results;
	}
	///////////////////////////////////////////////
	public function GetColumns($Table = 'Discussion', $Debug = false) {
		if ($Debug) echo '<br><h>'.__LINE__.' Table:'.$Table;
		$database = new Gdn_Database();
		$schema = new Gdn_Schema($Table, $database);
        //decho ($schema);
		$Fieldsandtypes  = array_column($schema->fields(),"Type","Name");
        //decho ($Fieldsandtypes);
        return $Fieldsandtypes;
	}
	///////////////////////////////////////////////
	// Display data for debugging
	public function Showdata($Data, $Message, $Find, $Nest=0, $BR='<br>') {
		//var_dump($Data);
		echo "<br>".str_repeat(".",$Nest*4)."<B>(".($Nest).") ".$Message."<n>";
		$Nest +=1;
		if ($Nest > 10) return;	
		if  (is_object($Data) || is_array($Data)) {
			foreach ($Data as $key => $value) {
				if  (is_object($value)) {
					$this->Showdata($value,'oooo '.gettype($value).'=>key:'.$key.' value =>','',$Nest,'<n>');
				} elseif (is_array($value)) {
					$this->Showdata($value,'aaaa '.gettype($value).'=>key:'.$key.' value[]:','',$Nest,'<n>');
				} else {
					$this->Showdata($value,'ssss '.gettype($value).'=>key:'.$key.'   value:','',$Nest,'<n>');
				}
			}
		} else {
			var_dump($Data);
		}
	}
	///////////////////////////////////////////////
   	// Display help messages
	public function HelpMessage($Message,$Fields = array()) {
        setcookie("IBPDfilter", null, time()+86400, "/");
        (isset(debug_backtrace()[0]['line'])) ? $line = debug_backtrace()[0]['line'] : $line = __LINE__;
		if (!$Message == "") echo "<P><H1><B>".$line." FilterDiscussion Plugin Message:".$Message."<N></H1></P>";
		echo '<div>'.'<BR>Syntax: &'. 'fieldname=operator:value:sortorder,...(can specify several field name combinations)';
		if (is_array($Fields) && !empty($Fields)) {
            if ( count(array_filter(array_keys($Fields), 'is_string')) > 0) {
                $fieldnames =  implode(", ", array_keys($Fields));
            } else {
                $fieldnames =  implode(", ", $Fields);
            }
			echo '<BR><b><span>Defined fields:</b><n></span><span style="color:#0000FF"> ' . $fieldnames . '</span>';
        }
		//$this->Showdata($Fields,'Defined fields:','');
		echo "<p><b>Valid operators: EQ, NE, GT, LT, NL, NN, LK, NK";
		echo "<br><b>For date fields these additional operators apply: d&gt, d&lt.  If used the value field is the +or - followed by the number of days from the current date";
		echo "<p><b>Valid sort order: d or a (for desending or ascending). Order is optional.";
		echo "<BR>Special parameters: (1) &!msg=  followed by the text of the title of the filtered view. ";
		echo "<BR>Special parameters: (2) &!filter=  followed by the name of a saved filter (set by the administrator)";
		echo "<BR>Example: discussions/FilterDiscussion/&InsertUserID=EQ:6:a&CategoryID=GT:9&!msg=This is a filtered list";
		echo "<BR>Example: discussions/FilterDiscussion/&!filter=Imagefilter</div>";
	}
  	///////////////////////////////////////////////
	// Plugin Setup 
	public function Setup() {
		// Initialize plugin defaults
        touchconfig('Plugins.FilterDiscussion.Fieldnames', 
			'DiscussionID,CategoryID,InsertUserID,UpdateUserID,Name,Body,FirstCommentID,LastCommentID');  //Set few default fields
        touchconfig('Plugins.FilterDiscussion.Ignoreparms', 
			'');  //Set few default fields
        touchconfig('Plugins.FilterDiscussion.DefaultFilter', 
			'DiscussionID=GT:0,&!msg='.Gdn::Translate('Discussions'));  //Set few default filter
        touchconfig('Plugins.FilterDiscussion.SavedName1', Gdn::Translate('mystuff'));  //Set example saved filter name
        touchconfig('Plugins.FilterDiscussion.SavedFilter1', 'InsertUserID=EQ:{$userid}');  //Set example saved filter name
	}
	///////////////////////////////////////////////
	//This is an example of a hook for the FilterDiscussion plugin.  It shows how to check for passed filter
	//arguments and how to change it.
	public function FilterDiscussion_UsingFilter_Handler($Sender, $Args) {
		$Plugin = $Args['PluginName'];						//Calling plugin name
		$Filtersource = $Args['Filtersource'];				//Source of the passed filter
		if ($Filtersource  == 'URL') return;				//Example:Ignore if filter comes from the url parameters
		$Filter = $Sender->EventArguments['Filter'];		//Passed Filter from the FilterDiscussion plugin
		$Filter = $Args['Filter'];
		//echo '<BR>'.__LINE__.' Plugin:'.$Plugin.' Filtersource:'.$Filtersource.' Filter:'.var_dump($Filter);
		if (array_key_exists('testfireevent', $Filter)) {	//Is there a "testfireevent" parameter?
			$Filter =  Array( 								//Set the new filters
					"Name" => "LK:test", 					//Discussion title contains "test" (like operator)
					"InsertUserID" => "NE:1",				//Discussion creator not the admin (assuming system ID=1) 						
					"!msg" => 'Discussions with title containing "test"'); //Set Display title
			$Sender->EventArguments['Filter'] = $Filter;	//Return the modified filter arguments to use 
		} elseif (array_key_exists('testfireevent2', $Filter)) {	//Is there a "testfireevent2" parameter?
			$Filter =  Array( 								//Set the new filters
					"!filter" => "Test2"); 					//Use saved filter name "Test2"
			$Sender->EventArguments['Filter'] = $Filter;	//Return the modified filter arguments to use 
		}
		//echo '<BR>'.__LINE__.' Plugin:'.$Plugin.' Filter:'.var_dump($Filter);
		//echo '<BR>'.__LINE__;
	}
	///////////////////////////////////////////////
	// Dashboard settings
	//public function PluginController_FilterDiscussion_Create($Sender) {
	public function settingsController_FilterDiscussion_Create($Sender) {
        $Sender->addCssFile('filterdiscussion.css', 'plugins/FilterDiscussion');
        $Plugininfo = Gdn::pluginManager()->getPluginInfo('FeedDiscussionsPlus');
		$Sender->Title('FilterDiscussion '.Gdn::Translate('Version').' '.$Plugininfo["Version"].' '.Gdn::Translate('Settings'));
        $Sender->AddSideMenu('plugin/FIlterDiscussion');
        $Sender->Permission('Garden.Settings.Manage');
        $Sender->Form = new Gdn_Form();
        $Validation = new Gdn_Validation();
        $ConfigurationModel = new Gdn_ConfigurationModel($Validation);
        $ConfigurationModel->SetField(array(
            'Plugins.FilterDiscussion.Fieldnames',
			'Plugins.FilterDiscussion.Ignoreparms',
			'Plugins.FilterDiscussion.DefaultFilter'));
        $Table = 'Discussion';
		$Fields = $this->GetColumns($Table,$Debug);
        //decho (array_keys($Fields));
        $Sender->SetData('Fields', array_keys($Fields));
		for ($x = 1; $x <= 20; $x++) {	
			$ConfigurationModel->SetField(array(
				'Plugins.FilterDiscussion.SavedName'.$x,
				'Plugins.FilterDiscussion.SavedFilter'.$x));
		}
        $Sender->Form->SetModel($ConfigurationModel);


        if ($Sender->Form->AuthenticatedPostBack() === false) {
            $Sender->Form->SetData($ConfigurationModel->Data);
        } else {
            $Data = $Sender->Form->FormValues();

            if ($Sender->Form->Save() !== false)
                $Sender->StatusMessage = Gdn::Translate("Your settings have been saved.");
        }
        $this->renderview($Sender, 'filterdiscussionsettings');
	}
	///////////////////////////////////////////////
    private function getquery($Duplicates = true) {
        $query = $_SERVER['QUERY_STRING'];
        if ($query == "!!") {
            if (isset($_COOKIE["IBPDfilter"])) {
                $query = $_COOKIE["IBPDfilter"];
            } else {
                $query = '';
            }
        }
        //  Substitute saved filters (if !filter specified)
        $request = Gdn::request()->get();
        if (isset($request['!filter'])){
            $filtername = $request['!filter'];
            $query = GetSavedFilter($filtername);
            if ($query == "") {
                $this->HelpMessage(Gdn::Translate('Named Filter "').$filtername. '" '.Gdn::Translate('Not defined'));  //Throw error as well as some help
                return null;
            }
        }
        parse_str($_SERVER['QUERY_STRING'], $Original);
        parse_str($query, $Parsed);
        $newrequest = '';
        foreach ($Parsed as $keyword => $value) {
            $parts = explode(':',$value);
            if (in_array($parts[0],["{0}","{1}","{2}","{3}","{4}","{5}","{6}","{7}","{8},{9}"]) OR 
                in_array($parts[1],["{0}","{1}","{2}","{3}","{4}","{5}","{6}","{7}","{8},{9}"])) {
                if (isset($Original[$parts[0]])) {
                    $parts[0] = $Original[$parts[0]];
                    $value = implode(':',$parts);
                    $newrequest .= "&".$keyword.'='.$value;
                }
                if (isset($Original[$parts[1]])) {
                    $parts[1] = $Original[$parts[1]];
                    $value = implode(':',$parts);
                    $newrequest .= "&".$keyword.'='.$value;
                }
            } else {
                $newrequest .= "&".$keyword.'='.$value;
            }
        }
        if ($newrequest) $query = $newrequest;
        //
        if ($Duplicates) {
            setcookie("IBPDfilter", trim($query), time()+86400, "/");
            return explode("&", $query);
        } else {
            $query = Gdn::request()->get();
            setcookie("IBPDfilter", trim($query), time()+86400, "/");
            return $query;
        }
    }
	///////////////////////////////////////////////
    private function renderview($Sender, $Viewname) {
        //$this->Showdata($Viewname,'Viewname',''); 
        // New method (only in 2.5+)
        if(version_compare(APPLICATION_VERSION, '2.5', '>=')) {
            $Sender->render($Viewname, '', 'plugins/FilterDiscussion');
        } else {
            $Viewname .= '.php';
            $View = $this->getView($Viewname);
            $Sender->render($View);
        }
        //
    }
	///////////////////////////////////////////////
        /*-------------------------------------------------------------------------------
		Below is a list of the Discussion table fields with several plugins enabled (and those may have added fields that won't necessarily
		exist in every installation):
			DiscussionID,Type,ForeignID,CategoryID,InsertUserID,UpdateUserID,FirstCommentID,LastCommentID,Name,Body,Format,Tags,CountComments,CountBookmarks, CountViews,Closed,Announce,Sink,DateInserted,DateUpdated,InsertIPAddress,UpdateIPAddress,DateLastComment,LastCommentUserID,Score,Attributes, RegardingID,Scheduled,ScheduleTime,Discussants,QnA,DateAccepted,DateOfAnswer,EventCalendarDate ,DiscussionEventDate,Resolved,DateResolved,ResolvedUserID 
		-------------------------------------------------------------------------------*/
    
	///////////////////////////////////////////////
   	// Throw with a severe message
	public function SevereMessage($Message) {
		echo "<P><H1><B>FilterDiscussion Plugin Message:".$Message."<N></H1></P>";
		//$Sender->InformMessage($Message);
		//throw new Gdn_UserException($Message);
	}
	///////////////////////////////////////////////
}
	//Global Functions
if (!function_exists('GetSavedFilter')) {
   	// Display handle debug parameter
	function GetSavedFilter($value,$Debug = false) {
		if ($Debug) {
			echo '<BR>'.__FUNCTION__.__LINE__.' Value:'.$value.' <br>';
		}
		$Addfilter = '';
		for ($x = 1; $x <= 20; $x++) {		
			if (trim($value) == trim(Gdn::config('Plugins.FilterDiscussion.SavedName'.$x,' '))) {
					$Addfilter = trim(Gdn::config('Plugins.FilterDiscussion.SavedFilter'.$x,' '));
				if ($Debug) echo '<BR>'.__FUNCTION__.__LINE__.' i:'.$x.' Addfilter:'.$Addfilter.' <br>';
				return $Addfilter;
			}
		}
		return $Addfilter;
	}
}
	///////////////////////////////////////////////