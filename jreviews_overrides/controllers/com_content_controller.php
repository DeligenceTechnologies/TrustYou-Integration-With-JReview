<?php
/**
 * JReviews - Reviews Extension
 * Copyright (C) 2010-2015 ClickFWD LLC
 * This is not free software, do not distribute it.
 * For licencing information visit https://www.jreviews.com
 * or contact sales@jreviews.com
**/

defined( 'MVC_FRAMEWORK') or die( 'Direct Access to this location is not allowed.' );

class ComContentController extends MyController {

    var $uses = array('user','menu','criteria','directory','field','media','favorite','review','category','vote');

    var $helpers = array('assets','routes','libraries','html','form','text','time','jreviews','media','custom_fields','rating','community','widgets');

    var $components = array('config','access','everywhere','media_storage');

    var $autoRender = false; //Output is returned

    var $autoLayout = true;

    var $listingResults;

    var $formTokenKeys = array('id'=>'review_id','pid'=>'listing_id','mode'=>'extension','criteria_id'=>'criteria_id');

    function beforeFilter()
    {
         # Call beforeFilter of MyController parent class
        parent::beforeFilter();

        # Make configuration available in models
        $this->Listing->Config = &$this->Config;
    }

    function afterFilter()
    {
        if(isset($this->review_fields))
        {
            $Assets = ClassRegistry::getClass('AssetsHelper');
            $Assets->assetParams['review_fields'] = $this->review_fields;
            $Assets->assetParams['owner_id'] = $this->owner_id;
            unset($this->review_fields);
        }
        parent::afterFilter();
    }

    // Need to return object by reference for PHP4
    function &getPluginModel()
    {
        return $this->Listing;
    }

    // Need to return object by reference for PHP4
    function &getObserverModel()
    {
        return $this->Listing;
    }

