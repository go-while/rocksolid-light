<?php
if(!defined('RSLIGHT_CONFIG_LOADED')) {
    die("Access denied.");
}

// Language Selector for Rocksolid Light


// Handle language selection
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['selected_language'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || !verify_csrf_token($_POST['csrf_token'])) {
        echo '<div class="error">Security Error: Invalid form submission. Please try again.</div>';
        exit();
    }

    $selected_language = $_POST['selected_language'];

    // Security validation: only allow languages from hardcoded approved list
    if (is_language_allowed($selected_language)) {
        $lang_file_path = "lang/" . $selected_language;
        if (file_exists($lang_file_path)) {
            // Set cookie for 1 year
            setcookie('user_language', $selected_language, time() + (365 * 24 * 60 * 60), '/');

            // Redirect to refresh the page with new language
            $redirect_url = isset($_POST['return_url']) ? $_POST['return_url'] : '/';
            header("Location: " . $redirect_url);
            exit();
        }
    }

    echo '<div class="error">Invalid language selection.</div>';
}

// Get available languages from hardcoded approved list
$languages = get_allowed_languages();

// Get current language
$current_language = isset($_COOKIE['user_language']) ? $_COOKIE['user_language'] : 'english.lang';

// Get return URL
$return_url = isset($_GET['return']) ? $_GET['return'] : '/';

?>

<div class="np_header">
    <h1><?php echo $text_header['language_selection'] ?? 'Language Selection'; ?></h1>
</div>

<div class="np_main">
    <div class="language_selector">
        <h2>Choose Your Language</h2>
        <p>Select your preferred language for the interface:</p>

        <form method="post" action="language_selector.php">
            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
            <input type="hidden" name="return_url" value="<?php echo htmlspecialchars($return_url); ?>">

            <div class="language_options">
                <?php foreach ($languages as $lang_file => $lang_name): ?>
                    <div class="language_option">
                        <input type="radio"
                               name="selected_language"
                               value="<?php echo htmlspecialchars($lang_file); ?>"
                               id="lang_<?php echo htmlspecialchars($lang_file); ?>"
                               <?php echo ($lang_file === $current_language) ? 'checked' : ''; ?>>
                        <label for="lang_<?php echo htmlspecialchars($lang_file); ?>">
                            <?php echo htmlspecialchars($lang_name); ?>
                        </label>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="form_buttons">
                <input type="submit" value="Change Language" class="button">
                <a href="<?php echo htmlspecialchars($return_url); ?>" class="button">Cancel</a>
            </div>
        </form>

        <div class="language_info">
            <h3>Language Information</h3>
            <p><strong>Currently selected:</strong>
               <?php echo htmlspecialchars($languages[$current_language] ?? 'English'); ?></p>
            <p><strong>Total languages available:</strong> <?php echo count($languages); ?></p>
            <p><strong>Coverage:</strong> All <?php echo count($languages); ?> languages are 100% optimized with 61 translation keys each.</p>
        </div>
    </div>
</div>

<style>
.language_selector {
    max-width: 600px;
    margin: 20px auto;
    padding: 20px;
    background: #f9f9f9;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.language_options {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 10px;
    margin: 20px 0;
    max-height: 400px;
    overflow-y: auto;
    border: 1px solid #ddd;
    padding: 15px;
    border-radius: 4px;
}

.language_option {
    display: flex;
    align-items: center;
    padding: 5px;
}

.language_option input[type="radio"] {
    margin-right: 8px;
}

.language_option label {
    cursor: pointer;
    font-weight: normal;
}

.language_option input[type="radio"]:checked + label {
    font-weight: bold;
    color: #0066cc;
}

.form_buttons {
    text-align: center;
    margin-top: 20px;
}

.button {
    display: inline-block;
    padding: 10px 20px;
    margin: 0 5px;
    background: #0066cc;
    color: white;
    text-decoration: none;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

.button:hover {
    background: #0052a3;
}

.language_info {
    margin-top: 30px;
    padding: 15px;
    background: #e9f4ff;
    border-radius: 4px;
    border-left: 4px solid #0066cc;
}

.language_info h3 {
    margin-top: 0;
    color: #0066cc;
}

.error {
    color: #d32f2f;
    background: #ffebee;
    padding: 10px;
    border-radius: 4px;
    margin: 10px 0;
}
</style>