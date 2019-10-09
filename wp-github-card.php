<?php
/**
 * Plugin Name: Card for GitHub
 * Plugin URI: https://github.com/ineo6/github-card
 * Description: GitHub 代码仓库短代码展示，另支持展示用户仓库一览
 * Version: 1.0.0
 * Author: Neo
 * Author URI: http://idayer.com
 **/

const WP_GITHUB_CARD_USERAGENT = "Github Card/1.0.0 (WordPress 3.9.0+) neo";
define('GITHUB_CARD_PLUGIN_DIR', plugin_dir_path(__FILE__));

require_once(GITHUB_CARD_PLUGIN_DIR . 'utils.php');
require_once(GITHUB_CARD_PLUGIN_DIR . 'api.php');

function wp_github_card_i18n()
{
    load_plugin_textdomain("repo", false, plugin_basename(__DIR__) . "/langs/");
}

function wp_github_card_style()
{
    wp_enqueue_style("github_card_style", plugins_url("wp-github-card.css", __FILE__));
}

/**
 * 显示用户和仓库列表
 * public function
 * @param $args
 */
function wp_github_card_user($args)
{
    if (isset($args['user'])) {
        $user = $args['user'];

        $data = array(
            'github_card_user_api' => rest_url('github-card/user'),
            'github_card_repos_api' => rest_url('github-card/repos'),
            'user' => $user
        );

        $userInfo = wp_github_user($user);

        $template = plugin_dir_path(__FILE__) . "repos.html";
        $pattern = '/{{([a-z_]+)}}/';

        echo wp_github_card_render($template, $pattern, array_merge($data, $userInfo));
    } else {
        echo "行星撞地球";
    }
}

function wp_github_user($user)
{
    $url = "https://api.github.com/users/" . $user;
    $userInfo = github_fetch($url);

    if (array_key_exists("message", $userInfo) || array_key_exists("documentation_url", $userInfo) || $userInfo["private"] == true) {
        return $userInfo;
    } else {
        $userInfo['followers'] = numberic_count($userInfo['followers']);
        $userInfo['public_gists'] = numberic_count($userInfo['public_gists']);
        $userInfo['public_repos'] = numberic_count($userInfo['public_repos']);

        return $userInfo;
    }
}

function wp_github_card($atts)
{
    if (array_key_exists("path", $atts)) {
        $atts_path = explode("/", $atts["path"]);
        $atts_owner = $atts_path[0];
        $atts_name = $atts_path[1];
    } else {
        $atts_owner = $atts["owner"];
        $atts_name = $atts["name"];
    }

    if ($atts_owner == null || $atts_name == null) {
        return "";
    }

    $url = "https://api.github.com/repos/" . $atts_owner . '/' . $atts_name;
    $repo = github_fetch($url);

    if (array_key_exists("message", $repo) || array_key_exists("documentation_url", $repo) || $repo["private"] == true) {
        $data = array(
            "owner" => $atts_owner,
            "owner_url" => "https://github.com/" . $atts_owner,
            "avatar_url" => "",
            "name" => $atts_name,
            "html_url" => "https://github.com/" . $atts_owner . "/" . $atts_name,
            "default_branch" => "-",
            "description" => __("This repository is not available anymore.", "repo"),
            "homepage" => "https://github.com/" . $atts_owner . "/" . $atts_name,
            "stargazers_count" => "-",
            "forks" => "-",
            "action" => ""
        );
    } else {
        $description_empty = ($repo["description"] == "");
        $homepage_empty = ($repo["homepage"] == "" || $repo["homepage"] == null);
        $data = array(
            "owner" => $repo["owner"]["login"],
            "owner_url" => $repo["owner"]["html_url"],
            "avatar_url" => $repo["owner"]["avatar_url"],
            "name" => $repo["name"],
            "html_url" => $repo["html_url"],
            "default_branch" => $repo["default_branch"],
            "description" => ($description_empty && $homepage_empty) ? __("This repository doesn't have description or homepage.", "repo") : $repo["description"],
            "homepage" => $homepage_empty ? $repo["html_url"] : $repo["homepage"],
            "stargazers_count" => number_format($repo["stargazers_count"]),
            "forks" => number_format($repo["forks"]),
            "action" => $repo["fork"] ? 'Forked by ' : 'Created by '
        );
    }

    $template = plugin_dir_path(__FILE__) . "wp-github-card.html";
    $pattern = '/{{([a-z_]+)}}/';

    return wp_github_card_render($template, $pattern, $data);
}

