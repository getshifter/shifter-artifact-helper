<?php
if (!defined('SHIFTER_URLS_CACHE_EXPIRES')) {
    define('SHIFTER_URLS_CACHE_EXPIRES', 300);
}

/**
 * Get URLs
 *
 * @param string  $request_path
 * @param boolean $rest_request
 * 
 * @return array|string
 */
function shifter_get_urls($request_path=null, $rest_request=false)
{
    if ($rest_request && '/'.ShifterUrls::PATH_404_HTML !== $request_path) {
        $request_path = trailingslashit($request_path);
    }
    $shifter_urls = ShifterUrls::get_instance();

    $page  = $shifter_urls->get_page(0);
    $limit = $shifter_urls->get_limit(100);
    $start = $page * $limit;

    $shifter_urls->set_url_count(0);
    $shifter_urls->set_transient_expires(intval(SHIFTER_URLS_CACHE_EXPIRES));
    $shifter_urls->set_start($start);
    $shifter_urls->set_end($start + $limit);
    if ($rest_request) {
        $shifter_urls->set_request_uri(home_url($request_path));
    }

    $json_data = [];
    switch ($shifter_urls->current_url_type($request_path, $rest_request)) {
    case ShifterUrls::URL_TOP:
        $json_data = $shifter_urls->get_urls_all();
        break;
    case ShifterUrls::URL_ARCHIVE:
        $json_data = $shifter_urls->get_urls_archive();
        break;
    case ShifterUrls::URL_SINGULAR:
        $json_data = $shifter_urls->get_urls_singular();
        break;
    case ShifterUrls::URL_404:
        $json_data = $shifter_urls->get_urls_404();
        break;
    default:
        $json_data = $shifter_urls->get_urls();
    }
    unset($shifter_urls);

    // For debug
    if ($json_data['count'] > 0) {
        error_log('');
        foreach ($json_data['items'] as $item) {
            error_log(json_encode($item));
        }
    }

    return $json_data;
}

