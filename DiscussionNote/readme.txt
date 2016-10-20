This plugin provides the ability to attach a "post-it"-like note to a discussion.

The administrator can customize how the plugin behaves via several dashboard options:

1. Limit the facility to selected categories (rather than all of the categories).

2. Specify whether the notes should be displayed (embedded) in the discussion list. If this option is not enabled the user needs to click on the icon to pop-up a window that displays the note.

3. Specify optional additional permissions required to see and add/change/delete notes. Note that regardless of these settings, to be able to add/change/delete a note the user must have category permission to add or edit the discussion.  The original user that created the discussion is always allowed to add/edit/delete a note to that discussion.

Presentation Interface:
Small icons in the discussion list as well as the discussion post (and last comment, if there is one) provide a pop-up link that displays or changes the note. If the cursor is rested on the icon a short preview is provided via tooltip.  Optionally the note can be displayed within the discussion list and the discussion post.

You can change the look and feel via the provided CSS file.

Change log:
1.1.2 - (1) Tighten up use of permissions. In addition to the plugin's own permissions ('Plugins.DiscussionNote.View','Plugins.DiscussionNote.Add') that are needed to view or add notes, the users also need the right to either edit or add discussions to a category in order to add a note to a discussion in that category.
(2) Minor CSS tweaks to tighten up the note icon (tested in Chrome and Firefox).
1.2 - (1) Added the ability to display the note within the discussion list.
(2) Rewrite of the plugin permission function with extensive commentary.
(3) Updated the configuration screen to display the list of categories (thanks to a great suggestion by R_J).
