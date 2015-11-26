==== TrustYou-Integration-With-JReview ====

In order to integrate TrustYou with JReview, please follow the given instruction:-

1> Put the jreviews_overrides folder in templates folder and rename trustyoutheme folder of jreviews_overrides->views->themes with the active template name.

2> After putting the jreviews_overrides folder, put the trustscore folder in root directory.

3> create a table
	CREATE TABLE IF NOT EXISTS `jos_jreview_listing_trustyou` (
	  `id` int(11) NOT NULL AUTO_INCREMENT,
	  `lid` int(11) NOT NULL,
	  `trustyou_id` varchar(255) NOT NULL,
	  `reviews_count` int(11) NOT NULL,
	  `sources_count` int(11) NOT NULL,
	  `score_description` varchar(255) NOT NULL,
	  `score` int(11) NOT NULL,
	  `date` varchar(255) NOT NULL,
	  PRIMARY KEY (`id`)
	) ENGINE=InnoDB  DEFAULT CHARSET=latin1;

4> Import the TrustScore and then generate TrustScore and after that you can export the TrustScore.

5>In order to import TrustScore, run the given url:-
  	http://hostname/trustscore/trustyouidimport.php

6>In order to generate TrustScore, run the given url:-
	http://hostname/trustscore/trustscoregenerate.php
	
7>In order to export TrustScore, run the given url:-
  	http://hostname/trustscore/listingexport.php	
	
