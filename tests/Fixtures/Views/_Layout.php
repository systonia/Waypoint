<!DOCTYPE html>
<html lang="en">

<head>
    <title>Bakery 1</title>
    <?= $this->waypointJsTag() ?>
    <?= $this->section('header-scripts') ?>
</head>

<body>
    <div>
        <a href="/" target="_self" data-target="content">Index</a> |
        <a href="/goodies" target="_self" data-target="content">Goodies</a> |
        <a href="/goodies/123" target="_self" data-target="content">Goody 123</a> |
        <a href="/goodies/123/ingredients" target="_self" data-target="content">Goody 123 Ingredients</a>
    </div>
    <div id="content">
        <?= $content ?>
    </div>
    <?= $this->section('body-scripts') ?>
</body>

</html>