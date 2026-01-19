<?php
/**
 * Opentape - Login Page
 */

require_once("opentape_common.php");

send_security_headers();
init_session();

// If already logged in, redirect to edit
if (is_logged_in()) {
    header("Location: " . $REL_PATH . "code/edit.php");
    exit();
}

// If no password set, redirect to welcome
if (!is_password_set()) {
    header("Location: " . $REL_PATH . "code/welcome.php");
    exit();
}

$status_msg = '';

// Handle login attempt
if (!empty($_POST['pass'])) {
    if (check_password($_POST['pass'])) {
        create_session();
        header("Location: " . $REL_PATH . "code/edit.php");
        exit();
    } else {
        $status_msg = 'Bad Password :(';
    }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Opentape / Admin Login</title>
    <link rel="stylesheet" href="<?php echo $REL_PATH; ?>res/style.css">
</head>
<body>
    <div class="container">
        <div class="banner">
            <h1>OPENTAPE</h1>
            <ul class="nav">
                <li id="user">
                    <a id="home" href="<?php echo $REL_PATH; ?>">YOUR TAPE &rarr;</a>
                </li>
            </ul>
            <div class="ajax_status"></div>
        </div>

        <div class="content">
            <div class="section">
                <form method="post" action="<?php echo htmlspecialchars(get_base_url() . 'code/login.php', ENT_QUOTES, 'UTF-8'); ?>">
                    <label for="pass">Password:</label>
                    <input name="pass" type="password" size="25" id="pass" autofocus><br>
                    <input type="submit" class="button" value="LOGIN">
                </form>
            </div>

            <div class="footer">
                <?php get_version_banner(); ?>
            </div>
        </div>
    </div>

<?php if (!empty($status_msg)): ?>
    <script src="<?php echo $REL_PATH; ?>res/statusfader.js"></script>
    <script>
        var fader = new StatusFader();
        fader.flash(<?php echo json_encode($status_msg); ?>, '#ff0000');
    </script>
<?php endif; ?>

</body>
</html>
