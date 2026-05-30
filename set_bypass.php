<?php
/**
 * Local Machine Login Bypass Setter
 * RUN ONCE ON LOCAL MACHINE, THEN DELETE OR IGNORE.
 */

$secret_hash = '9f86d081884c7d659a2feaa0c55ad015a3bf4f1b2b0b822cd15d6c15b0f00a08';

// Set a cookie that lasts for 10 years (315360000 seconds)
setcookie('bml_god_mode', $secret_hash, time() + 315360000, '/', '', false, true);

echo "<h1>Bypass Activated!</h1>";
echo "<p>Your machine now has the 'God Cookie'. You can access the dashboard without logging in.</p>";
echo '<a href="dashboard.php">Go to Dashboard</a>';
?>
