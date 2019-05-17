<?php
if (!defined('SHIFTER_URLS_CACHE_EXPIRES')) {
    define('SHIFTER_URLS_CACHE_EXPIRES', 300);
}

class ShifterUrlsBase
{
    private $_var = [
        'start' => 0,
        'end'   => 0,
        'url_count' => 0,
        'transient_expires' => SHIFTER_URLS_CACHE_EXPIRES,
    ];

    static $instance;

    const FINAL = 1;
    const NOT_FINAL = 0;

    const PATH_404_HTML = 'shifter_404.html';

    const REST_ENDPOINT = SHIFTER_REST_ENDPOINT;
    const REST_PATH     = '/urls';

    const URL_TOP = 'TOP';
    const URL_404 = '404';
    const URL_ARCHIVE = 'ARCHIVE';
    const URL_SINGULAR = 'SINGULAR';

    /**
     * Constructor
     */
    public function __construct()
    {
    }

    /**
     * Get self instance
     *
     * @return \ShifterUrlsBase
     */
    public static function get_instance()
    {
        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

    /**
     * Magic method
     *
     * @param string $name method name
     * @param array  $args argments
     *
     * @return array|string|boolean
     * @throws BadMethodCallException
     */
    public function __call($name, $args)
    {
        if (strncmp($name, 'get_', 4) === 0) {
            return $this->get(substr($name, 4), reset($args));
        } else if (strncmp($name, 'set_', 4) === 0) {
            return $this->set(substr($name, 4), reset($args));
        } else if (strncmp($name, 'chk_', 4) === 0) {
            return $this->set(substr($name, 4), reset($args));
        } else if (strncmp($name, 'inc_', 4) === 0) {
            return $this->increment(substr($name, 4), reset($args));
        } else if ($name === 'chk_skip') {
            return $this->_check_skip(reset($args));
        } else if ($name === 'chk_final') {
            return $this->_check_final();
        }
        throw new \BadMethodCallException('Method "'.$name.'" does not exist.');
    }

    /**
     * Magic method
     *
     * @param string $key
     * @param array|string $value
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Get values
     *
     * @param string $key
     * @param array|string $default
     * 
     * @return array|string
     */
    public function get($key, $default=null)
    {
        if (array_key_exists($key, $this->_var)) {
            return $this->_var[$key];
        } else {
            $value = $default;
            switch ($key) {
            case 'page':
                if (isset($_GET['urls']) && is_numeric($_GET['urls'])) {
                    $value  = intval($_GET['urls']);
                } else if (isset($_GET['page']) && is_numeric($_GET['page'])) {
                    $value  = intval($_GET['page']) - 1;
                }
                break;
            case 'limit':
                if (isset($_GET['max']) && is_numeric($_GET['max'])) {
                    $value = intval($_GET['max']);
                } else if (isset($_GET['limit']) && is_numeric($_GET['limit'])) {
                    $value = intval($_GET['limit']);
                }
                break;
            case 'disables':
                if (isset($_GET['disables'])) {
                    $value = explode(',', $_GET['disables']);
                }
                $value = (array)$value;
                break;
            case 'request_uri':
                $value = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
                if (defined('SHIFTER_REST_REQUEST') && SHIFTER_REST_REQUEST) {
                    $value = str_replace(
                        trailingslashit('/wp-json/'.self::REST_ENDPOINT.self::REST_PATH),
                        '/',
                        $value
                    );
                }
                $value = esc_html(self::link_normalize($value));
                break;
            case 'request_path':
                $value = preg_replace(
                    '#^https?://[^/]+/#',
                    '/',
                    $this->get('request_uri')
                );
                break;
            case 'home_url':
                $value = trailingslashit(home_url('/'));
                break;
            case 'home_urls':
                $value = [
                    'home' => $this->get_home_url(),
                ];
                break;
            case 'feed_urls':
                $value = $this->_get_feed_urls();
                break;
            case 'front_page_posts':
                $value = $this->_get_front_page_posts();
                break;
            case 'current_url':
                $value = home_url($this->get('request_path'));
                break;
            case 'urls':
                $value = $this->_default_urls_array();
                $this->set_url_count(isset($value['items']) ? count($value['items']) : 0);
                break;
            case 'urls_all':
                $value = $this->_get_urls_all();
                $this->set_url_count(isset($value['items']) ? count($value['items']) : 0);
                break;
            case 'urls_404':
                $value = $this->_get_urls_404();
                $this->set_url_count(isset($value['items']) ? count($value['items']) : 0);
                break;
            case 'urls_archive':
                $value = $this->_get_urls_archive();
                $this->set_url_count(isset($value['items']) ? count($value['items']) : 0);
                break;
            case 'urls_singular':
                $value = $this->_get_urls_singular();
                $this->set_url_count(isset($value['items']) ? count($value['items']) : 0);
                break;
            case 'pages_per_page':
                $value = intval(get_option('posts_per_page'));
                break;
            case 'post_types':
                $value = get_post_types(['public' => true], 'names');
                break;
            case 'feed_types':
                $value = [
                    'rdf_url',
                    'rss_url',
                    'rss2_url',
                    'atom_url',
                    'comments_rss2_url'
                ];
                break;
            case 'archive_types':
                $value = [
                    'yearly',
                    'monthly',
                    'daily'
                ];
                break;
            default:
                $value = get_option($key);
            }
            if ($value) {
                $this->set($key, $value);
            }
            return $value;
        }
    }

    /**
     * Set values
     *
     * @param string $key
     * @param array|string|integer $value
     */
    private function set($key, $value)
    {
        $this->_var[$key] = $value;
        if ('start' === $key && $value === 0) {
            $this->_paths([], true);
        }
    }

    /**
     * Increment values
     *
     * @param string $key
     * @param integer $inc
     */
    private function increment($key, $inc=1)
    {
        if (array_key_exists($key, $this->_var)) {
            if (is_numeric($this->_var[$key])) {
                $this->_var[$key] += $inc;
            }
        } else {
            $this->_var[$key] = $inc;
        }
        return $this->_var[$key];
    }

    /**
     * Get post ID from URL
     *
     * @param string $request_path
     * 
     * @return integer post id
     */
    protected function _get_postid_from_url($request_path)
    {
        $request_path  = preg_replace(
            '#^https?://[^/]+/#',
            '/',
            $request_path
        );
        $key = __METHOD__."-{$request_path}";
        if (false === ($post_id = $this->_get_transient($key))) {
            $post_id = url_to_postid(home_url($request_path));
            $this->_set_transient($key, $post_id);
        }
        return $post_id;
    }

    /**
     * Get current URL type
     *
     * @param string  $request_path
     * @param boolean $rest_request
     *
     * @return string
     */
    public function current_url_type($request_path=null, $rest_request=false)
    {
        if (!$request_path) {
            $request_path  = $this->get_request_path();
        }
        $current_url_type = self::URL_404;
        if (!$rest_request) {
            if (preg_match('#/'.preg_quote(self::PATH_404_HTML).'/?$#i', $request_path) || is_404()) {
                $current_url_type = self::URL_404;
            } else if (is_front_page() && '/' === $request_path) {
                $current_url_type = self::URL_TOP;
            } else if (is_singular()) {
                $current_url_type = self::URL_SINGULAR;
            } else {
                $current_url_type = self::URL_ARCHIVE;
            }
        } else {
            if (preg_match('#/'.preg_quote(self::PATH_404_HTML).'/?$#i', $request_path)) {
                $current_url_type = self::URL_404;
            } else if ('/' === $request_path) {
                $current_url_type = self::URL_TOP;
            } else if ($this->_get_postid_from_url($request_path)) {
                $current_url_type = self::URL_SINGULAR;
            } else {
                $current_url_type = self::URL_ARCHIVE;
            }
        }
        return $current_url_type;
    }

    /**
     * Get all URLs
     *
     * @return array
     */
    protected function _get_urls_all()
    {
        $urls = $this->_default_urls_array();

        // top page
        if (!$this->_check_skip('top')) {
            $this->_top_page_urls($urls, $this->_check_skip('top_pagenate'));
        }

        // feed links
        if (!$this->_check_skip('feed')) {
            $this->_feed_urls($urls);
        }

        // posts links
        if (!$this->_check_skip('singular')) {
            $this->_posts_urls($urls);
        }

        // archive links
        if (!$this->_check_skip('archive')) {
            if (!$this->_check_skip('post_archive')) {
                $this->_post_type_archive_urls($urls);   // archive links
            }
            if (!$this->_check_skip('term_archive')) {
                $this->_post_type_term_urls($urls);      // term links
            }
            if (!$this->_check_skip('date_archive')) {
                $this->_archive_urls($urls);             // date archives
            }
            if (!$this->_check_skip('author_archive')) {
                $this->_authors_urls($urls);             // authors link
            }
        }

        // redirection links
        if (!$this->_check_skip('redirection')) {
            $this->_redirection_urls($urls);
        }

        $urls['request_type'] = self::URL_TOP;
        $urls['request_path'] = $this->get_request_path();
        $urls['count'] = count($urls['items']);
        $urls['finished'] = $urls['count'] < $this->get_limit();
        if ($urls['finished']) {
            $this->_paths([], true);
        }
        return $urls;
    }

    /**
     * Get 404 URLs
     *
     * @return array
     */
    private function _get_urls_404()
    {
        $urls = $this->_default_urls_array();
        $urls['items'] = [];
        $urls['request_type'] = self::URL_404;
        $urls['request_path'] = $this->get_request_path();
        $urls['count'] = count($urls['items']);
        $urls['finished'] = $urls['count'] < $this->get_limit();
        return $urls;
    }

    /**
     * Get archive page URLs
     *
     * @return array
     */
    private function _get_urls_archive()
    {
        $request_uri  = $this->get_request_uri();
        $urls = $this->_default_urls_array();
        $this->_pagenate_urls($urls, $request_uri);  // pagenate links
        $urls['request_type'] = self::URL_ARCHIVE;
        $urls['request_path'] = $this->get_request_path();
        $urls['count'] = count($urls['items']);
        $urls['finished'] = $urls['count'] < $this->get_limit();
        return $urls;
    }

    /**
     * Get single page URLs
     *
     * @return array
     */
    private function _get_urls_singular()
    {
        $request_uri  = $this->get_request_uri();
        $urls = $this->_default_urls_array();
        $this->_singlepage_pagenate_urls($urls, $request_uri);   // single page links
        $urls['request_type'] = self::URL_SINGULAR;
        $urls['request_path'] = $this->get_request_path();
        $urls['count'] = count($urls['items']);
        $urls['finished'] = $urls['count'] < $this->get_limit();
        return $urls;
    }

    /**
     * Get pagenate URLs
     *
     * @param string  $base_url
     * @param integer $total_posts
     * 
     * @return array
     */
    static public function get_paginates($base_url, $total_posts)
    {
        $urls = [];
        $posts_per_page = get_option('posts_per_page');
        if ($posts_per_page === 0) {
            return $urls;
        }
        $num_of_pages = ceil(intval($total_posts) / $posts_per_page);
        $pagenate_links = paginate_links(
            [
                'base'     => "{$base_url}%_%",
                'format'   => 'page/%#%/',
                'total'    => $num_of_pages,
                'show_all' => true,
            ]
        );
        if (preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $pagenate_links, $pg_matches, PREG_SET_ORDER)) {
            foreach ( $pg_matches as $pg_match ) {
                $urls[] = self::link_normalize($pg_match[1]);
            }
        }
        unset($pg_matches);
        return $urls;
    }

