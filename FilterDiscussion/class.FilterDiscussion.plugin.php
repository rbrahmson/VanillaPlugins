<?php if(!defined('APPLICATION')) exit();
$PluginInfo['FilterDiscussion'] = array(
    'Name' => 'FilterDiscussion',
	'Description' => "FilterDiscussion - A plugin that dynamically creates custom filtered / sorted discussion lists.",
    'Version' => '1.8.1',
    'RequiredApplications' => array('Vanilla' => '2.6'),       		    /*This is what I tested it on...*/
    'RequiredTheme' => FALSE,
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
            - Support for sorting fields (thanks to user donshakespeare who initiated this feature). 
              Example:   /discussions/filterdiscussion?InsertUserID=EQ:{$userid}:d
            - support for filtering by explicit user names (rather thantheir internal numbers)
              Example:   /discussions/filterdiscussion?{$insertusername}=EQ:Joe%20Doe:a

No warranty whatsoever is implied by releasing this plugin to the Vanilla community.
*/

class FilterDiscussionPlugin extends Gdn_Plugin {

  // Indicates whether or not we are in the Filtered view
  private $CustomView = FALSE;
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
    $Sender->SetData('_PagerUrl', 'discussions/FilterDiscussion/'.$Page.$Parameters);
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
  // Main processing of the custom view
	public function DiscussionModel_BeforeGet_Handler($Sender) {
		$Debug = false;
		if($this->CustomView != TRUE)  return;
        $query = $this->getquery();
		if (!CheckPermission('Plugins.FilterDiscussion.View')) {  //Validate that the user has permission to filtered views
			$Title = Gdn::Translate('Not allowed.  Unrecognized parameters:').implode("&", $query);       //If not, don't accept the parameters
			Gdn::Controller()->Title($Title);
			return;														// and return without any custom view
		}
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
            if (substr($key.' ',0,1) == "!") {
                $value = $entry[1];
                $action = '';
                $order = '';
            } else {
                $Valuearray = explode(":",$entry[1]);
                //decho ($Valuearray);
                $action = $Valuearray[0];
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
			elseif ($key == "!msg") {				
				$Titlemsg = urldecode($value);
                $vars['{$category}'] = $categoryname;
                $vars['{$username}'] = $Username;
                $vars['{$rusername}'] = $rUsername;
                $Titlemsg = strtr($Titlemsg, $vars);  // 1.6 - support for dynamic reference to self userid and category name in title
				if ($Debug) echo '<br>'.__LINE__.' CurrentparmNum:'.$CurrentparmNum.'<br>';
				continue;
			} 
			elseif (!in_array($key, $Fields)) {					//Not in the current table?
				$this->HelpMessage($key. ' '.Gdn::Translate('Not in the '.$Table.' table'),$Fields);  //Throw error as well as some help
				echo '<br>Parameter '.$CurrentparmNum.' "'.$key.'" '.Gdn::Translate('Not allowed');
				if ($Debug) {
						echo '<br>'.__LINE__.' key:'.$key;
						echo "<BR>'.__LINE__.'Defined fields: " . implode(",", $Fields);
						//var_dump($Fields);
				}
				return;
			}
			elseif (is_array($ValidFields) && !empty($ValidFields) && !in_array($key, $ValidFields)) {					//Not in the list?
				$this->HelpMessage($key. " ".Gdn::Translate('Not allowed'),$ValidFields);  //Throw error as well as some help
				echo "<br>Parameter ".$CurrentparmNum." ".$key. " ".Gdn::Translate('Not allowed');
				return;
			}
            $value = strtr($value, $vars);  // 1.6 - support for dynamic reference to self userid
			$Searchcolumn = 'd.'.$key;					//For now just the discussion table
			$searchvalue = $value;
			if ($order) {
                if ($order == "d") {
                    $order = 'desc';
                } elseif ($order == "a") {
                    $order = 'asc';
                } elseif ($order == "") {
                } else {
                    echo "<br> ".Gdn::Translate('Use + or - for sorting order');
                    $this->HelpMessage($order. " ".Gdn::Translate(' is not a valid sort order'),$ValidFields);  //Throw error as well as some help
                    return;
                }						
                if ($Debug) {
                    echo '<BR>'.__LINE__.' key:'.$key.' operand:'.$action.' value:'.$searchvalue.' order:'.$order.'<br>';
                }
                if ($order) {
                    $Sender->SQL->orderBy('d.'.$key,$order);
                }
                if ($searchvalue === "" || !$action) continue;
            }
			// Handle the special case where the filter field is a date (DateInserted, DateUpdated, DateLastComment)
			if ($Debug) 
				echo '<BR>'.__FUNCTION__.__LINE__.' action:'.$action.' key:'.$key.' searchvalue:'.$searchvalue;
			if (substr($key.'    ',0,4) == 'Date') {
				if ($key == 'DateInserted') {
					$Basedate=date_create($Discussion->DateInserted);
				} elseif ($key == 'DateUpdated') {
					$Basedate=date_create($Discussion->DateUpdated);
				} elseif ($key == 'DateLastComment') {
					$Basedate=date_create($Discussion->DateLastComment);
				}
				$Basedate = new DateTime();
				$cdate = date_add($Basedate,date_interval_create_from_date_string($searchvalue.' days'));
				if ($action == 'd>') {
					$action = 'GT';
					$searchvalue = date_format($cdate,'Y-m-d H:i:s');
				} elseif ($action == 'd<') {
					$action = 'LT';
					$searchvalue = date_format($cdate,'Y-m-d H:i:s');
				}
			}
			//
			if ($Debug) 
				echo '<BR>'.__FUNCTION__.__LINE__.' action:'.$action.' key:'.$key.' searchvalue:'.$searchvalue;		
			switch  ($action) {											//Build the SQL and title based on the operation
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
		//That's all folks!
	}
	///////////////////////////////////////////////
	public function getcategoryid($categoryname, $Debug = false) {
		if ($Debug) echo '<br><h>'.__LINE__.' categoryname:'.$categoryname;
        //We need to use new DB access (to endure we don't destroy th in-flight SQL set by DiscussionModel_BeforeGet)
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
	///////////////////////////////////////////////
	public function GetColumns($Table = 'Discussion', $Debug = false) {
		if ($Debug) echo '<br><h>'.__LINE__.' Table:'.$Table;
		$database = new Gdn_Database();
		$schema = new Gdn_Schema($Table, $database);
		$Fields = array_keys($schema->fields());
		if ($Debug) echo '<br><h>'.__LINE__.' Table:'.$Table;
		if ($Debug) var_dump($Fields);
		return $Fields;	
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
		if (!$Message == "") echo "<P><H1><B>FilterDiscussion Plugin Message:".$Message."<N></H1></P>";
		echo '<div>'.'<BR>Syntax: &'. 'fieldname=operator:value:sortorder,...(can specify several field name combinations)';
		if (is_array($Fields) && !empty($Fields))
			echo '<BR><b><span>Defined fields:</b><n></span><span style="color:#0000FF"> ' . implode(", ", $Fields).'</span>';
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
		for ($x = 1; $x <= 20; $x++) {	
			$ConfigurationModel->SetField(array(
				'Plugins.FilterDiscussion.SavedName'.$x,
				'Plugins.FilterDiscussion.SavedFilter'.$x));
		}
        $Sender->Form->SetModel($ConfigurationModel);


        if ($Sender->Form->AuthenticatedPostBack() === FALSE) {
            $Sender->Form->SetData($ConfigurationModel->Data);
        } else {
            $Data = $Sender->Form->FormValues();

            if ($Sender->Form->Save() !== FALSE)
                $Sender->StatusMessage = Gdn::Translate("Your settings have been saved.");
        }
        $this->renderview($Sender, 'filterdiscussionsettings');
	}
	///////////////////////////////////////////////
    private function getquery($Duplicates = true) {
        $query = $_SERVER['QUERY_STRING'];
        //decho (explode("&", $query));
        if ($query == "!!") {
            $query = Gdn::request()->get();
            //decho ($query);
        }
        //  Substitute saved filters (if !filter specified)
        if (Gdn::request()->get('!filter')){
            $filtername = Gdn::request()->get('!filter');
            //decho ($filtername);
            $query = GetSavedFilter($filtername);
            if ($query == "") {
                $this->HelpMessage(Gdn::Translate('Named Filter "').$filtername. '" '.Gdn::Translate('Not defined'));  //Throw error as well as some help
                return null;
            }
            //decho ($query);
        }
        //
        if ($Duplicates) {
            return explode("&", $query);
        } else {
            $query = Gdn::request()->get();
            //decho ($query);
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
