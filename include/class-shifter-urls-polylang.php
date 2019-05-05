<?php
class ShifterUrlsPolylang extends ShifterUrlsBase
{
    static $instance;
    static $current_language;

    public function __construct()
    {
        parent::__construct();
    }

    public static function get_instance()
    {
        global $polylang;
        new PLL_Filters_Links($polylang);
        self::$current_language = pll_default_language();

        if (!isset(self::$instance)) {
            $c = __CLASS__;
            self::$instance = new $c();
        }
        return self::$instance;
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
        switch ($key) {
        case 'home_url':
            $value = $this->_get_polylang_home_url($default);
            break;
        case 'home_urls':
            $value = $this->_get_polylang_home_urls($default);
            break;
        case 'feed_urls':
            $value = $this->_get_polylang_feed_urls($default);
            break;
        case 'page_on_front':
            $value = pll_get_post(get_option('page_on_front'), self::$current_language);
            break;
        default:
            $value = parent::get($key, $default);
            break;
        }
        return $value;
    }

    protected function _urls_item($link_type, $post_type='', $link='', $redirect_action=null, $redirect_code=null)
    {
        $path = preg_replace('#^https?://[^/]+/#', '/', $link);

        $default_language = pll_default_language();
        $language = self::$current_language;
        $language_list = implode('|', pll_languages_list());
        foreach ([$link_type,$post_type] as $slug) {
            if ($default_language === $language && !empty($slug)) {
                if (preg_match('/^.*-('.$language_list.')$/', $slug)) {
                    $language = preg_replace(
                        '/^.*-('.$language_list.')$/',
                        '$1',
                        $slug
                    );
                }
            }
        }
        if ($default_language === $language && preg_match('#^/('.$language_list.')/#', $path)) {
            $language = preg_replace(
                '#^/('.$language_list.')/.*$#',
                '$1',
                $path
            );
        }

        $item = [
            'link_type' => $link_type,
            'post_type' => $post_type,
            'link'      => $link,
            'path'      => $path,
            'language'  => $language ? $language : $default_language,
        ];
        if ($redirect_action) {
            $item['redirect_to'] = $redirect_action;
        }
        if ($redirect_code) {
            $item['redirect_code'] = $redirect_code;
        }
        return $item;
    }

    protected function _get_permalink($post_id, $post_type, $amp_permalink=false)
    {
        $permalink = parent::_get_permalink($post_id, $post_type, $amp_permalink);
        self::$current_language = pll_get_post_language($post_id);
        return $permalink;
    }

    protected function _get_postid_from_url($request_path)
    {
        $post_id = parent::_get_postid_from_url($request_path);
        self::$current_language = pll_get_post_language($post_id);
        return $post_id;
    }

    protected function _posts_count_from_post_type($post_type)
    {
        $posts_count = pll_count_posts(
            self::$current_language,
            [
                'post_type' => $post_type
            ]
        );
        return $posts_count;
    }

    protected function _posts_count_from_author($author_name, $post_type='post')
    {
        $posts_count = pll_count_posts(
            self::$current_language,
            [
                'author_name' => $author_name,
                'post_type' => $post_type,
            ]
        );
        return $posts_count;
    }

    private function _get_language_from_homeurl($request_uri)
    {
        $default_language = pll_default_language();
        $language = $default_language;
        if (false !== ($key = array_search($request_uri, $this->_get_polylang_home_urls([])))) {
            $language = $key === 'home'
                ? $default_language
                : preg_replace('/^home-(.*)$/', '$1', $key);
        }
        return $language;
    }

    protected function _get_pagenate_urls($request_uri='/')
    {
        $paginate_links = [];
        $default_language = pll_default_language();
        $language = $this->_get_language_from_homeurl($request_uri);

        self::$current_language = $language;
        $posts_count = $this->_posts_count_from_post_type('post');
        $paginate_links = parent::get_paginates($request_uri, $posts_count);
        self::$current_language = pll_default_language();
        return $paginate_links;
    }

    protected function _get_object_taxonomies($post_type)
    {
        $taxonomy_names = [];
        foreach (parent::_get_object_taxonomies($post_type) as $taxonomy_name) {
            if (!preg_match('/^(language|term_(language|translations))$/',$taxonomy_name)) {
                $taxonomy_names[] = $taxonomy_name;
            }
        }
        return $taxonomy_names;
    }

    protected function _get_term_link($term)
    {
        $term_link = parent::_get_term_link($term);
        self::$current_language = pll_get_term_language($term->term_id);
        return $term_link;
    }

