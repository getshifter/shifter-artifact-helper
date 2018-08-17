<?php
/*
Plugin Name: Shifter – Artifact Helper
Plugin URI: https://github.com/getshifter/shifter-artifact-helper
Description: Helper tool for building Shifter Artifacts
Version: 0.9.10
Author: Shifter Team
Author URI: https://getshifter.io
License: GPLv2 or later
*/

add_action( 'template_redirect', function() {
    $request_uri = esc_html(remove_query_arg(array('urls','max'), $_SERVER['REQUEST_URI']));

    if ( !isset($_GET['urls']) ) {
        if ( preg_match('#/shifter_404\.html/?$#i', $request_uri) ) {
            header("HTTP/1.1 404 Not Found");
            $overridden_template = locate_template( '404.php' );
            if ( ! file_exists($overridden_template) ) {
                $overridden_template = locate_template( 'index.php' );
            }
            load_template( $overridden_template );
            die();
        } else {
            return;
        }
    }

    global $wpdb;
    $delimiter = ',';
    $url_count = 0;
    $transient_expires = 300;

    $page  =
        is_numeric($_GET['urls'])
        ? intval($_GET['urls'])
        : 0;
    $limit =
        (isset($_GET['max']) && is_numeric($_GET['max']))
        ? intval($_GET['max'])
        : 100;
    $start_position = $page * $limit;
    $end_position   = $start_position + $limit;

    $urls = array(
        'datetime' => date('Y-m-d H:i:s T'),
        'page'     => $page,
        'start'    => $start_position,
        'end'      => $end_position,
        'limit'    => $limit,
        'items'    => array(),
    );

    header('Content-Type: application/json');

    $get_paginates = function ($base_url, $total_posts) use(&$urls, &$url_count, $start_position, $end_position) {
      $pages_per_page = intval(get_option('posts_per_page'));
      if ($pages_per_page === 0) {
        return;
      }
      $num_of_pages = ceil(intval($total_posts) / $pages_per_page);
      $pagenate_links = paginate_links(array('base'=>"{$base_url}%_%", 'format'=>'page/%#%/', 'total'=> $num_of_pages, 'show_all' => true));
      if ( preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $pagenate_links, $pg_matches, PREG_SET_ORDER) ) {
          foreach ( $pg_matches as $pg_match ) {
              $paginate_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $pg_match[1]));
              if ( $url_count >= $start_position && $url_count < $end_position ) {
                  $urls['items'][] = array('link_type' => 'paginate_link', 'post_type' => '', 'link' => $paginate_link);
              }
              if ($url_count >= $end_position)
                  break;
              $url_count++;
            }
      }
      unset($pg_matches);
    };

    $home_url = home_url( '/' );
    $current_url = home_url($request_uri);

    if ( preg_match('#/shifter_404\.html/?$#i', $request_uri) ) {
        $urls['items'] = array();
        $url_count = 0;

    } else if ( is_front_page() && preg_replace('#^https://[^/]+/#','/',$home_url) === preg_replace('#^https://[^/]+/#','/',$current_url) ) {
        // top page link
        if ( $url_count >= $start_position && $url_count < $end_position ) {
            $urls['items'][$url_count] = array('link_type' => 'home', 'post_type' => '', 'link' => $home_url);
            $urls['items'][$url_count+1] = array('link_type' => '404', 'post_type' => '', 'link' => $home_url.'shifter_404.html');
        }
        $url_count++;
        $url_count++;

        if ($url_count < $end_position && get_option('shifter_skip_feed') !== 'yes') {
            foreach( array('rdf_url', 'rss_url', 'rss2_url', 'atom_url', 'comments_rss2_url') as $feed_type ) {
                if ( $feed_link = trailingslashit(get_bloginfo($feed_type)) ) {
                    if ( trailingslashit($feed_link) === trailingslashit($home_url))
                        continue;
                    if ($url_count >= $start_position && $url_count < $end_position)
                        $urls['items'][] = array('link_type' => 'feed', 'post_type' => $feed_type, 'link' => $feed_link);
                    if ($url_count >= $end_position)
                        break;
                    $url_count++;
                }
            }
        }

        if ($url_count < $end_position) {
            $post_types = get_post_types(array('public' => true), 'names');
            foreach ( $post_types as $post_type ) {
                if ($post_type === 'attachment' && get_option('shifter_skip_attachment') === 'yes') {
                    continue;
                }

                // post_type parmalink
                if ($url_count < $end_position) {
                    $transient_key = "shifter-helper-posts-{$post_type}";
                    if ( false === ($posts = get_transient($transient_key)) ) {
                        $sql = $wpdb->prepare(
                            "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status=%s ORDER BY post_date DESC",
                            $post_type,
                            $post_type !== 'attachment' ? 'publish' : 'inherit');
                        $posts = $wpdb->get_results( $sql, OBJECT );
                        set_transient( $transient_key, $posts, $transient_expires );
                    }
                    foreach ( $posts as $post ) {
                        if ($url_count < $start_position) {
                            $url_count++;
                            continue;
                        }
                        if ( $permalink = get_permalink($post->ID) ) {
                            if ( trailingslashit($permalink) === trailingslashit($home_url)) {
                                continue;
                            }
                            if ( !preg_match('#/$#',$permalink) ) {
                                if ( $post_type !== 'attachment' ) {
                                    //unset($urls['items']);
                                    $urls['count'] = count($urls['items']);
                                    $urls['finished'] = true;
                                    $urls['error'] = "Invalid permalink type. ({$permalink})";
                                    echo json_encode($urls);
                                    die();
                                }
                            } else {
                                if ($url_count >= $start_position && $url_count < $end_position)
                                    $urls['items'][] = array('link_type' => 'permalink', 'post_type' => $post_type, 'link' => $permalink);
                                if ($url_count >= $end_position)
                                    break;
                                $url_count++;

                                // // has <!--nexpage--> ?
                                $post_content = get_post_field( 'post_content', $post->ID, 'raw' );
                                $pcount = mb_substr_count($post_content, '<!--nextpage-->');
                                $pagenate_links = paginate_links(array('base'=>"{$permalink}%_%", 'format'=>'%#%/', 'total'=> $pcount + 1, 'show_all' => true));
                                if ( preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $pagenate_links, $pg_matches, PREG_SET_ORDER) ) {
                                    foreach ( $pg_matches as $pg_match ) {
                                        $paginate_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $pg_match[1]));
                                        if ( $url_count >= $start_position && $url_count < $end_position ) {
                                            $urls['items'][] = array('link_type' => 'paginate_link', 'post_type' => '', 'link' => $paginate_link);

                                        }
                                        if ($url_count >= $end_position)
                                            break;
                                        $url_count++;
                                    }
                                }
                                unset($pg_matches);

                                // Detect Automattic AMP
                                if ( function_exists( 'amp_get_permalink' ) ) {
                                    // supported_post_types is empty until first saved the setting.
                                    if (AMP_Options_Manager::get_option('supported_post_types')) {
                                        $amp_supported = AMP_Options_Manager::get_option('supported_post_types');
                                    } else {
                                        $amp_supported = array("post");
                                    }
                                    // Force ignnore page.
                                    if ($post_type !== 'page') {
                                        // Skip password_protected or other known errors.
                                        $support_errors_codes = AMP_Post_Type_Support::get_support_errors( $post->ID );
                                        if (!sizeof($support_errors_codes) > 0) {
                                            if (in_array($post_type, (array)$amp_supported)) {
                                                if (post_supports_amp($post)) {
                                                    $amp_permalink =amp_get_permalink($post->ID);
                                                    if ($url_count >= $start_position && $url_count < $end_position)
                                                        $urls['items'][] = array('link_type' => 'amphtml', 'post_type' => $post_type, 'link' => $amp_permalink);
                                                    if ($url_count >= $end_position)
                                                        break;
                                                    $url_count++;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                    unset($posts);
                }

                // post_type archive link
                $transient_key = "shifter-helper-post-archive-link-{$post_type}";
                if ( false === ($post_type_archive_link = get_transient($transient_key)) ) {
                    $post_type_archive_link = get_post_type_archive_link($post_type);
                    set_transient( $transient_key, $post_type_archive_link, $transient_expires );
                }
                if ( $post_type_archive_link ) {
                    if ( trailingslashit($post_type_archive_link) === trailingslashit($home_url))
                        continue;
                    if ( !preg_match('#/$#',$post_type_archive_link) )
                        continue;
                    if ($url_count >= $start_position && $url_count < $end_position)
                        $urls['items'][] = array('link_type' => 'post_type_archive_link', 'post_type' => $post_type, 'link' => $post_type_archive_link);
                    $url_count++;
                    if ($url_count >= $end_position)
                        break;

                    $posts_by_type = get_posts( array('post_type' => $post_type, 'post_status' => 'publish', 'posts_per_page' => -1) );
                    $get_paginates($post_type_archive_link, count($posts_by_type) );
                }

            }
        }

        if ($url_count < $end_position && get_option('shifter_skip_terms') !== 'yes') {
            foreach ( $post_types as $post_type ) {
                // post_type term link
                $transient_key = "shifter-helper-taxonomy_names-{$post_type}";
                if ( false === ($taxonomy_names = get_transient($transient_key)) ) {
                    $taxonomy_names = get_object_taxonomies( $post_type );
                    set_transient( $transient_key, $taxonomy_names, $transient_expires );
                }
                foreach ( $taxonomy_names as $taxonomy_name ) {
                    $transient_key = "shifter-helper-terms-{$post_type}-{$taxonomy_name}";
                    if ( false === ($terms = get_transient($transient_key)) ) {
                        $terms = get_terms( $taxonomy_name, 'orderby=count&hide_empty=1' );
                        set_transient( $transient_key, $terms, $transient_expires );
                    }
                    foreach ( $terms as $term ){
                        if ( $termlink = get_term_link( $term ) ) {
                            if ( trailingslashit($termlink) === trailingslashit($home_url))
                                continue;
                            if ( !preg_match('#/$#',$termlink) )
                                continue;
                            if ($url_count >= $start_position && $url_count < $end_position)
                                $urls['items'][] = array('link_type' => 'term_link', 'post_type' => $post_type, 'link' => $termlink);
                            if ($url_count >= $end_position)
                                break;
                            $url_count++;
                        }
                    }
                }
                unset($taxonomy_names);
            }
        }

        // archives
        if ($url_count < $end_position) {
            foreach ( array('yearly','monthly','daily') as $archive_type ) {
                if (get_option('shifter_skip_'.$archive_type) !== 'yes') {
                    $transient_key = "shifter-helper-archives-{$archive_type}";
                    if ( false === ($archives_list = get_transient($transient_key)) ) {
                        $archives_list = wp_get_archives(array('type'=>$archive_type,'format'=>'none','echo'=>0, 'show_post_count'=>true));
                        set_transient( $transient_key, $archives_list, $transient_expires );
                    }
                    if ( preg_match_all('/href=["\']([^"\']*)["\'].+\((\d+)\)/', $archives_list, $matches, PREG_SET_ORDER) ) {
                        foreach ( $matches as $match ) {
                            $archive_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $match[1]));
                            if ( trailingslashit($archive_link) === trailingslashit($home_url))
                                continue;
                            if ( !preg_match('#/$#',$archive_link) )
                                continue;
                            if ($url_count >= $start_position && $url_count < $end_position)
                                $urls['items'][] = array('link_type' => 'archive_link', 'post_type' => $archive_type, 'link' => $archive_link);
                                $url_count++;
                                $get_paginates($archive_link, intval($match[2]));
                            if ($url_count >= $end_position)
                                break;
                        }
                    }
                    unset($matches);
                }
            }
        }

        // pagenate links
        if ($url_count < $end_position) {
            $transient_key = "shifter-helper-paginate";
            if ( false === ($paginate_links = get_transient($transient_key)) ) {
                $paginate_links = paginate_links( array('show_all'=>true) );
                set_transient( $transient_key, $paginate_links, $transient_expires );
            }
            if ( preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $paginate_links, $matches, PREG_SET_ORDER) ) {
                foreach ( $matches as $match ) {
                    $paginate_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $match[1]));
                    if ( trailingslashit($paginate_link) === trailingslashit($home_url))
                        continue;
                    if ( !preg_match('#/$#', $paginate_link) )
                        continue;
                    if ($url_count >= $start_position && $url_count < $end_position)
                        $urls['items'][] = array('link_type' => 'paginate_link', 'post_type' => '', 'link' => $paginate_link);
                    if ($url_count >= $end_position)
                        break;
                    $url_count++;
                }
            }
            unset($matches);
        }

        // authors link
        if ($url_count < $end_position && get_option('shifter_skip_author') !== 'yes') {
            $transient_key = "shifter-helper-authors";
            if ( false === ($authors_list = get_transient($transient_key)) ) {
                $authors_list = wp_list_authors(array('style'=>'none','echo'=>false));
                set_transient( $transient_key, $authors_list, $transient_expires );
            }
            if ( preg_match_all('/href=["\']([^"\']*)["\']/', $authors_list, $matches, PREG_SET_ORDER) ) {
                foreach ( $matches as $match ) {
                    $author_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $match[1]));
                    if ( trailingslashit($author_link) === trailingslashit($home_url))
                        continue;
                    if ( !preg_match('#/$#',$author_link) )
                        continue;
                    if ($url_count >= $start_position && $url_count < $end_position)
                        $urls['items'][] = array('link_type' => 'author_link', 'post_type' => '', 'link' => $author_link);
                    if ($url_count >= $end_position)
                        break;
                    $url_count++;
                }
            }
            unset($matches);
        }

        // paginated category
        $transient_key = "shifter-helper-category_list";
        if ( false === ($category_list = get_transient($transient_key)) ) {
            $category_list = get_categories();
            set_transient( $transient_key, $category_list, $transient_expires );
        }
        foreach ($category_list as $cat) {
            $term_link = get_term_link($cat);
            $get_paginates($term_link, $cat->count);
        }

        // paginated tag
        $transient_key = "shifter-helper-tag_list";
        if ( false === ($tag_list = get_transient($transient_key)) ) {
            $tag_list = get_tags();
            set_transient( $transient_key, $tag_list, $transient_expires );
        }
        foreach ($tag_list as $tag) {
          $term_link = get_term_link($tag);
          $get_paginates($term_link, $tag->count);
        }

        // redirection link (redirection plugin)
        if ($url_count < $end_position && class_exists('Red_Item')) {
            $transient_key = "shifter-helper-redirection_list";
            if ( false === ($redirection_list = get_transient($transient_key)) ) {
                $redirection_list = Red_Item::get_all();
                set_transient( $transient_key, $redirection_list, $transient_expires );
            }
            foreach ( $redirection_list as $redirection ) {
                if ( $redirection->is_enabled() && ! $redirection->is_regex() ) {
                    $redirection_link = trailingslashit(remove_query_arg(
                        array('urls','max'),
                        str_replace('&#038;', '&', $redirection->get_url())
                    ));
                    if ( $redirection_link === trailingslashit($home_url))
                        continue;
                    if ($url_count >= $start_position && $url_count < $end_position) {
                        $redirect_action = maybe_unserialize($redirection->get_action_data());
                        $redirect_code   = (int)$redirection->get_action_code();
                        if ($redirect_code < 300 || $redirect_code > 400)
                            continue;
                        if (is_array($redirect_action)) {
                            foreach( ['logged_out','url_notfrom'] as $key) {
                                if (isset($redirect_action[$key])) {
                                    $redirect_action = $redirect_action[$key];
                                    break;
                                }
                            }
                        }
                        if (is_array($redirect_action) || empty($redirect_action))
                            continue;
                        if (! preg_match('#^(https?://|/)#i',$redirect_action))
                            $redirect_action = '/'.$redirect_action;
                        $urls['items'][] = array(
                            'link_type' => 'redirection',
                            'post_type' => '',
                            'link' => $redirection_link,
                            'redirect_to' => $redirect_action,
                            'redirect_code' => $redirect_code,
                        );
                        $url_count++;
                    }
                    if ($url_count >= $end_position)
                        break;
                }
            }
        }

    } else if ( !is_single() ) {
        // pagenate links
        $transient_key = "shifter-helper-paginate_links-{$request_uri}";
        if ( false === ($paginate_links = get_transient($transient_key)) ) {
            $paginate_links = paginate_links( array('show_all'=>true) );
            set_transient( $transient_key, $paginate_links, $transient_expires );
        }
        if ( preg_match_all('/class=["\']page-numbers["\'][\s]+href=["\']([^"\']*)["\']/', $paginate_links, $matches, PREG_SET_ORDER) ) {
            $pagenate_count = 0;
            foreach ( $matches as $match ) {
                $paginate_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $match[1]));
                if ( trailingslashit($paginate_link) === trailingslashit($home_url))
                    continue;
                if ( !preg_match('#/$#',$paginate_link) )
                    continue;
                if ( $url_count >= $start_position && $url_count < $end_position )
                    $urls['items'][] = array('link_type' => 'paginate_link', 'post_type' => '', 'link' => $paginate_link);
                if ($url_count >= $end_position)
                    break;
                $pagenate_count++;
                $url_count++;
            }
        }
        unset($matches);

    } else {
        while ( have_posts() ) {
            the_post();

            // pagenate links
            $paginate_links = wp_link_pages( array('echo' => false) );
            if ( preg_match_all('/href=["\']([^"\']*)["\']/', $paginate_links, $matches, PREG_SET_ORDER) ) {
                $pagenate_count = 0;
                foreach ( $matches as $match ) {
                    $paginate_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $match[1]));
                    if ( trailingslashit($paginate_link) === trailingslashit($home_url))
                        continue;
                    if ( !preg_match('#/$#',$paginate_link) )
                        continue;
                    if ( $url_count >= $start_position && $url_count < $end_position ) {
                        $post_type = get_post_type();
                        $urls['items'][] = array('link_type' => 'paginate_link', 'post_type' => $post_type ? $post_type : '', 'link' => $paginate_link);
                    }
                    if ($url_count >= $end_position)
                        break;
                    $pagenate_count++;
                    $url_count++;
                }
            }
            unset($matches);
        }
    }

    $urls['count'] = count($urls['items']);
    $urls['finished'] = $urls['count'] < $limit;
    if ( $urls['count'] <= 0) {
        header("HTTP/1.1 404 Not Found");
    }

    echo json_encode($urls);
    die();
});

