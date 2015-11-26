<?php
define('_JEXEC', 1);
ini_set('max_execution_time',500);
class ListingExport{
	private $db='';
	private $path= '/trustscore/export/';
	private $fileName='listingexport.csv';
	
	function __construct(){
		if (file_exists('../defines.php')){	
			include_once '../defines.php';
		}
		if (!defined('_JDEFINES')){
			define('JPATH_BASE', '..');
			require_once '../includes/defines.php';
		}
		require_once '../includes/framework.php';
		$this->db = JFactory::getDbo();
	}
	
	function createCSVFile(){
		$fp=fopen(JPATH_ROOT.$this->path.$this->fileName,"w");
		$headingArray=array("TrustYou ID","ID", "Name","Latitude", "Longitude", "Street", "Zip", "City", "State","Country", "Country ISO", "Address", "Phone", "External ID", "External Domain", "GIATA ID", "IFF ID");
		fputcsv($fp,$headingArray);
		
		$query="SELECT Listing.id, Listing.title, Field.jr_latitude, Field.jr_longitude, Field.jr_country, Field.jr_state, Field.jr_city, Field.jr_postalcode, Field.jr_address, Field.jr_phonenumber   FROM #__content AS Listing USE KEY (jr_created) LEFT JOIN #__jreviews_listing_totals AS Totals ON Totals.listing_id = Listing.id AND Totals.extension = 'com_content' INNER JOIN #__jreviews_content AS Field ON Field.contentid = Listing.id LEFT JOIN #__jreviews_categories AS JreviewsCategory ON JreviewsCategory.id = Listing.catid AND JreviewsCategory.`option` = 'com_content' LEFT JOIN #__categories AS Category ON Category.id = Listing.catid AND Category.extension = 'com_content' LEFT JOIN #__jreviews_directories AS Directory ON Directory.id = JreviewsCategory.dirid LEFT JOIN #__users AS User ON User.id = Listing.created_by LEFT JOIN #__jreviews_claims AS Claim ON Claim.listing_id = Listing.id AND Claim.user_id = Listing.created_by AND Claim.approved = 1 LEFT JOIN #__viewlevels AS ViewLevel ON ViewLevel.id = Listing.access LEFT JOIN #__jreview_listing_trustyou ON Listing.id = #__jreview_listing_trustyou.lid   WHERE  ( Listing.catid IN (SELECT id FROM #__jreviews_categories WHERE `option` = 'com_content') ) and  ISNULL(#__jreview_listing_trustyou.trustyou_id) order by Listing.id";
		$this->db->setQuery($query);
		$listing=$this->db->loadAssocList();
		for($i=0; $i < count($listing); $i++){
			$TrustYouID='';
			$ID=$listing[$i]['id'];
			$Name=str_replace('*','',$listing[$i]['title']);
			$Latitude=str_replace('*','',$listing[$i]['jr_latitude']);
			$Longitude=str_replace('*','',$listing[$i]['jr_longitude']);
			$Street=str_replace('*','',$listing[$i]['jr_address']);
			$Zip=str_replace('*','',$listing[$i]['jr_postalcode']);
			$City=str_replace('*','',$listing[$i]['jr_city']);
			$State=str_replace('*','',$listing[$i]['jr_state']);
			
			$query="select * from #__3166_1 where iso='".$listing[$i]['jr_country']."' or name ='".$listing[$i]['jr_country']."'";
			$this->db->setQuery($query);
			$country=$this->db->loadAssoc();
			if(count($country) > 0){
				$Country=str_replace('*','',$country['printable_name']);
				$CountryISO=str_replace('*','',$country['iso']);
			}
			$Address=str_replace('*','',$listing[$i]['jr_address']);
			$Phone=str_replace('*','',$listing[$i]['jr_phonenumber']);
			$ExternalID='';
			$ExternalDomain='';
			$GIATAID='';
			$IFFID='';
			
			$headingArray=array($TrustYouID,$ID, $Name,$Latitude, $Longitude, $Street, $Zip, $City, $State,$Country, $CountryISO, $Address, $Phone, $ExternalID, $ExternalDomain, $GIATAID, $IFFID);
		fputcsv($fp,$headingArray);
			
		}
		$this->fileDownload();
	}
	
	function fileDownload(){
		$fp=fopen(JPATH_ROOT.$this->path.$this->fileName,"r");
		$fsize = filesize(JPATH_ROOT.$this->path.$this->fileName);
		header("Content-type: application/octet-stream");
        header("Content-Disposition: filename=\"".$this->fileName."\"");
		header("Content-length: $fsize");
   		header("Cache-control: private"); 
    	while(!feof($fp)) {
           $buffer = fread($fp, 2048);
           echo $buffer;
    	}
		fclose ($fp);
		exit;
	}
	
	
	function exportCSVFile(){
		$this->createCSVFile();
	}
	
}

$listingExport=new ListingExport();
$listingExport->exportCSVFile();
?>