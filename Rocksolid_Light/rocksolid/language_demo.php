<?php
// Simple test script to demonstrate language switching
include "lib/config.inc.php";
include "allowed_languages.inc.php";

?><!DOCTYPE html>
<html>
<head>
    <title>Language Switch Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; }
        .demo-box { background: #f0f8ff; padding: 20px; margin: 20px 0; border-radius: 8px; border: 1px solid #ddd; }
        .language-info { background: #e8f5e8; padding: 15px; border-radius: 4px; margin: 10px 0; }
        .translation-examples { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px; }
        .translation-box { background: white; padding: 15px; border-radius: 4px; border: 1px solid #ccc; }
        .button { padding: 10px 20px; background: #0066cc; color: white; text-decoration: none; border-radius: 4px; display: inline-block; margin: 5px; }
        .current-lang { font-weight: bold; color: #0066cc; }
    </style>
</head>
<body>
    <h1>🌐 Rocksolid Light Language Switching Demo</h1>

    <div class="demo-box">
        <h2>Current Language Status</h2>
        <div class="language-info">
            <p><strong>Active Language File:</strong> <span class="current-lang"><?php echo htmlspecialchars($file_language); ?></span></p>
            <p><strong>Cookie Value:</strong> <?php echo isset($_COOKIE['user_language']) ? htmlspecialchars($_COOKIE['user_language']) : 'Not set (using default)'; ?></p>
            <p><strong>File Exists:</strong> <?php echo file_exists($file_language) ? '✅ Yes' : '❌ No'; ?></p>
        </div>
    </div>

    <div class="demo-box">
        <h2>Translation Examples</h2>
        <p>Here are some translated text examples using the current language:</p>

        <div class="translation-examples">
            <div class="translation-box">
                <h3>Thread Interface</h3>
                <ul>
                    <li><strong>New Thread:</strong> <?php echo htmlspecialchars($text_thread["button_write"] ?? 'Not available'); ?></li>
                    <li><strong>Search:</strong> <?php echo htmlspecialchars($text_thread["button_search"] ?? 'Not available'); ?></li>
                    <li><strong>Author:</strong> <?php echo strip_tags($text_thread["author"] ?? 'Not available'); ?></li>
                    <li><strong>Date:</strong> <?php echo strip_tags($text_thread["date"] ?? 'Not available'); ?></li>
                </ul>
            </div>

            <div class="translation-box">
                <h3>Article Interface</h3>
                <ul>
                    <li><strong>Refresh:</strong> <?php echo htmlspecialchars($text_article["refresh"] ?? 'Not available'); ?></li>
                    <li><strong>Reply:</strong> <?php echo htmlspecialchars($text_article["button_answer"] ?? 'Not available'); ?></li>
                    <li><strong>Cancel:</strong> <?php echo htmlspecialchars($text_article["button_cancel"] ?? 'Not available'); ?></li>
                </ul>
            </div>

            <div class="translation-box">
                <h3>Post Interface</h3>
                <ul>
                    <li><strong>Username:</strong> <?php echo htmlspecialchars($text_post["name"] ?? 'Not available'); ?></li>
                    <li><strong>Password:</strong> <?php echo htmlspecialchars($text_post["password"] ?? 'Not available'); ?></li>
                    <li><strong>Email:</strong> <?php echo htmlspecialchars($text_post["email"] ?? 'Not available'); ?></li>
                    <li><strong>Message:</strong> <?php echo htmlspecialchars($text_post["message"] ?? 'Not available'); ?></li>
                </ul>
            </div>
        </div>
    </div>

    <div class="demo-box">
        <h2>Quick Language Switch</h2>
        <p>Test different languages instantly:</p>        <?php
        // Get available languages from hardcoded approved list
        $test_languages = get_allowed_languages();
        // Show first 8 for quick testing
        $test_languages = array_slice($test_languages, 0, 8, true);

        foreach ($test_languages as $lang_file => $lang_name): ?>
            <a href="?setlang=<?php echo urlencode($lang_file); ?>" class="button">
                <?php echo htmlspecialchars($lang_name); ?>
            </a>
        <?php endforeach; ?>

        <?php
        // Handle quick language switch
        if (isset($_GET['setlang'])) {
            $setlang = $_GET['setlang'];
            if (is_language_allowed($setlang) && file_exists("lang/" . $setlang)) {
                setcookie('user_language', $setlang, time() + (365 * 24 * 60 * 60), '/');
                echo '<script>setTimeout(function(){ window.location.reload(); }, 100);</script>';
            }
        }
        ?>
    </div>

    <div class="demo-box">
        <h2>Language Management</h2>
        <a href="language_selector.php" class="button">🌐 Full Language Selector</a>
        <a href="?" class="button">🔄 Refresh Demo</a>
        <a href="../" class="button">🏠 Back to Main Site</a>
    </div>

    <div class="demo-box">
        <h2>Implementation Details</h2>
        <ul>
            <li><strong>Storage:</strong> Language preference stored in browser cookie (1 year expiry)</li>
            <li><strong>Fallback:</strong> Falls back to English if selected language file doesn't exist</li>
            <li><strong>Security:</strong> Only allows valid .lang files from the lang directory</li>
            <li><strong>Coverage:</strong> <?php echo count(get_allowed_languages()); ?> languages available, all 100% optimized</li>
            <li><strong>Keys:</strong> Each language has exactly 61 translation keys</li>
        </ul>
    </div>

</body>
</html>