add_action( 'init', function() {
    // upload dir -> relative path
    add_filter( 'upload_dir', function($uploads) {
        $parsed_url  = parse_url( home_url() );
        $host_name   = $parsed_url['host'];
        $server_name = $host_name . ( isset($parsed_url['port']) ? ':'.$parsed_url['port'] : '' );
        if ( isset( $uploads['url'] ) )
            $uploads['url'] = preg_replace( '#^(https?://|//)[^/]+/#', '/', $uploads['url'] );
        if ( isset( $uploads['baseurl'] ) )
            $uploads['baseurl'] = preg_replace( '#^(https?://|//)[^/]+/#', '/', $uploads['baseurl'] );
        return $uploads;
    });

    // shifter app url -> relative path
    $shifter_content_filter = function($content) {
        $content     = preg_replace( '#(https?://|//)?([a-z0-9\-]+\.)?app\.getshifter\.io:[0-9]+/#', '/', $content );
        return $content;
    };
    add_filter( 'the_editor_content', $shifter_content_filter );
    add_filter( 'the_content', $shifter_content_filter );

    // add menu
    if ( is_admin() ){
        add_action( 'admin_menu', 'shifter_add_settings_menu' );
    }
});

add_action( 'template_redirect', function() {
    if ( is_user_logged_in() ) {
        return;
    }

    // remove meta tag
    remove_action( 'wp_head', 'feed_links', 2 ); //サイト全体のフィード
    remove_action( 'wp_head', 'feed_links_extra', 3 ); //その他のフィード
    remove_action( 'wp_head', 'rsd_link' ); //Really Simple Discoveryリンク
    remove_action( 'wp_head', 'wlwmanifest_link' ); //Windows Live Writerリンク
    //remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head', 10, 0 ); //前後の記事リンク
    remove_action( 'wp_head', 'wp_shortlink_wp_head', 10, 0 ); //ショートリンク
    remove_action( 'wp_head', 'rel_canonical' ); //canonical属性
    //remove_action( 'wp_head', 'wp_generator' ); //WPバージョン
    remove_action( 'wp_head', 'rest_output_link_wp_head' ); // wp-json
    remove_action( 'wp_head', 'wp_oembed_add_discovery_links' );
    remove_action( 'wp_head', 'wp_oembed_add_host_js' );
}, 1);

