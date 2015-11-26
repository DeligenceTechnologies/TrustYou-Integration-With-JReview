<?php
/**
 * JReviews - Reviews Extension
 * Copyright (C) 2010-2015 ClickFWD LLC
 * This is not free software, do not distribute it.
 * For licencing information visit https://www.jreviews.com
 * or contact sales@jreviews.com
**/

defined( 'MVC_FRAMEWORK') or die( 'Direct Access to this location is not allowed.' );

class CategoriesController extends MyController
{

    var $uses = array('user','menu','criteria','criteria_rating','directory','category','field','media');

    var $helpers = array('assets','cache','routes','libraries','html','text','jreviews','widgets','time','paginator','rating','custom_fields','community','media');

    var $components = array('config','access','feeds','everywhere','media_storage');

    var $autoRender = false; //Output is returned

    var $autoLayout = true;

    var $layout = 'listings';

    var $click2search = false;

    var $search_no_results = false;

    function beforeFilter()
    {
        # Call beforeFilter of MyController parent class
        parent::beforeFilter();

        $this->Listing->controller = $this->name;

        $this->Listing->action = $this->action;

        # Make configuration available in models

        $this->Listing->Config = &$this->Config;
    }

    function getPluginModel()
    {
        return $this->Listing;
    }

    function getObserverModel()
    {
        return $this->Listing;
    }

    function alphaindex()
    {
        return $this->listings();
    }

    function category()
    {
        if(!Sanitize::getString($this->params,'cat'))
        {
            return cmsFramework::raiseError(404, JText::_('JGLOBAL_CATEGORY_NOT_FOUND'));
        }

        return $this->listings();
    }

    function favorites()
    {
        $user_id = Sanitize::getInt($this->params,'user',$this->_user->id);

        if (!$user_id)
        {
            return $this->render('elements','login');
        }

        $this->Listing->joins[] = 'INNER JOIN #__jreviews_favorites AS Favorite ON Listing.id = Favorite.content_id AND Favorite.user_id = ' . $user_id;

        return $this->listings();
    }

    function featured()
    {
        $this->Listing->conditions[] = 'Field.featured > 0';

        return $this->listings();
    }

    function featuredrandom()
    {
        $this->Listing->conditions[] = 'Field.featured > 0';

        return $this->listings();
    }

    function latest()
    {
        return $this->listings();
    }

    function mylistings()
    {
        $user_id = Sanitize::getInt($this->params,'user',$this->_user->id);

        if(!$user_id)
        {
            return $this->render('elements','login');
        }

        $this->Listing->conditions[] = 'Listing.' . EverywhereComContentModel::_LISTING_USER_ID . ' = '.$user_id;

        return $this->listings();
    }

    function mostreviews()
    {
        $this->Listing->conditions[] = 'Totals.user_comment_count > 0';

        return $this->listings();
    }

    function toprated()
    {
        $this->Listing->conditions[] = 'Totals.user_rating > 0';

        return $this->listings();
    }

    function topratededitor()
    {
        $this->Listing->conditions[] = 'Totals.editor_rating > 0';

        return $this->listings();
    }

    function popular()
    {
        return $this->listings();
    }

    function random()
    {
        return $this->listings();
    }