    /**
     * Get default URLs array
     *
     * @return array
     */
    private function _default_urls_array()
    {
        return [
            'datetime'     => date('Y-m-d H:i:s T'),
            'page'         => $this->get('page'),
            'start'        => $this->get('start'),
            'end'          => $this->get('end'),
            'limit'        => $this->get('limit'),
            'items'        => [],
            'request_type' => '',
            'request_path' => '/',
            'count'        => 0,
            'finished'     => false,
        ];
    }

    /**
     * Is range?
     *
     * @param integer|boolean $url_count
     * @param integer|boolean $start_position
     * @param integer|boolean $end_position
     * 
     * @return boolean
     */
    private function _check_range($url_count=false, $start_position=false, $end_position=false)
    {
        if ($url_count === false) {
            $url_count = $this->get('url_count');
        }
        if ($start_position === false) {
            $start_position = $this->get('start');
        }
        if ($end_position === false) {
            $end_position = $this->get('end');
        }
        return (
            $url_count >= $start_position && 
            !$this->_check_final($url_count, $end_position)
        );
    }

    /**
     * Is final?
     *
     * @param integer|boolean $url_count
     * @param integer|boolean $end_position
     *
     * @return boolean
     */
    private function _check_final($url_count=false, $end_position=false)
    {
        if ($url_count === false) {
            $url_count = $this->get('url_count');
        }
        if ($end_position === false) {
            $end_position = $this->get('end');
        }
        return ($url_count >= $end_position);
    }

