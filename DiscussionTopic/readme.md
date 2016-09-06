The DiscussionTopic plugin increase user engagement and enhance self-help by adding sharing a topic across multiple discussions.  The plugin adds a Topic field to the discussion table and operates in several modes:<br>
1. Manual - authorized users can manually add a topic to a discussion<br>
2. Deterministic - by picking double quoted phrases from the discussion title or through "Priority phrases" defined by the administrator.<br>
3. Heuristic analysis - through an approximation of linguistic analysis (English only) picking key terms as the topic.<br>
4. Deterministic - a combination of Deterministic and Heuristic modes<br>
The most effective result is through the plugin side panel which shows the titles of other discussions sharing the same topic.  Clicking on any one of the titles brings the appropriate discussion with the same side-panel, in effect allowing users to review related discussions.<br>
I ran a test on the Vanilla forum (using the forum feed) and after simple setting of priority phrases (e.g. "Something has gone wrong") I was able to see related results.<br>
<b>Note: if you are upgrading from Version 3.1.4 you should follow this process:</b><br>
1. If you modified the plugin en.php in the plugin locale folder make a backup of your en.php file
2. Disable the plugin, delete all files in the plugin folder
3. Install a newer version of the plugin
4. If you modified the plugin en.php in the plugin locale folder, replace the plugin locale.php with the backed up copy from step 1 above
5. Enable the plugin
<br>
Changes:<br>
Release 2.1 - First public release (Ported from an intranet implementation)<br>
Release 2.2 Changes:<br>
- Added detailed customization guide<br>
- Added explanations to the customization screen<br>
- Side panel links now contain the complete discussion names<br>
- Fixed manual update form error<br>
- Enhanced reporting of multiple record update during setup/customization<br>
- Some code cleaning & style standardization<br>

Release 3.1 Changes:<br>
- The side panel as well as the topic in the meta area are refreshed when the discussion is saved or when the discussion topic is updated<br>
- Added the ability to mark a discussions as "Top Topic" so that they would appear first on the side panel of discussions with the same topic<br>
- Clicking on the topic value shown in the meta area will display a filtered discussion list with the same topic
- Added the ability to add a "Topic-Search" option on the main menu bar.  If enabled, users will be able to quickly search for exact topics or enter a free form discussion title and it would search for the generated topic.  In effect, this provides an easy way to find answers before posting questions.
- Added explanation on how to use the plugin with non-English languages<br>
- Some more code cleaning & style standardization
<br>
Release 3.1.4 Changes:<br>
- More code standardization<br>
- Fix minor errors (bypass for https://github.com/vanilla/vanilla/issues/4391)<br>
<br>
Release 3.1.5 Changes:<br>
- Plugin folder cleanup
- Ensure Priority phrases are serched before any noise words processing
- Added "Explain" functionality to help admins understand how noise words, acronyms and priority phrases determine the topic.
<br>