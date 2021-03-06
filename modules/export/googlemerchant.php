<?php 

/**
 * The AcquistoShop extension allows the creation a OnlineStore width 
 * the OpenSource Contao CMS. For Question visit our Website under
 * http://www.contao-acquisto.de  
 *
 * PHP version 5
 * @package	   AcquistoShop
 * @subpackage Export
 * @author     Sascha Brandhoff <brandhoff@contao-acquisto.de>
 * @copyright  AcquistoShop
 * @license    LGPL.
 * @filesource
 */
 
class googlemerchant extends \Controller {
    protected $exportModule = 'Google Shopping';
    public $exportID;

    public function __construct()
    {
#        parent::__construct();
    }

    public function getExportInfo() {
        return $this->exportModule;
    }

    public function getSettingsData() {
        $arrConfig = array(
            'Seitentitel' => array
            (
                'label'                   => array('Seitentitel'),
                'inputType'               => 'text',
                'search'                  => true,
                'eval'                    => array('mandatory'=>true, 'maxlength'=>64, 'tl_class'=>'w50')
            ),
            'Beschreibung' => array
            (
                'label'                   => array('Beschreibung'),
                'inputType'               => 'text',
                'search'                  => true,
                'eval'                    => array('mandatory'=>true, 'maxlength'=>255, 'tl_class'=>'w50')
            )
        );

        return $arrConfig;
    }

    public function compile()
    {
        $this->Import('Database');
        $this->Import('Environment');
        $this->Import('acquistoShop', 'Shop');

        $objExport = $this->Database->prepare("SELECT * FROM tl_shop_export WHERE id = ?")->limit(1)->execute($this->exportID);
        $objPage = $this->Database->prepare("SELECT id, alias FROM tl_page WHERE id=?")->limit(1)->execute($objExport->produktDetails);
        $strUrl  = $this->generateFrontendUrl($objPage->fetchAssoc(), '/produkt/%s');

        $arrSettings = unserialize($objExport->exportSettings);

        $htmlData .= "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
        $htmlData .= "<rss version=\"2.0\" xmlns:g=\"http://base.google.com/ns/1.0\">\n";
        $htmlData .= "    <channel>\n";
        $htmlData .= "        <title>" . $arrSettings['Seitentitel'] . "</title>\n";
        $htmlData .= "        <description>" . $arrSettings['Beschreibung'] . "</description>\n";
        $htmlData .= "        <link>http://" . $this->Environment->httpHost . "</link>\n";

        $objProdukte = $this->Database->prepare("SELECT * FROM tl_shop_produkte;")->execute();
        while($objProdukte->next()) {

            $htmlData .= "        <item>\n";
            $htmlData .= "            <title><![CDATA[" . $objProdukte->bezeichnung . "]]></title>\n";
            $htmlData .= "            <link>http://" . $this->Environment->httpHost . "/" . sprintf($strUrl, $objProdukte->alias) . "</link>\n";
            $htmlData .= "            <description><![CDATA[" . $objProdukte->teaser . "]]></description>\n";
            $htmlData .= "            <g:id>" . $objProdukte->id . "</g:id>\n";
            $htmlData .= "            <g:condition>" . $objProdukte->zustand . "</g:condition>\n";
            $htmlData .= "            <g:preis>" . str_replace(".", ",", $this->Shop->getProductPrice($objProdukte->id, 0)) . " " . $GLOBALS['TL_CONFIG']['currency_symbol'] . "</g:preis>\n";
            $htmlData .= "            <g:availability>in stock</g:availability>\n";

            if($objProdukte->preview_image) {
                $objFile = FilesModel::findByPk($Produkt->preview_image);
                $htmlData .= "            <g:image_link>http://" . $this->Environment->httpHost . "/" . $objFile->path . "</g:image_link>\n";
            }

            $objVersandzonen = $this->Database->prepare("SELECT * FROM tl_shop_versandzonen")->execute();
            while($objVersandzonen->next()) {
                $objVersandarten = $this->Database->prepare("SELECT * FROM tl_shop_versandzonen_varten WHERE pid=? GROUP BY zahlungsart_id ORDER BY ab_einkaufpreis	ASC")->execute($objVersandzonen->id);
                while($objVersandarten->next()) {
                    $objZahlungsart = $this->Database->prepare("SELECT * FROM tl_shop_zahlungsarten WHERE id=?")->execute($objVersandarten->zahlungsart_id);

                    foreach(($dataCountry = unserialize($objVersandzonen->laender)) as $item) {
                        $htmlData .= "            <g:shipping>\n";
                        $htmlData .= "               <g:country>" . $item . "</g:country>\n";
                        $htmlData .= "               <g:service>" . $objZahlungsart->bezeichnung . "</g:service>\n";
                        $htmlData .= "               <g:price>" . substr(str_replace(".", ",", $objVersandarten->preis + 0.001), 0, -1) . " " . $GLOBALS['TL_CONFIG']['currency_symbol'] . "</g:price>\n";
                        $htmlData .= "            </g:shipping>\n";
                    }
                }
            }

            $htmlData .= "        </item>\n";
        }

        $htmlData .= "    </channel>\n";
        $htmlData .= "</rss>\n";

        return $htmlData;
    }
}


?>