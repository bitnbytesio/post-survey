<?php

/**
 * Plugin Name: Post Survey
 * Version: 1.0
 * Plugin URI: https://github.com/artisangang/post-survey/
 * Description: Get feedback about posts from visitors to make your blog more valuable and meaningfull to readers.
 * Author: Artisan Gang
 * Author URI: https://github.com/artisangang/
 */

/**
 * Define plugin base path
 */
define('PS_BASE_DIR', dirname(__FILE__));

if (!class_exists('POST_SURVEY_SETUP')) {

    /**
     * Class POST_SURVEY_SETUP
     * Main plugin file
     */
    class POST_SURVEY_SETUP
    {

        /**
         * @var array
         * Plugin settings collection
         */
        protected $options = [];

        /**
         * @var string
         * Stores default template
         */
        protected $default_templete;

        /**
         * @var array
         * Plugin info
         */
        protected $info = [
            'ver' => 1.0,
            'name' => 'Post Survey',

        ];

        /**
         * POST_SURVEY_SETUP constructor.
         * Register and Setup plugin
         */
        public function __construct()
        {

            register_activation_hook(__FILE__, array($this, '_activate'));
            register_uninstall_hook(__FILE__, array('POST_SURVEY_SETUP', '_uninstall'));

            $this->default_templete = file_get_contents(PS_BASE_DIR . '/template.php');

            $defaults = [
                'template' => esc_textarea($this->default_templete),
                'hook' => 1,
                'private' => 0,
                'login_url' => home_url('login'),
                'unique_ip' => 0,
                'comment' => 0,
                'count_type' => 0,
            ];

            $this->options = get_option('post-survey');

            if (!$this->options) {
                add_option('post-survey', $defaults);
                $this->options = $defaults;
            }


            if (is_admin()) {
                add_action('admin_menu', array($this, 'menu'));
                add_action('admin_init', array($this, 'init'));

                add_filter('manage_posts_columns', array($this, 'column_head'));
                add_action('manage_posts_custom_column', array($this, 'column_content'), 10, 2);
                add_filter('post_row_actions', array($this, 'feedback_action'), 10, 2);
                add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_files'));


            } else {

                add_action('wp_enqueue_scripts', array($this, 'enqueue_files'));
                add_shortcode('post-survey', array($this, 'shortcode'));
                if ($this->get('hook') == 1) {
                    add_filter('the_content', array($this, 'post_content'));
                }

                add_action('wp_footer', array($this, 'footer_data'));
            }


            add_action('wp_ajax_post_survey', array($this, 'handle_survey'));
            add_action('wp_ajax_nopriv_post_survey', array($this, 'handle_survey'));


        }

        /**
         * @param $defaults
         * @return mixed
         * Set custom column in backend posts list
         */
        public function column_head($defaults)
        {
            $defaults['post_survey'] = 'Post Survey';
            return $defaults;
        }

        /**
         * @param $column_name
         * @param $post_ID
         * Provides content for suctom column
         */
        public function column_content($column_name, $post_ID)
        {

            if ($column_name == 'post_survey') {

                $calc = $this->calculate($post_ID);

                $suffix = ($this->get('count_type') == 0) ? ' %' : '';

                echo '<span class="positive"><i class="post-survey-up post-survey-up-colored"></i> ' . $calc['positive'] . $suffix . '</span> <br> <span class="negative"><i class="post-survey-up post-survey-down-colored"></i> ' . $calc['negative'] . $suffix . '</span>';

            }


        }

        /**
         * @param $actions
         * @param $page_object
         * @return mixed
         */
        public function feedback_action($actions, $post)
        {

            $actions['post_survey_feedback_link'] = '<a href="' . admin_url('admin.php?page=post-survey-feedback&post=' . $post->ID) . '" class="post_survey_feedback_link">' . __('Feedback') . '</a>';
            return $actions;
        }

        /**
         * @param $key
         * @param null $default
         * @return mixed|null
         * Method to get plugin settings
         */
        public function get($key, $default = null)
        {
            if (isset($this->options[$key])) {
                $default = $this->options[$key];
            }
            return $default;
        }

        /**
         * @param $key
         * @param null $default
         * @return mixed|null
         * Method to get plugin info
         */
        public function info($key, $default = null)
        {
            if (isset($this->info[$key])) {
                $default = $this->info[$key];
            }
            return $default;
        }

        /**
         * Register hooks for admin panel
         */
        public function init()
        {


            register_setting('post-survey', 'post-survey', array($this, 'validate'));

            add_settings_section('post_survey_settings', 'Post Survey Settings', array($this, 'section_text'), 'post-survey');
            add_settings_field('post_survey_settings_template', 'Template', array($this, 'field_template'), 'post-survey', 'post_survey_settings');
            add_settings_field('post_survey_settings_hook', 'Hook All Posts', array($this, 'field_hook'), 'post-survey', 'post_survey_settings');


            add_settings_field('post_survey_settings_private', 'Allow registered users only', array($this, 'field_private'), 'post-survey', 'post_survey_settings');

            add_settings_field('post_survey_settings_login_url', 'Login page url', array($this, 'field_login_url'), 'post-survey', 'post_survey_settings');

            add_settings_field('post_survey_settings_unique_ip', 'Restict duplicate ip', array($this, 'field_unique_ip'), 'post-survey', 'post_survey_settings');

            add_settings_field('post_survey_settings_comment', 'Allow user to write comment', array($this, 'field_comment'), 'post-survey', 'post_survey_settings');

            add_settings_field('post_survey_settings_count_type', 'Show count in figure', array($this, 'field_count_type'), 'post-survey', 'post_survey_settings');


            add_action('delete_post', array($this, 'delete_post'), 10);

            add_action('delete_user', array($this, 'delete_user'));

        }

        /**
         * @param $uid
         * Delete user feedback when user is deleted
         */
        public function delete_user($uid)
        {

            global $wpdb;

            if ($wpdb->get_var($wpdb->prepare("SELECT user_id FROM post_survey WHERE user_id = %d", $uid))) {
                $wpdb->query($wpdb->prepare('DELETE FROM post_survey WHERE user_id = %d', $uid));
            }

        }

        /**
         * @param $pid
         * Delete post feedback when post is deleted
         */
        public function delete_post($pid)
        {

            global $wpdb;
            if ($wpdb->get_var($wpdb->prepare("SELECT post_id FROM post_survey WHERE post_id = %d", $pid))) {
                $wpdb->query($wpdb->prepare('DELETE FROM post_survey WHERE post_id = %d', $pid));
            }

        }

        /**
         * Plugin text
         */
        public function section_text()
        {
            echo '<p>You can also use [post-survey id="your post id here"] shortcode.</p>';
        }

        /**
         * Template field
         */
        public function field_template()
        {
            $value = $this->get('template');

            echo "<textarea id='plugin_text_string' name='post-survey[template]'>{$value}</textarea>";

        }

        /**
         * Hook all posts field
         */
        public function field_hook()
        {
            $value = $this->get('hook');
            $checked = ($value == 1) ? 'checked' : '';
            echo '<input id="post_survey_settings_hook" name="post-survey[hook]""  type="checkbox" value="1" ' . $checked . '  />';

        }

        /**
         * Allow registed users only field
         */
        public function field_private()
        {

            $value = $this->get('private');
            $checked = ($value == 1) ? 'checked' : '';
            echo '<input id="post_survey_settings_private" name="post-survey[private]""  type="checkbox" value="1" ' . $checked . '  />';


        }

        /**
         * Login url field
         */
        public function field_login_url()
        {
            $value = $this->get('login_url');
            echo "<input id='post_survey_settings_login_url' name='post-survey[login_url]'  type='text' value='{$value}'  />";


        }

        /**
         * Restrict duplicate field
         */
        public function field_unique_ip()
        {

            $value = $this->get('unique_ip');
            $checked = ($value == 1) ? 'checked' : '';
            echo '<input id="post_survey_settings_unique_ip" name="post-survey[unique_ip]""  type="checkbox" value="1" ' . $checked . '  />';

        }

        /**
         * Allow comment field
         */
        public function field_comment()
        {

            $value = $this->get('comment');
            $checked = ($value == 1) ? 'checked' : '';
            echo '<input id="post_survey_settings_comment" name="post-survey[comment]""  type="checkbox" value="1" ' . $checked . '  />';


        }

        /**
         * Show count in figure field
         */
        public function field_count_type()
        {
            $value = $this->get('count_type');
            $checked = ($value == 1) ? 'checked' : '';
            echo '<input id="post_survey_settings_count_type" name="post-survey[count_type]""  type="checkbox" value="1" ' . $checked . '  />';

        }

        /**
         * @param $input
         * @return mixed
         * Validation for future use, currently not in use
         */
        public function validate($input)
        {
            return $input;
        }

        /**
         * Hook menu in settings
         */
        public function menu()
        {
            add_options_page('Post Survey Settings', 'Post Survey Settings', 'manage_options', 'post-survey', array($this, 'backend'));
            add_menu_page('Post Survey Feedback', 'Post Survey Feedback', 'manage_options', 'post-survey-feedback', array($this, 'feeback_list'));


            remove_menu_page('post-survey-feedback');

        }

        /**
         * View post feedbacks
         */
        public function feeback_list()
        {

            global $wpdb;
            $table_name = $wpdb->prefix . 'post_survey';

            $post_id = $_GET['post'];


            $condition = "";

            if (!empty($_GET['from_date'])) {
                $from_date = $_GET['from_date'];
                $condition = " and date(created_at) >= '$from_date'";

            }

            if (!empty($_GET['to_date'])) {
                $to_date = $_GET['to_date'];
                $condition .= " and date(created_at) <= '$to_date'";
            }


            $skip = 0;
            $take = 20;

            $total = $wpdb->get_var($wpdb->prepare("select count(*) as total from $table_name where post_id = %d $condition", $post_id));


            $page = (!empty($_GET['paged']) && $_GET['paged'] > 1) ? $_GET['paged'] : 1;

            $skip = ($page - 1) * $take;

            $total_pages = ceil($total / $take);

            $results = $wpdb->get_results($wpdb->prepare("select * from $table_name where post_id = %d $condition limit $skip,$take", $post_id));


            include PS_BASE_DIR . '/list.php';
        }

        /**
         * Settings page
         */
        public function backend()
        {

            include PS_BASE_DIR . '/form.php';

        }

        /**
         * @param $template
         * @param $id
         * @return string
         * Render template
         */
        public function renderTemplate($template, $id)
        {


            $comment_field = "";

            if ($this->get('comment') == 1) {
                $comment_field = '<textarea id="post-survey-comment" placeholder="' . apply_filters('post_survey_comment_placeholder', 'Your feedback') . '"></textarea>';
            }

            $calc = $this->calculate($id);

            $count_positive = $calc['positive'];
            $count_negative = $calc['negative'];

            $vars = array("{id}", "{count_positive}", "{count_negative}", "{comment_field}");
            $values = array($id, $count_positive, $count_negative, $comment_field);
            return '<div class="post-survey-container" id="post-survey-container">' . str_replace($vars, $values, $template) . '</div>';

        }

        /**
         * @param $id
         * @return array
         * Calculte post feedback
         */
        public function calculate($id)
        {

            global $wpdb;
            $table_name = $wpdb->prefix . 'post_survey';

            $count_positive = $wpdb->get_var($wpdb->prepare("select count(*) as count_up from $table_name where mood = '1' and post_id = %d", $id));

            $count_negative = $wpdb->get_var($wpdb->prepare("select count(*) as count_down from $table_name where mood = '0' and post_id = %d", $id));


            if ($this->get('count_type') == 0) {

                $count_total = $wpdb->get_var($wpdb->prepare("select count(*) as count_total from $table_name where post_id = %d", $id));

                if ($count_positive > 0 && $count_total > 0) {
                    $count_positive = floor(($count_positive / $count_total) * 100);
                }

                if ($count_negative > 0 && $count_total > 0) {
                    $count_negative = floor(($count_negative / $count_total) * 100);
                }

            }

            return array('positive' => $count_positive, 'negative' => $count_negative);

        }

        /**
         * @param $attrs
         * @return string
         * Render short code
         */
        public function shortcode($attrs)
        {

            return $this->renderTemplate($this->get('template'), $attrs['id']);

        }

        /**
         * @param $content
         * @return string
         * Filter post content
         */
        public function post_content($content)
        {
            global $post;
            if (is_single()) {
                $content = $content . htmlspecialchars_decode($this->renderTemplate($this->get('template'), $post->ID));
            }

            return $content;
        }

        /**
         * Add javascript and style files
         */
        public function enqueue_files()
        {
            wp_register_style('post-survey', plugins_url('post-survey/style.css'));
            wp_register_script('post-survey', plugins_url('post-survey/script.js'), array('jquery-core'), '1.0');
            wp_enqueue_style('post-survey');
            wp_enqueue_script('post-survey');
        }

        public function admin_enqueue_files() {
            wp_register_style('post-survey-admin', plugins_url('post-survey/admin.css'));
            wp_enqueue_style('post-survey-admin');
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script('jquery-ui-datepicker', array('jquery'));
            wp_register_style('jquery-ui', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8/themes/base/jquery-ui.css');
            wp_enqueue_style( 'jquery-ui' );
        }

        /**
         * Settings for javascript use
         */
        public function footer_data()
        {
            ?>
            <script>
                var _wp_post_survey = {
                    ajax_url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    login_url: "<?php echo $this->get('login_url'); ?>",
                    site_url: "<?php echo get_site_url() ?>",
                    was_logged: <?php  echo is_user_logged_in() ? 1 : 0 ?>,
                    private: <?php echo $this->get('private') ? 1 : 0 ?>
                };
            </script>
            <?php
        }

        /**
         * Main method to handle request from user
         * This method collect feedback from user and save it in database
         */
        public function handle_survey()
        {

            global $wpdb;

            $type = $_POST['data']['type'];
            $post_id = $_POST['data']['post_id'];

            $ip = $this->client_ip();
            $user_email = null;
            $user_id = 0;
            $comment = null;

            $mood = 0;

            if ($type == 'positive') {
                $mood = 1;
            }

            if ($this->get('comment') == 1) {
                $comment = esc_html($_POST['data']['comment']);
            }


            $table_name = $wpdb->prefix . 'post_survey';

            $o = array('success' => true, 'errors' => array());

            if (is_user_logged_in()) {
                $user_id = get_current_user_id();
                $user_info = get_userdata(1);
                $user_email = $user_info->user_email;

                if ($wpdb->get_var($wpdb->prepare("select user_id from {$table_name} where post_id = %d and user_id = %d", $post_id, $user_id))) {

                    $o['success'] = false;
                    $o['alert'] = apply_filters('post_survey_duplicate_from_user', 'You have already voted');

                    echo json_encode($o);
                    exit;

                }
            }

            if ($this->get('private') == 1 && !is_user_logged_in()) {

                $o['success'] = true;
                $o['redirect'] = $this->get('login_url');

                echo json_encode($o);
                exit;

            }

            $private_condition = '';


            if ($this->get('unique_ip') == 1 && $wpdb->get_var($wpdb->prepare("select ip from {$table_name} where ip = %s and post_id = %d", $ip, $post_id))) {

                $o['success'] = false;
                $o['alert'] = apply_filters('post_survey_duplicate_from_ip', 'You have already voted');

                echo json_encode($o);
                exit;


            }

            if ($o['success'] == true) {

                $saved = $wpdb->insert($table_name,
                    array(
                        'post_id' => $post_id,
                        'user_id' => $user_id,
                        'email' => $user_email,
                        'mood' => $mood,
                        'comment' => $comment,
                        'ip' => $ip
                    ), array('%d', '%d', '%s', '%s', '%s', '%s'));


                if (!$saved) {

                    $o['success'] = false;
                    $o['alert'] = apply_filters('post_survey_failed', 'An error occurred while saving you inputs!');


                }
            }


            $calc = $this->calculate($post_id);

            $o['count_positive'] = $calc['positive'];
            $o['count_negative'] = $calc['negative'];

            $o['alert'] = apply_filters('post_survey_success', 'Thanks for you valuable feedback!');


            echo json_encode($o);
            exit;


        }

        /**
         * @return mixed
         * Method to get client ip
         */
        public function client_ip()
        {
            if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
                //check ip from share internet
                $ip = $_SERVER['HTTP_CLIENT_IP'];
            } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                //to check ip is pass from proxy
                $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } else {
                $ip = $_SERVER['REMOTE_ADDR'];
            }
            return $ip;
        }

        /**
         * Method runs when plugin activated
         */
        public function _activate()
        {

            $option = get_option('post-survey-info');
            $create_table = false;
            if (!$option) {
                add_option('post-survey-info', $this->info);
                $option = $this->info;
                $create_table = true;
            }

            if ($option['ver'] > $this->info('ver')) {
                $name = $this->info('name');
                wp_die("You are using latest version of $name, This version is not capable to replace it.");
            }

            if ($create_table) {
                global $wpdb;
                $charset_collate = $wpdb->get_charset_collate();
                $table_name = $wpdb->prefix . 'post_survey';

                $sql = "CREATE TABLE `$table_name` ( `post_id` bigint(20) NOT NULL, `user_id` bigint(20) NOT NULL DEFAULT '0', `email` varchar(100) DEFAULT NULL, `mood` enum('0','1') NOT NULL DEFAULT '0', `comment` varchar(255) DEFAULT NULL, `ip` varchar(16) DEFAULT NULL, `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ) $charset_collate;";

                require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
                dbDelta($sql);

                dbDelta("ALTER TABLE `$table_name` ADD KEY `post_id` (`post_id` ), ADD KEY `user_id` (`user_id`)");
            }


        }

        /**
         * Method to uninstall plugin
         */
        public static function _uninstall()
        {

            delete_option('post-survey-info');
            delete_option('post-survey');
            global $wpdb;
            $table = $wpdb->prefix . "post_survey";
            $wpdb->query("DROP TABLE IF EXISTS $table");

        }

    }
}

/**
 * Setup plugin
 */
new POST_SURVEY_SETUP;
