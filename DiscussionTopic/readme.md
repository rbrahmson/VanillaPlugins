The DiscussionTopic plugin increase user engagement and enhance self-help by adding sharing a topic across multiple discussions.  The plugin adds a Topic field to the discussion table and operates in several modes:
1. Manual - authorized users can manually add a topic to a discussion
2. Automatic deterministic - by picking double quoted phrases from the discussion title or through "Priority phrases" defined by the administrator.
3. Heuristic analysis - through an approximation of linguistic analysis (English only) picking key terms as the topic.

The most effective result is through the plugin side panel which shows the titles of other discussions sharing the same topic.  Clicking on any one of the titles brings the appropriate discussion with the same side-panel, in effect allowing users to review related discussions.

I ran a test on the Vanilla forum (using the forum feed) and after simple tuning of the plugin through the configuration screen I was able to see positive results.  Your results may vary.  

Changes:

Release 2.1 - First public release (Ported from an intranet implementation)

Release 2.2 Changes:
- Added detailed customiation guide
- Added explanations to the customiation screen
- Side panel links now contain the complete discussion names
- Fixed manual update form error 
- Enhanced reporting of multiple record update during setup/customization
- Some code cleaning & style standardization 

Release 3.1 Changes:
- The side panel as well as the topic in the meta area are refreshed when the discussion is saved or when the discussion topic is updated
- Added the ability to mark a discussions as "Top Topic" so that they would appear first on the side panel of discussions with the same topic
- Clicking on the topic value shown in the meta area will display a filtered discussion list with the same topic
- Added the ability to add a "Topic-Search" option on the main menu bar.  If enabled, users will be able to quickly search for exact topics or enter a free form discussion title and it would search for the generated topic.  In effect, this provides an easy way to find answers before posting questions.
- Added explanation on how to use the plugin with non-English languages
- Some more code cleaning & style standardization

