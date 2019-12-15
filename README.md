The plugin filters the discussion list based on the value of the fields in the discussions database. The filtered view is flexible as the filters are specified via URL parameters using flexible syntax. For example, filtering by the CategoryID field is equivalent to display discussions for a particular category. A combination of fields is allowed and the list of supported fields is specified in the administration dashboard. 

Combination field names can be specified to create refined filters.  If the Discussion table in the database has been extended with additional fields, then these can be used as well. For example, the PrefixDiscussion plugin adds a field called "Prefix", so it is possible to filter with the "Prefix=" parameter. Example: /discussions/filterdiscussion&Prefix=EQ:Video&InsertUserID=EQ:13&CategoryID=EQ:6

The title of the resulting filtered screen can also be specified via the &!msg= parameter. Some html tags can be specified (at your risk).
Example: /discussions/filterdiscussion&Prefix=EQ:Video&!msg=<span%20style="color:white;background-color:blue">Highlighted%20Videos</span>

Named filters can be globally saved via the plugin settings through the admin dashboard. Saved filters provide the ability to apply filters
without exposing to the end user the actual parameters being used.   
For example, assume a saved filter named "AlertedVideos" is defined as "&Prefix=EQ:Video&Alert=NN&!msg=Videos of interest"
To invoke that view the following url is needed "/discussions/filterdiscussion&!filter=AlertedVideos
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
Version 1.5 - Added relative date filtering for discussion date columns (for example, DateInserted=d>:-7 filters on discussions created in the last seven days)
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
Version 1.8.4 - filtering on unread discussions (use operator !R).   
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
                    