    /**
     * Is skip?
     *
     * @param string $key
     * 
     * @return boolean
     */
    private function _check_skip($key)
    {
        $result = get_option('shifter_skip_'.$key) === 'yes';
        $disables = $this->get('disables', []);
        if (in_array($key, $disables)) {
            $result = true;
        }
        return $result;
    }

    /**
     * Is correct link?
     *
     * @param string $link
     * 
     * @return boolean
     */
    private function _check_link_format($link)
    {
        if (!$link || trailingslashit($link) === trailingslashit($this->get('home_url'))) {
            return false;
        }
        if (!preg_match('#/$#', $link)) {
            return false;
        }
        return true;
    }

    /**
     * Get URLs item
     *
     * @param string  $link_type
     * @param string  $post_type
     * @param string  $link
     * @param string  $redirect_action
     * @param integer $redirect_code
     * 
     * @return array
     */
    protected function _urls_item($link_type, $post_type='', $link='', $redirect_action=null, $redirect_code=null)
    {
        $item = [
            'link_type' => $link_type,
            'post_type' => $post_type,
            'link'      => $link,
            'path'      => preg_replace('#^https?://[^/]+/#', '/', $link),
        ];
        if ($redirect_action) {
            $item['redirect_to'] = $redirect_action;
        }
        if ($redirect_code) {
            $item['redirect_code'] = $redirect_code;
        }
        return $item;
    }

    /**
     * Get transient cache
     *
     * @param string                $transient_key
     * @param string|array|boolean  $default
     *
     * @return string|array|boolean
     */
    private function _get_transient($transient_key, $default=false)
    {
        $transient_key = __CLASS__."-{$transient_key}";
        $value = get_transient($transient_key);
        return $value ? $value : $default;
    }

    /**
     * Set transient cache
     *
     * @param string  $transient_key
     * @param string|array|integer  $value
     * 
     * @return boolean
     */
    private function _set_transient($transient_key, $value)
    {
        $transient_key = __CLASS__."-{$transient_key}";
        return set_transient(
            $transient_key,
            $value,
            intval($this->get('transient_expires'))
        );
    }

    /**
     * Delete transient cache
     *
     * @param string  $transient_key
     * 
     * @return boolean
     */
    private function _delete_transient($transient_key)
    {
        $transient_key = __CLASS__."-{$transient_key}";
        return delete_transient($transient_key);
    }

    /**
     * Normalize link value
     *
     * @param string  $link
     * 
     * @return string
     */
    static public function link_normalize($link, $remove_index_html = false)
    {
        $link = remove_query_arg(
            ['urls','max','page','limit', 'disables'],
            str_replace('&#038;', '&', $link)
        );
        if (defined('SHIFTER_REST_REQUEST') && SHIFTER_REST_REQUEST) {
            $link = str_replace(
                trailingslashit('/wp-json/'.self::REST_ENDPOINT.self::REST_PATH),
                '/',
                $link
            );
        }
        if ($remove_index_html && preg_match('#/index\.html?$#', $link)) {
            $link = preg_replace('#/index\.html?$#', '/', $link);
        }
        return $link;
    }