    function listings()
    {
        if(Sanitize::getString($this->params,'action') == 'xml')
        {
            $access =  $this->Access->getAccessLevels();

            $feed_filename = S2_CACHE . 'views' . DS . 'jreviewsfeed_'.md5($access.$this->here).'.xml';

            $this->Feeds->useCached($feed_filename,'listings');
        }

        $this->name = 'categories';   // Required for assets helper

        if($this->_user->id === 0 && ($this->action != 'search' || ($this->action == 'search' && Sanitize::getVar($this->params,'tag') != '')))
        {
            $this->cacheAction = Configure::read('Cache.expires');
        }

        $this->autoRender = false;

        $fieldOrderArray = $ratingCriteriaOrderArray = array();

        $action = Sanitize::paranoid($this->action);

        $dir_id = str_replace(array('_',' '),array(',',''),Sanitize::getString($this->params,'dir'));

        $cat_id = Sanitize::getString($this->params,'cat');

        $listing_type_id = Sanitize::getString($this->params,'criteria');

        $user_id = Sanitize::getInt($this->params,'user',$this->_user->id);

        $index = Sanitize::getString($this->params,'index');

        $sort = Sanitize::getString($this->params,'order');

        $listview = Sanitize::getString($this->params,'listview');

        $tmpl_suffix = Sanitize::getString($this->params,'tmpl_suffix');

        $order_field = Sanitize::getString($this->Config,'list_order_field');

        $order_default = Sanitize::getString($this->Config,'list_order_default');

        // generate canonical tag for urls with order param
        $canonical = Sanitize::getBool($this->Config,'url_param_joomla') && $sort.$listview.$tmpl_suffix != '';

        if($sort == '' && $order_field != '' && in_array($this->action,array('category','alphaindex','search','custom'))) {

            $sort = $order_field;
        }
        elseif($sort == '') {

            $sort = $order_default;
        }

        $this->params['default_order'] = $order_default;

        $menu_id = Sanitize::getInt($this->params,'menu',Sanitize::getString($this->params,'Itemid'));

        $query_listings = true; // Check if it can be disabled for parent category pages when listings are disabled

        $total_special = Sanitize::getInt($this->data,'total_special');

        if(!in_array($this->action,array('category')) && $total_special > 0) {

            $total_special <= $this->limit and $this->limit = $total_special;
        }

        $listings = array();

        $parent_categories = array();

        $count = 0;

        $conditions = array();

        $joins = array();

        if($action == 'category' || ($action == 'search' && is_numeric($cat_id) && $cat_id > 0))
        {
            $parent_categories = $this->Category->findParents($cat_id);

            if($action == 'category' && !$parent_categories)
            {
                return cmsFramework::raiseError(404, JText::_('JGLOBAL_CATEGORY_NOT_FOUND'));
            }

            if($parent_categories)
            {
                $category = end($parent_categories); // This is the current category

                if(!$category['Category']['published'] || !$this->Access->isAuthorized($category['Category']['access']))
                {
                    return $this->render('elements','login');
                }

                $dir_id = $this->params['dir'] = $category['Directory']['dir_id'];

                $categories = $this->Category->findChildren($cat_id, $category['Category']['level']);

                // Check the listing type of all subcategories and if it's the same one apply the overrides to the parent category as well
                // Also get set the listing type id and default ordering based on a common listing type if it's the same for all sub-categories

                $overrides = array();

                if(count($categories) > 1 && $category['Category']['criteria_id'] == 0 && !empty($categories)) {

                    foreach($categories AS $tmp_cat) {

                        if($tmp_cat['Category']['criteria_id'] > 0 && !empty($tmp_cat['ListingType']['config'])) {

                            $overrides[$tmp_cat['Category']['criteria_id']] = $tmp_cat['ListingType']['config'];
                        }
                    }

                    if(count($overrides) == 1) {

                        $category['ListingType']['config'] = array_shift($overrides);

                        $listing_type_id = $tmp_cat['Category']['criteria_id'];
                    }
                }
                else {

                    $listing_type_id = $category['Category']['criteria_id'];
                }
            }

            # Override global configuration

            $order_field_override = '';

            $order_default_override = -1;

            if(isset($category))
            {
                isset($category['ListingType']) and $this->Config->override($category['ListingType']['config']);

                if(!is_array($category['ListingType']['config'])) {

                    $category['ListingType']['config'] = json_decode($category['ListingType']['config'],true);
                }

                $order_field_override = Sanitize::getString($category['ListingType']['config'],'list_order_field');

                $order_default_override = Sanitize::getString($category['ListingType']['config'],'list_order_default');
            }

            if($order_field_override != '') {

                $sort_default = $order_field_override;
            }
            elseif ($order_default_override != -1) {

                $sort_default = $order_default_override;
            }
            elseif($order_field != '') {

                $sort_default = $order_field;
            }
            else {

                $sort_default = $order_default;
            }

            $this->params['default_order'] = $sort_default;

            $sort = Sanitize::getString($this->params,'order',$sort_default);

            // Set default order for pagination
            $sort == '' and $sort = $order_default;
        }

        # Set the theme layout and suffix

        $this->Theming->setSuffix(array('categories'=>$parent_categories));

        $this->Theming->setLayout(array('categories'=>$parent_categories));

        if($this->action == 'category'
                && isset($category)
                && !empty($category)
                && (!$this->Access->isAuthorized($category['Category']['access']) || !$category['Category']['published'])
           )
        {
            return $this->render('elements','login');
        }

        // Get the criteria ratings if we have the listing type id or a single cat id
        // We use this to generate the order by criteria list

        if(is_numeric($listing_type_id) && $listing_type_id > 0 && in_array($action,array('category','search')))
        {
            $ratingCriteriaOrderArray = $this->CriteriaRating->findAll(array('conditions'=>array('CriteriaRating.listing_type_id = ' . $listing_type_id)));
        }

        // Get the list ordering options

        if(is_numeric($listing_type_id) && $listing_type_id > 0 && in_array($action,array('category','search','alphaindex')))
        {
            $fieldOrderArray = $this->Field->getOrderList($listing_type_id,'listing');
        }

        # Get listings

        # Modify and perform database query based on lisPage type

        if($action == 'alphaindex')
        {
            $conditions[] = ($index == '0' ? 'Listing.' . EverywhereComContentModel::_LISTING_TITLE . ' REGEXP "^[0-9]"' : 'Listing.' . EverywhereComContentModel::_LISTING_TITLE . ' LIKE '.$this->Quote($index.'%'));
        }

        $children = $this->action == 'category' ? $this->Config->list_show_child_listings : true;

        $criteria_id = $listing_type_id;

        $this->Listing->addCategoryFiltering($conditions, $this->Access, compact('children','cat_id','dir_id','criteria_id'));

        $this->Listing->addListingFiltering($conditions, $this->Access, compact('user_id'));

        $queryData = array(
            /*'fields' they are set in the model*/
            'joins'=>$joins,
            'conditions'=>$conditions,
            'limit'=>$this->limit,
            'offset'=>$this->offset
        );

        # Modify query for correct ordering. Change FIELDS, ORDER BY and HAVING BY directly in Listing Model variables

        if($this->action != 'custom' || ($this->action == 'custom' && empty($this->Listing->order)))
        {
            $this->Listing->processSorting($action,$sort);
        }

        // This is used in Listings model to know whether this is a list page to remove the plugin tags
        $this->Listing->controller = 'categories';

        // Check if review scope checked in advancd search
        $scope = explode('_',Sanitize::getString($this->params,'scope'));

        if($this->action == 'search' && in_array('reviews',$scope))
        {
            $queryData['joins'][] = "LEFT JOIN #__jreviews_comments AS Review ON Listing.id = Review.pid AND Review.published = 1 AND Review.mode = 'com_content'";

            $queryData['group'][] = "Listing.id"; // Group By required due to one to many relationship between listings => reviews table
        }

        if(!$this->search_no_results)
        {
            $query_listings and $listings = $this->Listing->findAll($queryData);
        }

        # If only one result then redirect to it
        if($this->Config->search_one_result && count($listings)==1 && $this->action == 'search' && $this->page == 1)
        {
            $listing = array_shift($listings);

            $url = cmsFramework::makeAbsUrl($listing['Listing']['url'],array('sef'=>true));

            cmsFramework::redirect($url);
        }

        # Prepare Listing count query

        if(!$this->search_no_results)
        {
            if(in_array($action,array('category')) || $action != 'favorites')
            {
                unset($queryData['joins']['User'],$queryData['joins']['Claim']);

                if($this->action == 'search' && in_array('reviews',$scope))
                {
                    $queryData['joins']['Review'] = "LEFT JOIN #__jreviews_comments AS Review ON Listing.id = Review.pid AND Review.published = 1 AND Review.mode = 'com_content'";
                }
            }

            // Need to add user table join for author searches

            if(isset($this->params['author']))
            {
                $queryData['joins'][] = "LEFT JOIN #__users AS User ON User." . UserModel::_USER_ID . " = Listing." . EverywhereComContentModel::_LISTING_USER_ID;
            }

            if($query_listings && !isset($this->Listing->count))
            {
                if(in_array($this->action,array('favorites','mylistings'))) {

                    $queryData['session_cache'] = false;
                }

                $count = $this->Listing->findCount($queryData, ($this->action == 'search' && in_array('reviews',$scope)) ? 'DISTINCT Listing.id' : '*');
            }
            elseif(isset($this->Listing->count))
            {
                $count = $this->Listing->count;
            }

            if($total_special > 0 && $total_special < $count)
            {
                $count = Sanitize::getInt($this->data,'total_special');
            }

        }

        # Get directory info for breadcrumb if dir id is a url parameter
        $directory = array();

        if(is_numeric($dir_id))
        {
            $directory = $this->Directory->findRow(array(
                'fields'=>array(
                    'Directory.id AS `Directory.dir_id`',
                    'Directory.title AS `Directory.slug`',
                    'Directory.desc AS `Directory.title`'
                ),
                'conditions'=>array('Directory.id = ' . $dir_id)
            ));
        }

        /******************************************************************
        * Process page title and description
        *******************************************************************/

        $name_choice = constant('UserModel::_USER_' . strtoupper($this->Config->name_choice) );

        $page = $this->createPageArray($menu_id);

        switch($action)
        {
            case 'category':

                if(isset($category)) {

                    $menu_action = 2;

                    $page['title'] == '' and $page['title'] = $category['Category']['title'];

                    // Could be a direct category menu or a menu for a parent category
                    $menu_exists = !empty($page['menuParams']) && isset($page['menuParams']['action']);

                    $menu_is_for_this_category = $menu_exists
                                                    && $page['menuParams']['action'] == $menu_action
                                                    && $page['menuParams']['catid'] == $category['Category']['cat_id'];

                    $menu_page_title = Sanitize::getString($page['menuParams'],'page_title');

                    $menu_page_heading = Sanitize::getString($page['menuParams'],'page_heading');

                    // Prevent the show_page_heading menu param from disabling the display of the category title
                    $page['show_title'] = true;

                    // Ensure the correct title is displayed in subcategory pages when the subcategory doesn't have its own menu
                    if(!$menu_is_for_this_category) {

                        $page['title'] = $category['Category']['title'];

                        $page['title_seo'] = $category['Category']['title_seo'];
                    }
                    else {

                        // Menu page settings override everything else

                        if($menu_page_title != '') {

                            $page['title_seo'] = $menu_page_title;
                        }
                        else {

                            $page['title_seo'] = $category['Category']['title_seo'];
                        }

                        if($menu_page_heading != '') {

                            $page['title'] = $menu_page_heading;
                        }
                    }

                    if(Sanitize::getString($page,'top_description') == '')  {

                        $page['top_description'] = $category['Category']['description'];
                    }

                    // if($menu_not_for_this_category || Sanitize::getString($category['Category'],'metadesc') != '' && Sanitize::getString($page,'description') == '') {
                    // If category doesn't have a menu, but the meta data is available from the Joomla category manager we use it
                    if(($menu_is_for_this_category && Sanitize::getString($page['menuParams'],'menu-meta_description') == '')
                        ||
                        (!$menu_is_for_this_category && Sanitize::getString($category['Category'],'metadesc') != '')) {

                        $page['description'] =  Sanitize::htmlClean($category['Category']['metadesc']);

                        // Ensure menu params doesn't override Joomla category manager setting
                        $page['menuParams']['menu-meta_description'] = '';
                    }

                    // If category doesn't have a menu, but the meta data is available from the Joomla category manager we use it
                    if(($menu_is_for_this_category && Sanitize::getString($page['menuParams'],'menu-meta_keywords') == '')
                        ||
                        (!$menu_is_for_this_category && Sanitize::getString($category['Category'],'metakey') != '')) {
                    // if($menu_not_for_this_category || Sanitize::getString($category['Category'],'metakey') != '' && Sanitize::getString($page,'keywords') == '') {

                        $page['keywords'] =  Sanitize::htmlClean($category['Category']['metakey']);

                        // Ensure sure menu params doesn't override Joomla category manager setting
                        $page['menuParams']['menu-meta_keywords'] = '';
                    }

                    // Process Category SEO Manager title, keywords and description
                    $page['description'] = str_replace('{category}',$category['Category']['title'],Sanitize::getString($page,'description'));

                    $page['keywords'] = str_replace('{category}',$category['Category']['title'],Sanitize::getString($page,'keywords'));

                    $matches1 = $matches2 = $matches3 = array();

                    $tags = $replacements = array();

                    if(!empty($parent_categories) &&
                        (
                            preg_match('/{category[0-9]+}/',$page['description'],$matches1)
                            || preg_match('/{category[0-9]+}/',$page['keywords'],$matches2)
                            || preg_match('/{category[0-9]+}/',$page['title_seo'],$matches3)
                        )
                    ) {
                        $matches = array_merge($matches1,$matches2,$matches3);

                        if(!empty($matches)) {

                            $i = 0;

                            foreach($parent_categories AS $category) {

                                $i++;

                                $tags[] = '{category'.$i.'}';

                                $replacements[] = $category['Category']['title'];
                            }
                        }
                    }

                    $tags[] = '{category}';

                    $replacements[] = $category['Category']['title'];

                    if($menu_page_heading == '') {

                        $page['title'] = str_replace($tags,$replacements,$category['Category']['title_override'] ? $page['title_seo'] : $page['title']);
                    }

                    $page['title_seo'] = str_replace($tags,$replacements,$page['title_seo']);

                    $page['description'] = str_replace($tags,$replacements,$page['description']);

                    $page['keywords'] = str_replace($tags,$replacements,$page['keywords']);

                    $page['top_description'] = str_replace($tags,$replacements,$page['top_description']);

                    // Category image
                    //
                    if($categoryParams = Sanitize::getString($category['Category'],'params')) {

                        $categoryParams = json_decode($categoryParams);

                        $page['image'] = Sanitize::getString($categoryParams,'image');

                    }

                    // Check if this is a listing submit category or disable listing submissions

                    if(Sanitize::getInt($category['Category'],'criteria_id') == 0) {

                        $this->Config->list_show_addnew = 0;
                    }
                }

                break;

            case 'custom':

                // Ordering should not be included in the page title for custom lists that have a specific odering set

                $custom_params = array();

                parse_str(Sanitize::getString($page['menuParams'],'custom_params'), $custom_params);

                if(Sanitize::getString($page['menuParams'],'custom_order') != '' || isset($custom_params['order']))
                {
                    $sort = '';

                    $page['menuParams']['custom_order'] = ' ';

                    unset($this->params['order']);
                }

                break;

            case 'alphaindex':

                $title = isset($directory['Directory']) ? Sanitize::getString($directory['Directory'],'title','') : '';

                $page['title'] = ($title != '' ? $title . ' - ' . ($index == '0' ? '0-9' : $index) : ($index == '0' ? '0-9' : $index));

                break;

            case 'mylistings':

                $user_name = '';

                if($user_id > 0)
                {
                    $this->User->fields = array();

                    $user_name = $this->User->findOne(
                        array(
                            'fields'=>array('User.' . $name_choice . ' AS `User.name`'),
                            'conditions'=>array('User.' . UserModel::_USER_ID . ' = ' . $user_id)
                        )
                    );

                }
                elseif($this->_user->id > 0) {

                    $user_name = $this->_user->{$name_choice};
                }

                if($user_name == '')
                {
                    return cmsFramework::raiseError( 404, s2Messages::errorGeneric() );
                }

                $page['title'] = $page['title_seo'] = sprintf(JreviewsLocale::getPHP('LIST_PAGE_LISTINGS_BY_TITLE_SEO'),$user_name);

                $page['show_title'] = 1;

                break;

            case 'favorites':

                $user_name = '';

                if($user_id > 0)
                {
                    $this->User->fields = array();

                    $user_name = $this->User->findOne(
                        array(
                            'fields'=>array('User.' . $name_choice . ' AS `User.name`'),
                            'conditions'=>array('User. ' . UserModel::_USER_ID . ' = ' . $user_id)
                        )
                    );

                } elseif($this->_user->id > 0) {

                    $user_name = $this->_user->{$name_choice};
                }

                if($user_name == '')
                {
                    return cmsFramework::raiseError( 404, s2Messages::errorGeneric() );
                }

                $page['show_title'] = 1;

                $page['title'] = $page['title_seo'] = sprintf(JreviewsLocale::getPHP('LIST_PAGE_FAVORITES_BY_TITLE_SEO'), $user_name);

                break;

            case 'list':
            case 'search':

                $this->__seo_fields($page, $cat_id);

                break;

            case 'featured':
            case 'latest':
            case 'mostreviews':
            case 'popular':
            case 'toprated':
            case 'topratededitor':

                break;

            default:

                $page['title'] = $menu_title;

                break;
        }

        if(Sanitize::getString($page,'top_description') != '') $page['show_description'] = true;

        // If empty unset the keys so they don't overwrite the ones set via menu
        if(trim(strip_tags(Sanitize::getString($page,'description'))) == '') unset($page['description']);

        if(trim(strip_tags(Sanitize::getString($page,'keywords'))) == '') unset($page['keywords']);

        /******************************************************************
        * Generate SEO canonical tags for sorted pages
        *******************************************************************/
        if($canonical) {

            $page['canonical'] = cmsFramework::getCurrentUrl(array('order','listview','tmpl_suffix'));
        }

        /******************************************************************
        * Generate SEO titles for re-ordered pages (most reviews, top user rated, etc.)
        *******************************************************************/
        if(Sanitize::getString($page,'title_seo') == '' && isset($page['title'])) {

            $page['title_seo'] = $page['title'];
        }

        if(($this->action !='search' || Sanitize::getVar($this->params,'tag')) && isset($this->params['order']) && $sort != '')
        {
            S2App::import('helper','jreviews','jreviews');

            $matches = array();

            $ordering_options = JreviewsHelper::orderingOptions();

            $tmp_order = str_replace('rjr','jr',$sort);

            if(isset($ordering_options[$sort]))
            {
                $page['title_seo'] .= ' ' . sprintf(JreviewsLocale::getPHP('LIST_PAGE_ORDERED_BY_TITLE_SEO'), mb_strtolower($ordering_options[$sort],'UTF-8'));
            }
            elseif(isset($fieldOrderArray[$tmp_order])) {

                if($sort{0} == 'r')
                {

                    $page['title_seo'] .= ' ' . sprintf(JreviewsLocale::getPHP('LIST_PAGE_ORDERED_BY_DESC_TITLE_SEO'), mb_strtolower($fieldOrderArray[$tmp_order]['text'],'UTF-8'));
                }
                else {

                    $page['title_seo'] .= ' ' . sprintf(JreviewsLocale::getPHP('LIST_PAGE_ORDERED_BY_TITLE_SEO'), mb_strtolower($fieldOrderArray[$sort]['text'],'UTF-8'));
                }
            }
            elseif(preg_match('/rating-(?P<criteria>\d+)/', $sort,$matches)) {

                if(isset($matches['criteria']) && isset($ratingCriteriaOrderArray[$matches['criteria']]))
                {
                    $criteria_title = $ratingCriteriaOrderArray[$matches['criteria']]['CriteriaRating']['title'];

                    $page['title_seo'] .= ' ' . sprintf(JreviewsLocale::getPHP('LIST_PAGE_ORDERED_BY_TITLE_SEO'), $criteria_title);
                }
            }
        }

        $this->params['order'] = $sort; // This is the param read in the views so we need to update it

        $fieldCrumbs = array();

        if(isset($this->params['tag']))
        {
            $control_fieldid = Sanitize::getInt($this->params['tag'],'control_fieldid');

            $control_value = Sanitize::getString($this->params['tag'],'control_value');

            $fieldCrumbs = array($this->params['tag']);

            if($control_fieldid > 0 && $control_value != '')
            {
                $this->Field->getBreadCrumbs($fieldCrumbs, $this->params['tag']['control_fieldid'], $this->params['tag']['control_value']);

                $fieldCrumbs = array_reverse($fieldCrumbs);
            }
        }

        /******************************************************************
        * Set view (theme) vars
        *******************************************************************/
        $this->set(array(
            'Config'=>$this->Config,
            'User'=>$this->_user,
            'subclass'=>'listing',
            'page'=>$page,
            'directory'=>$directory,
            'category'=>isset($category) ? $category : array(), // Category list
            'categories'=>isset($categories) ? $categories : array(),
            'parent_categories'=>$parent_categories, // Used for breadcrumb
            'cat_id'=>$cat_id,
            'listings'=>$listings,
            'fieldCrumbs'=>$fieldCrumbs,
            'pagination'=>array('total'=>$count),
            'fieldOrderArray'=>$fieldOrderArray,
            'ratingCriteriaOrderArray'=>$ratingCriteriaOrderArray
        ));


        /******************************************************************
        * RSS Feed: caches and displays feed when xml action param is present
        *******************************************************************/
        if(Sanitize::getString($this->params,'action') == 'xml') {
            $this->Feeds->saveFeed($feed_filename,'listings');
        }

        return $this->render('listings','listings_' . $this->listview);
    }

