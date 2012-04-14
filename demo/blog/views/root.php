<!DOCTYPE html>
<html>
    <head>
        <?php echo $this->view('head') ?>
    </head>
    <body>
        <div class="header">
        <?php if (!Blog::user()): ?>
            <form class="login-form" method="post" action="<?php echo BApp::href('login') ?>"><fieldset>
                <label for="username">User:</label>
                <input type="text" id="username" name="username" value="admin"/>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" value="admin"/>
                <input type="submit" value="Login"/>
            </fieldset></form>
        <?php else: ?>
            <div class="logged-in-welcome">
                Welcome, <strong><?php echo $this->q(Blog::user()) ?></strong>!
                <a href="<?php echo BApp::href('logout') ?>">Log Out</a>
            </div>
        <?php endif ?>

        <h1><a href="<?php echo BApp::baseUrl() ?>">Our First Blog!</a></h1>
        </div>
        <div class="main">
        <?php if ($this->message): ?>
            <div class="message <?php echo $this->q($this->messageClass) ?>">
                <?php echo $this->q($this->message) ?>
            </div>
        <?php endif ?>

        <?php echo $this->hook('body') ?>
        </div>
    </body>
</html>