    /**
     *
     * @param array  $paths_new
     * @param boolean  $init
     * 
     * @return array
     */
    private function _paths($paths_new=[],$init=false)
    {
        if ($init) {
            $this->_delete_transient('paths');
            if (!empty($paths_new)) {
                $this->_set_transient('paths', (array)$paths_new);
            }
            return (array)$paths_new;
        } else {
            $paths = $this->_get_transient('paths', []);
            if (!empty($paths_new)) {
                $paths = array_merge((array)$paths_new, $paths);
            }
            $this->_set_transient('paths', $paths);
            return $paths;
        }
    }

    /**
     * Added item to URLs array
     *
     * @param array   $urls
     * @param array   $new_urls
     * @param string  $link_type
     * @param string  $post_type
     * @param string  $redirect_action
     * @param integer $redirect_code
     *
     * @return integer
     */
    private function _add_urls(&$urls=array(), $new_urls=array(), $link_type='', $post_type='', $redirect_action=null, $redirect_code=null)
    {
        if ($this->_check_final()) {
            return self::FINAL;
        }

        foreach ((array)$new_urls as $new_url) {
            if (preg_match('#^/#', $new_url)) {
                $new_url = home_url($new_url);
            }
            $path = preg_replace('#^https?://[^/]+/#', '/', $new_url);
            if ('home' === $link_type || '404' === $link_type || $this->_check_link_format($new_url)) {
                if ($this->_check_range()) {
                    $url_item = $this->_urls_item(
                        (string)$link_type,
                        $post_type,
                        $new_url,
                        $redirect_action,
                        $redirect_code
                    );
                    $urls['items'][] = $url_item;
                    $this->_paths($path);
                }
                if ($this->_check_final()) {
                    return self::FINAL;
                }
                $this->increment('url_count');
            }
        }

        return self::NOT_FINAL;
    }

    /**
     * Init URLs array
     *
     * @param array   $urls
     * 
     * @return integer
     */
    private function _urls_init(&$urls = array()){
        if (empty($urls)) {
            $urls = $this->get('urls');
        }
        if ($this->_check_final()) {
            return self::FINAL;
        }
        return self::NOT_FINAL;
    }

    protected function _get_permalink($post_id,$post_type,$amp_permalink=false)
    {
        $amp_permalink = $amp_permalink && function_exists('amp_get_permalink');
        $key = __METHOD__."-{$post_type}-permalink-{$post_id}".($amp_permalink ? '-amp' : '');
        if (false === ($permalink = $this->_get_transient($key))) {
            $permalink = 
                !$amp_permalink
                ? get_permalink($post_id)
                : amp_get_permalink($post_id);
            $this->_set_transient($key, $permalink);
        }
        $permalink = preg_replace('/#[^#]*$/', '', $permalink);
        return $permalink;
    }

    /**
     * Get top page URLs
     *
     * @param array   $urls
     *
     * @return integer
     */
    protected function _top_page_urls(&$urls = array(), $skip_top_pagenate=false)
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        // home
        $home_urls = $this->get('home_urls');
        foreach ($home_urls as $url_type => $url) {
            $added = $this->_add_urls(
                $urls,
                (array)$url,
                (string)$url_type,
                ''
            );
            if (self::FINAL === $added) {
                break;
            }

            // Front pagenate links
            if (!$skip_top_pagenate) {
                query_posts('');
                if ('posts' === $this->get('show_on_front')) {
                    $this->_pagenate_urls($urls, $url);
                } else {
                    $this->_pagenate_urls_page_on_front($urls, $url);
                    $this->_pagenate_urls_page_for_posts($urls, $url);
                }
                wp_reset_query();
            }
        }

