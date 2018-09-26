<?php

class ShifterUrls {
    private $var = [
        'page'  => 0,
        'limit' => 100,
        'start' => 0,
        'end'   => 100,
        'url_count' => 0,
        'transient_expires' => 300,
    ];

    static $instance;
    const FINAL = 1;
    const NOT_FINAL = 0;

    private function __construct()
    {
    }

    public static function get_instance()
    {
        if( !isset( self::$instance ) ) {
            $c = __CLASS__;
            self::$instance = new $c();
        }

        return self::$instance;
    }

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

    public function __set($key, $value)
    {
        $this->set($key, $value);
    }

    private function get($key, $default=null)
    {
        if (array_key_exists($key, $this->var)) {
            return $this->var[$key];
        } else {
            $value = $default;
            switch ($key) {
            case 'request_uri':
                $value = esc_html(
                    $this->_link_nomalize(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '')
                );
                break;
            case 'home_url':
                $value = trailingslashit(home_url('/'));
                break;
            case 'current_url':
                $value = home_url($this->get('request_uri'));
                break;
            case 'urls':
                $value = $this->_default_urls_array();
                break;
            case 'pages_per_page':
                $value = intval(get_option('posts_per_page'));
                break;
            case 'post_types':
                $value = get_post_types(['public' => true], 'names');
                break;
            case 'feed_types':
                $value = ['rdf_url', 'rss_url', 'rss2_url', 'atom_url', 'comments_rss2_url'];
                break;
            case 'archive_types':
                $value = ['yearly','monthly','daily'];
                break;
            default:
                $value = $default;
            }
            if ($value) {
                $this->set($key, $value);
            }
            return $value;
        }
    }

    private function set($key, $value)
    {
        $this->var[$key] = $value;
    }