    function compareCatchAll()
    {
        $this->action = 'compare';

        return $this->compare();
    }

    function compare()
    {
        $listings = array();

        $menu_id = Sanitize::getInt($this->params,'Itemid');

        $listingType = Sanitize::getInt($this->params,'type');

        $menuParams = $this->Menu->getMenuParams($menu_id);

        $is_mobile = Configure::read('System.isMobile');

        $isMenu = false;

        $listing_ids = cleanIntegerCommaList(Sanitize::getString($menuParams,'listing_ids'));

        if(!empty($listing_ids)) {

            $listing_ids = explode(',',$listing_ids);

            $isMenu = true;
        }
        elseif($listing_ids = Sanitize::getString($this->params,'id')) {

            $listing_ids = cleanIntegerCommaList($listing_ids);

            if(!empty($listing_ids)) {

                $listing_ids = explode(',',$listing_ids);
            }
            else {

                $listing_ids = null;
            }

            $isMenu = false;
        }
        else {

            $listing_ids = null;
        }

        if(empty($listing_ids)) {

            cmsFramework::raiseError(404, JreviewsLocale::getPHP('COMPARISON_NO_LISTINGS'));
        }

        $conditions[] = "Listing." . EverywhereComContentModel::_LISTING_ID . " IN (".implode(",",$listing_ids).")";

        $this->Listing->addListingFiltering($conditions, $this->Access, array('state'=>1));

        // Should pass the listing ids to get the cat ids and then filter by those that can be accessed
        // $this->Listing->addCategoryFiltering($conditions, $this->Access, compact('listing_id'));

        $listings = $this->Listing->findAll(array('conditions'=>$conditions,'order'=>array('FIELD(Listing.id,'.implode(",",$listing_ids).')')));

        $listing_type_id = array();

        foreach($listings AS $listing) {

            $listing_type_id[$listing['Criteria']['criteria_id']] = $listing['Criteria']['criteria_id'];
        }

        if(count($listing_type_id) > 1)
        {
            return '<div class="jrError">'.JreviewsLocale::getPHP('COMPARISON_VALIDATE_DIFFERENT_TYPES').'</div>';
        }

        $firstListing = reset($listings);

        # Override configuration
        isset($firstListing['ListingType']) and $this->Config->override($firstListing['ListingType']['config']);

        $listingType = $firstListing['Criteria'];

        $listing_type_title = $listingType['title'];

        // Get the list of fields for the chosen listing type to render the groups and field in the correct order

        $fieldGroups = $this->Field->getFieldsArrayNew($listingType['criteria_id']);

        /******************************************************************
        * Process page title and description
        *******************************************************************/
        $page = $this->createPageArray($menu_id);

        if($page['title'] == '') {

            $page['show_title'] = true;

            $page['title'] = sprintf(JreviewsLocale::getPHP('COMPARISON_DEFAULT_TITLE'),$listing_type_title);
        }

        if(Sanitize::getInt($menuParams,'action') == '103') {

            $page['title_seo'] = $page['title'];
        }

        $this->set(array(
            'listingType'=>$listingType,
            'Config'=>$this->Config,
            'User'=>$this->_user,
            'fieldGroups'=>$fieldGroups,
            'listings'=>$listings,
            'page'=>$page,
            'isMenu'=>$isMenu
        ));

        if (!$is_mobile)
        {
            return $this->render('listings','listings_compare');
        }
        else {

            return $this->render('listings','listings_blogview');
        }
    }

