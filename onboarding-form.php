<?php
/**
 * Plugin Name: Onboarding Form
 * Plugin URI: 
 * Description: Een stapsgewijze onboarding formulier met animaties
 * Version: 1.1
 * Author: JpWebcreation
 * Author URI: https://jpwebcreation.nl/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: onboarding-form
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

// Activation hook
register_activation_hook(__FILE__, 'onboarding_form_activate');

function onboarding_form_activate() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        question_text text NOT NULL,
        field_type varchar(50) NOT NULL,
        options text NULL,
        step_order int NOT NULL DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY  (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        wp_die('Fout bij het aanmaken van de database tabel. Neem contact op met de beheerder.');
    }
}

// Add an admin notice if table doesn't exist
function check_table_exists() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        echo '<div class="notice notice-error"><p>De onboarding form tabel bestaat niet. Deactiveer en activeer de plugin opnieuw.</p></div>';
    }
}
add_action('admin_notices', 'check_table_exists');

// Add menu item to WordPress admin
function onboarding_form_menu() {
    add_menu_page(
        'Onboarding Form', // Page title
        'Onboarding Form', // Menu title
        'manage_options', // Capability required
        'onboarding-form', // Menu slug
        'onboarding_form_page', // Function to display the page
        'dashicons-welcome-write-blog', // Icon
        30 // Position
    );
    
    // Add submenu for settings
    add_submenu_page(
        'onboarding-form',
        'Instellingen',
        'Instellingen',
        'manage_options',
        'onboarding-form-settings',
        'onboarding_form_settings_page'
    );
}
add_action('admin_menu', 'onboarding_form_menu');

// Add AJAX handler for updating question order
add_action('wp_ajax_update_question_order', 'update_question_order');
function update_question_order() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    
    check_admin_referer('question_order_nonce', 'nonce');
    
    $order = $_POST['order'];
    foreach ($order as $position => $id) {
        $wpdb->update(
            $table_name,
            array('step_order' => $position * 10),
            array('id' => intval($id)),
            array('%d'),
            array('%d')
        );
    }
    wp_send_json_success();
}

// Display the admin page content
function onboarding_form_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    
    // Start output buffering at the very beginning
    ob_start();
    
    // Process form submissions
    if (!empty($_POST)) {
        // Handle form submission for edit
        if (isset($_POST['update_question'])) {
            check_admin_referer('question_nonce', 'question_nonce');
            $id = intval($_POST['question_id']);
            $question_text = sanitize_text_field($_POST['question_text']);
            $field_type = sanitize_text_field($_POST['field_type']);
            $options = '';
            
            if (in_array($field_type, ['dropdown', 'multiselect']) && isset($_POST['options'])) {
                $option_values = array_map('sanitize_text_field', $_POST['options']);
                $option_values = array_filter($option_values, 'strlen');
                $options = implode("\n", $option_values);
            }
            
            $result = $wpdb->update(
                $table_name,
                array(
                    'question_text' => $question_text,
                    'field_type' => $field_type,
                    'options' => $options
                ),
                array('id' => $id),
                array('%s', '%s', '%s'),
                array('%d')
            );

            if ($result === false) {
                set_transient('onboarding_form_message', array(
                    'type' => 'error',
                    'message' => 'Er is een fout opgetreden bij het bijwerken van de vraag: ' . $wpdb->last_error
                ), 45);
            } else {
                set_transient('onboarding_form_message', array(
                    'type' => 'success',
                    'message' => 'Vraag succesvol bijgewerkt!'
                ), 45);
            }
            
            // Instead of redirect, set a flag to use JavaScript redirect
            echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=onboarding-form')) . '";</script>';
            exit;
        }

        // Handle form submission for new question
        if (isset($_POST['submit_question'])) {
            check_admin_referer('question_nonce', 'question_nonce');
            if (empty($_POST['question_text']) || empty($_POST['field_type'])) {
                set_transient('onboarding_form_message', array(
                    'type' => 'error',
                    'message' => 'Alle verplichte velden moeten worden ingevuld.'
                ), 45);
            } else {
                $question_text = sanitize_text_field($_POST['question_text']);
                $field_type = sanitize_text_field($_POST['field_type']);
                $options = '';
                
                if (in_array($field_type, ['dropdown', 'multiselect']) && isset($_POST['options'])) {
                    $option_values = array_map('sanitize_text_field', $_POST['options']);
                    $option_values = array_filter($option_values, 'strlen');
                    $options = implode("\n", $option_values);
                }
                
                $max_order = $wpdb->get_var("SELECT MAX(step_order) FROM $table_name");
                $step_order = (int)$max_order + 1000;
                
                $result = $wpdb->insert(
                    $table_name,
                    array(
                        'question_text' => $question_text,
                        'field_type' => $field_type,
                        'options' => $options,
                        'step_order' => $step_order
                    ),
                    array('%s', '%s', '%s', '%d')
                );

                if ($result === false) {
                    set_transient('onboarding_form_message', array(
                        'type' => 'error',
                        'message' => 'Er is een fout opgetreden bij het toevoegen van de vraag: ' . $wpdb->last_error
                    ), 45);
                } else {
                    set_transient('onboarding_form_message', array(
                        'type' => 'success',
                        'message' => 'Vraag succesvol toegevoegd!'
                    ), 45);
                }
            }
            
            // Instead of redirect, use JavaScript redirect
            echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=onboarding-form')) . '";</script>';
            exit;
        }
    }

    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $wpdb->delete($table_name, array('id' => $id), array('%d'));
        set_transient('onboarding_form_message', array(
            'type' => 'success',
            'message' => 'Vraag succesvol verwijderd!'
        ), 45);
        echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=onboarding-form')) . '";</script>';
        exit;
    }

    // Handle edit action
    $editing = false;
    $question_to_edit = null;
    if (isset($_GET['action']) && $_GET['action'] === 'edit' && isset($_GET['id'])) {
        $id = intval($_GET['id']);
        $question_to_edit = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE id = %d", $id));
        if ($question_to_edit === null) {
            set_transient('onboarding_form_message', array(
                'type' => 'error',
                'message' => 'Vraag niet gevonden.'
            ), 45);
            echo '<script>window.location.href = "' . esc_url(admin_url('admin.php?page=onboarding-form')) . '";</script>';
            exit;
        }
        $editing = true;
    }

    // Get all questions
    $questions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY step_order DESC");
    
    // Enqueue styles and scripts
    wp_enqueue_style('onboarding-admin-style', plugins_url('css/admin-style.css', __FILE__));
    wp_enqueue_script('onboarding-admin-script', plugins_url('js/admin-script.js', __FILE__), array('jquery', 'jquery-ui-sortable'), '1.0.0', true);
    wp_localize_script('onboarding-admin-script', 'onboardingAjax', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('question_order_nonce')
    ));
    
    // Output the admin interface
    ?>
    <div class="wrap">
        <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
        
        <?php
        // Display any messages
        $message = get_transient('onboarding_form_message');
        if ($message) {
            delete_transient('onboarding_form_message');
            $class = ($message['type'] === 'error') ? 'notice-error' : 'notice-success';
            ?>
            <div class="notice <?php echo $class; ?> is-dismissible">
                <p><?php echo esc_html($message['message']); ?></p>
            </div>
            <?php
        }
        ?>
        
        <div class="admin-columns">
            <!-- Add/Edit question form -->
            <div class="admin-column">
                <div class="card">
                    <h2><?php echo $editing ? 'Vraag bewerken' : 'Nieuwe vraag toevoegen'; ?></h2>
                    <form method="post" action="">
                        <?php wp_nonce_field('question_nonce', 'question_nonce'); ?>
                        <?php if ($editing): ?>
                            <input type="hidden" name="question_id" value="<?php echo esc_attr($question_to_edit->id); ?>">
                        <?php endif; ?>
                        <table class="form-table">
                            <tr>
                                <th><label for="question_text">Vraag</label></th>
                                <td>
                                    <input type="text" name="question_text" id="question_text" class="regular-text" required 
                                        value="<?php echo $editing && isset($question_to_edit->question_text) ? esc_attr($question_to_edit->question_text) : ''; ?>">
                                    <div class="media-placeholder">
                                        <span class="dashicons dashicons-images-alt2"></span>
                                        <p>Media toevoegen (binnenkort beschikbaar)</p>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <th><label for="field_type">Type veld</label></th>
                                <td>
                                    <select name="field_type" id="field_type" required>
                                        <option value="text" <?php echo $editing && isset($question_to_edit->field_type) && $question_to_edit->field_type === 'text' ? 'selected' : ''; ?>>Tekstveld</option>
                                        <option value="dropdown" <?php echo $editing && isset($question_to_edit->field_type) && $question_to_edit->field_type === 'dropdown' ? 'selected' : ''; ?>>Dropdown</option>
                                        <option value="multiselect" <?php echo $editing && isset($question_to_edit->field_type) && $question_to_edit->field_type === 'multiselect' ? 'selected' : ''; ?>>Meervoudige selectie</option>
                                    </select>
                                    <p class="description">Kies het type invoerveld voor deze vraag</p>
                                </td>
                            </tr>
                            <tr class="options-row" style="display: none;">
                                <th><label>Opties</label></th>
                                <td>
                                    <button type="button" class="button add-option">+ Optie toevoegen</button>
                                    <div class="options-container">
                                        <?php
                                        if ($editing && in_array($question_to_edit->field_type, ['dropdown', 'multiselect'])) {
                                            $options = explode("\n", $question_to_edit->options);
                                            foreach ($options as $option) {
                                                echo '<div class="option-row">';
                                                echo '<input type="text" name="options[]" value="' . esc_attr(trim($option)) . '" class="regular-text">';
                                                echo '<button type="button" class="button remove-option">-</button>';
                                                echo '</div>';
                                            }
                                        } else {
                                            ?>
                                            <div class="option-row">
                                                <input type="text" name="options[]" class="regular-text" placeholder="Voer een optie in">
                                            </div>
                                            <?php
                                        }
                                        ?>
                                    </div>
                                    <p class="description">Voeg opties toe voor dropdown of meervoudige selectie</p>
                                </td>
                            </tr>
                        </table>
                        <p class="submit">
                            <?php if ($editing): ?>
                                <input type="submit" name="update_question" class="button button-primary" value="Vraag bijwerken">
                                <a href="?page=onboarding-form" class="button">Annuleren</a>
                            <?php else: ?>
                                <input type="submit" name="submit_question" class="button button-primary" value="Vraag toevoegen">
                            <?php endif; ?>
                        </p>
                    </form>
                </div>
            </div>

            <!-- Questions list -->
            <div class="admin-column questions-list">
                <div class="card">
                    <h2>Bestaande vragen</h2>
                    <?php if (empty($questions)): ?>
                        <p>Er zijn nog geen vragen toegevoegd.</p>
                    <?php else: ?>
                    <p class="description">Sleep de vragen om de volgorde aan te passen.</p>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th>Vraag</th>
                                <th>Type</th>
                                <th>Opties</th>
                                <th>Acties</th>
                            </tr>
                        </thead>
                        <tbody class="sortable-questions">
                            <?php foreach ($questions as $question): ?>
                            <tr data-id="<?php echo $question->id; ?>">
                                <td><span class="dashicons dashicons-menu"></span> <?php echo esc_html($question->question_text); ?></td>
                                <td><?php echo esc_html($question->field_type); ?></td>
                                <td><?php echo esc_html($question->options); ?></td>
                                <td>
                                    <a href="?page=onboarding-form&action=edit&id=<?php echo $question->id; ?>" class="button button-small">Bewerken</a>
                                    <a href="?page=onboarding-form&action=delete&id=<?php echo $question->id; ?>" class="button button-small" onclick="return confirm('Weet je zeker dat je deze vraag wilt verwijderen?')">Verwijderen</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Add PHP mailer configuration
add_action('phpmailer_init', 'configure_php_mailer');
function configure_php_mailer($phpmailer) {
    // Set up SMTP
    $phpmailer->isSMTP();
    $phpmailer->Host = 'smtp.gmail.com'; // Gmail SMTP server
    $phpmailer->SMTPAuth = true;
    $phpmailer->Port = 587; // TLS port
    $phpmailer->SMTPSecure = 'tls';
    
    // Set the sender
    $phpmailer->From = 'wordpress@' . $_SERVER['HTTP_HOST'];
    $phpmailer->FromName = get_bloginfo('name');
    
    // Debug info
    $debug_messages = array(
        'Mailer: SMTP',
        'From: ' . $phpmailer->From,
        'FromName: ' . $phpmailer->FromName,
        'Host: ' . $phpmailer->Host,
        'Port: ' . $phpmailer->Port,
        'SMTPSecure: ' . $phpmailer->SMTPSecure
    );
    update_option('onboarding_mail_debug', $debug_messages);
}

// Add settings page
function onboarding_form_settings_page() {
    $debug_messages = array();
    
    if (isset($_POST['save_settings'])) {
        check_admin_referer('onboarding_settings_nonce', 'settings_nonce');
        $notification_email = sanitize_email($_POST['notification_email']);
        $smtp_user = sanitize_text_field($_POST['smtp_user']);
        $smtp_pass = sanitize_text_field($_POST['smtp_pass']);
        
        update_option('onboarding_form_notification_email', $notification_email);
        update_option('onboarding_form_smtp_user', $smtp_user);
        if (!empty($smtp_pass)) {
            update_option('onboarding_form_smtp_pass', $smtp_pass);
        }
        echo '<div class="notice notice-success is-dismissible"><p>Instellingen opgeslagen!</p></div>';
    }
    
    if (isset($_POST['test_email'])) {
        check_admin_referer('onboarding_settings_nonce', 'settings_nonce');
        $debug_messages = test_wp_mail();
    }
    
    $notification_email = get_option('onboarding_form_notification_email', '');
    $smtp_user = get_option('onboarding_form_smtp_user', '');
    ?>
    <div class="wrap">
        <h1>Onboarding Form Instellingen</h1>
        <form method="post" action="">
            <?php wp_nonce_field('onboarding_settings_nonce', 'settings_nonce'); ?>
            <table class="form-table">
                <tr>
                    <th><label for="notification_email">Notificatie E-mail</label></th>
                    <td>
                        <input type="email" name="notification_email" id="notification_email" 
                               class="regular-text" value="<?php echo esc_attr($notification_email); ?>" required>
                        <p class="description">E-mailadres waar formulier inzendingen naar worden verzonden.</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="smtp_user">SMTP Gebruikersnaam</label></th>
                    <td>
                        <input type="text" name="smtp_user" id="smtp_user" 
                               class="regular-text" value="<?php echo esc_attr($smtp_user); ?>" required>
                        <p class="description">Gmail adres voor SMTP authenticatie</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="smtp_pass">SMTP Wachtwoord</label></th>
                    <td>
                        <input type="password" name="smtp_pass" id="smtp_pass" class="regular-text">
                        <p class="description">Gmail app-specifiek wachtwoord (laat leeg om bestaand wachtwoord te behouden)</p>
                    </td>
                </tr>
                <tr>
                    <th></th>
                    <td>
                        <input type="submit" name="test_email" class="button" value="Test Email Verzenden">
                        <span class="description">Klik om een test email te verzenden en de configuratie te controleren.</span>
                    </td>
                </tr>
            </table>
            <p class="submit">
                <input type="submit" name="save_settings" class="button button-primary" value="Instellingen Opslaan">
            </p>
        </form>
        
        <?php if (!empty($debug_messages)): ?>
        <div class="card">
            <h2>Email Debug Informatie</h2>
            <pre style="background: #f8f9fa; padding: 15px; border: 1px solid #ddd; overflow: auto;">
<?php foreach ($debug_messages as $message): ?>
<?php echo esc_html($message) . "\n"; ?>
<?php endforeach; ?>
            </pre>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h2>Gmail SMTP Configuratie Hulp</h2>
            <p>Om Gmail SMTP te gebruiken, volg deze stappen:</p>
            <ol>
                <li>Ga naar je Google Account instellingen</li>
                <li>Schakel 2-staps verificatie in als je dat nog niet hebt gedaan</li>
                <li>Ga naar 'App-wachtwoorden' in de beveiligingsinstellingen</li>
                <li>Maak een nieuw app-wachtwoord aan voor 'Mail' en 'WordPress'</li>
                <li>Gebruik dit gegenereerde wachtwoord in het SMTP Wachtwoord veld hierboven</li>
                <li>Gebruik je volledige Gmail adres als SMTP gebruikersnaam</li>
            </ol>
        </div>
    </div>
    <?php
}

// Modify the test email function to use SMTP credentials
function test_wp_mail() {
    $debug_messages = array();
    $to = get_option('onboarding_form_notification_email');
    
    if (empty($to)) {
        $debug_messages[] = 'ERROR: No notification email configured';
        return $debug_messages;
    }
    
    // Add SMTP credentials to PHPMailer
    add_action('phpmailer_init', function($phpmailer) {
        $smtp_user = get_option('onboarding_form_smtp_user');
        $smtp_pass = get_option('onboarding_form_smtp_pass');
        
        if (!empty($smtp_user) && !empty($smtp_pass)) {
            $phpmailer->Username = $smtp_user;
            $phpmailer->Password = $smtp_pass;
        }
    });
    
    $debug_messages[] = 'Attempting to send test email to: ' . $to;
    
    $subject = 'Test Email from Onboarding Form';
    $message = 'This is a test email to verify the email sending functionality is working.';
    $headers = array(
        'Content-Type: text/plain; charset=UTF-8'
    );
    
    $email_sent = wp_mail($to, $subject, $message, $headers);
    $debug_messages[] = 'Email sending attempt result: ' . ($email_sent ? 'Success' : 'Failed');
    
    if (!$email_sent) {
        global $phpmailer;
        if (isset($phpmailer) && is_object($phpmailer)) {
            $debug_messages[] = 'PHPMailer Error: ' . $phpmailer->ErrorInfo;
        }
    }
    
    // Get WordPress email settings
    $debug_messages[] = "\nWordPress Email Configuration:";
    $debug_messages[] = 'WordPress Email: ' . get_option('admin_email');
    $debug_messages[] = 'SMTP Plugin Active: ' . (defined('WPMS_PLUGIN_VER') ? 'Yes' : 'No');
    
    // Add PHP mailer debug info
    $php_mail_debug = get_option('onboarding_mail_debug', array());
    if (!empty($php_mail_debug)) {
        $debug_messages[] = "\nPHP Mailer Configuration:";
        foreach ($php_mail_debug as $debug_line) {
            $debug_messages[] = $debug_line;
        }
    }
    
    return $debug_messages;
}

// Register shortcode
function onboarding_form_shortcode($atts) {
    // Enqueue necessary styles and scripts
    wp_enqueue_style('onboarding-form-style', plugins_url('css/style.css', __FILE__));
    wp_enqueue_script('onboarding-form-script', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0.0', true);
    
    // Pass email settings to JavaScript
    wp_localize_script('onboarding-form-script', 'onboardingFormSettings', array(
        'ajaxurl' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('onboarding_submit_nonce')
    ));
    
    // Get questions from database
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    $questions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY step_order ASC");
    
    // Start output buffering
    ob_start();
    ?>
    <div class="onboarding-form-container">
        <form class="onboarding-form" method="post">
            <div class="form-progress">
                <div class="progress-bar"></div>
            </div>
            <div class="form-steps">
                <?php foreach ($questions as $index => $question): ?>
                <div class="form-step <?php echo $index === 0 ? 'active' : ''; ?>" data-step="<?php echo $index; ?>">
                    <h2><?php echo esc_html($question->question_text); ?></h2>
                    <?php
                    switch ($question->field_type) {
                        case 'text':
                            echo '<input type="text" class="form-input" name="question_' . $question->id . '" data-question-id="' . $question->id . '">';
                            break;
                        case 'dropdown':
                            $options = explode("\n", $question->options);
                            echo '<select class="form-select" name="question_' . $question->id . '" data-question-id="' . $question->id . '">';
                            echo '<option value="">Selecteer een optie</option>';
                            foreach ($options as $option) {
                                echo '<option value="' . esc_attr(trim($option)) . '">' . esc_html(trim($option)) . '</option>';
                            }
                            echo '</select>';
                            break;
                        case 'multiselect':
                            $options = explode("\n", $question->options);
                            echo '<div class="multiselect-container" data-question-id="' . $question->id . '">';
                            foreach ($options as $option) {
                                $option_value = trim($option);
                                echo '<label><input type="checkbox" name="question_' . $question->id . '[]" value="' . esc_attr($option_value) . '"> ' . esc_html($option_value) . '</label>';
                            }
                            echo '</div>';
                            break;
                    }
                    ?>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="form-navigation">
                <button type="button" class="prev-step" style="display: none;">Vorige</button>
                <button type="button" class="next-step">Volgende</button>
                <button type="submit" class="submit-form" style="display: none;">Versturen</button>
            </div>
        </form>
    </div>
    <?php
    // Return the buffered content
    return ob_get_clean();
}
add_shortcode('onboarding_form', 'onboarding_form_shortcode');

// Register Elementor widget
function register_onboarding_form_widget($widgets_manager) {
    require_once(__DIR__ . '/widgets/onboarding-form-widget.php');
    $widgets_manager->register(new \Onboarding_Form_Widget());
}
add_action('elementor/widgets/register', 'register_onboarding_form_widget');

// Add AJAX handler for form submissions
add_action('wp_ajax_onboarding_form_submit', 'handle_onboarding_form_submit');
add_action('wp_ajax_nopriv_onboarding_form_submit', 'handle_onboarding_form_submit');

function handle_onboarding_form_submit() {
    check_ajax_referer('onboarding_submit_nonce', 'nonce');
    
    $submission_data = $_POST['formData'];
    $notification_email = get_option('onboarding_form_notification_email');
    $debug_messages = array();
    
    $debug_messages[] = 'Form submission data: ' . print_r($submission_data, true);
    $debug_messages[] = 'Notification email: ' . $notification_email;
    
    // Send email
    $subject = 'Nieuwe Onboarding Form Inzending';
    $message = "Er is een nieuwe inzending ontvangen:\n\n";
    
    foreach ($submission_data as $question_id => $answer) {
        $question_text = get_question_text($question_id);
        $message .= $question_text . ": " . (is_array($answer) ? implode(', ', $answer) : $answer) . "\n";
    }
    
    $headers = array('Content-Type: text/plain; charset=UTF-8');
    
    if (!empty($notification_email)) {
        $email_sent = wp_mail($notification_email, $subject, $message, $headers);
        $debug_messages[] = 'Email sending attempt result: ' . ($email_sent ? 'Success' : 'Failed');
        
        if (!$email_sent) {
            global $phpmailer;
            if (isset($phpmailer) && is_object($phpmailer)) {
                $debug_messages[] = 'PHPMailer Error: ' . $phpmailer->ErrorInfo;
            }
        }
    }
    
    wp_send_json_success(array(
        'message' => 'Formulier succesvol verzonden',
        'debug' => array(
            'notification_email' => $notification_email,
            'submission_data' => $submission_data,
            'email_sent' => isset($email_sent) ? $email_sent : false,
            'email_error' => isset($phpmailer) && is_object($phpmailer) ? $phpmailer->ErrorInfo : '',
            'debug_messages' => $debug_messages
        )
    ));
}

function get_question_text($question_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    $question = $wpdb->get_var($wpdb->prepare("SELECT question_text FROM $table_name WHERE id = %d", $question_id));
    return $question ? $question : 'Onbekende vraag';
} 