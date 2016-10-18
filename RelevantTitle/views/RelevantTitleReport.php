<?php
/**
 * RelevantTitle plugin Report Wrapper.
 *
 */
echo $this->Form->Open();
echo $this->Form->Errors();
//
$Report = new RelevantTitlePlugin($Sender);
//
$Report->controller_relevanttitlereport($Sender);
//
echo '</div>';