    function com_content_view($passedArgs)
    {
        $this->layout = 'detail';

        $empty_content_row = new stdClass;

        $content_row = Sanitize::getVar($passedArgs,'row',$empty_content_row);

        $content_params = Sanitize::getVar($passedArgs,'params');

        $preview = Sanitize::getBool($passedArgs,'preview');

        $editor_review = array();

        $reviews  = array();

        $crumbs = array();

        $listing_id = Sanitize::getInt($content_row,'id',Sanitize::getInt($this->params,'id'));

        # Check if item category is configured for jreviews

        if(!$preview && !$this->Category->isJreviewsCategory($content_row->catid))
        {
            return array('row'=>$content_row,'params'=>$content_params);
        }

        # Override content page parameter settings

        if($content_params && method_exists($content_row->params, 'set')) {

            $content_row->params->set('access-edit',0);
            $content_row->params->set('show_title',0);
            $content_row->params->set('show_category',0);
            $content_row->params->set('show_author',0);
            $content_row->params->set('show_create_date',0);
            $content_row->params->set('show_publish_date',0);
            $content_row->params->set('show_modify_date',0);
            $content_row->params->set('show_page_title',0); // J1.5.4+
            $content_row->params->set('show_hits',0);
            $content_row->params->set('show_parent_category',0);

            /* Place holder in case it ever gets fixed */
            $content_row->params->set('show_page_heading',0);

            if(is_object($content_params) && method_exists($content_params,'set'))
            {
                /* For some reason setting it on $content_row does not work*/
                $content_params->set('show_page_heading',0);
            }
        }

        # Get listing and review summary data

        $this->Listing->controller = $this->name;

        $this->Listing->action = $this->action;

        // Need to query the listing even if view cache enabled because otherwise there's no way to set breadcrumbs and meta data in the content plugin
		
		//Added by santosh
		 $table_alias = 'ListingTrustYou';
         $this->Listing->joins[$table_alias] = "LEFT JOIN #__jreview_listing_trustyou AS ".$table_alias." ON Listing.id = " . $table_alias . ".lid";
		 
		 array_push($this->Listing->fields,'ListingTrustYou.trustyou_id as trustyou_id');
		 array_push($this->Listing->fields,'ListingTrustYou.reviews_count as trustyou_reviews_count');
		 array_push($this->Listing->fields,'ListingTrustYou.sources_count as trustyou_sources_count');
		 array_push($this->Listing->fields,'ListingTrustYou.score_description as trustyou_score_description');
		 array_push($this->Listing->fields,'ListingTrustYou.score as trustyou_score');
		 //End of santosh

        $listing = $this->Listing->findRow(array(
            'cache'=>!((bool) $this->_user->id),
            'conditions'=>array(
                'Listing.' . EverywhereComContentModel::_LISTING_ID . ' = '. $listing_id
                )
            )
        );

        if(!$listing)
        {
            return cmsFramework::noAccess(true);
        }

        // Joomla does this by itself

        if(_CMS_NAME == 'wordpress')
        {
            $expired = $listing['Listing']['publish_down'] != NULL_DATE && strtotime($listing['Listing']['publish_down']) < strtotime(_CURRENT_SERVER_TIME);

            if($expired && !$this->Access->isEditor())
            {
                return cmsFramework::noAccess(true);
            }
        }

        # Escape quotes in meta tags

        $metadesc = Sanitize::getString($listing['Listing'],'metadesc');

        $metakey = Sanitize::getString($listing['Listing'],'metakey');

        $metadesc and $content_row->metadesc = htmlspecialchars($metadesc,ENT_QUOTES,'UTF-8');

        $metakey and $content_row->metakey = htmlspecialchars($metakey,ENT_QUOTES,'UTF-8');

        // Access check for preview mode - display only for unpublished listings when the registered user is the owner and for editors and above
        if($preview && $listing['Listing']['state'] > 0
            &&
            (NULL_DATE == $listing['Listing']['publish_down'] || strtotime($listing['Listing']['publish_down']) >= strtotime(_TODAY))
            &&
            (NULL_DATE == $listing['Listing']['publish_up'] || strtotime($listing['Listing']['publish_up']) <= strtotime(_END_OF_TODAY))
        ) {

            // Listing is published so we redirect to the published url

            $url = cmsFramework::makeAbsUrl($listing['Listing']['url'],array('sef'=>true));

            cmsFramework::redirect($url);

            exit;
        }
        elseif ($preview &&
                ($listing['User']['user_id'] == 0 || $this->_user->id != $listing['User']['user_id']) &&
                !$this->Access->isEditor()
        ) {

            // Preview mode, but listing owner is guest, or registered user is not the listing owner and it's also not a Joomla editor or above

            return cmsFramework::noAccess(true);
        }

        $cat_id = Sanitize::getInt($content_row,'catid',$listing['Category']['cat_id']);

        $text = Sanitize::getString($content_row,'text',$listing['Listing']['summary'].$listing['Listing']['description']);

        if($preview && class_exists('JHTML')) {

            $text = JHTML::_('content.prepare', $text);
        }

        /**
         * Stop article fulltext image from displaying if it is being used for syncing the main media
         */

        if(_CMS_NAME == 'joomla')
        {
            $sync_image = Sanitize::getString($this->Config,'media_article_sync');

            $media_article_hide = Sanitize::getString($this->Config,'media_article_hide');

            if(is_object($content_row))
            {
                $content_images = json_decode($content_row->images,true);

                switch($media_article_hide)
                {
                    case 'all':

                        $content_images['image_intro'] = '';

                        $content_images['image_fulltext'] = '';

                        break;

                    case 'image_intro':
                    case 'image_fulltext':

                        $content_images[$media_article_hide] = '';

                        break;

                    default:
                        break;
                }

                $content_row->images = json_encode($content_images);
            }

            $this->Media->updateListingCoreImage($listing);
        }

        # Set the theme suffix  - $parent_categories also used for J16 breadcrumb
        $parent_categories = $this->Category->findParents($cat_id);

        $this->Theming->setSuffix(array('categories'=>$parent_categories));

        # These should be moved to the s2framework model to unset automatically after every query so that there's no confusion when the model is used
        # in modules, plugins, etc.
        unset($this->Listing->controller);

        unset($this->Listing->action);

        # Override global configuration
        isset($listing['ListingType']) and $this->Config->override($listing['ListingType']['config']);

        // Override CMS breadcrumbs
        S2App::import('Helper','routes');

        $Routes = ClassRegistry::getClass('RoutesHelper');

        $Routes->Config = $this->Config;

        $Routes->params = $this->params;

        $this->Config->breadcrumb_detail_directory and $crumbs[] = array('name'=>$listing['Directory']['title'],'link'=>$Routes->directory($listing,array('return_url'=>true)));

        # Generate crumbs
        while($cat = array_shift($parent_categories))
        {
            $crumbs[] = array('name'=>$cat['Category']['title'],'link'=>$Routes->category($cat,array('return_url'=>true)));
        }

        $crumbs[] = array('name'=>$listing['Listing']['title'],'link'=>'');

        $this->set(array('listing'=>$listing,'crumbs'=>$crumbs));

        if(!$this->Config->breadcrumb_detail_override) $crumbs = array();

        # Get cached vesion
        if(!$preview && $this->_user->id === 0)
        {
            $page = $this->cached($this->here . '.detail');

            if($page) {

                $content_row->text = $page;

                return array('row'=>$content_row,'params'=>$content_params,'listing'=>$listing,'crumbs'=>$crumbs);
            }
        }

        $this->owner_id = $listing['Listing']['user_id']; // Used in AssetsHelper

        // Check if the listing has any html tags, and if it does, then strip the double /r/r added by J1.5, otherwise it is
        // required for proper spacing of summary and description fields

        if(preg_match('/(<\w+)(\s*[^>]*)(>)/',$text)) {

            $listing['Listing']['text'] = str_replace("\r",'',$text); // Eliminates double break between summary and description
        }
        else {

            $listing['Listing']['text'] = $text;
        }

        # Get editor review data

        if ($this->Config->author_review)
        {
             $conditions = array(
                'Review.pid = '. $listing['Listing']['listing_id'],
                'Review.mode = ' . $this->Quote('com_content'),
                'Review.published = 1',
                'Review.author = 1',
                'JreviewsCategory.option = ' . $this->Quote('com_content')
            );

            $queryData = array(
                'conditions'=>$conditions,
                'offset'=>0,
                'limit'=>$this->Config->editor_limit,
                'order'=>array($this->Review->processSorting())
            );

            $editor_review = $this->Review->findAll($queryData);
        }

        # Ger user review data
        if($this->Config->user_reviews)
        {
            $fields = array(
                'Review.owner_reply_approved As `Review.owner_reply_approved`',
                'Review.owner_reply_text As `Review.owner_reply_text`'
            );

            $conditions = array(
                'Review.pid = '. $listing['Listing']['listing_id'],
                'Review.mode = ' . $this->Quote('com_content'),
                'Review.published = 1',
                'Review.author = 0',
                'JreviewsCategory.option = ' . $this->Quote('com_content')
            );

            $queryData = array
            (
                'fields'=>$fields,
                'conditions'=>$conditions,
                'offset'=>0,
                'limit'=>$this->Config->user_limit,
                'order'=>array($this->Review->processSorting($this->Config->user_review_order))
            );

            $reviews = $this->Review->findAll($queryData);
        }

        # Get custom fields for review form if form is shown on page

        $review_fields = $this->review_fields = $this->Field->getFieldsArrayNew($listing['Criteria']['criteria_id'], 'review');

        # Initialize review array and set Criteria and extension keys

        $review = $this->Review->init();

        $review['Criteria'] = $listing['Criteria'];

        $review['Review']['extension'] = $listing['Listing']['extension'];

        # Get current listing review count for logged in user

        $listing['User']['user_review_count'] = $this->_user->id == 0 ? 0 : $this->Review->findCount(array(
                'conditions'=>array(
                    'Review.pid = '.$listing_id,
                    "Review.mode = " . $this->Quote($listing['Listing']['extension']),
                    "Review.published >= 0",
                    "Review.author = 0",
                    "Review.userid = " . (int) $this->_user->id
                )));

        $listing['User']['editor_review_count'] = $this->_user->id == 0 ? 0 : $this->Review->findCount(array(
                'conditions'=>array(
                    'Review.pid = '.$listing_id,
                    "Review.mode = " . $this->Quote($listing['Listing']['extension']),
                    "Review.published >= 0",
                    "Review.author = 1",
                    "Review.userid = " . (int) $this->_user->id
                )));

        # check for duplicate reviews

        $is_jr_editor = $this->Access->isJreviewsEditor($this->_user->id);

        $listing['User']['duplicate_review'] = 0;

        // It's a guest so we only care about checking the IP address if this feature is not disabled and
        // server is not localhost

        if($this->_user->id == 0)
        {
            if(!$this->Config->review_ipcheck_disable && $this->ipaddress != '127.0.0.1' && $this->ipaddress != '::1')
            {
                // Do the ip address check everywhere except in localhost

               $listing['User']['duplicate_review'] = $this->Review->findCount(array(
                   'conditions'=>array(
                        'Review.pid = '.$listing_id,
                        "Review.mode = " . $this->Quote($listing['Listing']['extension']),
                        "Review.published >= 0",
                        "Review.author = 0",
                        "Review.ipaddress = '{$this->ipaddress}'"
                    ),
                    'session_cache'=>false
               ));
            }
        }
        elseif(
            (!$is_jr_editor && !$this->Config->user_multiple_reviews)  // registered user and one review per user allowed when multiple reviews is disabled
            ||
            ($is_jr_editor && $this->Config->author_review == 2) // editor and one review per editor allowed when multiple editor reviews is enabled
        )
        {
            $listing['User']['duplicate_review'] = $this->Review->findCount(array(
                'conditions'=>array(
                    'Review.pid = '.$listing_id,
                    "Review.mode = " . $this->Quote($listing['Listing']['extension']),
                    "Review.published >= 0",
                    ($this->Config->author_review == 0 ? "Review.author = 0" : "Review.author >= 0"),
                    "(Review.userid = {$this->_user->id}" .
                        (
                            $this->ipaddress != '127.0.0.1' && $this->ipaddress != '::1' && !$this->Config->review_ipcheck_disable && !$is_jr_editor //&& (!$is_jr_editor || !$this->Config->review_ipcheck_disable)
                        ?
                            " OR Review.ipaddress = '{$this->ipaddress}') "
                        :
                            ')'
                        )
                ),
                'session_cache'=>false
            ));
        }

          $this->set(array(
                'extension'=>'com_content',
                'User'=>$this->_user,
                'listing'=>$listing,
                'editor_review'=>$editor_review,
                'reviews'=>$reviews,
                'review_fields'=>$review_fields,
                'review'=>$review,
                'formTokenKeys'=>$this->formTokenKeys
            )
        );

        $page = $this->render('listings','detail');

        # Save cached version
        if(!$preview && $this->_user->id === 0) {

            $this->cacheView('listings','detail',$this->here . '.detail', $page);
        }

        if(!$preview) {

            $content_row->text = $page;

            return array('row'=>$content_row,'params'=>$content_params,'listing'=>$listing,'crumbs'=>$crumbs);
        }
        else {

            return $page;
        }
    }

