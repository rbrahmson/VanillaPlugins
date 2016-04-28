​The Top Bookmarks plugin displays information of top bookmarked discussions (those with the highest number of bookmarks).  
What is considered "Top bookmarked" and what to display is highly customizable and subject to permissions set by the configuration screen.
Change log:  
Version 1.1 - 	Added Aging support.

<b>Top Bookmarks Definition:</b>

There are two parameters to the definition of 'Top Bookmarks":
<br><b>(A) Aging:</b> How "aged" a bookmarked discussion is allowed to be to be considered at "Top Bookmarked".  
The discussion age is determined from the most recent date of creation or commenting.  
You can specify the number of recent activity days to include in top bookmarks or leave zero disregard discussion aging).
<br><b>(B) Minimum Count:</b> The minimum number of bookmarks for a discussion to be considered "Top Bookmarked".  
Pick a number not too high as to have an empty list and not too low as to water down the meaning ot "Top Bookmarks".

Note: TO help you decide the Minimum Count, the plugin will inform you dusring configuration of the current highest number of bookmarks a discussion has in your forum.

<b>Display Options:</b>

There are five display options that can be turned on (all subject to permission settings - see below):

Option 1: Add a menu bar link to display the top bookmarked Discussions (sorted by the number of Bookmarks).

Option 2: Add a side panel link to display the top bookmarked Discussions (sorted by the number of Bookmarks).

Option 3: Display Top Bookmark information in the meta area.

Option 4: If Option 3 is turned on, then this option provided a pop up link to show who bookmarked the discussion.

Option 5: Add an options gear (❁) a pop up link to show who bookmarked the discussion.
 
<b>Permission Settings:</b>

Two permissions settings are provided:
1. Option to require "TopBookmarks View" permission in "Roles and Permissions" to see the Top Bookmarks.  If this option is turned on display options 1, 2, 3, and 4 are not available to a user lacking the permission.
2. Option to require "TopBookmarks ViewMarkers" permission in "Roles and Permissions" to see who bookmarked the top bookmarked discussions.  If this option is turned on display options 4 and 5 are not available to a user lacking the permission.

<b>No performance impact is expected by the use of this plugin:  
- Both aging and the minimum number of bookmarks per discussion will affect the size of the Top-Bookmarks discussion list.  However the list is using the standard Vanilla Discussion list access further filtered but these two parameters, both of which are in the discussion table.  Thus this should not affect performance.
- The list of users who bookmarked a discussion is limited to 200 (a hard coded limit in the plugin source). The list is displayed on demand (user click on the link) and access a single table.   

​
