<?php
 	require_once("Rest.inc.php");
	
	class API extends REST {
	
		public $data = "";
		public $url='http://api.trustyou.com/bulk';
		public $method='POST';
		public $key='put the key here';
		public $result='';	
		
		/*
		 * Dynmically call the method based on the query string
		 */
		public function processApi($data){
			$this->inputs();
			$this->data=$data.'&key='.$this->key;
			$this->result=$this->CallAPI($this->method,$data);
			return $this->response(json_decode($this->result,true));
		}
		
		// it is used for request and response of rest webservice
				
		
		public function CallAPI($method,  $data = false){
			$curl = curl_init();
			switch ($method)
			{
				case "POST":
					curl_setopt($curl, CURLOPT_POST, 1);
		
					if ($data)
						curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
					break;
				case "PUT":
					curl_setopt($curl, CURLOPT_PUT, 1);
					break;
				default:
					if ($data)
						$url = sprintf("%s?%s", $url, http_build_query($data));
			}
		
			// Optional Authentication:
			curl_setopt($curl, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
			//curl_setopt($curl, CURLOPT_USERPWD, $this->username.":".$this->password);
		
			curl_setopt($curl, CURLOPT_URL, $this->url);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		
			$result = curl_exec($curl);
		
			curl_close($curl);
			return $result;
		}
		/*
		 *	Encode array into JSON
		*/
		private function json($data){
			if(is_array($data)){
				return json_encode($data);
			}
		}
	}
?>