class ShifterUrls
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

    const REST_ENDPOINT = 'shifter/v1';
    const REST_PATH     = '/urls';

    const URL_TOP = 'TOP';
    const URL_404 = '404';
    const URL_ARCHIVE = 'ARCHIVE';
    const URL_SINGULAR = 'SINGULAR';

    /**
     * Constructor
     *
     * @return nothing
     */
    private function __construct()
    {
    }

    /**
     * Get self instance
     *
     * @return object
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
     * @return array|string
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
     * @param array|strnig $value
     * 
     * @return nothing
     */
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    /**
     * Get values
     *
     * @param string $key
     * @param array|strnig $default
     * 
     * @return array|strnig
     */
    private function get($key, $default=null)
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
     * @param array|strnig $value
     * 
     * @return nothing
     */
    private function set($key, $value)
    {
        $this->_var[$key] = $value;
        if ('start' === $key && $value === 0) {
            $this->_paths(null, true);
        }
    }

    /**
     * Increment values
     *
     * @param string $key
     * @param integer $inc
     * 
     * @return nothing
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
    private function _get_postid_from_url($request_path)
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
    private function _get_urls_all()
    {
        $urls = $this->_default_urls_array();

        // top page & feed links
        if (!$this->_check_skip('top')) {
            $this->_top_page_urls($urls);
            // Front pagenate links
            if (!$this->_check_skip('top_pagenate')) {
                query_posts('');
                if ('posts' === get_option('show_on_front')) {
                    $this->_pagenate_urls($urls);
                } else {
                    $this->_pagenate_urls_page_on_front($urls);
                    $this->_pagenate_urls_page_for_posts($urls);
                }
                wp_reset_query();
            }
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
            $this->_paths(null, true);
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
    private function _get_paginates($base_url, $total_posts)
    {
        $urls = [];
        $pages_per_page = $this->get('pages_per_page');
        if ($pages_per_page === 0) {
            return $urls;
        }
        $num_of_pages = ceil(intval($total_posts) / $pages_per_page);
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
     * @param integer $url_count
     * @param integer $start_position
     * @param integer $end_position
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
     * @param integer $url_count
     * @param integer $end_position
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
    private function _urls_item($link_type, $post_type='', $link='', $redirect_action=null, $redirect_code=null)
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
     * @param string  $transient_key
     * @param string|array  $default
     * 
     * @return string|array
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
     * @param string|array  $value
     * 
     * @return boolean
     */
    private function _set_transient($transient_key, $value)
    {
        $transient_key = __CLASS__."-{$transient_key}";
        return set_transient(
            $transient_key,
            $value,
            $this->get('transient_expires')
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
    static public function link_normalize($link)
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
     * @return string
     */
    private function _add_urls(&$urls=array(), $new_urls=array(), $link_type='', $post_type='', $redirect_action=null, $redirect_code=null){
        if ($this->_check_final()) {
            return self::FINAL;
        }

        foreach ((array)$new_urls as $new_url) {
            if (preg_match('#^/#', $new_url)) {
                $new_url = home_url($new_url);
            }
            $path = preg_replace('#^https?://[^/]+/#', '/', $new_url);
            if ('home' == $link_type || '404' == $link_type || $this->_check_link_format($new_url)) {
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
                    break;
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
     * @return string
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

    /**
     * Get top page URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _top_page_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        // home, 404
        $home_url = $this->get('home_url');
        $home_urls = [
            'home' => $home_url,
            '404'  => $home_url.self::PATH_404_HTML,
        ];
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
        }

        // feed
        if (!$this->_check_final() && !$this->_check_skip('feed')) {
            foreach ($this->get_feed_types() as $feed_type) {
                $feed_link = trailingslashit(get_bloginfo($feed_type));
                if (!$this->_check_link_format($feed_link)) {
                    continue;
                }
                $added = $this->_add_urls(
                    $urls,
                    (array)$feed_link,
                    'feed',
                    $feed_type
                );
                if (self::FINAL === $added) {
                    break;
                }
            }
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get post parmalink URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _posts_urls(&$urls = array())
    {
        global $wpdb;

        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        foreach ($this->get_post_types() as $post_type) {
            if ($this->_check_skip($post_type)) {
                continue;
            }

            $key = __METHOD__."-{$post_type}";
            if (false === ($posts = $this->_get_transient($key))) {
                $sql =
                    "SELECT ID".
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
                $this->_set_transient($key, $posts);
            }

            foreach ($posts as $post) {
                $key = __METHOD__."-{$post_type}-permalink-{$post->ID}";
                if (false === ($permalink = $this->_get_transient($key))) {
                    $permalink = get_permalink($post->ID);
                    $this->_set_transient($key, $permalink);
                }
                $pagenate_format = '%#%/';
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
                } else {
                    $pagenate_format = 'page/%#%/';
                }

                // has <!--nexpage--> ?
                $key = __METHOD__."-{$post_type}-permalink-{$post->ID}-nextpages";
                if (false === ($pg_matches = $this->_get_transient($key))) {
                    $post_content = get_post_field('post_content', $post->ID, 'raw');
                    $pcount = mb_substr_count($post_content, '<!--nextpage-->');
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

                if (!empty($pg_matches)) {
                    $added = $this->_add_urls(
                        $urls,
                        (array)$pg_matches,
                        'paginate_link',
                        $post_type
                    );
                    if (self::FINAL === $added) {
                        break;
                    }
                }
                unset($pg_matches);

                // Detect Automattic AMP
                if (function_exists('amp_get_permalink') && class_exists('AMP_Options_Manager')) {
                    if ($this->_check_skip('amp')) {
                        continue;
                    }
                    // Force ignnore page.
                    if ($post_type !== 'page') {
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
                                    $amp_permalink = amp_get_permalink($post->ID);
                                    $added = $this->_add_urls(
                                        $urls,
                                        (array)$amp_permalink,
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
            }
            unset($posts);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get post archive URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _post_type_archive_urls(&$urls = array())
    {
        global $wpdb;

        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        foreach ($this->get_post_types() as $post_type) {
            if ($this->_check_skip($post_type)) {
                continue;
            }

            $key = __METHOD__."-{$post_type}";
            if (false === ($post_type_archive_link = $this->_get_transient($key))) {
                $post_type_archive_link = get_post_type_archive_link($post_type);
                $this->_set_transient($key, $post_type_archive_link);
            }
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
                $sql =
                    "SELECT ID".
                    " FROM {$wpdb->posts}".
                    " WHERE post_type=%s AND post_status=%s"
                    ;
                $sql = $wpdb->prepare(
                    $sql,
                    $post_type,
                    $post_type !== 'attachment' ? 'publish' : 'inherit'
                );
                $posts = $wpdb->get_results($sql, OBJECT);
                $pagenate_urls = $this->_get_paginates($post_type_archive_link, count($posts));
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
     * 
     * @return array
     */
    private function _get_term_taxonomy_slugs($term_taxonomy_id)
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

    /**
     * Get term_taxonomy slugs
     *
     * @param integer   $term_taxonomy_id
     * @param array     $slugs
     * 
     * @return array
     */
    private function _get_term_taxonomy_children($term_taxonomy_id, $slugs = [])
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

    /**
     * Get term archive URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _post_type_term_urls(&$urls = array())
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
                $taxonomy_names = get_object_taxonomies($post_type);
                $this->_set_transient($key, $taxonomy_names);
            }
            foreach ($taxonomy_names as $taxonomy_name) {
                $key = __METHOD__."-{$post_type}-{$taxonomy_name}";
                if (false === ($terms = $this->_get_transient($key)) ) {
                    $terms = get_terms($taxonomy_name, 'orderby=count&hide_empty=1');
                    $this->_set_transient($key, $terms);
                }

                foreach ($terms as $term) {
                    $termlink = get_term_link($term);
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
                        $slugs = $this->_get_term_taxonomy_slugs($term->term_taxonomy_id);
                        if (!empty($slugs)) {
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
                            $pagenate_urls = $this->_get_paginates($termlink, count($posts));
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

    /**
     * Get archive URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _archive_urls(&$urls = array())
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
                        $pagenate_urls = $this->_get_paginates($archive_link, intval($match[2]));
                        foreach ($pagenate_urls as $pagenate_url) {
                            if ($pagenate_url !== $archive_link) {
                                $archives_lists[] = $pagenate_url;
                            }
                        }
                    }
                }
                unset($matches);
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
     * @param array   $urls
     * @param string  $request_uri
     * 
     * @return string
     */
    private function _pagenate_urls(&$urls = array(), $request_uri='/')
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

    /**
     * Get paginate URLs (Front page)
     *
     * @param array   $urls
     * @param string  $request_uri
     * 
     * @return string
     */
    private function _pagenate_urls_page_on_front(&$urls = array(), $request_uri='/')
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        $post = get_post(get_option('page_on_front')); 
        if (!$post) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        $key = "posts_urls-{$post->post_type}-permalink-{$post->ID}";
        if (false === ($permalink = $this->_get_transient($key))) {
            $permalink = get_permalink($post_id);
            $this->_set_transient($key, $permalink);
        }
        $pagenate_format = '%#%/';
        if (trailingslashit($permalink) !== trailingslashit($this->get('home_url'))) {
            if (!$this->_check_link_format($permalink)) {
                return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
            }
            $added = $this->_add_urls(
                $urls,
                (array)$permalink,
                'permalink',
                $post_type
            );
            if (self::FINAL === $added) {
                $this->set('urls', $urls);
                return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
            }
        } else {
            $pagenate_format = 'page/%#%/';
        }

        // has <!--nexpage--> ?
        $key = "posts_urls-{$post->post_type}-permalink-{$post->ID}-nextpages";
        if (false === ($pg_matches = $this->_get_transient($key))) {
            $post_content = get_post_field('post_content', $post->ID, 'raw');
            $pcount = mb_substr_count($post_content, '<!--nextpage-->');
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

        if (!empty($pg_matches)) {
            $added = $this->_add_urls(
                $urls,
                (array)$pg_matches,
                'paginate_link',
                $post_type
            );
            if (self::FINAL === $added) {
                $this->set('urls', $urls);
                return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
            }
        }
        unset($pg_matches);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get paginate URLs (Front page)
     *
     * @param array   $urls
     * @param string  $request_uri
     * 
     * @return string
     */
    private function _pagenate_urls_page_for_posts(&$urls = array(), $request_uri='/')
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        $post = get_post(get_option('page_for_posts')); 
        if (!$post) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        $key = "posts_urls-{$post->post_type}-permalink-{$post->ID}";
        if (false === ($permalink = $this->_get_transient($key))) {
            $permalink = get_permalink($post_id);
            $this->_set_transient($key, $permalink);
        }
        if (!$this->_check_link_format($permalink)) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }
        $added = $this->_add_urls(
            $urls,
            (array)$permalink,
            'permalink',
            $post_type
        );
        if (self::FINAL === $added) {
            $this->set('urls', $urls);
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        $request_uri = preg_replace('#https?://[^/]+/#', '/', $permalink);
        $this->_pagenate_urls($urls, $request_uri);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    /**
     * Get author archive URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _authors_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }
        if ($this->_check_skip('author')) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        $key = __METHOD__;
        if (false === ($authors_links = $this->_get_transient($key))) {
            preg_match_all(
                '/href=["\']([^"\']*)["\']/',
                wp_list_authors(['style'=>'none', 'echo'=>false]),
                $matches,
                PREG_SET_ORDER
            );
            $authors_links = [];
            foreach ((array)$matches as $match) {
                $authors_links[] = self::link_normalize($match[1]);
            }
            unset($matches);
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

    /**
     * Get redirection URLs
     *
     * @param array   $urls
     * 
     * @return string
     */
    private function _redirection_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }
        if (!class_exists('Red_Item')) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }
        if ($this->_check_skip('redirection')) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        $key = __METHOD__;
        if (false === ($redirection_list = $this->_get_transient($key))) {
            $redirection_list = Red_Item::get_all();
            $this->_set_transient($key, $redirection_list);
        }

        foreach ( $redirection_list as $redirection ) {
            if (!$redirection->is_enabled() || $redirection->is_regex()) {
                continue;
            }

            $redirection_link = trailingslashit(self::link_normalize($redirection->get_url()));
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
     * @param array   $urls
     * @param single  $request_path
     * 
     * @return string
     */
    private function _singlepage_pagenate_urls(&$urls = array(), $request_path='/') {
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

        $permalink = get_permalink($post_id);
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
                    $post_type ? $post_type : ''
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
