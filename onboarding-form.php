<?php
/**
 * Plugin Name: Onboarding Form
 * Plugin URI: 
 * Description: Een stapsgewijze onboarding formulier met animaties
 * Version: 1.0.0
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
                                        value="<?php echo $editing ? esc_attr($question_to_edit->question_text) : ''; ?>">
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
                                        <option value="text" <?php echo $editing && $question_to_edit->field_type === 'text' ? 'selected' : ''; ?>>Tekstveld</option>
                                        <option value="dropdown" <?php echo $editing && $question_to_edit->field_type === 'dropdown' ? 'selected' : ''; ?>>Dropdown</option>
                                        <option value="multiselect" <?php echo $editing && $question_to_edit->field_type === 'multiselect' ? 'selected' : ''; ?>>Meervoudige selectie</option>
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

// Register shortcode
function onboarding_form_shortcode($atts) {
    // Enqueue necessary styles and scripts
    wp_enqueue_style('onboarding-form-style', plugins_url('css/style.css', __FILE__));
    wp_enqueue_script('onboarding-form-script', plugins_url('js/script.js', __FILE__), array('jquery'), '1.0.0', true);
    
    // Get questions from database
    global $wpdb;
    $table_name = $wpdb->prefix . 'onboarding_questions';
    $questions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY step_order ASC");
    
    // Start output buffering
    ob_start();
    ?>
    <div class="onboarding-form-container">
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
                        echo '<input type="text" class="form-input" data-question-id="' . $question->id . '">';
                        break;
                    case 'dropdown':
                        $options = explode("\n", $question->options);
                        echo '<select class="form-select" data-question-id="' . $question->id . '">';
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
                            echo '<label><input type="checkbox" value="' . esc_attr(trim($option)) . '"> ' . esc_html(trim($option)) . '</label>';
                        }
                        echo '</div>';
                        break;
                }
                ?>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="form-navigation">
            <button class="prev-step" style="display: none;">Vorige</button>
            <button class="next-step">Volgende</button>
            <button class="submit-form" style="display: none;">Versturen</button>
        </div>
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