        // 404
        $home_urls['404'] = $this->get('home_url').self::PATH_404_HTML;
        $added = $this->_add_urls(
            $urls,
            (array)$home_urls['404'],
            (string)'404',
            ''
        );

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    protected function _feed_urls(&$urls = array())
    {
        $added = null;
        // feed
        if (!$this->_check_final()) {
            foreach ($this->get('feed_urls') as $feed_type => $feed_link) {
                if ($this->_check_link_format($feed_link)) {
                    $added = $this->_add_urls(
                        $urls,
                        (array)$feed_link,
                        'feed',
                        $feed_type
                    );
                }
                if (self::FINAL === $added) {
                    break;
                }
            }
        }
        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get Feed URLs
     *
     * @return array
     */
    protected function _get_feed_urls()
    {
        $feed_urls = [];
        foreach ($this->get_feed_types() as $feed_type) {
            $feed_link = trailingslashit(get_bloginfo($feed_type));
            $feed_urls[$feed_type] = $feed_link;
        }
        return $feed_urls;
    }

    protected function _get_posts($post_type)
    {
        global $wpdb;

        $sql =
            "SELECT ID,post_type,post_content".
            " FROM {$wpdb->posts}".
            " WHERE post_type=%s AND post_status=%s".
            " ORDER BY post_date DESC"
            ;
        $sql = $wpdb->prepare(
            $sql,
            $post_type,
            $post_type !== 'attachment' ? 'publish' : 'inherit'
        );
        $posts = $wpdb->get_results($sql, OBJECT);
        return $posts;
    }

    /**
     * Get post parmalink URLs
     *
     * @param array   $urls
     *
     * @return integer
     */
    protected function _posts_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        foreach ($this->get_post_types() as $post_type) {
            if ($this->_check_skip($post_type)) {
                continue;
            }

            $key = __METHOD__."-{$post_type}";
            if (false === ($posts = $this->_get_transient($key))) {
                $posts = $this->_get_posts($post_type);
                $this->_set_transient($key, $posts);
            }

            foreach ($posts as $post) {
                $permalink = $this->_get_permalink($post->ID, $post_type);
                if (trailingslashit($permalink) !== trailingslashit($this->get('home_url'))) {
                    if (!$this->_check_link_format($permalink)) {
                        continue;
                    }
                    $added = $this->_add_urls(
                        $urls,
                        (array)$permalink,
                        'permalink',
                        $post_type
                    );
                    if (self::FINAL === $added) {
                        break;
                    }
                }
    
                // Force ignnore attachment.
                if ($post_type === 'attachment') {
                    continue;
                }

                // has <!--nexpage--> ?
                $pagenate_links = $this->_has_pages($post, $permalink);
                if (!empty($pagenate_links)) {
                    $added = $this->_add_urls(
                        $urls,
                        (array)$pagenate_links,
                        'paginate_link',
                        $post_type
                    );
                    if (self::FINAL === $added) {
                        break;
                    }
                }
                unset($pagenate_links);

                // Force ignnore page.
                if ($post_type === 'page') {
                    continue;
                }

                // Detect Automattic AMP
                if (!$this->_check_skip('amp') && class_exists('AMP_Options_Manager')) {
                    // supported_post_types is empty until first saved the setting.
                    $amp_supported =
                        AMP_Options_Manager::get_option('supported_post_types')
                        ? AMP_Options_Manager::get_option('supported_post_types')
                        : ['post'];
                    // Skip password_protected or other known errors.
                    $support_errors_codes = AMP_Post_Type_Support::get_support_errors($post->ID);
                    if (!sizeof($support_errors_codes) > 0) {
                        if (in_array($post_type, (array)$amp_supported)) {
                            if (post_supports_amp($post)) {
                                $amp_permalink = $this->_get_permalink($post->ID, $post_type, true);
                                $added = $this->_add_urls(
                                    $urls,
                                    [$amp_permalink],
                                    'amphtml',
                                    $post_type
                                );
                                if (self::FINAL === $added) {
                                    break;
                                }
                            }
                        }
                    }
                }
            }
            unset($posts);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    protected function _get_post_type_archive_link($post_type)
    {
        $key = __METHOD__."-{$post_type}";
        if (false === ($post_type_archive_link = $this->_get_transient($key))) {
            $post_type_archive_link = get_post_type_archive_link($post_type);
            $this->_set_transient($key, $post_type_archive_link);
        }
        return $post_type_archive_link;
    }

    /**
     * Get post archive URLs
     *
     * @param array   $urls
     *
     * @return integer
     */
    protected function _post_type_archive_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        foreach ($this->get_post_types() as $post_type) {
            if ($this->_check_skip($post_type)) {
                continue;
            }

            $post_type_archive_link = $this->_get_post_type_archive_link($post_type);
            if (!$this->_check_link_format($post_type_archive_link)) {
                continue;
            }
            $added = $this->_add_urls(
                $urls,
                (array)$post_type_archive_link,
                'post_type_archive_link',
                $post_type
            );
            if (self::FINAL === $added) {
                break;
            }

            // pagenate links
            $key = __METHOD__."-{$post_type}-pagenate";
            if (false === ($pagenate_urls = $this->_get_transient($key))) {
                $posts = $this->_get_posts($post_type);
                $pagenate_urls = self::get_paginates($post_type_archive_link, count($posts));
                $this->_set_transient($key, $pagenate_urls);
                unset($posts);
            }
            $added = $this->_add_urls(
                $urls,
                (array)$pagenate_urls,
                'post_type_archive_link',
                $post_type
            );
            if (self::FINAL === $added) {
                break;
            }
            unset($pagenate_urls);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get term_taxonomy slugs
     *
     * @param integer   $term_taxonomy_id
     * @param array     $slugs
     * 
     * @return array
     */
    protected function _get_term_taxonomy_children($term_taxonomy_id, $slugs = [])
    {
        global $wpdb;

        $sql =
            "SELECT tt.term_taxonomy_id,t.slug".
            " FROM {$wpdb->terms} AS t".
            " INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id".
            " WHERE tt.parent=%d"
            ;
        $sql = $wpdb->prepare(
            $sql,
            $term_taxonomy_id
        );
        $results = $wpdb->get_results($sql, ARRAY_N);
        if (!is_wp_error($results) && !empty($results)) {
            foreach ($results as $term_taxonomy) {
                $slugs[$term_taxonomy[0]] = $term_taxonomy[1];
                $slugs = $this->_get_term_taxonomy_children(
                    $term_taxonomy[0],
                    $slugs
                );
            }
        }
        unset($results);
        return $slugs;
    }

    protected function _get_object_taxonomies($post_type)
    {
        return get_object_taxonomies($post_type);
    }

    protected function _get_terms($taxonomy_name, $arg='')
    {
        return get_terms($taxonomy_name, $arg);
    }

    protected function _get_term_link($term)
    {
        return get_term_link($term);
    }

    protected function _get_posts_from_term($term, $slugs, $post_type)
    {
        $posts = get_posts(
            [
                'tax_query' =>
                [
                    [
                        'taxonomy' => $term->taxonomy,
                        'field'    => 'slug',
                        'terms'    => $slugs,
                    ]
                ],
                'post_type' => $post_type,
                'post_status' => 'publish',
                'posts_per_page' => -1
            ]
        );
        return $posts;
    }

    /**
     * Get term_taxonomy slugs
     *
     * @param \WP_Term  $term
     *
     * @return array
     */
    protected function _get_term_taxonomy_slugs($term)
    {
        global $wpdb;

        $slugs = [];
        $sql =
            "SELECT tt.term_taxonomy_id,t.slug".
            " FROM {$wpdb->terms} AS t".
            " INNER JOIN {$wpdb->term_taxonomy} AS tt ON t.term_id = tt.term_id".
            " WHERE tt.term_taxonomy_id=%d"
            ;
        $sql = $wpdb->prepare(
            $sql,
            $term->term_taxonomy_id
        );
        $results = $wpdb->get_results($sql, ARRAY_N);
        if (!is_wp_error($results) && !empty($results)) {
            foreach ($results as $term_taxonomy) {
                $slugs[$term_taxonomy[0]] = $term_taxonomy[1];
                $slugs = $this->_get_term_taxonomy_children(
                    $term_taxonomy[0],
                    $slugs
                );
            }
        }
        unset($results);
        return $slugs;
    }

    /**
     * Get term archive URLs
     *
     * @param array   $urls
     * 
     * @return integer
     */
    protected function _post_type_term_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        foreach ($this->get_post_types() as $post_type) {
            if ($this->_check_skip($post_type)) {
                continue;
            }

            $key = __METHOD__."-{$post_type}";
            if (false === ($taxonomy_names = $this->_get_transient($key))) {
                $taxonomy_names = $this->_get_object_taxonomies($post_type);
                $this->_set_transient($key, $taxonomy_names);
            }
            foreach ($taxonomy_names as $taxonomy_name) {
                if ($this->_check_skip(str_replace($post_type.'_', '', $taxonomy_name))) {
                    continue;
                }
                $key = __METHOD__."-{$post_type}-{$taxonomy_name}";
                if (false === ($terms = $this->_get_transient($key)) ) {
                    $terms = $this->_get_terms($taxonomy_name, 'orderby=count&hide_empty=1');
                    $this->_set_transient($key, $terms);
                }

                foreach ($terms as $term) {
                    $termlink = $this->_get_term_link($term);
                    if (!$this->_check_link_format($termlink)) {
                        continue;
                    }
                    $added = $this->_add_urls(
                        $urls,
                        (array)$termlink,
                        'term_link',
                        $term->slug
                    );
                    if (self::FINAL === $added) {
                        break;
                    }

                    // pagenate links
                    $key = __METHOD__."-{$term->term_taxonomy_id}-pagenate";
                    if (false === ($pagenate_urls = $this->_get_transient($key))) {
                        $pagenate_urls = [];
                        $slugs = $this->_get_term_taxonomy_slugs($term);
                        if (!empty($slugs)) {
                            $posts = $this->_get_posts_from_term($term, $slugs, $post_type);
                            $pagenate_urls = self::get_paginates($termlink, count($posts));
                            unset($posts);
                        }
                        unset($slugs);
                        $this->_set_transient($key, $pagenate_urls);
                    }
                    $added = $this->_add_urls(
                        $urls,
                        (array)$pagenate_urls,
                        'term_link',
                        $term->slug
                    );
                    if (self::FINAL === $added) {
                        break;
                    }
                    unset($pagenate_urls);
                }
            }
            unset($taxonomy_names);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    protected function _get_archive_lists($archive_type)
    {
        $archives_lists = wp_get_archives(
            [
                'type'   => $archive_type,
                'format' => 'none',
                'echo'   => 0,
                'show_post_count' => true
            ]
        );
        preg_match_all('/href=["\']([^"\']*)["\'].+\((\d+)\)/', $archives_lists, $matches, PREG_SET_ORDER);
        $archives_lists = [];
        foreach ((array)$matches as $match) {
            $archive_link = self::link_normalize($match[1]);
            $archives_lists[] = $archive_link;
            if (intval($match[2]) > $this->get_pages_per_page()) {
                $pagenate_urls = self::get_paginates($archive_link, intval($match[2]));
                foreach ($pagenate_urls as $pagenate_url) {
                    if ($pagenate_url !== $archive_link) {
                        $archives_lists[] = $pagenate_url;
                    }
                }
            }
        }
        unset($matches);
        return $archives_lists;
    }

    /**
     * Get archive URLs
     *
     * @param array   $urls
     * 
     * @return integer
     */
    protected function _archive_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        foreach ($this->get_archive_types() as $archive_type) {
            if ($this->_check_skip($archive_type)) {
                continue;
            }

            $key = __METHOD__."-{$archive_type}";
            if (false === ($archives_lists = $this->_get_transient($key))) {
                $archives_lists = $this->_get_archive_lists($archive_type);
                $this->_set_transient($key, $archives_lists);
            }
            $added = $this->_add_urls(
                $urls,
                (array)$archives_lists,
                'archive_link',
                $archive_type
            );
            if (self::FINAL === $added) {
                break;
            }
            unset($archives_lists);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get paginate URLs
     *
     * @param string  $request_uri
     *
     * @return array
     */
    protected function _get_pagenate_urls($request_uri='/')
    {
        $paginate_links = [];

        $request_uri = preg_replace('#https?://[^/]+/#', '/', $request_uri);
        if (preg_match('#/page/[0-9]+/$#', $request_uri)) {
            return [];
        }
        $current_page = max(1, get_query_var('paged'));
        if ($current_page > 1) {
            return [];
        }

        $key = __METHOD__."-{$request_uri}";
        if (false === ($paginate_links = $this->_get_transient($key))) {
            preg_match_all(
                '/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/',
                paginate_links(['show_all'=> true]),
                $matches,
                PREG_SET_ORDER
            );
            $paginate_links = [];
            foreach ((array)$matches as $match) {
                $paginate_links[] = self::link_normalize($match[1]);
            }
            unset($matches);
            $this->_set_transient($key, $paginate_links);
        }
        return $paginate_links;
    }

    protected function _pagenate_urls(&$urls = array(), $request_uri='/')
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        $request_uri = preg_replace('#https?://[^/]+/#', '/', $request_uri);
        if (preg_match('#/page/[0-9]+/$#', $request_uri)) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }
        $current_page = max(1, get_query_var('paged'));
        if ($current_page > 1) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        $paginate_links = $this->_get_pagenate_urls(trailingslashit(home_url($request_uri)));
        $added = $this->_add_urls(
            $urls,
            (array)$paginate_links,
            'paginate_link',
            ''
        );
        unset($paginate_links);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    protected function _has_pages($post, $permalink)
    {
        // has <!--nexpage--> ?
        $key = __METHOD__."-{$post->post_type}-permalink-{$post->ID}-nextpages";
        if (false === ($pg_matches = $this->_get_transient($key))) {
            $pagenate_format = '%#%/';
            if (trailingslashit($permalink) === trailingslashit($this->get('home_url'))) {
                $pagenate_format = 'page/%#%/';
            }

            $pcount = mb_substr_count($post->post_content, '<!--nextpage-->');
            $pagenate_links = paginate_links(
                [
                    'base'     => "{$permalink}%_%",
                    'format'   => $pagenate_format,
                    'total'    => $pcount + 1,
                    'show_all' => true,
                ]
            );
            $pg_matches = [];
            if (preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $pagenate_links, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $pg_matches[] = self::link_normalize($match[1]);
                }
            }
            unset($matches);
            $this->_set_transient($key, $pg_matches);
        }
        return $pg_matches;
    }

    protected function _get_front_page_posts()
    {
        global $wpdb;
        $post_id = intval($this->get('page_on_front'));
        if (!$post_id) {
            return [];
        }
        $sql =
            "SELECT ID,post_type,post_content".
            " FROM {$wpdb->posts}".
            " WHERE ID=%d"
            ;
        $sql = $wpdb->prepare(
            $sql,
            $post_id
        );
        $post = $wpdb->get_results($sql);
        return [$post];
    }

    /**
     * Get paginate URLs (Front page)
     *
     * @param array   $urls
     * @param string  $request_uri
     *
     * @return integer
     */
    protected function _pagenate_urls_page_on_front(&$urls = array(), $request_uri='/')
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        foreach ($this->get('front_page_posts') as $post) {
            $permalink = $this->_get_permalink($post->ID, $post->post_type);
            if (trailingslashit($permalink) !== trailingslashit($this->get('home_url'))) {
                if (!$this->_check_link_format($permalink)) {
                    return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
                }
                $added = $this->_add_urls(
                    $urls,
                    (array)$permalink,
                    'permalink',
                    $post->post_type
                );
                if (self::FINAL === $added) {
                    $this->set('urls', $urls);
                    return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
                }
            }

            // has <!--nexpage--> ?
            $pagenate_links = $this->_has_pages($post, $permalink);
            if (!empty($pagenate_links)) {
                $added = $this->_add_urls(
                    $urls,
                    (array)$pagenate_links,
                    'paginate_link',
                    $post->post_type
                );
                $this->set('urls', $urls);
                if (self::FINAL === $added) {
                    return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
                }
            }
            unset($pagenate_links);

            if ($this->_check_final()) {
                return self::FINAL;
            }
        }
        return self::NOT_FINAL;
    }

    protected function _posts_count_from_post_type($post_type)
    {
        global $wpdb;
        $sql =
            "SELECT count(*)".
            " FROM {$wpdb->posts}".
            " WHERE post_type=%s AND post_status=%s"
            ;
        $sql = $wpdb->prepare(
            $sql,
            $post_type,
            'publish'
        );
        $posts_count = $wpdb->get_var($sql);
        return $posts_count;
    }

    protected function _posts_count_from_author($author_name, $post_type='post')
    {
        global $wpdb;
        $sql =
            "SELECT count(*)".
            " FROM {$wpdb->posts}".
            " WHERE post_status=%s".
            " AND post_type=%s".
            " AND post_author =".
            " (SELECT ID FROM {$wpdb->users} WHERE user_login=%s)"
            ;
        $sql = $wpdb->prepare(
            $sql,
            'publish',
            $post_type,
            $author_name
        );
        $posts_count = $wpdb->get_var($sql);
        return $posts_count;
    }

    /**
     * Get paginate URLs (Front page)
     *
     * @param array   $urls
     * @param string  $request_uri
     *
     * @return integer
     */
    protected function _pagenate_urls_page_for_posts(&$urls = array(), $request_uri='/')
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        $archive_base = '/';
        $post_id = intval($this->get('page_for_posts'));
        if (!$post_id) {
            return self::NOT_FINAL;
        }

        $key = __METHOD__."-page_for_posts";
        if (false === ($archives_lists = $this->_get_transient($key))) {
            $post = get_post($post_id);
            $permalink = $this->_get_permalink($post_id, $post->post_type);
            $archive_base = preg_replace('#https?://[^/]+/#', '/', $permalink);

            $archives_lists = [$archive_base];
            $posts_count = $this->_posts_count_from_post_type('post');
            $pagenate_urls = self::get_paginates($archive_base, $posts_count);
            foreach ($pagenate_urls as $pagenate_url) {
                $archives_lists[] = $pagenate_url;
            }
            $this->_set_transient($key, $archives_lists);
        }
        $added = $this->_add_urls(
            $urls,
            (array)$archives_lists,
            'paginate_link',
            'post'
        );
        unset($archives_lists);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    protected function _get_authors_links()
    {
        $authors_links = [];
        $args = [
            'style' => 'none',
            'echo' => false,
            'exclude_admin' => false,
        ];
        preg_match_all(
            '/href=["\']([^"\']*)["\']/',
            wp_list_authors($args),
            $matches,
            PREG_SET_ORDER
        );
        foreach ((array)$matches as $match) {
            $authors_link = self::link_normalize($match[1]);
            $authors_links[] = $authors_link;
            $author_name = preg_replace('#^.*/([^/]+)/?$#', '$1', $authors_link);
            $post_count = $this->_posts_count_from_author($author_name);
            $pagenate_urls = self::get_paginates($authors_link, $post_count);
            foreach ($pagenate_urls as $pagenate_url) {
                $authors_links[] = $pagenate_url;
            }
        }
        unset($matches);
        return $authors_links;
    }

    /**
     * Get author archive URLs
     *
     * @param array   $urls
     *
     * @return integer
     */
    protected function _authors_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        } else if ($this->_check_skip('author')) {
            return self::NOT_FINAL;
        }

        $key = __METHOD__;
        if (false === ($authors_links = $this->_get_transient($key))) {
            $authors_links = $this->_get_authors_links();
            $this->_set_transient($key, $authors_links);
        }
        $added = $this->_add_urls(
            $urls,
            (array)$authors_links,
            'author_link',
            ''
        );
        unset($authors_links);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    protected function _get_redirection_list()
    {
        $key = __METHOD__;
        if (false === ($redirection_list = $this->_get_transient($key))) {
            $redirection_list = class_exists('Red_Item') ? Red_Item::get_all() : [];
            $this->_set_transient($key, $redirection_list);
        }
        return $redirection_list;
    }

    /**
     * Get redirection URLs
     *
     * @param array $urls
     *
     * @return integer
     */
    protected function _redirection_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        } else if ($this->_check_skip('redirection')) {
            return self::NOT_FINAL;
        }

