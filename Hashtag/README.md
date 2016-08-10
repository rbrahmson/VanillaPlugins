​The Hashtag plugin automatically adds tags from hashtags embedded in the discussion title and optionally from the discussion and comment contents.

Several options are controlled via the configuration screen:
- Optionally set embedded hashtags as links to all discussions with the same hashtag.
- Display of hashtags in the meta area of discussion lists
- Display a side panel with links to discussions with the same hashtag set (e.g. if the current discussion is hashtagged with #plugin and #test then the side panel will display discussions tagged with both hashtags)

The plugin requires the official Vanilla Tagging plugin.

Permission Settings:
- User permission to add Vanilla tags must be set in Roles and Permissions.
- Additionally Permission to add hashtags must be set in Roles and Permissions.
The dual permissions provides Administrators the ability to retrict automatic hashtagging to a subset of the users allowed to tag discussions.

The plugin honors the Vanilla security model - the side panel will only show discussions in categories the user has access to.
See the included screen shots.
 
Tested under Vanila 2.2 as well as Vanila 2.3b1.

Change log:

Version 2: 

- Added hashtag display in the meta area
- Added sidepanel
- Minor code formatting
- Annotated screen captures

Version 2.1.2:

-Decreased setting for minimum number of letters in a hashtag from 4 to 2
-Fixed the bug that marked html tags with an hashtag as true user-intended hashtags
-Tighten up the validation of hashtags
-Limit the maximum number of hashtags processed per discussion to the 'Plugin.Tagging.Max' settings of the Tagging Plugin (the Vanila default maximum value is 5).
-Added verification that the taggin plugin was not disabled after the hashtag plugin was enabled.

Version 2.1.3​

- Some code standardization & optimization (more expected in future versions)