    # Custom List menu - reads custom where and custom order from menu parameters
    function custom() {

        $menu_id = Sanitize::getInt($this->params,'Itemid');

        $params = $this->Menu->getMenuParams($menu_id);

        $custom_where = Sanitize::getString($params,'custom_where');

        $custom_order = Sanitize::getString($params,'custom_order');

        $custom_params = Sanitize::getString($params,'custom_params');

        $custom_params_array = array();

        parse_str($custom_params, $custom_params_array);

        if(!empty($custom_params_array))
        {
            $this->params = array_insert($this->params, $custom_params_array);

            if(!isset($this->params['scope']))
            {
                $this->params['scope'] = 'title_introtext_fulltext';
            }

            if(!isset($this->params['query']))
            {
                $this->params['query'] = 'any';
            }
        }

        if($custom_where !='') {

            $custom_where = str_replace(
                array('{user_id}'),
                array($this->_user->id),
                $custom_where);

            $this->Listing->conditions[] = '(' . $custom_where . ')';
        }

        $custom_order !='' and $this->Listing->order[] = $custom_order;

        // Prevent data from proximity search from getting into the search conditionals because it was already
        // processed in the GeoMaps add-on

        $jr_lat = Sanitize::getString($this->Config,'geomaps.latitude');

        $jr_lon = Sanitize::getString($this->Config,'geomaps.longitude');

        $search_address_field = Sanitize::getString($this->Config,'geomaps.advsearch_input');

        if($jr_lat && $jr_lon && $search_address_field)
        {
            unset(
                $this->params[$jr_lat]
                ,$this->params[$jr_lon]
                ,$this->params[$search_address_field]
                );
        }

        return $this->search();
    }