    protected function _get_archive_lists($archive_type)
    {
        $default_language = pll_default_language();
        $language_list = pll_languages_list();
        $archives_lists = [];

        $archives_list = wp_get_archives(
            [
                'type'   => $archive_type,
                'format' => 'none',
                'echo'   => 0,
                'show_post_count' => true
            ]
        );
        preg_match_all('/href=["\']([^"\']*)["\'].+\((\d+)\)/', $archives_list, $matches, PREG_SET_ORDER);
        foreach ((array)$matches as $match) {
            $archive_link_org = self::link_normalize($match[1]);
            $args = ['post_type' => 'post'];
            switch ($archive_type) {
            case 'yearly':
                $preg = '#^.*/([0-9]+)/$#';
                $args['year'] = intval(preg_replace($preg, '$1', $archive_link_org));
                break;
            case 'monthly':
                $preg = '#^.*/([0-9]+)/([0-9]+)/$#';
                $args['year'] = intval(preg_replace($preg, '$1', $archive_link_org));
                $args['monthnum'] = intval(preg_replace($preg, '$2', $archive_link_org));
                break;
            case 'daily':
                $preg = '#^.*/([0-9]+)/([0-9]+)/([0-9]+)/$#';
                $args['year'] = intval(preg_replace($preg, '$1', $archive_link_org));
                $args['monthnum'] = intval(preg_replace($preg, '$2', $archive_link_org));
                $args['day'] = intval(preg_replace($preg, '$3', $archive_link_org));
                break;
            }
            foreach ($language_list as $language) {
                self::$current_language = $language;
                $archive_link = $this->_transform_polylang_url($archive_link_org, $language);
                $post_count = pll_count_posts($language, $args);
                if ($post_count > 0) {
                    $archives_lists[] = $archive_link;
                    $pagenate_urls = self::get_paginates($archive_link, $post_count);
                    foreach ($pagenate_urls as $pagenate_url) {
                        if ($pagenate_url !== $archive_link) {
                            $archives_lists[] = $pagenate_url;
                        }
                    }
                }
            }
        }
        unset($matches);
        self::$current_language = $default_language;
        return $archives_lists;
    }

    protected function _get_authors_links(&$urls = array())
    {
        $default_language = pll_default_language();
        $language_list = pll_languages_list();
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
            $authors_link_org = self::link_normalize($match[1]);
            $author_name = preg_replace('#^.*/([^/]+)/?$#', '$1', $authors_link_org);
            foreach ($language_list as $language) {
                self::$current_language = $language;
                $authors_link = $this->_transform_polylang_url($authors_link_org, $language);
                $post_count = $this->_posts_count_from_author($author_name);
                if ($post_count > 0) {
                    $authors_links[] = $authors_link;
                    $pagenate_urls = self::get_paginates($authors_link, $post_count);
                    foreach ($pagenate_urls as $pagenate_url) {
                        if ($pagenate_url !== $authors_link) {
                            $authors_links[] = $pagenate_url;
                        }
                    }
                }
            }
        }
        unset($matches);
        self::$current_language = $default_language;
        return $authors_links;
    }

    protected function _get_term_taxonomy_slugs($term)
    {
        $slugs = parent::_get_term_taxonomy_slugs($term);
        self::$current_language = pll_get_term_language($term->term_id);
        return $slugs;
    }

    private function _get_polylang_home_url($default=null)
    {
        $language_home = trailingslashit(pll_home_url(self::$current_language));
        return $language_home;
    }

    private function _get_polylang_home_urls($default=null)
    {
        $urls = (array)parent::get('home_urls', $default);
        $default_language = pll_default_language();
        foreach (pll_languages_list() as $language) {
            self::$current_language = $language;
            if ($default_language !== $language) {
                $language_home_base = trailingslashit(home_url("/{$language}/"));
                $language_home = $this->get('home_url');
                if ($language_home_base !== $language_home) {
                    $urls["home-{$language}-base"] = $language_home_base;
                }
                $urls["home-{$language}"] = $language_home;
            }
        }
        self::$current_language = $default_language;
        return $urls;
    }

    private function _get_polylang_feed_urls($default=null)
    {
        $urls = parent::get('feed_urls', $default);
        $default_language = pll_default_language();
        foreach (pll_languages_list() as $language) {
            self::$current_language = $language;
            if ($default_language === $language) {
                continue;
            }
            foreach ($this->get('feed_types') as $feed_type) {
                $feed_link = $this->_transform_polylang_url(get_bloginfo($feed_type), $language);
                $urls["{$feed_type}-{$language}"] = trailingslashit($feed_link);
            }
        }
        self::$current_language = $default_language;
        return $urls;
    }

    private function _transform_polylang_url($url_org, $language)
    {
        if (pll_default_language() === $language) {
            return $url_org;
        }

        $default_home = trailingslashit(home_url('/'));
        $language_home = trailingslashit(home_url("/{$language}/"));
        $url = str_replace($default_home, $language_home, $url_org);
        return $url;
    }
}
