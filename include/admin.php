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
    ];
?>


<div class="wrap">

<h1>Shifter</h1>

<div class="card">
<h2>Generator Settings</h2>

<form method="post" action="options.php">
    <p>Skip content you may not need and speed up the generating process. Selecting these options will exlucde them from your static Artifact.</p>
    <?php settings_fields('shifter-options'); ?>
    <?php do_settings_sections('shifter-options'); ?>
    <table class="form-table">
<?php foreach ($options as $key => $title) { ?>
        <tr valign="top">
        <th scope="row"><?php echo ucfirst($title); ?></th>
        <td>
            <input type="checkbox" name="<?php echo esc_attr($key); ?>" id="<?php echo esc_attr($key); ?>" value="yes" <?php echo get_option($key) === 'yes' ? 'checked ' : '' ; ?>/>
            <label for="<?php echo esc_attr($key); ?>">Skip <?php echo $title; ?></label>
        </td>
        </tr>
<?php } ?>
    </table>

    <?php submit_button(); ?>

</form>
</div>
</div>
<?php
}
