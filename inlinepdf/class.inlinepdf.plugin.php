<?php

$PluginInfo['inlinepdf'] = [
    'Name' => 'inlinepdf',
    'Description' => 'Plugin that (on supporting browsers) takes a referened PDF url from the discussion body and displays it inline.',
    'Version' => '1.1.1',
    'RequiredApplications' => ['Vanilla' => '2.6'],
    'MobileFriendly' => true,
    'HasLocale' => true,
    'Author' => 'RB',
    'SettingsUrl' => '/dashboard/settings/inlinepdf',
    'SettingsPermission' => 'Vanilla.inlinepdf.Manage',
    'License' => 'MIT'
];

/**
 * inlinepdf allows users to embed PDF url for embedded viewing.
 */
class inlinepdfPlugin extends Gdn_Plugin {
    /**
     *
     */
    public function setup() {
        touchConfig('Plugins.inlinepdf.maxviewers',1);
        touchConfig('Plugins.inlinepdf.height','700px');
    }
    /**
     * 
     */
    public function settingsController_inlinepdf_create($sender) {
        $sender->permission('Garden.Settings.Manage');
        $sender->title(t('Inline PDF settings'));
        $sender->addSideMenu('dashboard/settings/plugins');
        if ($sender->Form->authenticatedPostBack()) {
            $formPostValues = $sender->Form->formValues();
            $maxviewers = $formPostValues['maxviewers'];
            $height = $formPostValues['height'];
            //
        } else {
            $maxviewers = c('Plugins.inlinepdf.maxviewers', 1);
            $height = c('Plugins.inlinepdf.height','700px');
            $PastEventsCategory = c('EventCalendar.PastEventsCategory', '');
            $sender->Form->setValue('maxviewers', $maxviewers);
            $sender->Form->setValue('height', $height);
        }
        $sender->setData('Schema', [
            'maxviewers' => [
                'Control' => 'Textbox',
                'LabelCode' => 'maxviewers',
                'Description' => 'Number of inline PDFs to show ',
                'Options' => ['ValueField' => 'maxviewers', 'TextField' => 'Max Inline PDF Viewers']
            ],
            'height' => [
                'Control' => 'Textbox',
                'LabelCode' => 'height',
                'Description' => 'Height of PDF viewer box ',
                'Options' => ['ValueField' => 'v', 'TextField' => 'Height']
            ],
        ]);

        $sender->render('settings', '', 'plugins/EventCalendar');
    }
    //
    private function addjs($sender) {       
        $Isadmin = checkPermission('Garden.Settings.Manage');
        //if (!$Isadmin) return;
        $Discussion = $sender->EventArguments['Discussion'];
        if (!$Discussion) return;
        $DiscussionID = $Discussion->DiscussionID;
        $sender->addDefinition('DiscussionID', $DiscussionID);    
        $maxviewers = (int) c('Plugins.inlinepdf.maxviewers',1); 
        $sender->addDefinition('maxviewers', $maxviewers); 
        $height = c('Plugins.inlinepdf.height',"700px");
        $sender->addDefinition('height', $height);    
        echo <<< EOT
        <script Type="text/javascript">
        jQuery(document).ready(function ($) {
            var maxviewers = gdn.definition("maxviewers");
            var DiscussionID = gdn.definition("DiscussionID");
            var height = gdn.definition("height");
            ID = "#Discussion_" + DiscussionID + " a[href$='.pdf']"; /*Use this to include attachments*/
            ID =  "div.Message.userContent a[href$='.pdf']";
            console.log(height);
            console.log(maxviewers);
            console.log(ID);
            var count = 0;
            console.log(count);
            $(ID).each(function(){
                console.log(count);
                ++count;
                var href = $(this).attr('href'); 
                console.log(href);
                if($(this).children().length < 1) {
                    var index = $(this).index();
                    if (count <= maxviewers) {
                        $(this).attr('id', 'inlinepdf'+count);
                        var options = {
                            height: height,
                            id: "inlinepdfa"+count,
                            pdfOpenParams: {
                                view: 'Fith',
                                navpanes: 0,
                                toolbar: 0,
                                statusbar: 0
                            }
                        };
                        var myPDF = PDFObject.embed(href, '#inlinepdf' +count, options);
                        $('#inlinepdfa'+count).addClass("inlinepdf").attr("href", href).attr('target','_blank');
                        $('#inlinepdf'+count).attr('target','_blank');
                    }
                }
            });
        })
      </script>
EOT;
          
    }
    /**
     */
    public function discussionController_beforeDiscussionRender_handler($sender) {
        $sender->addCssFile('inlinepdf.css', 'plugins/inlinepdf');
        $sender->addJsFile('pdfobject.js', 'plugins/inlinepdf');
    }
    /**
     * Add prefix to discussions lists.
    */
    public function DiscussionController_AfterDiscussion_Handler($sender) {
        $this->addjs($sender);
    }
}