    private function increment($key, $inc=1)
    {
        if (array_key_exists($key, $this->var)) {
            if (is_numeric($this->var[$key])) {
                $this->var[$key] += $inc;
            }
        } else {
            $this->var[$key] = $inc;
        }
        return $this->var[$key];
    }

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
                $urls[] = $this->_link_nomalize($pg_match[1]);
            }
        }
        unset($pg_matches);
        return $urls;
    }

    private function _default_urls_array()
    {
        return [
            'datetime' => date('Y-m-d H:i:s T'),
            'page'     => $this->get('page'),
            'start'    => $this->get('start'),
            'end'      => $this->get('end'),
            'limit'    => $this->get('limit'),
            'items'    => [],
        ];
    }

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

    private function _check_skip($key)
    {
        return (get_option('shifter_skip_'.$key) === 'yes');
    }

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

    private function _urls_item($link_type, $post_type='', $link='', $redirect_action=null, $redirect_code=null)
    {
        $item = [
            'link_type' => $link_type,
            'post_type' => $post_type,
            'link'      => $link,
        ];
        if ($redirect_action) {
            $item['redirect_to'] = $redirect_action;
        }
        if ($redirect_code) {
            $item['redirect_code'] = $redirect_code;
        }
        return $item;
    }

    private function _get_transient($transient_key)
    {
        $transient_key = __CLASS__."-{$transient_key}";
        return get_transient($transient_key);
    }

    private function _set_transient($transient_key, $value)
    {
        $transient_key = __CLASS__."-{$transient_key}";
        set_transient($transient_key, $value, $this->get('transient_expires'));
    }

    private function _link_nomalize($link)
    {
        return remove_query_arg(
            ['urls','max'],
            str_replace('&#038;', '&', $link)
        );
    }

    private function _add_urls(&$urls=array(), $new_urls=array(), $link_type='', $post_type='', $redirect_action=null, $redirect_code=null){
        if ($this->_check_final()) {
            return self::FINAL;
        }

        foreach ((array)$new_urls as $new_url) {
            if ('home' == $link_type || '404' == $link_type || $this->_check_link_format($new_url)) {
                if ($this->_check_range()) {
                    $urls['items'][] = $this->_urls_item(
                        (string)$link_type,
                        $post_type,
                        $new_url,
                        $redirect_action,
                        $redirect_code
                    );
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

    private function _urls_init(&$urls = array()){
        if (empty($urls)) {
            $urls = $this->get('urls');
        }
        if ($this->_check_final()) {
            return self::FINAL;
        }
        return self::NOT_FINAL;
    }

    public function top_page_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        // home, 404
        $home_url = $this->get('home_url');
        $home_urls = [
            'home' => $home_url,
            '404'  => $home_url.'shifter_404.html',
        ];
        foreach ($home_urls as $url_type => $url) {
            if (self::FINAL === $this->_add_urls($urls, (array)$url, (string)$url_type, '')) {
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
                if (self::FINAL === $this->_add_urls($urls, (array)$feed_link, 'feed', $feed_type)) {
                    break;
                }
            }
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    // post_type parmalink
    public function posts_urls(&$urls = array())
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
                if (!$this->_check_link_format($permalink)) {
                    continue;
                }
                if (self::FINAL === $this->_add_urls($urls, (array)$permalink, 'permalink', $post_type)) {
                    break;
                }

                // has <!--nexpage--> ?
                $key = __METHOD__."-{$post_type}-permalink-{$post->ID}-nextpages";
                if (false === ($pg_matches = $this->_get_transient($key))) {
                    $post_content = get_post_field('post_content', $post->ID, 'raw');
                    $pcount = mb_substr_count($post_content, '<!--nextpage-->');
                    $pagenate_links = paginate_links(
                        [
                            'base'     => "{$permalink}%_%",
                            'format'   => '%#%/',
                            'total'    => $pcount + 1,
                            'show_all' => true,
                        ]
                    );
                    $pg_matches = [];
                    if (preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $pagenate_links, $matches, PREG_SET_ORDER)) {
                        foreach ($matches as $match) {
                            $pg_matches[] = $this->_link_nomalize($match[1]);
                        }
                    }
                    unset($matches);
                    $this->_set_transient($key, $pg_matches);
                }

                if (self::FINAL === $this->_add_urls($urls, (array)$pg_matches, 'paginate_link', $post_type)) {
                    break;
                }
                unset($pg_matches);

                // Detect Automattic AMP
                if (function_exists('amp_get_permalink') && class_exists('AMP_Options_Manager')) {
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
                                    if (self::FINAL === $this->_add_urls($urls, (array)$amp_permalink, 'amphtml', $post_type)) {
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

    // post_type archive link
    public function post_type_archive_urls(&$urls = array())
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
            if (self::FINAL === $this->_add_urls($urls, (array)$post_type_archive_link, 'post_type_archive_link', $post_type)) {
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

            if (self::FINAL === $this->_add_urls($urls, (array)$pagenate_urls, 'post_type_archive_link', $post_type)) {
                break;
            }
            unset($pagenate_urls);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

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

    // post_type term link
    public function post_type_term_urls(&$urls = array())
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
                    if (self::FINAL === $this->_add_urls($urls, (array)$termlink, 'term_link', $term->slug)) {
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

                    if (self::FINAL === $this->_add_urls($urls, (array)$pagenate_urls, 'term_link', $term->slug)) {
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

    // archive link
    public function archive_urls(&$urls = array())
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
                    $archive_link = $this->_link_nomalize($match[1]);
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

            if (self::FINAL === $this->_add_urls($urls, (array)$archives_lists, 'archive_link', $archive_type)) {
                break;
            }
            unset($archives_lists);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    // pagenate link
    public function pagenate_urls(&$urls = array(), $request_uri='/')
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
            global $wp_query;
            $big = 999999999;
            $args = [
                'base'    => str_replace($big, '%#%', esc_url(get_pagenum_link($big))),
                'format'  => 'page/%#%/',
                'current' => $current_page,
                'total'   => $wp_query->max_num_pages,
            ];
            preg_match_all(
                '/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/',
                paginate_links(['show_all'=>true]),
                $matches,
                PREG_SET_ORDER
            );
            $paginate_links = [];
            foreach ((array)$matches as $match) {
                $paginate_links[] = $this->_link_nomalize($match[1]);
            }
            unset($matches);
            $this->_set_transient($key, $paginate_links);
        }

        $this->_add_urls($urls, (array)$paginate_links, 'paginate_link', '');
        unset($paginate_links);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    // authors link
    public function authors_urls(&$urls = array())
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
                $authors_links[] = $this->_link_nomalize($match[1]);
            }
            unset($matches);
            $this->_set_transient($key, $authors_links);
        }

        $this->_add_urls($urls, (array)$authors_links, 'author_link', '');
        unset($authors_links);

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    // redirection link
    public function redirection_urls(&$urls = array())
    {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }
        if (!class_exists('Red_Item')) {
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

            $redirection_link = trailingslashit($this->_link_nomalize($redirection->get_url()));
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

            $redirect_code   = (int)$redirection->get_action_code();
            if ($redirect_code < 300 || $redirect_code > 400) {
                continue;
            }

            if ($this->_check_range()) {
                $urls['items'][] = $this->_urls_item(
                    'redirection',
                    '',
                    $redirection_link,
                    $redirect_action,
                    $redirect_code
                );
            }
            if ($this->_check_final()) {
                break;
            }
            $this->increment('url_count');
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }

    public function singlepage_pagenate_urls(&$urls = array(), $request_uri='/') {
        if (self::FINAL === $this->_urls_init($urls)) {
            return self::FINAL;
        }

        $request_uri = preg_replace('#https?://[^/]+/#', '/', $request_uri);
        if (preg_match('#/page/[0-9]+/$#', $request_uri)) {
            return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
        }

        while (have_posts()) {
            the_post();

            // pagenate links
            $current_page = max(1, get_query_var('page'));
            $paginate_links = wp_link_pages(['echo' => false]);
            if (preg_match_all('/href=["\']([^"\']*)["\']/', $paginate_links, $matches, PREG_SET_ORDER)) {
                $pagenate_count = 0;
                foreach ($matches as $match) {
                    $paginate_link = $this->_link_nomalize($match[1]);
                    $page_number = max(1, intval(preg_replace('#^.*/([0-9]+)/$#', '$1', $paginate_link)));
                    if (!$this->_check_link_format($paginate_link)) {
                        continue;
                    }

                    if ($this->_check_range()) {
                        $post_type = get_post_type();
                        $urls['items'][] = $this->_urls_item(
                            'paginate_link',
                            $post_type ? $post_type : '',
                            $paginate_link
                        );
                    }
                    if ($this->_check_final()) {
                        break;
                    }
                    $pagenate_count++;
                    $this->increment('url_count');
                }
            }
            unset($matches);
        }

        $this->set('urls', $urls);
        return $this->_check_final() ? self::FINAL : self::NOT_FINAL;
    }
}