    function com_content_blog($passedArgs)
    {
        $this->autoLayout = true;

        $this->layout = 'cmsblog';

        $content_row = $passedArgs['row'];

        $content_params = $passedArgs['params'];

        // Check if item category is configured for jreviews

        if(!$this->Category->isJreviewsCategory($content_row->catid))
        {
            return array('row'=>$content_row,'params'=>$content_params);
        }

        # Set the theme suffix  - $parent_categories also used for J16 breadcrumb

        $parent_categories = $this->Category->findParents($content_row->catid);

        $this->Theming->setSuffix(array('categories'=>$parent_categories));

        # Override content page parameter settings
//            $content_row->params->set('show_title',0);
//            $content_row->params->set('show_category',0);

        $sync_image = Sanitize::getString($this->Config,'media_article_sync');

        if(is_object($content_row))
        {
            $content_images = json_decode($content_row->images,true);

            if($sync_image != 'none')
            {
                $content_images[$sync_image] = '';
            }

            $content_row->images = json_encode($content_images);
        }

        $content_row->params->set('show_author',0);

        is_object($content_params) and $content_params->set('show_page_heading',0); /* For some reason setting it on $content_row does not work*/

        $content_row->params->set('show_page_heading',0); /* Place holder in case it ever gets fixed */

        $content_row->params->set('show_create_date',0);

        $content_row->params->set('show_modify_date',0);

        $content_row->params->set('show_publish_date',0);

        $content_row->params->set('show_vote',0);

        $content_row->params->set('show_hits',0);

        # Get listing and review summary data

        $listing = $this->Listing->findRow(array('conditions'=>array('Listing.id = '. $content_row->id)));

        $listing['Listing']['text'] = $content_row->text;

        $this->set(array(
                'User'=>$this->_user,
                'listing'=>$listing
        ));

        $content_row->text = $this->render('listings','cmsblog');

        return array('row'=>$content_row,'params'=>$content_params);
    }
}