    function liveSearch()
    {
        $menu_id = Sanitize::getInt($this->data,'menu_id');

        S2App::import('Controller','search','jreviews');

        $SearchController = ClassRegistry::getClass('SearchController',array(
            'name'=>'search',
            'action'=>'_process',
            'params'=>$this->params,
            'data'=>$this->data,
            'Config'=>$this->Config,
            'ajaxRequest'=>1));

        $SearchController->__initComponents();

        $SearchController->beforeFilter();

        $this->search_results_url = $url = $SearchController->_process();

        // Check if legacy param style is used because we need to parse it in a different way

        $param_style = $this->Config->url_param_joomla;

        if($param_style)
        {
            $params = parse_url($url,PHP_URL_QUERY);

            parse_str($params, $this->params);
        }
        else {

            $route = array('url'=>array('url'=>$url));

            $params = S2Router::parse($route,false,'jreviews');

            $params = $params['url'];

            unset($params['url']);

            $this->params = $params;
        }

        // Make sure that the filters from the menu are used for specific types of menus

        if($menu_id)
        {
            $menuParams = $this->Menu->getMenuParams($menu_id);

            $action = Sanitize::getString($menuParams,'action');

            // If it's an adv. search menu and click2search url, use the menu criteria id
            switch($action) {
                case '2':

                    !isset($this->params['cat']) && $this->params['cat'] = $menuParams['catid'];

                    break;
                case '11':

                    $this->params['criteria'] = $menuParams['criteriaid'];

                    break;
            }
        }

        // Prevent data from proximity search from getting into the search conditionals because it was already
        // processed in the GeoMaps add-on

        $jr_lat = Sanitize::getString($this->Config,'geomaps.latitude');

        $jr_lon = Sanitize::getString($this->Config,'geomaps.longitude');

        $search_address_field = Sanitize::getString($this->Config,'geomaps.advsearch_input');

        if($jr_lat && $jr_lon && $search_address_field)
        {
            unset(
                $this->params[$jr_lat]
                ,$this->params[$jr_lon]
                ,$this->params[$search_address_field]
                );
        }

        return $this->search();
    }

    function liveSearchResults()
    {
        // Get Joomla module/WP widget parameters

        $module_id = Sanitize::getString($this->data,'module_id');

        $this->params['module'] = cmsFramework::getModuleParams($module_id);

        $limit = Sanitize::getInt($this->params['module'],'results_limit',5);

        $this->viewSuffix = Sanitize::getString($this->params['module'],'results_tmpl_suffix');

        $dir_id = str_replace(array('_',' '),array(',',''),Sanitize::getString($this->params,'dir'));

        $cat_id = Sanitize::getString($this->params,'cat');

        $criteria_id = Sanitize::getString($this->params,'criteria');

        $user_id = Sanitize::getInt($this->params,'user',$this->_user->id);

        $order_field = Sanitize::getString($this->Config,'list_order_field');

        $order_default = Sanitize::getString($this->Config,'list_order_default');

        $sort = Sanitize::getString($this->params,'order');

        if($sort == '' && $order_field != '') {

            $sort = $order_field;
        }
        elseif($sort == '') {

            $sort = $order_default;
        }

        $this->Listing->processSorting('search',$sort);

        $conditions = array();

        $children = true;

        $this->Listing->addStopAfterFindModel(array('Favorite'));

        $this->Listing->addCategoryFiltering($conditions, $this->Access, compact('children','cat_id','dir_id','criteria_id'));

        $this->Listing->addListingFiltering($conditions, $this->Access, compact('user_id'));

        $queryData = array(
            /*'fields' they are set in the model*/
            'conditions'=>$conditions,
            'limit'=>$limit
        );

        $listings = $this->Listing->findAll($queryData);

        $count = $this->Listing->findCount($queryData);

        $this->set(array(
            'search_url'=>$this->search_results_url,
            'listings'=>$listings,
            'distance'=>1,
            'count'=>$count
            ));

        return $this->render('listings','listings_search_results');
    }

