<p>The plugin creates custom filtered and optionally sorted discussions lists. The customized lists can be invoked through other plugins, menu options or panel links.</p>
<p>The filtered/sorted lists are specified via url parameters which can be concealed from the end user via pre-saved named filtered lists. </p>
<p><strong>Security</strong>: </p>
<ol>
<li>The plugin complies with the built-in Vanilla security model -- it can only display what the user is already permitted to see.</li>
<li>The use of the plugin requires additional permission set by the admin in "Roles and Permission" ('Plugins.FilterDiscussion.View'). An admin testing the plugin may not notice this requirement because admins are authorized to see everything.</li>
<li>The plugin settings defines which columns are eligible for filtering preventing any attempt to filter on other columns.</li>
<li>As mentioned above, filters can be named and saved so that the filtering/sorting parameters are concealed from the end user.</li>
</ol>
<p><strong>Filtering Specification:</strong></p>
<p>The basic syntax of a filter is:&nbsp;<em>column-name=OP:value:sort-order</em> </p>
<p>this set can be repeated to specify multiple conditions.</p>
<p>The only required parameter is the <em>column</em>-<em>name </em>and one of the operands following the equal sign.</p>
<p>All parameters are AND conditions -- all filters must be satisfied to display a discussion on the list </p>
<p>Example: </p>
<p><code spellcheck="false">Prefix=EQ:Video&amp;InsertUserID=EQ:13&amp;CategoryID=EQ:6&amp;Name=::a </code></p>
<p><em>Prefix </em>is a column name (created by the&nbsp;<a href="https://open.vanillaforums.com/addon/prefixdiscussion-plugin" target="_blank" rel="noopener">Prefix Discussion</a>&nbsp;plugin).&nbsp;In this example the filtered list shows discussions with the prefix "Video" which were created by user 13 and saved in category 6, and the list is sorted by the discussion title (<em>name</em>) in ascending order. Clearly this need to know the user and category numbers is inconvenient and for that reason the plugin offers symbolic substitution that allows to filter on user and category names instead of their internal numbers.&nbsp;More about that later.</p>
<p>The full URL of that filter is:</p>
<p><code spellcheck="false"><em>your-forum/discussions/filterdiscussion?Prefix=EQ:Video&amp;InsertUserID=EQ:13&amp;CategoryID=EQ:6&amp;Name=::a</em></code></p>
<p>The plugin supports multiple conditions on the same column.&nbsp;For example, the following filter displays discussions with titles starting with the letters A through B:</p>
<p><code spellcheck="false">&amp;Name=GE:A:a&amp;Name=LE:B</code></p>
<p><em>Column-name</em> - a column name from the discussion table.&nbsp;Note that few plugins add additional columns to the discussion table and those are eligible for processing.</p>
<p><em>OP </em>- one of these comparison operators:&nbsp;EQ, NE, GT, LT, NL, NN (equal, not equal, greater than, less than, null and not null)</p>
<p><em>value </em>- the value to compare with.</p>
<p><em>sort-order</em> - either "a" for ascending or "d" for descending.</p>
<p><strong>Symbolic Substitution</strong></p>
<p>Three column names can be specified symbolically:</p>
<ul>
<li>{$category} in the column field indicates that the Value field contains the category name. Example: <em>{$category}=EQ:Sport</em> If the associated "Sport" CategoryID number is "5" then the example is equivalent to entering <code spellcheck="false"><em>CategoryID=EQ:5</em></code></li>
<li>{$insertusername} in the column field indicates that the Value field contains the user name of the user who created the discussion. Example: <em>{$insertusername}=EQ:Joe Doe </em>If the associated "Joe Doe" UserID number is "15" then the example is equivalent to entering<code spellcheck="false">&nbsp;<em>InsertUserID=EQ:15</em>&nbsp;</code>&nbsp;</li>
<li>{$updateuserrname} in the column field indicates that the Value field contains the user name of the user who last updated the discussion. Example:&nbsp;<code spellcheck="false">&nbsp;<em>{$updateuserrname}=EQ:Joe Doe&amp;CategoryID=2</em></code> If the associated "Joe Doe" UserID number is "15" then the example is equivalent to entering <code spellcheck="false"><em>UpdateUserID=EQ:15</em></code><em> </em></li>
</ul>
<p>Additionally these symbolic substitutions are also available in the <em>value </em>parameter:</p>
<ul>
<li>a positive or negative number can be specified in date column fields in the format -nnn or +nnn. For example: <code spellcheck="false">/discussions/FilterDiscussion/?DateInserted=EQ:0</code> can be used to filter on discussions created today and <code spellcheck="false">/discussions/FilterDiscussion/?DateInserted=EQ:-2</code> will display discussions created two days ago</li>
<li>{$userid} in the value field is substituted for the logged in UserID number. Example:&nbsp;<code spellcheck="false"><em>UpdateUserID=EQ:{$userid}</em></code> will show all discussions updated by own user.</li>
</ul>
<p><strong>Discussion List Title</strong></p>
<p>You can specify the discussion list title via the !msg= parameter.</p>
<p>Example: <code spellcheck="false"><em>InsertUserID=EQ:{$userid}&amp;!msg=My Discussions</em></code></p>
<p>The !msg parameter should be the last one specified.&nbsp;If it is omitted the plugin builds a message reflecting the filter.</p>
<p>The following symbolic parameters can be specified in the !msg parameter:</p>
<ul>
<li>{$userid} - for the current userid&nbsp;</li>
<li>{$username} - for the current username</li>
<li>{$rusername} - for the Referred username in the last specified&nbsp;InsertUserID&nbsp;or&nbsp;UpdateUserID&nbsp;parameter.</li>
</ul>
<p>Example:&nbsp;<code spellcheck="false"><em>InsertUserID=EQ:4&amp;!msg={$rusername} Discussions</em></code></p>
<p>Note: The plugin does not validate that the title makes sense - it is all up to you. </p>
<p><strong>Use Cases:</strong></p>
<ul>
<li>For administrators - to check on content without having to go to the SQL database</li>
<li>For administrators - to add menu options or side panel links for specialized views (see the&nbsp;<a href="https://open.vanillaforums.com/addon/addmenuitem-plugin" target="_blank" rel="noopener">Add Menu Item</a>&nbsp;and&nbsp;<a href="https://open.vanillaforums.com/addon/sidepanellinks-plugin" target="_blank" rel="noopener">Side Panel Links</a>&nbsp;plugins).</li>
<li>For developers - to link to filtered views from web pages (e.g. The userid field in a discussion can link to a filtered view that shows discussions by that userid)&nbsp;</li>
</ul>
<p>Note: You may have other plugins that use URL parameters and you will need to define them in the "ignore list" on&nbsp;the dashboard setting for FilterDiscussion plugin so that it will ignore them and won't throw an error for an undefined parameter.</p>
<p><strong>Change log:</strong></p>
<p>Version 1.2 - Added ignore list (ignored url parameters that may be used by other plugins/applications)</p>
<p>Version 1.3 - Added saved filters (to use a named parameter to invoke multiple filters while hiding the filters from the user)</p>
<p>Version 1.4 - Restructured internal functions, out of array index bug fix, increased number of defined filters</p>
<p>Version 1.5 - Added relative date filtering for discussion date columns (for example, DateInserted=d&gt;:-7 filters on discussions created in the last seven days)</p>
<p>Version 1.6 - Support for Vanilla 2.6.</p>
<p>- Support for using {$userid} in the value field to refer to one's own userid number.</p>
<p>- Support for using named categories.</p>
<p>- Support for including {username} and {category} in the title)</p>
<p>Version 1.8.2 - Support for Vanilla 2.8. </p>
<p> - Support for sorting fields (thanks to user donshakespeare).&nbsp;</p>
<p> - Support for filtering by explicit user names (rather than their internal numbers</p>
<p> - Support for multiple filters on the same column name</p>
<p> - Enhanced support for filtering with relative dates on date columns</p>