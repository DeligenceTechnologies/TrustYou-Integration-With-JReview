<?php
define('_JEXEC', 1);
ini_set('max_execution_time',500);
include_once 'Rest.inc.php';
include_once 'api.php';

class TrustScoreWidget extends API{
	private $db='';
	private $days=7;
	private $noOfReq=100;
	private $requestList=array();
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
	
	function createTrustScore(){
		$this->createRequestList();
		for($i=0; $i < count($this->requestList); $i++){
			$reqList=implode(",",$this->requestList[$i]);
			$reqList='request_list=['.$reqList.']';
			$resList=$this->processApi($reqList);
			$resList=$this->parseTrustScorewidget($resList);
			
			for($j=0; $j < count($resList); $j++){
				$query="update #__jreview_listing_trustyou set reviews_count='".$resList[$j]['reviews_count']."',sources_count='".$resList[$j]['sources_count']."',score_description='".$resList[$j]['score_description']."',score='".$resList[$j]['score']."',date='".time()."' where trustyou_id='".$resList[$j]['ty_id']."'";
				$this->db->setQuery($query);
				$this->db->execute();
				array_push($this->trust_you_id,$resList[$j]['ty_id']);
			}
			
		}
		echo "\n";
		echo "Trust Score has been generated successfully.";
		if(count($this->trust_you_id) > 0){
		echo "\n\n";
		print_r($this->trust_you_id);
		}
		
	}
	
	function createRequestList(){
		$noOfSecond=$this->days*24*60*60;
		$todayTime=time();
		$query="SELECT #__jreview_listing_trustyou.*   FROM #__content AS Listing USE KEY (jr_created) LEFT JOIN #__jreviews_listing_totals AS Totals ON Totals.listing_id = Listing.id AND Totals.extension = 'com_content' LEFT JOIN #__jreviews_content AS Field ON Field.contentid = Listing.id LEFT JOIN #__jreviews_categories AS JreviewsCategory ON JreviewsCategory.id = Listing.catid AND JreviewsCategory.`option` = 'com_content' LEFT JOIN #__categories AS Category ON Category.id = Listing.catid AND Category.extension = 'com_content' LEFT JOIN #__jreviews_directories AS Directory ON Directory.id = JreviewsCategory.dirid LEFT JOIN #__users AS User ON User.id = Listing.created_by LEFT JOIN #__jreviews_claims AS Claim ON Claim.listing_id = Listing.id AND Claim.user_id = Listing.created_by AND Claim.approved = 1 LEFT JOIN #__viewlevels AS ViewLevel ON ViewLevel.id = Listing.access INNER JOIN #__jreview_listing_trustyou ON Listing.id = #__jreview_listing_trustyou.lid   WHERE  ( Listing.catid IN (SELECT id FROM #__jreviews_categories WHERE `option` = 'com_content') ) and #__jreview_listing_trustyou.trustyou_id != '' and ($todayTime - #__jreview_listing_trustyou.date) >= $noOfSecond order by #__jreview_listing_trustyou.id";
		$this->db->setQuery($query);
		$listing=$this->db->loadAssocList();
		$k=0;
		
		for($i=0; $i < count($listing); $i++){
			
			for($j=0; $j < $this->noOfReq; $j++){
				if(!isset($listing[$i]))break;
				$reqStr='"/hotels/'.$listing[$i]['trustyou_id'].'/seal.json"';
				$this->requestList[$k][$j]=$reqStr;	
				if($j != ( $this->noOfReq-1))$i++;
			}
			
			$k++;
    	}
	}
	
	function parseTrustScorewidget($req){
		$res=array();
		if($req['meta']['code'] == 200){
			for($i=0; $i < count($req['response']['response_list']); $i++){
				if($req['response']['response_list'][$i]['meta']['code'] == 200){
					$res[$i]=$req['response']['response_list'][$i]['response'];
				} 
			}
		} else {
			$res['error']=$req['meta']['error'];
		}
		return $res;
	}
	
	
}
$trustScoreWidget=new TrustScoreWidget();
$trustScoreWidget->createTrustScore();

?>