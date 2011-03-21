<!DOCTYPE html>
<html>
<head>

</head>
<body>
    VIEW TEMPLATE TEST
    <div style="background:yellow"><?php echo $this->def ?></div>
    <div style="background:orange"><?php echo $this->var ?></div>
    <div style="background:magenta"><?php echo $this->param ?></div>
    <?php echo $this->view('child.php') ?>
</body>
</html>