<?php
if (!defined('ABSPATH')) {
    exit; // don't access directly
};

/**
 * Option page
 */
add_action(
    'init',
    function () {
        // add menu
        if (is_admin()) {
            add_action('admin_menu', 'shifter_add_settings_menu');
        }
    }
);

/**
 * Callback function for admin menu
 */
function shifter_add_settings_menu()
{
    add_submenu_page(
        'shifter',
        'Shifter Settings',
        'Settings',
        'administrator',
        'shifter-settings',
        'shifter_settings_page'
    );
    add_submenu_page(
        'shifter',
        'URL Preview',
        'URL Preview',
        'administrator',
        'shifter-url-preview',
        'shifter_url_preview_page'
    );
    add_action(
        'admin_init',
        'shifter_register_settings'
    );
}

/**
 * Callback function for option values
 */
function shifter_register_settings()
{
    register_setting('shifter-options', 'shifter_skip_attachment');
    register_setting('shifter-options', 'shifter_skip_yearly');
    register_setting('shifter-options', 'shifter_skip_monthly');
    register_setting('shifter-options', 'shifter_skip_daily');
    register_setting('shifter-options', 'shifter_skip_terms');
    register_setting('shifter-options', 'shifter_skip_tag');
    register_setting('shifter-options', 'shifter_skip_author');
    register_setting('shifter-options', 'shifter_skip_feed');
    register_setting('shifter-options', 'shifter_skip_embed');
    register_setting('shifter-options', 'shifter_custom_urls');
    register_setting('shifter-options', 'shifter_exclude_urls');
}

/**
 * Callback function for setting box
 */
function shifter_settings_page()
{
    $options = [
        "shifter_skip_attachment" => "media pages",
        "shifter_skip_yearly"     => "yearly archives",
        "shifter_skip_monthly"    => "monthly archives",
        "shifter_skip_daily"      => "daily archives",
        "shifter_skip_terms"      => "term archives",
        "shifter_skip_tag"        => "tag archives",
        "shifter_skip_author"     => "author archives",
        "shifter_skip_feed"       => "feeds",
        "shifter_skip_embed"      => "embeds",
    ];
?>


<div class="wrap">

<h1>Shifter</h1>

<div class="card">
<h2>Shifter Settings</h2>

<form method="post" action="options.php">
    <?php settings_fields('shifter-options'); ?>
    <?php do_settings_sections('shifter-options'); ?>
    
    <h3>Generator Settings</h3>
    <p>Skip content you may not need and speed up the generating process. Selecting these options will exclude them from your static Artifact.</p>
    <table class="form-table">
<?php foreach ($options as $key => $title) { ?>
<?php
        $default = '';
        if (preg_match('/^shifter_skip_(embed|attachment)$/', $key)) {
            $default = 'yes';
        }
?>
        <tr valign="top">
        <th scope="row"><?php echo ucfirst($title); ?></th>
        <td>
            <input type="checkbox" name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" value="yes" <?php echo get_option($key, $default) === 'yes' ? 'checked ' : '' ; ?>/>
            <label for="<?php echo esc_attr($key); ?>">Skip <?php echo $title; ?></label>
        </td>
        </tr>
<?php } ?>
    </table>

    <h3>Custom URLs</h3>
    <p>Add custom URLs to include in static generation. Enter one URL per line.</p>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">Additional URLs</th>
        <td>
            <textarea name="shifter_custom_urls" id="shifter_custom_urls" rows="10" cols="80" class="large-text code"><?php echo esc_textarea(get_option('shifter_custom_urls', '')); ?></textarea>
            <p class="description">Enter one URL per line. Example:<br/>
            https://example.com/special-page/<br/>
            /custom-endpoint.json<br/>
            /assets/custom.css</p>
        </td>
        </tr>
    </table>

    <h3>Exclude URLs</h3>
    <p>Exclude URLs from static generation using prefixes or file extensions.</p>
    <table class="form-table">
        <tr valign="top">
        <th scope="row">URL Prefixes to Exclude</th>
        <td>
            <textarea name="shifter_exclude_urls" id="shifter_exclude_urls" rows="8" cols="80" class="large-text code"><?php echo esc_textarea(get_option('shifter_exclude_urls', '')); ?></textarea>
            <p class="description">Enter one prefix or pattern per line. Examples:<br/>
            /wp-json/<br/>
            /wp-admin/<br/>
            .jpg<br/>
            .png<br/>
            .pdf</p>
        </td>
        </tr>
    </table>

    <?php submit_button(); ?>

</form>
</div>
</div>
<?php
}

function shifter_url_preview_page()
{
?>
<div class="wrap">
<h1>URL Preview</h1>
<div class="card">
<h2>Static Generation Target URLs</h2>
<p>Preview the URLs that will be included in static generation.</p>

<div id="shifter-url-preview-controls">
    <button type="button" id="load-urls" class="button button-primary">Load URLs</button>
    <span id="loading-indicator" style="display:none;">Loading...</span>
</div>

<div id="url-preview-results" style="margin-top: 20px;">
    <p>Click "Load URLs" to preview the URLs that will be generated.</p>
</div>
</div>
</div>

<script type="text/javascript">
jQuery(document).ready(function($) {
    $('#load-urls').click(function() {
        var button = $(this);
        var indicator = $('#loading-indicator');
        var results = $('#url-preview-results');
        
        button.prop('disabled', true);
        indicator.show();
        
        $.ajax({
            url: '<?php echo home_url('/wp-json/shifter/v1/urls'); ?>',
            method: 'GET',
            success: function(data) {
                var html = '<h3>Found ' + data.count + ' URLs</h3>';
                html += '<table class="wp-list-table widefat fixed striped">';
                html += '<thead><tr><th>URL</th><th>Type</th><th>Post Type</th></tr></thead>';
                html += '<tbody>';
                
                if (data.items && data.items.length > 0) {
                    data.items.forEach(function(item) {
                        html += '<tr>';
                        html += '<td><a href="' + item.link + '" target="_blank">' + item.link + '</a></td>';
                        html += '<td>' + (item.link_type || '') + '</td>';
                        html += '<td>' + (item.post_type || '') + '</td>';
                        html += '</tr>';
                    });
                } else {
                    html += '<tr><td colspan="3">No URLs found</td></tr>';
                }
                
                html += '</tbody></table>';
                results.html(html);
            },
            error: function() {
                results.html('<p style="color: red;">Error loading URLs. Please try again.</p>');
            },
            complete: function() {
                button.prop('disabled', false);
                indicator.hide();
            }
        });
    });
});
</script>
<?php
}