    function search()
    {
        $urlSeparator = "_"; //Used for url parameters that pass something more than just a value

        $simplesearch_custom_fields = 1 ; // Search custom fields in simple search

        $simplesearch_query_type = Sanitize::getString($this->Config,'search_simple_query_type','all'); // any|all

        $min_word_chars = 3; // Only words with min_word_chars or higher will be used in any|all query types

        $category_ids = '';

        $criteria_ids = Sanitize::getString($this->params,'criteria');

        $dir_id = Sanitize::getString($this->params,'dir','');

        $accepted_query_types = array ('any','all','exact');

        $query_type = Sanitize::getString($this->params,'query');

        $keywords = urldecode(Sanitize::getString($this->params,'keywords'));

        $user_rating_params = Sanitize::getVar($this->params,S2_QVAR_RATING_AVG,0);

        $editor_rating_params = Sanitize::getVar($this->params,S2_QVAR_EDITOR_RATING_AVG,0);

        if(!is_array($user_rating_params))
        {
            $user_rating_params = array($user_rating_params);
        }

        if(!is_array($editor_rating_params))
        {
            $editor_rating_params = array($editor_rating_params);
        }

        $user_rating_params = array_filter($user_rating_params);

        $editor_rating_params = array_filter($editor_rating_params);

        $scope_params = Sanitize::getString($this->params,'scope');

        $author = urldecode(Sanitize::getString($this->params,'author'));

        $ignored_search_words = $keywords != '' ? cmsFramework::getIgnoredSearchWords() : array();

        $scope = array();

        if (!in_array($query_type,$accepted_query_types))
        {
            $query_type = 'all'; // default value if value used is not recognized
        }

        // Build search where statement for standard fields
        $wheres = array();

        // Transform scope into DB table columns

        $scope_terms = array_filter(explode($urlSeparator,$scope_params));

        foreach($scope_terms AS $key=>$term)
        {
            switch($term) {

                case 'title':
                case 'introtext':
                case 'fulltext':

                    $scope[$term] = $this->Listing->_SIMPLE_SEARCH_FIELDS[$term];

                    break;

                default:

                    unset($scope[$term]);

                    break;
            }
        }

        /****************************************************************************
        * First pass of url params to get all field names used in search
        * If the fieldNameArray is empty at the end, then we can do a simple search
        ****************************************************************************/

        $fieldNameArray = array();

        // Process custom fields
        $query_string = Sanitize::getString($this->passedArgs,'url');

        $customFields = $this->Field->getFieldNames('listing',array('published'=>1));

        if($tag = Sanitize::getVar($this->params,'tag'))
        {
            $this->click2search = true;

            $click2search_field = 'jr_'.$tag['field'];

            if(!in_array($click2search_field,$customFields))
            {
                return cmsFramework::raiseError(404, s2Messages::submitErrorGeneric());
            }

            if($menu_id = Sanitize::getInt($this->params,'Itemid'))
            {
                $menuParams = $this->Menu->getMenuParams($menu_id);

                $action = Sanitize::getString($menuParams,'action');

                // If it's an adv. search menu and click2search url, use the menu criteria id
                switch($action) {
                    case '2':

                        !isset($this->params['cat']) && $this->params['cat'] = $menuParams['catid'];

                        break;
                    case '11':

                        $this->params['criteria'] = $menuParams['criteriaid'];

                        break;

                    default:

                        break;
                }

            }

            // Field value underscore fix: remove extra menu parameter not removed in routes regex
            $tag['value'] = preg_replace(array('/_m[0-9]+$/','/_m$/','/_$/'),'',$tag['value']);

            // Below is included fix for dash to colon change in J1.5
            $query_string = 'jr_'.$tag['field']. _PARAM_CHAR .str_replace(':','-',$tag['value']) . '/'.$query_string;
        }

        $url_array = explode ("/", $query_string);

        // Include external parameters for custom fields - this is required for components such as sh404sef

        foreach($this->params AS $varName=>$varValue)
        {
            if(substr($varName,0,3) == "jr_" && false === array_search($varName . _PARAM_CHAR . $varValue,$url_array))
            {
                $url_array[] = $varName . _PARAM_CHAR . $varValue;
            }
        }

        foreach ($url_array as $url_param)
        {
            // Fixes issue where colon separating field name from value gets converted to a dash by Joomla!
            if(preg_match('/^(jr_[a-z0-9]+)-([\S\s]*)/',$url_param,$matches)) {
                $key = $matches[1];
                $value = $matches[2];
            }
            else {
                $param = explode (":",$url_param);
                $key = $param[0];
                $value = Sanitize::getVar($param,'1',null); // '1' is the key where the value is stored in $param
            }

            if (substr($key,0,3)=="jr_" && in_array($key,$customFields) && !is_null($value) && $value != '') {
                $fieldNameArray[$key] = $value;
            }
        }

        /****************************************************************************
        * PROCESS RATING SEARCH PARAMS
        ****************************************************************************/

        if(!empty($user_rating_params))
        {
            foreach($user_rating_params AS $user_rating)
            {
                $user_rating = explode(',',$user_rating);

                $user_rating_value = Sanitize::getInt($user_rating,0);

                if(($this->Config->rating_scale > 5 && $user_rating_value < 5) || ($user_rating_value > $this->Config->rating_scale))
                {
                    $user_rating_value = min(4,$user_rating_value) * ($this->Config->rating_scale/5);
                }

                if(count($user_rating) == 1 && $user_rating_value)
                {
                    $wheres[] = "Totals.user_rating >= " . $user_rating_value;
                }
                else {

                    $user_rating_criteria_id = Sanitize::getInt($user_rating,1);

                    if($user_rating_criteria_id)
                    {
                        $table_alias = 'ListingRatingUser' . $user_rating_criteria_id;

                        $this->Listing->joins[$table_alias] = "LEFT JOIN #__jreviews_listing_ratings AS ".$table_alias." ON ".$table_alias.".listing_id = Listing." . EverywhereComContentModel::_LISTING_ID . " AND ".$table_alias.".extension = 'com_content'";

                        $wheres[] = $table_alias . '.user_rating >= ' . $user_rating_value;

                        $wheres[] = $table_alias . '.criteria_id = ' . $user_rating_criteria_id;
                    }
                }
            }
        }

        if(!empty($editor_rating_params))
        {
            foreach($editor_rating_params AS $editor_rating)
            {
                $editor_rating = explode(',',$editor_rating);

                $editor_rating_value = Sanitize::getInt($editor_rating,0);

                if(($this->Config->rating_scale > 5 && $editor_rating_value < 5) || ($editor_rating_value > $this->Config->rating_scale))
                {
                    $editor_rating_value = min(4,$editor_rating_value) * ($this->Config->rating_scale/5);
                }

                if(count($editor_rating) == 1 && $editor_rating_value)
                {
                    $wheres[] = "Totals.editor_rating >= " . $editor_rating_value;
                }
                else {

                    $editor_rating_criteria_id = Sanitize::getInt($editor_rating,1);

                    if($editor_rating_criteria_id)
                    {
                        $table_alias = 'ListingRatingEditor' . $editor_rating_criteria_id;

                        $this->Listing->joins[$table_alias] = "LEFT JOIN #__jreviews_listing_ratings AS ".$table_alias." ON ".$table_alias.".listing_id = Listing." . EverywhereComContentModel::_LISTING_ID . " AND ".$table_alias.".extension = 'com_content'";

                        $wheres[] = $table_alias . '.editor_rating >= ' . $editor_rating_value;

                        $wheres[] = $table_alias . '.criteria_id = ' . $editor_rating_criteria_id;
                    }
                }
            }
        }
		//Added by santosh
		 $table_alias = 'ListingTrustYou';
         $this->Listing->joins[$table_alias] = "LEFT JOIN #__jreview_listing_trustyou AS ".$table_alias." ON Listing.id = " . $table_alias . ".lid";
		 
		 array_push($this->Listing->fields,'ListingTrustYou.reviews_count as trustyou_reviews_count');
		 array_push($this->Listing->fields,'ListingTrustYou.sources_count as trustyou_sources_count');
		 array_push($this->Listing->fields,'ListingTrustYou.score_description as trustyou_score_description');
		 array_push($this->Listing->fields,'ListingTrustYou.score as trustyou_score');
		 //End of santosh

        /****************************************************************************
        * SIMPLE SEARCH
        ****************************************************************************/

        if(($keywords != '' &&  empty($scope)) || empty($fieldNameArray))
        {
            // If scope has changed in the form to 'title' only for example then we use it

            if(empty($scope))
            {
                $scope = $this->Listing->_SIMPLE_SEARCH_FIELDS;
            }

            $words = array_unique(explode( ' ', $keywords));

            // Include custom fields

            if($simplesearch_custom_fields == 1)
            {
                $fields = $this->Field->getTextBasedFieldNames();

                // Add the 'Field.' column alias so it's used in the query

                if(!empty($fields))
                {
                    array_walk($fields, function(&$item) {
                        $item = 'Field.' . $item;
                    });
                }
                // TODO: find out which fields have predefined selection values to get the searchable values instead of reference

                // Merge standard fields with custom fields
                $scope = array_merge($scope, $fields);
            }

            $whereFields = array();

            foreach ($words as $word)
            {
                $whereContentFields = array();

                if(strlen($word) >= $min_word_chars && !in_array($word,$ignored_search_words))
                {
                    $word = urldecode(trim($word));

                    foreach ($scope as $contentfield)
                    {
                        $whereContentFields[] = " $contentfield LIKE " . $this->QuoteLike($word);
                    }

                    if(!empty($whereContentFields)){

                        $whereFields[] = " (" . implode(') OR (', $whereContentFields ) . ')';
                    }
                }
            }

            if(!empty($whereFields))
            {
                $wheres[] = " (" . implode(  ($simplesearch_query_type == 'all' ? ') AND (' : ') OR ('), $whereFields ) . ')';
            }
        }
        else {

        /****************************************************************************
        * ADVANCED SEARCH
        ****************************************************************************/

            // Process core content fields and reviews
            if($keywords != '' && !empty($scope))
            {
                $allowedContentFields = array('title','introtext','fulltext','reviews','metakey');

                // Only add meta keywords if the db column exists

                if(EverywhereComContentModel::_LISTING_METAKEY != '')
                {
                    $scope['metakey'] = EverywhereComContentModel::_LISTING_METAKEY;
                }

                switch($query_type)
                {
                    case 'exact':

                        foreach($scope as $scope_key=>$contentfield)
                        {
                            if(in_array($scope_key,$allowedContentFields))
                            {
                                $w = array();

                                if ($contentfield == 'reviews')
                                {
                                    $w[] = " Review.comments LIKE " . $this->QuoteLike($keywords);

                                    $w[] = " Review.title LIKE " . $this->QuoteLike($keywords);
                                }
                                else {

                                    $w[] = " $contentfield LIKE ".$this->QuoteLike($keywords);
                                }

                                $whereContentOptions[]     = "\n" . implode( ' OR ', $w);
                            }
                        }

                        $wheres[] = implode( ' OR ', $whereContentOptions);

                        break;

                    case 'any':
                    case 'all':
                    default:

                        $words = array_unique(explode( ' ', $keywords));

                        $whereFields = array();

                        foreach($scope as $scope_key=>$contentfield)
                        {
                            if(in_array($scope_key,$allowedContentFields)) {
                            {
                                $whereContentFields = array();

                                $whereReviewComment = array();

                                $whereReviewTitle = array();

                                foreach ($words as $word)
                                {
                                    if(strlen($word) >= $min_word_chars && !in_array($word,$ignored_search_words))
                                    {
                                        if($contentfield == 'reviews')
                                        {
                                            $whereReviewComment[] = "Review.comments LIKE ".$this->QuoteLike($word);

                                            $whereReviewTitle[] = "Review.title LIKE ".$this->QuoteLike($word);
                                        }
                                        else {

                                            $whereContentFields[] = "$contentfield LIKE ".$this->QuoteLike($word);
                                        }
                                    }
                                }

                                if($contentfield == 'reviews')
                                {
                                    if(!empty($whereReviewTitle))
                                    {
                                        $whereFields[] = "\n(" . implode( ($query_type == 'all' ? ') AND (' : ') OR ('), $whereReviewTitle ) . ")";
                                    }

                                    if(!empty($whereReviewComment))
                                    {
                                        $whereFields[] = "\n(" . implode( ($query_type == 'all' ? ') AND (' : ') OR ('), $whereReviewComment ) . ")";
                                    }
                                }
                                elseif(!empty($whereContentFields))
                                {
                                    $whereFields[] = "\n(" . implode( ($query_type == 'all' ? ') AND (' : ') OR ('), $whereContentFields ) . ")";
                                }

                            }
                        }
                    }

                    if(!empty($whereFields))
                    {
                        $wheres[] = '(' . implode(  ') OR (', $whereFields ) . ')';
                    }

                    break;
                }
            }
            else {

                $scope = array();
            }

            // Process author field
            if ($author && $this->Config->search_item_author)
            {
                $wheres[] = "
                    (
                        User." . UserModel::_USER_REALNAME . " LIKE ".$this->QuoteLike($author)." OR
                        User." . UserModel::_USER_ALIAS . " LIKE ".$this->QuoteLike($author)." OR
                        Listing." . EverywhereComContentModel::_LISTING_AUTHOR_ALIAS . " LIKE ".$this->QuoteLike($author) .
                    ")";
            }

            /****************************************************************************
            * Find the field types to create the correct conditionals
            ****************************************************************************/

            if(!empty($fieldNameArray))
            {
                $query = '
                    SELECT
                        name, type
                    FROM
                        #__jreviews_fields
                    WHERE
                        name IN (' .$this->Quote(array_keys($fieldNameArray)) . ')'
                    ;

                $fieldTypesArray = $this->Field->query($query, 'loadAssocList', 'name');
            }

            $OR_fields = array("select","radiobuttons"); // Single option

            $AND_fields = array("selectmultiple","checkboxes","relatedlisting"); // Multiple option

            foreach ($fieldNameArray AS $key=>$value)
            {
                $searchValues = explode($urlSeparator, $value);

                $fieldType = $fieldTypesArray[$key]['type'];

                // Process values with separator for multiple values or operators. The default separator is an underscore
                if (substr_count($value,$urlSeparator)) {

                    // Check if it is a numeric or date value
                    $allowedOperators = array("equal"=>'=',"higher"=>'>=',"lower"=>'<=', "between"=>'between');
                    $operator = $searchValues[0];

                    $isDate = false;
                    if ($searchValues[1] == "date") {
                        $isDate = true;
                    }

                    if (in_array($operator,array_keys($allowedOperators)) && (is_numeric($searchValues[1]) || $isDate))
                    {
                        if ($operator == "between")
                        {
                            if ($isDate)
                            {
                                @$searchValues[1] = low($searchValues[2]) == 'today' ? _TODAY : $searchValues[2];
                                @$searchValues[2] = low($searchValues[3]) == 'today' ? _TODAY : $searchValues[3];
                            }

                            $low = is_numeric($searchValues[1]) ? $searchValues[1] : $this->Quote($searchValues[1]);
                            $high = is_numeric($searchValues[2]) ? $searchValues[2] : $this->Quote($searchValues[2]);
                            $wheres[] = "\n".$key." BETWEEN " . $low . ' AND ' . $high;
                        }
                        else {
                            if ($searchValues[1] == "date") {
                                $searchValues[1] = low($searchValues[2]) == 'today' ? _TODAY : $searchValues[2];
                            }
                            $value = is_numeric($searchValues[1]) ? $searchValues[1] : $this->Quote($searchValues[1]);
                            $wheres[] = "\n".$key.$allowedOperators[$operator].$value;
                        }
                    }
                    else {
                        // This is a field with pre-defined options
                        $whereFields = array();

                        if(isset($tag) && $key = 'jr_'.$tag['field'])
                        {
                            // Field value underscore fix
                            if(in_array($fieldType,$OR_fields))
                            {
                                $whereFields[] = "Field." . $key . " = '*" . $this->Quote('*'.urldecode($value).'*');
                            }
                            else {
                                $whereFields[] = "Field." . $key . " LIKE " . $this->Quote('%*'.urldecode($value).'*%');
                            }
                        }
                        elseif(!empty($searchValues))
                        {
                            foreach ($searchValues as $value)
                            {
                                $searchValue = urldecode($value);

                                if(in_array($fieldType,$OR_fields))
                                {
                                    $whereFields[] = "Field." . $key . " = " . $this->Quote('*'.$value.'*') ;
                                }
                                else {
                                    $whereFields[] = "Field." . $key . " LIKE " . $this->Quote('%*'.$value.'*%');
                                }
                            }
                        }

                        if (in_array($fieldType,$OR_fields))
                        {
                            // Single option field

                            $wheres[] = '(' . implode( ') OR (', $whereFields ) . ')';
                        }
                        else { // Multiple option field

                            $wheres[] = '(' . implode( ') AND (', $whereFields ) . ')';
                        }
                    }

                }
                else {

                    $value = urldecode($value);

                    $whereFields = array();

                    switch($fieldType) {

                        case in_array($fieldType,$OR_fields):

                            $whereFields[] = "Field." . $key . " = ".$this->Quote('*'.$value.'*') ;

                        break;

                        case in_array($fieldType,$AND_fields):

                            $whereFields[] = "Field." . $key . " LIKE ".$this->Quote('%*'.$value.'*%');

                        break;

                        case 'decimal':

                            $whereFields[] = "Field." . $key . " = " . (float) $value;

                        break;

                        case 'integer':

                            $whereFields[] = "Field." . $key . " = " . (int) $value;

                        break;

                        case 'date':

                            $order = Sanitize::getString($this->params,'order');

                            $begin_week = date('Y-m-d', strtotime('monday this week'));

                            $end_week = date('Y-m-d', strtotime('monday this week +6 days'));

                            $begin_month = date('Y-m-d',mktime(0, 0, 0, date('m'), 1));

                            $end_month = date('Y-m-t', strtotime('this month'));

                            $lastseven = date('Y-m-d', strtotime('-1 week'));

                            $lastthirty = date('Y-m-d', strtotime('-1 month'));

                            $nextseven = date('Y-m-d', strtotime('+1 week'));

                            $nextthirty = date('Y-m-d', strtotime('+1 month'));

                            switch($value) {

                                case 'future':
                                    $whereFields[] = "Field." . $key . " >= " . $this->Quote(_TODAY);
                                    $order == '' and $this->Listing->order = array($key . ' ASC');
                                break;
                                case 'today':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote(_TODAY) . ' AND ' . $this->Quote(_END_OF_TODAY);
                                    $order == '' and $this->Listing->order = array($key . ' ASC');
                                break;
                                case 'week':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote($begin_week) . ' AND ' . $this->Quote($end_week);
                                    $order == '' and $this->Listing->order = array($key . ' ASC');
                                break;
                                case 'month':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote($begin_month) . ' AND ' . $this->Quote($end_month);
                                    $order == '' and $this->Listing->order = array($key . ' ASC');
                                break;
                                case '+7':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote(_TODAY) . ' AND ' . $this->Quote($nextseven);
                                    $order == '' and $this->Listing->order = array($key . ' ASC');
                                break;
                                case '+30':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote(_TODAY) . ' AND ' . $this->Quote($nextthirty);
                                    $order == '' and $this->Listing->order = array($key . ' ASC');
                                break;
                                case '-7':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote($lastseven) . ' AND ' . $this->Quote(_END_OF_TODAY);
                                    $order == '' and $this->Listing->order = array($key . ' DESC');
                                break;
                                case '-30':
                                    $whereFields[] = "Field." . $key . " BETWEEN " . $this->Quote($lastthirty) . ' AND ' . $this->Quote(_END_OF_TODAY);
                                    $order == '' and $this->Listing->order = array($key . ' DESC');
                                break;
                                default:
                                    $whereFields[] = "Field." . $key . " = " . $this->Quote($value);
                                break;
                            }

                        break;

                        default:

                            if(isset($tag) && $key == 'jr_'.$tag['field'] && $fieldType == 'text')
                            {
                               $whereFields[] = "Field." . $key . " = " . $this->Quote($value);
                            }
                            else {

                               $whereFields[] = "Field." . $key . " LIKE " . $this->QuoteLike($value);
                            }

                        break;
                    }

                    $wheres[] = " (" . implode(  ') AND (', $whereFields ) . ")";
                }

            } // endforeach
        }

        $where = !empty($wheres) ? "\n (" . implode( ") AND (", $wheres ) . ")" : '';

        // Determine which categories to include in the queries
        if ($cat_id = Sanitize::getString($this->params,'cat'))
        {
            $category_ids = explode($urlSeparator,$this->params['cat']);

            // Remove empty or nonpositive values from array
            if(!empty($category_ids))
            {
                foreach ($category_ids as $index => $value)
                {
                    if (empty($value) || $value < 1 || !is_numeric($value))
                    {
                        unset($category_ids[$index]);
                    }
                }
            }

            $category_ids = is_array($category_ids) ? implode (',',$category_ids) : $category_ids;

            $category_ids != '' and $this->params['cat'] = $category_ids;
        }
        elseif (isset($criteria_ids) && trim($criteria_ids) != '')
        {
            $criteria_ids = str_replace($urlSeparator,',',$criteria_ids);

            $criteria_ids != '' and $this->params['criteria'] = $criteria_ids;
        }
        elseif (isset($dir_id) && trim($dir_id) != '')
        {
            $dir_id = str_replace($urlSeparator,',',$dir_id);

            $dir_id != '' and $this->params['dir'] = $dir_id;
        }

        # Add search conditions to Listing model

        if($where != '' ) {

            $this->Listing->conditions[] = $where;
        }
        elseif ((
            count($this->Listing->conditions) == 0
            &&
            $dir_id == ''
            &&
            $category_ids == ''
            &&
            $criteria_ids == ''
            )
         &&
         !Sanitize::getBool($this->Config,'search_return_all',false))
        {
            $this->search_no_results = true;
        }

        if($this->ajaxRequest)
        {
            $out = $this->liveSearchResults();
        }
        else {

            $out = $this->listings();
        }

        return $out;
    }

    function __seo_fields(&$page, $cat_id = null)
    {
        $category = $parent_category = '';

        if($tag = Sanitize::getVar($this->params,'tag'))
        {
            $field = 'jr_'.$tag['field'];
//            $value = $tag['value'];
            // Field value underscore fix: remove extra menu parameter not removed in routes regex
            $value = preg_replace(array('/_m[0-9]+$/','/_m$/','/_$/','/:/'),array('','','','-'),$tag['value']);

            $query = "
                SELECT
                    fieldid,
                    type,
                    metatitle,
                    options,
                    metakey,
                    metadesc
                FROM
                    #__jreviews_fields
                WHERE
                    name = ".$this->Quote($field)." AND `location` = 'content'
            ";


            $field = $this->Field->query($query, 'loadObjectList');

            if($field)
            {
                $field = array_shift($field);

                $params = stringToArray($field->options);

                $multichoice = array('select','selectmultiple','checkboxes','radiobuttons');

                if(in_array($field->type,$multichoice))
                {
                    $query = "
                        SELECT
                            FieldOption.optionid,
                            FieldOption.text,
                            FieldOption.control_field,
                            FieldOption.control_value,
                            Field.fieldid AS control_fieldid
                        FROM
                            #__jreviews_fieldoptions AS FieldOption
                        LEFT JOIN
                            #__jreviews_fields AS Field ON FieldOption.control_field = Field.name
                        WHERE
                            FieldOption.fieldid = "  . (int) $field->fieldid . "
                            AND FieldOption.value = " . $this->Quote(stripslashes($value))
                        ;

                    $option = $this->Field->query($query,'loadAssoc');

                    if(!$option)
                    {
                        return cmsFramework::raiseError(404, JText::_('JERROR_LAYOUT_PAGE_NOT_FOUND'));
                    }

                    $this->params['tag'] = array_merge($this->params['tag'], $option);

                    $fieldValue = $option['text'];
                }
                elseif(in_array($field->type,array('decimal','integer')))
                {
                    if($field->type == 'integer')
                    {
                        $fieldValue = Sanitize::getInt($params,'curr_format') ? number_format($value,0,__l('DECIMAL_SEPARATOR',true),__l('THOUSANDS_SEPARATOR',true)) : $value;
                    }
                    else
                    {
                        $decimals = Sanitize::getInt($params,'decimals',2);

                        $fieldValue = Sanitize::getInt($params,'curr_format') ? number_format($value,$decimals,__l('DECIMAL_SEPARATOR',true),__l('THOUSANDS_SEPARATOR',true)) : round($value,$decimals);
                    }

                    $fieldValue = str_ireplace('{fieldtext}', $fieldValue, $params['output_format']);

                    $fieldValue = strip_tags(urldecode($fieldValue));
                }
                else {

                    $fieldValue = urldecode($value);
                }

                if($cat_id
                    && ( stristr($field->metatitle.$field->metakey.$field->metadesc,'{category}')
                        || stristr($field->metatitle.$field->metakey.$field->metadesc,'{parent_category}'))
                    )
                {
                    if($categories = $this->Category->findParents($cat_id)) {

                        $category_array = array_pop($categories);

                        $category = $category_array['Category']['title'];

                        if(!empty($categories)) {

                            $parent_category_array = array_pop($categories);

                            $parent_category = $parent_category_array['Category']['title'];

                        }

                    }

                }

                $search = array('{fieldvalue}','{category}','{parent_category}');

                $replace = array($fieldValue, $category, $parent_category);

                $page['title'] = $page['title_seo'] = $field->metatitle == '' ? $fieldValue : trim(str_ireplace($search,$replace,$field->metatitle));

                $page['keywords'] = $page['menuParams']['menu-meta_keywords'] = trim(str_ireplace($search,$replace,$field->metakey));

                $page['description'] = $page['menuParams']['menu-meta_description'] = trim(str_ireplace($search,$replace,$field->metadesc));

                $page['show_title'] = $this->Config->seo_title;

                $page['show_description'] = $this->Config->seo_description;

                if($page['show_description']) {

                    $page['top_description'] = $page['description'];
                }
            }
        }

    }
}