function wp_github_card_options_link($links)
{
    $url = add_query_arg(array('page' => 'wp-github-card-options'), admin_url('options-general.php'));
    $settings_link = '<a href="' . esc_url($url) . '">' . __('Settings', 'repo') . '</a>';
    array_unshift($links, $settings_link);
    return $links;
}

function wp_github_card_options_menu()
{
    add_options_page(__('GitHub Card', 'repo'), __('GitHub Card', 'repo'), 'manage_options', 'wp-github-card-options', 'wp_github_card_options_page');
}

function wp_github_card_register_settings()
{
    add_option('wp_github_card_github_token', '');
    register_setting('wp_github_card_options_group', 'wp_github_card_github_token');
}

function wp_github_card_options_page()
{
    ?>
    <div class="wrap">
        <h2><?php _e('GitHub Card options', 'repo') ?></h2>
        <form method="post" action="options.php">
            <?php settings_fields('wp_github_card_options_group'); ?>
            <div id="wp_github_card_options_github_auth">
                <h3><?php _e('Get authenticated to GitHub API (HIGHLY RECOMMENDED!)', 'repo') ?></h3>
                <p class="description"><?php _e("For unauthenticated requests, the rate limit allows for up to <b>60</b> requests per hour. For authenticated requests, the rate limit is <b>5,000</b> times per hour. If your card works bad, it's possible that unauthenticated request quota is used up.", 'repo') ?></p>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="wp_github_card_github_token"><?php _e('Personal Access Token', 'repo') ?></label>
                        </th>
                        <td>
                            <input type="password" name="wp_github_card_github_token" id="wp_github_card_github_token"
                                   class="regular-text"
                                   value="<?php echo get_option('wp_github_card_github_token'); ?>">
                            <button type="button" id="wp_github_card_github_token_toggler"
                                    class="button button-secondary hidden"><?php _e('Show', 'repo') ?></button>
                        </td>
                    </tr>
                </table>
                <script>
                    void function () {
                        var token = document.getElementById('wp_github_card_github_token');
                        var button = document.getElementById('wp_github_card_github_token_toggler');
                        var isHidden = true;
                        button.addEventListener('click', function (e) {
                            isHidden = !isHidden;
                            token.type = isHidden ? 'password' : 'text';
                            button.innerHTML = isHidden ? "<?php _e('Show', 'repo') ?>" : "<?php _e('Hide', 'repo') ?>";
                        }, false);
                        button.classList.remove('hidden');
                    }();
                </script>
            </div>
            <?php submit_button(); ?>
        </form>
        <h3><?php _e('How do I get the personal access token?', 'repo') ?></h3>
        <p><?php _e('Visit <strong><a href="https://github.com/settings/tokens/new" target="_blank">https://github.com/settings/tokens/new</a></strong>, make sure <strong>public_repo</strong> is checked (it is the only scope requested by GitHub Card, you may uncheck others) and generate a token.', 'repo') ?></p>
        <p><img src="<?php echo plugins_url("screenshot-2.png", __FILE__); ?>" alt="GitHub Personal Access Token"
                style="box-shadow:0 0 15px lightgray"></p>
    </div>
    <?php
}

add_action('admin_init', 'wp_github_card_register_settings');
add_action('rest_api_init', array('GitHubCard_REST_API', 'init'));
add_filter("plugins_loaded", "wp_github_card_i18n");
add_filter("wp_enqueue_scripts", "wp_github_card_style");
add_filter('admin_menu', 'wp_github_card_options_menu');
add_filter('plugin_action_links_' . plugin_basename(plugin_dir_path(__FILE__) . 'wp-github-card.php'), 'wp_github_card_options_link');
add_shortcode("ghCard", "wp_github_card");

?>