        foreach ($this->_get_redirection_list() as $redirection) {
            if (!$redirection->is_enabled() || $redirection->is_regex()) {
                continue;
            }

            $redirection_link = trailingslashit(
                self::link_normalize($redirection->get_url(), true)
            );
            if ($redirection_link === $this->get('home_url')) {
                continue;
            }
            if (!$this->_check_link_format($redirection_link)) {
                continue;
            }

            $redirect_action = maybe_unserialize($redirection->get_action_data());
            if (is_array($redirect_action)) {
                foreach (['logged_out','url_notfrom'] as $key) {
                    if (isset($redirect_action[$key])) {
                        $redirect_action = $redirect_action[$key];
                        break;
                    }
                }
            }
            if (is_array($redirect_action) || empty($redirect_action)) {
                continue;
            }
            if (!preg_match('#^(https?://|/)#i', $redirect_action)) {
                $redirect_action = '/'.$redirect_action;
            }

            $redirect_code = (int)$redirection->get_action_code();
            if ($redirect_code < 300 || $redirect_code > 400) {
                continue;
            }
            $added = $this->_add_urls(
                $urls,
                (array)$redirection_link,
                'redirection',
                '',
                $redirect_action,
                $redirect_code
            );
            if (self::FINAL === $added) {
                break;
            }
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get singlepage pagenate URLs
     *
     * @param array  $urls
     * @param string $request_path
     * 
     * @return integer
     */
    protected function _singlepage_pagenate_urls(&$urls = array(), $request_path='/')
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        $request_path = preg_replace('#https?://[^/]+/#', '/', $request_path);
        if (preg_match('#/page/[0-9]+/$#', $request_path)) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        global $post;
        $post_id = $this->_get_postid_from_url($request_path);
        $post = get_post($post_id);
        setup_postdata($post);

        $permalink = $this->_get_permalink($post_id, $post->post_type);
        $permalink_path = preg_replace('#https?://[^/]+/#', '/', $permalink);
        $current_page = 1;
        if ($permalink_path !== $request_path) {
            $current_page = max(
                1,
                intval(preg_replace('#^.*?/(\d+)/?$#', '$1', $request_path))
            );
        }
        if ($current_page > 1) {
            return self::FINAL;
        }

        // pagenate links
        $paginate_links = wp_link_pages(['echo' => false]);
        if (preg_match_all('/href=["\']([^"\']*)["\']/', $paginate_links, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $paginate_link = self::link_normalize($match[1]);
                $page_number = 1;
                if ($permalink !== $paginate_link) {
                    $page_number = max(
                        1,
                        intval(preg_replace('#^.*?/(\d+)/?$#', '$1', $paginate_link))
                    );
                }
                if ($page_number === 1) {
                    continue;
                }
                if (!$this->_check_link_format($paginate_link)) {
                    continue;
                }
                $added = $this->_add_urls(
                    $urls,
                    (array)$paginate_link,
                    'paginate_link',
                    $post->post_type ? $post->post_type : ''
                );
                if (self::FINAL === $added) {
                    break;
                }
            }
        }
        unset($matches);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }
}
