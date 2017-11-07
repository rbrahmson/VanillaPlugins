<?php if (!defined('APPLICATION')) exit(); ?>
<h1><?php echo 'Update Feed'; ?></h1>
<div class="AddFeed">
   <?php 
      echo $this->Form->Open(array(
         'action'  => Url('plugin/feeddiscussions/updatefeed')
      ));
      echo $this->Form->Errors();
      
      $Refreshments = array(
               "1m"  => T("Every Minute"),
               "5m"  => T("Every 5 Minutes"),
               "30m" => T("Twice Hourly"),
               "1h"  => T("Hourly"),
               "1d"  => T("Daily"),
               "3d"  => T("Every 3 Days"),
               "1w"  => T("Weekly"),
               "2w"  => T("Every 2 Weeks")
            );
      
   ?>
      <ul>
         <li>
            <div class="Info">Update a new Auto Discussion Feed</div>
         <?php
            echo $this->Form->Label('Feed URL', 'FeedURL');
            echo $this->Form->TextBox('FeedURL', array('class' => 'InputBox'));
         ?></li>
         <li><?php
            echo $this->Form->CheckBox('Historical', T('Import Older Posts'), array('value' => '1'));
         ?></li>
               
         <li><?php
            echo $this->Form->Label('Maximum Polling Frequency', 'Refresh');
            echo $this->Form->DropDown('Refresh', $Refreshments, array(
               'value'  => "1d"
            ));
         ?></li>
          <?php
			echo "<b>Filters</b><br>If specified a feed item title must match the following filters to be saved as a new discussion";
			echo "When specifying multiple words they ust be comma delimited.  The match is case insensitive.<br>";
            echo $this->Form->Label('OR  Filter:', 'Filter');
            echo $this->Form->TextBox('Filter', array('class' => 'InputBox')).'  (any matched word will satisfy the filter)<br>';
            echo $this->Form->Label('AND Filter:', 'AndFilter');
            echo $this->Form->TextBox('AndFilter', array('class' => 'InputBox')).'  (all words must match to satisfy the filter)<br>';
			echo "<b>Minimum Content</b><br>If specified a feed item body must contain a minimum number of words to be saved as a new discussion.<br>";
            echo $this->Form->Label('Minumum words:', 'Minwords');
            echo $this->Form->TextBox('Minwords', array('class' => 'InputBox')).'  (Use this to ignore mostly empty items)<br>';
         ?></li>
         <li><?php
            echo $this->Form->Label('Target Category', 'Category');
            echo $this->Form->CategoryDropDown('Category');
         ?></li>
      </ul>
   <?php
		echo $this->Form->button(t('Save'));
		echo $this->Form->button(t('Cancel')); 
		echo $this->Form->Close("update Feed");
   ?>
   
</div>
