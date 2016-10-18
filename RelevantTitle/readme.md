● The RelevantTitle plugin is designed to increase discussions visibility with rules that attempt to make titles relevant to the discussion content.

● Depending on the rules you set, the plugin may even stop some bots in their tracks.

See the plugin settings on the admin dashboard left side under "Forum".

Relevance Heuristics

● The plugin checks whether English title words appears to be related to the discussion body while ignoring some noise words like "the", "a", "will" and matching some word variants like "Bank", "Banks", "banking" and "banked". For efficiency this is not a foolproof process but when prompted to change the title users can easily be more explicit and make it work.

● URLs and html tags within the discussion body are not matched against the title.

● You can set tite rules without enforcing them and then you can run reports to check which of the existing discussions meet the rules you set.  Once you are satisfied you can enforce the rules which are processed when a discussion is saved.  Then, if a the title does not meet the rules requirements the user is prompted to reword the title.


Examples of typical titles that are rejected: "What it this", "I have a question", "See this product".

Versions:

Version '1.1.0' -	INITIAL BETA RELEASE - still contain debugging code


Version '1.1.2' -	BETA RELEASE - still contain debugging code
				-	Change config category selection to  checkbox selection
				-	Prepared for 2.3 compatibility
				-	Better config setup error checking & messaging
				-	Allow the plugin to run on mobile devices
				-	Corrected typos

Version '1.2.0' -	First Official Release.
				-	Bypass relevance testing when saved discussions are moved across categories
				-	Bypass relevance testing when dicsussions are created with the sytemID
				-   Support for the Feed Discussions plugin (option to reject feeds that don't conform to the rules)
				-	Enhanced reporting presentation (color, links to discussion)
				-	Enhanced performance

Version '1.2.1' -	Fixed erroneous feedback message bug.

Version '1.2.2' -	Added Found Words Report to show admins which title words matched discussion bodies

