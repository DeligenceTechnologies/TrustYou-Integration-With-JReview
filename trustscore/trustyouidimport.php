<?php
define('_JEXEC', 1);
ini_set('max_execution_time',500);
class TrustYouIDStoreInDB{
	private $db='';
	private $path= '/trustscore/import/';
	private $fileName='listimport.csv';
	private $trust_you_id=array();
	
	
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
	
	function parseCSVFile(){
		$i=0;
		if(file_exists(JPATH_ROOT.$this->path.$this->fileName)){
			$fp=fopen(JPATH_ROOT.$this->path.$this->fileName,"r");
			while(!feof($fp)){
				$line_of_text = fgetcsv($fp,999999,",");
				if($i != 0 and $line_of_text[0] != '' and $line_of_text[1] != '' and $line_of_text[2] != ''){
					$query="select #__content.id as listingId, #__jreview_listing_trustyou.*  from #__content LEFT JOIN #__jreview_listing_trustyou ON #__content.id = #__jreview_listing_trustyou.lid where #__content.id=$line_of_text[1]";
					$this->db->setQuery($query);
					$listing=$this->db->loadAssoc();
					if(count($listing) > 0){
						if($listing['id'] == '' and $listing['lid'] == '' and $listing['trustyou_id'] == ''){
							$query="insert into #__jreview_listing_trustyou set lid=".$listing['listingId'].", trustyou_id='".$line_of_text[0]."'";
							$this->db->setQuery($query);
							$this->db->execute();
							array_push($this->trust_you_id,$line_of_text[0]);
						} elseif($listing['trustyou_id'] != $line_of_text[0]){
							$query="update #__jreview_listing_trustyou set  trustyou_id='".$line_of_text[0]."',reviews_count=0, sources_count=0, score_description='', score=0, date='' where id =".$listing['id'];
							$this->db->setQuery($query);
							$this->db->execute();
							array_push($this->trust_you_id,$line_of_text[0]);
						}
					}
				}
				$i++;
			}
			fclose($fp);	
			echo "TrustYou id has been imported successfully.";
			if(count($this->trust_you_id) > 0){
				echo "<pre>";
				print_r($this->trust_you_id);
				echo "</pre>";
			}
		} else {
			echo $this->fileName." file does not exist.";	
			
		}
	}
	
	function storeTrustYouId(){
		$this->parseCSVFile();
	}
	
}
$trustYouIDStoreInDB=new TrustYouIDStoreInDB();
$trustYouIDStoreInDB->storeTrustYouId();
?>