<!DOCTYPE html>
<html>
    <head>
        <?php echo $this->view('head') ?>
    </head>
    <body>
        <h1><a href="<?php echo BApp::baseUrl() ?>">Our First Blog!</a></h1>

        <?php if ($this->message): ?>
            <div class="<?php echo $this->messageClass ?>">
                <?php echo $this->q($this->message) ?>
            </div>
        <?php endif ?>

        <?php if (!Blog::user()): ?>
            <form class="login-form" method="post" action="<?php echo BApp::baseUrl().'/login' ?>"><fieldset>
                <label for="username">User (admin):</label>
                <input type="text" id="username" name="username"/>
                <label for="password">Password (admin):</label>
                <input type="password" id="password" name="password"/>
                <br/>
                <input type="submit" value="Login"/>
            </fieldset></form>
        <?php else: ?>
            Welcome, <?php echo $this->q(Blog::user()) ?>!
            <a href="<?php echo BApp::baseUrl().'/logout' ?>">Log Out</a>
        <?php endif ?>

        <?php echo $this->view('body') ?>
    </body>
</html>