<?php
/**
 * Plugin to display extra meta data in the Discussion Lists.
 *
 */
$PluginInfo['CivilTongueAlert'] = array(
    'Name' => 'CivilTongueAlert',
    'Description' => 'Plugin to alert users before they save discussion/comment with banned words',
    'Version' => '1.1',
    'RequiredApplications' => array('Vanilla' => '2.6'),
    'RequiredTheme' => false, 
    'RequiredPlugins' => 'CivilTongueEx',
    'MobileFriendly' => true,
    'HasLocale' => false,
    'usePopupSettings' => false,
    'SettingsPermission' => 'Garden.Settings.Manage',
    'Author' => "Roger Brahmson",
    'GitHub' => "https://github.com/rbrahmson/VanillaPlugins/tree/master/CivilTongueAlert",
    'License' => 'GPLv2'
);
/**
* Plugin to display extra meta data in the Discussion Lists.
*/
class CivilTongueAlertPlugin extends Gdn_Plugin {
/**
* Hook after validations and before discussion is saved.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return none
*/
    public function DiscussionModel_AfterValidateDiscussion_Handler($sender, $args) {
        $words = c('Plugins.CivilTongue.Words', null);
        if ($words === null) {
            return;
        }
        $pattern = '`\b' . implode('\b|\b', explode(';', $words)) . '\b`isu';;
        if (preg_match($pattern, $text, $results )) {
            $sender->Validation->addValidationResult('Body', t('Please don\'t use this word: "').$results[0] .'"');
        }
        $this->checkforbanned($sender, $args["FormPostValues"]["Name"] . ' ' . $args["FormPostValues"]["Body"]);  
    }
/**
* Hook after validations and before comment is saved.
*
* @param object $Sender standard
* @param array  $Args   standard
*
* @return none
*/
    public function CommentModel_AfterValidateComment_Handler($sender, $args) {
        $this->DiscussionModel_AfterValidateDiscussion_Handler($sender, $args);   
    }
/**
* Function to validate content does not contain banned words.
*
* @param object $Sender standard
* @param array  $text   text to verify
*
* @return none
*/
    public function checkforbanned($sender, $text) {
        $words = c('Plugins.CivilTongue.Words', null);
        if ($words === null) {
            return;
        }
        $pattern = '`\b' . implode('\b|\b', explode(';', $words)) . '\b`isu';;
        if (preg_match($pattern, $text, $results )) {
            $sender->Validation->addValidationResult('Body', 'Please don\'t use this word: "'.$results[0] .'"');
        }    
    }
    
}