function shifter_add_settings_menu(){
    add_menu_page( 'Shifter Settings', 'Shifter Settings', 'administrator', __FILE__, 'shifter_settings_page' , '/wp-content/mu-plugins/shifter-support-plugin/dist/images/shifter-icon.png' );
    add_action( 'admin_init', 'shifter_register_settings' );
}

function shifter_register_settings(){
    register_setting( 'shifter-options', 'shifter_skip_attachment' );
    register_setting( 'shifter-options', 'shifter_skip_yearly' );
    register_setting( 'shifter-options', 'shifter_skip_monthly' );
    register_setting( 'shifter-options', 'shifter_skip_daily' );
    register_setting( 'shifter-options', 'shifter_skip_terms' );
    register_setting( 'shifter-options', 'shifter_skip_author' );
    register_setting( 'shifter-options', 'shifter_skip_feed' );
}

function shifter_settings_page(){
    $options = array(
        "shifter_skip_attachment" => "media pages",
        "shifter_skip_yearly"     => "yearly archives",
        "shifter_skip_monthly"    => "monthly archives",
        "shifter_skip_daily"      => "daily archives",
        "shifter_skip_terms"      => "term archives",
        "shifter_skip_author"     => "author archives",
        "shifter_skip_feed"       => "feeds",
    );
?>
<div class="wrap">
<h1>Shifter Settings</h1>

<form method="post" action="options.php">
    <p>Shifter generating process to skip over pages.</p>
    <?php settings_fields( 'shifter-options' ); ?>
    <?php do_settings_sections( 'shifter-options' ); ?>
    <table class="form-table">
<?php foreach($options as $key => $title) { ?>
        <tr valign="top">
        <th scope="row"><?php echo ucfirst($title); ?></th>
        <td>
            <input type="checkbox" name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" value="yes" <?php echo get_option($key) === 'yes' ? 'checked ' : '' ; ?>/>
            <label for="<?php echo esc_attr($key); ?>">Shifter generating process to skip <?php echo $title; ?>.</label>
        </td>
        </tr>
<?php } ?>
    </table>

    <?php submit_button(); ?>

</form>
</div>
<?php
}
