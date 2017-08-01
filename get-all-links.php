<?php
/*
Plugin Name: Get ALL Links.
Plugin URI:
Description:
Version: 0.6.0
Author: DigitalCube
Author URI:
License: GPLv2 or later
*/

add_action( 'template_redirect', function() {
    if ( !isset($_GET['urls']) ) {
        return;
    }

    global $wpdb;
    $delimiter = ',';
    $url_count = 0;

    $page  = is_numeric($_GET['urls']) ? intval($_GET['urls']) : 0;
    $limit = (isset($_GET['max']) && is_numeric($_GET['max'])) ? intval($_GET['max']) : 100;
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

    if ( is_front_page() ) {
        // top page link
        $home_url = home_url( '/' );
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
                    $sql = $wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE post_type=%s AND post_status=%s ORDER BY post_date DESC",
                        $post_type,
                        $post_type !== 'attachment' ? 'publish' : 'inherit');
                    $posts = $wpdb->get_results( $sql, OBJECT );;
                    foreach ( $posts as $post ) {
                        if ( $permalink = get_permalink($post->ID) ) {
                            if ( trailingslashit($permalink) === trailingslashit($home_url))
                                continue;
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
                            }
                        }
                    }
                    unset($posts);
                }

                // post_type archive link
                if ( $post_type_archive_link = get_post_type_archive_link($post_type) ) {
                    if ( trailingslashit($post_type_archive_link) === trailingslashit($home_url))
                        continue;
                    if ( !preg_match('#/$#',$post_type_archive_link) )
                        continue;
                    if ($url_count >= $start_position && $url_count < $end_position)
                        $urls['items'][] = array('link_type' => 'post_type_archive_link', 'post_type' => $post_type, 'link' => $post_type_archive_link);
                    if ($url_count >= $end_position)
                        break;
                    $url_count++;
                }

            }
        }

        if ($url_count < $end_position && get_option('shifter_skip_terms') !== 'yes') {
            foreach ( $post_types as $post_type ) {
                // post_type term link
                $taxonomy_names = get_object_taxonomies( $post_type );
                foreach ( $taxonomy_names as $taxonomy_name ) {
                    $terms = get_terms( $taxonomy_name, 'orderby=count&hide_empty=1' );
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
                    $archives_list = wp_get_archives(array('type'=>$archive_type,'format'=>'none','echo'=>0));
                    if ( preg_match_all('/href=["\']([^"\']*)["\']/', $archives_list, $matches, PREG_SET_ORDER) ) {
                        foreach ( $matches as $match ) {
                            $archive_link = remove_query_arg(array('urls','max'), str_replace('&#038;', '&', $match[1]));
                            if ( trailingslashit($archive_link) === trailingslashit($home_url))
                                continue;
                            if ( !preg_match('#/$#',$archive_link) )
                                continue;
                            if ($url_count >= $start_position && $url_count < $end_position)
                                $urls['items'][] = array('link_type' => 'archive_link', 'post_type' => $archive_type, 'link' => $archive_link);
                            if ($url_count >= $end_position)
                                break;
                            $url_count++;
                        }
                    }
                    unset($matches);
                }
            }
        }

        // pagenate links
        if ($url_count < $end_position) {
            $paginate_links = paginate_links( array('show_all'=>true) );
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
            $authors_list = wp_list_authors(array('style'=>'none','echo'=>false));
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

    } else if ( !is_single() ) {
        // pagenate links
        $paginate_links = paginate_links( array('show_all'=>true) );
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
    add_menu_page( 'Shifter Settings', 'Shifter Settings', 'administrator', __FILE__, 'shifter_settings_page' , 'https://getshifter.io/shifter-20x20.png' );
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
